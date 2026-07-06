<?php
/**
 * Test helper functions.
 *
 * @package Outstand\WP\QueryLoop\Analytics\Tests
 */

namespace Outstand\WP\QueryLoop\Analytics\Tests;

use Outstand\WP\QueryLoop\Analytics\Analytics;
use Outstand\WP\QueryLoop\Analytics\Settings;
use WP_Block;

/**
 * Build a WP_Block instance with context.
 *
 * @param string               $block_name Block name.
 * @param array                $attributes Block attributes.
 * @param array<string, mixed> $context    Block context.
 * @param array<int, array>    $inner      Inner parsed blocks.
 *
 * @return WP_Block
 */
function make_block( string $block_name, array $attributes = [], array $context = [], array $inner = [] ): WP_Block {
	$parsed = [
		'blockName'    => $block_name,
		'attrs'        => $attributes,
		'innerBlocks'  => $inner,
		'innerHTML'    => '',
		'innerContent' => [],
	];

	$block = new WP_Block( $parsed, $context );

	// WP_Block only copies context keys the block type declares in uses_context,
	// so a raw context array passed for an arbitrary block (e.g. core/query) is
	// dropped. Force it to mirror what the plugin filter actually receives at
	// render time (the post-template block, which uses_context these keys).
	$block->context = array_merge( $block->context, $context );

	return $block;
}

/**
 * Create N published posts and return their IDs.
 *
 * @param int                  $n    Number of posts.
 * @param array<string, mixed> $args Additional wp_insert_post args.
 *
 * @return int[]
 */
function create_posts( int $n, array $args = [] ): array {
	$ids = [];

	for ( $i = 0; $i < $n; $i++ ) {
		$ids[] = wp_insert_post(
			array_merge(
				[
					'post_title'  => 'Post ' . ( $i + 1 ),
					'post_status' => 'publish',
					'post_type'   => 'post',
				],
				$args
			)
		);
	}

	return $ids;
}

/**
 * Seed the popular posts cache.
 *
 * @param array<int>      $post_ids   Ranked post IDs.
 * @param array<int>|null $pageviews  Optional pageview values aligned with IDs.
 */
function seed_popular_posts( array $post_ids, ?array $pageviews = null ): void {
	$data = [];
	foreach ( $post_ids as $i => $id ) {
		$data[] = [
			'post_id'   => (int) $id,
			'pageviews' => (int) ( $pageviews[ $i ] ?? ( 100 - $i ) ),
		];
	}
	update_option( Analytics::CACHE_KEY, $data, false );
}

/**
 * Reset all plugin state between tests.
 */
function reset_plugin_state(): void {
	// wp-env ships a persistent object cache dropin, so WP_UnitTestCase does not
	// reset it between tests. Flush it here to drop stale url_to_postid entries
	// (a prior test can cache a path -> 0 before the post exists) and cached options.
	wp_cache_flush();

	delete_option( Settings::OPTION_SETTINGS );
	delete_option( Settings::OPTION_TOKENS );
	delete_option( Analytics::CACHE_KEY );
	delete_option( Analytics::REFRESH_LOCK_KEY );
	delete_option( 'outstand_query_loop_analytics_autoload_migrated' );
	delete_transient( Analytics::ERROR_BACKOFF_KEY );
	delete_transient( Analytics::LAST_SYNC_KEY );
	delete_transient( Settings::PROPERTIES_CACHE_KEY );
	Analytics::unschedule();
}
