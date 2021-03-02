<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-03-02 17:58:14
 *
 */
namespace Kovey\Rpc\Work;

use PHPUnit\Framework\TestCase;
use Kovey\Event\EventManager;
use Kovey\Rpc\Event;
use Kovey\Rpc\Protocol\Json;
use Kovey\Container\Container;
use Kovey\Rpc\App\Bootstrap\Autoload;

class HandlerTest extends TestCase
{
    public function testHandler()
    {
        $packet = $this->createMock(Json::class);
        $packet->method('getPath')
               ->willReturn('kovey');
        $packet->method('getMethod')
               ->willReturn('framework');
        $packet->method('getArgs')
               ->willReturn(array('name'));
        $packet->method('getTraceId')
               ->willReturn(hash('sha256', 123456));

        $autoload = new Autoload();
        $autoload->addLocalPath(APPLICATION_PATH . '/application');
        $autoload->register();

        $event = new EventManager(array(
            'handler' => Event\Handler::class
        ));

        $handler = new Handler('Handler');
        $handler->setContainer(new Container())
            ->setEventManager($event);

        $this->assertEquals(array(
            'err' => '',
            'type' => 'success',
            'code' => 0,
            'result' => 'name',
            'trace' => '' 
        ), $handler->run(new Event\Handler($packet, '127.0.0.1')));
    }
    
    public function testHandlerFailure()
    {
        $packet = $this->createMock(Json::class);
        $packet->method('getPath')
               ->willReturn('framework');
        $packet->method('getMethod')
               ->willReturn('framework');
        $packet->method('getArgs')
               ->willReturn(array('name'));
        $packet->method('getTraceId')
               ->willReturn(hash('sha256', 123456));
        $autoload = new Autoload();
        $autoload->addLocalPath(APPLICATION_PATH . '/application');
        $autoload->register();
        $event = new EventManager(array(
            'handler' => Event\Handler::class
        ));

        $handler = new Handler('Handler');
        $handler->setContainer(new Container())
            ->setEventManager($event);

        $this->assertEquals(array(
            'err' => 'Handler\Framework is not extends HandlerAbstract',
            'type' => 'exception',
            'code' => 1,
            'trace' => '',
            'result' => null
        ), $handler->run(new Event\Handler($packet, '127.0.0.1')));
    }
}
