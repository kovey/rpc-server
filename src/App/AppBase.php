<?php
/**
 * @description App基类，用于多端口监听
 *
 * @package Kovey\Rpc\App
 *
 * @author kovey
 *
 * @time 2020-03-21 18:24:46
 *
 */
namespace Kovey\Rpc\App;

use Kovey\Rpc\Handler\HandlerAbstract;
use Kovey\Container\ContainerInterface;
use Kovey\Library\Config\Manager;
use Kovey\Rpc\App\Bootstrap\Autoload;
use Kovey\Library\Server\PortInterface;
use Kovey\Logger\Monitor;
use Kovey\Library\Exception\KoveyException;
use Kovey\Rpc\Event;
use Kovey\Event\Dispatch;
use Kovey\Event\Listener\Listener;
use Kovey\Event\Listener\ListenerProvider;

class AppBase
{
    /**
     * @description 服务器
     *
     * @var Kovey\Library\Server\PortInterface
     */
    protected PortInterface $server;

    /**
     * @description 容器对象
     *
     * @var Kovey\Library\Container\ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @description 应用配置
     *
     * @var Array
     */
    protected Array $config;

    /**
     * @description 自动加载
     *
     * @var Kovey\Rpc\App\Bootstrap\Autoload
     */
    protected Autoload $autoload;

    /**
     * @description 事件
     *
     * @var Array
     */
    protected Array $onEvents;

    protected static Array $events = array(
        'pipeMessage' => Event\PipeMessage::class,
        'monitor' => Event\Monitor::class,
    );

    protected Dispatch $dispatch;

    protected ListenerProvider $provider;

    /**
     * @description 构造函数
     *
     * @return AppBase
     */
    public function __construct()
    {
        $this->config = array();
        $this->onEvents = array();
        $this->provider = new ListenerProvider();
        $this->dispatch = new Dispatch($this->provider);
    }

    /**
     * @description 事件监听
     *
     * @param string $event
     *
     * @param callable | Array $callable
     *
     * @return AppBase
     */
    public function on(string $event, Array | callable $callable) : AppBase
    {
        if (!isset(self::$events[$event])) {
            return $this;
        }

        if (!is_callable($callable)) {
            return $this;
        }

        $this->onEvents[$event] = $event;
        $listener = new Listener();
        $listener->addEvent(self::$events[$event], $callable);
        $this->provider->addListener($listener);

        return $this;
    }

    /**
     * @description 设置配置
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
     * @description 获取配置
     *
     * @return Array
     */
    public function getConfig() : Array
    {
        return $this->config;
    }

    /**
     * @description handler业务
     *
     * @param Event\Handler $event
     *
     * @return Array
     */
    public function handler(Event\Handler $event) : Array
    {
        $class = $this->config['rpc']['handler'] . '\\' . ucfirst($event->getClass());
        $keywords = $this->container->getKeywords($class, $event->getMethod());
        $instance = $this->container->get($class, $event->getTraceId(), $keywords['ext']);
        if (!$instance instanceof HandlerAbstract) {
            return array(
                'err' => sprintf('%s is not extends HandlerAbstract', ucfirst($class)),
                'type' => 'exception',
                'code' => 1,
                'result' => null,
                'trace' => ''
            );
        }

        $instance->setClientIp($event->getClientIP());

        if ($keywords['openTransaction']) {
            $keywords['database']->getConnection()->beginTransaction();
            try {
                $result = call_user_func(array($instance, $event->getMethod()), ...$event->getArgs());
                $keywords['database']->getConnection()->commit();
            } catch (\Throwable $e) {
                $keywords['database']->getConnection()->rollBack();
                throw $e;
            }
        } else {
            $result = call_user_func(array($instance, $event->getMethod()), ...$event->getArgs());
        }

        return array(
            'err' => '',
            'type' => 'success',
            'code' => 0,
            'result' => $result,
            'trace' => ''
        );
    }

    /**
     * @description 注册自动加载
     *
     * @param Autoload $autoload
     *
     * @return AppBase
     */
    public function registerAutoload(Autoload $autoload) : AppBase
    {
        $this->autoload = $autoload;
        return $this;
    }

    /**
     * @description 注册服务端
     *
     * @param PortInterface $server
     *
     * @return AppBase
     */
    public function registerServer(PortInterface $server) : AppBase
    {
        $this->server = $server;
        $this->server
            ->on('handler', array($this, 'handler'))
            ->on('monitor', array($this, 'monitor'));

        return $this;
    }

    /**
     * @description 监控
     *
     * @param Array $data
     *
     * @return null
     */
    public function monitor(Event\Monitor $event)
    {
        Monitor::write($event->getData());
        go (function ($event) {
            $this->dispatch->dispatch($event);
        }, $event);
    }

    /**
     * @description 注册容器
     *
     * @param ContainerInterface $container
     *
     * @return AppBase
     */
    public function registerContainer(ContainerInterface $container) : AppBase
    {
        $this->container = $container;
        return $this;
    }

    /**
     * @description 检测配置
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
     * @description 注册本地加载路径
     *
     * @param string $path
     *
     * @return AppBase
     */
    public function registerLocalLibPath(string $path) : AppBase
    {
        if (!is_object($this->autoload)) {
            return $this;
        }

        $this->autoload->addLocalPath($path);
        return $this;
    }

    /**
     * @description 获取容器
     *
     * @return ContainerInterface
     */
    public function getContainer() : ContainerInterface
    {
        return $this->container;
    }

    /**
     * @description 服务器事件监听
     *
     * @param string $event
     *
     * @param callable $callable
     *
     * @return AppBase
     */
    public function serverOn(string $event, $callable) : AppBase
    {
        $this->server->on($event, $callable);
        return $this;
    }
}
