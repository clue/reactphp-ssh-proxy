<?php

namespace Clue\Tests\React\SshProxy;

use Clue\React\SshProxy\SshProcessConnector;
use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;

class IntegrationSshProcessConnectorTest extends TestCase
{
    public function testConnectWillResolveWithConnectionInterfaceWhenProcessOutputsChannelOpenConfirmMessage()
    {
        $loop = Factory::create();
        $connector = new SshProcessConnector('host', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);
        $ref->setValue($connector, 'echo "debug2: channel 0: open confirm rwindow 2097152 rmax 32768" >&2; #');

        $promise = $connector->connect('example.com:80');
        $promise->then($this->expectCallableOnceWith($this->isInstanceOf('React\Socket\ConnectionInterface')));

        $loop->run();
    }

    public function testConnectWillRejectWithExceptionWhenProcessOutputsChannelOpenFailedMessage()
    {
        $loop = Factory::create();
        $connector = new SshProcessConnector('host', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);
        $ref->setValue($connector, 'echo "channel 0: open failed: administratively prohibited: open failed" >&2; #');

        $promise = $connector->connect('example.com:80');
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        $loop->run();
    }

    public function testConnectWillRejectWithExceptionWhenProcessOutputsEndsWithoutChannelMessage()
    {
        $loop = Factory::create();
        $connector = new SshProcessConnector('host', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);
        $ref->setValue($connector, 'echo foo >&2; #');

        $promise = $connector->connect('example.com:80');
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        $loop->run();
    }

    public function testConnectWillResolveWithConnectionThatWillEmitImmediateDataFromProcessStdoutAfterChannelOpenConfirmMessage()
    {
        $loop = Factory::create();
        $connector = new SshProcessConnector('host', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);
        $ref->setValue($connector, 'echo "debug2: channel 0: open confirm rwindow 2097152 rmax 32768" >&2; echo foo #');

        $promise = $connector->connect('example.com:80');

        $data = $this->expectCallableOnceWith("foo\n");
        $promise->then(function (ConnectionInterface $connection) use ($data) {
            $connection->on('data', $data);
        });

        $loop->run();
    }
}
