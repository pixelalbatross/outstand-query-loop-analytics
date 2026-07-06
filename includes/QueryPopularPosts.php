<?php

namespace Outstand\WP\QueryLoop\Analytics;

use WP_Block;
use WP_Query;
use WP_REST_Request;

/**
 * Modifies Query Loop block queries to order by popularity
 * when the orderByPopularity attribute is enabled.
 */
class QueryPopularPosts extends BaseModule {

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter( 'query_loop_block_query_vars', [ $this, 'maybe_order_by_popularity' ], 10, 3 );
		add_action( 'pre_get_posts', [ $this, 'handle_popular_posts_query_arg' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_query_filters' ] );
	}

	/**
	 * Register REST collection-query filters that power the editor preview.
	 *
	 * The post-template edit component forwards an `outstand_popular_posts` flag
	 * as a REST query arg; each public post type's collection query is intercepted so
	 * the canvas mirrors the frontend popularity ordering. No ServerSideRender,
	 * no dedicated endpoint — the ranking is applied in one place in PHP.
	 */
	public function register_rest_query_filters(): void {
		$post_types = get_post_types(
			[
				'show_in_rest' => true,
				'public'       => true,
			]
		);

		foreach ( $post_types as $post_type ) {
			add_filter( "rest_{$post_type}_query", [ $this, 'filter_rest_query' ], 10, 2 );
		}
	}

	/**
	 * Apply popularity ordering to a REST collection query for the editor
	 * preview when the `outstand_popular_posts` flag is present on the request.
	 *
	 * @param array<string, mixed> $args    WP_Query args for the REST request.
	 * @param WP_REST_Request      $request The REST request.
	 * @return array<string, mixed>
	 */
	public function filter_rest_query( array $args, WP_REST_Request $request ): array {
		if ( ! $request->get_param( 'outstand_popular_posts' ) ) {
			return $args;
		}

		return $this->apply_popular_ordering( $args );
	}

	/**
	 * Modify Query Loop block query vars to order by popularity.
	 *
	 * @param array<string, mixed> $query The query vars.
	 * @param WP_Block             $block The block instance.
	 * @param int                  $page  The current page.
	 * @return array<string, mixed>
	 */
	public function maybe_order_by_popularity( array $query, WP_Block $block, int $page ): array {
		$order_by_popularity = $block->context['outstand/orderByPopularity'] ?? false;

		if ( ! $order_by_popularity ) {
			return $query;
		}

		return $this->apply_popular_ordering( $query );
	}

	/**
	 * Handle the popular_posts custom query arg for WP_Query.
	 *
	 * @param WP_Query $query The query instance.
	 */
	public function handle_popular_posts_query_arg( WP_Query $query ): void {
		if ( ! $query->get( 'popular_posts' ) ) {
			return;
		}

		$post_ids = self::get_popular_post_ids( $query->get( 'post_type' ) );
		if ( empty( $post_ids ) ) {
			return;
		}

		$existing = $query->get( 'post__in' );
		if ( ! empty( $existing ) && is_array( $existing ) ) {
			$post_ids = array_values( array_intersect( $post_ids, $existing ) );
			if ( empty( $post_ids ) ) {
				return;
			}
		}

		$query->set( 'post__in', $post_ids );
		$query->set( 'orderby', 'post__in' );
		$query->set( 'order', '' );
	}

	/**
	 * Get cached popular post IDs in ranked order.
	 *
	 * @param string|array<string> $post_types Optional post type(s) to scope the
	 *                                          result to. Empty returns all cached IDs.
	 * @return array<int>
	 */
	public static function get_popular_post_ids( $post_types = [] ): array {
		$popular = get_option( Analytics::CACHE_KEY, [] );
		if ( ! is_array( $popular ) || empty( $popular ) ) {
			return [];
		}

		$post_ids = array_values( array_filter( array_map( 'intval', array_column( $popular, 'post_id' ) ) ) );

		$post_types = array_filter( (array) $post_types );
		if ( empty( $post_types ) ) {
			return $post_ids;
		}

		return array_values(
			array_filter(
				$post_ids,
				static function ( int $post_id ) use ( $post_types ): bool {
					return in_array( get_post_type( $post_id ), $post_types, true );
				}
			)
		);
	}

	/**
	 * Apply ranked popular IDs to an array-style query, intersecting with any
	 * existing post__in constraint. Shared by the frontend and REST paths.
	 *
	 * @param array<string, mixed> $query The query args (post_type / post__in aware).
	 * @return array<string, mixed>
	 */
	private function apply_popular_ordering( array $query ): array {
		$post_ids = self::get_popular_post_ids( $query['post_type'] ?? [] );
		if ( empty( $post_ids ) ) {
			return $query;
		}

		if ( ! empty( $query['post__in'] ) && is_array( $query['post__in'] ) ) {
			$post_ids = array_values( array_intersect( $post_ids, $query['post__in'] ) );
			if ( empty( $post_ids ) ) {
				return $query;
			}
		}

		$query['post__in'] = $post_ids;
		$query['orderby']  = 'post__in';

		unset( $query['order'] );

		return $query;
	}
}
