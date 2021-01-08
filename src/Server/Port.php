<?php
/**
 * @description Rpc服务端口
 *
 * @package
 *
 * @author kovey
 *
 * @time 2020-03-21 20:27:42
 *
 */
namespace Kovey\Rpc\Server;

use Kovey\Library\Server\Base;
use Kovey\Library\Protocol\ProtocolInterface;
use Kovey\Rpc\Protocol\Json;
use Kovey\Library\Exception\BusiException;
use Kovey\Library\Exception\KoveyException;
use Kovey\Library\Exception\ProtocolException;
use Kovey\Library\Server\PortInterface;
use Kovey\Logger\Logger;
use Kovey\Rpc\Event;
use Kovey\Event\Dispatch;
use Kovey\Event\Listener\Listener;
use Kovey\Event\Listener\ListenerProvider;

class Port implements PortInterface
{
    const TCP_PORT = 1;

    /**
     * @description 服务器
     *
     * @var Swoole\Server
     */
    protected \Swoole\Server $serv;

    /**
     * @description 端口
     *
     * @var Swoole\Server\Port
     */
    protected \Swoole\Server\Port $port;

    /**
     * @description 监听的事件
     *
     * @var Array
     */
    protected Array $onEvents;

    /**
     * @description 配置
     *
     * @var Array
     */
    protected Array $conf;
    
    /**
     * @description 允许监听的事件
     */
    protected Array $allowEvents = array(
        'monitor' => Event\Monitor::class,
        'handler' => Event\Handler::class,
        'unpack' => Event\Unpack::class,
        'pack' => Event\Pack::class
    );

    private Dispatch $dispatch;

    private ListenerProvider $provider;

    /**
     * @description 构造
     *
     * @param Server $serv
     *
     * @param Array $conf
     *
     * @param int $type
     *
     * @return Base
     */
    final public function __construct(\Swoole\Server $serv, Array $conf, int $type = self::TCP_PORT)
    {
        $this->serv = $serv;
        $this->port = $this->serv->listen($conf['host'], $conf['port'], $type == self::TCP_PORT ? SWOOLE_SOCK_TCP : SWOOLE_SOCK_UDP);
        $this->onEvents = array();
        $this->conf = $conf;
        $this->provider = new ListenerProvider();
        $this->dispatch = new Dispatch($this->provider);
        $this->init();
    }

    /**
     * @description 事件监听
     *
     * @param string $event
     *
     * @param callable $callable
     *
     * @return PortInterface
     *
     * @return throws
     */
    public function on(string $event, callable | Array $callable) : PortInterface
    {
        if (!$this->isAllow($event)) {
            throw new KoveyException('unknown event: ' . $event);
        }

        if (!is_callable($callable)) {
            throw new KoveyException('callback can not callable');
        }

        $listener = new Listener();
        $listener->addEvent($this->allowEvents[$event], $callable);
        $this->provider->addListener($listener);
        $this->onEvents[$event] = $event;
        return $this;
    }

    /**
     * @description 初始化
     *
     * @return mixed
     */
    protected function init()
    {
        $this->port->set(array(
            'open_length_check' => true,
            'package_max_length' => ProtocolInterface::MAX_LENGTH,
            'package_length_type' => ProtocolInterface::PACK_TYPE,
            'package_length_offset' => ProtocolInterface::LENGTH_OFFSET,
            'package_body_offset' => ProtocolInterface::BODY_OFFSET,
            'event_object' => true
        ));

        $this->port->on('connect', array($this, 'connect'));
        $this->port->on('receive', array($this, 'receive'));
        $this->port->on('close', array($this, 'close'));
    }

    /**
     * @description 是否允许监听事件
     *
     * @param string $event
     *
     * @return bool
     */
    protected function isAllow(string $event) : bool
    {
        return isset($this->allowEvents[$event]);
    }

    /**
     * @description 链接回调
     *
     * @param Swoole\Server $serv
     *
     * @param int $fd
     *
     * @return null
     */
    public function connect(\Swoole\Server $serv, \Swoole\Server\Event $event)
    {
    }

    /**
     * @description 接收回调
     *
     * @param Swoole\Server $serv
     *
     * @param Swoole\Server\Event $event
     *
     * @return null
     */
    public function receive(\Swoole\Server $serv, \Swoole\Server\Event $event)
    {
        $proto = null;
        try {
            if (isset($this->onEvents['unpack'])) {
                $proto = $this->dispatch->dispatchWithReturn(new Event\Unpack($event->data, $this->conf['secret_key'], $this->conf['encrypt_type'] ?? 'aes'));
                if (!$proto instanceof ProtocolInterface) {
                    $this->send(array(
                        'err' => 'parse data error',
                        'type' => 'exception',
                        'trace' => '',
                        'code' => 1000,
                        'packet' => $event->data
                    ), $event->fd);
                    $serv->close($event->fd);
                    return;
                }
            } else {
                $proto = new Json($event->data, $this->conf['secret_key'], $this->conf['encrypt_type'] ?? 'aes');
            }

            if (!$proto->parse()) {
                $this->send(array(
                    'err' => 'parse data error',
                    'type' => 'exception',
                    'trace' => '',
                    'code' => 1000,
                    'packet' => $event->data
                ), $event->fd);
                $serv->close($event->fd);
                return;
            }
        } catch (ProtocolException $e) {
            $this->send(array(
                'err' => $e->getMessage(),
                'type' => 'protocol_exception',
                'trace' => $e->getTraceAsString(),
                'code' => $e->getCode(),
                'packet' => $event->data
            ), $event->fd);
            $serv->close($event->fd);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
            return;
        } catch (KoveyException $e) {
            $this->send(array(
                'err' => $e->getMessage(),
                'type' => 'kovey_exception',
                'trace' => $e->getTraceAsString(),
                'code' => $e->getCode(),
                'packet' => $event->data
            ), $event->fd);
            $serv->close($event->fd);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
            return;
        }

        $this->handler($proto, $event->fd);

        $serv->close($event->fd);
    }

