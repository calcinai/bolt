<?php
/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Protocol;

use Calcinai\Bolt\Client;
use Calcinai\Bolt\Exception\ConnectionLostException;
use React\EventLoop\Timer\Timer;
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

    /**
     * @var Timer
     */
    protected $heartbeat_timer;

    public function __construct(Client $client, DuplexStreamInterface $stream) {
        $this->client = $client;
        $this->stream = $stream;

        $that = $this;
        $this->stream->on(
            'data', function ($data) use ($that) {
            static $buffer;

            //Handle partial chunks.
            $buffer .= $data;

            //If the handler returns true, was successfully processed and can empty buffer
            if($that->onStreamData($buffer)) {
                $buffer = '';
            }
        });

        $this->stream->on(
            'close', function () use ($client) {
            $client->setState(Client::STATE_CLOSED);
        });

    }

    public function onHeartbeat(){

        if(isset($this->heartbeat_timer)){
            $this->heartbeat_timer->cancel();
        }

        $this->client->getLoop()->addTimer($this->client->getHeartbeatInterval(), function(){
            //Set a new timeout (1 sec seems reasonable)
            $this->heartbeat_timer = $this->client->getLoop()->addTimer(2, function(){
                $this->stream->close();
                throw new ConnectionLostException();
            });

            $this->sendHeartbeat();

        });
    }

    public function sendHeartbeat() {}

}