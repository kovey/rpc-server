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
use Kovey\Library\Container\ContainerInterface;
use Kovey\Library\Config\Manager;
use Kovey\Rpc\App\Bootstrap\Autoload;
use Kovey\Library\Server\PortInterface;
use Kovey\Logger\Monitor;
use Kovey\Library\Exception\KoveyException;

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
	protected Array $events;

	/**
	 * @description 构造函数
	 *
	 * @return AppBase
	 */
	public function __construct()
	{
		$this->events = array();
	}

	/**
	 * @description 事件监听
	 *
	 * @param string $event
	 *
	 * @param callable $callable
	 *
	 * @return AppBase
	 */
	public function on(string $event, $callable) : AppBase
	{
		if (!is_callable($callable)) {
			return $this;
		}

		$this->events[$event] = $callable;
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
	 * @param string $class
	 *
	 * @param string $method
	 *
	 * @param Array $args
     *
     * @param string $traceId
	 *
	 * @return Array
	 */
	public function handler(string $class, string $method, Array $args, string $traceId) : Array
	{
        $class = $this->config['rpc']['handler'] . '\\' . ucfirst($class);
        $keywords = $this->container->getKeywords($class, $method);
		$instance = $this->container->get($class, $traceId, $keywords['ext']);
		if (!$instance instanceof HandlerAbstract) {
			return array(
				'err' => sprintf('%s is not extends HandlerAbstract', ucfirst($class)),
				'type' => 'exception',
				'code' => 1,
			);
		}

        if (isset($keywords['openTransaction']) && $keywords['openTransaction']) {
            $keywords['database']->beginTransaction();
            try {
                $result = $instance->$method(...$args);
                $keywords['database']->commit();
            } catch (\Throwable $e) {
                $keywords['database']->rollBack();
                throw $e;
            }
        } else {
            $result = $instance->$method(...$args);
        }

		return array(
			'err' => '',
			'type' => 'success',
			'code' => 0,
			'result' => $result
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
	public function monitor(Array $data)
	{
		Monitor::write($data);
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
				'host', 'port', 'log_file', 'pid_file'	, 'secret_key'
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
