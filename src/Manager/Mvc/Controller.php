<?php
/**
 * @description 控制器
 *
 * @package Kovey\Rpc\Manager\Mvc
 *
 * @author kovey
 *
 * @time 2020-03-24 21:11:37
 *
 */
namespace Kovey\Rpc\Manager\Mvc;

class Controller
{
    /**
     * @description 视图
     *
     * @var View
     */
    protected View $view;

    /**
     * @description 试图状态
     *
     * @var bool
     */
    protected bool $viewStatus = false;

    /**
     * @description 构造函数
     *
     * @return Controller
     */
    final public function __construct()
    {
        $this->view = new View();
    }

    /**
     * @description 设置试图模板
     *
     * @param string $template
     *
     * @return void
     */
    public function setTemplate(string $template)
    {
        $this->view->setTemplate($template);
    }

    /**
     * @description 视图渲染
     *
     * @return void
     */
    public function render()
    {
        return $this->view->render();
    }

    /**
     * @description 视图是否禁用
     *
     * @return bool
     */
    public function isDisableView() : bool
    {
        return $this->viewStatus;
    }
 
    /**
     * @description 禁用视图
     *
     * @return void
     */
    public function disableView()
    {
        $this->viewStatus = true;
    }
}
