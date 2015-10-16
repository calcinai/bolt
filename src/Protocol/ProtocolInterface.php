<?php

/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Protocol;

interface ProtocolInterface {
    public function onStreamData(&$buffer);
    public function upgrade();
    public function send($string);
    public static function getVersion();
}