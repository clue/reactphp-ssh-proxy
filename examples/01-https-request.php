<?php

// A simple example which uses an HTTP client to requesthttps://example.com/ through an SSH proxy server.
// The proxy can be given through the SSH_PROXY env and defaults to localhost otherwise.
//
// You can assign the SSH_PROXY environment and prefix this with a space to make
// sure your login credentials are not stored in your bash history like this:
//
// $  export SSH_PROXY=user:secret@example.com
// $ php examples/01-https-request.php

require __DIR__ . '/../vendor/autoload.php';

$url = getenv('SSH_PROXY') !== false ? getenv('SSH_PROXY') : 'ssh://localhost:22';

$proxy = new Clue\React\SshProxy\SshProcessConnector($url);

$connector = new React\Socket\Connector(array(
    'tcp' => $proxy,
    'timeout' => 3.0,
    'dns' => false
));

$browser = new React\Http\Browser($connector);

$browser->get('https://example.com/')->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump($response->getHeaders(), (string) $response->getBody());
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
