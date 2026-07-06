<?php

namespace Outstand\WP\QueryLoop\Analytics\Tests\Unit;

use Outstand\WP\QueryLoop\Analytics\Logger;
use ReflectionClass;

/**
 * Tests for the Logger class.
 *
 * @covers \Outstand\WP\QueryLoop\Analytics\Logger
 */
class LoggerTest extends \WP_UnitTestCase {

	/**
	 * Invoke the private redact_message() method via reflection.
	 *
	 * @param string $message Message to redact.
	 * @return string Redacted message.
	 */
	private function redact( string $message ): string {
		$ref    = new ReflectionClass( Logger::class );
		$method = $ref->getMethod( 'redact_message' );
		$method->setAccessible( true );
		return (string) $method->invoke( null, $message );
	}

	/**
	 * Invoke the private redact_context() method via reflection.
	 *
	 * @param array<mixed> $context Context to redact.
	 * @return array<mixed> Redacted context.
	 */
	private function redact_context( array $context ): array {
		$ref    = new ReflectionClass( Logger::class );
		$method = $ref->getMethod( 'redact_context' );
		$method->setAccessible( true );
		return (array) $method->invoke( null, $context );
	}

	/**
	 * Redact strips access token.
	 */
	public function test_redact_strips_access_token(): void {
		$out = $this->redact( 'response: access_token=ya29.abcDEF123_-.xyz foo' );
		$this->assertStringNotContainsString( 'ya29.abcDEF123_-.xyz', $out );
		$this->assertStringContainsString( '[REDACTED]', $out );
	}

	/**
	 * Redact strips refresh token.
	 */
	public function test_redact_strips_refresh_token(): void {
		$out = $this->redact( '"refresh_token":"1//0abcDEFG-xyz_123" trailing' );
		$this->assertStringNotContainsString( '1//0abcDEFG-xyz_123', $out );
	}

	/**
	 * Redact strips bearer header.
	 */
	public function test_redact_strips_bearer_header(): void {
		$out = $this->redact( 'Authorization: Bearer ya29.tokenvalue-here' );
		$this->assertStringNotContainsString( 'ya29.tokenvalue-here', $out );
	}

	/**
	 * Redact strips client secret.
	 */
	public function test_redact_strips_client_secret(): void {
		$out = $this->redact( 'client_secret=GOCSPX-verySensitive trailing' );
		$this->assertStringNotContainsString( 'GOCSPX-verySensitive', $out );
	}

	/**
	 * Redact passes through benign message.
	 */
	public function test_redact_passes_through_benign_message(): void {
		$this->assertSame( 'plain message with no secrets', $this->redact( 'plain message with no secrets' ) );
	}

	/**
	 * Context redaction masks sensitive keys.
	 */
	public function test_redact_context_masks_sensitive_keys(): void {
		$out = $this->redact_context(
			[
				'user'          => 'alice',
				'access_token'  => 'ya29.secret',
				'client_secret' => 'GOCSPX-secret',
			]
		);

		$this->assertSame( 'alice', $out['user'] );
		$this->assertSame( '[REDACTED]', $out['access_token'] );
		$this->assertSame( '[REDACTED]', $out['client_secret'] );
	}

	/**
	 * Context redaction recurses into nested arrays.
	 */
	public function test_redact_context_recurses(): void {
		$out = $this->redact_context(
			[
				'response' => [
					'status' => 200,
					'token'  => 'abc123',
				],
			]
		);

		$this->assertSame( 200, $out['response']['status'] );
		$this->assertSame( '[REDACTED]', $out['response']['token'] );
	}

	/**
	 * Logging is disabled unless the debug constant is truthy.
	 */
	public function test_is_enabled_requires_debug_constant(): void {
		// Constant is not defined in the test bootstrap.
		$this->assertFalse( Logger::is_enabled() );
	}
}
