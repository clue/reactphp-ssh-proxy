<?php

// A simple example which requests http://google.com/ either directly or through
// an SSH proxy server.
// The proxy can be given through the SSH_PROXY env or does not use a proxy otherwise.
// This example highlights how changing from direct connection to using a proxy
// actually adds very little complexity and does not mess with your actual
// network protocol otherwise.
//
// You can assign the SSH_PROXY environment and prefix this with a space to make
// sure your login credentials are not stored in your bash history like this:
//
// $  export SSH_PROXY=user:secret@example.com
// $ php examples/02-optional-proxy-http.php
//
// For illustration purposes only. If you want to send HTTP requests in a real
// world project, take a look at https://github.com/clue/reactphp-buzz#http-proxy

use Clue\React\SshProxy\SshProcessConnector;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

// SSH_PROXY environment given? use this as the proxy URL
if (getenv('SSH_PROXY') !== false) {
    $proxy = new SshProcessConnector(getenv('SSH_PROXY'), $loop);
    $connector = new Connector($loop, array(
        'tcp' => $proxy,
        'timeout' => 3.0,
        'dns' => false
    ));
} else {
    $connector = new Connector($loop);
}

$connector->connect('tcp://google.com:80')->then(function (ConnectionInterface $stream) {
    $stream->write("GET / HTTP/1.1\r\nHost: google.com\r\nConnection: close\r\n\r\n");
    $stream->on('data', function ($chunk) {
        echo $chunk;
    });
}, 'printf');

$loop->run();
