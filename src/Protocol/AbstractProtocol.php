<?php
/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Protocol;

use Calcinai\Bolt\Client;
use React\Stream\DuplexStreamInterface;

abstract class AbstractProtocol implements ProtocolInterface {

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var DuplexStreamInterface
     */
    protected $stream;

    public function __construct(Client $client, DuplexStreamInterface $stream){
        $this->client = $client;
        $this->stream = $stream;

        $that = $this;
        $this->stream->on('data', function($data) use($that){
            $that->onStreamData($data);
        });
    }

}