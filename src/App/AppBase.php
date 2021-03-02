<?php
/**
 * @description App Base
 *
 * @package Kovey\Rpc\App
 *
 * @author kovey
 *
 * @time 2020-03-21 18:24:46
 *
 */
namespace Kovey\Rpc\App;

use Kovey\App\App;
use Kovey\App\Components\ServerInterface;
use Kovey\Library\Exception\KoveyException;
use Kovey\Rpc\Work\Handler;

class AppBase extends App
{
    /**
     * @description Application instance
     *
     * @var Application
     */
    private static ?Application $instance = null;

    /**
     * @description server
     *
     * @var ServerInterface
     */
    protected ServerInterface $server;

    /**
     * @description get instance
     *
     * @param Array $config
     *
     * @return Application
     */
    public static function getInstance(Array $config = array()) : AppBase
    {
        if (empty(self::$instance)) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    protected function init() : AppBase
    {
        return $this;
    }

    protected function initWork() : AppBase
    {
        $this->work = new Handler($this->config['rpc']['handler']);
        $this->work->setEventManager($this->event);
        return $this;
    }

    /**
     * @description set Config
     *
     * @param Array $config
     *
     * @return AppBase
     */
    public function setConfig(Array $config) : AppBase
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @description register server
     *
     * @param ServerInterface $server
     *
     * @return AppBase
     */
    public function registerServer(ServerInterface $server) : AppBase
    {
        $this->server = $server;
        $this->server
            ->on('handler', array($this->work, 'run'))
            ->on('monitor', array($this, 'monitor'));

        return $this;
    }

    /**
     * @description check config
     *
     * @return AppBase
     *
     * @throws Exception
     */
    public function checkConfig() : AppBase
    {
        $fields = array(
            'server' => array(
                'host', 'port', 'log_file', 'pid_file'    , 'secret_key'
            ), 
            'rpc' => array(
                'name', 'handler'
            )
        );

        foreach ($fields as $key => $field) {
            if (!isset($this->config[$key])) {
                throw new KoveyException("$key is not exists", 500);
            }

            foreach ($field as $fe) {
                if (!isset($this->config[$key][$fe])) {
                    throw new KoveyException("$fe of $key is not exists", 500);
                }
            }
        }

        return $this;
    }

    /**
     * @description event listen on server
     *
     * @param string $event
     *
     * @param callable $callable
     *
     * @return AppBase
     */
    public function serverOn(string $event, array | callable $callable) : AppBase
    {
        $this->server->on($event, $callable);
        return $this;
    }
}
