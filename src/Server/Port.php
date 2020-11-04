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
use Kovey\Library\Logger\Logger;

class Port extends Base
{
    /**
     * @description 允许监听的事件
     */
    protected Array $allowEvents = array(
        'monitor' => 1,
        'handler' => 1,
        'unpack' => 1,
        'pack' => 1
    );

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
    public function connect($serv, $fd)
    {
    }

	/**
	 * @description 接收回调
	 *
	 * @param Swoole\Server $serv
	 *
	 * @param int $fd
	 *
	 * @param int $reactor_id
	 *
	 * @param mixed $data
	 *
	 * @return null
	 */
    public function receive($serv, $fd, $reactor_id, $data)
    {
        $proto = null;
        if (isset($this->events['unpack'])) {
            $proto = call_user_func($this->events['unpack'], $data, $this->conf['secret_key'], $this->conf['encrypt_type'] ?? 'aes');
            if (!$proto instanceof ProtocolInterface) {
                $this->send(array(
                    'err' => 'parse data error',
                    'type' => 'exception',
                    'code' => 1000,
                    'packet' => $data
                ), $fd);
                $serv->close($fd);
                return;
            }
        } else {
            $proto = new Json($data, $this->conf['secret_key'], $this->conf['encrypt_type'] ?? 'aes');
        }

		if (!$proto->parse()) {
			$this->send(array(
				'err' => 'parse data error',
				'type' => 'exception',
				'code' => 1000,
				'packet' => $data
			), $fd);
            $serv->close($fd);
			return;
		}

        $this->handler($proto, $fd);

        $serv->close($fd);
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
			if (!isset($this->events['handler'])) {
				$this->send(array(
					'err' => 'handler events is not register',
					'type' => 'exception',
					'code' => 1000,
					'packet' => $packet->getClear()
				), $fd);
				return;
			}

			$result = call_user_func($this->events['handler'], $packet->getPath(), $packet->getMethod(), $packet->getArgs());
			if ($result['code'] > 0) {
				$result['packet'] = $packet->getClear();
			}
		} catch (BusiException $e) {
            $result = array(
                'err' => $e->getMessage(),
                'type' => 'busi_exception',
                'code' => $e->getCode(),
                'packet' => $packet->getClear()
            );
        } catch (KoveyException $e) {
            $result = array(
                'err' => $e->getMessage(),
                'type' => 'kovey_exception',
                'code' => $e->getCode(),
                'packet' => $packet->getClear()
            );
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
            $result = array(
                'err' => $e->getMessage() . PHP_EOL . $e->getTraceAsString(),
                'type' => 'exception',
                'code' => 1000,
                'packet' => $packet->getClear()
            );
        }

        $this->send($result, $fd);
		$end = microtime(true);
		$this->monitor($begin, $end, $packet, $reqTime, $result, $fd);
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
		if (!isset($this->events['monitor'])) {
			return;
		}

		try {
			call_user_func($this->events['monitor'], array(
				'delay' => round(($end - $begin) * 1000, 2),
				'type' => $result['type'],
				'err' => $result['err'],
				'service' => $this->conf['name'],
				'class' => $packet->getPath(),
				'method' => $packet->getMethod(),
				'args' => $packet->getArgs(),
				'ip' => $this->serv->getClientInfo($fd)['remote_ip'],
				'time' => $reqTime,
				'timestamp' => date('Y-m-d H:i:s', $reqTime),
                'minute' => date('YmdHi', $reqTime),
                'traceId' => $packet->getTraceId()
			));
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
        if (isset($this->events['pack'])) {
            $data = call_user_func($this->events['pack'], $packet, $this->conf['secret_key'], $this->conf['encrypt_type'] ?? 'aes');
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
}
