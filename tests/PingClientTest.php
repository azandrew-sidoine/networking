<?php

namespace Drewlabs\Net\Tests;

use Drewlabs\Net\Ping\Client;
use Drewlabs\Net\Ping\Method;
use Drewlabs\Net\Ping\PingResult;
use PHPUnit\Framework\TestCase;

class PingClientTest extends TestCase
{
    
    public function test_default_request()
    {
        $client = new Client('https://www.liksoft.tg');
        $response = $client->request();
        $this->assertNotNull($response->latency());
        $this->assertNotNull($response->ip());
        $this->assertInstanceOf(PingResult::class, $response);
    }

    public function test_fsockopen_request()
    {
        $client = new Client('https://www.liksoft.tg');
        $response = $client->request(Method::FSOCKOPEN);
        $this->assertNotNull($response->latency());
        $this->assertInstanceOf(PingResult::class, $response);
    }
}