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

interface SocketWriter
{
    /**
     * Write (all) data to the socket.
     * Timeout throws SocketTransportException.
     *
     * @throws SocketTransportException
     *
     * @return void
     */
    public function write(string $buffer, int $chunkSize = null);
}
