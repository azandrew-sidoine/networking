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

/**
 * The socket method uses raw network packet data to try sending an ICMP ping
 * packet to a server, then measures the response time. Using this method
 * requires the script to be run with root privileges, though, so this method
 * only works reliably on Windows systems and on Linux servers where the
 * script is not being run as a web user.
 */
class SocketClient implements ClientInterface
{
    /**
     * @var string
     */
    private $data;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function send(string $host, ?int $port = null)
    {
        /**
         * Calculate a checksum.
         *
         * @param string $data data for which checksum will be calculated
         *
         * @return string binary string checksum of $data
         */
        $get_check_sum = static function (string $data) {
            if (\strlen($data) % 2) {
                $data .= "\x00";
            }

            $bit = unpack('n*', $data);
            $sum = array_sum($bit);

            while ($sum >> 16) {
                $sum = ($sum >> 16) + ($sum & 0xFFFF);
            }

            return pack('n*', ~$sum);
        };
        // Create a package.
        $type = "\x08";
        $code = "\x00";
        $checksum = "\x00\x00";
        $identifier = "\x00\x00";
        $seq_number = "\x00\x00";
        // Calculate the checksum.
        $checksum = $get_check_sum($type.$code.$checksum.$identifier.$seq_number.$this->data);
        // Finalize the package.
        $package = $type.$code.$checksum.$identifier.$seq_number.$this->data;

        try {
            $latency = false;
            $ip = preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $host) ? $host : null;
            // Create a socket, connect to server, then read socket and calculate.
            if ($socket = @socket_create(\AF_INET, \SOCK_RAW, getprotobyname('icmp'))) {
                socket_set_option(
                    $socket,
                    \SOL_SOCKET,
                    \SO_RCVTIMEO,
                    [
                        'sec' => 10,
                        'usec' => 0,
                    ]
                );
                // Prevent errors from being printed when host is unreachable.
                @socket_connect($socket, $host, $port);
                // Get Ip address
                if (null === $ip) {
                    @socket_getpeername($socket, $ip);
                }
                $start = microtime(true);
                // Send the package.
                @socket_send($socket, $package, \strlen($package), 0);
                if (false !== @socket_read($socket, 255)) {
                    $latency = microtime(true) - $start;
                    $latency = round($latency * 1000, 4);
                }
            }
            return new PingResult($latency, null, ($error = socket_last_error()) ? socket_strerror($error) : null, $ip);
        } finally {
            if ($socket) {
                // Close the socket.
                @socket_close($socket);
            }
        }
    }
}
