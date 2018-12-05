<?php

use PHPUnit\Framework\TestCase;
use React\EventLoop\Factory;
use Clue\React\SshProxy\SshProcessConnector;

class FunctionalSshProcessConnectorTest extends TestCase
{
    const TIMEOUT = 10.0;

    private $loop;
    private $connector;

    public function setUp()
    {
        $url = getenv('SSH_PROXY');
        if ($url === false) {
            $this->markTestSkipped('No SSH_PROXY env set');
        }

        $this->loop = Factory::create();
        $this->connector = new SshProcessConnector($url, $this->loop);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Connection to example.com:80 failed because SSH client died
     */
    public function testConnectInvalidProxyUriWillReturnRejectedPromise()
    {
        $this->connector = new SshProcessConnector(getenv('SSH_PROXY') . '.invalid', $this->loop);
        $promise = $this->connector->connect('example.com:80');

        \Clue\React\Block\await($promise, $this->loop, self::TIMEOUT);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Connection to example.invalid:80 rejected:
     */
    public function testConnectInvalidTargetWillReturnRejectedPromise()
    {
        $promise = $this->connector->connect('example.invalid:80');

        \Clue\React\Block\await($promise, $this->loop, self::TIMEOUT);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Connection to example.com:80 cancelled while waiting for SSH client
     */
    public function testCancelConnectWillReturnRejectedPromise()
    {
        $promise = $this->connector->connect('example.com:80');
        $promise->cancel();

        \Clue\React\Block\await($promise, $this->loop, 0);
    }

    public function testConnectValidTargetWillReturnPromiseWhichResolvesToConnection()
    {
        $promise = $this->connector->connect('example.com:80');

        $connection = \Clue\React\Block\await($promise, $this->loop, self::TIMEOUT);

        $this->assertInstanceOf('React\Socket\ConnectionInterface', $connection);
        $this->assertTrue($connection->isReadable());
        $this->assertTrue($connection->isWritable());
        $connection->close();
    }
}
