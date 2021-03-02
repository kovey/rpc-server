<?php
/**
 * @description server
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
use Kovey\App\Components\ServerAbstract;
use Kovey\Rpc\Event;

class Server extends ServerAbstract
{
    /**
     * @description rpc port
     *
     * @var Swoole\Server\Port
     */
    private Port $port;

    /**
     * @description init server
     *
     * @return Server
     */
    protected function initServer() : Server
    {
        if ($this->config['test_open'] !== 'On') {
            $this->serv = new \Swoole\Server($this->config['host'], $this->config['port'], SWOOLE_BASE);
            $this->serv->set(array(
                'open_length_check' => true,
                'package_max_length' => ProtocolInterface::MAX_LENGTH,
                'package_length_type' => ProtocolInterface::PACK_TYPE,
                'package_length_offset' => ProtocolInterface::LENGTH_OFFSET,
                'package_body_offset' => ProtocolInterface::BODY_OFFSET,
                'enable_coroutine' => true,
                'worker_num' => $this->config['worker_num'],
                'max_coroutine' => $this->config['max_co'],
                'daemonize' => !$this->isRunDocker,
                'pid_file' => $this->config['pid_file'],
                'log_file' => $this->config['logger_dir'] . '/server/server.log',
                'event_object' => true,
                'log_rotation' => SWOOLE_LOG_ROTATION_DAILY,
                'log_date_format' => '%Y-%m-%d %H:%M:%S'
            ));

            $this->serv->on('connect', array($this, 'connect'));
            $this->serv->on('receive', array($this, 'receive'));
            $this->serv->on('close', array($this, 'close'));
            
            $this->event->addSupportEvents(array(
                'handler' => Event\Handler::class,
                'unpack' => Event\Unpack::class,
                'pack' => Event\Pack::class
            ));
            return $this;
        }

        $this->serv = new \Swoole\Http\Server($this->config['host'], $this->config['port'] + 10000);
        $this->serv->set(array(
            'daemonize' => !$this->isRunDocker,
            'http_compression' => true,
            'enable_static_handler' => true,
            'pid_file' => $this->config['pid_file'],
            'log_file' => $this->config['logger_dir'] . '/server/server.log',
            'worker_num' => $this->config['worker_num'],
            'enable_coroutine' => true,
            'max_coroutine' => $this->config['max_co'],
            'event_object' => true,
            'log_rotation' => SWOOLE_LOG_ROTATION_DAILY,
            'log_date_format' => '%Y-%m-%d %H:%M:%S'
        ));

        $this->serv->on('request', array($this, 'request'));
        $this->port = new Port($this->serv, $this->config);
        $this->event->addSupportEvents(array(
            'run_action' => Event\RunAction::class
        ));
        return $this;
    }

    /**
     * @description connect event
     *
     * @param Swoole\Server $serv
     *
     * @param Swoole\Server\Event $event
     *
     * @return void
     */
    public function connect(\Swoole\Server $serv, \Swoole\Server\Event $event) : void
    {
    }

    /**
     * @description receive event
     *
     * @param Swoole\Server $serv
     *
     * @param Swoole\Server\Event $event
     *
     * @return void
     */
    public function receive(\Swoole\Server $serv, \Swoole\Server\Event $event) : void
    {
        $business = new Business($event, $this->config);
        $business->begin($this)
                 ->run($this->event)
                 ->end($this)
                 ->monitor($this);
    }

    /**
     * @description http request
     *
     * @param Swoole\Http\Request $request
     *
     * @param Swoole\Http\Response $response
     *
     * @return void
     */
    public function request(\Swoole\Http\Request $request, \Swoole\Http\Response $response) : void
    {
        $business = new HttpBusi($request, $response);
        $business->begin()
                 ->run($this->event)
                 ->end();
    }

    /**
     * @description send data to client
     *
     * @param Array $packet
     *
     * @param int $fd
     *
     * @return bool
     */
    public function send(Array $packet, int $fd) : bool
    {
        if (!$this->serv->exist($fd)) {
            return false;
        }

        $data = false;
        if ($this->event->listened('pack')) {
            $data = $this->event->dispatchWithReturn(new Event\Pack($packet, $this->config['secret_key'], $this->config['encrypt_type'] ?? 'aes'));
        } else {
            $data = Json::pack($packet, $this->config['secret_key'], $this->config['encrypt_type'] ?? 'aes');
        }
        if (!$data) {
            return false;
        }

        $len = strlen($data);
        if ($len <= ProtocolInterface::MAX_LENGTH) {
            return $this->serv->send($fd, $data);
        }

        $sendLen = 0;
        while ($sendLen < $len) {
            $this->serv->send($fd, substr($data, $sendLen, ProtocolInterface::MAX_LENGTH));
            $sendLen += ProtocolInterface::MAX_LENGTH;
        }

        return true;
    }

    /**
     * @description close connection
     *
     * @param Swoole\Server $serv
     *
     * @param Swoole\Server\Event $event
     *
     * @return void
     */
    public function close(\Swoole\Server $serv, \Swoole\Server\Event $event) : void
    {
    }
}
