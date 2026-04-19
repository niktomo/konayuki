<?php

declare(strict_types=1);

namespace Konayuki\Hint;

/**
 * Resolves the host's primary IP address for hint-based worker_id allocation.
 *
 * Strategy: open a UDP socket to a non-routable public address (no packets sent —
 * UDP "connect" is just a routing-table lookup) and read the local socket name.
 * This returns the IP the OS would actually use for outbound traffic, which is
 * more reliable than `gethostbyname(gethostname())` — that can return 127.0.1.1
 * on Debian/Ubuntu due to /etc/hosts conventions.
 *
 * Falls back to gethostbyname() if ext-sockets is unavailable.
 */
final class IpResolver
{
    /**
     * Returns the host's primary IPv4 or IPv6 address.
     */
    public static function primaryIp(): string
    {
        $ip = self::detectViaSocket('1.1.1.1', 80);
        if ($ip !== null) {
            return $ip;
        }

        return self::detectViaHostname();
    }

    /**
     * Returns the host's primary IPv4 address. Throws if only IPv6 is available.
     */
    public static function primaryIpv4(): string
    {
        $ip = self::primaryIp();
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new \RuntimeException("Primary IP is not IPv4: {$ip}");
        }

        return $ip;
    }

    private static function detectViaSocket(string $remoteIp, int $remotePort): ?string
    {
        if (! function_exists('socket_create')) {
            return null;
        }
        $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock === false) {
            return null;
        }
        try {
            if (! @socket_connect($sock, $remoteIp, $remotePort)) {
                return null;
            }
            $localIp = '';
            $localPort = 0;
            if (! @socket_getsockname($sock, $localIp, $localPort)) {
                return null;
            }

            return $localIp !== '' ? $localIp : null;
        } finally {
            socket_close($sock);
        }
    }

    private static function detectViaHostname(): string
    {
        $hostname = gethostname();
        if (! is_string($hostname) || $hostname === '') {
            throw new \RuntimeException('Cannot determine primary IP: gethostname() failed');
        }
        $ip = gethostbyname($hostname);
        if ($ip === $hostname) {
            throw new \RuntimeException("Cannot resolve hostname to IP: {$hostname}");
        }

        return $ip;
    }
}
