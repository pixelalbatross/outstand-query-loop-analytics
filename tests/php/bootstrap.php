<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Outstand\WP\QueryLoop\Analytics
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php — run 'npm run test:setup' to start wp-env." . PHP_EOL; // phpcs:ignore
	exit( 1 );
}

// Load Composer autoloader.
$plugin_dir = dirname( __DIR__, 2 );
if ( file_exists( $plugin_dir . '/vendor/autoload.php' ) ) {
	require_once $plugin_dir . '/vendor/autoload.php';
}

// Define plugin constants normally set in plugin.php.
if ( ! defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_VERSION' ) ) {
	define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_VERSION', '1.0.0-test' );
}

if ( ! defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_BASENAME' ) ) {
	define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_BASENAME', 'outstand-query-loop-analytics/plugin.php' );
}

if ( ! defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_PATH' ) ) {
	define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_PATH', $plugin_dir . '/' );
}

if ( ! defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_URL' ) ) {
	define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_URL', 'http://example.org/wp-content/plugins/outstand-query-loop-analytics/' );
}

if ( ! defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_DIST_PATH' ) ) {
	define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_DIST_PATH', OUTSTAND_QUERY_LOOP_ANALYTICS_PATH . 'build/' );
}

if ( ! defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_DIST_URL' ) ) {
	define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_DIST_URL', OUTSTAND_QUERY_LOOP_ANALYTICS_URL . 'build/' );
}

// Load WordPress test suite functions.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin's modules on muplugins_loaded.
 *
 * Skip plugin.php so the PUC updater isn't wired during tests.
 */
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		$plugin = \Outstand\WP\QueryLoop\Analytics\Plugin::get_instance();
		$plugin->enable();
	}
);

// Bootstrap WordPress test suite.
require $_tests_dir . '/includes/bootstrap.php';

// Load test helper functions (after WP is booted).
require_once __DIR__ . '/helpers/functions.php';
