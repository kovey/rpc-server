<?php
/**
 * @description 短连接服务端
 *
 * @package Server
 *
 * @author kovey
 *
 * @time 2019-11-13 14:43:19
 *
 */
namespace Kovey\Rpc\Server;

use Kovey\Rpc\Protocol\Json;
use Kovey\Library\Protocol\ProtocolInterface;
use Kovey\Library\Exception\BusiException;
use Kovey\Library\Exception\KoveyException;
use Kovey\Library\Exception\ProtocolException;
use Kovey\Logger\Logger;
use Kovey\Library\Server\PortInterface;
use Swoole\Server\Port;

class Server implements PortInterface
{
	/**
	 * @description 服务器
	 *
	 * @var Swoole\Server | Swoole\Http\Server
	 */
    private \Swoole\Server | \Swoole\Http\Server $serv;

    /**
     * @description rpc服务监听
     *
     * @var Swoole\Server\Port
     */
    private Port $port;

	/**
	 * @description 配置
	 *
	 * @var Array
	 */
    private Array $conf;

	/**
	 * @description 事件
	 *
	 * @var Array
	 */
	private Array $events;

	/**
	 * @description 允许的事件
	 *
	 * @var Array
	 */
	private Array $allowEvents;

	/**
	 * @description 是否运行在docker中
	 *
	 * @var bool
	 */
	private bool $isRunDocker;

	/**
	 * @description 构造函数
	 *
	 * @param Array $conf
	 *
	 * @return Server
	 */
    public function __construct(Array $conf)
    {
        $this->conf = $conf;
		$this->isRunDocker = ($this->conf['run_docker'] ?? 'Off') === 'On';
		$this->initAllowEvents()
            ->initServer()
            ->initCallback()
            ->initLog();

    }

    /**
     * @description 初始化服务
     *
     * @return Server
     */
    private function initServer() : Server
    {
        if ($this->conf['test_open'] !== 'On') {
            $this->serv = new \Swoole\Server($this->conf['host'], $this->conf['port'], SWOOLE_BASE);
            $this->serv->set(array(
                'open_length_check' => true,
                'package_max_length' => ProtocolInterface::MAX_LENGTH,
                'package_length_type' => ProtocolInterface::PACK_TYPE,
                'package_length_offset' => ProtocolInterface::LENGTH_OFFSET,
                'package_body_offset' => ProtocolInterface::BODY_OFFSET,
                'enable_coroutine' => true,
                'worker_num' => $this->conf['worker_num'],
                'max_coroutine' => $this->conf['max_co'],
                'daemonize' => !$this->isRunDocker,
                'pid_file' => $this->conf['pid_file'],
                'log_file' => $this->conf['log_file'],
            ));

            $this->serv->on('connect', array($this, 'connect'));
            $this->serv->on('receive', array($this, 'receive'));
            $this->serv->on('close', array($this, 'close'));
            return $this;
        }

        $this->serv = new \Swoole\Http\Server($this->conf['host'], $this->conf['port'] + 10000);
        $this->serv->set(array(
            'daemonize' => !$this->isRunDocker,
			'http_compression' => true,
			'enable_static_handler' => true,
            'pid_file' => $this->conf['pid_file'],
            'log_file' => $this->conf['log_file'],
            'worker_num' => $this->conf['worker_num'],
			'enable_coroutine' => true,
			'max_coroutine' => $this->conf['max_co']
        ));
        $this->serv->on('request', array($this, 'request'));

        $port = $this->serv->listen($this->conf['host'], $this->conf['port'], SWOOLE_SOCK_TCP);
        $port->set(array(
            'open_length_check' => true,
            'package_max_length' => ProtocolInterface::MAX_LENGTH,
            'package_length_type' => ProtocolInterface::PACK_TYPE,
            'package_length_offset' => ProtocolInterface::LENGTH_OFFSET,
            'package_body_offset' => ProtocolInterface::BODY_OFFSET,
        ));

        $port->on('connect', array($this, 'connect'));
        $port->on('receive', array($this, 'receive'));
        $port->on('close', array($this, 'close'));

        $this->port = $port;

        return $this;
    }

    /**
     * @description 初始化LOG
     *
     * @return Server
     */
    private function initLog() : Server
    {
		$logDir = dirname($this->conf['log_file']);
		if (!is_dir($logDir)) {
			mkdir($logDir, 0777, true);
		}
		$pidDir = dirname($this->conf['pid_file']);
		if (!is_dir($pidDir)) {
			mkdir($pidDir, 0777, true);
		}

        return $this;
    }

	/**
	 * @description 初始化允许的事件
	 *
	 * @return Server
	 */
	private function initAllowEvents() : Server
	{
		$this->allowEvents = array(
			'handler' => 1,
			'pipeMessage' => 1,
			'initPool' => 1,
            'monitor' => 1,
            'run_action' => 1,
            'unpack' => 1,
            'pack' => 1
		);

		return $this;
	}

	/**
	 * @description 初始化回调
	 *
	 * @return Server
	 */
    private function initCallback() : Server
    {
        $this->serv->on('pipeMessage', array($this, 'pipeMessage'));
        $this->serv->on('workerStart', array($this, 'workerStart'));
        $this->serv->on('managerStart', array($this, 'managerStart'));
		return $this;
    }

	/**
	 * @description manager 启动回调
	 *
	 * @param Swoole\Server $serv
	 *
	 * @return null
	 */
    public function managerStart($serv)
    {
        ko_change_process_name($this->conf['name'] . ' master');
    }

