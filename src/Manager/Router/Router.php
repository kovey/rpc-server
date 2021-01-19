<?php
/**
 * @description  router
 *
 * @package Kovey\Rpc\Manager\Router
 *
 * @author kovey
 *
 * @time 2020-03-24 20:58:19
 *
 */
namespace Kovey\Rpc\Manager\Router;

class Router
{
    /**
     * @description controller
     *
     * @var string
     */
    private string $controller;

    /**
     * @description action
     *
     * @var string
     */
    private string $action;

    /**
     * @description path
     *
     * @var string
     */
    private string $rootLib = 'Kovey\Rpc\Manager\Web\Controllers\\';

    /**
     * @description template
     *
     * @var string
     */
    private string $template;

    /**
     * @description construct
     *
     * @param string $path
     *
     * @return Router
     */
    public function __construct($path)
    {
        if ($path === '/') {
            $this->controller = $this->rootLib . 'IndexController';
            $this->action = 'indexAction';
            $this->template = __DIR__ . '/../Web/Views/Index/Index.phtml';
            return;
        }

        $info = explode('/', $path);
        if (count($info) > 1) {
            $this->controller = $this->rootLib . ucfirst($info[1]) . 'Controller';
        }

        if (count($info) > 2) {
            $this->action = $info[2] . 'Action';
            $this->template = __DIR__ . '/../Web/Views/' . ucfirst($info[1]) . '/' . ucfirst($info[2]) . '.phtml';
        } else {
            $this->action = 'indexAction';
            $this->template = __DIR__ . '/../Web/Views/' . ucfirst($info[1]) . '/Index.phtml';
        }
    }

    /**
     * @description get controller
     *
     * @return string
     */
    public function getController() : string
    {
        return $this->controller;
    }

    /**
     * @description get action
     *
     * @return string
     */
    public function getAction() : string
    {
        return $this->action;
    }

    /**
     * @description get template
     *
     * @return string
     */
    public function getTemplate() : string
    {
        return $this->template;
    }
}
