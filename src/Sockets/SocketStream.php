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

namespace Drewlabs\Net\Sockets;

use Drewlabs\Net\DNS;

/**
 * TCP Socket Transport for use with multiple protocols.
 * Supports connection pools and IPv6 in addition to providing a few public methods to make life easier.
 * It's primary purpose is long running connections, since it don't support socket re-use, ip-blacklisting, etc.
 * It assumes a blocking/synchronous architecture, and will block when reading or writing, but will enforce timeouts.
 *
 * Copyright (C) 2011 OnlineCity
 * Licensed under the MIT license, which can be read at: http://www.opensource.org/licenses/mit-license.php
 *
 * @author hd@onlinecity.dk
 */
class SocketStream implements SocketStreamInterface
{
    /**
     * Static random host.
     *
     * @var bool
     */
    public static $randomHost = false;

    /**
     * TCP socket.
     *
     * @var \Socket
     */
    protected $socket;

    /**
     * List of hosts.
     *
     * @var array
     */
    protected $hosts;

    /**
     * Makes the socket connection persistable.
     *
     * @var bool
     */
    protected $persist;

    /**
     * @var callable
     */
    protected $debugLogger;

    /**
     * @var bool
     */
    private static $debugMode;

    /**
     * Forces transport to use TCP v4 connection.
     *
     * @var bool
     */
    private static $forceIpv4 = false;

    /**
     * @var int
     */
    private static $defaultSendTimeout = 100;
    /**
     * @var int
     */
    private static $defaultRecvTimeout = 750;

    /**
     * DNS instance.
     *
     * @var DNS
     */
    private $dns;

