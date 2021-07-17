# clue/reactphp-ssh-proxy

[![CI status](https://github.com/clue/reactphp-ssh-proxy/workflows/CI/badge.svg)](https://github.com/clue/reactphp-ssh-proxy/actions)
[![Packagist Downloads](https://img.shields.io/packagist/dt/clue/reactphp-ssh-proxy?color=blue)](https://packagist.org/packages/clue/reactphp-ssh-proxy)

Async SSH proxy connector and forwarder, tunnel any TCP/IP-based protocol through an SSH server,
built on top of [ReactPHP](https://reactphp.org).

[Secure Shell (SSH)](https://en.wikipedia.org/wiki/Secure_Shell) is a secure
network protocol that is most commonly used to access a login shell on a remote
server. Its architecture allows it to use multiple secure channels over a single
connection. Among others, this can also be used to create an "SSH tunnel", which
is commonly used to tunnel HTTP(S) traffic through an intermediary ("proxy"), to
conceal the origin address (anonymity) or to circumvent address blocking
(geoblocking). This can be used to tunnel any TCP/IP-based protocol (HTTP, SMTP,
IMAP etc.) and as such also allows you to access local services that are otherwise
not accessible from the outside (database behind firewall).
This library is implemented as a lightweight process wrapper around the `ssh` client
binary and provides a simple API to create these tunneled connections for you.
Because it implements ReactPHP's standard
[`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface),
it can simply be used in place of a normal connector.
This makes it fairly simple to add SSH proxy support to pretty much any
existing higher-level protocol implementation.

* **Async execution of connections** -
  Send any number of SSH proxy requests in parallel and process their
  responses as soon as results come in.
  The Promise-based design provides a *sane* interface to working with out of
  order responses and possible connection errors.
* **Standard interfaces** -
  Allows easy integration with existing higher-level components by implementing
  ReactPHP's standard
  [`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface).
* **Lightweight, SOLID design** -
  Provides a thin abstraction that is [*just good enough*](https://en.wikipedia.org/wiki/Principle_of_good_enough)
  and does not get in your way.
  Builds on top of well-tested components and well-established concepts instead of reinventing the wheel.
* **Good test coverage** -
  Comes with an automated tests suite and is regularly tested against actual SSH servers in the wild.

**Table of contents**

* [Support us](#support-us)
* [Quickstart example](#quickstart-example)
* [API](#api)
    * [SshProcessConnector](#sshprocessconnector)
    * [SshSocksConnector](#sshsocksconnector)
* [Usage](#usage)
    * [Plain TCP connections](#plain-tcp-connections)
    * [Secure TLS connections](#secure-tls-connections)
    * [HTTP requests](#http-requests)
    * [Database tunnel](#database-tunnel)
    * [Connection timeout](#connection-timeout)
    * [DNS resolution](#dns-resolution)
    * [Password authentication](#password-authentication)
* [Install](#install)
* [Tests](#tests)
* [License](#license)
* [More](#more)

## Support us

We invest a lot of time developing, maintaining and updating our awesome
open-source projects. You can help us sustain this high-quality of our work by
[becoming a sponsor on GitHub](https://github.com/sponsors/clue). Sponsors get
numerous benefits in return, see our [sponsoring page](https://github.com/sponsors/clue)
for details.

Let's take these projects to the next level together! 🚀

## Quickstart example

The following example code demonstrates how this library can be used to send a
plaintext HTTP request to google.com through a remote SSH server:

```php
$proxy = new Clue\React\SshProxy\SshProcessConnector('user@example.com');

$connector = new React\Socket\Connector(null, array(
    'tcp' => $proxy,
    'dns' => false
));

$connector->connect('tcp://google.com:80')->then(function (React\Socket\ConnectionInterface $connection) {
    $connection->write("GET / HTTP/1.1\r\nHost: google.com\r\nConnection: close\r\n\r\n");
    $connection->on('data', function ($chunk) {
        echo $chunk;
    });
    $connection->on('close', function () {
        echo '[DONE]';
    });
}, 'printf');
```

See also the [examples](examples).

## API

### SshProcessConnector

The `SshProcessConnector` is responsible for creating plain TCP/IP connections to
any destination by using an intermediary SSH server as a proxy server.

```
[you] -> [proxy] -> [destination]
```

This class is implemented as a lightweight process wrapper around the `ssh`
client binary, so it will spawn one `ssh` process for each connection. For
example, if you [open a connection](#plain-tcp-connections) to
`tcp://reactphp.org:80`, it will run the equivalent of `ssh -W reactphp.org:80 user@example.com`
and forward data from its standard I/O streams. For this to work, you'll have to
make sure that you have a suitable SSH client installed. On Debian/Ubuntu-based
systems, you may simply install it like this:

```bash
$ sudo apt install openssh-client
```

Its constructor simply accepts an SSH proxy server URL:

```php
$proxy = new Clue\React\SshProxy\SshProcessConnector('user@example.com');
```

The proxy URL may or may not contain a scheme and port definition. The default
port will be `22` for SSH, but you may have to use a custom port depending on
your SSH server setup.

This class takes an optional `LoopInterface|null $loop` parameter that can be used to
pass the event loop instance to use for this object. You can use a `null` value
here in order to use the [default loop](https://github.com/reactphp/event-loop#loop).
This value SHOULD NOT be given unless you're sure you want to explicitly use a
given event loop instance.

Keep in mind that this class is implemented as a lightweight process wrapper
around the `ssh` client binary and that it will spawn one `ssh` process for each
connection. If you open more connections, it will spawn one `ssh` process for
each connection. Each process will take some time to create a new SSH connection
and then keep running until the connection is closed, so you're recommended to
limit the total number of concurrent connections. If you plan to only use a
single or few connections (such as a single database connection), using this
class is the recommended approach. If you plan to create multiple connections or
have a larger number of connections (such as an HTTP client), you're recommended
to use the [`SshSocksConnector`](#sshsocksconnector) instead.

This is one of the two main classes in this package.
Because it implements ReactPHP's standard
[`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface),
it can simply be used in place of a normal connector.
Accordingly, it provides only a single public method, the
[`connect()`](https://github.com/reactphp/socket#connect) method.
The `connect(string $uri): PromiseInterface<ConnectionInterface, Exception>`
method can be used to establish a streaming connection.
It returns a [Promise](https://github.com/reactphp/promise) which either
fulfills with a [ConnectionInterface](https://github.com/reactphp/socket#connectioninterface)
on success or rejects with an `Exception` on error.

This makes it fairly simple to add SSH proxy support to pretty much any
higher-level component:

```diff
- $acme = new AcmeApi($connector);
+ $proxy = new Clue\React\SshProxy\SshProcessConnector('user@example.com');
+ $acme = new AcmeApi($proxy);
```

### SshSocksConnector

The `SshSocksConnector` is responsible for creating plain TCP/IP connections to
any destination by using an intermediary SSH server as a proxy server.

```
[you] -> [proxy] -> [destination]
```

This class is implemented as a lightweight process wrapper around the `ssh`
client binary and it will spawn one `ssh` process on demand for multiple
connections. For example, once you [open a connection](#plain-tcp-connections)
to `tcp://reactphp.org:80` for the first time, it will run the equivalent of
`ssh -D 1080 user@example.com` to run the SSH client in local SOCKS proxy server
mode and will then create a SOCKS client connection to this server process. You
can create any number of connections over this one process and it will keep this
process running while there are any open connections and will automatically
close it when it is idle. For this to work, you'll have to make sure that you
have a suitable SSH client installed. On Debian/Ubuntu-based systems, you may
simply install it like this:

```bash
$ sudo apt install openssh-client
```

Its constructor simply accepts an SSH proxy server URL:

```php
$proxy = new Clue\React\SshProxy\SshSocksConnector('user@example.com');
```

The proxy URL may or may not contain a scheme and port definition. The default
port will be `22` for SSH, but you may have to use a custom port depending on
your SSH server setup.

This class takes an optional `LoopInterface|null $loop` parameter that can be used to
pass the event loop instance to use for this object. You can use a `null` value
here in order to use the [default loop](https://github.com/reactphp/event-loop#loop).
This value SHOULD NOT be given unless you're sure you want to explicitly use a
given event loop instance.

Keep in mind that this class is implemented as a lightweight process wrapper
around the `ssh` client binary and that it will spawn one `ssh` process for
multiple connections. This process will take some time to create a new SSH
connection and then keep running until the last connection is closed. If you
plan to create multiple connections or have a larger number of concurrent
connections (such as an HTTP client), using this class is the recommended
approach. If you plan to only use a single or few connections (such as a single
database connection), you're recommended to use the [`SshProcessConnector`](#sshprocessconnector)
instead.

This class defaults to spawning the SSH client process in SOCKS proxy server
mode listening on `127.0.0.1:1080`. If this port is already in use or if you want
to use multiple instances of this class to connect to different SSH proxy
servers, you may optionally pass a unique bind address like this:

```php
$proxy = new Clue\React\SshProxy\SshSocksConnector('user@example.com?bind=127.1.1.1:1081',);
```

> *Security note for multi-user systems*: This class will spawn the SSH client
  process in local SOCKS server mode and will accept connections only on the
  localhost interface by default. If you're running on a multi-user system,
  other users on the same system may be able to connect to this proxy server and
  create connections over it. If this applies to your deployment, you're
  recommended to use the [`SshProcessConnector](#sshprocessconnector) instead
  or set up custom firewall rules to prevent unauthorized access to this port.

This is one of the two main classes in this package.
Because it implements ReactPHP's standard
[`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface),
it can simply be used in place of a normal connector.
Accordingly, it provides only a single public method, the
[`connect()`](https://github.com/reactphp/socket#connect) method.
The `connect(string $uri): PromiseInterface<ConnectionInterface, Exception>`
method can be used to establish a streaming connection.
It returns a [Promise](https://github.com/reactphp/promise) which either
fulfills with a [ConnectionInterface](https://github.com/reactphp/socket#connectioninterface)
on success or rejects with an `Exception` on error.

This makes it fairly simple to add SSH proxy support to pretty much any
higher-level component:

```diff
- $acme = new AcmeApi($connector);
+ $proxy = new Clue\React\SshProxy\SshSocksConnector('user@example.com');
+ $acme = new AcmeApi($proxy);
```

## Usage

### Plain TCP connections

SSH proxy servers are commonly used to issue HTTPS requests to your destination.
However, this is actually performed on a higher protocol layer and this
project is actually inherently a general-purpose plain TCP/IP connector.
As documented above, you can simply invoke the `connect()` method to establish
a streaming plain TCP/IP connection on the `SshProcessConnector` or `SshSocksConnector`
and use any higher level protocol like so:

```php
$proxy = new Clue\React\SshProxy\SshProcessConnector('user@example.com');
// or
$proxy = new Clue\React\SshProxy\SshSocksConnector('user@example.com');

$proxy->connect('tcp://smtp.googlemail.com:587')->then(function (React\Socket\ConnectionInterface $connection) {
    $connection->write("EHLO local\r\n");
    $connection->on('data', function ($chunk) use ($connection) {
        echo $chunk;
    });
});
```

You can either use the `SshProcessConnector` or `SshSocksConnector` directly or you
may want to wrap this connector in ReactPHP's [`Connector`](https://github.com/reactphp/socket#connector):

```php
$proxy = new Clue\React\SshProxy\SshProcessConnector('user@example.com');
// or
$proxy = new Clue\React\SshProxy\SshSocksConnector('user@example.com');

$connector = new React\Socket\Connector(null, array(
    'tcp' => $proxy,
    'dns' => false
));

$connector->connect('tcp://smtp.googlemail.com:587')->then(function (React\Socket\ConnectionInterface $connection) {
    $connection->write("EHLO local\r\n");
    $connection->on('data', function ($chunk) use ($connection) {
        echo $chunk;
    });
});
```

For this example, you can use either the `SshProcessConnector` or `SshSocksConnector`.
Keep in mind that this project is implemented as a lightweight process wrapper
around the `ssh` client binary. While the `SshProcessConnector` will spawn one
`ssh` process for each connection, the `SshSocksConnector` will spawn one `ssh`
process that will be shared for multiple connections, see also above for more
details.

### Secure TLS connections

The `SshSocksConnector` can also be used if you want to establish a secure TLS connection
(formerly known as SSL) between you and your destination, such as when using
secure HTTPS to your destination site. You can simply wrap this connector in
ReactPHP's [`Connector`](https://github.com/reactphp/socket#connector) or the
low-level [`SecureConnector`](https://github.com/reactphp/socket#secureconnector):

```php
$proxy = new Clue\React\SshProxy\SshSocksConnector('user@example.com');

$connector = new React\Socket\Connector(null, array(
    'tcp' => $proxy,
    'dns' => false
));

$connector->connect('tls://smtp.googlemail.com:465')->then(function (React\Socket\ConnectionInterface $connection) {
    $connection->write("EHLO local\r\n");
    $connection->on('data', function ($chunk) use ($connection) {
        echo $chunk;
    });
});
```

> Note how secure TLS connections are in fact entirely handled outside of
  this SSH proxy client implementation.
  The `SshProcessConnector` does not currently support secure TLS connections
  because PHP's underlying crypto functions require a socket resource and do not
  work for virtual connections. As an alternative, you're recommended to use the
  `SshSocksConnector` as given in the above example.

### HTTP requests

This library also allows you to send
[HTTP requests through an SSH proxy server](https://github.com/reactphp/http#ssh-proxy).

In order to send HTTP requests, you first have to add a dependency for
[ReactPHP's async HTTP client](https://github.com/reactphp/http#client-usage).
This allows you to send both plain HTTP and TLS-encrypted HTTPS requests like this:

```php
$proxy = new Clue\React\SshProxy\SshSocksConnector('user@example.com');

$connector = new React\Socket\Connector(null, array(
    'tcp' => $proxy,
    'dns' => false
));

$browser = new React\Http\Browser(null, $connector);

$browser->get('https://example.com/')->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump($response->getHeaders(), (string) $response->getBody());
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
```

We recommend using the `SshSocksConnector`, this works for both plain HTTP
and TLS-encrypted HTTPS requests. When using the `SshProcessConnector`, this only
works for plaintext HTTP requests.

See also [ReactPHP's HTTP client](https://github.com/reactphp/http#client-usage)
and any of the [examples](examples) for more details.

### Database tunnel

We should now have a basic understanding of how we can tunnel any TCP/IP-based
protocol over an SSH proxy server. Besides using this to access "external"
services, this is also particularly useful because it allows you to access
network services otherwise only local to this SSH server from the outside, such
as a firewalled database server.

For example, this allows us to combine an
[async MySQL database client](https://github.com/friends-of-reactphp/mysql) and
the above SSH proxy server setup, so we can access a firewalled MySQL database
server through an SSH tunnel. Here's the gist:

```php
$proxy = new Clue\React\SshProxy\SshProcessConnector('user@example.com');

$uri = 'test:test@localhost/test';
$factory = new React\MySQL\Factory(null, $proxy);
$connection = $factory->createLazyConnection($uri);

$connection->query('SELECT * FROM book')->then(
    function (React\MySQL\QueryResult $command) {
        echo count($command->resultRows) . ' row(s) in set' . PHP_EOL;
    },
    function (Exception $error) {
        echo 'Error: ' . $error->getMessage() . PHP_EOL;
    }
);

$connection->quit();
```

See also [example #21](examples) for more details.

This example will automatically launch the `ssh` client binary to create the
connection to a database server that can not otherwise be accessed from the
outside. From the perspective of the database server, this looks just like a
regular, local connection. From this code's perspective, this will create a
regular, local connection which just happens to use a secure SSH tunnel to
transport this to a remote server, so you can send any query like you would to a
local database server.

### Connection timeout

By default, neither the `SshProcessConnector` nor the `SshSocksConnector` implement
any timeouts for establishing remote connections.
Your underlying operating system may impose limits on pending and/or idle TCP/IP
connections, anywhere in a range of a few minutes to several hours.

Many use cases require more control over the timeout and likely values much
smaller, usually in the range of a few seconds only.

You can use ReactPHP's [`Connector`](https://github.com/reactphp/socket#connector)
or the low-level
[`TimeoutConnector`](https://github.com/reactphp/socket#timeoutconnector)
to decorate any given `ConnectorInterface` instance.
It provides the same `connect()` method, but will automatically reject the
underlying connection attempt if it takes too long:

```php
$proxy = new Clue\React\SshProxy\SshProcessConnector('user@example.com');
// or
$proxy = new Clue\React\SshProxy\SshSocksConnector('user@example.com');

$connector = new React\Socket\Connector(null, array(
    'tcp' => $proxy,
    'dns' => false,
    'timeout' => 3.0
));

$connector->connect('tcp://google.com:80')->then(function (React\Socket\ConnectionInterface $connection) {
    // connection succeeded within 3.0 seconds
});
```

See also any of the [examples](examples).

> Note how the connection timeout is in fact entirely handled outside of this
  SSH proxy client implementation.

### DNS resolution

By default, neither the `SshProcessConnector` nor the `SshSocksConnector` perform
any DNS resolution at all and simply forward any hostname you're trying to
connect to the remote proxy server. The remote proxy server is thus responsible
for looking up any hostnames via DNS (this default mode is thus called *remote DNS resolution*).

As an alternative, you can also send the destination IP to the remote proxy
server.
In this mode you either have to stick to using IPs only (which is ofen unfeasable)
or perform any DNS lookups locally and only transmit the resolved destination IPs
(this mode is thus called *local DNS resolution*).

The default *remote DNS resolution* is useful if your local `SshProcessConnector` 
or `SshSocksConnector` either can not resolve target hostnames because it has no
direct access to the internet or if it should not resolve target hostnames
because its outgoing DNS traffic might be intercepted.

As noted above, the `SshProcessConnector` and `SshSocksConnector` default to using
remote DNS resolution. However, wrapping them in ReactPHP's
[`Connector`](https://github.com/reactphp/socket#connector) actually
performs local DNS resolution unless explicitly defined otherwise.
Given that remote DNS resolution is assumed to be the preferred mode, all
other examples explicitly disable DNS resolution like this:

```php
$proxy = new Clue\React\SshProxy\SshProcessConnector('user@example.com');
// or
$proxy = new Clue\React\SshProxy\SshSocksConnector('user@example.com');

$connector = new React\Socket\Connector(null, array(
    'tcp' => $proxy,
    'dns' => false
));
```

If you want to explicitly use *local DNS resolution*, you can use the following code:

```php
$proxy = new Clue\React\SshProxy\SshProcessConnector('user@example.com');
// or
$proxy = new Clue\React\SshProxy\SshSocksConnector('user@example.com');

// set up Connector which uses Google's public DNS (8.8.8.8)
$connector = new React\Socket\Connector(null, array(
    'tcp' => $proxy,
    'dns' => '8.8.8.8'
));
```

> Note how local DNS resolution is in fact entirely handled outside of this
  SSH proxy client implementation.

### Password authentication

Note that this class is implemented as a lightweight process wrapper around the
`ssh` client binary. It works under the assumption that you have verified you
can access your SSH proxy server on the command line like this:

```bash
# test SSH access
$ ssh user@example.com echo hello
```

Because this class is designed to be used to create any number of connections,
it does not provide a way to interactively ask for your password. Similarly,
the `ssh` client binary does not provide a way to "pass" in the password on the
command line for security reasons. This means that you are highly recommended to
set up pubkey-based authentication without a password for this to work best.

Additionally, this library provides a way to pass in a password in a somewhat
less secure way if your use case absolutely requires this. Before proceeding,
please consult your SSH documentation to find out why this may be a bad idea and
why pubkey-based authentication is usually the better alternative.
If your SSH proxy server requires password authentication, you may pass the
username and password as part of the SSH proxy server URL like this:

```php
$proxy = new Clue\React\SshProxy\SshProcessConnector('user:pass@example.com');
// or
$proxy = new Clue\React\SshProxy\SshSocksConnector('user:pass@example.com');
```

For this to work, you will have to have the `sshpass` binary installed. On
Debian/Ubuntu-based systems, you may simply install it like this:

```bash
$ sudo apt install sshpass
```

Note that both the username and password must be percent-encoded if they contain
special characters:

```php
$user = 'he:llo';
$pass = 'p@ss';

$proxy = new Clue\React\SshProxy\SshProcessConnector(
    rawurlencode($user) . ':' . rawurlencode($pass) . '@example.com:2222'
);
```

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This project follows [SemVer](https://semver.org/).
This will install the latest supported version:

```bash
$ composer require clue/reactphp-ssh-proxy:^1.2
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 8+ and
HHVM.
It's *highly recommended to use PHP 7+* for this project.

This project is implemented as a lightweight process wrapper around the `ssh`
client binary, so you'll have to make sure that you have a suitable SSH client
installed. On Debian/Ubuntu-based systems, you may simply install it like this:

```bash
$ sudo apt install openssh-client
```

Additionally, if you use [password authentication](#password-authentication)
(not recommended), then you will have to have the `sshpass` binary installed. On
Debian/Ubuntu-based systems, you may simply install it like this:

```bash
$ sudo apt install sshpass
```

*Running on [Windows is currently not supported](https://github.com/reactphp/child-process/issues/9)*

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

The test suite contains a number of tests that require an actual SSH proxy server.
These tests will be skipped unless you configure your SSH login credentials to
be able to create some actual test connections. You can assign the `SSH_PROXY`
environment and prefix this with a space to make sure your login credentials are
not stored in your bash history like this:

```bash
$  export SSH_PROXY=user:secret@example.com
$ php vendor/bin/phpunit --exclude-group internet
```

## License

This project is released under the permissive [MIT license](LICENSE).

> Did you know that I offer custom development services and issuing invoices for
  sponsorships of releases and for contributions? Contact me (@clue) for details.

## More

* If you want to learn more about how the
  [`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface)
  and its usual implementations look like, refer to the documentation of the underlying
  [react/socket](https://github.com/reactphp/socket) component.
* If you want to learn more about processing streams of data, refer to the
  documentation of the underlying
  [react/stream](https://github.com/reactphp/stream) component.
* As an alternative to an SSH proxy server, you may also want to look into
  using a SOCKS5 or SOCKS4(a) proxy instead.
  You may want to use [clue/reactphp-socks](https://github.com/clue/reactphp-socks)
  which also provides an implementation of the same
  [`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface)
  so that supporting either proxy protocol should be fairly trivial.
* As another alternative to an SSH proxy server, you may also want to look into
  using an HTTP CONNECT proxy instead.
  You may want to use [clue/reactphp-http-proxy](https://github.com/clue/reactphp-http-proxy)
  which also provides an implementation of the same
  [`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface)
