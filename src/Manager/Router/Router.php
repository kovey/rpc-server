<?php
/**
 * @description  路由
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
     * @description 控制器
     *
     * @var string
     */
    private string $controller;

    /**
     * @description 行为
     *
     * @var string
     */
    private string $action;

    /**
     * @description 路径
     *
     * @var string
     */
    private string $rootLib = 'Kovey\Rpc\Manager\Web\Controllers\\';

    /**
     * @description 模板
     *
     * @var string
     */
    private string $template;

    /**
     * @description 构造函数
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
     * @description 获取控制器
     *
     * @return string
     */
    public function getController() : string
    {
        return $this->controller;
    }

    /**
     * @description 获取行为
     *
     * @return string
     */
    public function getAction() : string
    {
        return $this->action;
    }

    /**
     * @description 获取模板
     *
     * @return string
     */
    public function getTemplate() : string
    {
        return $this->template;
    }
}
