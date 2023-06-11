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

interface ClientInterface
{
    /**
     * Send a Ping request to a given host.
     *
     * @param int $port
     *
     * @return PingResult
     */
    public function send(string $host, int $port = null);
}
