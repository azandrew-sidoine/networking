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

use Drewlabs\Net\Ping\FSockOpenClient;
use Drewlabs\Net\Ping\PingResult;
use PHPUnit\Framework\TestCase;

class FSockOpenClientTest extends TestCase
{
    public function test_fsock_send()
    {
        $client = new FSockOpenClient(2000);
        $response = $client->send('www.google.com');
        $this->assertNotNull($response->latency());
        $this->assertInstanceOf(PingResult::class, $response);
    }
}
