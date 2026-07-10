<?php

namespace Outstand\WP\QueryLoop\Analytics\Tests\Unit;

use Outstand\WP\QueryLoop\Analytics\Analytics;
use Outstand\WP\QueryLoop\Analytics\Settings;
use ReflectionClass;

use function Outstand\WP\QueryLoop\Analytics\Tests\reset_plugin_state;

/**
 * Tests for the Analytics class.
 *
 * @covers \Outstand\WP\QueryLoop\Analytics\Analytics
 */
class AnalyticsTest extends \WP_UnitTestCase {

	/**
	 * Module under test.
	 *
	 * @var Analytics
	 */
	protected Analytics $module;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		reset_plugin_state();
		// resolve_path_to_post_id() strips the query string and resolves pretty
		// paths via url_to_postid(), so the resolver needs a permalink structure.
		$this->set_permalink_structure( '/%postname%/' );
		$this->module = new Analytics();
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		reset_plugin_state();
		parent::tearDown();
	}

	/**
	 * Fetch data noop when not configured.
	 */
	public function test_fetch_data_noop_when_not_configured(): void {
		$this->assertFalse( $this->module->fetch_data() );
	}

	/**
	 * Fetch data noop during error backoff.
	 */
	public function test_fetch_data_noop_during_error_backoff(): void {
		// Configure plugin.
		Settings::set_tokens(
			[
				'access_token'  => 'x',
				'refresh_token' => 'y',
			]
		);
		update_option(
			Settings::OPTION_SETTINGS,
			[
				'property_id'   => '1',
				'client_id'     => 'a',
				'client_secret' => 'b',
			]
		);

		set_transient( Analytics::ERROR_BACKOFF_KEY, 'ga4_fetch_failed', 60 );
		$this->assertFalse( $this->module->fetch_data() );
	}

	/**
	 * Maybe schedule cron schedules when configured.
	 */
	public function test_maybe_schedule_cron_schedules_when_configured(): void {
		Settings::set_tokens(
			[
				'access_token'  => 'x',
				'refresh_token' => 'y',
			]
		);
		update_option(
			Settings::OPTION_SETTINGS,
			[
				'property_id'   => '1',
				'client_id'     => 'a',
				'client_secret' => 'b',
			]
		);

		$this->module->maybe_schedule_cron();

		$this->assertNotFalse( wp_next_scheduled( Analytics::CRON_HOOK ) );
	}

	/**
	 * Maybe schedule cron noop when not configured.
	 */
	public function test_maybe_schedule_cron_noop_when_not_configured(): void {
		$this->module->maybe_schedule_cron();
		$this->assertFalse( wp_next_scheduled( Analytics::CRON_HOOK ) );
	}

	/**
	 * Register cron schedule exposes interval.
	 */
	public function test_register_cron_schedule_exposes_interval(): void {
		update_option( Settings::OPTION_SETTINGS, [ 'cache_duration' => 6 ] );
		$schedules = $this->module->register_cron_schedule( [] );
		$this->assertArrayHasKey( Analytics::CRON_SCHEDULE, $schedules );
		$this->assertSame( 6 * HOUR_IN_SECONDS, $schedules[ Analytics::CRON_SCHEDULE ]['interval'] );
	}

	/**
	 * Resolve path strips query and fragment.
	 */
	public function test_resolve_path_strips_query_and_fragment(): void {
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_name'   => 'hello-world',
			]
		);

		$this->assertSame( $post_id, $this->invoke_resolver( '/hello-world/' ) );
		$this->assertSame( $post_id, $this->invoke_resolver( '/hello-world/?utm_source=x#section' ) );
	}

	/**
	 * Resolve path handles full url.
	 */
	public function test_resolve_path_handles_full_url(): void {
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_name'   => 'full-url-post',
			]
		);

		$resolved = $this->invoke_resolver( home_url( '/full-url-post/' ) );
		$this->assertSame( $post_id, $resolved );
	}

	/**
	 * Resolve path handles empty input.
	 */
	public function test_resolve_path_handles_empty_input(): void {
		$this->assertSame( 0, $this->invoke_resolver( '' ) );
		$this->assertSame( 0, $this->invoke_resolver( '   ' ) );
	}

	/**
	 * Resolve path prepends leading slash.
	 */
	public function test_resolve_path_prepends_leading_slash(): void {
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_name'   => 'leading-slash',
			]
		);

		$resolved = $this->invoke_resolver( 'leading-slash/' );
		$this->assertSame( $post_id, $resolved );
	}

	/**
	 * Reschedule on settings change re schedules.
	 */
	public function test_reschedule_on_settings_change_re_schedules(): void {
		// Seed credentials first; tokens follow (mirrors the real OAuth flow, and
		// keeps the update_option_ credential-change side effect from wiping them).
		update_option(
			Settings::OPTION_SETTINGS,
			[
				'property_id'    => '1',
				'client_id'      => 'a',
				'client_secret'  => 'b',
				'cache_duration' => 12,
			]
		);
		Settings::set_tokens(
			[
				'access_token'  => 'x',
				'refresh_token' => 'y',
			]
		);
		$this->module->maybe_schedule_cron();
		$initial = wp_next_scheduled( Analytics::CRON_HOOK );
		$this->assertNotFalse( $initial );

		$this->module->reschedule_on_settings_change(
			[ 'cache_duration' => 12 ],
			[ 'cache_duration' => 6 ]
		);
		$this->assertNotFalse( wp_next_scheduled( Analytics::CRON_HOOK ) );
	}

	/**
	 * Reschedule on settings change triggers on property change.
	 */
	public function test_reschedule_on_settings_change_triggers_on_property_change(): void {
		// Seed credentials first; tokens follow (mirrors the real OAuth flow, and
		// keeps the update_option_ credential-change side effect from wiping them).
		update_option(
			Settings::OPTION_SETTINGS,
			[
				'property_id'    => '1',
				'client_id'      => 'a',
				'client_secret'  => 'b',
				'cache_duration' => 12,
			]
		);
		Settings::set_tokens(
			[
				'access_token'  => 'x',
				'refresh_token' => 'y',
			]
		);
		$this->module->maybe_schedule_cron();
		$this->assertNotFalse( wp_next_scheduled( Analytics::CRON_HOOK ) );

		Analytics::unschedule();
		$this->assertFalse( wp_next_scheduled( Analytics::CRON_HOOK ) );

		$this->module->reschedule_on_settings_change(
			[
				'property_id'    => '1',
				'cache_duration' => 12,
			],
			[
				'property_id'    => '2',
				'cache_duration' => 12,
			]
		);
		$this->assertNotFalse( wp_next_scheduled( Analytics::CRON_HOOK ), 'property_id change must reschedule.' );
	}

	/**
	 * Reschedule on settings change noop when nothing watched changes.
	 */
	public function test_reschedule_on_settings_change_noop_when_nothing_watched_changes(): void {
		Settings::set_tokens(
			[
				'access_token'  => 'x',
				'refresh_token' => 'y',
			]
		);
		update_option(
			Settings::OPTION_SETTINGS,
			[
				'property_id'    => '1',
				'client_id'      => 'a',
				'client_secret'  => 'b',
				'cache_duration' => 12,
			]
		);
		Analytics::unschedule();

		$this->module->reschedule_on_settings_change(
			[
				'property_id'    => '1',
				'client_id'      => 'a',
				'cache_duration' => 12,
				'client_secret'  => 'enc:v1:X',
			],
			[
				'property_id'    => '1',
				'client_id'      => 'a',
				'cache_duration' => 12,
				'client_secret'  => 'enc:v1:Y',
			]
		);

		$this->assertFalse( wp_next_scheduled( Analytics::CRON_HOOK ), 'Changing only encrypted ciphertext must not reschedule.' );
	}

	/**
	 * Refresh lock existing fresh lock blocks refresh.
	 */
	public function test_refresh_lock_existing_fresh_lock_blocks_refresh(): void {
		// Simulate another process holding a fresh lock.
		add_option( Analytics::REFRESH_LOCK_KEY, time(), '', false );

		$ref    = new ReflectionClass( Analytics::class );
		$method = $ref->getMethod( 'refresh_with_lock' );
		$method->setAccessible( true );

		$client = $this->createMock( \Outstand\WP\QueryLoop\Analytics\GoogleClient::class );
		$client->method( 'is_token_expired' )->willReturn( true );

		$result = $method->invoke( $this->module, $client, [ 'refresh_token' => 'r' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'refresh_in_progress', $result->get_error_code() );
	}

	/**
	 * Unschedule clears hook.
	 */
	public function test_unschedule_clears_hook(): void {
		wp_schedule_event( time() + 100, 'hourly', Analytics::CRON_HOOK );
		$this->assertNotFalse( wp_next_scheduled( Analytics::CRON_HOOK ) );
		Analytics::unschedule();
		$this->assertFalse( wp_next_scheduled( Analytics::CRON_HOOK ) );
	}

	/**
	 * Invoke private resolve_path_to_post_id via reflection.
	 *
	 * @param string $path Path to resolve.
	 * @return int Resolved post ID.
	 */
	private function invoke_resolver( string $path ): int {
		$ref    = new ReflectionClass( Analytics::class );
		$method = $ref->getMethod( 'resolve_path_to_post_id' );
		$method->setAccessible( true );
		return (int) $method->invoke( $this->module, $path );
	}
}
