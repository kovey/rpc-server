<?php
/**
 * @description handler abstract
 *
 * @package
 *
 * @author kovey
 *
 * @time 2019-11-14 22:58:02
 *
 */
namespace Kovey\Rpc\Handler;

use Kovey\Logger\Trace\StackInterface;
use Kovey\Logger\Trace\Stack;

abstract class HandlerAbstract
{
    protected string $clientIp;

    protected StackInterface $stack;

    public function init() : void
    {
        $this->stack = new Stack();
    }

    public function setClientIp(string $clientIp)
    {
        $this->clientIp = $clientIp;
    }

    public function getStack() : ?StackInterface
    {
        return $this->stack;
    }
}
