<?php

declare(strict_types=1);

/*
 * This file is part of the Drewlabs package.
 *
 * (c) Sidoine Azandrew <azandrewdevelopper@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Drewlabs\Net\Ping;

class Client
{
    /**
     * Host server IP/Domain.
     *
     * @var string
     */
    private $host;

    /**
     * TTL frequence of the request.
     *
     * @var int
     */
    private $ttl;

    /**
     * Wait timeout of each ping request.
     *
     * @var int
     */
    private $timeout;

    /**
     * Server port to ping.
     *
     * @var int
     */
    private $port = 80;

    /**
     * Creates an instance of the Ping client.
     *
     * @param string   $host    the host to be pinged
     * @param int|null $port    number
     * @param int      $timeout timeout (in ms) used for ping and fsockopen()
     * @param int      $ttl     Time-to-live (TTL) (You may get a 'Time to live exceeded' error if this
     *                          value is set too low. The TTL value indicates the scope or range in whicha packet may
     *                          be forwarded. By convention:
     *                          - 0 = same host
     *                          - 1 = same subnet
     *                          - 32 = same site
     *                          - 64 = same region
     *                          - 128 = same continent
     *                          - 255 = unrestricted
     *
     * @throws \Exception if the host is not set
     */
    public function __construct(?string $host = null, ?int $port = 80, int $timeout = 1000, int $ttl = 255)
    {
        [$host, $port] = $this->parseHost($host, $port);
        $this->host = $host;
        $this->port = $port ?? $this->port;
        $this->ttl = $ttl;
        $this->timeout = $timeout;
    }

    public static function fromArray(array $values)
    {
        $self = new self();
        foreach ($values as $key => $value) {
            if (property_exists($self, $key)) {
                $self->{$key} = $value;
            }
        }

        return $self;
    }

    public function toArray()
    {
        return [
            'ttl' => $this->ttl,
            'timeout' => $this->timeout,
            'host' => $this->host,
        ];
    }

    /**
     * Ping a host.
     *
     * @param string $method
     *                       Method to use when pinging:
     *                       - exec (default): Pings through the system ping command. Fast and
     *                       robust, but a security risk if you pass through user-submitted data.
     *                       - fsockopen: Pings a server on port 80.
     *                       - socket: Creates a RAW network socket. Only usable in some
     *                       environments, as creating a SOCK_RAW socket requires root privileges.
     *
     * @throws \InvalidArgumentException if $method is not supported
     *
     * @return PingResult
     */
    public function request($method = Method::EXEC_BIN)
    {
        if (!isset($this->host)) {
            throw new \LogicException('Error: Host name not supplied.');
        }
        switch ($method) {
            case Method::EXEC_BIN:
                $client = new BinaryClient($this->ttl, min(3600, $this->timeout));
                break;
            case Method::FSOCKOPEN:
                $client = new FSockOpenClient(min(3600, $this->timeout) / 1000);
                break;
            case Method::SOCK:
                $client = new SocketClient('Ping');
                break;
            default:
                throw new \InvalidArgumentException('Unsupported ping method.');
        }

        return $client->send($this->host, $this->port);
    }

    /**
     * @return (string|int)[]|(int|null|string)[]
     */
    private function parseHost(string $url, ?int $port = null)
    {
        if (!preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $url)) {
            /**
             * @var string
             */
            $host = parse_url($url, \PHP_URL_HOST);
            /**
             * @var string|int
             */
            $port = $port ?? parse_url($url, \PHP_URL_PORT);
            if ($host) {
                return [gethostbyname($host), $port];
            }
            throw new \InvalidArgumentException('HOST URL is not a valid url component nor a valid address');
        }

        return [$url, $port];
    }
}
