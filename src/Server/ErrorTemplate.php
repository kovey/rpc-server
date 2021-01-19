<?php
/**
 * @description http code error
 *
 * @package     
 *
 * @time        2020-01-18 15:15:32
 *
 * @author      kovey
 */
namespace Kovey\Rpc\Server;

class ErrorTemplate
{
    const HTTP_CODE_200 = 200;

    const HTTP_CODE_201 = 201;

    const HTTP_CODE_202 = 202;

    const HTTP_CODE_203 = 203;

    const HTTP_CODE_204 = 204;

    const HTTP_CODE_205 = 205;

    const HTTP_CODE_206 = 206;

    const HTTP_CODE_300 = 300;

    const HTTP_CODE_301 = 301;

    const HTTP_CODE_302 = 302;

    const HTTP_CODE_303 = 303;

    const HTTP_CODE_304 = 304;

    const HTTP_CODE_305 = 305;

    const HTTP_CODE_307 = 307;

    const HTTP_CODE_400 = 400;

    const HTTP_CODE_401 = 401;

    const HTTP_CODE_403 = 403;

    const HTTP_CODE_404 = 404;

    const HTTP_CODE_405 = 405;

    const HTTP_CODE_500 = 500;

    const HTTP_CODE_501 = 501;

    const HTTP_CODE_502 = 502;

    const HTTP_CODE_503 = 503;

    const HTTP_CODE_504 = 504;

    const HTTP_CODE_505 = 505;

    /**
     * @description 简单的错误模板
     *
     * @var Array
     */
    private static Array $templates = array(
        self::HTTP_CODE_404 => '<p style="text-align:center;">Kovey Frawework</p><p style="text-align:center">Page Not Found!</p>',
        self::HTTP_CODE_500 => '<p style="text-align:center;">Kovey Frawework</p><p style="text-align:center">Internal Error!</p>',
        self::HTTP_CODE_502 => '<p style="text-align:center;">Kovey Frawework</p><p style="text-align:center">Service Not Available!</p>',
        self::HTTP_CODE_504 => '<p style="text-align:center;">Kovey Frawework</p><p style="text-align:center">Gateway Timeout!</p>',
        self::HTTP_CODE_405 => '<p style="text-align:center;">Kovey Frawework</p><p style="text-align:center">Method Disable!</p>'
    );

    /**
     * @description 获取错误模板
     *
     * @param int $code
     *
     * @return string
     */
    public static function getContent(int $code) : string
    {
        return self::$templates[$code] ?? '<p style="text-align:center;">Kovey Frawework</p><p style="text-align:center">Network Error!</p>';
    }
}
