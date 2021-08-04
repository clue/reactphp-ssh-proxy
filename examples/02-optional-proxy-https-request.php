<?php

// A simple example which uses an HTTP client to request https://example.com/ (optional: Through an SSH proxy server.)
//
// The proxy can be given through the SSH_PROXY env or does not use a proxy otherwise.
// You can assign the SSH_PROXY environment and prefix this with a space to make
// sure your login credentials are not stored in your bash history like this:
//
// $  export SSH_PROXY=alice:password@example.com
// $ php examples/02-optional-proxy-https-request.php

require __DIR__ . '/../vendor/autoload.php';

// SSH_PROXY environment given? use this as the proxy URL
$connector = null;
if (getenv('SSH_PROXY') !== false) {
    $proxy = new Clue\React\SshProxy\SshProcessConnector(getenv('SSH_PROXY'));

    $connector = new React\Socket\Connector(array(
        'tcp' => $proxy,
        'timeout' => 3.0,
        'dns' => false
    ));
}

$browser = new React\Http\Browser($connector);

$browser->get('https://example.com/')->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump($response->getHeaders(), (string) $response->getBody());
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
