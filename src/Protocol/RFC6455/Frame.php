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

    const EXT_PAYLOAD_BITS_S = 16;
    const EXT_PAYLOAD_BITS_L = 64;

    public function __construct($payload = null, $opcode = null, $fin = true){

        $this->meta_decoded = false;

        if($payload === null || $opcode === null){
            return;
        }

        $this->frame_fin = $fin ? 0b1 : 0b0;
        $this->frame_rsv1 = 0b0;
        $this->frame_rsv2 = 0b0;
        $this->frame_rsv3 = 0b0;
        $this->frame_opcode = $opcode;
        $this->frame_masked = 0b0;
        $this->frame_length = strlen($payload);

        $this->payload = $payload;


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


    public function encode($masked = true){

        $this->frame_masked = $masked ? 0b1 : 0b0;

        $control = 0;
        self::addBits($control, self::FRAME_BITS_FIN, $this->frame_fin);
        self::addBits($control, self::FRAME_BITS_RSV1, $this->frame_rsv1); //Reserved
        self::addBits($control, self::FRAME_BITS_RSV2, $this->frame_rsv2); //Reserved
        self::addBits($control, self::FRAME_BITS_RSV3, $this->frame_rsv3); //Reserved
        self::addBits($control, self::FRAME_BITS_OPCODE, $this->frame_opcode);
        self::addBits($control, self::FRAME_BITS_MASKED, $this->frame_masked);

        //Work out how many extended bits need to be added based on payload size
        if ($this->frame_length > pow(2, self::EXT_PAYLOAD_BITS_S) -1) {
            // >16 bit - 8 bytes size
            self::addBits($control, self::FRAME_BITS_LENGTH, 127);

            $low = $this->frame_length & pow(2, 32) - 1;
            $high = $this->frame_length >> 32;
            $packed = pack('nNN', $control, $high, $low);

        } elseif($this->frame_length > 125) {
            // 2 bytes size
            self::addBits($control, self::FRAME_BITS_LENGTH, 126);

            $packed = pack('nn', $control, $this->frame_length);
        } else {
            // standard size frame
            self::addBits($control, self::FRAME_BITS_LENGTH, $this->frame_length);
            $packed = pack('n', $control);
        }

        if($this->isMasked()){
            $this->masking_key = self::generateMaskingKey();
            $packed .= pack('N', $this->masking_key);
            self::mask($this->payload, $this->masking_key);
        }

        $packed .= $this->payload;

        return $packed;

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
            self::mask($buffer, $this->masking_key);
        }

        $this->payload .= $buffer;

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


    private function eatBytes(&$buffer, $num_bytes){

        if(!isset($buffer[$num_bytes])){
            throw new IncompleteFrameException($this);
        }

        $consumed = substr($buffer, 0, $num_bytes);
        $buffer = substr($buffer, $num_bytes);

        return $consumed;
    }

    public static function extractBits(&$data, $num_bits){

        //Calculate the product
        $bits = $data & pow(2, $num_bits) - 1;
        //Shift off the read bits
        $data = $data >> $num_bits;

        return $bits;
    }

    private static function addBits(&$data, $num_bits, $bits){

        $data = $data << $num_bits;
        $data |= $bits;
    }


    public static function generateMaskingKey() {
        $mask = 0;

        for ($i = 0; $i < 32; $i += 8) {
            $mask |= rand(32, 255) << $i;
        }

        return $mask;
    }


    private static function mask(&$data, $masking_key) {

        $data_size = strlen($data);

        for($i = 0; $i < $data_size; $i++) {
            $remainder = 3 - $i % 4; //work backward through the masking key
            $shift_bits = $remainder * 8; //Make the shift a whole byte
            $xor = $masking_key >> $shift_bits;
            $data[$i] = $data[$i] ^ chr($xor);
        }
    }


}