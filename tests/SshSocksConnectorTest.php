<?php

namespace Clue\Tests\React\SshProxy;

use Clue\React\SshProxy\SshSocksConnector;
use React\Promise\Deferred;

class SshSocksConnectorTest extends TestCase
{
    public function testConstructorAcceptsUri()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshSocksConnector('host', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);

        $this->assertEquals('exec ssh -v -o ExitOnForwardFailure=yes -N -o BatchMode=yes -D \'127.0.0.1:1080\' \'host\'', $ref->getValue($connector));
    }

    public function testConstructorAcceptsUriWithDefaultPortWillNotBeAddedToCommand()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshSocksConnector('host:22', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);

        $this->assertEquals('exec ssh -v -o ExitOnForwardFailure=yes -N -o BatchMode=yes -D \'127.0.0.1:1080\' \'host\'', $ref->getValue($connector));
    }

    public function testConstructorAcceptsUriWithUserAndCustomPort()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshSocksConnector('user@host:2222', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);

        $this->assertEquals('exec ssh -v -o ExitOnForwardFailure=yes -N -o BatchMode=yes -p 2222 -D \'127.0.0.1:1080\' \'user@host\'', $ref->getValue($connector));
    }

    public function testConstructorAcceptsUriWithPasswordWillPrefixSshCommandWithSshpassAndWithoutBatchModeOption()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshSocksConnector('user:pass@host', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);

        $this->assertEquals('exec sshpass -p \'pass\' ssh -v -o ExitOnForwardFailure=yes -N -D \'127.0.0.1:1080\' \'user@host\'', $ref->getValue($connector));
    }

    public function testConstructorAcceptsUriWithCustomBindUrl()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshSocksConnector('host?bind=127.1.0.1:2711', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);

        $this->assertEquals('exec ssh -v -o ExitOnForwardFailure=yes -N -o BatchMode=yes -D \'127.1.0.1:2711\' \'host\'', $ref->getValue($connector));
    }

    public function testConstructorAcceptsUriWithCustomBindUrlIpv6()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshSocksConnector('host?bind=[::1]:2711', $loop);

        $ref = new \ReflectionProperty($connector, 'cmd');
        $ref->setAccessible(true);

        $this->assertEquals('exec ssh -v -o ExitOnForwardFailure=yes -N -o BatchMode=yes -D \'[::1]:2711\' \'host\'', $ref->getValue($connector));
    }

    public function testConstructorThrowsForInvalidUri()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $this->setExpectedException('InvalidArgumentException');
        new SshSocksConnector('///', $loop);
    }

    public function testConstructorThrowsForInvalidUser()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $this->setExpectedException('InvalidArgumentException');
        new SshSocksConnector('-invalid@host', $loop);
    }

    public function testConstructorThrowsForInvalidPass()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $this->setExpectedException('InvalidArgumentException');
        new SshSocksConnector('user:-invalid@host', $loop);
    }

    public function testConstructorThrowsForInvalidHost()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $this->setExpectedException('InvalidArgumentException');
        new SshSocksConnector('-host', $loop);
    }

    public function testConstructorThrowsForInvalidBindHost()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $this->setExpectedException('InvalidArgumentException');
        new SshSocksConnector('host?bind=example:1080', $loop);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testConstructorAcceptsHostWithLeadingDashWhenPrefixedWithUser()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshSocksConnector('user@-host', $loop);
    }

    public function testConnectReturnsRejectedPromiseForInvalidUri()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $connector = new SshSocksConnector('host', $loop);

        $promise = $connector->connect('///');
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('InvalidArgumentException')));
    }

    public function testConnectCancellationShouldReturnRejectedPromiseAndStartIdleTimer()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer');

        $connector = new SshSocksConnector('host', $loop);

        $promise = $connector->connect('google.com:80');
        $promise->cancel();

        $exception = null;
        $promise->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });

        $this->setExpectedException('RuntimeException', 'Connection to google.com:80 cancelled');
        throw $exception;
    }

    public function testConnectTwiceAndCancelOneShouldNotStartIdleTimer()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->never())->method('addTimer');

        $connector = new SshSocksConnector('host', $loop);

        $first = $connector->connect('google.com:80');
        $second = $connector->connect('google.com:80');
        $first->cancel();
    }

    public function testConnectTwiceAndCancelBothShouldStartIdleTimerOnce()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer');

        $connector = new SshSocksConnector('host', $loop);

        $first = $connector->connect('google.com:80');
        $second = $connector->connect('google.com:80');
        $first->cancel();
        $second->cancel();
    }

    public function testConnectWillCancelPendingIdleTimerAndWaitForSshListener()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('cancelTimer');

        $connector = new SshSocksConnector('host', $loop);

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        $ref = new \ReflectionProperty($connector, 'idleTimer');
        $ref->setAccessible(true);
        $ref->setValue($connector, $timer);

        $connector->connect('google.com:80');
    }

    public function testConnectWithFailingSshListenerShouldReturnRejectedPromise()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connector = new SshSocksConnector('host', $loop);

        $deferred = new Deferred();
        $ref = new \ReflectionProperty($connector, 'listen');
        $ref->setAccessible(true);
        $ref->setValue($connector, $deferred->promise());

        $promise = $connector->connect('google.com:80');

        $deferred->reject(new \RuntimeException('foobar'));

        $exception = null;
        $promise->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });

        $this->setExpectedException('RuntimeException', 'Connection to google.com:80 failed because SSH client process died (foobar)');
        throw $exception;
    }

    public function testConnectTwiceWithFailingSshListenerShouldRejectBothPromises()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connector = new SshSocksConnector('host', $loop);

        $deferred = new Deferred();
        $ref = new \ReflectionProperty($connector, 'listen');
        $ref->setAccessible(true);
        $ref->setValue($connector, $deferred->promise());

        $first = $connector->connect('google.com:80');
        $first->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        $second = $connector->connect('google.com:80');
        $second->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));

        $deferred->reject(new \InvalidArgumentException());
    }

    public function testConnectCancellationWithFailingSshListenerShouldAddTimerOnce()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer');

        $connector = new SshSocksConnector('host', $loop);

        $deferred = new Deferred();
        $ref = new \ReflectionProperty($connector, 'listen');
        $ref->setAccessible(true);
        $ref->setValue($connector, $deferred->promise());

        $promise = $connector->connect('google.com:80');
        $promise->cancel();

        $deferred->reject(new \InvalidArgumentException());
    }

    public function testConnectWithSuccessfulSshListenerWillInvokeSocksConnector()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connector = new SshSocksConnector('host', $loop);

        $ref = new \ReflectionProperty($connector, 'listen');
        $ref->setAccessible(true);
        $ref->setValue($connector, \React\Promise\resolve(null));

        $deferred = new Deferred();
        $socks = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $socks->expects($this->once())->method('connect')->with('google.com:80')->willReturn($deferred->promise());

        $ref = new \ReflectionProperty($connector, 'socks');
        $ref->setAccessible(true);
        $ref->setValue($connector, $socks);

        $promise = $connector->connect('google.com:80');
    }

    public function testConnectCancellationWithSuccessfulSshListenerWillCancelSocksConnectorAndStartIdleTimer()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer');

        $connector = new SshSocksConnector('host', $loop);

        $ref = new \ReflectionProperty($connector, 'listen');
        $ref->setAccessible(true);
        $ref->setValue($connector, \React\Promise\resolve(null));

        $deferred = new Deferred(function () {
            throw new \RuntimeException('SOCKS cancelled');
        });
        $socks = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $socks->expects($this->once())->method('connect')->willReturn($deferred->promise());

        $ref = new \ReflectionProperty($connector, 'socks');
        $ref->setAccessible(true);
        $ref->setValue($connector, $socks);

        $promise = $connector->connect('google.com:80');
        $promise->cancel();

        $exception = null;
        $promise->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });

        $this->setExpectedException('RuntimeException', 'SOCKS cancelled');
        throw $exception;
    }

    public function testConnectWithSuccessfulSshListenerButFailingSocksConnectorShouldReturnRejectedPromiseAndStartIdleTimer()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer');

        $connector = new SshSocksConnector('host', $loop);

        $ref = new \ReflectionProperty($connector, 'listen');
        $ref->setAccessible(true);
        $ref->setValue($connector, \React\Promise\resolve(null));

        $deferred = new Deferred();
        $socks = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $socks->expects($this->once())->method('connect')->willReturn($deferred->promise());

        $ref = new \ReflectionProperty($connector, 'socks');
        $ref->setAccessible(true);
        $ref->setValue($connector, $socks);

        $promise = $connector->connect('google.com:80');

        $deferred->reject(new \RuntimeException('Connection failed'));

        $exception = null;
        $promise->then(null, function ($reason) use (&$exception) {
            $exception = $reason;
        });

        $this->setExpectedException('RuntimeException', 'Connection failed');
        throw $exception;
    }

    public function testConnectWithSuccessfulSshListenerAndSuccessfulSocksConnectorShouldReturnResolvedPromise()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();

        $connector = new SshSocksConnector('host', $loop);

        $ref = new \ReflectionProperty($connector, 'listen');
        $ref->setAccessible(true);
        $ref->setValue($connector, \React\Promise\resolve(null));

        $connection = $this->getMockBuilder('React\Socket\ConnectionInterface')->getMock();

        $deferred = new Deferred();
        $deferred->resolve($connection);
        $socks = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $socks->expects($this->once())->method('connect')->willReturn($deferred->promise());

        $ref = new \ReflectionProperty($connector, 'socks');
        $ref->setAccessible(true);
        $ref->setValue($connector, $socks);

        $promise = $connector->connect('google.com:80');

        $promise->then($this->expectCallableOnceWith($connection), null);
    }

    public function testConnectWithSuccessfulSshListenerAndSuccessfulSocksConnectorWillStartIdleTimerWhenConnectionCloses()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer');

        $connector = new SshSocksConnector('host', $loop);

        $ref = new \ReflectionProperty($connector, 'listen');
        $ref->setAccessible(true);
        $ref->setValue($connector, \React\Promise\resolve(null));

        $connection = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close'))->getMock();

        $deferred = new Deferred();
        $deferred->resolve($connection);
        $socks = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $socks->expects($this->once())->method('connect')->willReturn($deferred->promise());

        $ref = new \ReflectionProperty($connector, 'socks');
        $ref->setAccessible(true);
        $ref->setValue($connector, $socks);

        $promise = $connector->connect('google.com:80');

        $connection->emit('close');
    }
}