    /**
     * Construct a new socket for this transport to use.
     *
     * @param string[]|string           $hosts       list of hosts to try
     * @param int[]|string[]|string|int $ports       list of ports to try, or a single common port
     * @param bool                      $persist     use persistent sockets
     * @param mixed                     $debugLogger callback for debug info
     */
    public function __construct($hosts, $ports, $persist = false, $debugLogger = null)
    {
        $this->debugLogger = $debugLogger ?: 'error_log';
        $this->dns = new DNS($this->debugLogger);
        $this->hosts = $this->resolveHosts(\is_array($hosts) ? $hosts : [$hosts], $ports);
        $this->persist = $persist;
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function getSocketOption($option, $lvl = \SOL_SOCKET)
    {
        return socket_get_option($this->socket, $lvl, $option);
    }

    public function setSocketOption($option, $value, $lvl = \SOL_SOCKET)
    {
        return socket_set_option($this->socket, $lvl, $option, $value);
    }

    public function setSendTimeout($timeout)
    {
        if (!$this->isOpen()) {
            self::$defaultSendTimeout = $timeout;
        } else {
            socket_set_option($this->socket, \SOL_SOCKET, \SO_SNDTIMEO, $this->msToSolArray($timeout));
        }
    }

    public function setRecvTimeout($timeout)
    {
        if (!$this->isOpen()) {
            self::$defaultRecvTimeout = $timeout;
        } else {
            socket_set_option($this->socket, \SOL_SOCKET, \SO_RCVTIMEO, $this->msToSolArray($timeout));
        }
    }

    public function isOpen()
    {
        if (!(\is_resource($this->socket) || (class_exists(\Socket::class) && $this->socket instanceof \Socket))) {
            return false;
        }

        $rsock = null;
        $wsock = null;
        $excepts = [$this->socket];
        $result = socket_select($rsock, $wsock, $excepts, 0);
        if (false === $result) {
            throw new SocketStreamException('Could not examine socket; ' . socket_strerror(socket_last_error()), socket_last_error());
        }
        if (!empty($excepts)) {
            return false;
        }
        // if there is an exception on our socket it's probably dead
        return true;
    }

    public function open(int $type = \SOCK_STREAM, int $protocol = \SOL_TCP)
    {
        // Use Ipv6 socket by default if not forcing Ipv4 connection
        if (!self::$forceIpv4) {
            $this->openIPv6Socket($type, $protocol);
        }

        // Case the socket could not be resolve using Ipv6 connection, use ipv4
        if (null === $this->socket) {
            $this->openIPv4Socket($type, $protocol);
        }
        
        // Case the socket property is still null, we throw an exception
        if (null === $this->socket) {
            throw new SocketStreamException('Could not connect to any of the specified hosts');
        }
    }

    private function openIPv4Socket(int $type = \SOCK_STREAM, $protocol = \SOL_TCP)
    {
        $socket = @socket_create(\AF_INET, $type, $protocol);
        if (false === $socket) {
            throw new SocketStreamException('Could not create socket; ' . socket_strerror(socket_last_error()), socket_last_error());
        }
        socket_set_option($socket, \SOL_SOCKET, \SO_SNDTIMEO, $this->msToSolArray(self::$defaultSendTimeout));
        socket_set_option($socket, \SOL_SOCKET, \SO_RCVTIMEO, $this->msToSolArray(self::$defaultRecvTimeout));

        // Creates a sockets iterator
        $hosts = new \ArrayIterator($this->hosts);
        while ($hosts->valid()) {
            $host = $hosts->current();
            foreach ($host->getIp4s() as $ip) {
                $port = $host->getPort();
                $this->log("Using ipv4, Connecting to $ip:$port...");
                if (@socket_connect($socket, $ip, $port)) {
                    $this->log("Using ipv4, Connected to $ip:$port!");
                    $this->socket = $socket;
                    return;
                }
                $this->log("Using ipv4, Socket connect to $ip:$port failed; " . socket_strerror(socket_last_error()));
            }
            $hosts->next();
        }
    }

    private function openIPv6Socket(int $type = \SOCK_STREAM, $protocol = \SOL_TCP)
    {
        $socket = @socket_create(\AF_INET6, $type, $protocol);
        if (false === $socket) {
            throw new SocketStreamException('Could not create socket; ' . socket_strerror(socket_last_error()), socket_last_error());
        }
        socket_set_option($socket, \SOL_SOCKET, \SO_SNDTIMEO, $this->msToSolArray(self::$defaultSendTimeout));
        socket_set_option($socket, \SOL_SOCKET, \SO_RCVTIMEO, $this->msToSolArray(self::$defaultRecvTimeout));

        // Creates a sockets iterator
        $hosts = new \ArrayIterator($this->hosts);
        while ($hosts->valid()) {
            $host = $hosts->current();
            foreach ($host->getIp6s() as $ip) {
                $port = $host->getPort();
                $this->log("Connecting to $ip:$port...");
                if (@socket_connect($socket, $ip, $port)) {
                    $this->log("Connected to $ip:$port!");
                    $this->socket = $socket;
                    return;
                } else {
                    $this->log("Socket connect to $ip:$port failed; " . socket_strerror(socket_last_error()));
                }
            }
            $hosts->next();
        }
    }

    public function close()
    {
        $arrOpt = ['l_onoff' => 1, 'l_linger' => 1];
        socket_set_block($this->socket);
        socket_set_option($this->socket, \SOL_SOCKET, \SO_LINGER, $arrOpt);
        socket_close($this->socket);
    }

    /**
     * Check if there is data waiting for us on the wire.
     *
     * @throws SocketStreamException
     *
     * @return bool
     */
    public function hasData()
    {
        $rsock = [$this->socket];
        $wsock = null;
        $excepts = null;
        $result = socket_select($rsock, $wsock, $excepts, 0);
        if (false === $result) {
            throw new SocketStreamException('Could not examine socket; ' . socket_strerror(socket_last_error()), socket_last_error());
        }

        if (!empty($rsock)) {
            return true;
        }

        return false;
    }

    public function read(int $length)
    {
        $data = socket_read($this->socket, $length, \PHP_BINARY_READ);
        if (false === $data && \SOCKET_EAGAIN === socket_last_error()) {
            return false;
        }
        // sockets give EAGAIN on timeout
        if (false === $data) {
            throw new SocketStreamException('Could not read ' . $length . ' bytes from socket; ' . socket_strerror(socket_last_error()), socket_last_error());
        }

        if ('' === $data) {
            return false;
        }

        return $data;
    }

    public function readAll(int $length)
    {
        $data = '';
        // Total bytes read from the socket connection
        $bytes = 0;
        $readTimeout = socket_get_option($this->socket, \SOL_SOCKET, \SO_RCVTIMEO);
        while ($bytes < $length) {
            $buffer = '';
            $bytes += socket_recv($this->socket, $buffer, $length - $bytes, \MSG_DONTWAIT);
            if (false === $bytes) {
                throw new SocketStreamException('Could not read ' . $length . ' bytes from socket; ' . socket_strerror(socket_last_error()), socket_last_error());
            }

            $data .= $buffer;
            if ($bytes === $length) {
                break;
            }

            // wait for data to be available, up to timeout
            $rsock = [$this->socket];
            $wsock = null;
            $excepts = [$this->socket];
            $result = socket_select($rsock, $wsock, $excepts, $readTimeout['sec'], $readTimeout['usec']);

            // check
            if (false === $result) {
                throw new SocketStreamException('Could not examine socket; ' . socket_strerror(socket_last_error()), socket_last_error());
            }

            if (!empty($excepts)) {
                throw new SocketStreamException('Socket exception while waiting for data; ' . socket_strerror(socket_last_error()), socket_last_error());
            }

            if (empty($rsock)) {
                throw new SocketStreamException('Timed out waiting for data on socket');
            }
        }

        return $data;
    }

    public function write(string $buffer, int $chunkSize = null)
    {
        $bytes = $chunkSize ?? \strlen($buffer);
        $writeTimeout = socket_get_option($this->socket, \SOL_SOCKET, \SO_SNDTIMEO);

        while ($bytes > 0) {
            $wrote = socket_write($this->socket, $buffer, $bytes);
            if (false === $wrote) {
                throw new SocketStreamException('Could not write ' . $chunkSize . ' bytes to socket; ' . socket_strerror(socket_last_error()), socket_last_error());
            }

            $bytes -= $wrote;
            if (0 === $bytes) {
                break;
            }

            $buffer = substr($buffer, $wrote);

            // wait for the socket to accept more data, up to timeout
            $bytes = null;
            $wsock = [$this->socket];
            $excepts = [$this->socket];
            $result = socket_select($bytes, $wsock, $excepts, $writeTimeout['sec'], $writeTimeout['usec']);

            // check
            if (false === $result) {
                throw new SocketStreamException('Could not examine socket; ' . socket_strerror(socket_last_error()), socket_last_error());
            }

            if (!empty($excepts)) {
                throw new SocketStreamException('Socket exception while waiting to write data; ' . socket_strerror(socket_last_error()), socket_last_error());
            }

            if (empty($wsock)) {
                throw new SocketStreamException('Timed out waiting to write data on socket');
            }
        }
    }

    /**
     * Static method setting the forceIpv4 for the transport.
     *
     * @return void
     */
    public static function setForceIpv4(bool $value)
    {
        static::$forceIpv4 = $value;
        DNS::$forceIpv4 = $value;
    }

    public function setDebugLogger(callable $debugLogger)
    {
        $this->debugLogger = $debugLogger;
        $this->dns->setDebugLogger($debugLogger);
    }

    public static function setDefaultDebugLogger(callable $debugLogger)
    {
        static::$debugMode = $debugLogger;
    }

    public static function debug()
    {
        self::$debugMode = true;
        DNS::debug();
    }

    /**
     * Resolve the hostnames into IPs, and sort them into IPv4 or IPv6 groups.
     * If using DNS hostnames, and all lookups fail, a \InvalidArgumentException is thrown.
     *
     * @throws \InvalidArgumentException
     */
    protected function resolveHosts(array $hosts, $ports)
    {
        // Deal with optional port
        $list = [];
        foreach ($hosts as $key => $host) {
            $list[] = [$host, \is_array($ports) ? $ports[$key] : $ports];
        }
        if (self::$randomHost) {
            shuffle($list);
        }

        return $this->dns->resolveHosts($list, false);
    }

    /**
     * Log debug message using the debug logger.
     *
     * @return void
     */
    private function log(string $message)
    {
        if (self::$debugMode) {
            \call_user_func($this->debugLogger, $message);
        }
    }

    /**
     * Convert a milliseconds into a socket sec+usec array.
     *
     * @return array
     */
    private function msToSolArray(int $ms)
    {
        $usec = $ms * 1000;

        return ['sec' => floor($usec / 1000000), 'usec' => $usec % 1000000];
    }
}
