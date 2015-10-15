<?php
/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Protocol;


class Hixie76 extends AbstractProtocol {

    public function __construct(){
        throw new \BadMethodCallException('Protocol not implemented');
    }

    public static function getVersion() {
        return 0;
    }

    public function upgrade() {
        // TODO: Implement upgrade() method.
    }

    public function onStreamData(&$buffer) {
        // TODO: Implement onStreamData() method.
    }
}