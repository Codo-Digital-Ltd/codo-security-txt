<?php
/**
 * Serves /.well-known/security.txt.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Http;

use CodoDigital\SecurityTxt\Document\Builder;
use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Answers requests for the file before WordPress routes them, so no rewrite
 * rules are needed and nothing has to be flushed.
 */
class Endpoint {

	const PATH   = '/.well-known/security.txt';
	const LEGACY = '/security.txt';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Status.
	 *
	 * @var Status
	 */
	private $status;

	/**
	 * Builder.
	 *
	 * @var Builder
	 */
	private $builder;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 * @param Status   $status   Status.
	 * @param Builder  $builder  Builder.
	 */
	public function __construct( Settings $settings, Status $status, Builder $builder ) {
		$this->settings = $settings;
		$this->status   = $status;
		$this->builder  = $builder;
	}

	/**
	 * Answer the current request when it is for the file.
	 *
	 * @return void
	 */
	public function maybe_handle() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path   = wp_parse_url( $uri, PHP_URL_PATH );

		$response = $this->resolve( $method, is_string( $path ) ? $path : '' );
		if ( null !== $response ) {
			$this->send( $response );
		}
	}

	/**
	 * Work out the response for a request, or null when it is not ours.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Request path (no query string).
	 * @return array{status: int, headers: array<string, string>, body: string, head: bool}|null
	 */
	public function resolve( $method, $path ) {
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return null;
		}

		$settings = $this->settings->all();
		if ( self::PATH !== $path && ! ( self::LEGACY === $path && $settings['legacy_path'] ) ) {
			return null;
		}

		// A subdirectory subsite shares its host with the main site, which owns the file.
		if ( is_multisite() && ! is_subdomain_install() && ! is_main_site() ) {
			return null;
		}

		$state = $this->status->evaluate();
		if ( ! Status::is_published( $state['state'] ) ) {
			return null;
		}

		$body = $this->body();

		$headers = array(
			'Content-Type'           => 'text/plain; charset=utf-8',
			'X-Content-Type-Options' => 'nosniff',
			'Cache-Control'          => 'public, max-age=3600',
		);

		/**
		 * Filters the response headers.
		 *
		 * @param array<string, string> $headers Header name => value.
		 */
		$headers = apply_filters( 'codo_security_txt_headers', $headers );

		return array(
			'status'  => 200,
			'headers' => is_array( $headers ) ? $headers : array(),
			'body'    => $body,
			'head'    => 'HEAD' === $method,
		);
	}

	/**
	 * The text of the file, exactly as it is served.
	 *
	 * @return string
	 */
	public function body() {
		/**
		 * Filters the security.txt text before it is sent.
		 *
		 * @param string $body Plain text.
		 */
		return (string) apply_filters( 'codo_security_txt_body', $this->builder->build( $this->settings->all(), $this->status->canonical_url() ) );
	}

	/**
	 * Send a response and stop.
	 *
	 * @codeCoverageIgnore Terminates the request.
	 *
	 * @param array{status: int, headers: array<string, string>, body: string, head: bool} $response Response.
	 * @return void
	 */
	protected function send( array $response ) {
		status_header( $response['status'] );
		foreach ( $response['headers'] as $name => $value ) {
			header( $name . ': ' . $value );
		}
		if ( ! $response['head'] ) {
			echo $response['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text, not HTML.
		}
		exit;
	}
}
