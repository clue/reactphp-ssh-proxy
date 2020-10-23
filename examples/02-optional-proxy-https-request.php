<?php

// A simple example which uses an HTTP client to request https://example.com/ (optional: Through an SSH proxy server.)
//
// The proxy can be given through the SSH_PROXY env or does not use a proxy otherwise.
// You can assign the SSH_PROXY environment and prefix this with a space to make
// sure your login credentials are not stored in your bash history like this:
//
// $  export SSH_PROXY=user:secret@example.com
// $ php examples/02-optional-proxy-https-request.php

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

$browser = new React\Http\Browser($loop, $connector);

$browser->get('https://example.com/')->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump($response->getHeaders(), (string) $response->getBody());
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$loop->run();
