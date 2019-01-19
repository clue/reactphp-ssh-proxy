<?php

namespace Clue\React\SshProxy\Io;

use React\ChildProcess\Process;

/**
 * Returns a list of active file descriptors (may contain bogus entries)
 *
 * @param string $path
 * @return array
 * @internal
 */
function fds($path = '/proc/self/fd')
{
    // try to get list of all open FDs (Linux only) or simply assume range 0-1024 (FD_SETSIZE)
    $fds = @\scandir($path);

    return $fds !== false ? $fds : \range(0, 1024);
}

/**
 * Creates a Process with the given command modified in such a way that any additional FDs are explicitly not passed along
 *
 * @param string $command
 * @return Process
 * @internal
 */
function processWithoutFds($command)
{
    // try to get list of all open FDs
    $fds = fds();

    // do not inherit open FDs by explicitly closing all of them
    foreach ($fds as $fd) {
        if ($fd > 2) {
            $command .= ' ' . $fd . '>&-';
        }
    }

    // default `sh` only accepts single-digit FDs, so run in bash if needed
    if ($fds && max($fds) > 9) {
        $command = 'exec bash -c ' . escapeshellarg($command);
    }

    return new Process($command);
}
