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

interface StreamReader
{
    /**
     * Read up to $length bytes from the socket.
     * Does not guarantee that all the bytes are read.
     * Returns false on EOF
     * Returns false on timeout (technically EAGAIN error).
     * Throws SocketTransportException if data could not be read.
     *
     * @throws SocketStreamException
     *
     * @return mixed
     */
    public function read(int $length);

    /**
     * Read all the bytes, and block until they are read.
     * Timeout throws SocketTransportException.
     *
     * @throws SocketStreamException
     *
     * @return string
     */
    public function readAll(int $length);
}
