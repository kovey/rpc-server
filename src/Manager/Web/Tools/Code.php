<?php
/**
 * @description 打印类型
 *
 * @package Kovey\Rpc\Manager\Web\Tools
 *
 * @author kovey
 *
 * @time 2020-03-27 22:51:20
 *
 */
namespace Kovey\Rpc\Manager\Web\Tools;

class Code
{
    /**
     * @description 打印
     *
     * @param array ...$params
     *
     * @return string
     */
    public static function dump(...$params) : string
    {
        $result = array();
        foreach ($params as $param) {
            $result[] = self::dumpItem($param);
        }

        return implode('', $result);
    }

    /**
     * @description 打印单个变量
     *
     * @param $param
     *
     * @param int $i
     *
     * @return string
     */
    public static function dumpItem($param, int $i = 0) : string
    {
        switch (gettype($param)) {
            case 'array':
                return self::dumpArray($param, $i);
            case 'object':
                return self::dumpObject($param, $i);
            case 'boolean':
                return "bool(".($param ? "true" : "false").")";
            case 'integer':
                return "int({$param})</p>";
            case 'string':
                return 'string(' . strlen($param). ') &quot;' . $param . '&quot;';
            case 'double':
                return "float({$param})";
            case 'null':
                return 'null';
            case 'resource':
                return 'resource';
            default:
                return 'null';
        }
    }

    /**
     * @description 打印数组
     *
     * @param $param
     *
     * @param int $i
     *
     * @return string
     */
    public static function dumpArray($param, int $i = 0) : string
    {
        $result = '<p>array('.count($param).') {</p>';
        foreach ($param as $key => $item) {
            $result .= '<p style="margin-left:1.5rem;">[' . $key . '] =&gt; </p>';
            $result .= '<div style="margin-left:1.5rem;">' . self::dumpItem($item, $i+1) . '</div>';
        }

        return $result . '<p>' . $space . '}</p>';
    }

    /**
     * @description 打印对象
     *
     * @param $param
     *
     * @param int $i
     *
     * @return string
     */
    public static function dumpObject($param, int $i = 0) : string
    {
        $rf = new \ReflectionClass($param);
        $vars = $rf->getProperties();
        $className = get_class($param);
        $result = '<p>object(' . $className . ') ('.count($vars).') {</p>';
        foreach ($vars as $pro) {
            $result .= '<p style="margin-left:1.5rem;">[&quot;' . $pro->getName() . '&quot;:&quot;' . $className . '&quot;:&quot;' . self::getType($pro) . '&quot;] =&gt; </p>';
            $pro->setAccessible(true);
            $result .= '<p style="margin-left:1.5rem;">' . self::dumpItem($pro->getValue($param), $i + 1) . '</p>';
        }

        return $result . '<p>' . $space . '}</p>';
    }

    /**
     * @description 获取对象属性类型
     *
     * @param mixed $obj
     *
     * @return string
     */
    private static function getType($obj) : string
    {
        $static = $obj->isStatic() ? 'static:' : '';

        if ($obj->isPrivate()) {
            return $static . 'private';
        }

        if ($obj->isProtected()) {
            return $static . 'protected';
        }

        if ($obj->isPublic()) {
            return $static . 'public';
        }

        return $static . 'default';
    }
}
