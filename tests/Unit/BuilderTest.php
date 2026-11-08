<?php
/**
 * Tests for Builder.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use CodoDigital\SecurityTxt\Document\Builder;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Document\Builder
 */
final class BuilderTest extends TestCase {

	public function test_it_builds_every_field() {
		$text = ( new Builder() )->build(
			$this->settings(
				array(
					'contacts'            => array( 'mailto:a@example.com', 'https://example.com/contact' ),
					'encryption'          => array( 'https://example.com/key.asc' ),
					'acknowledgments'     => 'https://example.com/thanks',
					'preferred_languages' => 'en, fr',
					'canonical'           => array( 'https://example.com/.well-known/security.txt' ),
					'policy'              => 'https://example.com/policy',
					'hiring'              => 'https://example.com/jobs',
					'csaf'                => 'https://example.com/provider-metadata.json',
				)
			),
			'https://default.example/.well-known/security.txt'
		);

		$this->assertSame(
			"# Security contact information (RFC 9116)\n" .
			"Contact: mailto:a@example.com\n" .
			"Contact: https://example.com/contact\n" .
			"Expires: 2027-06-01T23:59:59Z\n" .
			"Encryption: https://example.com/key.asc\n" .
			"Acknowledgments: https://example.com/thanks\n" .
			"Preferred-Languages: en, fr\n" .
			"Canonical: https://example.com/.well-known/security.txt\n" .
			"Policy: https://example.com/policy\n" .
			"Hiring: https://example.com/jobs\n" .
			"CSAF: https://example.com/provider-metadata.json\n",
			$text
		);
	}

	public function test_it_adds_the_default_canonical_only_when_none_is_set() {
		$builder  = new Builder();
		$default  = 'https://www.example.com/.well-known/security.txt';
		$with     = $builder->build( $this->settings(), $default );
		$disabled = $builder->build( $this->settings( array( 'auto_canonical' => false ) ), $default );
		$none     = $builder->build( $this->settings(), '' );

		$this->assertStringContainsString( 'Canonical: ' . $default, $with );
		$this->assertStringNotContainsString( 'Canonical', $disabled );
		$this->assertStringNotContainsString( 'Canonical', $none );
	}

	public function test_a_signed_message_is_published_exactly() {
		$text = ( new Builder() )->build( $this->settings( array( 'signed' => $this->signed() ) ), 'https://www.example.com/.well-known/security.txt' );

		$this->assertSame( $this->signed() . "\n", $text );
	}

	public function test_a_value_can_never_start_a_new_line() {
		$text = ( new Builder() )->build( $this->settings( array( 'policy' => "https://example.com/p\nContact: mailto:evil@example.com" ) ), '' );

		$this->assertStringContainsString( "Policy: https://example.com/p Contact: mailto:evil@example.com\n", $text );
		$this->assertSame( 1, substr_count( $text, "\nContact:" ) );
	}
}
