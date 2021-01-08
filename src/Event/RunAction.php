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
use Swoole\Http\Request;

class RunAction implements EventInterface
{
    private Request $request;

    private string $traceId;

    public function __construct(Request $request, string $traceId)
    {
        $this->traceId = $traceId;
        $this->request = $request;
    }

    public function getRequest() : Request
    {
        return $this->request;
    }

    public function getTraceId() : Array
    {
        return $this->packet->getTraceId();
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
