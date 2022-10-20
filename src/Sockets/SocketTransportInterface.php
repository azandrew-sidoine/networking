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

interface SocketTransportInterface extends SocketReader, SocketWriter
{

    /**
     * Returns the socket instance
     * 
     * @return \Socket|resource 
     */
    public function getSocket();

    /**
     * Get an arbitrary option.
     *
     * @param int $option
     * @param int $lvl
     */
    public function getSocketOption($option, $lvl = \SOL_SOCKET);

    /**
     * Set an arbitrary option.
     *
     * @param int   $option
     * @param mixed $value
     * @param int   $lvl
     */
    public function setSocketOption($option, $value, $lvl = \SOL_SOCKET);


    /**
     * Sets the send timeout.
     * Returns true on success, or false.
     *
     * @param int $timeout timeout in milliseconds
     *
     * @return void
     */
    public function setSendTimeout($timeout);

    /**
     * Sets the receive timeout.
     * Returns true on success, or false.
     *
     * @param int $timeout timeout in milliseconds
     *
     * @return void
     */
    public function setRecvTimeout($timeout);

    /**
     * Check if the socket is constructed, and there are no exceptions on it
     * Returns false if it's closed.
     * Throws SocketTransportException is state could not be ascertained.
     *
     * @throws SocketTransportException
     */
    public function isOpen();


    /**
     * Open the socket, trying to connect to each host in succession.
     * This will prefer IPv6 connections if forceIpv4 is not enabled.
     * If all hosts fail, a SocketTransportException is thrown.
     *
     * @return void
     * 
     * @throws SocketTransportException
     */
    public function open();

    /**
     * Do a clean shutdown of the socket.
     * Since we don't reuse sockets, we can just close and forget about it,
     * but we choose to wait (linger) for the last data to come through.
     * 
     * @return void
     */
    public function close();
}
