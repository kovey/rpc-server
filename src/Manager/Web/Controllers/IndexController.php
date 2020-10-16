<?php
/**
 * @description rpc接口界面
 *
 * @package Kovey\Rpc\Manager\Web\Controllers
 *
 * @author kovey
 *
 * @time 2020-03-24 20:45:23
 *
 */
namespace Kovey\Rpc\Manager\Web\Controllers;

use Kovey\Rpc\Manager\Mvc\Controller;
use Kovey\Rpc\Manager\Web\Tools\Rf;
use Kovey\Library\Util\Json;
use Kovey\Library\Config\Manager;

class IndexController extends Controller
{
    /**
     * @description 接口界面
     *
     * @return void
     */
    public function indexAction()
    {
        $service = $this->data['s'] ?? '';
        $this->view->services = $this->getService($service);
    }

    /**
     * @description 获取所有服务
     *
     * @param string $service
     *
     * @return Array
     */
    private function getService(string $service) : Array
    {
        $handler = Manager::get('server.rpc.handler');
        $services = array();
        if (!empty($service)) {
            $class = $handler . '\\' . ucfirst($service);
            $services[$service] = Rf::get($class);
            return $services;
        }

        $files = scandir(APPLICATION_PATH . '/application/' . str_replace('\\', '/', $handler));
        foreach ($files as $file) {
            if (substr($file, -3) !== 'php') {
                continue;
            }

            $service = substr($file, 0, strlen($file) - 4);
            $class = $handler . '\\' . ucfirst($service);
            $info = Rf::get($class);
            $services[$service] = $info;
        }

        return $services;
    }
}
