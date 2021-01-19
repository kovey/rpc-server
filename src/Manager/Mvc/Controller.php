<?php
/**
 * @description construct
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
     * @description view
     *
     * @var View
     */
    protected View $view;

    /**
     * @description view status
     *
     * @var bool
     */
    protected bool $viewStatus = false;

    /**
     * @description construct
     *
     * @return Controller
     */
    final public function __construct()
    {
        $this->view = new View();
    }

    /**
     * @description set template
     *
     * @param string $template
     *
     * @return Controller
     */
    public function setTemplate(string $template) : Controller
    {
        $this->view->setTemplate($template);
        return $this;
    }

    /**
     * @description render view
     *
     * @return string
     */
    public function render() : string
    {
        return $this->view->render();
    }

    /**
     * @description is disable view
     *
     * @return bool
     */
    public function isDisableView() : bool
    {
        return $this->viewStatus;
    }
 
    /**
     * @description disable view
     *
     * @return Controller
     */
    public function disableView() : Controller
    {
        $this->viewStatus = true;
        return $this;
    }
}
