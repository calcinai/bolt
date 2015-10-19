<?php
/**
 * @package    server-store
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Exception;


class NotFoundException extends \Exception {

    /**
     * NotFoundException constructor.
     * @param $message
     */
    public function __construct($message) {
    }
}