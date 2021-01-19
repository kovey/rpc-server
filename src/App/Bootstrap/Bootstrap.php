<?php
/**
 *
 * @description init before app start
 *
 * @package     App\Bootstrap
 *
 * @time        Tue Sep 24 09:00:10 2019
 *
 * @author      kovey
 */
namespace Kovey\Rpc\App\Bootstrap;

use Kovey\Process\Process;
use Kovey\Library\Config\Manager;
use Kovey\Logger\Logger;
use Kovey\Logger\Monitor;
use Kovey\Logger\Db;
use Kovey\Container\Container;
use Kovey\Rpc\Application;
use Kovey\Rpc\Server\Server;
use Kovey\Rpc\Manager\Router\Router;
use Kovey\Process\UserProcess;
use Kovey\Rpc\Protocol\Exception;
use Kovey\Library\Util\Json;
use Kovey\Rpc\Event;

class Bootstrap
{
    /**
     * @description init logger
     *
     * @param Application $app
     *
     * @return void
     */
    public function __initLogger(Application $app) : void
    {
        ko_change_process_name(Manager::get('server.rpc.name') . ' rpc root');
        Logger::setLogPath(Manager::get('server.server.logger_dir'));
        Logger::setCategory(Manager::get('server.rpc.name'));
        Db::setLogDir(Manager::get('server.server.logger_dir'));
        Monitor::setLogDir(Manager::get('server.server.logger_dir'));
    }

    /**
     * @description init app
     *
     * @param Application $app
     *
     * @return void
     */
    public function __initApp(Application $app) : void
    {
        $app->registerServer(new Server($app->getConfig()['server']))
            ->registerContainer(new Container())
            ->registerUserProcess(new UserProcess($app->getConfig()['server']['worker_num']));
    }

    /**
     * @description init user custom process
     *
     * @param Application $app
     *
     * @return void
     */
    public function __initProcess(Application $app) : void
    {
        $app->registerProcess('kovey_config', (new Process\Config())->setProcessName(Manager::get('server.rpc.name') . ' config'));
    }

    /**
     * @description init custom bootstrap
     *
     * @param Application $app
     *
     * @return void
     */
    public function __initCustomBoot(Application $app) : void
    {
        $bootstrap = $app->getConfig()['rpc']['boot'] ?? 'application/Bootstrap.php';
        $file = APPLICATION_PATH . '/' . $bootstrap;
        if (!is_file($file)) {
            return;
        }

        require_once $file;

        $app->registerCustomBootstrap(new \Bootstrap());
    }

    public function __initRunAction(Application $app) : void
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

    public function __initParseInject(Application $app) : void
    {
        $app->registerLocalLibPath(APPLICATION_PATH . '/application');

        $handler = $app->getConfig()['rpc']['handler'];
        $app->getContainer()->parse(APPLICATION_PATH . '/application/' . $handler, $handler);
    }
}
