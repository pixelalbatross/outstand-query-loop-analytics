<?php

namespace Outstand\WP\QueryLoop\Analytics\Tests\Unit;

use Outstand\WP\QueryLoop\Analytics\Encryption;

/**
 * Tests for the Encryption class.
 *
 * @covers \Outstand\WP\QueryLoop\Analytics\Encryption
 */
class EncryptionTest extends \WP_UnitTestCase {

	/**
	 * Encrypt decrypt roundtrip.
	 */
	public function test_encrypt_decrypt_roundtrip(): void {
		$plaintext = 'the quick brown fox 🦊';
		$encrypted = Encryption::encrypt( $plaintext );
		$this->assertIsString( $encrypted );
		$this->assertNotSame( $plaintext, $encrypted );
		$this->assertSame( $plaintext, Encryption::decrypt( $encrypted ) );
	}

	/**
	 * Encrypt produces different ciphertext each call.
	 */
	public function test_encrypt_produces_different_ciphertext_each_call(): void {
		$plaintext = 'same input';
		$a         = Encryption::encrypt( $plaintext );
		$b         = Encryption::encrypt( $plaintext );
		$this->assertNotSame( $a, $b, 'Nonce should differ between encryptions.' );
		$this->assertSame( $plaintext, Encryption::decrypt( $a ) );
		$this->assertSame( $plaintext, Encryption::decrypt( $b ) );
	}

	/**
	 * Decrypt rejects malformed input.
	 */
	public function test_decrypt_rejects_malformed_input(): void {
		$this->assertFalse( Encryption::decrypt( '' ) );
		$this->assertFalse( Encryption::decrypt( 'not-base64-$$$' ) );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- benign test input.
		$this->assertFalse( Encryption::decrypt( base64_encode( 'too short' ) ) );
	}

	/**
	 * Decrypt fails on tampered ciphertext.
	 */
	public function test_decrypt_fails_on_tampered_ciphertext(): void {
		$encrypted = Encryption::encrypt( 'payload' );
		$tampered  = substr_replace( $encrypted, 'A', -5, 1 );
		$this->assertFalse( Encryption::decrypt( $tampered ) );
	}
}
