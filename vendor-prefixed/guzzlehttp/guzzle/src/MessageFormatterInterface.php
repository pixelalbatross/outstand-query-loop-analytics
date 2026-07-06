<?php
/**
 * @license MIT
 *
 * Modified by Sérgio Santos on 06-July-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Outstand\WP\QueryLoop\Analytics\Dependencies\GuzzleHttp;

use Outstand\WP\QueryLoop\Analytics\Dependencies\Psr\Http\Message\RequestInterface;
use Outstand\WP\QueryLoop\Analytics\Dependencies\Psr\Http\Message\ResponseInterface;

interface MessageFormatterInterface
{
    /**
     * Returns a formatted message string.
     *
     * @param RequestInterface       $request  Request that was sent
     * @param ResponseInterface|null $response Response that was received
     * @param \Throwable|null        $error    Exception that was received
     */
    public function format(RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $error = null): string;
}
