<?php
/**
 * @license MIT
 *
 * Modified by Sérgio Santos on 06-July-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Outstand\WP\QueryLoop\Analytics\Dependencies\GuzzleHttp;

final class TransportSharing
{
    public const NONE = 'none';
    public const HANDLER_PREFER = 'handler_prefer';
    public const HANDLER_REQUIRE = 'handler_require';

    private function __construct()
    {
    }
}
