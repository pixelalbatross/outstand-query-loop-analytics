<?php
/**
 * @license MIT
 *
 * Modified by Sérgio Santos on 06-July-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace Outstand\WP\QueryLoop\Analytics\Dependencies\GuzzleHttp\Psr7;

/**
 * @internal
 */
final class Rfc3986
{
    /**
     * Sub-delims for use in a regex.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.2
     */
    public const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    /**
     * Unreserved characters for use in a regex.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.3
     */
    public const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
}
