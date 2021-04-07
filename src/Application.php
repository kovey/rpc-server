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

use Kovey\App\Components\ServerInterface;
use Kovey\Library\Exception\KoveyException;
use Kovey\Rpc\App\AppBase;
use Kovey\Rpc\App\Bootstrap\BaseInit;

class Application extends AppBase
{
    /**
     * @description Application instance
     *
     * @var Application
     */
    private static ?Application $instance;

    /**
     * @description get instance
     *
     * @return Application
     */
    public static function getInstance(array $config = array()) : Application
    {
        if (empty(self::$instance)) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    protected function init() : Application
    {
        $this->bootstrap->add(new BaseInit());
        return $this;
    }

    /**
     * @description register server
     *
     * @param ServerInterface $server
     *
     * @return Application
     */
    public function registerServer(ServerInterface $server) : Application
    {
        $this->server = $server;
        $this->server
            ->on('handler', array($this->work, 'run'))
            ->on('console', array($this, 'console'))
            ->on('initPool', array($this->pools, 'initPool'))
            ->on('monitor', array($this, 'monitor'));

        return $this;
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
}
