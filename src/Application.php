<?php
/**
 * @description Global App
 *
 * @package     Kovey\Rpc
 *
 * @time        2019-11-16 17:28:41
 *
 * @author      kovey
 */
namespace Kovey\Rpc;

use Kovey\Process\ProcessAbstract;
use Kovey\Connection\Pool\PoolInterface;
use Kovey\Library\Server\PortInterface;
use Kovey\Rpc\Server\Server;
use Kovey\Process\UserProcess;
use Kovey\Logger\Logger;
use Kovey\Library\Exception\KoveyException;
use Kovey\Rpc\App\AppBase;
use Kovey\Connection\AppInterface;

class Application extends AppBase implements AppInterface
{
    /**
     * @description Application instance
     *
     * @var Application
     */
    private static Application $instance;

    /**
     * @description bootstrap before app start
     *
     * @var Kovey\Rpc\Bootstrap\Bootstrap
     */
    private $bootstrap;

    /**
     * @description custom bootstrap
     *
     * @var mixed
     */
    private $customBootstrap;

    /**
     * @description user custom process
     *
     * @var Kovey\Process\UserProcess
     */
    private UserProcess $userProcess;

    /**
     * @description connection pool
     *
     * @var Array
     */
    private Array $pools;

    /**
     * @description global veriable
     *
     * @var Array
     */
    private Array $globals;

    /**
     * @description construct
     *
     * @return Application
     */
    public function __construct()
    {
        $this->pools = array();
        $this->globals = array();
        parent::__construct();
    }

    private function __clone()
    {}

    /**
     * @description get instance
     *
     * @return Application
     */
    public static function getInstance() : Application
    {
        if (empty(self::$instance) || !self::$instance instanceof Application) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @description register global veriable
     *
     * @param string $name
     *
     * @param mixed $val
     *
     * @return Application
     */
    public function registerGlobal(string $name, $val) : Application
    {
        $this->globals[$name] = $val;
        return $this;
    }

    /**
     * @description get global
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getGlobal(string $name) : mixed
    {
        return $this->globals[$name] ?? null;
    }

    /**
     * @description bootstrap
     *
     * @return Application
     */
    public function bootstrap() : Application
    {
        if (is_object($this->bootstrap)) {
            $btfuns = get_class_methods($this->bootstrap);
            foreach ($btfuns as $fun) {
                if (substr($fun, 0, 6) !== '__init') {
                    continue;
                }

                $this->bootstrap->$fun($this);
            }
        }

        if (is_object($this->customBootstrap)) {
            $funs = get_class_methods($this->customBootstrap);
            foreach ($funs as $fun) {
                if (substr($fun, 0, 6) !== '__init') {
                    continue;
                }

                $this->customBootstrap->$fun($this);
            }
        }

        return $this;
    }

    /**
     * @description register server
     *
     * @param PortInterface $server
     *
     * @return Application
     */
    public function registerServer(PortInterface $server) : Application
    {
        $this->server = $server;
        $this->server
            ->on('handler', array($this, 'handler'))
            ->on('pipeMessage', array($this, 'pipeMessage'))
            ->on('initPool', array($this, 'initPool'))
            ->on('monitor', array($this, 'monitor'));

        return $this;
    }

    /**
     * @description pipe message event
     *
     * @param string $path
     *
     * @param string $method
     *
     * @param Array $args
     *
     * @param string $traceId
     *
     * @return void
     */
    public function pipeMessage(Event\PipeMessage $event) : void
    {
        try {
            $this->dispatch->dispatch($event);
        } catch (\Exception $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $event->getTraceId());
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $event->getTraceId());
        }
    }

    /**
     * @description init pool event
     *
     * @param Event\InitPool
     *
     * @return void
     */
    public function initPool(Event\InitPool $event) : void
    {
        try {
            foreach ($this->pools as $pool) {
                if (is_array($pool)) {
                    foreach ($pool as $pl) {
                        $pl->init();
                        if (count($pl->getErrors()) > 0) {
                            Logger::writeErrorLog(__LINE__, __FILE__, implode(';', $pl->getErrors()));
                        }
                    }
                    continue;
                }
                $pool->init();
                if (count($pool->getErrors()) > 0) {
                    Logger::writeErrorLog(__LINE__, __FILE__, implode(';', $pool->getErrors()));
                }
            }
        } catch (\Exception $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        } catch (\Throwable $e) {
            Logger::writeExceptionLog(__LINE__, __FILE__, $e);
        }
    }

    /**
     * @description check config
     *
     * @return Application
     *
     * @throws KoveyException
     */
    public function checkConfig() : Application
    {
        $fields = array(
            'server' => array(
                'host', 'port', 'logger_dir', 'pid_file', 'secret_key'
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
     * @description register bootstrap
     *
     * @param mixed Bootstrap
     *
     * @return Application
     */
    public function registerBootstrap(mixed $bootstrap) : Application
    {
        $this->bootstrap = $bootstrap;
        return $this;
    }

    /**
     * @description registerPool custom bootstrap
     *
     * @param mixed Bootstrap
     *
     * @return Application
     */
    public function registerCustomBootstrap(mixed $bootstrap) : Application
    {
        $this->customBootstrap = $bootstrap;
        return $this;
    }

    /**
     * @description register user process
     *
     * @param UserProcess $userProcess
     *
     * @return Application
     */
    public function registerUserProcess(UserProcess $userProcess) : Application
    {
        $this->userProcess = $userProcess;
        return $this;
    }

    /**
     * @description get user process
     *
     * @return UserProcess
     */
    public function getUserProcess() : UserProcess
    {
        return $this->userProcess;
    }

    /**
     * @description register user process
     *
     * @param string $name
     *
     * @param ProcessAbstract $process
     *
     * @return Application
     */
    public function registerProcess(string $name, ProcessAbstract $process) : Application
    {
        if (!is_object($this->server)) {
            return $this;
        }

        $process->setServer($this->server->getServ());
        $this->userProcess->addProcess($name, $process);
        return $this;
    }

    /**
     * @description register pool
     *
     * @param string $name
     *
     * @param PoolInterface $pool
     *
     * @param int $partition
     *
     * @return Application
     */
    public function registerPool(string $name, PoolInterface $pool, int $partition = 0) : AppInterface
    {
        $this->pools[$name] ??= array();
        $this->pools[$name][$partition] = $pool;
        return $this;
    }

    /**
     * @description get pool
     *
     * @param string $name
     * 
     * @param int $partition
     *
     * @return PoolInterface
     */
    public function getPool(string $name, int $partition = 0) : ? PoolInterface
    {
        return $this->pools[$name][$partition] ?? null;
    }

    /**
     * @description app run
     *
     * @return void
     *
     * @throws KoveyException
     */
    public function run() : void
    {
        if (!is_object($this->server)) {
            throw new KoveyException('server not register');
        }

        $this->server->start();
    }

    /**
     * @description event listen on server
     *
     * @param string $event
     *
     * @param callable $callable
     *
     * @return Application
     */
    public function serverOn(string $event, $callable) : Application
    {
        $this->server->on($event, $callable);
        return $this;
    }
}
