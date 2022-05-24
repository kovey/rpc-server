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
use Kovey\Connection\ManualCollectInterface;
use Kovey\Container\Keyword\Fields;

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
        $instance = $this->container->get($class, $event->getTraceId(), $event->getSpanId(), $keywords['ext']);
        if (!$instance instanceof HandlerAbstract) {
            return array(
                'err' => sprintf('%s is not extends HandlerAbstract', ucfirst($class)),
                'type' => 'exception',
                'code' => 1,
                'result' => null,
                'trace' => ''
            );
        }

        try {
            $instance->init();
            $instance->setClientIp($event->getClientIP());
            $instance->getStack()->setTraceId($event->getTraceId());

            if ($keywords[Fields::KEYWORD_OPEN_TRANSACTION]) {
                $keywords[Fields::KEYWORD_DATABASE]->beginTransaction();
                try {
                    $result = call_user_func(array($instance, $event->getMethod()), ...$event->getArgs());
                    $keywords[Fields::KEYWORD_DATABASE]->commit();
                } catch (\Throwable $e) {
                    $keywords[Fields::KEYWORD_DATABASE]->rollBack();
                    throw $e;
                }
            } else {
                $result = call_user_func(array($instance, $event->getMethod()), ...$event->getArgs());
            }
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            foreach ($keywords as $value) {
                if (!$value instanceof ManualCollectInterface) {
                    continue;
                }

                $value->collect();
            }
            $instance->getStack()->write();
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
