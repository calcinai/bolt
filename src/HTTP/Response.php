<?php
/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\HTTP;


use Calcinai\Bolt\Exception\ForbiddenException;
use Calcinai\Bolt\Exception\NotFoundException;

class Response {

    private $status;
    private $message;

    private $headers;

    private $data;


    public function __construct($status, $message, $headers, $data){
        $this->status = $status;
        $this->message = $message;
        $this->headers = $headers;
        $this->data = $data;
    }

    public function hasHeader($header_name){
        return isset($this->headers[$header_name]);
    }

    public function getHeader($header_name){
        return $this->headers[$header_name];
    }

    public static function create($data){

        $cr = "\r\n";
        $header_delimiter = $cr.$cr;

        if(false === $header_pos = strpos($data, $header_delimiter)){
            return null;
        }

        $text_headers = substr($data, 0, $header_pos);
        $header_lines = explode($cr, $text_headers);

        $status_line = array_shift($header_lines);

        list(, $status_code, $status_message) = explode(' ', $status_line, 3);

        //Go off and throw exceptions if need be.
        self::checkStatusCode($status_code, $status_message);

        //If no exceptions thrown, continue to process headers etc.
        $headers = [];
        foreach($header_lines as $line){
            list($header_name, $header_value) = explode(':', $line, 2);
            $headers[$header_name] = trim($header_value);
        }

        $data = substr($data, $header_pos + strlen($header_delimiter));

        return new self($status_code, $status_message, $headers, $data);

    }

    public static function checkStatusCode($code, $message){
        switch($code){
            case 403:
                throw new ForbiddenException($message);
            case 404:
                throw new NotFoundException($message);
        }
    }

}