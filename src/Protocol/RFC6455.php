<?php
/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Protocol;

use Calcinai\Bolt\Client;
use GuzzleHttp\Psr7\Uri;
use Ratchet\RFC6455\Handshake\ClientNegotiator;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Messaging\MessageInterface;
use function GuzzleHttp\Psr7\parse_response;

class RFC6455 extends AbstractProtocol
{

    /** @var ClientNegotiator */
    private $negotiator;

    /** @var \GuzzleHttp\Psr7\Request */
    private $connection_request;

    /** @var MessageBuffer */
    private $message_buffer;

    public function upgrade()
    {

        $this->negotiator = new ClientNegotiator();

        /** @var \GuzzleHttp\Psr7\Request $request */
        $this->connection_request = $this->negotiator->generateRequest(new Uri($this->client->getURI()));

        // If your WebSocket server uses Basic Auth this needs to be added manually as a header
        $uri = parse_url($this->client->getURI());

        if (isset($uri['user']) || isset($uri['pass'])) {
            $this->connection_request = $this->connection_request->withAddedHeader('Authorization',
                'Basic ' . base64_encode($uri['user'] . ':' . $uri['pass']));
        }

        $this->stream->write(\GuzzleHttp\Psr7\str($this->connection_request));

    }

    public function onStreamData(&$buffer)
    {

        if ($this->client->getState() !== Client::STATE_CONNECTED) {

            $response = parse_response($buffer);

            if (false === $this->negotiator->validateResponse($this->connection_request, $response)) {
                // Invalid response from server
                $this->client->setState(Client::STATE_CLOSING);
                $this->stream->end();

                return false;

            } else {

                $this->client->setState(Client::STATE_CONNECTED);

                $that = $this;

                $this->message_buffer = new MessageBuffer(
                    new CloseFrameChecker(),
                    function (MessageInterface $msg) use ($that) {
                        $that->client->emit('message', [$msg->getPayload()]);
                    },
                    function (FrameInterface $frame) use ($that) {
                        $that->processControlFrame($frame);
                    },
                    false
                );

                $buffer = $response->getBody()->getContents();

            }

        }

        $this->message_buffer->onData($buffer);

        return true;

    }

    public function processControlFrame(FrameInterface $frame)
    {

        switch ($frame->getOpcode()) {

            case Frame::OP_PING:
                $f = new Frame($frame->getPayload(), true, Frame::OP_PONG);
                $this->stream->write($f->maskPayload()->getContents());
                break;
            case Frame::OP_PONG:
                $this->onHeartbeat();
                break;
            case Frame::OP_CLOSE:
                $f = new Frame($frame->getPayload(), true, Frame::OP_CLOSE);
                $this->stream->end($f->maskPayload()->getContents());
                break;
        }

    }

    public function send($string, $type = Frame::OP_TEXT)
    {
        $frame = new Frame($string, true, $type);
        $this->stream->write($frame->maskPayload()->getContents());
    }

    public function sendHeartbeat()
    {
        $frame = new Frame(uniqid(), true, Frame::OP_PING);
        $this->stream->write($frame->maskPayload()->getContents());

    }

    public static function getVersion()
    {
        return 10;
    }

}
