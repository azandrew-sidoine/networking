<?php

declare(strict_types=1);

/*
 * This file is part of the drewlabs namespace.
 *
 * (c) Sidoine Azandrew <azandrewdevelopper@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Drewlabs\Net\Ping;

/**
 * The fsockopen method simply tries to reach the host on a port. This method
 * is often the fastest, but not necessarily the most reliable. Even if a host
 * doesn't respond, fsockopen may still make a connection.
 */
class FSockOpenClient implements ClientInterface
{
    /**
     * @var float|int
     */
    private $timeout;

    /**
     * @param mixed $timeout Timeout in seconds
     *
     * @return self
     */
    public function __construct($timeout = null)
    {
        $this->timeout = $timeout;
    }

    public function send(string $host, int $port = null)
    {
        $start = microtime(true);
        $latency = false;
        // fsockopen prints a bunch of errors if a host is unreachable. Hide those
        // irrelevant errors and deal with the results instead.
        $fp = @fsockopen($host, $port ?? 80, $errno, $errstr, $this->timeout);
        if ($fp) {
            $latency = microtime(true) - $start;
            $latency = round($latency * 1000, 4);
            fclose($fp);
        }

        return new PingResult($latency);
    }
}
