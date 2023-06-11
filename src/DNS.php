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

namespace Drewlabs\Net;

class DNS
{
    /**
     * Makes the TCP class to force using ip v4 connection.
     *
     * @var bool
     */
    public static $forceIpv4 = false;

    /**
     * Makes the TCP class to force using ip v6 connection.
     *
     * @var bool
     */
    public static $forceIpv6 = false;

    /**
     * Makes the DNS supports debugging.
     *
     * @var bool
     */
    private static $debugMode = false;

    /**
     * Debug Logger.
     *
     * @var callable|null
     */
    private $debugLogger;

    /**
     * Creates an instance of {@see DNS} class.
     *
     * @return static
     */
    public function __construct(callable $debugLogger = null)
    {
        $this->debugLogger = $debugLogger;
    }

    /**
     * Query for a server address using the server ip address.
     *
     * @return string|null
     */
    public function getHostByAddress(string $ip)
    {
        return ($addr = gethostbyaddr($ip)) === false ? null : $addr;
    }

    /**
     * Query for server ipv4 address using it domain name.
     *
     * @return string
     */
    public function getHostByName(string $host)
    {
        return gethostbyname($host);
    }

    /**
     * Query for DNS records using developper provided parameters.
     *
     * @param mixed $authoritativeNameServers
     *
     * @return array
     */
    public function getRecords(string $hostname, int $type, &$authoritativeNameServers = null, bool $raw = false)
    {
        return ($records = dns_get_record($hostname, $type, $authoritativeNameServers, null, $raw)) === false ? [] : $records;
    }

    /**
     * Resolve a TCP host instance from a host string.
     *
     * @param string|int $port
     *
     * @return Host
     */
    public function resolveHost(string $host, $port, bool $resolveHostByAddress = false)
    {
        [$hostname, $port] = [$host, $port];
        if (preg_match('/^([12]?[0-9]?[0-9]\.){3}([12]?[0-9]?[0-9])$/', $hostname)) {
            return new Host(
                $resolveHostByAddress ? $this->getHostByAddress($hostname) : $host,
                $port,
                [$hostname],
                []
            );
        }
        if (preg_match('/^([0-9a-f:]+):[0-9a-f]{1,4}$/i', $hostname)) {
            return new Host(
                $resolveHostByAddress ? $this->getHostByAddress($hostname) : $host,
                $port,
                [],
                [$hostname]
            );
        }
        [$ip4s, $ip6s] = [[], []];
        // Do a DNS lookup
        if (!self::$forceIpv4) {
            // if not in IPv4 only mode, check the AAAA records first
            $records = $this->getRecords($hostname, \DNS_AAAA);
            if (false === $records) {
                $this->log('DNS lookup for AAAA records for: '.$hostname.' failed');
            }
            if ($records) {
                foreach ($records as $record) {
                    if (isset($record['ipv6']) && $record['ipv6']) {
                        $ip6s[] = $record['ipv6'];
                    }
                }
            }
            $this->log("IPv6 addresses for $hostname: ".implode(', ', $ip6s));
        }
        if (!self::$forceIpv6) {
            // if not in IPv6 mode check the A records also
            $records = $this->getRecords($hostname, \DNS_A);
            if (false === $records) {
                $this->log('DNS lookup for A records for: '.$hostname.' failed');
            }
            if ($records) {
                foreach ($records as $record) {
                    if (isset($record['ip']) && $record['ip']) {
                        $ip4s[] = $record['ip'];
                    }
                }
            }
            // also try gethostbyname, since name could also be something else, such as "localhost" etc.
            $ip = $this->getHostByName($hostname);
            if ($ip !== $hostname && !\in_array($ip, $ip4s, true)) {
                $ip4s[] = $ip;
            }
            $this->log("IPv4 addresses for $hostname: ".implode(', ', $ip4s));
        }

        return new Host($hostname, $port, $ip4s, $ip6s);
    }

    /**
     * Resolve a list of TCP host based on developper parameters.
     *
     * @param bool $resolveHostByAddress
     *
     * @throws \InvalidArgumentException
     *
     * @return Host[]
     */
    public function resolveHosts(array $hosts, $resolveHostByAddress = false)
    {
        $index = 0;
        $tcpHosts = [];
        foreach ($hosts as $host) {
            [$h, $p] = $this->prepareHostPort($host);
            $tcpHost = $this->resolveHost($h, $p, $resolveHostByAddress);
            [$ip4s, $ip6s] = [$tcpHost->getIp4s(), $tcpHost->getIp6s()];
            if ((self::$forceIpv4 && empty($ip4s))
                || (self::$forceIpv6 && empty($ip6s))
                || (empty($ip4s) && empty($ip6s))
            ) {
                continue;
            }
            if ($this->isDebugging()) {
                $index += \count($ip4s) + \count($ip6s);
            }
            $tcpHosts[] = $tcpHost;
        }
        $this->log('Built connection pool of '.\count($tcpHosts)." host(s) with $index ip(s) in total");

        if (empty($tcpHosts)) {
            throw new \InvalidArgumentException('No valid hosts was found');
        }

        return $tcpHosts;
    }

    /**
     * Debug property setter.
     *
     * @return void
     */
    public static function debug()
    {
        static::$debugMode = true;
    }

    /**
     * Debug logger property setter.
     *
     * @return void
     */
    public function setDebugLogger(callable $debugLogger)
    {
        $this->debugLogger = $debugLogger;
    }

    /**
     * Returns true is running in debug mode.
     *
     * @return bool
     */
    public function isDebugging()
    {
        return (bool) static::$debugMode;
    }

    /**
     * Log debug message using the debug logger.
     *
     * @return void
     */
    private function log(string $message)
    {
        if ($this->isDebugging() && (null !== $this->debugLogger)) {
            \call_user_func($this->debugLogger, $message);
        }
    }

    private function prepareHostPort($host)
    {
        $p = \is_array($host) ? $host[1] : (\is_string($host) ? explode(':', $host)[1] ?? null : null);
        $h = \is_array($host) ? $host[0] : (\is_string($host) ? explode(':', $host)[1] ?? $host : null);

        return [$h, $p];
    }
}
