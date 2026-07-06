<?php

namespace Outstand\WP\QueryLoop\Analytics\Tests\Unit;

use Outstand\WP\QueryLoop\Analytics\QueryPopularPosts;
use WP_Query;

use function Outstand\WP\QueryLoop\Analytics\Tests\create_posts;
use function Outstand\WP\QueryLoop\Analytics\Tests\make_block;
use function Outstand\WP\QueryLoop\Analytics\Tests\reset_plugin_state;
use function Outstand\WP\QueryLoop\Analytics\Tests\seed_popular_posts;

/**
 * Tests for the QueryPopularPosts class.
 *
 * @covers \Outstand\WP\QueryLoop\Analytics\QueryPopularPosts
 */
class QueryPopularPostsTest extends \WP_UnitTestCase {

	/**
	 * Module under test.
	 *
	 * @var QueryPopularPosts
	 */
	protected QueryPopularPosts $module;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		reset_plugin_state();
		$this->module = new QueryPopularPosts();
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		reset_plugin_state();
		parent::tearDown();
	}

	/**
	 * Get popular post ids returns empty without data.
	 */
	public function test_get_popular_post_ids_returns_empty_without_data(): void {
		$this->assertSame( [], QueryPopularPosts::get_popular_post_ids() );
	}

	/**
	 * Get popular post ids returns ranked ids.
	 */
	public function test_get_popular_post_ids_returns_ranked_ids(): void {
		$ids = create_posts( 3 );
		seed_popular_posts( $ids );
		$this->assertSame( $ids, QueryPopularPosts::get_popular_post_ids() );
	}

	/**
	 * Get popular post ids scopes to post type.
	 */
	public function test_get_popular_post_ids_scopes_to_post_type(): void {
		$posts = create_posts( 2 );
		$pages = create_posts( 2, [ 'post_type' => 'page' ] );
		seed_popular_posts( [ $posts[0], $pages[0], $posts[1], $pages[1] ] );

		$this->assertSame( [ $posts[0], $posts[1] ], QueryPopularPosts::get_popular_post_ids( 'post' ) );
		$this->assertSame( [ $pages[0], $pages[1] ], QueryPopularPosts::get_popular_post_ids( 'page' ) );
	}

	/**
	 * Maybe order by popularity scopes to query post type.
	 */
	public function test_maybe_order_by_popularity_scopes_to_query_post_type(): void {
		$posts = create_posts( 2 );
		$pages = create_posts( 1, [ 'post_type' => 'page' ] );
		seed_popular_posts( [ $posts[0], $pages[0], $posts[1] ] );

		$block  = make_block( 'core/query', [], [ 'outstand/orderByPopularity' => true ] );
		$result = $this->module->maybe_order_by_popularity( [ 'post_type' => 'page' ], $block, 1 );

		$this->assertSame( [ $pages[0] ], $result['post__in'] );
		$this->assertSame( 'post__in', $result['orderby'] );
	}

	/**
	 * Maybe order by popularity passthrough when disabled.
	 */
	public function test_maybe_order_by_popularity_passthrough_when_disabled(): void {
		$ids = create_posts( 2 );
		seed_popular_posts( $ids );

		$block  = make_block( 'core/query', [], [ 'outstand/orderByPopularity' => false ] );
		$result = $this->module->maybe_order_by_popularity( [], $block, 1 );
		$this->assertSame( [], $result );
	}

	/**
	 * Maybe order by popularity passthrough when cache empty.
	 */
	public function test_maybe_order_by_popularity_passthrough_when_cache_empty(): void {
		$block  = make_block( 'core/query', [], [ 'outstand/orderByPopularity' => true ] );
		$result = $this->module->maybe_order_by_popularity( [ 'post_type' => 'post' ], $block, 1 );
		$this->assertSame( [ 'post_type' => 'post' ], $result );
	}

	/**
	 * Maybe order by popularity applies ids and orderby.
	 */
	public function test_maybe_order_by_popularity_applies_ids_and_orderby(): void {
		$ids = create_posts( 3 );
		seed_popular_posts( $ids );

		$block  = make_block( 'core/query', [], [ 'outstand/orderByPopularity' => true ] );
		$result = $this->module->maybe_order_by_popularity( [], $block, 1 );

		$this->assertSame( $ids, $result['post__in'] );
		$this->assertSame( 'post__in', $result['orderby'] );
		$this->assertArrayNotHasKey( 'order', $result );
	}

	/**
	 * Maybe order by popularity intersects existing post in.
	 */
	public function test_maybe_order_by_popularity_intersects_existing_post_in(): void {
		$ids = create_posts( 4 );
		seed_popular_posts( $ids );

		$allowed = [ $ids[1], $ids[3] ];
		$block   = make_block( 'core/query', [], [ 'outstand/orderByPopularity' => true ] );
		$result  = $this->module->maybe_order_by_popularity(
			[ 'post__in' => $allowed ],
			$block,
			1
		);

		$this->assertSame( $allowed, $result['post__in'] );
		$this->assertSame( 'post__in', $result['orderby'] );
	}

	/**
	 * Maybe order by popularity noop when intersection empty.
	 */
	public function test_maybe_order_by_popularity_noop_when_intersection_empty(): void {
		$ids = create_posts( 2 );
		seed_popular_posts( $ids );

		$block  = make_block( 'core/query', [], [ 'outstand/orderByPopularity' => true ] );
		$result = $this->module->maybe_order_by_popularity(
			[ 'post__in' => [ 9999 ] ],
			$block,
			1
		);

		$this->assertSame( [ 9999 ], $result['post__in'], 'Should not overwrite when no overlap.' );
		$this->assertArrayNotHasKey( 'orderby', $result );
	}

	/**
	 * Register rest query filters hooks each public post type.
	 */
	public function test_register_rest_query_filters_hooks_post_types(): void {
		$this->module->register_rest_query_filters();

		$this->assertSame(
			10,
			has_filter( 'rest_post_query', [ $this->module, 'filter_rest_query' ] )
		);

		remove_filter( 'rest_post_query', [ $this->module, 'filter_rest_query' ], 10 );
	}

	/**
	 * Filter rest query orders by popularity when the flag is present.
	 */
	public function test_filter_rest_query_orders_by_popularity(): void {
		$ids = create_posts( 3 );
		seed_popular_posts( $ids );

		$request = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'outstand_popular_posts', 1 );

		$result = $this->module->filter_rest_query( [ 'post_type' => 'post' ], $request );

		$this->assertSame( $ids, $result['post__in'] );
		$this->assertSame( 'post__in', $result['orderby'] );
		$this->assertArrayNotHasKey( 'order', $result );
	}

	/**
	 * Filter rest query noop without the flag.
	 */
	public function test_filter_rest_query_noop_without_flag(): void {
		$ids = create_posts( 2 );
		seed_popular_posts( $ids );

		$request = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
		$args    = [ 'post_type' => 'post' ];

		$this->assertSame( $args, $this->module->filter_rest_query( $args, $request ) );
	}

	/**
	 * Handle popular posts query arg sets post in.
	 */
	public function test_handle_popular_posts_query_arg_sets_post_in(): void {
		$ids = create_posts( 3 );
		seed_popular_posts( $ids );

		$q = new WP_Query();
		$q->set( 'popular_posts', true );
		$this->module->handle_popular_posts_query_arg( $q );

		$this->assertSame( $ids, $q->get( 'post__in' ) );
		$this->assertSame( 'post__in', $q->get( 'orderby' ) );
	}

	/**
	 * Handle popular posts query arg noop without flag.
	 */
	public function test_handle_popular_posts_query_arg_noop_without_flag(): void {
		$ids = create_posts( 2 );
		seed_popular_posts( $ids );

		$q = new WP_Query();
		$this->module->handle_popular_posts_query_arg( $q );
		$this->assertEmpty( $q->get( 'post__in' ) );
	}

	/**
	 * Handle popular posts query arg intersects.
	 */
	public function test_handle_popular_posts_query_arg_intersects(): void {
		$ids = create_posts( 4 );
		seed_popular_posts( $ids );

		$q = new WP_Query();
		$q->set( 'popular_posts', true );
		$q->set( 'post__in', [ $ids[0], $ids[2] ] );
		$this->module->handle_popular_posts_query_arg( $q );

		$this->assertSame( [ $ids[0], $ids[2] ], $q->get( 'post__in' ) );
	}
}
