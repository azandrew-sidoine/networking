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
 * The exec method uses the possibly insecure exec() function, which passes
 * the input to the system. This is potentially VERY dangerous if you pass in
 * any user-submitted data. Be sure you sanitize your inputs!
 */
class BinaryClient implements ClientInterface
{
    /**
     * @var float|int
     */
    private $timeout;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @param float|int|null $timeout (in ms)
     *
     * @return void
     */
    public function __construct(int $ttl = 255, ?float $timeout = 10)
    {
        $this->timeout = $timeout;
        $this->ttl = $ttl;
    }

    /**
     * Matches an IP on command output and returns.
     *
     * @return string
     */
    public function getIpAddress()
    {
        $match = [];
        if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $this->output, $match)) {
            return $match[0];
        }

        return null;
    }

    public function send(string $host, int $port = null)
    {
        $latency = false;
        $ttl = escapeshellcmd((string) $this->ttl);
        $timeout = escapeshellcmd((string) $this->timeout);
        $host = escapeshellcmd($host);

        // Exec string for Windows-based systems.
        if ('WIN' === strtoupper(substr(\PHP_OS, 0, 3))) {
            // -n = number of pings; -i = ttl; -w = timeout (in milliseconds).
            $exec_string = "ping -n 1 -i $ttl -w $timeout $host";
        } elseif ('DARWIN' === strtoupper(\PHP_OS)) {
            // -n = numeric output; -c = number of pings; -m = ttl; -t = timeout.
            $exec_string = "ping -n -c 1 -m $ttl -t $timeout $host";
        } else {
            // -n = numeric output; -c = number of pings; -t = ttl; -W = timeout
            $exec_string = "ping -n -c 1 -t $ttl -W $timeout $host 2>&1";
        }

        exec($exec_string, $output, $return);
        $output_str = implode(' ', $output);
        $output = array_values(array_filter($output));
        // If the result line in the output is not empty, parse it.
        if (!empty($output[1])) {
            // Search for a 'time' value in the result line.
            $response = preg_match("/time(?:=|<)(?<time>[\.0-9]+)(?:|\s)ms/", $output[1], $matches);
            // If there's a result and it's greater than 0, return the latency.
            if ($response > 0 && isset($matches['time'])) {
                $latency = round((float) $matches['time'], 4);
            }
            // Search ip address
            $response = preg_match("/from (?<from>\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/", $output[1], $matches);
            $ip = $response > 0 ? $matches['from'] ?? null : null;

        }

        return new PingResult($latency, $output_str, null, $ip);
    }
}
