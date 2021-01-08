<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2020-11-04 09:46:49
 *
 */
namespace Kovey\Rpc\Server;

use PHPUnit\Framework\TestCase;
use Kovey\Rpc\Protocol\Json;
use Kovey\Rpc\Event;

class ServerTest extends TestCase
{
    protected $server;

    protected function setUp() : void
    {
        $this->server = $this->createMock(\Swoole\Server::class);
        $this->server->method('getClientInfo')
             ->willReturn(array('remote_ip' => '127.0.0.1'));

        $port = $this->createMock(\Swoole\Server\Port::class);
        $port->method('set')
            ->willReturn(null);
        $port->method('on')
            ->willReturn(null);
        $this->server->method('listen')
            ->willReturn($port);
        $this->server->method('send')
             ->willReturn(true);
        $this->server->method('close')
             ->willReturn(true);
        $this->server->method('exist')
            ->willReturn(true);
    }

    public function testPort()
    {
        $key = 'U0ZLf0s8NQgggMrBi16KDeUyPBgzLPWALhohHKRRyxK';
        $port = new Port($this->server, array('host' => '127.0.0.1', 'port' => 9910, 'secret_key' => $key, 'encrypt_type' => 'aes', 'name' => 'test'));
        $port->on('handler', function (Event\Handler $event) {
            $this->assertEquals('Kovey', $event->getClass());
            $this->assertEquals('framework', $event->getMethod());
            $this->assertEquals(array(1, 2), $event->getArgs());
            $this->assertEquals(hash('sha256', '123456'), $event->getTraceId());
            $this->assertEquals('127.0.0.1', $event->getClientIP());
            return array(
                'code' => 0,
                'err' => '',
                'type' => 'success',
                'packet' => '',
                'trace' => '',
                'result' => 'kovey'
            );
        })
             ->on('monitor', function (Event\Monitor $event) {
                 $this->assertEquals(18, count($event->getData()));
             });

        $event = new \Swoole\Server\Event();
        $event->data = Json::pack(array(
            'p' => 'Kovey', 'm' => 'framework', 'a' => array(1, 2), 't' => hash('sha256', '123456'
        )), $key, 'aes');
        $event->fd = 1;
        $event->workerId = 1;

        $this->assertEquals(null, $port->receive($this->server, $event));
    }
}
