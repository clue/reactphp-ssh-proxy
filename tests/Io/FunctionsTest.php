<?php

use Clue\React\SshProxy\Io;
use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{
    public function testFdsReturnsArray()
    {
        $fds = Io\fds();

        $this->assertInternalType('array', $fds);
    }

    public function testFdsReturnsArrayWithStdioHandles()
    {
        // skip when running with closed handles: vendor/bin/phpunit 0<&-
        if (!defined('STDIN') || !defined('STDOUT') || !defined('STDERR') || !@fstat(STDIN) || !@fstat(STDOUT) || !@fstat(STDERR)) {
            $this->markTestSkipped('Test suite does not appear to run with standard I/O handles');
        }

        $fds = Io\fds();

        $this->assertContains(0, $fds);
        $this->assertContains(1, $fds);
        $this->assertContains(2, $fds);
    }

    public function testFdsReturnsSameArrayTwice()
    {
        $fds = Io\fds();
        $second = Io\fds();

        $this->assertEquals($fds, $second);
    }

    public function testFdsWithInvalidPathReturnsArray()
    {
        $fds = Io\fds('/dev/null');

        $this->assertInternalType('array', $fds);
    }

    public function testFdsWithInvalidPathReturnsSubsetOfFdsFromDevFd()
    {
        if (@scandir('/dev/fd') === false) {
            $this->markTestSkipped('Unable to read /dev/fd');
        }

        $fds = Io\fds();
        $second = Io\fds('/dev/null');

        foreach ($second as $one) {
            $this->assertContains($one, $fds);
        }
    }

    public function testProcessWithoutFdsReturnsProcessWithoutClosingDefaultHandles()
    {
        $process = Io\processWithoutFds('sleep 10');

        $this->assertInstanceOf('React\ChildProcess\Process', $process);

        $this->assertNotContains(' 0>&-', $process->getCommand());
        $this->assertNotContains(' 1>&-', $process->getCommand());
        $this->assertNotContains(' 2>&-', $process->getCommand());
    }

    public function testProcessWithoutFdsReturnsProcessWithOriginalCommandPartOfActualCommandWhenDescriptorsNeedToBeClosed()
    {
        // skip when running with closed handles: vendor/bin/phpunit 0<&-
        // bypass for example with dummy handles: vendor/bin/phpunit 8<&-
        $fds = Io\fds();
        if (!$fds || max($fds) < 3) {
            $this->markTestSkipped('Did not detect additional file descriptors to be closed');
        }

        $process = Io\processWithoutFds('sleep 10');

        $this->assertInstanceOf('React\ChildProcess\Process', $process);

        $this->assertNotEquals('sleep 10', $process->getCommand());
        $this->assertContains('sleep 10', $process->getCommand());
    }
}
