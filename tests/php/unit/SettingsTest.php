<?php

namespace Outstand\WP\QueryLoop\Analytics\Tests\Unit;

use Outstand\WP\QueryLoop\Analytics\Settings;

use function Outstand\WP\QueryLoop\Analytics\Tests\reset_plugin_state;

/**
 * Tests for the Settings class.
 *
 * @covers \Outstand\WP\QueryLoop\Analytics\Settings
 */
class SettingsTest extends \WP_UnitTestCase {

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
	 * Defaults.
	 */
	public function test_defaults(): void {
		$defaults = Settings::get_defaults();
		$this->assertSame( '', $defaults['client_id'] );
		$this->assertSame( '', $defaults['client_secret'] );
		$this->assertSame( '', $defaults['property_id'] );
		$this->assertSame( 30, $defaults['date_range_days'] );
		$this->assertSame( 20, $defaults['fetch_limit'] );
		$this->assertSame( 12, $defaults['cache_duration'] );
	}

	/**
	 * Get settings merges defaults.
	 */
	public function test_get_settings_merges_defaults(): void {
		update_option( Settings::OPTION_SETTINGS, [ 'client_id' => 'abc' ] );
		$settings = Settings::get_settings();
		$this->assertSame( 'abc', $settings['client_id'] );
		$this->assertSame( 30, $settings['date_range_days'], 'Defaults should fill missing keys.' );
	}

	/**
	 * Is configured requires tokens and property.
	 */
	public function test_is_configured_requires_tokens_and_property(): void {
		$this->assertFalse( Settings::is_configured() );

		Settings::set_tokens(
			[
				'access_token'  => 'x',
				'refresh_token' => 'y',
			]
		);
		$this->assertFalse( Settings::is_configured(), 'Needs property_id too.' );

		update_option( Settings::OPTION_SETTINGS, [ 'property_id' => '123' ] );
		$this->assertTrue( Settings::is_configured() );
	}

	/**
	 * Token roundtrip is encrypted.
	 */
	public function test_token_roundtrip_is_encrypted(): void {
		$tokens = [
			'access_token'  => 'ya29.access',
			'refresh_token' => '1//refresh',
			'expires_in'    => 3600,
		];
		$this->assertTrue( Settings::set_tokens( $tokens ) );

		$raw = get_option( Settings::OPTION_TOKENS );
		$this->assertIsString( $raw );
		$this->assertStringNotContainsString( 'ya29.access', $raw, 'Stored token must be encrypted.' );

		$this->assertSame( $tokens, Settings::get_tokens() );
	}

	/**
	 * Get tokens returns null when missing.
	 */
	public function test_get_tokens_returns_null_when_missing(): void {
		$this->assertNull( Settings::get_tokens() );
	}

	/**
	 * Defines credential constants, which cannot be undefined and would leak into
	 * later tests (stripping constant-backed secrets on write). Isolate the process.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_constants_override_stored_credentials(): void {
		update_option(
			Settings::OPTION_SETTINGS,
			[
				'client_id'     => 'stored-id',
				'client_secret' => 'stored-secret',
			]
		);

		if ( ! defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_ID' ) ) {
			define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_ID', 'const-id' );
		}
		if ( ! defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_SECRET' ) ) {
			define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_SECRET', 'const-secret' );
		}

		$settings = Settings::get_settings();
		$this->assertSame( 'const-id', $settings['client_id'] );
		$this->assertSame( 'const-secret', $settings['client_secret'] );
		$this->assertTrue( Settings::has_credential_constants() );
	}

	/**
	 * Sanitize settings coerces numbers.
	 */
	public function test_sanitize_settings_coerces_numbers(): void {
		$settings = new Settings();
		$result   = $settings->sanitize_settings(
			[
				'client_id'       => '  trim-me  ',
				'client_secret'   => 'secret',
				'property_id'     => '42',
				'date_range_days' => '30abc',
				'fetch_limit'     => '-5',
				'cache_duration'  => '12',
			]
		);
		$this->assertSame( 'trim-me', $result['client_id'] );
		$this->assertSame( '42', $result['property_id'] );
		$this->assertSame( 30, $result['date_range_days'] );
		$this->assertSame( 5, $result['fetch_limit'], 'absint should coerce.' );
		$this->assertSame( 12, $result['cache_duration'] );
		$this->assertStringStartsWith( 'enc:v1:', $result['client_secret'], 'Secret must be encrypted.' );
	}

	/**
	 * Sanitize property id rejects non numeric.
	 */
	public function test_sanitize_property_id_rejects_non_numeric(): void {
		$this->assertSame( '', Settings::sanitize_property_id( 'abc' ) );
		$this->assertSame( '', Settings::sanitize_property_id( 'properties/abc' ) );
		$this->assertSame( '123', Settings::sanitize_property_id( '123' ) );
		$this->assertSame( '123', Settings::sanitize_property_id( 'properties/123' ) );
		$this->assertSame( '', Settings::sanitize_property_id( '' ) );
		$this->assertSame( '', Settings::sanitize_property_id( null ) );
	}

