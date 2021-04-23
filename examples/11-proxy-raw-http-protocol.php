<?php

// A simple example which requests http://google.com/ through an SSH proxy server.
// The proxy can be given through the SSH_PROXY env and defaults to localhost otherwise.
//
// You can assign the SSH_PROXY environment and prefix this with a space to make
// sure your login credentials are not stored in your bash history like this:
//
// $  export SSH_PROXY=user:secret@example.com
// $ php examples/11-proxy-raw-http-protocol.php
//
// For illustration purposes only. If you want to send HTTP requests in a real
// world project, take a look at example #01, example #02 and https://github.com/reactphp/http#client-usage.

require __DIR__ . '/../vendor/autoload.php';

$url = getenv('SSH_PROXY') !== false ? getenv('SSH_PROXY') : 'ssh://localhost:22';

$loop = React\EventLoop\Factory::create();

$proxy = new Clue\React\SshProxy\SshProcessConnector($url, $loop);
$connector = new React\Socket\Connector($loop, array(
    'tcp' => $proxy,
    'timeout' => 3.0,
    'dns' => false
));

$connector->connect('tcp://google.com:80')->then(function (React\Socket\ConnectionInterface $connection) {
    $connection->write("GET / HTTP/1.1\r\nHost: google.com\r\nConnection: close\r\n\r\n");
    $connection->on('data', function ($chunk) {
        echo $chunk;
    });
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$loop->run();
