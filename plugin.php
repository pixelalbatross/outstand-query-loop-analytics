<?php // phpcs:ignore Generic.Commenting.DocComment.MissingShort
/**
 * @wordpress-plugin
 * Plugin Name:       Outstand Query Loop Analytics
 * Description:       Populates the Query Loop block with popular posts based on analytics data.
 * Plugin URI:        https://outstand.site/?utm_source=wp-plugins&utm_medium=outstand-query-loop-analytics&utm_campaign=plugin-uri
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Version:           1.1.1
 * Author:            Outstand
 * Author URI:        https://outstand.site/?utm_source=wp-plugins&utm_medium=outstand-query-loop-analytics&utm_campaign=author-uri
 * License:           GPL-3.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-3.0-or-later.html
 * Update URI:        https://outstand.site/
 * GitHub Plugin URI: https://github.com/pixelalbatross/outstand-query-loop-analytics
 * Text Domain:       outstand-query-loop-analytics
 * Domain Path:       /languages
 */

namespace Outstand\WP\QueryLoop\Analytics;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_VERSION', '1.1.1' );
define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_BASENAME', plugin_basename( __FILE__ ) );
define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_URL', plugin_dir_url( __FILE__ ) );
define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_PATH', plugin_dir_path( __FILE__ ) );
define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_DIST_URL', OUTSTAND_QUERY_LOOP_ANALYTICS_URL . 'build/' );
define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_DIST_PATH', OUTSTAND_QUERY_LOOP_ANALYTICS_PATH . 'build/' );

if ( ! file_exists( OUTSTAND_QUERY_LOOP_ANALYTICS_PATH . 'vendor/autoload.php' ) ) {
	return;
}

require_once OUTSTAND_QUERY_LOOP_ANALYTICS_PATH . 'vendor/autoload.php';

if ( class_exists( PucFactory::class ) ) {
	PucFactory::buildUpdateChecker(
		'https://github.com/pixelalbatross/outstand-query-loop-analytics/',
		__FILE__,
		'outstand-query-loop-analytics'
	)->setBranch( 'main' );
}

/**
 * Load the plugin.
 */
add_action(
	'plugins_loaded',
	function () {
		$plugin = Plugin::get_instance();
		$plugin->enable();
	}
);

register_activation_hook( __FILE__, [ Plugin::class, 'on_activation' ] );
register_deactivation_hook( __FILE__, [ Plugin::class, 'on_deactivation' ] );
