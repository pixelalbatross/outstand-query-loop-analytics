<?php

namespace Outstand\WP\QueryLoop\Analytics\Tests\Unit;

use Outstand\WP\QueryLoop\Analytics\Analytics;

use function Outstand\WP\QueryLoop\Analytics\get_last_sync_time;
use function Outstand\WP\QueryLoop\Analytics\get_popular_post_ids;
use function Outstand\WP\QueryLoop\Analytics\get_popular_posts;
use function Outstand\WP\QueryLoop\Analytics\Tests\create_posts;
use function Outstand\WP\QueryLoop\Analytics\Tests\reset_plugin_state;
use function Outstand\WP\QueryLoop\Analytics\Tests\seed_popular_posts;

/**
 * Tests for the Functions class.
 *
 * @covers \Outstand\WP\QueryLoop\Analytics\get_popular_posts
 * @covers \Outstand\WP\QueryLoop\Analytics\get_popular_post_ids
 * @covers \Outstand\WP\QueryLoop\Analytics\get_last_sync_time
 */
class FunctionsTest extends \WP_UnitTestCase {

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		reset_plugin_state();
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		reset_plugin_state();
		parent::tearDown();
	}

	/**
	 * Get popular posts returns empty when no cache.
	 */
	public function test_get_popular_posts_returns_empty_when_no_cache(): void {
		$this->assertSame( [], get_popular_posts() );
	}

	/**
	 * Get popular posts slices to count.
	 */
	public function test_get_popular_posts_slices_to_count(): void {
		$ids = create_posts( 5 );
		seed_popular_posts( $ids );
		$this->assertCount( 3, get_popular_posts( 3 ) );
		$this->assertCount( 5, get_popular_posts( 20 ) );
		$this->assertCount( 0, get_popular_posts( 0 ) );
	}

	/**
	 * Get popular post ids returns ranked ids.
	 */
	public function test_get_popular_post_ids_returns_ranked_ids(): void {
		$ids = create_posts( 2 );
		seed_popular_posts( $ids );
		$this->assertSame( $ids, get_popular_post_ids() );
	}

	/**
	 * Get last sync time null when missing.
	 */
	public function test_get_last_sync_time_null_when_missing(): void {
		$this->assertNull( get_last_sync_time() );
	}

	/**
	 * Get last sync time returns timestamp.
	 */
	public function test_get_last_sync_time_returns_timestamp(): void {
		$ts = time() - 42;
		set_transient( Analytics::LAST_SYNC_KEY, $ts, HOUR_IN_SECONDS );
		$this->assertSame( $ts, get_last_sync_time() );
	}
}
