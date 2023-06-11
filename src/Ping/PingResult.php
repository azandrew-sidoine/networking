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

class PingResult
{
    /**
     * @var float
     */
    private $latency;

    /**
     * @var string|null
     */
    private $output;

    /**
     * @var string
     */
    private $error;

    /**
     * @var string
     */
    private $ip;

    /**
     * @param float|bool $latency
     *
     * @return void
     */
    public function __construct($latency, string $output = null, string $error = null, string $ip = null)
    {
        $this->latency = $latency;
        $this->output = $output;
        $this->error = $error;
        $this->ip = $ip;
    }

    public function latency()
    {
        return $this->latency;
    }

    public function output()
    {
        return $this->output;
    }

    public function error()
    {
        return $this->error;
    }

    public function ip()
    {
        return $this->ip;
    }
}
