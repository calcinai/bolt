<?php
/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Protocol;

use Calcinai\Bolt\Client;
use Calcinai\Bolt\Request;
use Calcinai\Bolt\Response;

class RFC6455 extends AbstractProtocol {

    private $websocket_key;

    const WEBSOCKET_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    public function upgrade() {

        $this->sendUpgradeRequest();
    }

    public function onStreamData(&$data) {


        if($this->client->getState() !== Client::STATE_CONNECTED){

            if(null === $response = Response::create($data)){
                return;
            }

            $this->processUpgrade($response);
        } else {
            echo $data;
        }





//        if(!$this->connected){
//            if (!$this->containsCompleteHeader($data)) {
//                return array();
//            }
//            $data = $this->readHandshakeResponse($data);
//        }
//
//        $frames = array();
//        while ($frame = WebSocketFrame::decode($data)){
//            if (WebSocketOpcode::isControlFrame($frame->getType()))
//                $this->processControlFrame($frame);
//            else
//                $this->processMessageFrame($frame);
//
//            $frames[] = $frame;
//        }
//
//
//
//


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

    }


    public function getExpectedAcceptKey(){
        if(!isset($this->websocket_key)){
            //todo handle
        }
        return base64_encode(pack('H*', sha1($this->websocket_key . self::WEBSOCKET_GUID)));
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