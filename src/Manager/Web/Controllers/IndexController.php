<?php
/**
 * @description rpc interface ui
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
     * @description api ui
     *
     * @return void
     */
    public function indexAction() : void
    {
        $service = $this->data['s'] ?? '';
        $this->view->services = $this->getService($service);
    }

    /**
     * @description get all service
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
            try {
                $services[$service] = Rf::get($class);
            } catch (\Throwable $e) {
                $services[$service] = $e->getMessage();
            }
            return $services;
        }

        $files = scandir(APPLICATION_PATH . '/application/' . str_replace('\\', '/', $handler));
        foreach ($files as $file) {
            if (substr($file, -3) !== 'php') {
                continue;
            }

            $service = substr($file, 0, strlen($file) - 4);
            $class = $handler . '\\' . ucfirst($service);
            try {
                $info = Rf::get($class);
                $services[$service] = $info;
            } catch (\Throwable $e) {
                $services[$service] = $e->getMessage();
            }
        }

        return $services;
    }
}
