<?php

namespace Outstand\WP\QueryLoop\Analytics;

use WP_Block;
use WP_REST_Request;

/**
 * Excludes the current post from a Popular Posts Query Loop when the
 * excludeCurrentPost attribute is enabled.
 *
 * Runs independently of popularity ordering (priority 11, after
 * QueryPopularPosts) so exclusion still applies when the popular cache is
 * empty and no post__in constraint was set.
 */
class ExcludeCurrentPost extends BaseModule {

	/**
	 * REST query arg forwarded by the editor preview to carry the current
	 * post ID into the canvas query.
	 *
	 * @var string
	 */
	public const REST_ARG = 'outstand_exclude_current';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter( 'query_loop_block_query_vars', [ $this, 'maybe_exclude_current' ], 11, 2 );
		add_action( 'rest_api_init', [ $this, 'register_rest_query_filters' ] );
	}

	/**
	 * Register REST collection-query filters that power the editor preview.
	 *
	 * Mirrors QueryPopularPosts: each public post type's collection query is
	 * intercepted so the canvas mirrors the frontend exclusion.
	 */
	public function register_rest_query_filters(): void {
		$post_types = get_post_types(
			[
				'show_in_rest' => true,
				'public'       => true,
			]
		);

		foreach ( $post_types as $post_type ) {
			add_filter( "rest_{$post_type}_query", [ $this, 'filter_rest_query' ], 11, 2 );
		}
	}

	/**
	 * Exclude the current post from a Query Loop block query when enabled.
	 *
	 * @param array<string, mixed> $query The query vars.
	 * @param WP_Block             $block The block instance.
	 * @return array<string, mixed>
	 */
	public function maybe_exclude_current( array $query, WP_Block $block ): array {
		$exclude = $block->context['outstand/excludeCurrentPost'] ?? false;

		if ( ! $exclude ) {
			return $query;
		}

		return $this->apply_exclusion( $query, self::resolve_current_id() );
	}

	/**
	 * Apply exclusion to a REST collection query for the editor preview when
	 * the current post ID is forwarded on the request.
	 *
	 * @param array<string, mixed> $args    WP_Query args for the REST request.
	 * @param WP_REST_Request      $request The REST request.
	 * @return array<string, mixed>
	 */
	public function filter_rest_query( array $args, WP_REST_Request $request ): array {
		// Intentional: anonymous REST requests may pass `outstand_exclude_current` to
		// exclude a post from any public post type's collection. Impact is limited to
		// excluding a single already-public post from the results.
		$id = (int) $request->get_param( self::REST_ARG );

		if ( $id <= 0 ) {
			return $args;
		}

		return $this->apply_exclusion( $args, $id );
	}

	/**
	 * Resolve the ID of the post currently being viewed. Mirrors AQL's
	 * resolution order: queried object first, then the global post.
	 *
	 * @return int Post ID, or 0 when none is resolvable.
	 */
	public static function resolve_current_id(): int {
		global $post;

		$id = (int) get_queried_object_id();

		if ( $id <= 0 && $post && isset( $post->ID ) ) {
			$id = (int) $post->ID;
		}

		return $id;
	}

	/**
	 * Remove a post ID from an array-style query: add it to post__not_in and
	 * drop it from any ranked post__in. Shared by the frontend and REST paths.
	 *
	 * @param array<string, mixed> $query The query args.
	 * @param int                  $id    Post ID to exclude.
	 * @return array<string, mixed>
	 */
	private function apply_exclusion( array $query, int $id ): array {
		if ( $id <= 0 ) {
			return $query;
		}

		$not_in                = array_map( 'intval', (array) ( $query['post__not_in'] ?? [] ) );
		$not_in[]              = $id;
		$query['post__not_in'] = array_values( array_unique( $not_in ) );

		if ( ! empty( $query['post__in'] ) && is_array( $query['post__in'] ) ) {
			$post_in = array_values( array_diff( array_map( 'intval', $query['post__in'] ), [ $id ] ) );

			// An empty post__in means "no restriction" in WP_Query, which would
			// return all posts. Force an impossible ID so the result stays empty.
			$query['post__in'] = empty( $post_in ) ? [ 0 ] : $post_in;
		}

		return $query;
	}
}
