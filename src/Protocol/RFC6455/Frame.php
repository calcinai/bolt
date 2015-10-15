<?php

/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt\Protocol\RFC6455;

use Calcinai\Bolt\Exception\IncompleteFrameException;
use Calcinai\Bolt\Exception\IncompletePayloadException;

class Frame {


    private $frame_fin;      // 1 bit in length
    private $frame_rsv1;     // 1 bit in length
    private $frame_rsv2;     // 1 bit in length
    private $frame_rsv3;     // 1 bit in length
    private $frame_opcode;   // 4 bits in length
    private $frame_masked;   // 1 bit in length
    private $frame_length;   // 7 bits in length (initially)

    private $payload;
    private $masking_key;

    private $meta_decoded;

    const OP_CONTINUE = 0b0000;
    const OP_TEXT     = 0b0001;
    const OP_BINARY   = 0b0010;
    const OP_CLOSE    = 0b1000;
    const OP_PING     = 0b1001;
    const OP_PONG     = 0b1010;

    const FRAME_BITS_FIN    = 1;
    const FRAME_BITS_RSV1   = 1;
    const FRAME_BITS_RSV2   = 1;
    const FRAME_BITS_RSV3   = 1;
    const FRAME_BITS_OPCODE = 4;
    const FRAME_BITS_MASKED = 1;
    const FRAME_BITS_LENGTH = 7;


    public function __construct(){
        $this->meta_decoded = false;
    }

    public function isMasked() {
        //It's just 1 bit
        return $this->frame_masked === 0b1;
    }

    public function isFinalFragment(){
        return $this->frame_fin === 0b1;
    }

    public function getOpcode() {
        return $this->frame_opcode;
    }

    public function getPayload() {
        return $this->payload;
    }

    /**
     * @param $buffer
     * @return string
     * @throws IncompletePayloadException
     */
    public function appendBuffer(&$buffer){
        if(!$this->meta_decoded){
            $this->decode($buffer);
        }

        $this->appendPayload($buffer);
    }

    public function appendPayload($buffer){

        if($this->isMasked()){
            //What good does this even do!?
            $mask_size = strlen($this->masking_key);
            foreach(str_split($buffer, $mask_size) as $chunk){
                $this->payload .= $chunk ^ ($this->masking_key >> ($mask_size - strlen($chunk))); //That strlen catches the remainder.
            }
        } else {
            $this->payload .= $buffer;
        }


        $payload_length = strlen($this->payload);
        if($payload_length < $this->frame_length){
            //Underrun
            throw new IncompletePayloadException($this);
        } elseif($payload_length > $this->frame_length){
            //Overflow
            $this->payload = substr($this->payload, 0, $this->frame_length);
            return substr($this->payload, $this->frame_length);
        } else{
            //No underrun/overflow
            return null;
        }

    }

    public function decode(&$buffer){

        //unpack n takes 2 bytes
        $control = current(unpack('n', $this->eatBytes($buffer, 2)));

        //Go through all the bits, shifting along the way.
        $this->frame_length = self::extractBits($control, self::FRAME_BITS_LENGTH);
        $this->frame_masked = self::extractBits($control, self::FRAME_BITS_MASKED);
        $this->frame_opcode = self::extractBits($control, self::FRAME_BITS_OPCODE);
        $this->frame_rsv3 = self::extractBits($control, self::FRAME_BITS_RSV3);
        $this->frame_rsv2 = self::extractBits($control, self::FRAME_BITS_RSV2);
        $this->frame_rsv1 = self::extractBits($control, self::FRAME_BITS_RSV1);
        $this->frame_fin = self::extractBits($control, self::FRAME_BITS_FIN);


        if($this->frame_length === 126){
            $this->frame_length = current(unpack('n', $this->eatBytes($buffer, 2))); //First 2 Bytes
        } elseif($this->frame_length === 127){
            list(, $high, $low) = unpack('N2', $this->eatBytes($buffer, 8)); //First 8 bytes, well, 2x4 bytes
            $this->frame_length = ($high << 32) + $low; //PHP 5.6 has a pack/J, which would make this unnecessary.
        }

        if($this->isMasked()){
            $this->masking_key = $this->eatBytes($buffer, 4);
        }

        $this->meta_decoded = true;
    }



    public static function extractBits(&$data, $num_bits){

        //Calculate the product
        $bits = $data & pow(2, $num_bits) - 1;
        //Shift off the read bits
        $data = $data >> $num_bits;

        return $bits;
    }

    private function eatBytes(&$buffer, $num_bytes){

        if(!isset($buffer[$num_bytes])){
            throw new IncompleteFrameException($this);
        }

        $consumed = substr($buffer, 0, $num_bytes);
        $buffer = substr($buffer, $num_bytes);

        return $consumed;
    }

}