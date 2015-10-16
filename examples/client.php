<?php

require __DIR__.'/../vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();
$dns_factory = new React\Dns\Resolver\Factory();
$resolver = $dns_factory->createCached('8.8.8.8', $loop);

$client = new \Calcinai\Bolt\Client('ws://127.0.0.1:1337/chat', $loop, $resolver);
$client->setOrigin('127.0.0.1');
$client->connect();


$client->on('stateChange', function($newState){
    echo "State changed to: $newState\n";
});

$client->on('message', function($message) use ($client){
    
});

$loop->run();

//print_r($client);

