<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2020-11-04 09:32:35
 *
 */
namespace Kovey\Rpc;

use PHPUnit\Framework\TestCase;
use Kovey\Rpc\App\Bootstrap\Autoload;
use Kovey\Rpc\Server\Server;
use Kovey\Container\Container;
use Kovey\Container\ContainerInterface;
use Kovey\Process\UserProcess;
use Kovey\Connection\Pool\Mysql;
use Kovey\Connection\Pool\PoolInterface;
use Kovey\Db\Adapter;
use Kovey\Library\Config\Manager;
use Kovey\Rpc\Event;
use Kovey\App\Event as AE;

class ApplicationTest extends TestCase
{
    public static function setUpBeforeClass() : void
    {
        Manager::init(APPLICATION_PATH . '/conf/');
        Application::getInstance(array('rpc' => array('handler' => 'Handler')));
    }

    public function testRegisterGetGlobal()
    {
        $this->assertInstanceOf(Application::class, Application::getInstance()->registerGlobal('test', 'kovey'));
        $this->assertInstanceOf(Application::class, Application::getInstance()->registerGlobal('http', 1));

        $this->assertEquals('kovey', Application::getInstance()->getGlobal('test'));
        $this->assertEquals(1, Application::getInstance()->getGlobal('http'));
    }

    public function testRegisterAutoload()
    {
        $autoload = new Autoload();
        $autoload->register();
        $this->assertInstanceOf(Application::class, Application::getInstance()->registerAutoload($autoload));
    }

    public function testRegisterServer()
    {
        $server = $this->createMock(Server::class);
        $server->method('on')
            ->willReturn($server);
        $this->assertInstanceOf(Application::class, Application::getInstance()->registerServer($server));
    }

    public function testRegisterPipeMessage()
    {
        Application::getInstance()->on('console', function (AE\Console $event) {
            $this->assertEquals('path', $event->getPath());
            $this->assertEquals('method', $event->getMethod());
            $this->assertEquals(array('path', 'method'), $event->getArgs());
            $this->assertEquals(hash('sha256', '123456'), $event->getTraceId());
        });

        Application::getInstance()->console(new AE\Console('path', 'method', array('path', 'method'), hash('sha256', '123456')));
    }

    public function testRegisterContainer()
    {
        $this->assertInstanceOf(Application::class, Application::getInstance()->registerContainer(new Container()));
        $this->assertInstanceOf(ContainerInterface::class, Application::getInstance()->getContainer());
    }

    public function testRegisterUserProcess()
    {
        $this->assertInstanceOf(Application::class, Application::getInstance()->registerUserProcess(new UserProcess(4)));
        $this->assertInstanceOf(UserProcess::class, Application::getInstance()->getUserProcess());
    }

    public function testRegisterPool()
    {
        $pool = new Mysql(array(
            'min' => 1,
            'max' => 2
        ), array(
            'dbname' => 'test',
            'host' => '127.0.0.1',
            'username' => 'root',
            'password' => '',
            'port' => 3306,
            'charset' => 'UTF8',
            'adapter' => Adapter::DB_ADAPTER_PDO,
            'options' => array()
        ));
        $this->assertInstanceOf(Application::class, Application::getInstance()->registerPool($pool::getWriteName(), $pool));
        $this->assertInstanceOf(PoolInterface::class, Application::getInstance()->getPool($pool::getWriteName()));
        $this->assertEquals(null, Application::getInstance()->getPool('test'));
    }

    public function tearDown() : void
    {
    }
}
