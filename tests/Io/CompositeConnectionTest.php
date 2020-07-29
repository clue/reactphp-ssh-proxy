<?php

use Clue\React\SshProxy\Io\CompositeConnection;
use \Clue\Tests\React\SshProxy\TestCase;
use React\Stream\ThroughStream;

class CompositeConnectionTest extends TestCase
{
    public function testGetRemoteAddressIsNull()
    {
        $read = new ThroughStream();
        $write = new ThroughStream();

        $stream = new CompositeConnection($read, $write);

        $this->assertNull($stream->getRemoteAddress());
    }

    public function testGetLocalAddressIsNull()
    {
        $read = new ThroughStream();
        $write = new ThroughStream();

        $stream = new CompositeConnection($read, $write);

        $this->assertNull($stream->getLocalAddress());
    }

    public function testWriteWillBeForwardedToWritableStream()
    {
        $read = new ThroughStream();

        $write = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $write->expects($this->once())->method('isWritable')->willReturn(true);
        $write->expects($this->once())->method('write')->with('hello')->willReturn(true);

        $stream = new CompositeConnection($read, $write);
        $ret = $stream->write('hello');

        $this->assertTrue($ret);
    }

    public function testEndWillBeForwardedToWritableStream()
    {
        $read = new ThroughStream();

        $write = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $write->expects($this->once())->method('isWritable')->willReturn(true);
        $write->expects($this->once())->method('end')->with('hello');

        $stream = new CompositeConnection($read, $write);
        $stream->end('hello');
    }

    public function testPauseWillBeForwardedToReadableStream()
    {
        $read = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $read->expects($this->once())->method('isReadable')->willReturn(true);
        $read->expects($this->once())->method('pause');

        $write = new ThroughStream();

        $stream = new CompositeConnection($read, $write);
        $stream->pause();
    }

    public function testResumeWillBeForwardedToReadableStream()
    {
        $read = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $read->expects($this->once())->method('isReadable')->willReturn(true);
        $read->expects($this->once())->method('resume');

        $write = new ThroughStream();

        $stream = new CompositeConnection($read, $write);
        $stream->resume();
    }

    public function testPipeWillReturnDestinationStream()
    {
        $read = new ThroughStream();
        $write = new ThroughStream();

        $stream = new CompositeConnection($read, $write);
        $dest = new ThroughStream();
        $ret = $stream->pipe($dest);

        $this->assertSame($dest, $ret);
    }

    public function testCloseWillEmitCloseAndWillBeForwardedToBothStreams()
    {
        $read = new ThroughStream();
        $read->on('close', $this->expectCallableOnce());

        $write = new ThroughStream();
        $write->on('close', $this->expectCallableOnce());

        $stream = new CompositeConnection($read, $write);
        $stream->on('close', $this->expectCallableOnce());
        $stream->close();

        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
    }

    public function testConstructWithClosedReadableStreamWillAlsoCloseWritableStream()
    {
        $read = new ThroughStream();
        $read->close();

        $write = new ThroughStream();
        $write->on('close', $this->expectCallableOnce());

        $stream = new CompositeConnection($read, $write);

        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
    }

    public function testConstructWithClosedWritableStreamWillAlsoCloseReadableStream()
    {
        $read = new ThroughStream();
        $read->on('close', $this->expectCallableOnce());

        $write = new ThroughStream();
        $write->close();

        $stream = new CompositeConnection($read, $write);

        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
    }
}
