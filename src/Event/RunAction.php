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

    private string $spanId;

    public function __construct(Request $request, string $traceId, string $spanId)
    {
        $this->traceId = $traceId;
        $this->request = $request;
        $this->spanId = $spanId;
    }

    public function getRequest() : Request
    {
        return $this->request;
    }

    public function getTraceId() : string
    {
        return $this->traceId;
    }

    public function getSpanId() : string
    {
        return $this->spanId;
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
