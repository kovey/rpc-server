<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-02-26 17:48:16
 *
 */
namespace Kovey\Rpc\Server;

use Kovey\Event\EventManager;
use Kovey\Rpc\Event;
use Swoole\Server\Event as SSE;
use Kovey\Library\Protocol\ProtocolInterface;
use Kovey\Rpc\Protocol\Json;
use Kovey\Library\Exception\BusiException;
use Kovey\Library\Exception\KoveyException;
use Kovey\Library\Exception\ProtocolException;
use Kovey\Logger\Logger;
use Kovey\App\Components\ServerInterface;

class Business
{
    private SSE $event;

    private Array $result;

    private string $clientIp;

    private float $begin;

    private int $reqTime;

    private ProtocolInterface $packet;

    private Array $config;

    private bool $needClose;

    private string $spanId;

    public function __construct(SSE $event, Array $config)
    {
        $this->event = $event;
        $this->result = array();
        $this->config = $config;
        $this->needClose = false;
    }

    public function begin(ServerInterface $server) : Business
    {
        $this->clientIp = $server->getClientIP($this->event->fd);
        $this->begin = microtime(true);
        $this->reqTime = time();
        $this->spanId = md5($this->event->fd . microtime(true));
        return $this;
    }

    public function run(EventManager $event) : Business
    {
        try {
            if ($event->listened('unpack')) {
                $this->packet = $event->dispatchWithReturn(new Event\Unpack($this->event->data, $this->config['secret_key'], $this->config['encrypt_type'] ?? 'aes'));
                if (!$this->packet instanceof ProtocolInterface) {
                    $this->parseResultDefault();
                    $this->needClose = true;
                    return $this;
                }
            } else {
                $this->packet = new Json($this->event->data, $this->config['secret_key'], $this->config['encrypt_type'] ?? 'aes');
            }

            if (!$this->packet->parse()) {
                $this->parseResultDefault();
                $this->needClose = true;
                return $this;
            }
        } catch (ProtocolException $e) {
            $this->parseResult($e, 'protocol_exception', $this->event->data);
            $this->needClose = true;
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
            return $this;
        } catch (KoveyException $e) {
            $this->parseResult($e, 'kovey_exception', $this->event->data);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
            return $this;
        } catch (\Throwable $e) {
            $this->parseResult($e, 'fatal_error_exception', $this->event->data, 1000);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
            return $this;
        }

        return $this->handler($event);
    }

    /**
     * @description Handler process
     *
     * @return Business
     */
    private function handler(EventManager $event) : Business
    {
        try {
            $this->result = $event->dispatchWithReturn(new Event\Handler($this->packet, $this->clientIp, $this->spanId));
            if ($this->result['code'] > 0) {
                $this->result['packet'] = $this->packet->getClear();
            }
        } catch (BusiException $e) {
            $this->parseResult($e, 'busi_exception', $this->packet->getClear());
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $this->packet->getTraceId());
        } catch (KoveyException $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $this->packet->getTraceId());
            $this->parseResult($e, 'kovey_exception', $this->packet->getClear(), 1000);
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $this->packet->getTraceId());
            $this->parseResult($e, 'exception', $this->packet->getClear(), 1000);
        }

        return $this;
    }

    public function end(ServerInterface $server) : Business
    {
        $server->send($this->result, $this->event->fd);
        if ($this->needClose) {
            $server->getServ()->close($this->event->fd);
        }
        return $this;
    }

    public function monitor(ServerInterface $server) : Business
    {
        $end = microtime(true);
        $server->monitor(array(
            'delay' => round(($end - $this->begin) * 1000, 2),
            'request_time' => $this->begin * 10000,
            'type' => $this->result['type'],
            'err' => $this->result['error'] ?? '',
            'trace' => $this->result['trace'],
            'service' => $this->config['name'],
            'service_type' => 'rpc',
            'class' => $this->packet->getPath(),
            'method' => $this->packet->getMethod(),
            'args' => $this->packet->getArgs(),
            'ip' => $this->clientIp,
            'time' => $this->reqTime,
            'timestamp' => date('Y-m-d H:i:s', $this->reqTime),
            'minute' => date('YmdHi', $this->reqTime),
            'response' => $this->result['result'] ?? null,
            'traceId' => $this->packet->getTraceId(),
            'from' => $this->packet->getFrom(),
            'end' => $end * 10000,
            'parentId' => $this->packet->getSpanId(),
            'spanId' => $this->spanId
        ), $this->packet->getTraceId());

        return $this;
    }

    private function parseResult(\Throwable $e, string $type, string $clear, int $code = -1) : void
    {
        $this->result['error'] = $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        $this->result['err'] = $e->getMessage();
        $this->result['trace'] = $e->getTraceAsString();
        $this->result['type'] = $type;
        $this->result['code'] = $code < 0 ? $e->getCode() : $code;
        $this->result['packet'] = $clear;
    }

    private function parseResultDefault() : void
    {
        $this->result = array(
            'err' => 'parse data error',
            'error' => 'parse data error',
            'type' => 'exception',
            'trace' => '',
            'code' => 1000,
            'packet' => $this->event->data
        );
    }
}
