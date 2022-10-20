<?php

declare(strict_types=1);

/*
 * This file is part of the Drewlabs package.
 *
 * (c) Sidoine Azandrew <azandrewdevelopper@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Drewlabs\Net\Sockets;

use ArrayIterator;
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
class SocketTransport implements SocketTransportInterface
{

    /**
     * 
     * @var bool
     */
    private static $debugMode;

    /**
     * Forces transport to use TCP v6 connection
     * 
     * @var bool
     */
    private static $forceIpv6 = false;

    /**
     * Forces transport to use TCP v4 connection
     * 
     * @var bool
     */
    private static $forceIpv4 = false;


    /**
     * 
     * @var int
     */
    private static $defaultSendTimeout = 100;
    /**
     * 
     * @var int
     */
    private static $defaultRecvTimeout = 750;

    /**
     * Static random host
     * 
     * @var bool
     */
    public static $randomHost = false;

    /**
     * TCP socket
     * 
     * @var \Socket
     */
    protected $socket;

    /**
     * List of hosts
     * 
     * @var array
     */
    protected $hosts;

    /**
     * Makes the socket connection persistable
     * 
     * @var bool
     */
    protected $persist;

    /**
     * 
     * @var callable
     */
    protected $debugLogger;

    /**
     * DNS instance
     * 
     * @var DNS
     */
    private $dns;

    /**
     * Construct a new socket for this transport to use.
     *
     * @param string[]|string $hosts        list of hosts to try
     * @param int[]|string[]|string|int $ports        list of ports to try, or a single common port
     * @param bool  $persist      use persistent sockets
     * @param mixed $debugLogger callback for debug info
     */
    public function __construct($hosts, $ports, $persist = false, $debugLogger = null)
    {
        $this->debugLogger = $debugLogger ?: 'error_log';
        $this->dns = new DNS($this->debugLogger);
        $this->hosts = $this->resolveHosts(is_array($hosts) ? $hosts : [$hosts], $ports);
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
            throw new SocketTransportException('Could not examine socket; ' . socket_strerror(socket_last_error()), socket_last_error());
        }
        if (!empty($excepts)) {
            return false;
        }
        // if there is an exception on our socket it's probably dead
        return true;
    }

    public function open()
    {
        if (!self::$forceIpv4) {
            $socket6 = @socket_create(\AF_INET6, \SOCK_STREAM, \SOL_TCP);
            if (false === $socket6) {
                throw new SocketTransportException('Could not create socket; ' . socket_strerror(socket_last_error()), socket_last_error());
            }
            socket_set_option($socket6, \SOL_SOCKET, \SO_SNDTIMEO, $this->msToSolArray(self::$defaultSendTimeout));
            socket_set_option($socket6, \SOL_SOCKET, \SO_RCVTIMEO, $this->msToSolArray(self::$defaultRecvTimeout));
        }
        if (!self::$forceIpv6) {
            $socket4 = @socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP);
            if (false === $socket4) {
                throw new SocketTransportException('Could not create socket; ' . socket_strerror(socket_last_error()), socket_last_error());
            }
            socket_set_option($socket4, \SOL_SOCKET, \SO_SNDTIMEO, $this->msToSolArray(self::$defaultSendTimeout));
            socket_set_option($socket4, \SOL_SOCKET, \SO_RCVTIMEO, $this->msToSolArray(self::$defaultRecvTimeout));
        }
        /**
         * @var \ArrayIterator<Host>
         */
        $hosts = new \ArrayIterator($this->hosts);
        while ($hosts->valid()) {
            /**
             * @var Host
             */
            $host = $hosts->current();
            [$port, $ip6s, $ip4s] = [$host->getPort(), $host->getIp6s(), $host->getIp4s()];
            if (!self::$forceIpv4 && !empty($ip6s)) {
                foreach ($ip6s as $ip) {
                    if (self::$debugMode) {
                        $this->log("Connecting to $ip:$port...");
                    }
                    $rsock = @socket_connect($socket6, $ip, $port);
                    if ($rsock) {
                        if (self::$debugMode) {
                            $this->log("Connected to $ip:$port!");
                        }
                        // In case we were able to create a TPC v6 connection, we drop any v4 connection
                        // if exists
                        if (isset($socket4) && (null !== $socket4)) {
                            @socket_close($socket4);
                        }
                        $this->socket = $socket6;
                        return;
                    }
                    
                    if (self::$debugMode) {
                        $this->log("Socket connect to $ip:$port failed; " . socket_strerror(socket_last_error()));
                    }
                }
            }
            if (!self::$forceIpv6 && !empty($ip4s)) {
                foreach ($ip4s as $ip) {
                    if (self::$debugMode) {
                        $this->log("Using ipv4, Connecting to $ip:$port...");
                    }
                    $rsock = @socket_connect($socket4, $ip, $port);
                    if ($rsock) {
                        if (self::$debugMode) {
                            $this->log("Using ipv4, Connected to $ip:$port!");
                        }
                        // In case we were able to create a TPC v6 connection, we drop any v4 connection
                        // if exists
                        if (isset($socket6) && null !== $socket6) {
                            @socket_close($socket6);
                        }
                        $this->socket = $socket4;
                        return;
                    }
                    
                    if (self::$debugMode) {
                        $this->log("Using ipv4, Socket connect to $ip:$port failed; " . socket_strerror(socket_last_error()));
                    }
                }
            }
            $hosts->next();
        }
        throw new SocketTransportException('Could not connect to any of the specified hosts');
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
     * @throws SocketTransportException
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
            throw new SocketTransportException('Could not examine socket; ' . socket_strerror(socket_last_error()), socket_last_error());
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
            throw new SocketTransportException('Could not read ' . $length . ' bytes from socket; ' . socket_strerror(socket_last_error()), socket_last_error());
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
                throw new SocketTransportException('Could not read ' . $length . ' bytes from socket; ' . socket_strerror(socket_last_error()), socket_last_error());
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
                throw new SocketTransportException('Could not examine socket; ' . socket_strerror(socket_last_error()), socket_last_error());
            }

            if (!empty($excepts)) {
                throw new SocketTransportException('Socket exception while waiting for data; ' . socket_strerror(socket_last_error()), socket_last_error());
            }

            if (empty($rsock)) {
                throw new SocketTransportException('Timed out waiting for data on socket');
            }
        }
        return $data;
    }

    public function write(string $buffer, ?int $chunkSize = null)
    {
        $bytes = $chunkSize ?? strlen($buffer);
        $writeTimeout = socket_get_option($this->socket, \SOL_SOCKET, \SO_SNDTIMEO);

        while ($bytes > 0) {
            $wrote = socket_write($this->socket, $buffer, $bytes);
            if (false === $wrote) {
                throw new SocketTransportException('Could not write ' . $chunkSize . ' bytes to socket; ' . socket_strerror(socket_last_error()), socket_last_error());
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
                throw new SocketTransportException('Could not examine socket; ' . socket_strerror(socket_last_error()), socket_last_error());
            }

            if (!empty($excepts)) {
                throw new SocketTransportException('Socket exception while waiting to write data; ' . socket_strerror(socket_last_error()), socket_last_error());
            }

            if (empty($wsock)) {
                throw new SocketTransportException('Timed out waiting to write data on socket');
            }
        }
    }

    /**
     * Resolve the hostnames into IPs, and sort them into IPv4 or IPv6 groups.
     * If using DNS hostnames, and all lookups fail, a \InvalidArgumentException is thrown.
     *
     * @param array $hosts
     * @param int|string|int[]|string[] $name
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
     * Static method setting the forceIpv4 for the transport
     * 
     * @param bool $value 
     * @return void 
     */
    public static function setForceIpv4(bool $value)
    {
        static::$forceIpv4 = $value;
        DNS::$forceIpv4 = $value;
    }

    /**
     * Static method setting the forceIpv6 for the transport
     * 
     * @param bool $value 
     * @return void 
     */
    public static function setForceIpv6(bool $value)
    {
        static::$forceIpv6 = $value;
        DNS::$forceIpv6 = $value;
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
     * Log debug message using the debug logger
     * 
     * @param string $message 
     * @return void 
     */
    private function log(string $message)
    {
        \call_user_func($this->debugLogger, $message);
    }

    /**
     * Convert a milliseconds into a socket sec+usec array.
     *
     * @param int $ms
     *
     * @return array
     */
    private function msToSolArray(int $ms)
    {
        $usec = $ms * 1000;
        return ['sec' => floor($usec / 1000000), 'usec' => $usec % 1000000];
    }
}
