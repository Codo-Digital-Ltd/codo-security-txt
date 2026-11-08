<?php
/**
 * Overall state of the published file.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt;

use CodoDigital\SecurityTxt\Document\Expiry;
use CodoDigital\SecurityTxt\Document\SignedMessage;
use CodoDigital\SecurityTxt\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Works out whether the file is published, valid and fresh.
 */
class Status {

	const DISABLED = 'disabled';
	const INVALID  = 'invalid';
	const EXPIRED  = 'expired';
	const EXPIRING = 'expiring';
	const OK       = 'ok';

	const WARN_DAYS   = 30;
	const URGENT_DAYS = 7;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Evaluate the stored settings.
	 *
	 * @param int|null $now Current Unix timestamp (default: now).
	 * @return array{state: string, expires: int|null, days_left: int|null, signed: bool}
	 */
	public function evaluate( $now = null ) {
		$now      = null === $now ? time() : (int) $now;
		$settings = $this->settings->all();
		$result   = array(
			'state'     => self::DISABLED,
			'expires'   => null,
			'days_left' => null,
			'signed'    => '' !== $settings['signed'],
		);

		if ( $this->settings->disabled_by_constant() || ! $settings['enabled'] ) {
			return $result;
		}

		$result['state'] = self::INVALID;
		if ( $result['signed'] ) {
			$parsed = SignedMessage::parse( $settings['signed'] );
			if ( ! $parsed['valid'] ) {
				return $result;
			}
			$timestamp = Expiry::parse( $parsed['fields']['expires'][0] );
		} else {
			if ( empty( $settings['contacts'] ) ) {
				return $result;
			}
			$timestamp = Expiry::parse( $settings['expires'] );
		}
		if ( null === $timestamp ) {
			return $result;
		}

		$result['expires']   = $timestamp;
		$result['days_left'] = Expiry::days_left( $timestamp, $now );

		if ( $timestamp <= $now ) {
			$result['state'] = self::EXPIRED;
		} elseif ( $result['days_left'] < self::WARN_DAYS ) {
			$result['state'] = self::EXPIRING;
		} else {
			$result['state'] = self::OK;
		}
		return $result;
	}

	/**
	 * Whether a state is published at /.well-known/security.txt.
	 *
	 * An expired file is still served: researchers can still find the contact
	 * address, and consumers are required to treat the expired file as stale.
	 *
	 * @param string $state State from evaluate().
	 * @return bool
	 */
	public static function is_published( $state ) {
		return in_array( $state, array( self::OK, self::EXPIRING, self::EXPIRED ), true );
	}

	/**
	 * Where this site's file lives: the site's host root, whatever path WordPress is installed in.
	 *
	 * @return string
	 */
	public function file_url() {
		$parts = wp_parse_url( home_url() );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return '';
		}
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return strtolower( $parts['scheme'] ) . '://' . $parts['host'] . $port . '/.well-known/security.txt';
	}

	/**
	 * The canonical URL for the file, or '' when the site is not on https
	 * (RFC 9116 only allows https canonical URIs).
	 *
	 * @return string
	 */
	public function canonical_url() {
		$url = $this->file_url();
		return 0 === strpos( $url, 'https://' ) ? $url : '';
	}
}
