<?php

// A more advanced example to show how a MySQL server can be accessed through an SSH tunnel.
// The SSH proxy can be given through the SSH_PROXY env and defaults to localhost otherwise.
// The MySQL server can be given through the MYSQL_LOGIN env and default to localhost otherwise.
//
// You can assign the SSH_PROXY and MYSQL_LOGIN environment and prefix this with
// a space to make sure your login credentials are not stored in your bash
// history like this:
//
// $  export SSH_PROXY=user:secret@example.com
// $  export MYSQL_LOGIN=user:password@localhost
// $ php examples/21-mysql-ssh-tunnel.php
//
// See also https://github.com/friends-of-reactphp/mysql

use Clue\React\SshProxy\SshProcessConnector;
use React\MySQL\Factory;
use React\MySQL\QueryResult;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$url = getenv('SSH_PROXY') !== false ? getenv('SSH_PROXY') : 'ssh://localhost:22';
$proxy = new SshProcessConnector($url, $loop);

$url = getenv('MYSQL_LOGIN') !== false ? getenv('MYSQL_LOGIN') : 'user:pass@localhost';
$factory = new Factory($loop, $proxy);
$client = $factory->createLazyConnection($url);

$client->query('SELECT * FROM (SELECT "foo" UNION SELECT "bar") data')->then(function (QueryResult $query) {
    var_dump($query->resultRows);
}, 'printf');
$client->quit();

$loop->run();
