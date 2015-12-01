<?php
/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Protocol;

use Calcinai\Bolt\Client;
use Calcinai\Bolt\Exception\IncompleteFrameException;
use Calcinai\Bolt\Exception\IncompletePayloadException;
use Calcinai\Bolt\HTTP\Request;
use Calcinai\Bolt\HTTP\Response;
use Calcinai\Bolt\Message;
use Calcinai\Bolt\Protocol\RFC6455\Frame;

class RFC6455 extends AbstractProtocol {

    /**
     * @var Frame
     */
    private $current_frame;

    /**
     * @var Message
     */
    private $current_message;
    private $websocket_key;

    const WEBSOCKET_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    public function upgrade() {

        $this->sendUpgradeRequest();
    }

    public function onStreamData(&$buffer) {

        if($this->client->getState() !== Client::STATE_CONNECTED){

            if(null === $response = Response::create($buffer)){
                return false;
            }

            $this->processUpgrade($response);

            //In this case, the response will have no body if it was just an upgrade
            if(!$response->hasBody()){
                return true;
            }

            //If we get to here, it had some body which will be the beginning of a (or complete) WS frame
            $buffer = $response->getBody();
        }


        try {
            if(!isset($this->current_frame)){
                $this->current_frame = new Frame();
            }
            $overflow = $this->current_frame->appendBuffer($buffer);
        } catch (IncompletePayloadException $e){
            return true;
        } catch (IncompleteFrameException $e){
            return false;
        }


        //At this point we have a complete frame
        $this->processFrame($this->current_frame);

        unset($this->current_frame);

        //Now that we're done, we can repeat/recurse for the overflow as fragment is PBR
        if($overflow !== ''){
            $this->onStreamData($overflow);
        }
        return true;
    }


    private function processFrame(Frame $frame) {

        switch($frame->getOpcode()){
            case Frame::OP_BINARY:
            case Frame::OP_TEXT:
            case Frame::OP_CONTINUE:
                $this->addFragmentToMessage($frame);
                break;
            case Frame::OP_PONG:
                $this->onHeartbeat();
                break;
            case Frame::OP_CLOSE:
                //TODO - close
                break;
        }
    }

    private function addFragmentToMessage(Frame $frame) {
        if(!isset($this->current_message)){
            $this->current_message = new Message();
        }

        $this->current_message->addBody($frame->getPayload())
            ->setIsComplete($frame->isFinalFragment());

        if($this->current_message->isComplete()){
            $this->client->emit('message', [$this->current_message->getBody()]);
            unset($this->current_message);
        }
    }


    /**
     * @return Request
     */
    private function sendUpgradeRequest() {

        $this->websocket_key = self::generateKey();

        $request = new Request();
        $request->setURI($this->client->getURI());
        $request->setHeader('Connection', 'Upgrade');
        $request->setHeader('Sec-WebSocket-Key', $this->websocket_key);
        $request->setHeader('Sec-WebSocket-Version', 13);
        $request->setHeader('Upgrade', 'websocket');

        $this->stream->write($request->toString());
    }

    private function processUpgrade(Response $response) {

        $swa = 'Sec-WebSocket-Accept';

        if(!$response->hasHeader($swa)){
            throw new \Exception(sprintf('Server did not respond with a [%s] header', $swa));
        }

        if($response->getHeader($swa) !== $this->getExpectedAcceptKey()){
            throw new \Exception(sprintf('[%s] header did not match expected value', $swa));
        }

        $this->client->setState(Client::STATE_CONNECTED);
        $this->sendHeartbeat();

    }

    public function getExpectedAcceptKey(){
        if(!isset($this->websocket_key)){
            //todo handle
        }
        return base64_encode(pack('H*', sha1($this->websocket_key . self::WEBSOCKET_GUID)));
    }


    public function send($string, $type = Frame::OP_TEXT) {
        $frame = new Frame($string, $type);
        $this->stream->write($frame->encode());
    }


    public function sendHeartbeat(){
        $frame = new Frame('', Frame::OP_PING);
        $this->stream->write($frame->encode());

    }

    protected static function generateKey() {
        static $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"$&/()=[]{}0123456789';
        $key = '';
        $chars_length = strlen($chars);
        for ($i = 0; $i < 16; $i++){
            $key .= $chars[mt_rand(0, $chars_length-1)];
        }
        return base64_encode($key);
    }

    public static function getVersion() {
        return 10;
    }

}