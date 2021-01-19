<?php
/**
 * @description View
 *
 * @package Kovey\Rpc\Manager\Mvc
 *
 * @author kovey
 *
 * @time 2020-03-24 21:09:19
 *
 */
namespace Kovey\Rpc\Manager\Mvc;

class View
{
    /**
     * @description template path
     *
     * @var string
     */
    private string $template;

    /**
     * @description view data
     *
     * @var Array
     */
    private Array $data;

    /**
     * @description construct
     *
     * @param ResponseInterface $res
     *
     * @param string $template
     *
     * @return ViewInterface
     */
    final public function __construct()
    {
        $this->data = array();
    }

    /**
     * @description set template
     *
     * @param string $template
     *
     * @return View
     */
    public function setTemplate(string $template) : View
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @description set veriable
     *
     * @param string $name
     *
     * @param mixed $val
     *
     * @return void
     */
    public function __set(string $name, mixed $val) : void
    {
        $this->data[$name] = $val;
    }

    /**
     * @description get aata
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name) : mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * @description render view
     *
     * @return string
     */
    public function render() : string
    {
        ob_start();
        ob_implicit_flush(0);
        extract($this->data);
        require($this->template);
        return ob_get_clean();
    }
}
