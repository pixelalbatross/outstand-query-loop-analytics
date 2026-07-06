<?php

namespace Outstand\WP\QueryLoop\Analytics;

/**
 * Get popular posts from the cached analytics data.
 *
 * @param int $count Number of posts to return.
 * @return array<array{post_id: int, pageviews: int}>
 */
function get_popular_posts( int $count = 5 ): array {
	$data = get_option( Analytics::CACHE_KEY, [] );

	if ( ! is_array( $data ) ) {
		return [];
	}

	return array_slice( $data, 0, max( 0, $count ) );
}

/**
 * Get the cached ranked post IDs.
 *
 * @return array<int>
 */
function get_popular_post_ids(): array {
	return QueryPopularPosts::get_popular_post_ids();
}

/**
 * Get timestamp of the most recent successful sync.
 *
 * @return int|null Unix timestamp, or null if never synced.
 */
function get_last_sync_time(): ?int {
	$ts = get_transient( Analytics::LAST_SYNC_KEY );
	return $ts ? (int) $ts : null;
}
