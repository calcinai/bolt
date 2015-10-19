# bolt

Asynchronous WebSocket client client library for PHP. Supports HyBi, ~~as well as Hixie #76~~ (no point).

This was built purely to be a client, as the majority of the WS clients available also contain servers and are very bloated.

# Installation
    
Using composer:

    "require": {
        "calcinai/bolt": "^0.1"
    }    


# Usage
       
Since this really lends itself to being an asynchronous app, it is built to use the React event loops and resolver since there's a good 
chance they'll be in your project already and this lets you attach to the same loop.

```php
$loop = \React\EventLoop\Factory::create();
$dns_factory = new React\Dns\Resolver\Factory();
$resolver = $dns_factory->createCached('8.8.8.8', $loop);

$client = new \Calcinai\Bolt\Client('ws://127.0.0.1:1337/chat', $loop, $resolver);

//Most WS servers will complain/forbid if there is no origin header
$client->setOrigin('127.0.0.1');

$client->connect();


$client->on('stateChange', function($newState){
    echo "State changed to: $newState\n";
});

$client->on('message', function($message) use ($client){
    echo "New message: \n";
    echo $message;
    
    $client->send('This is a response message');
});

$loop->run();
```
   
   
Other available events are ```connecting```, ```connect```, ```disconnecting```, ```disconnect```


HTTP basic auth is also supported via the URI inf the form ```user:pass@host```


Any feedback is most welcome