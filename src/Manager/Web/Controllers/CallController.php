<?php
/**
 * @description call service interface
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
     * @description service api
     *
     * @param Kovey\Rpc\Application
     *
     * @return string
     */
    public function serviceAction($app) : string
    {
        $this->disableView();

        $service = $this->data['service'] ?? '';
        $method = $this->data['method'] ?? '';
        $args = $this->data['args'] ?? array();

        if (empty($service) || empty($method)) {
            return 'service or method is empty.';
        }

        $class = 'Handler\\' . $service;
        $keywords = $app->getContainer()->getKeywords($class, $method);
        $obj = $app->getContainer()->get($class, hash('sha256', time()), md5(time()), $keywords['ext']);
        $params = array();
        foreach ($args as $arg) {
            if ($arg['type'] != 'array') {
                $params[] = $arg['value'];
                continue;
            }

            $params[] = Json::decode($arg['value']);
        }

        try {
            if ($keywords['openTransaction']) {
                $keywords['database']->getConnection()->beginTransaction();
                try {
                    $result = Code::dump($obj->$method(...$params));
                    $keywords['database']->getConnection()->commit();
                    return $result;
                } catch (\Throwable $e) {
                    $keywords['database']->getConnection()->rollBack();
                    throw $e;
                }
            } else {
                return Code::dump($obj->$method(...$params));
            }
        } catch (\Exception $e) {
            return Code::dump(sprintf("%s in %s on %d", $e->getMessage(), $e->getFile(), $e->getLine()) . PHP_EOL . $e->getTraceAsString());
        } catch (\Throwable $e) {
            return Code::dump(sprintf("%s in %s on %d", $e->getMessage(), $e->getFile(), $e->getLine()) . PHP_EOL . $e->getTraceAsString());
        }
    }

    /**
     * @description call user interface
     *
     * @return void
     */
    public function indexAction() : void
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
