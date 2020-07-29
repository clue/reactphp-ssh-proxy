<?php

use Clue\React\SshProxy\Io\LineSeparatedReader;
use \Clue\Tests\React\SshProxy\TestCase;
use React\Stream\ThroughStream;

class LineSeparatedReaderTest extends TestCase
{
    public function testStreamDataWillNotEmitDataWhenContainsNoNewline()
    {
        $input = new ThroughStream();
        $stream = new LineSeparatedReader($input);

        $stream->on('data', $this->expectCallableNever());

        $input->write('hello!');
    }

    public function testStreamDataWillEmitDataWithoutTrailingNewlineWhenDataContainsNewline()
    {
        $input = new ThroughStream();
        $stream = new LineSeparatedReader($input);

        $stream->on('data', $this->expectCallableOnceWith("hello!"));

        $input->write("hello!\n");
    }

    public function testStreamDataWillEmitDataBehindNewlineWhenDataContainsDataBehindNewline()
    {
        $input = new ThroughStream();
        $stream = new LineSeparatedReader($input);

        $stream->on('data', $this->expectCallableOnceWith("hello!"));

        $input->write("hello!\nignored");
    }


    public function testStreamEndWillEndWithoutDataWhenNoPreviousDataInBuffer()
    {
        $input = new ThroughStream();
        $stream = new LineSeparatedReader($input);

        $stream->on('data', $this->expectCallableNever());
        $stream->on('end', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());

        $input->end();
    }

    public function testStreamEndWillEndWithDataWhenDataFromPreviousEventIsInBuffer()
    {
        $input = new ThroughStream();
        $stream = new LineSeparatedReader($input);

        $stream->on('data', $this->expectCallableOnceWith("hello!"));
        $stream->on('end', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());

        $input->write('hello!');
        $input->end();
    }

    public function testStreamCloseWillCloseWithoutDataEvenWhenDataFromPreviousEventIsInBuffer()
    {
        $input = new ThroughStream();
        $stream = new LineSeparatedReader($input);

        $stream->on('data', $this->expectCallableNever());
        $stream->on('end', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableOnce());

        $input->write('hello!');
        $input->close();
    }

    public function testStreamErrorWillEmitErrorAndClose()
    {
        $input = new ThroughStream();
        $stream = new LineSeparatedReader($input);

        $error = new \RuntimeException();
        $stream->on('error', $this->expectCallableOnceWith($error));
        $stream->on('close', $this->expectCallableOnce());

        $input->emit('error', array($error));
    }

    public function testClosedInputStreamWillReturnClosed()
    {
        $input = new ThroughStream();
        $input->close();

        $stream = new LineSeparatedReader($input);

        $this->assertFalse($stream->isReadable());

        $stream->on('close', $this->expectCallableNever());
        $stream->close();
    }

    public function testPauseWillBeForwardedToUnderlyingStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('isReadable')->willReturn(true);
        $input->expects($this->once())->method('pause');

        $stream = new LineSeparatedReader($input);
        $stream->pause();
    }

    public function testResumeWillBeForwardedToUnderlyingStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('isReadable')->willReturn(true);
        $input->expects($this->once())->method('resume');

        $stream = new LineSeparatedReader($input);
        $stream->resume();
    }

    public function testPipeWillReturnDestinationStream()
    {
        $input = new ThroughStream();
        $dest = new ThroughStream();

        $stream = new LineSeparatedReader($input);
        $ret = $stream->pipe($dest);

        $this->assertSame($dest, $ret);
    }
}
