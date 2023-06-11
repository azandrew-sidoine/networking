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

class Host
{
    /**
     * Host name property.
     *
     * @var string
     */
    private $name;

    /**
     * Host port number.
     *
     * @var int|string
     */
    private $port;

    /**
     * IP6 list of addresses.
     *
     * @var array
     */
    private $ip6s;

    /**
     * IP4 list of addresses.
     *
     * @var array
     */
    private $ip4s;

    /**
     * Creates class instance.
     *
     * @param int|string $port
     * @param string[]   $ip4s
     * @param string[]   $ip6s
     *
     * @return static
     */
    public function __construct(string $name, $port, array $ip4s = [], array $ip6s = [])
    {
        $this->name = $name;
        $this->port = $port;
        $this->ip4s = $ip4s;
        $this->ip6s = $ip6s;
    }

    /**
     * Returns the name of the TCP host.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the port number of the TCP host.
     *
     * @return int|string
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Returns the list of ip v4 addresses.
     *
     * @return array
     */
    public function getIp4s()
    {
        return $this->ip4s ?? [];
    }

    /**
     * Returns the list of ip v6 addresses.
     *
     * @return array
     */
    public function getIp6s()
    {
        return $this->ip6s ?? [];
    }
}
