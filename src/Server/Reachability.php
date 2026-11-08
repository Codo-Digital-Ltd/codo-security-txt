<?php
/**
 * Checks that the web server really delivers the file.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Server;

defined( 'ABSPATH' ) || exit;

/**
 * Requests the site's own public URL, like Site Health's loopback test.
 */
class Reachability {

	const TRANSIENT = 'codo_security_txt_reachability';

	/**
	 * Request /.well-known/security.txt and compare it with the expected text.
	 *
	 * @param string $url      Absolute URL of the file.
	 * @param string $expected Expected body.
	 * @return array{status: string, message: string} status is ok, warning or error.
	 */
	public function check( $url, $expected ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 3,
				/** This filter is documented in wp-includes/class-wp-http-streams.php */
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %s: error message from the HTTP request. */
					__( 'The site could not fetch its own security.txt: %s. Some hosts block a site from requesting itself, so test it from outside too.', 'codo-security-txt' ),
					$response->get_error_message()
				),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array(
				'status'  => 'error',
				'message' => sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Fetching the file returned HTTP %d. The web server may not be passing /.well-known/ requests to WordPress. See the FAQ for NGINX and Apache rules.', 'codo-security-txt' ),
					$code
				),
			);
		}

		if ( trim( (string) wp_remote_retrieve_body( $response ) ) !== trim( $expected ) ) {
			return array(
				'status'  => 'warning',
				'message' => __( 'The file is being served, but its content differs from the settings. A cache, a CDN or a physical file may be serving an older copy.', 'codo-security-txt' ),
			);
		}

		if ( 0 !== stripos( (string) wp_remote_retrieve_header( $response, 'content-type' ), 'text/plain' ) ) {
			return array(
				'status'  => 'warning',
				'message' => __( 'The file is served with the wrong Content-Type. RFC 9116 requires text/plain.', 'codo-security-txt' ),
			);
		}

		return array(
			'status'  => 'ok',
			'message' => __( 'The web server delivers the file correctly.', 'codo-security-txt' ),
		);
	}

	/**
	 * The last stored result.
	 *
	 * @return array{status: string, message: string}|null
	 */
	public function cached() {
		$value = get_transient( self::TRANSIENT );
		return is_array( $value ) && isset( $value['status'], $value['message'] ) ? $value : null;
	}

	/**
	 * Check and remember the result for 12 hours.
	 *
	 * @param string $url      Absolute URL of the file.
	 * @param string $expected Expected body.
	 * @return array{status: string, message: string}
	 */
	public function run_and_store( $url, $expected ) {
		$result = $this->check( $url, $expected );
		set_transient( self::TRANSIENT, $result, 12 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Forget the stored result (after the settings change).
	 *
	 * @return void
	 */
	public function forget() {
		delete_transient( self::TRANSIENT );
	}
}
