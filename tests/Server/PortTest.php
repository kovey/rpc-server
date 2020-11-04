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

class PortTest extends TestCase
{
    protected $server;

    protected function setUp() : void
    {
        $this->server = $this->createMock(\Swoole\Server::class);
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
        $port = new Port($this->server, array('host' => '127.0.0.1', 'port' => 9910, 'secret_key' => md5('123456'), 'encrypt_type' => 'aes'));
        $this->assertEquals(null, $port->receive($this->server, 1, 1, Json::pack(array(
            'p' => 'Kovey', 'm' => 'framework', 'a' => array(1, 2), 't' => hash('sha256', '123456'
        )), md5('123456'), 'aes')));
    }
}
