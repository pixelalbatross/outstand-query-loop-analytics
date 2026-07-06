<?php

namespace Outstand\WP\QueryLoop\Analytics\Tests\Unit;

use Outstand\WP\QueryLoop\Analytics\Auth;

/**
 * Tests for the Auth class.
 *
 * @covers \Outstand\WP\QueryLoop\Analytics\Auth
 */
class AuthTest extends \WP_UnitTestCase {

	/**
	 * Default redirect uri points at admin post.
	 */
	public function test_default_redirect_uri_points_at_admin_post(): void {
		$uri = Auth::get_redirect_uri();
		$this->assertStringContainsString( 'admin-post.php', $uri );
		$this->assertStringContainsString( 'action=' . Auth::CALLBACK_ACTION, $uri );
	}

	/**
	 * Redirect uri is filterable.
	 */
	public function test_redirect_uri_is_filterable(): void {
		add_filter(
			'outstand_query_loop_analytics_redirect_uri',
			static fn(): string => 'https://example.test/custom',
		);

		try {
			$this->assertSame( 'https://example.test/custom', Auth::get_redirect_uri() );
		} finally {
			remove_all_filters( 'outstand_query_loop_analytics_redirect_uri' );
		}
	}
}
