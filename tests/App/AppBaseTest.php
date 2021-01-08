<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2020-11-03 14:48:25
 *
 */
namespace Kovey\Rpc\App;

use PHPUnit\Framework\TestCase;
use Kovey\Rpc\App\Bootstrap\Autoload;
use Kovey\Container\Container;
use Kovey\Rpc\Event;
use Kovey\Rpc\Protocol\Json;

class AppBaseTest extends TestCase
{
    public function testOn()
    {
        $base = new AppBase();
        $this->assertInstanceOf(AppBase::class, $base->on('test', function () {}));
    }

    public function testSetConfig()
    {
        $base = new AppBase();
        $this->assertInstanceOf(AppBase::class, $base->setConfig(array('kovey' => 'test')));
        $this->assertEquals(array('kovey' => 'test'), $base->getConfig());
    }

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
        $autoload->register();
        $base = new AppBase();
        $base->registerAutoload($autoload)
             ->registerContainer(new Container())
             ->registerLocalLibPath(APPLICATION_PATH . '/application');

        $base->setConfig(array(
            'rpc' => array(
                'handler' => 'Handler',
                'name' => 'kovey-rpc'
            )
        ));
        $this->assertEquals(array(
            'err' => '',
            'type' => 'success',
            'code' => 0,
            'result' => 'name',
            'trace' => '' 
        ), $base->handler(new Event\Handler($packet, '127.0.0.1')));
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
        $autoload->register();
        $base = new AppBase();
        $base->registerAutoload($autoload)
             ->registerContainer(new Container())
             ->registerLocalLibPath(APPLICATION_PATH . '/application');

        $base->setConfig(array(
            'rpc' => array(
                'handler' => 'Handler',
                'name' => 'kovey-rpc'
            )
        ));
        $this->assertEquals(array(
            'err' => 'Handler\Framework is not extends HandlerAbstract',
            'type' => 'exception',
            'code' => 1,
            'trace' => '',
            'result' => null
        ), $base->handler(new Event\Handler($packet, '127.0.0.1')));
    }
}
