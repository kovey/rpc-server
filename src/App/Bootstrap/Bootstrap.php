<?php
/**
 *
 * @description 整个运用启动前的初始化
 *
 * @package     App\Bootstrap
 *
 * @time        Tue Sep 24 09:00:10 2019
 *
 * @author      kovey
 */
namespace Kovey\Rpc\App\Bootstrap;

use Kovey\Library\Process;
use Kovey\Library\Config\Manager;
use Kovey\Logger\Logger;
use Kovey\Logger\Monitor;
use Kovey\Logger\Db;
use Kovey\Container\Container;
use Kovey\Rpc\Application;
use Kovey\Rpc\Server\Server;
use Kovey\Rpc\Manager\Router\Router;
use Kovey\Library\Process\UserProcess;
use Kovey\Rpc\Protocol\Exception;
use Kovey\Library\Util\Json;
use Kovey\Rpc\Event;

class Bootstrap
{
    /**
     * @description 初始化日志
     *
     * @param Application $app
     *
     * @return null
     */
    public function __initLogger(Application $app)
    {
        ko_change_process_name(Manager::get('server.rpc.name') . ' rpc root');
        Logger::setLogPath(
            Manager::get('server.logger.info'), Manager::get('server.logger.exception'), Manager::get('server.logger.error'), 
            Manager::get('server.logger.warning'), Manager::get('server.logger.busi_exception')
        );
        Logger::setCategory(Manager::get('server.rpc.name'));
        Db::setLogDir(Manager::get('server.logger.db'));
        Monitor::setLogDir(Manager::get('server.logger.monitor'));
    }

    /**
     * @description 初始化APP
     *
     * @param Application $app
     *
     * @return null
     */
    public function __initApp(Application $app)
    {
        $app->registerServer(new Server($app->getConfig()['server']))
            ->registerContainer(new Container())
            ->registerUserProcess(new UserProcess($app->getConfig()['server']['worker_num']));
    }

    /**
     * @description 初始化自定义进程
     *
     * @param Application $app
     *
     * @return null
     */
    public function __initProcess(Application $app)
    {
        $app->registerProcess('kovey_config', (new Process\Config())->setProcessName(Manager::get('server.rpc.name') . ' config'));
    }

    /**
     * @description 初始化自定义的Bootsrap
     *
     * @param Application $app
     *
     * @return null
     */
    public function __initCustomBoot(Application $app)
    {
        $bootstrap = $app->getConfig()['rpc']['boot'] ?? 'application/Bootstrap.php';
        $file = APPLICATION_PATH . '/' . $bootstrap;
        if (!is_file($file)) {
            return;
        }

        require_once $file;

        $app->registerCustomBootstrap(new \Bootstrap());
    }

    public function __initRunAction(Application $app)
    {
        $app->serverOn('run_action', function (Event\RunAction $event) use ($app) {
            $router = new Router($event->getRequest()->server['path_info'] ?? '/');
            $instance = $app->getContainer()->get($router->getController(), $event->getTraceId());
            $instance->data = strtolower($event->getRequest()->server['request_method']) === 'get' ? $event->getRequest()->get : $event->getRequest()->post;
            if (empty($instance->data)) {
                if (!empty($event->getRequest()->getContent())) {
                    $instance->data = Json::decode($event->getRequest()->getContent());
                }
            }
            try {
                $instance->setTemplate($router->getTemplate());
                $result = call_user_func(array($instance, $router->getAction()), $app);
                if ($instance->isDisableView()) {
                    return array(
                        'httpCode' => 200,
                        'header' => array(
                            'content-type' => 'text/html'
                        ),
                        'content' => $result
                    );
                }

                return array(
                    'httpCode' => 200,
                    'header' => array(
                        'content-type' => 'text/html'
                    ),
                    'content' => $instance->render()
                );
            } catch (Exception $e) {
                return array(
                    'httpCode' => 200,
                    'header' => array(
                        'content-type' => 'text/html'
                    ),
                    'content' => $e->getMessage() . PHP_EOL . $e->getTraceAsString(),
                    'cookie' => array()
                );
            }
        });
    }

    public function __initParseInject(Application $app)
    {
        $app->registerLocalLibPath(APPLICATION_PATH . '/application');

        $handler = $app->getConfig()['rpc']['handler'];
        $app->getContainer()->parse(APPLICATION_PATH . '/application/' . $handler, $handler);
    }
}
