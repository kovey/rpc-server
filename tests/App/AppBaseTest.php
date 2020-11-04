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
use Kovey\Library\Container\Container;

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
            'result' => 'name'
        ), $base->handler('kovey', 'framework', array('name'), hash('sha256', 123456)));
    }
    
    public function testHandlerFailure()
    {
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
            'err' => 'Framework is not extends HandlerAbstract',
            'type' => 'exception',
            'code' => 1,
        ), $base->handler('Framework', 'framework', array('name'), hash('sha256', 123456)));
    }
}
