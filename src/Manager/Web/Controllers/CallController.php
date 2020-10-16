<?php
/**
 * @description 服务调用
 *
 * @package Kovey\Rpc\Manager\Web\Controllers
 *
 * @author kovey
 *
 * @time 2020-03-24 21:25:42
 *
 */
namespace Kovey\Rpc\Manager\Web\Controllers;

use Kovey\Rpc\Manager\Mvc\Controller;
use Kovey\Rpc\Manager\Web\Tools\Code;
use Kovey\Library\Util\Json;

class CallController extends Controller
{
    /**
     * @description 服务接口
     *
     * @param Kovey\Rpc\Application
     *
     * @return null
     */
    public function serviceAction($app)
    {
        $this->disableView();

        $service = $this->data['service'] ?? '';
        $method = $this->data['method'] ?? '';
        $args = $this->data['args'] ?? array();

        if (empty($service) || empty($method)) {
            return 'service or method is empty.';
        }

        $obj = $app->getContainer()->get('Handler\\' . $service);
        $params = array();
        foreach ($args as $arg) {
            if ($arg['type'] != 'array') {
                $params[] = $arg['value'];
                continue;
            }

            $params[] = Json::decode($arg['value']);
        }

        try {
            return Code::dump($obj->$method(...$params));
        } catch (\Exception $e) {
            return Code::dump($e->getMessage() . PHP_EOL . $e->getTraceAsString());
        } catch (\Throwable $e) {
            return Code::dump($e->getMessage() . PHP_EOL . $e->getTraceAsString());
        }
    }

    /**
     * @description 调用界面
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view->args = empty($this->data['a']) ? array() : Json::decode($this->data['a']);
        $this->view->service = $this->data['s'] ?? '';
        $this->view->method = $this->data['m'] ?? '';
        $this->view->argsType = array(
            'other' => 'other',
            'boolean' => 'boolean',
            'array' => 'array'
        );
    }
}
