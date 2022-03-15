<?php

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