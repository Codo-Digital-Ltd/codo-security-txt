<?php
/**
 * PGP clear-signed security.txt.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Document;

defined( 'ABSPATH' ) || exit;

/**
 * Parses a pasted clear-signed message. The signature itself is not verified
 * (that needs GnuPG); the structure and the required fields are.
 */
final class SignedMessage {

	const MAX_BYTES = 32768;

	/**
	 * Normalise line endings and surrounding whitespace.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function normalise( $text ) {
		return trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $text ) );
	}

	/**
	 * Parse a clear-signed message.
	 *
	 * @param string $text Pasted message.
	 * @return array{valid: bool, error: string, fields: array<string, string[]>, body: string}
	 */
	public static function parse( $text ) {
		$result = array(
			'valid'  => false,
			'error'  => '',
			'fields' => array(),
			'body'   => self::normalise( $text ),
		);

		if ( strlen( $result['body'] ) > self::MAX_BYTES ) {
			$result['error'] = __( 'The signed message is too large (32 KB maximum).', 'codo-security-txt' );
			return $result;
		}
		if ( preg_match( '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $result['body'] ) ) {
			$result['error'] = __( 'The signed message contains control characters.', 'codo-security-txt' );
			return $result;
		}
		if ( ! preg_match( '/\A-----BEGIN PGP SIGNED MESSAGE-----\n(?:[A-Za-z]+: [^\n]*\n)*\n(.*?)\n-----BEGIN PGP SIGNATURE-----\n.*\n-----END PGP SIGNATURE-----\z/s', $result['body'], $m ) ) {
			$result['error'] = __( 'That is not a PGP clear-signed message. It must start with -----BEGIN PGP SIGNED MESSAGE----- and end with the signature block.', 'codo-security-txt' );
			return $result;
		}

		foreach ( explode( "\n", $m[1] ) as $line ) {
			// Undo dash-escaping.
			if ( 0 === strpos( $line, '- ' ) ) {
				$line = substr( $line, 2 );
			}
			if ( preg_match( '/^([A-Za-z-]+):[ \t]*(\S.*)$/', $line, $f ) ) {
				$result['fields'][ strtolower( $f[1] ) ][] = trim( $f[2] );
			}
		}

		if ( empty( $result['fields']['contact'] ) ) {
			$result['error'] = __( 'The signed message has no Contact field.', 'codo-security-txt' );
			return $result;
		}
		if ( empty( $result['fields']['expires'] ) || null === Expiry::parse( $result['fields']['expires'][0] ) ) {
			$result['error'] = __( 'The signed message has no valid Expires field.', 'codo-security-txt' );
			return $result;
		}

		$result['valid'] = true;
		return $result;
	}
}
