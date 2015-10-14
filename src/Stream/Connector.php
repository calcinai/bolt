<?php
/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Stream;


use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;

class Connector extends \React\SocketClient\Connector {

    public function __construct(LoopInterface $loop, Resolver $resolver = null)
    {
        parent::__construct($loop, $resolver);
    }


}