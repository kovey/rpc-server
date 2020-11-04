<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2020-11-03 18:03:42
 *
 */
namespace Handler;

use Kovey\Rpc\Handler\HandlerAbstract;

class Kovey extends HandlerAbstract
{
    public function framework($name)
    {
        return $name;
    }
}
