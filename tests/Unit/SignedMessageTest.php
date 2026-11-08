<?php
/**
 * Tests for SignedMessage.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use CodoDigital\SecurityTxt\Document\SignedMessage;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Document\SignedMessage
 */
final class SignedMessageTest extends TestCase {

	public function test_normalise_converts_line_endings_and_trims() {
		$this->assertSame( "a\nb", SignedMessage::normalise( "  a\r\nb\r\n" ) );
		$this->assertSame( "a\nb", SignedMessage::normalise( "a\rb" ) );
	}

	public function test_a_valid_message_is_parsed_including_dash_escaped_lines() {
		$result = SignedMessage::parse( str_replace( "\n", "\r\n", $this->signed() ) );

		$this->assertTrue( $result['valid'] );
		$this->assertSame( '', $result['error'] );
		$this->assertSame( array( 'mailto:security@example.com' ), $result['fields']['contact'] );
		$this->assertSame( array( '2027-06-01T23:59:59Z' ), $result['fields']['expires'] );
		$this->assertStringContainsString( '-----BEGIN PGP SIGNATURE-----', $result['body'] );
		$this->assertStringNotContainsString( "\r", $result['body'] );
	}

	public function test_it_rejects_oversized_text() {
		$result = SignedMessage::parse( str_repeat( 'a', SignedMessage::MAX_BYTES + 1 ) );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'too large', $result['error'] );
	}

	public function test_it_rejects_control_characters() {
		$result = SignedMessage::parse( "-----BEGIN PGP SIGNED MESSAGE-----\n\n\x00Contact: x" );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'control characters', $result['error'] );
	}

	public function test_it_rejects_text_that_is_not_clearsigned() {
		$result = SignedMessage::parse( "Contact: mailto:security@example.com\nExpires: 2027-06-01T23:59:59Z" );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'not a PGP clear-signed message', $result['error'] );
	}

	public function test_it_requires_a_contact() {
		$text   = "-----BEGIN PGP SIGNED MESSAGE-----\n\nExpires: 2027-06-01T23:59:59Z\n-----BEGIN PGP SIGNATURE-----\n\nabc\n-----END PGP SIGNATURE-----";
		$result = SignedMessage::parse( $text );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'no Contact', $result['error'] );
	}

	public function test_it_requires_a_valid_expires() {
		$none = "-----BEGIN PGP SIGNED MESSAGE-----\n\nContact: mailto:a@example.com\n-----BEGIN PGP SIGNATURE-----\n\nabc\n-----END PGP SIGNATURE-----";
		$bad  = "-----BEGIN PGP SIGNED MESSAGE-----\n\nContact: mailto:a@example.com\nExpires: someday\n-----BEGIN PGP SIGNATURE-----\n\nabc\n-----END PGP SIGNATURE-----";

		$this->assertStringContainsString( 'no valid Expires', SignedMessage::parse( $none )['error'] );
		$this->assertStringContainsString( 'no valid Expires', SignedMessage::parse( $bad )['error'] );
	}
}
