# Changelog

## 1.3.0 (2021-08-06)

*   Feature: Simplify usage by supporting new default loop.
    (#27 and #28 by @clue)

    ```php
    // old (still supported)
    $proxy = new Clue\React\SshProxy\SshProcessConnector('user@example.com', $loop);
    $proxy = new Clue\React\SshProxy\SshSocksConnector('user@example.com', $loop);

    // new (using default loop)
    $proxy = new Clue\React\SshProxy\SshProcessConnector('user@example.com');
    $proxy = new Clue\React\SshProxy\SshSocksConnector('user@example.com');
    ```

*   Documentation improvements and updated examples.
    (#25, #29 and #30 by @clue and #23 and #26 by @SimonFrings)

*   Improve test suite and use GitHub actions for continuous integration (CI).
    (#24 by @SimonFrings)

## 1.2.0 (2020-10-23)

*   Fix: Fix error reporting when parsing invalid SSH server URL.
    (#15 by @clue)

*   Enhanced documentation for ReactPHP's new HTTP client.
    (#21 by @SimonFrings)

*   Improve test suite and add `.gitattributes` to exclude dev files from exports.
    Prepare PHP 8 support, update to PHPUnit 9 and simplify test matrix.
    (#14 by @clue and #16, #18, #19 and 22 by @SimonFrings)

## 1.1.1 (2019-05-01)

*   Fix: Only start consuming STDOUT data once connection is ready.
    (#11 by @clue)

*   Add documentation and example for MySQL database SSH tunnel.
    (#13 by @clue)

## 1.1.0 (2019-01-21)

*   Feature: Improve platform support (chroot environments, Mac and others) and
    do not inherit open FDs to SSH child process by overwriting and closing.
    (#10 by @clue)

## 1.0.0 (2018-12-19)

*   First stable release, following SemVer
