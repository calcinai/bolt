<?php

/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt;

use Evenement\EventEmitter;

use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;

class Client extends EventEmitter {

    public function __construct($uri, LoopInterface $loop, Resolver $resolver = null){

        $resolver->resolve('google.com')->then(function($dns){
            echo $dns;
        });

    }
}