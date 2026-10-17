<?php

namespace Clue\Tests\React\SshProxy;

use Clue\React\SshProxy\SshProcessConnector;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;

class IntegrationSshProcessConnectorTest extends TestCase
{
    public function testConnectWillResolveWithConnectionInterfaceWhenProcessOutputsChannelOpenConfirmMessage()
    {
        $connector = new SshProcessConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, 'echo "debug2: channel 0: open confirm rwindow 2097152 rmax 32768" >&2; #');

        $promise = $connector->connect('example.com:80');
        $promise->then($this->expectCallableOnceWith($this->isInstanceOf('React\Socket\ConnectionInterface')));

        Loop::run();
    }

    public function testConnectWillRejectWithExceptionWhenProcessOutputsChannelOpenFailedMessage()
    {
        $connector = new SshProcessConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, 'echo "channel 0: open failed: administratively prohibited: open failed" >&2; #');

        $promise = $connector->connect('example.com:80');
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        Loop::run();
    }

    public function testConnectWillRejectWithExceptionWhenProcessOutputsEndsWithoutChannelMessage()
    {
        $connector = new SshProcessConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, 'echo foo >&2; #');

        $promise = $connector->connect('example.com:80');
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        Loop::run();
    }

    public function testConnectWillRejectWithExceptionWhenProcessOutputsEndsAfterChannelMessageWithoutOpenConfirm()
    {
        $connector = new SshProcessConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, 'echo "debug1: channel 0: new stdio-forward [stdio-forward] (inactive timeout: 0)" >&2; #');

        $promise = $connector->connect('example.com:80');
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        Loop::run();
    }

    public function testConnectCancellationWillRejectWithException()
    {
        $connector = new SshProcessConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, 'exec sleep 10; #');

        $promise = $connector->connect('example.com:80');
        $promise->cancel();

        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        Loop::run();
    }

    public function testConnectWillRejectWithExceptionAndTerminateProcessWhenProcessOutputsLineExceedingMaximumBufferSize()
    {
        $connector = new SshProcessConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, 'head -c 70000 /dev/zero | tr "\0" a >&2; exec sleep 10; #');

        $promise = $connector->connect('example.com:80');

        $this->setExpectedException('RuntimeException', 'Connection to example.com:80 failed because SSH client died (Buffer size exceeded)');
        \React\Async\await($promise);
    }

    public function testConnectWillResolveWithConnectionThatWillEmitImmediateDataFromProcessStdoutAfterChannelOpenConfirmMessage()
    {
        $connector = new SshProcessConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, 'echo "debug2: channel 0: open confirm rwindow 2097152 rmax 32768" >&2; echo foo #');

        $promise = $connector->connect('example.com:80');

        $data = $this->expectCallableOnceWith("foo\n");
        $promise->then(function (ConnectionInterface $connection) use ($data) {
            $connection->on('data', $data);
        });

        Loop::run();
    }
}
