<?php
/**
 * Uninstall handler for Outstand Query Loop Analytics.
 *
 * Removes all plugin options, transients, and scheduled events.
 *
 * @package Outstand\WP\QueryLoop\Analytics
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Plugin options.
delete_option( 'outstand_query_loop_analytics_settings' );
delete_option( 'outstand_query_loop_analytics_tokens' );
delete_option( 'outstand_query_loop_analytics_popular_posts' );
delete_option( 'outstand_query_loop_analytics_refresh_lock' );
delete_option( 'outstand_query_loop_analytics_autoload_migrated' );

// Transients.
delete_transient( 'outstand_query_loop_analytics_popular_posts' );
delete_transient( 'outstand_query_loop_analytics_error_backoff' );
delete_transient( 'outstand_query_loop_analytics_last_sync' );
delete_transient( 'outstand_query_loop_analytics_properties' );

// Scheduled events.
wp_clear_scheduled_hook( 'outstand_query_loop_analytics_fetch' );