    /**
     * @description Hander 处理
     *
     * @param ProtocolInterface $packet
     *
     * @param int $fd
     *
     * @return null
     */
    private function handler(ProtocolInterface $packet, $fd)
    {
        $begin = microtime(true);
        $reqTime = time();
        $result = null;

        try {
            $event = new Event\Handler($packet, $this->getClientIP($fd));
            $result = $this->dispatch->dispatchWithReturn($event);
            if ($result['code'] > 0) {
                $result['packet'] = $packet->getClear();
            }
        } catch (BusiException $e) {
            $result = array(
                'err' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'type' => 'busi_exception',
                'code' => $e->getCode(),
                'packet' => $packet->getClear()
            );
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $packet->getTraceId());
        } catch (KoveyException $e) {
            $result = array(
                'err' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'type' => 'kovey_exception',
                'code' => $e->getCode(),
                'packet' => $packet->getClear()
            );
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $packet->getTraceId());
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $packet->getTraceId());
            $result = array(
                'err' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'type' => 'exception',
                'code' => 1000,
                'packet' => $packet->getClear()
            );
        }

        $this->send($result, $fd);
        $end = microtime(true);
        if (!isset($this->conf['monitor_open']) || $this->conf['monitor_open'] !== 'Off') {
            $this->monitor($begin, $end, $packet, $reqTime, $result, $fd);
        }
    }

    /**
     * @description 监控
     *
     * @param float $begin
     *
     * @param float $end
     *
     * @param ProtocolInterface $packet
     *
     * @param int $reqTime
     *
     * @param Array $result
     *
     * @param int $fd
     *
     * @return null
     */
    private function monitor(float $begin, float $end, ProtocolInterface $packet, int $reqTime, Array $result, $fd)
    {
        try {
            $event = new Event\Monitor(array(
                'delay' => round(($end - $begin) * 1000, 2),
                'request_time' => $begin * 10000,
                'type' => $result['type'],
                'err' => $result['err'],
                'trace' => $result['trace'],
                'service' => $this->conf['name'],
                'service_type' => 'rpc',
                'class' => $packet->getPath(),
                'method' => $packet->getMethod(),
                'args' => $packet->getArgs(),
                'ip' => $this->getClientIP($fd),
                'time' => $reqTime,
                'timestamp' => date('Y-m-d H:i:s', $reqTime),
                'minute' => date('YmdHi', $reqTime),
                'response' => $result['result'] ?? null,
                'traceId' => $packet->getTraceId(),
                'from' => $packet->getFrom(),
                'end' => $end * 10000
            ));
            $this->dispatch->dispatch($event);
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        }
    }

    /**
     * @description 发送数据
     *
     * @param Array $packet
     *
     * @param int $fd
     *
     * @return bool
     */
    private function send(Array $packet, $fd) : bool
    {
        if (!$this->serv->exist($fd)) {
            return false;
        }

        $data = false;
        if (isset($this->onEvents['pack'])) {
            $event = new Event\Pack($packet, $this->conf['secret_key'], $this->conf['encrypt_type'] ?? 'aes');
            $data = $this->dispatch->dispatchWithReturn($event);
        } else {
            $data = Json::pack($packet, $this->conf['secret_key'], $this->conf['encrypt_type'] ?? 'aes');
        }

        if (!$data) {
            return false;
        }

        $len = strlen($data);
        if ($len <= self::PACKET_MAX_LENGTH) {
            return $this->serv->send($fd, $data);
        }

        $sendLen = 0;
        while ($sendLen < $len) {
            $this->serv->send($fd, substr($data, $sendLen, self::PACKET_MAX_LENGTH));
            $sendLen += self::PACKET_MAX_LENGTH;
        }

        return true;
    }

    /**
     * @description 关闭链接
     *
     * @param Swoole\Server $serv
     *
     * @param int $fd
     *
     * @return null
     */
    public function close($serv, $fd)
    {
    }

    /**
     * @description get ip
     *
     * @param int $fd
     *
     * @return string
     */
    public function getClientIP($fd) : string
    {
        $info = $this->serv->getClientInfo($fd);
        if (empty($info)) {
            return '';
        }

        return $info['remote_ip'] ?? '';
    }
}
