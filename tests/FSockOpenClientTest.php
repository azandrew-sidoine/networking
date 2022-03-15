<?php

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