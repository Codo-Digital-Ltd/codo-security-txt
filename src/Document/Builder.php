<?php
/**
 * Builds the security.txt text.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Document;

defined( 'ABSPATH' ) || exit;

/**
 * Turns validated settings into the RFC 9116 document.
 */
class Builder {

	/**
	 * Build the file.
	 *
	 * @param array<string, mixed> $settings          Validated settings.
	 * @param string               $default_canonical Canonical URL to use when none is set ('' for none).
	 * @return string
	 */
	public function build( array $settings, $default_canonical ) {
		if ( '' !== $settings['signed'] ) {
			return SignedMessage::normalise( $settings['signed'] ) . "\n";
		}

		$lines = array( '# Security contact information (RFC 9116)' );

		foreach ( $settings['contacts'] as $contact ) {
			$lines[] = 'Contact: ' . self::clean( $contact );
		}
		$lines[] = 'Expires: ' . self::clean( $settings['expires'] );

		foreach ( $settings['encryption'] as $encryption ) {
			$lines[] = 'Encryption: ' . self::clean( $encryption );
		}
		if ( '' !== $settings['acknowledgments'] ) {
			$lines[] = 'Acknowledgments: ' . self::clean( $settings['acknowledgments'] );
		}
		if ( '' !== $settings['preferred_languages'] ) {
			$lines[] = 'Preferred-Languages: ' . self::clean( $settings['preferred_languages'] );
		}

		$canonical = $settings['canonical'];
		if ( empty( $canonical ) && $settings['auto_canonical'] && '' !== $default_canonical ) {
			$canonical = array( $default_canonical );
		}
		foreach ( $canonical as $url ) {
			$lines[] = 'Canonical: ' . self::clean( $url );
		}

		if ( '' !== $settings['policy'] ) {
			$lines[] = 'Policy: ' . self::clean( $settings['policy'] );
		}
		if ( '' !== $settings['hiring'] ) {
			$lines[] = 'Hiring: ' . self::clean( $settings['hiring'] );
		}
		if ( '' !== $settings['csaf'] ) {
			$lines[] = 'CSAF: ' . self::clean( $settings['csaf'] );
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Defence in depth: a value can never start a new line.
	 *
	 * @param string $value Field value.
	 * @return string
	 */
	private static function clean( $value ) {
		return trim( preg_replace( '/[\r\n]+/', ' ', (string) $value ) );
	}
}
