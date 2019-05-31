<?php

/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Protocol;

use Ratchet\RFC6455\Messaging\Frame;

interface ProtocolInterface
{
    public function onStreamData(&$buffer);

    public function upgrade();

    public function send($string, $type = Frame::OP_TEXT);

    public static function getVersion();
}
