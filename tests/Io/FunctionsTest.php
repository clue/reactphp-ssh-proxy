<?php

use Clue\React\SshProxy\Io;
use \Clue\Tests\React\SshProxy\TestCase;

class FunctionsTest extends TestCase
{
    public function testFdsReturnsArray()
    {
        $fds = Io\fds();

        $this->assertEquals('array', gettype($fds));
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

        $this->assertEquals('array', gettype($fds));
    }

    public function testFdsWithInvalidPathReturnsSubsetOfFdsFromDevFd()
    {
        if (@scandir('/dev/fd') === false) {
            $this->markTestSkipped('Unable to read /dev/fd');
        }

        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on HHVM');
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

        $this->assertNotContainsString(' 0>&-', $process->getCommand());
        $this->assertNotContainsString(' 1>&-', $process->getCommand());
        $this->assertNotContainsString(' 2>&-', $process->getCommand());
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
        $this->assertContainsString('sleep 10', $process->getCommand());
    }
}
