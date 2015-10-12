<?php

require __DIR__.'/../vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();
$dns_factory = new React\Dns\Resolver\Factory();
$resolver = $dns_factory->createCached('8.8.8.8', $loop);

$client = new \Calcinai\Bolt\Client('ws://localhost:1337/chat', $loop, $resolver);

$loop->run();

//print_r($client);


