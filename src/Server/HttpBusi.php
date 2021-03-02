<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-03-02 17:03:00
 *
 */
namespace Kovey\Rpc\Server;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Kovey\Rpc\Event;
use Kovey\Logger\Logger;
use Kovey\Event\EventManager;

class HttpBusi
{
    private Request $request;

    private Response $response;

    private string $traceId;

    private Array $result;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function begin() : HttpBusi
    {
        $this->result = array(
            'httpCode' => 200,
            'header' => array(
                'content-type' => 'text/html'
            ),
            'cookie' => array()
        );

        $this->httpCode = 200;
        $this->traceId = hash('sha256', uniqid($this->request->server['request_uri'], true) . random_int(1000000, 9999999));
        return $this;
    }

    public function run(EventManager $event) : HttpBusi
    {
        try {
            $this->result = $event->dispatchWithReturn(new Event\RunAction($this->request, $this->traceId));
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $this->traceId);
            $this->result['httpCode'] = 500;
            $this->result['content'] = ErrorTemplate::getContent(500);
        }

        return $this;
    }

    public function end() : HttpBusi
    {
        $httpCode = $this->result['httpCode'] ?? 500;
        $header = $this->result['header'] ?? array();
        foreach ($header as $k => $v) {
            $this->response->header($k, $v);
        }
        $this->response->header('Request-Id', $this->traceId);

        $cookie = $this->result['cookie'] ?? array();
        foreach ($cookie as $cookie) {
            $this->response->header('Set-Cookie', $cookie);
        }
        $this->response->status($httpCode);
        $this->response->end($httpCode == 200 ? $this->result['content'] : ErrorTemplate::getContent($httpCode));

        return $this;
    }
}
