<?php

namespace Clue\Tests\React\SshProxy;

use Clue\React\SshProxy\SshSocksConnector;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class IntegrationSshSocksConnectorTest extends TestCase
{
    public static function providePortListenerMessage()
    {
        return array(
            'OpenSSH < 9.2' => array(
                'debug1: channel 0: new [port listener]'
            ),
            'OpenSSH 9.2+' => array(
                'debug1: channel 0: new port-listener [port listener] (inactive timeout: 0)'
            ),
            'OpenSSH 10.6+' => array(
                'debug1: channel 0: new port-listener [port listener] (inactive timeout: 0.000000)'
            )
        );
    }

    /**
     * @dataProvider providePortListenerMessage
     * @param string $line
     */
    public function testConnectWillInvokeSocksConnectorWhenProcessOutputsPortListenerMessage($line)
    {
        $connector = new SshSocksConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, 'echo "' . $line . '" >&2; #');

        $deferred = new Deferred();
        $socks = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $socks->expects($this->once())->method('connect')->with('example.com:80')->willReturn($deferred->promise());

        $ref = new \ReflectionProperty($connector, 'socks');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, $socks);

        $connector->connect('example.com:80');

        Loop::run();
    }

    public function testConnectWillRejectWithExceptionAndNotInvokeSocksConnectorWhenProcessEndsWithoutPortListenerMessage()
    {
        $connector = new SshSocksConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, 'echo "debug1: channel 1: new dynamic-tcpip [dynamic-tcpip] (inactive timeout: 0)" >&2; #');

        $socks = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $socks->expects($this->never())->method('connect');

        $ref = new \ReflectionProperty($connector, 'socks');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, $socks);

        $promise = $connector->connect('example.com:80');
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        Loop::run();
    }

    public function testConnectWillRejectWithExceptionAndTerminateIdleProcessWhenSocksConnectorRejects()
    {
        $connector = new SshSocksConnector('host');

        $ref = new \ReflectionProperty($connector, 'cmd');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, 'echo "debug1: channel 0: new [port listener]" >&2; exec sleep 10; #');

        $socks = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $socks->expects($this->once())->method('connect')->willReturn(\React\Promise\reject(new \RuntimeException('Connection refused')));

        $ref = new \ReflectionProperty($connector, 'socks');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue($connector, $socks);

        $promise = $connector->connect('example.com:80');
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        $ref = new \ReflectionProperty($connector, 'listen');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $listen = $ref->getValue($connector);
        assert($listen instanceof PromiseInterface);

        $process = null;
        $listen->then(function ($value) use (&$process) {
            $process = $value;
        });

        Loop::run();

        $this->assertInstanceOf('React\ChildProcess\Process', $process);
        $this->assertTrue($process->isTerminated());
    }
}
