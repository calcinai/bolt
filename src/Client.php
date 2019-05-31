<?php

/**
 * @package    calcinai/bolt
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\Bolt;

use Calcinai\Bolt\Protocol\ProtocolInterface;
use Calcinai\Bolt\Protocol\RFC6455;
use Evenement\EventEmitter;
use Ratchet\RFC6455\Messaging\Frame;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

class Client extends EventEmitter
{


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

    private $heartbeat_interval;

    /**
     * @var bool
     */
    public $use_exceptions;

    private $state;

    const PORT_DEFAULT_HTTP  = 80;
    const PORT_DEFAULT_HTTPS = 443;

    const STATE_CONNECTING = 'connecting';
    const STATE_CONNECTED  = 'connected';
    const STATE_CLOSING    = 'closing';
    const STATE_CLOSED     = 'closed';

    public function __construct($uri, LoopInterface $loop, Resolver $resolver = null, $protocol = null)
    {

        if (false === filter_var($uri, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException(sprintf('Invalid URI [%s]. Must be in format ws(s)://host:port/path', $uri));
        }

        if ($protocol !== null) {
            if (!in_array(ProtocolInterface::class, class_implements($protocol))) {
                throw new \InvalidArgumentException(sprintf('%s must implement %s', $protocol, ProtocolInterface::class));
            }
            $this->protocol = $protocol;
        } else {
            $this->protocol = RFC6455::class;
        }

        $this->uri = $uri;
        $this->loop = $loop;
        $this->resolver = $resolver;
        $this->state = self::STATE_CLOSED;
        $this->heartbeat_interval = null;
        $this->use_exceptions = true;
    }

    public function connect()
    {

        $connector = new Connector($this->loop, ['dns' => $this->resolver, 'timeout' => 5]);

        $uri = (object)parse_url($this->uri);

        switch ($uri->scheme) {
            case 'ws':
                $scheme = 'tcp';
                $port = isset($uri->port) ? $uri->port : self::PORT_DEFAULT_HTTP;
                break;
            case 'wss':
                $scheme = 'tls';
                $port = isset($uri->port) ? $uri->port : self::PORT_DEFAULT_HTTPS;
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Invalid scheme [%s]', $uri->scheme));
        }

        $this->setState(self::STATE_CONNECTING);

        return $connector->connect($scheme . '://' . $uri->host . ':' . $port)
            ->then(function (ConnectionInterface $stream) {
                $this->transport = new $this->protocol($this, $stream);
                $this->transport->upgrade();
            })->otherwise(function (\Exception $e) {
                $this->emit('error');

                if ($this->use_exceptions) {
                    throw $e;
                }
            });

    }

    public function setState($state)
    {
        $this->state = $state;

        switch ($state) {
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

    public function getState()
    {
        return $this->state;
    }

    public function getURI()
    {
        return $this->uri;
    }

    public function getLoop()
    {
        return $this->loop;
    }

    public function send($string, $type = Frame::OP_TEXT)
    {
        $this->transport->send($string, $type);
    }

    public function setHeartbeatInterval($interval)
    {
        $this->heartbeat_interval = $interval;
    }

    public function getHeartbeatInterval()
    {
        return $this->heartbeat_interval;
    }

    public function useExceptions()
    {
        return $this->use_exceptions;
    }

    /**
     * @param bool $use_exceptions
     */
    public function setUseExceptions($use_exceptions)
    {
        $this->use_exceptions = $use_exceptions;
    }

}
