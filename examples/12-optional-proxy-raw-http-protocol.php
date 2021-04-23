<?php

// A simple example which requests http://google.com/ either directly or through an SSH proxy server.
// The proxy can be given through the SSH_PROXY env or does not use a proxy otherwise.
//
// You can assign the SSH_PROXY environment and prefix this with a space to make
// sure your login credentials are not stored in your bash history like this:
//
// $  export SSH_PROXY=user:secret@example.com
// $ php examples/12-optional-proxy-raw-http-protocol.php
//
// This example highlights how changing from direct connection to using a proxy
// actually adds very little complexity and does not mess with your actual
// network protocol otherwise.
//
// For illustration purposes only. If you want to send HTTP requests in a real
// world project, take a look at example #01, example #02 and https://github.com/reactphp/http#client-usage.

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

// SSH_PROXY environment given? use this as the proxy URL
if (getenv('SSH_PROXY') !== false) {
    $proxy = new Clue\React\SshProxy\SshProcessConnector(getenv('SSH_PROXY'), $loop);
    $connector = new React\Socket\Connector($loop, array(
        'tcp' => $proxy,
        'timeout' => 3.0,
        'dns' => false
    ));
} else {
    $connector = new React\Socket\Connector($loop);
}

$connector->connect('tcp://google.com:80')->then(function (React\Socket\ConnectionInterface $connection) {
    $connection->write("GET / HTTP/1.1\r\nHost: google.com\r\nConnection: close\r\n\r\n");
    $connection->on('data', function ($chunk) {
        echo $chunk;
    });
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$loop->run();
