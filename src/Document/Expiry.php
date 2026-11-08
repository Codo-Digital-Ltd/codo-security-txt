<?php
/**
 * Expires field helpers.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Document;

defined( 'ABSPATH' ) || exit;

/**
 * Parses, formats and extends RFC 3339 expiry timestamps.
 */
final class Expiry {

	const FORMAT = 'Y-m-d\TH:i:s\Z';

	/**
	 * Parse an RFC 3339 date-time into a Unix timestamp.
	 *
	 * @param mixed $value Candidate value.
	 * @return int|null Timestamp, or null when it is not a valid date-time.
	 */
	public static function parse( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = trim( $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/i', $value, $m ) ) {
			return null;
		}
		if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return null;
		}
		try {
			$date = new \DateTimeImmutable( $value );
		} catch ( \Exception $e ) {
			return null;
		}
		return $date->getTimestamp();
	}

	/**
	 * The end (UTC) of a calendar date, as an Expires value.
	 *
	 * @param string $date Y-m-d.
	 * @return string|null
	 */
	public static function from_date( $date ) {
		if ( ! is_string( $date ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', trim( $date ), $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return null;
		}
		return trim( $date ) . 'T23:59:59Z';
	}

	/**
	 * Format a timestamp as an Expires value.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	public static function format( $timestamp ) {
		return gmdate( self::FORMAT, (int) $timestamp );
	}

	/**
	 * Whole days from now until a timestamp (negative once passed).
	 *
	 * @param int $timestamp Unix timestamp.
	 * @param int $now       Current Unix timestamp.
	 * @return int
	 */
	public static function days_left( $timestamp, $now ) {
		return (int) floor( ( (int) $timestamp - (int) $now ) / DAY_IN_SECONDS );
	}

	/**
	 * A new Expires value one year (365 days) after now, at the end of that day.
	 *
	 * @param int $now Current Unix timestamp.
	 * @return string
	 */
	public static function extended( $now ) {
		return gmdate( 'Y-m-d\T23:59:59\Z', (int) $now + 365 * DAY_IN_SECONDS );
	}
}
