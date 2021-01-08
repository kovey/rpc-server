<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-01-08 10:02:48
 *
 */
namespace Kovey\Rpc\Event;

use Kovey\Event\EventInterface;
use Kovey\Library\Protocol\ProtocolInterface;

class Handler implements EventInterface
{
    private ProtocolInterface $packet;

    private string $clientIp;

    public function __construct(ProtocolInterface $packet, string $clientIp)
    {
        $this->clientIp = $clientIp;
        $this->packet = $packet;
    }

    public function getClass() : string
    {
        return $this->packet->getPath();
    }

    public function getMethod() : string
    {
        return $this->packet->getMethod();
    }

    public function getArgs() : Array
    {
        return $this->packet->getArgs();
    }

    public function getTraceId() : Array
    {
        return $this->packet->getTraceId();
    }

    public function getClientIP() : string
    {
        return $this->clientIp;
    }

    /**
     * @description propagation stopped
     *
     * @return bool
     */
    public function isPropagationStopped() : bool
    {
        return true;
    }

    /**
     * @description stop propagation
     *
     * @return EventInterface
     */
    public function stopPropagation() : EventInterface
    {
        return $this;
    }
}
