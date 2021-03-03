<?php
/**
 * @description Rpc Port
 *
 * @package
 *
 * @author kovey
 *
 * @time 2020-03-21 20:27:42
 *
 */
namespace Kovey\Rpc\Server;

use Kovey\Rpc\Protocol\Json;
use Kovey\App\Components\PortAbstract;
use Kovey\Library\Protocol\ProtocolInterface;
use Kovey\Rpc\Event;

class Port extends PortAbstract
{
    /**
     * @description init
     *
     * @return void
     */
    protected function init() : void
    {
        $this->event->addSupportEvents(array(
            'handler' => Event\Handler::class,
            'unpack' => Event\Unpack::class,
            'pack' => Event\Pack::class
        ));

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
     * @description connect callback
     *
     * @param Swoole\Server $serv
     *
     * @param int $fd
     *
     * @return void
     */
    public function connect(\Swoole\Server $serv, \Swoole\Server\Event $event) : void
    {
    }

    /**
     * @description receive callback
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
            $event = new Event\Pack($packet, $this->config['secret_key'], $this->config['encrypt_type'] ?? 'aes');
            $data = $this->event->dispatchWithReturn($event);
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
    
    public function on(string $type, callable | Array $callback) : ServerInterface
    {
        if ($this->config['test_open'] === 'On') {
            $this->port->on($type, $callback);
        }

        return parent::on($type, $callback);
    }
}
