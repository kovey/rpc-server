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

class Pack implements EventInterface
{
    private Array $packet;

    private string $key;

    private string $type;

    private int $compress;

    public function __construct(Array $packet, string $key, string $type, int $compress)
    {
        $this->packet = $packet;
        $this->key = $key;
        $this->type = $type;
        $this->compress = $compress;
    }

    public function getPacket() : Array
    {
        return $this->packet;
    }

    public function getKey() : string
    {
        return $this->key;
    }

    public function getType() : string
    {
        return $this->type;
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

    public function getCompress() : int
    {
        return $this->compress;
    }
}
