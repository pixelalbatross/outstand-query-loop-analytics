<?php

// For older (pre-2.7.2) verions of google/apiclient
if (
    file_exists(__DIR__ . '/../apiclient/src/Google/Client.php')
    && !class_exists('Outstand_QueryLoopAnalytics_Google_Client', false)
) {
    require_once(__DIR__ . '/../apiclient/src/Google/Client.php');
    if (
        defined('Outstand_QueryLoopAnalytics_Google_Client::LIBVER')
        && version_compare(Outstand_QueryLoopAnalytics_Google_Client::LIBVER, '2.7.2', '<=')
    ) {
        $servicesClassMap = [
            'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Client' => 'Outstand_QueryLoopAnalytics_Google_Client',
            'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Service' => 'Outstand_QueryLoopAnalytics_Google_Service',
            'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Service\\Resource' => 'Outstand_QueryLoopAnalytics_Google_Service_Resource',
            'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Model' => 'Outstand_QueryLoopAnalytics_Google_Model',
            'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Collection' => 'Outstand_QueryLoopAnalytics_Google_Collection',
        ];
        foreach ($servicesClassMap as $alias => $class) {
            class_alias($class, $alias);
        }
    }
}
spl_autoload_register(function ($class) {
    if (0 === strpos($class, 'Google_Service_')) {
        // Autoload the new class, which will also create an alias for the
        // old class by changing underscores to namespaces:
        //     Google_Service_Speech_Resource_Operations
        //      => Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Service\Speech\Resource\Operations
        $classExists = class_exists($newClass = str_replace('_', '\\', $class));
        if ($classExists) {
            return true;
        }
    } elseif (0 === strpos($class, 'Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Service\\')) {
        $relativeClass = substr($class, strlen('Outstand\\WP\\QueryLoop\\Analytics\\Dependencies\\Google\\Service\\'));
        $parts = explode('\\', $relativeClass);
        $leaf = array_pop($parts);
        if (strlen($leaf) > 139) {
            $shortenedLeaf = substr($leaf, 0, 80) . '_' . strtoupper(substr(md5($leaf), 0, 8));
            $subPath = implode('/', $parts);
            $filePath = __DIR__ . '/src/' . ($subPath ? $subPath . '/' : '') . $shortenedLeaf . '.php';
            if (file_exists($filePath)) {
                require_once $filePath;
                return true;
            }
        }
    }
}, true, true);

