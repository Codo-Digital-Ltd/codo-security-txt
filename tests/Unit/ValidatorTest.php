<?php
/**
 * Tests for Validator.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use CodoDigital\SecurityTxt\Document\Validator;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Document\Validator
 */
final class ValidatorTest extends TestCase {

	/**
	 * Valid form input, with overrides.
	 *
	 * @param array<string, mixed> $overrides Values to change.
	 * @return array<string, mixed>
	 */
	private function input( array $overrides = array() ) {
		return array_merge(
			array(
				'enabled'  => '1',
				'contacts' => 'security@example.com',
				'expires'  => '2027-06-01',
			),
			$overrides
		);
	}

	private function run_validate( array $input ) {
		return ( new Validator() )->validate( $input, $this->now );
	}

	public function test_valid_input_is_normalised() {
		$result = $this->run_validate(
			$this->input(
				array(
					'legacy_path'         => '1',
					'contacts'            => "security@example.com\r\nmailto:sec@example.com?subject=Vuln\ntel:+44 20 7946 0958\nhttps://example.com/contact\nsecurity@example.com",
					'encryption'          => "https://example.com/key.asc\ndns:5d2d37ab76d47d36._openpgpkey.example.com?type=OPENPGPKEY\nopenpgp4fpr:5F2DE18D3AFE3B8F6FB5B1D4C1A3B4D5E6F7A8B9",
					'acknowledgments'     => 'https://example.com/thanks',
					'preferred_languages' => 'en, en-GB, en',
					'canonical'           => 'https://example.com/.well-known/security.txt',
					'policy'              => 'https://example.com/policy',
					'hiring'              => 'https://example.com/jobs',
					'csaf'                => 'https://example.com/provider-metadata.json',
				)
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( array(), $result['warnings'] );
		$settings = $result['settings'];
		$this->assertTrue( $settings['enabled'] );
		$this->assertTrue( $settings['legacy_path'] );
		$this->assertFalse( $settings['reminders'] );
		$this->assertSame(
			array( 'mailto:security@example.com', 'mailto:sec@example.com?subject=Vuln', 'tel:+442079460958', 'https://example.com/contact' ),
			array_values( array_unique( $settings['contacts'] ) )
		);
		$this->assertSame( '2027-06-01T23:59:59Z', $settings['expires'] );
		$this->assertCount( 3, $settings['encryption'] );
		$this->assertSame( 'en, en-GB', $settings['preferred_languages'] );
	}

	public function test_a_full_datetime_expiry_is_accepted() {
		$result = $this->run_validate( $this->input( array( 'expires' => '2027-06-01T10:00:00+01:00' ) ) );

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( '2027-06-01T09:00:00Z', $result['settings']['expires'] );
	}

	public function test_enabling_requires_a_contact_and_an_expiry() {
		$result = $this->run_validate( array( 'enabled' => '1' ) );

		$this->assertArrayHasKey( 'contacts', $result['errors'] );
		$this->assertArrayHasKey( 'expires', $result['errors'] );
	}

	public function test_a_disabled_file_may_be_incomplete() {
		$result = $this->run_validate( array( 'contacts' => 'security@example.com' ) );

		$this->assertSame( array(), $result['errors'] );
		$this->assertFalse( $result['settings']['enabled'] );
		$this->assertSame( '', $result['settings']['expires'] );
	}

	/**
	 * @dataProvider bad_contacts
	 */
	public function test_bad_contacts_are_rejected( $contact ) {
		$result = $this->run_validate( $this->input( array( 'contacts' => $contact ) ) );

		$this->assertArrayHasKey( 'contacts', $result['errors'] );
		$this->assertSame( array(), $result['settings']['contacts'] );
	}

	public function bad_contacts() {
		return array(
			'plain text'       => array( 'call me' ),
			'http url'         => array( 'http://example.com/contact' ),
			'bad mailto'       => array( 'mailto:not-an-email' ),
			'tel without plus' => array( 'tel:02079460958' ),
			'javascript'       => array( 'javascript:alert(1)' ),
			'credentials'      => array( 'https://user:pass@example.com/' ),
			'bad host'         => array( 'https://exa mple.com/' ),
			'underscore host'  => array( 'https://exa_mple.com/' ),
			'quote'            => array( 'https://example.com/"x' ),
			'too long'         => array( 'https://example.com/' . str_repeat( 'a', 2100 ) ),
		);
	}

	public function test_a_long_rejected_value_is_shortened_in_the_message() {
		$result = $this->run_validate( $this->input( array( 'contacts' => str_repeat( 'x', 200 ) ) ) );

		$this->assertStringContainsString( str_repeat( 'x', 80 ) . '...', $result['errors']['contacts'][0] );
		$this->assertStringNotContainsString( str_repeat( 'x', 81 ), $result['errors']['contacts'][0] );
	}

	public function test_too_many_lines_are_rejected() {
		$lines  = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$lines[] = "sec{$i}@example.com";
		}
		$result = $this->run_validate( $this->input( array( 'contacts' => implode( "\n", $lines ) ) ) );

		$this->assertArrayHasKey( 'contacts', $result['errors'] );
		$this->assertCount( 10, $result['settings']['contacts'] );
	}

	public function test_bad_expiry_values_are_rejected() {
		$this->assertArrayHasKey( 'expires', $this->run_validate( $this->input( array( 'expires' => 'tomorrow' ) ) )['errors'] );
		$this->assertArrayHasKey( 'expires', $this->run_validate( $this->input( array( 'expires' => '2027-02-30' ) ) )['errors'] );
		$this->assertArrayHasKey( 'expires', $this->run_validate( $this->input( array( 'expires' => '2026-11-07' ) ) )['errors'] );
	}

	public function test_an_expiry_more_than_a_year_ahead_is_a_warning_not_an_error() {
		$result = $this->run_validate( $this->input( array( 'expires' => '2029-01-01' ) ) );

		$this->assertSame( array(), $result['errors'] );
		$this->assertArrayHasKey( 'expires', $result['warnings'] );
		$this->assertSame( '2029-01-01T23:59:59Z', $result['settings']['expires'] );
	}

	public function test_a_past_expiry_is_allowed_while_the_file_is_disabled() {
		$result = $this->run_validate( array( 'expires' => '2020-01-01' ) );

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( '2020-01-01T23:59:59Z', $result['settings']['expires'] );
	}

	/**
	 * @dataProvider bad_encryption
	 */
	public function test_bad_encryption_values_are_rejected( $value ) {
		$result = $this->run_validate( $this->input( array( 'encryption' => $value ) ) );

		$this->assertArrayHasKey( 'encryption', $result['errors'] );
	}

	public function bad_encryption() {
		return array(
			'http'            => array( 'http://example.com/key.asc' ),
			'short finger'    => array( 'openpgp4fpr:ABCD' ),
			'bad dns'         => array( 'dns:bad host' ),
			'plain'           => array( 'my key' ),
		);
	}

	public function test_bad_urls_are_rejected_in_every_url_field() {
		foreach ( array( 'acknowledgments', 'policy', 'hiring', 'csaf' ) as $field ) {
			$result = $this->run_validate( $this->input( array( $field => 'http://example.com/' ) ) );
			$this->assertArrayHasKey( $field, $result['errors'] );
			$this->assertSame( '', $result['settings'][ $field ] );
		}

		$result = $this->run_validate( $this->input( array( 'canonical' => "https://example.com/ok\nhttp://example.com/bad" ) ) );
		$this->assertArrayHasKey( 'canonical', $result['errors'] );
		$this->assertSame( array( 'https://example.com/ok' ), $result['settings']['canonical'] );
	}

	public function test_bad_languages_are_rejected() {
		$bad = $this->run_validate( $this->input( array( 'preferred_languages' => 'en, English!' ) ) );
		$this->assertArrayHasKey( 'preferred_languages', $bad['errors'] );
		$this->assertSame( '', $bad['settings']['preferred_languages'] );

		$tags = array();
		for ( $i = 0; $i < 21; $i++ ) {
			$tags[] = 'x' . chr( 97 + ( $i % 26 ) ) . chr( 97 + intdiv( $i, 26 ) );
		}
		$many = $this->run_validate( $this->input( array( 'preferred_languages' => implode( ', ', $tags ) ) ) );
		$this->assertArrayHasKey( 'preferred_languages', $many['errors'] );
	}

	public function test_non_string_values_are_treated_as_empty() {
		$result = $this->run_validate(
			array(
				'contacts'            => array( 'a' ),
				'expires'             => array( '2027-01-01' ),
				'policy'              => array( 'x' ),
				'preferred_languages' => array( 'en' ),
				'signed'              => array( 'x' ),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( array(), $result['settings']['contacts'] );
		$this->assertSame( '', $result['settings']['signed'] );
	}

	public function test_a_valid_signed_message_replaces_contact_and_expiry() {
		$result = $this->run_validate(
			array(
				'enabled' => '1',
				'signed'  => $this->signed(),
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( $this->signed(), $result['settings']['signed'] );
	}

	public function test_an_invalid_signed_message_is_rejected() {
		$result = $this->run_validate( $this->input( array( 'signed' => 'Contact: mailto:a@example.com' ) ) );

		$this->assertArrayHasKey( 'signed', $result['errors'] );
		$this->assertSame( '', $result['settings']['signed'] );
	}

	public function test_an_expired_signed_message_is_rejected_when_enabled() {
		$enabled  = $this->run_validate(
			array(
				'enabled' => '1',
				'signed'  => $this->signed( '2026-01-01T00:00:00Z' ),
			)
		);
		$disabled = $this->run_validate( array( 'signed' => $this->signed( '2026-01-01T00:00:00Z' ) ) );

		$this->assertArrayHasKey( 'signed', $enabled['errors'] );
		$this->assertSame( array(), $disabled['errors'] );
	}

	public function test_labels() {
		$this->assertSame( 'Contact', Validator::label( 'contacts' ) );
		$this->assertSame( 'Signed message', Validator::label( 'signed' ) );
		$this->assertSame( 'other', Validator::label( 'other' ) );
	}
}
