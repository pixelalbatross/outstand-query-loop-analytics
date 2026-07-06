<?php
/**
 * @license MIT
 *
 * Modified by Sérgio Santos on 06-July-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace Outstand\WP\QueryLoop\Analytics\Dependencies\GuzzleHttp\Psr7;

use Outstand\WP\QueryLoop\Analytics\Dependencies\Psr\Http\Message\UriInterface;

/**
 * Provides methods to determine if a modified URL should be considered cross-origin.
 *
 * @author Graham Campbell
 */
final class UriComparator
{
    /**
     * Determines if a modified URL should be considered cross-origin with
     * respect to an original URL.
     */
    public static function isCrossOrigin(UriInterface $original, UriInterface $modified): bool
    {
        if (\strcasecmp($original->getHost(), $modified->getHost()) !== 0) {
            return true;
        }

        if ($original->getScheme() !== $modified->getScheme()) {
            return true;
        }

        if (self::computePort($original) !== self::computePort($modified)) {
            return true;
        }

        return false;
    }

    private static function computePort(UriInterface $uri): ?int
    {
        $port = $uri->getPort();

        if (null !== $port) {
            return $port;
        }

        if ('http' === $uri->getScheme()) {
            return 80;
        }

        if ('https' === $uri->getScheme()) {
            return 443;
        }

        return null;
    }

    private function __construct()
    {
        // cannot be instantiated
    }
}
