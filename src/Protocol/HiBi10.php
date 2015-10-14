<?php
/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Protocol;

/**
 * This is a compatibility class for legacy naming of the protocol pre-RFC
 *
 * Class HiBi10
 * @package Calcinai\Bolt\Protocol
 */
class HiBi10 extends RFC6455 {

    public static function getVersion() {
        return 8;
    }
}