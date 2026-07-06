<?php
/**
 * @license BSD-3-Clause
 *
 * Modified by Sérgio Santos on 06-July-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Outstand\WP\QueryLoop\Analytics\Dependencies\Firebase\JWT;

class ExpiredException extends \UnexpectedValueException implements JWTExceptionWithPayloadInterface
{
    private object $payload;

    private ?int $timestamp = null;

    public function setPayload(object $payload): void
    {
        $this->payload = $payload;
    }

    public function getPayload(): object
    {
        return $this->payload;
    }

    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }
}
