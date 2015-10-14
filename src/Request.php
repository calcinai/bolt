<?php
/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt;


class Request {

    const METHOD_GET    = 'GET';
    const METHOD_POST   = 'POST';

    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $path;

    /**
     * @var array
     */
    private $headers;


    private static $default_headers = [];


    public function __construct($path = null, $method = self::METHOD_GET){
        $this->method = $method;
        $this->path = $path;
        $this->headers = self::$default_headers;
    }


    public function setURI($uri) {
        $path = $uri->path;
        if(isset($uri->query)){
            $path = sprintf('%s?%s', $path, $uri->query);
        }

        $this->setHeader('Host', $uri->host);
        $this->setPath($path);
        return $this;
    }


    /**
     * @return string
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * @param string $path
     * @return Request
     */
    public function setPath($path) {
        $this->path = $path;
        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders() {
        return $this->headers;
    }

    /**
     * @param array $headers
     * @return Request
     */
    public function setHeaders($headers) {
        $this->headers = $headers;
        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return Request
     */
    public function setHeader($key, $value) {
        $this->headers[$key] = $value;
        return $this;
    }

    public function toString(){
        static $cr = "\r\n";

        $out = sprintf("%s %s HTTP/1.1%s", $this->method, $this->path, $cr);
        foreach($this->headers as $header_name => $header_value){
            $out .= sprintf("%s: %s%s", $header_name, $header_value, $cr);
        }
        $out .= $cr;

        return $out;
    }

    /**
     * @return array
     */
    public static function getDefaultHeaders() {
        return self::$default_headers;
    }

    /**
     * @param array $headers
     * @return Request
     */
    public static function setDefaultHeaders($headers) {
        self::$default_headers = $headers;
    }

    /**
     * @param $key
     * @param $value
     * @return Request
     */
    public static function setDefaultHeader($key, $value) {
        self::$default_headers[$key] = $value;
    }

}