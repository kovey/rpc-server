<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-02-26 16:44:39
 *
 */
namespace Kovey\Rpc\Work;

use Kovey\App\Components\Work;
use Kovey\Event\EventInterface;
use Kovey\Rpc\Handler\HandlerAbstract;

class Handler extends Work
{
    private string $handler;

    public function __construct(string $handler)
    {
        $this->handler = $handler;
    }

    public function run(EventInterface $event) : Array
    {
        $class = $this->handler . '\\' . ucfirst($event->getClass());
        $keywords = $this->container->getKeywords($class, $event->getMethod());
        $instance = $this->container->get($class, $event->getTraceId(), $keywords['ext']);
        if (!$instance instanceof HandlerAbstract) {
            return array(
                'err' => sprintf('%s is not extends HandlerAbstract', ucfirst($class)),
                'type' => 'exception',
                'code' => 1,
                'result' => null,
                'trace' => ''
            );
        }

        $instance->setClientIp($event->getClientIP());

        if ($keywords['openTransaction']) {
            $keywords['database']->getConnection()->beginTransaction();
            try {
                $result = call_user_func(array($instance, $event->getMethod()), ...$event->getArgs());
                $keywords['database']->getConnection()->commit();
            } catch (\Throwable $e) {
                $keywords['database']->getConnection()->rollBack();
                throw $e;
            }
        } else {
            $result = call_user_func(array($instance, $event->getMethod()), ...$event->getArgs());
        }

        return array(
            'err' => '',
            'type' => 'success',
            'code' => 0,
            'result' => $result,
            'trace' => ''
        );
    }
}
