<?php
/**
 * @description reflection
 *
 * @package Kovey\Rpc\Manager\Web\Tools
 *
 * @author kovey
 *
 * @time 2020-03-24 21:59:53
 *
 */
namespace Kovey\Rpc\Manager\Web\Tools;

class Rf
{
    private static Array $excludes = array(
        '__construct' => 1,
        '__destruct' => 1,
        'setClientIp' => 1,
        'getStack' => 1,
        'init' => 1
    );
    /**
     * @description get interface info
     *
     * @param string $class
     *
     * @return Array
     */
    public static function get(string $class) : Array
    {
        $rf = new \ReflectionClass($class);
        $methods = $rf->getMethods(\ReflectionMethod::IS_PUBLIC);
        if (empty($methods)) {
            return array();
        }

        $result = array();
        foreach ($methods as $method) {
            if (isset(self::$excludes[$method->getName()])) {
                continue;
            }

            $returnType = $method->getReturnType();
            $info = array(
                'doc' => $method->getDocComment(),
                'return' => empty($returnType) ? 'mixed' : $returnType->__toString(),
                'modifier' => 'public'
            );

            $params = $method->getParameters();
            $info['args'] = array();
            if (!empty($params)) {
                foreach ($params as $param) {
                    preg_match('/>(.*)]/', $param->__toString(), $match);
                    $p = trim($match[1]);
                    $tmpInfo = explode('=', $p);
                    $tlen = count($tmpInfo);
                    $default = '';
                    if ($tlen > 1) {
                        $p = trim($tmpInfo[0]);
                        $default = trim($tmpInfo[1]);
                    }
                    $ainfo = explode(' ', $p);
                    $len = count($ainfo);
                    if ($len > 1) {
                        $info['args'][$param->getPosition()] = array(
                            'type' => $ainfo[0],
                            'param' => $ainfo[$len - 1],
                            'default' => $default
                        );
                        continue;
                    }

                    $info['args'][$param->getPosition()] = array(
                        'type' => '',
                        'param' => $p,
                        'default' => $default
                    );
                }

                ksort($info['args']);
            }

            $result[$method->getName()] = $info;
        }

        ksort($result); 
        return $result;
    }
}
