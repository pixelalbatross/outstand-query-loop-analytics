<?php
/**
 * @license MIT
 *
 * Modified by Sérgio Santos on 06-July-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Outstand\WP\QueryLoop\Analytics\Dependencies\GuzzleHttp;

use Outstand\WP\QueryLoop\Analytics\Dependencies\Psr\Http\Message\MessageInterface;

interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
