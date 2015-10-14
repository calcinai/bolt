<?php

/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Protocol;

interface ProtocolInterface {
    public function upgrade();
    public function onStreamData(&$data);
    public static function getVersion();
}