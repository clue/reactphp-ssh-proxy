<?php

namespace Clue\Tests\React\SshProxy;

use Clue\React\SshProxy\SshSocksConnector;

class FunctionalSshSocksConnectorTest extends TestCase
{
    const TIMEOUT = 10.0;

    private $connector;

    /**
     * @before
     */
    public function setUpConnector()
    {
        $url = getenv('SSH_PROXY');
        if ($url === false) {
            $this->markTestSkipped('No SSH_PROXY env set');
        }

        $this->connector = new SshSocksConnector($url);
    }

    /**
     * @after
     */
    public function tearDownSSHClientProcess()
    {
        // run loop in order to shut down SSH client process again
        \React\Async\await(\React\Promise\Timer\sleep(0.001));
    }

    public function testConnectInvalidProxyUriWillReturnRejectedPromise()
    {
        $this->connector = new SshSocksConnector(getenv('SSH_PROXY') . '.invalid');

        $promise = $this->connector->connect('example.com:80');

        $this->setExpectedException('RuntimeException', 'Connection to example.com:80 failed because SSH client process died');
        \React\Async\await(\React\Promise\Timer\timeout($promise, self::TIMEOUT));
    }

    public function testConnectInvalidTargetWillReturnRejectedPromise()
    {
        $promise = $this->connector->connect('example.invalid:80');

        $this->setExpectedException('RuntimeException', 'Connection to tcp://example.invalid:80 failed because connection to proxy was lost');
        \React\Async\await(\React\Promise\Timer\timeout($promise, self::TIMEOUT));
    }

    public function testCancelConnectWillReturnRejectedPromise()
    {
        $promise = $this->connector->connect('example.com:80');
        $promise->cancel();

        $this->setExpectedException('RuntimeException', 'Connection to example.com:80 cancelled while waiting for SSH client');
        \React\Async\await(\React\Promise\Timer\timeout($promise, 0));
    }

    public function testConnectValidTargetWillReturnPromiseWhichResolvesToConnection()
    {
        $promise = $this->connector->connect('example.com:80');

        $connection = \React\Async\await(\React\Promise\Timer\timeout($promise, self::TIMEOUT));

        $this->assertInstanceOf('React\Socket\ConnectionInterface', $connection);
        $this->assertTrue($connection->isReadable());
        $this->assertTrue($connection->isWritable());
        $connection->close();
    }

    public function testConnectValidTargetWillReturnPromiseWhichResolvesToConnectionForCustomBindAddress()
    {
        $this->connector = new SshSocksConnector(getenv('SSH_PROXY') . '?bind=127.0.0.1:1081');
        $promise = $this->connector->connect('example.com:80');

        $connection = \React\Async\await(\React\Promise\Timer\timeout($promise, self::TIMEOUT));

        $this->assertInstanceOf('React\Socket\ConnectionInterface', $connection);
        $this->assertTrue($connection->isReadable());
        $this->assertTrue($connection->isWritable());
        $connection->close();
    }

    public function testConnectPendingWillNotInheritActiveFileDescriptors()
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($server, false);

        // ensure that we can not listen on the same address twice
        $copy = @stream_socket_server('tcp://' . $address);
        if ($copy !== false) {
            fclose($server);
            fclose($copy);

            $this->markTestSkipped('Platform does not prevent binding to same address (Windows?)');
        }

        $promise = $this->connector->connect('example.com:80');

        // close server and ensure we can start a new server on the previous address
        // the pending SSH connection process should not inherit the existing server socket
        fclose($server);

        $server = @stream_socket_server('tcp://' . $address);
        if ($server === false) {
            // There's a very short race condition where the forked php process
            // first has to `dup()` the file descriptor specs before invoking
            // `exec()` to switch to the actual `ssh` child process. We don't
            // need to wait for the child process to be ready, but only for the
            // forked process to close the file descriptors. This happens ~80%
            // of times on single core machines and almost never on multi core
            // systems, so simply wait 5ms (plenty of time!) and retry again twice.
            usleep(5000);
            $server = @stream_socket_server('tcp://' . $address);

            if ($server === false) {
                usleep(5000);
                $server = stream_socket_server('tcp://' . $address);
            }
        }

        $this->assertTrue(is_resource($server));
        fclose($server);

        $promise->cancel();
    }
}
