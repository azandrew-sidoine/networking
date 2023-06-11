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

namespace Drewlabs\Net\Tests;

use Drewlabs\Net\Ping\BinaryClient;
use Drewlabs\Net\Ping\PingResult;
use PHPUnit\Framework\TestCase;

class BinaryClientTest extends TestCase
{
    public function test_bin_send()
    {
        $client = new BinaryClient();
        $response = $client->send('www.google.com');
        $this->assertNotNull($response->latency());
        $this->assertNotNull($response->ip());
        $this->assertInstanceOf(PingResult::class, $response);
    }
}
