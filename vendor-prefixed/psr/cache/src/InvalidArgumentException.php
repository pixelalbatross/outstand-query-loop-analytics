<?php
/**
 * @license MIT
 *
 * Modified by Sérgio Santos on 06-July-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Outstand\WP\QueryLoop\Analytics\Dependencies\Psr\Cache;

/**
 * Exception interface for invalid cache arguments.
 *
 * Any time an invalid argument is passed into a method it must throw an
 * exception class which implements Outstand\WP\QueryLoop\Analytics\Dependencies\Psr\Cache\InvalidArgumentException.
 */
interface InvalidArgumentException extends CacheException
{
}
