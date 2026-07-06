<?php
/**
 * @license Apache-2.0
 *
 * Modified by Sérgio Santos on 06-July-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

if (class_exists('Outstand_QueryLoopAnalytics_Google_Client', false)) {
    // Prevent error with preloading in PHP 7.4
    // @see https://github.com/googleapis/google-api-php-client/issues/1976
    return;
}

$classMap = [
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Client' => 'Outstand_QueryLoopAnalytics_Google_Client',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Service' => 'Outstand_QueryLoopAnalytics_Google_Service',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\AccessToken\\Revoke' => 'Outstand_QueryLoopAnalytics_Google_AccessToken_Revoke',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\AccessToken\\Verify' => 'Outstand_QueryLoopAnalytics_Google_AccessToken_Verify',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Model' => 'Outstand_QueryLoopAnalytics_Google_Model',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Utils\\UriTemplate' => 'Outstand_QueryLoopAnalytics_Google_Utils_UriTemplate',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\AuthHandler\\Guzzle6AuthHandler' => 'Outstand_QueryLoopAnalytics_Google_AuthHandler_Guzzle6AuthHandler',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\AuthHandler\\Guzzle7AuthHandler' => 'Outstand_QueryLoopAnalytics_Google_AuthHandler_Guzzle7AuthHandler',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\AuthHandler\\AuthHandlerFactory' => 'Outstand_QueryLoopAnalytics_Google_AuthHandler_AuthHandlerFactory',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Http\\Batch' => 'Outstand_QueryLoopAnalytics_Google_Http_Batch',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Http\\MediaFileUpload' => 'Outstand_QueryLoopAnalytics_Google_Http_MediaFileUpload',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Http\\REST' => 'Outstand_QueryLoopAnalytics_Google_Http_REST',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Task\\Retryable' => 'Outstand_QueryLoopAnalytics_Google_Task_Retryable',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Task\\Exception' => 'Outstand_QueryLoopAnalytics_Google_Task_Exception',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Task\\Runner' => 'Outstand_QueryLoopAnalytics_Google_Task_Runner',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Collection' => 'Outstand_QueryLoopAnalytics_Google_Collection',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Service\\Exception' => 'Outstand_QueryLoopAnalytics_Google_Service_Exception',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Service\\Resource' => 'Outstand_QueryLoopAnalytics_Google_Service_Resource',
    'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Exception' => 'Outstand_QueryLoopAnalytics_Google_Exception',
];

foreach ($classMap as $class => $alias) {
    class_alias($class, $alias);
}

/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
class Outstand_QueryLoopAnalytics_Google_Task_Composer extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Task\Composer
{
}

/** @phpstan-ignore-next-line */
if (\false) {
    class Outstand_QueryLoopAnalytics_Google_AccessToken_Revoke extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\AccessToken\Revoke
    {
    }
    class Outstand_QueryLoopAnalytics_Google_AccessToken_Verify extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\AccessToken\Verify
    {
    }
    class Outstand_QueryLoopAnalytics_Google_AuthHandler_AuthHandlerFactory extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\AuthHandler\AuthHandlerFactory
    {
    }
    class Outstand_QueryLoopAnalytics_Google_AuthHandler_Guzzle6AuthHandler extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\AuthHandler\Guzzle6AuthHandler
    {
    }
    class Outstand_QueryLoopAnalytics_Google_AuthHandler_Guzzle7AuthHandler extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\AuthHandler\Guzzle7AuthHandler
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Client extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Client
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Collection extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Collection
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Exception extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Exception
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Http_Batch extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Http\Batch
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Http_MediaFileUpload extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Http\MediaFileUpload
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Http_REST extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Http\REST
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Model extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Model
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Service extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Service
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Service_Exception extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Service\Exception
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Service_Resource extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Service\Resource
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Task_Exception extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Task\Exception
    {
    }
    interface Outstand_QueryLoopAnalytics_Google_Task_Retryable extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Task\Retryable
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Task_Runner extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Task\Runner
    {
    }
    class Outstand_QueryLoopAnalytics_Google_Utils_UriTemplate extends \Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Utils\UriTemplate
    {
    }
}
