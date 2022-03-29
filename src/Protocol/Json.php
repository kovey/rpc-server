<?php
/**
 *
 * @description rpc protocol
 *
 * @package     Protocol
 *
 * @time        2019-11-16 18:14:53
 *
 * @author      kovey
 */
namespace Kovey\Rpc\Protocol;

use Kovey\Library\Util\Util;
use Kovey\Library\Encryption\Encryption;
use Kovey\Library\Exception\ProtocolException;
use Kovey\Library\Protocol\ProtocolInterface;
use Kovey\Library\Util\Json as JS;

class Json implements ProtocolInterface
{
    /**
     * @description path
     *
     * @var string
     */
    private string $path;

    /**
     * @description method
     *
     * @var string
     */
    private string $method;

    /**
     * @description arguments
     *
     * @var Array
     */
    private Array $args;

    /**
     * @description body
     *
     * @var string
     */
    private string $body;

    /**
     * @description secret key
     *
     * @var string
     */
    private string $secretKey;

    /**
     * @description clear text
     *
     * @var Array
     */
    private Array $clear;

    /**
     * @description encrypt type
     *
     * @var string
     */
    private string $encryptType;

    /**
     * @description is public key
     *
     * @var bool
     */
    private bool $isPub;

    /**
     * @description trace Id
     *
     * @var string
     */
    private string $traceId;

    /**
     * @description from service
     *
     * @var string
     */
    private string $from;

    /**
     * @description client program language
     *
     * @var string
     */
    private string $clientLang;

    /**
     * @description span id
     *
     * @var string
     */
    private string $spanId;

    /**
     * @description client version
     *
     * @var string
     */
    private string $version;

    /**
     * @description compress
     *
     * @var string
     */
    private string $compress;

    /**
     * @description construct
     *
     * @param string $body
     *
     * @param string $key
     *
     * @param string $type
     *
     * @param bool $isPub
     *
     * @return Json
     */
    public function __construct(string $body, string $key, string $type = 'aes', bool $isPub = false)
    {
        $this->body = $body;
        $this->secretKey = $key;
        $this->encryptType = $type;
        $this->isPub = $isPub;
        $this->args = array();
        $this->path = '';
        $this->method = '';
        $this->traceId = '';
        $this->from = '';
        $this->clientLang = 'php';
        $this->spanId = '';
        $this->version = '1.0';
        $this->compress = self::COMPRESS_NO;
    }

    /**
     * @description parse body
     *
     * @return bool
     */
    public function parse() : bool
    {
        $info = self::unpack($this->body, $this->secretKey, $this->encryptType, $this->isPub);
        $this->compress = $info['compress'];
        $this->clear = $info['packet'];
        if (empty($this->clear)) {
            return false;
        }

        if (!is_array($this->clear)) {
            return false;
        }

        if (!isset($this->clear['p'])
            || !isset($this->clear['m'])
            || empty($this->clear['p'])
            || empty($this->clear['m'])
        ) {
            return false;
        }

        $this->args = $this->clear['a'] ?? array();
        if (!is_array($this->args)) {
            $this->args = json_decode($this->args, true);
        }

        if (!is_array($this->args)) {
            return false;
        }

        $this->path  = $this->clear['p'];
        $this->method = $this->clear['m'];
        $this->traceId = $this->clear['t'] ?? '';
        $this->from = $this->clear['f'] ?? '';
        $this->clientLang = $this->clear['c'] ?? 'php';
        $this->spanId = $this->clear['s'] ?? '';
        $this->version = $this->clear['v'] ?? '1.0';

        return true;
    }

    /**
     * @description get path
     *
     * @return string
     */
    public function getPath() : string
    {
        return $this->path;
    }

    /**
     * @description get method
     *
     * @return string
     */
    public function getMethod() : string
    {
        return $this->method;
    }

    /**
     * @description get arguments
     *
     * @return Array
     */
    public function getArgs() : Array
    {
        return $this->args;
    }

    /**
     * @description get clear
     *
     * @return string
     */
    public function getClear() : string
    {
        return JS::encode($this->clear);
    }

    /**
     * @description get traceId
     *
     * @return string
     */
    public function getTraceId() : string
    {
        return $this->traceId;
    }

    /**
     * @description get from id
     *
     * @return string
     */
    public function getFrom() : string
    {
        return $this->from;
    }

    /**
     * @description get client language
     *
     * @return string
     */
    public function getClientLang() : string
    {
        return $this->clientLang;
    }

    /**
     * @description get client version
     *
     * @return string
     */
    public function getVersion() : string
    {
        return $this->version;
    }

    /**
     * @description package
     *
     * @param Array $packet
     *
     * @param string $secretKey
     *
     * @param string $type
     *
     * @param bool $isPub
     *
     * @return string
     *
     * @throws KoveyException
     */
    public static function pack(Array $packet, string $secretKey, string $type = 'aes', bool $isPub = false, int $compress = self::COMPRESS_NO) : string
    {
        $ps = json_encode($packet);

        $data = Encryption::encrypt($ps, $secretKey, $type, $isPub);
        if ($compress == self::COMPRESS_GZIP) {
            $data = gzcompress($data);
            return pack(self::PACK_TYPE, strlen($data) + 4) . pack(self::PACK_TYPE, $compress) . $data;
        }

        return pack(self::PACK_TYPE, strlen($data)) . $data;
    }

    /**
     * @description unpackage
     *
     * @param string $data
     *
     * @param string $secretKey
     *
     * @param string $type
     *
     * @param bool $isPub
     *
     * @return Array
     *
     * @throws KoveyException | ProtocolException
     */
    public static function unpack(string $data, string $secretKey, string $type = 'aes', bool $isPub = false) : Array
    {
        $info = unpack(self::PACK_TYPE . 'a/' . self::PACK_TYPE . 'b', substr($data, self::LENGTH_OFFSET, self::HEADER_LENGTH_NEW));
        $compress = $info['b'];
        if ($compress != self::COMPRESS_GZIP) {
            $info = unpack(self::PACK_TYPE . 'a', substr($data, self::LENGTH_OFFSET, self::HEADER_LENGTH));
            $compress = self::COMPRESS_NO;
        }

        $length = $info['a'];
        if (!Util::isNumber($length) || $length < 1) {
            throw new ProtocolException('unpack packet failure', 1005, 'pack_error');
        }

        $offset = self::BODY_OFFSET;
        if ($compress == self::COMPRESS_GZIP) {
            $length -= 4;
            $offset = self::HEADER_LENGTH_NEW;
        }

        $encrypt = substr($data, $offset, $length);
        if ($compress == self::COMPRESS_GZIP) {
            $encrypt = gzuncompress($encrypt, self::COMPRESS_LENGTH);
        }

        $packet = Encryption::decrypt($encrypt, $secretKey, $type, $isPub);
        return array(
            'compress' => $compress,
            'packet' => json_decode($packet, true)
        );
    }

    public function getSpanId() : string
    {
        return $this->spanId;
    }

    public function getCompress() : int
    {
        return $this->compress;
    }
}