	/**
	 * Sanitize settings preserves existing secret on empty input.
	 */
	public function test_sanitize_settings_preserves_existing_secret_on_empty_input(): void {
		// Seed existing encrypted secret via the write path.
		update_option(
			Settings::OPTION_SETTINGS,
			[
				'client_id'     => 'id',
				'client_secret' => 'kept-secret',
			]
		);

		$settings = new Settings();
		$result   = $settings->sanitize_settings(
			[
				'client_id'     => 'id',
				'client_secret' => '',
			]
		);

		$this->assertStringStartsWith( 'enc:v1:', $result['client_secret'] );

		// Simulate full write cycle and verify decrypt.
		update_option( Settings::OPTION_SETTINGS, $result );
		$this->assertSame( 'kept-secret', Settings::get_settings()['client_secret'] );
	}

	/**
	 * Write encrypts plaintext client secret.
	 */
	public function test_write_encrypts_plaintext_client_secret(): void {
		update_option(
			Settings::OPTION_SETTINGS,
			[
				'client_id'     => 'id',
				'client_secret' => 'my-raw-secret',
				'property_id'   => '7',
			]
		);

		$raw = get_option( Settings::OPTION_SETTINGS );
		$this->assertStringStartsWith( 'enc:v1:', $raw['client_secret'] );
		$this->assertStringNotContainsString( 'my-raw-secret', $raw['client_secret'] );
		$this->assertSame( 'my-raw-secret', Settings::get_settings()['client_secret'] );
	}

	/**
	 * Defines credential constants; isolate the process so they cannot leak.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_normalize_strips_constant_backed_credentials(): void {
		if ( ! defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_ID' ) ) {
			define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_ID', 'const-id' );
		}
		if ( ! defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_SECRET' ) ) {
			define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_SECRET', 'const-secret' );
		}

		update_option(
			Settings::OPTION_SETTINGS,
			[
				'client_id'     => 'should-not-persist',
				'client_secret' => 'also-not-persist',
				'property_id'   => '9',
			]
		);

		$raw = get_option( Settings::OPTION_SETTINGS );
		$this->assertSame( '', $raw['client_id'], 'Constant-backed client_id must not reach DB.' );
		$this->assertSame( '', $raw['client_secret'], 'Constant-backed client_secret must not reach DB.' );
		$this->assertSame( '9', $raw['property_id'] );
	}

	/**
	 * Credentials change wipes tokens and caches.
	 */
	public function test_credentials_change_wipes_tokens_and_caches(): void {
		// Seed state.
		update_option(
			Settings::OPTION_SETTINGS,
			[
				'client_id'     => 'old-id',
				'client_secret' => 'old-secret',
				'property_id'   => '1',
			]
		);
		Settings::set_tokens(
			[
				'access_token'  => 'a',
				'refresh_token' => 'r',
			]
		);
		update_option(
			\Outstand\WP\QueryLoop\Analytics\Analytics::CACHE_KEY,
			[
				[
					'post_id'   => 1,
					'pageviews' => 10,
				],
			],
			false
		);

		$this->assertNotNull( Settings::get_tokens() );

		$settings = new Settings();
		$old      = get_option( Settings::OPTION_SETTINGS );
		$result   = $settings->sanitize_settings(
			[
				'client_id'     => 'new-id',
				'client_secret' => 'new-secret',
				'property_id'   => '1',
			]
		);
		update_option( Settings::OPTION_SETTINGS, $result );

		// Side effects live on the update_option_ hook, not the sanitize callback.
		$settings->on_settings_updated( $old, $result );

		$this->assertNull( Settings::get_tokens(), 'Tokens wiped on credentials change.' );
		$this->assertSame( [], get_option( \Outstand\WP\QueryLoop\Analytics\Analytics::CACHE_KEY, [] ) );
	}

	/**
	 * Render text field never prefills password value.
	 */
	public function test_render_text_field_never_prefills_password_value(): void {
		update_option(
			Settings::OPTION_SETTINGS,
			[
				'client_id'     => 'id',
				'client_secret' => 'sensitive-secret',
			]
		);

		$settings = new Settings();

		ob_start();
		$settings->render_text_field(
			[
				'label_for' => 'client_secret',
				'key'       => 'client_secret',
				'type'      => 'password',
			]
		);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'type="password"', $html );
		$this->assertStringContainsString( 'value=""', $html );
		$this->assertStringNotContainsString( 'sensitive-secret', $html );
		$this->assertStringContainsString( 'autocomplete="new-password"', $html );
	}
}
