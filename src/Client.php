<?php

/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt;

use Calcinai\Bolt\HTTP\Request;
use Calcinai\Bolt\Protocol\ProtocolInterface;
use Calcinai\Bolt\Protocol\RFC6455;
use Calcinai\Bolt\Stream\Connector;
use Evenement\EventEmitter;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\SocketClient\SecureConnector;
use React\Stream\DuplexStreamInterface;

class Client extends EventEmitter {

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var Resolver
     */
    private $resolver;

    /**
     * The uri of the conenction
     *
     * StdClass with parameters from parse_url
     *
     * @var object
     */
    private $uri;

    /**
     * The protocol class to use
     *
     * @var string
     */
    private $protocol;

    /**
     * @var Protocol\AbstractProtocol
     */
    private $transport;

    private $state;

    const PORT_DEFAULT_HTTP  = 80;
    const PORT_DEFAULT_HTTPS = 443;

    const STATE_CONNECTING  = 'connecting';
    const STATE_CONNECTED   = 'connected';
    const STATE_CLOSING     = 'closing';
    const STATE_CLOSED      = 'closed';


    public function __construct($uri, LoopInterface $loop, Resolver $resolver = null, $protocol = null){

        if(false === filter_var($uri, FILTER_VALIDATE_URL)){
            throw new \InvalidArgumentException(sprintf('Invalid URI [%s]. Must be in format ws(s)://host:port/path', $uri));
        }

        if($protocol !== null) {
            if(!in_array(ProtocolInterface::class, class_implements($protocol))){
                throw new \InvalidArgumentException(sprintf('%s must implement %s', $protocol, ProtocolInterface::class));
            }
            $this->protocol = $protocol;
        } else{
            $this->protocol = RFC6455::class;
        }

        $this->uri = (object) parse_url($uri);
        $this->loop = $loop;
        $this->resolver = $resolver;
        $this->state = self::STATE_CLOSED;
    }

    public function connect() {

        $connector = new Connector($this->loop, $this->resolver);

        switch($this->uri->scheme){
            case 'ws':
                $port = isset($this->uri->port) ? $this->uri->port : self::PORT_DEFAULT_HTTP;
                break;
            case 'wss':
                $port = isset($this->uri->port) ? $this->uri->port : self::PORT_DEFAULT_HTTPS;
                //Upgrade the connector
                $connector = new SecureConnector($connector, $this->loop);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Invalid scheme [%s]', $this->uri->scheme));
        }

        $that = $this;
        $connector->create($this->uri->host, $port)->then(function(DuplexStreamInterface $stream) use($that) {
            $that->transport = new $that->protocol($that, $stream);
            $that->transport->upgrade();
        });

        $this->setState(self::STATE_CONNECTING);
    }

    public function setState($state){
        $this->state = $state;

        switch($state){
            case self::STATE_CONNECTING:
                $this->emit('connecting');
                break;
            case self::STATE_CONNECTED:
                $this->emit('connect');
                break;
            case self::STATE_CLOSING:
                $this->emit('closing');
                break;
            case self::STATE_CLOSED:
                $this->emit('close');
                break;
        }

        $this->emit('stateChange', [$state]);

        return $this;
    }

    public function getState(){
        return $this->state;
    }

    public function getURI(){
        return $this->uri;
    }

    public function setOrigin($origin) {
        Request::setDefaultHeader('Origin', $origin);
        return $this;
    }

    public function send($string) {
        $this->transport->send($string);
    }

}