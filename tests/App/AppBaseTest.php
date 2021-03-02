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
        $base = new AppBase(array('rpc' => array('handler' => 'Handler')));
        $this->assertInstanceOf(AppBase::class, $base->on('test', function () {}));
    }

    public function testSetConfig()
    {
        $base = new AppBase(array('rpc' => array('handler' => 'Handler')));
        $this->assertInstanceOf(AppBase::class, $base->setConfig(array('kovey' => 'test')));
        $this->assertEquals(array('kovey' => 'test'), $base->getConfig());
    }
}