	/**
	 * @description worker 启动回调
	 *
	 * @param Swoole\Server $serv
	 *
	 * @param int $workerId
	 *
	 * @return null
	 */
    public function workerStart($serv, $workerId)
    {
        ko_change_process_name($this->conf['name'] . ' worker');

		if (!isset($this->events['initPool'])) {
			return;
		}

		try {
			call_user_func($this->events['initPool'], $this);
		} catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
		}
    }

	/**
	 * @description 添加事件
	 *
	 * @param string $events
	 *
	 * @param callable $cal
	 *
     * @return PortInterface
     *
     * @throws Exception
	 */
	public function on(string $event, $call) : PortInterface
	{
		if (!isset($this->allowEvents[$event])) {
            throw new KoveyException('event: "' . $event . '" is not allow');
		}

		if (!is_callable($call)) {
            throw new KoveyException('callback is not callable');
		}

		$this->events[$event] = $call;
		return $this;
	}

	/**
	 * @description 管道事件回调
	 *
	 * @param Swoole\Server $serv
	 *
	 * @param int $workerId
	 *
	 * @param mixed $data
	 *
	 * @return null
	 */
    public function pipeMessage($serv, $workerId, $data)
    {
        try {
			if (!isset($this->events['pipeMessage'])) {
				return;
			}

			call_user_func($this->events['pipeMessage'], $data['p'] ?? '', $data['m'] ?? '', $data['a'] ?? array(), $data['t'] ?? '');
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $data['t'] ?? '');
		}
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
        try {
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
        } catch (ProtocolException $e) {
            $this->send(array(
                'err' => $e->getMessage() . PHP_EOL . $e->getTraceAsString(),
                'type' => 'protocol_exception',
                'code' => $e->getCode(),
                'packet' => $data
            ), $fd);
            $serv->close($fd);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
            return;
        } catch (KoveyException $e) {
            $this->send(array(
                'err' => $e->getMessage() . PHP_EOL . $e->getTraceAsString(),
                'type' => 'kovey_exception',
                'code' => $e->getCode(),
                'packet' => $data
            ), $fd);
            $serv->close($fd);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
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
					'type' => 'kovey_exception',
					'code' => 1000,
					'packet' => $packet->getClear()
				), $fd);
				return;
			}

			$result = call_user_func($this->events['handler'], $packet->getPath(), $packet->getMethod(), $packet->getArgs(), $packet->getTraceId());
			if ($result['code'] > 0) {
				$result['packet'] = $packet->getClear();
			}
		} catch (BusiException $e) {
            $result = array(
                'err' => $e->getMessage() . PHP_EOL . $e->getTraceAsString(),
                'type' => 'busi_exception',
                'code' => $e->getCode(),
                'packet' => $packet->getClear()
            );
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $packet->getTraceId());
        } catch (KoveyException $e) {
            $result = array(
                'err' => $e->getMessage() . PHP_EOL . $e->getTraceAsString(),
                'type' => 'kovey_exception',
                'code' => $e->getCode(),
                'packet' => $packet->getClear()
            );
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $packet->getTraceId());
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $packet->getTraceId());
            $result = array(
                'err' => $e->getMessage() . PHP_EOL . $e->getTraceAsString(),
                'type' => 'exception',
                'code' => 1000,
                'packet' => $packet->getClear()
            );
        }

        $this->send($result, $fd);
        go (function ($begin, $packet, $reqTime, $result, $fd) {
            $end = microtime(true);
            $this->monitor($begin, $end, $packet, $reqTime, $result, $fd);
        }, $begin, $packet, $reqTime, $result, $fd);
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
                'request_time' => $begin * 10000,
				'type' => $result['type'],
				'err' => $result['err'],
				'service' => $this->conf['name'],
				'class' => $packet->getPath(),
				'method' => $packet->getMethod(),
				'args' => $packet->getArgs(),
				'ip' => $this->getClientIP($fd),
				'time' => $reqTime,
				'timestamp' => date('Y-m-d H:i:s', $reqTime),
                'minute' => date('YmdHi', $reqTime),
                'response' => $result['result'] ?? null,
                'traceId' => $packet->getTraceId()
			));
		} catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
		}
	}

	/**
	 * @description http 请求
	 *
	 * @param Swoole\Http\Request $request
	 *
	 * @param Swoole\Http\Response $response
	 *
	 * @return null
	 */
    public function request($request, $response)
	{
		if (!isset($this->events['run_action'])) {
			$response->status(500);
			$response->header('content-type', 'text/html');
			$response->end(ErrorTemplate::getContent(500));
			return;
		}

        $traceId = hash('sha256', uniqid($request->server['request_uri'], true) . random_int(1000000, 999999));
		$result = array();
		try {
			$result = call_user_func($this->events['run_action'], $request, $traceId);
		} catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $traceId);
			$result = array(
				'httpCode' => 500,
				'header' => array(
					'content-type' => 'text/html'
				),
				'content' => ErrorTemplate::getContent(500),
				'cookie' => array()
			);
		}

		$httpCode = $result['httpCode'] ?? 500;
		$response->status($httpCode);

		$header = $result['header'] ?? array();
		foreach ($header as $k => $v) {
			$response->header($k, $v);
		}
        $response->header('Request-Id', $traceId);

		$cookie = $result['cookie'] ?? array();
		foreach ($cookie as $cookie) {
			$response->header('Set-Cookie', $cookie);
		}

        $response->end($httpCode == 200 ? $result['content'] : ErrorTemplate::getContent($httpCode));
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

	/**
	 * @description 启动服务
	 *
	 * @return null
	 */
    public function start()
    {
        $this->serv->start();
    }

	/**
	 * @description 获取底层服务
	 *
	 * @return Swoole\Server | Swoole\Http\Server
	 */
	public function getServ()
	{
		return $this->serv;
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
