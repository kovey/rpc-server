<?php
/**
 * @description 格式化
 *
 * @package Kovey\Rpc\Manager\Web\Tools
 *
 * @author kovey
 *
 * @time 2020-03-28 11:09:42
 *
 */
namespace Kovey\Rpc\Manager\Web\Tools;

class Format
{
    /**
     * @description 格式化异常错误
     *
     * @param string $message
     *
     * @return string
     */
    public static function exception(string $message) : string
    {
        $lines = explode(PHP_EOL, $message);
        array_walk($lines, function(&$line) {
            $line = '<p>' . $line . '</p>';
        });

        return implode('', $lines);
    }
}
