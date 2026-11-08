<?php
/**
 * Validates and sanitises submitted settings against RFC 9116.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Document;

defined( 'ABSPATH' ) || exit;

/**
 * Strict input handling. Nothing is stored unless it passes.
 */
class Validator {

	const MAX_ITEMS     = 10;
	const MAX_URL       = 2048;
	const MAX_LANGUAGES = 20;

	/**
	 * Validate submitted values.
	 *
	 * @param array<string, mixed> $input Raw (unslashed) form values.
	 * @param int                  $now   Current Unix timestamp.
	 * @return array{settings: array<string, mixed>, errors: array<string, string[]>, warnings: array<string, string[]>}
	 */
	public function validate( array $input, $now ) {
		$errors   = array();
		$warnings = array();
		$enabled  = ! empty( $input['enabled'] );

		$clean = array(
			'enabled'        => $enabled,
			'legacy_path'    => ! empty( $input['legacy_path'] ),
			'reminders'      => ! empty( $input['reminders'] ),
			'auto_canonical' => ! empty( $input['auto_canonical'] ),
		);

		$signed          = $this->signed( $input, $errors );
		$clean['signed'] = $signed['text'];

		$clean['contacts'] = $this->contacts( $this->lines( $input, 'contacts', $errors ), $errors );
		if ( $enabled && empty( $clean['contacts'] ) && ! $signed['valid'] && empty( $errors['contacts'] ) ) {
			$errors['contacts'][] = __( 'Add at least one contact.', 'codo-security-txt' );
		}

		$clean['expires'] = $this->expires( $input, $enabled && ! $signed['valid'], $now, $errors, $warnings );
		if ( $signed['valid'] ) {
			$signed_ts = Expiry::parse( $signed['fields']['expires'][0] );
			if ( $enabled && $signed_ts <= $now ) {
				$errors['signed'][] = __( 'The signed message has already expired. Sign a fresh one with a new Expires date.', 'codo-security-txt' );
			}
		}

		$clean['encryption'] = $this->encryption( $this->lines( $input, 'encryption', $errors ), $errors );
		$clean['canonical']  = $this->url_list( 'canonical', $this->lines( $input, 'canonical', $errors ), $errors );

		foreach ( array( 'acknowledgments', 'policy', 'hiring', 'csaf' ) as $field ) {
			$clean[ $field ] = $this->single_url( $field, $input, $errors );
		}

		$clean['preferred_languages'] = $this->languages( $input, $errors );

		return array(
			'settings' => $clean,
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Field labels for messages.
	 *
	 * @param string $field Field key.
	 * @return string
	 */
	public static function label( $field ) {
		$labels = array(
			'contacts'            => __( 'Contact', 'codo-security-txt' ),
			'expires'             => __( 'Expires', 'codo-security-txt' ),
			'encryption'          => __( 'Encryption', 'codo-security-txt' ),
			'acknowledgments'     => __( 'Acknowledgments', 'codo-security-txt' ),
			'preferred_languages' => __( 'Preferred languages', 'codo-security-txt' ),
			'canonical'           => __( 'Canonical', 'codo-security-txt' ),
			'policy'              => __( 'Policy', 'codo-security-txt' ),
			'hiring'              => __( 'Hiring', 'codo-security-txt' ),
			'csaf'                => __( 'CSAF', 'codo-security-txt' ),
			'signed'              => __( 'Signed message', 'codo-security-txt' ),
		);
		return isset( $labels[ $field ] ) ? $labels[ $field ] : $field;
	}

	/**
	 * Split a textarea into trimmed, unique, non-empty lines.
	 *
	 * @param array<string, mixed> $input  Form values.
	 * @param string               $key    Field key.
	 * @param array                $errors Errors (by reference).
	 * @return string[]
	 */
	private function lines( array $input, $key, array &$errors ) {
		$value = isset( $input[ $key ] ) && is_string( $input[ $key ] ) ? $input[ $key ] : '';
		$lines = array();
		foreach ( preg_split( '/[\r\n]+/', $value ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line && ! in_array( $line, $lines, true ) ) {
				$lines[] = $line;
			}
		}
		if ( count( $lines ) > self::MAX_ITEMS ) {
			$errors[ $key ][] = sprintf(
				/* translators: %d: maximum number of lines. */
				__( 'Use at most %d lines.', 'codo-security-txt' ),
				self::MAX_ITEMS
			);
			$lines = array_slice( $lines, 0, self::MAX_ITEMS );
		}
		return $lines;
	}

	/**
	 * Validate the signed message.
	 *
	 * @param array<string, mixed> $input  Form values.
	 * @param array                $errors Errors (by reference).
	 * @return array{text: string, valid: bool, fields: array<string, string[]>}
	 */
	private function signed( array $input, array &$errors ) {
		$out  = array(
			'text'   => '',
			'valid'  => false,
			'fields' => array(),
		);
		$text = isset( $input['signed'] ) && is_string( $input['signed'] ) ? SignedMessage::normalise( $input['signed'] ) : '';
		if ( '' === $text ) {
			return $out;
		}
		$parsed = SignedMessage::parse( $text );
		if ( ! $parsed['valid'] ) {
			$errors['signed'][] = $parsed['error'];
			return $out;
		}
		return array(
			'text'   => $parsed['body'],
			'valid'  => true,
			'fields' => $parsed['fields'],
		);
	}

	/**
	 * Validate contact lines.
	 *
	 * @param string[] $lines  Lines.
	 * @param array    $errors Errors (by reference).
	 * @return string[]
	 */
	private function contacts( array $lines, array &$errors ) {
		$out = array();
		foreach ( $lines as $line ) {
			$value = $this->contact( $line );
			if ( null === $value ) {
				$errors['contacts'][] = sprintf(
					/* translators: %s: the rejected value. */
					__( '"%s" is not a valid contact. Use an email address, a mailto: or tel: link, or an https:// URL.', 'codo-security-txt' ),
					$this->excerpt( $line )
				);
				continue;
			}
			$out[] = $value;
		}
		return $out;
	}

	/**
	 * Normalise one contact.
	 *
	 * @param string $line Value.
	 * @return string|null
	 */
	private function contact( $line ) {
		if ( preg_match( '/[\x00-\x20\x7f]/', $line ) && 0 !== stripos( $line, 'tel:' ) ) {
			return null;
		}
		if ( is_email( $line ) ) {
			return 'mailto:' . $line;
		}
		if ( 0 === stripos( $line, 'mailto:' ) ) {
			$rest    = substr( $line, 7 );
			$address = strstr( $rest, '?', true );
			$address = false === $address ? $rest : $address;
			return is_email( $address ) ? 'mailto:' . $rest : null;
		}
		if ( 0 === stripos( $line, 'tel:' ) ) {
			$number = preg_replace( '/[ ]+/', '', $line );
			return preg_match( '/^tel:\+[0-9][0-9.\-()]{4,30}$/i', $number ) ? $number : null;
		}
		return $this->https_url( $line );
	}

	/**
	 * Validate encryption values (https URL, dns: URI or openpgp4fpr: fingerprint).
	 *
	 * @param string[] $lines  Lines.
	 * @param array    $errors Errors (by reference).
	 * @return string[]
	 */
	private function encryption( array $lines, array &$errors ) {
		$out = array();
		foreach ( $lines as $line ) {
			if ( preg_match( '/^dns:[A-Za-z0-9._\-]+(?:\?type=[A-Za-z0-9]+)?$/i', $line ) || preg_match( '/^openpgp4fpr:[A-Fa-f0-9]{40,64}$/', $line ) || null !== $this->https_url( $line ) ) {
				$out[] = $line;
				continue;
			}
			$errors['encryption'][] = sprintf(
				/* translators: %s: the rejected value. */
				__( '"%s" is not valid. Use an https:// URL to your public key, a dns: URI or an openpgp4fpr: fingerprint.', 'codo-security-txt' ),
				$this->excerpt( $line )
			);
		}
		return $out;
	}

	/**
	 * Validate a list of https URLs.
	 *
	 * @param string   $field  Field key.
	 * @param string[] $lines  Lines.
	 * @param array    $errors Errors (by reference).
	 * @return string[]
	 */
	private function url_list( $field, array $lines, array &$errors ) {
		$out = array();
		foreach ( $lines as $line ) {
			if ( null === $this->https_url( $line ) ) {
				$errors[ $field ][] = sprintf(
					/* translators: %s: the rejected value. */
					__( '"%s" is not a valid https:// URL.', 'codo-security-txt' ),
					$this->excerpt( $line )
				);
				continue;
			}
			$out[] = $line;
		}
		return $out;
	}

	/**
	 * Validate an optional single https URL.
	 *
	 * @param string               $field  Field key.
	 * @param array<string, mixed> $input  Form values.
	 * @param array                $errors Errors (by reference).
	 * @return string
	 */
	private function single_url( $field, array $input, array &$errors ) {
		$value = isset( $input[ $field ] ) && is_string( $input[ $field ] ) ? trim( $input[ $field ] ) : '';
		if ( '' === $value ) {
			return '';
		}
		if ( null === $this->https_url( $value ) ) {
			$errors[ $field ][] = sprintf(
				/* translators: %s: the rejected value. */
				__( '"%s" is not a valid https:// URL.', 'codo-security-txt' ),
				$this->excerpt( $value )
			);
			return '';
		}
		return $value;
	}

	/**
	 * Validate Preferred-Languages.
	 *
	 * @param array<string, mixed> $input  Form values.
	 * @param array                $errors Errors (by reference).
	 * @return string
	 */
	private function languages( array $input, array &$errors ) {
		$value = isset( $input['preferred_languages'] ) && is_string( $input['preferred_languages'] ) ? trim( $input['preferred_languages'] ) : '';
		if ( '' === $value ) {
			return '';
		}
		$tags = array();
		foreach ( explode( ',', $value ) as $tag ) {
			$tag = trim( $tag );
			if ( ! preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $tag ) ) {
				$errors['preferred_languages'][] = sprintf(
					/* translators: %s: the rejected value. */
					__( '"%s" is not a valid language tag. Use tags such as en, en-GB or fr.', 'codo-security-txt' ),
					$this->excerpt( $tag )
				);
				return '';
			}
			if ( ! in_array( $tag, $tags, true ) ) {
				$tags[] = $tag;
			}
		}
		if ( count( $tags ) > self::MAX_LANGUAGES ) {
			$errors['preferred_languages'][] = __( 'List 20 languages at most.', 'codo-security-txt' );
			return '';
		}
		return implode( ', ', $tags );
	}

	/**
	 * Validate the Expires value (a Y-m-d date or a full RFC 3339 date-time).
	 *
	 * @param array<string, mixed> $input    Form values.
	 * @param bool                 $required Whether a value is required.
	 * @param int                  $now      Current Unix timestamp.
	 * @param array                $errors   Errors (by reference).
	 * @param array                $warnings Warnings (by reference).
	 * @return string
	 */
	private function expires( array $input, $required, $now, array &$errors, array &$warnings ) {
		$value = isset( $input['expires'] ) && is_string( $input['expires'] ) ? trim( $input['expires'] ) : '';
		if ( '' === $value ) {
			if ( $required ) {
				$errors['expires'][] = __( 'Set an expiry date. RFC 9116 requires one.', 'codo-security-txt' );
			}
			return '';
		}

		$normalised = Expiry::from_date( $value );
		$timestamp  = null === $normalised ? Expiry::parse( $value ) : Expiry::parse( $normalised );
		if ( null === $timestamp ) {
			$errors['expires'][] = __( 'That is not a valid date. Use a date such as 2027-11-08.', 'codo-security-txt' );
			return '';
		}

		$value = Expiry::format( $timestamp );
		if ( $required && $timestamp <= $now ) {
			$errors['expires'][] = __( 'The expiry date must be in the future.', 'codo-security-txt' );
		} elseif ( $timestamp - $now > 366 * DAY_IN_SECONDS ) {
			$warnings['expires'][] = __( 'RFC 9116 recommends an expiry less than a year ahead, so the file is reviewed regularly.', 'codo-security-txt' );
		}
		return $value;
	}

	/**
	 * Accept only https URLs without credentials, whitespace or markup characters.
	 *
	 * @param string $value Candidate.
	 * @return string|null The URL, or null when invalid.
	 */
	private function https_url( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || strlen( $value ) > self::MAX_URL || preg_match( '/[\x00-\x20\x7f"<>\\\\^`{|}]/', $value ) ) {
			return null;
		}
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || 'https' !== strtolower( $parts['scheme'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return null;
		}
		if ( ! preg_match( '/^(?:[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?\.)*[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?$|^\[[0-9A-Fa-f:.]+\]$/', $parts['host'] ) ) {
			return null;
		}
		return $value;
	}

	/**
	 * Shorten a rejected value for a message.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function excerpt( $value ) {
		$value = preg_replace( '/[\x00-\x1f\x7f]/', '', (string) $value );
		return strlen( $value ) > 80 ? substr( $value, 0, 80 ) . '...' : $value;
	}
}
