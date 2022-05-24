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

use Kovey\Logger\Trace\Stack;

abstract class HandlerAbstract
{
    protected string $clientIp;

    protected Stack $stack;

    public function __construct() 
    {
        $this->stack = $stack;
    }

    protected function init() : void
    {
    }

    public function setClientIp(string $clientIp)
    {
        $this->clientIp = $clientIp;
    }

    public function getStack() : Stack
    {
        return $this->stack;
    }
}
