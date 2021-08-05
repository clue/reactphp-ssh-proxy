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
        $ref->setAccessible(true);
        $ref->setValue($connector, 'echo "debug2: channel 0: open confirm rwindow 2097152 rmax 32768" >&2; #');

        $promise = $connector->connect('example.com:80');
        $promise->then($this->expectCallableOnceWith($this->isInstanceOf('React\Socket\ConnectionInterface')));

        Loop::run();
    }

    public function testConnectWillRejectWithExceptionWhenProcessOutputsChannelOpenFailedMessage()
    {
        $connector = new SshProcessConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);
        $ref->setValue($connector, 'echo "channel 0: open failed: administratively prohibited: open failed" >&2; #');

        $promise = $connector->connect('example.com:80');
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        Loop::run();
    }

    public function testConnectWillRejectWithExceptionWhenProcessOutputsEndsWithoutChannelMessage()
    {
        $connector = new SshProcessConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);
        $ref->setValue($connector, 'echo foo >&2; #');

        $promise = $connector->connect('example.com:80');
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        Loop::run();
    }

    public function testConnectWillResolveWithConnectionThatWillEmitImmediateDataFromProcessStdoutAfterChannelOpenConfirmMessage()
    {
        $connector = new SshProcessConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);
        $ref->setValue($connector, 'echo "debug2: channel 0: open confirm rwindow 2097152 rmax 32768" >&2; echo foo #');

        $promise = $connector->connect('example.com:80');

        $data = $this->expectCallableOnceWith("foo\n");
        $promise->then(function (ConnectionInterface $connection) use ($data) {
            $connection->on('data', $data);
        });

        Loop::run();
    }
}
