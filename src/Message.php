<?php
/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt;


class Message {

    private $is_complete;
    private $body;

    public function __construct() {
        $this->is_complete = false;
    }

    public function addBody($body) {
        $this->body .= $body;
        return $this;
    }

    public function getBody() {
        return $this->body;
    }

    public function isComplete() {
        return $this->is_complete;
    }

    public function setIsComplete($is_complete) {
         $this->is_complete = $is_complete;
        return $this;
    }
}