<?php
/**
 * Base test case.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Sets up Brain Monkey, common WordPress stubs and an in-memory option store.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * In-memory options.
	 *
	 * @var array<string, mixed>
	 */
	protected $options = array();

	/**
	 * In-memory transients.
	 *
	 * @var array<string, mixed>
	 */
	protected $transients = array();

	/**
	 * A fixed "now" for date maths: 2026-11-08 12:00:00 UTC.
	 *
	 * @var int
	 */
	protected $now = 1794139200;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->options    = array();
		$this->transients = array();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\stubs(
			array(
				'wp_parse_url'          => static function ( $url, $component = -1 ) {
					return parse_url( $url, $component );
				},
				'sanitize_text_field'   => static function ( $text ) {
					return trim( strip_tags( (string) $text ) );
				},
				'sanitize_textarea_field' => static function ( $text ) {
					return is_scalar( $text ) ? trim( strip_tags( (string) $text ) ) : '';
				},
				'wp_unslash'            => static function ( $value ) {
					return $value;
				},
				'home_url'              => static function ( $path = '' ) {
					return 'https://www.example.com' . ( '' === $path ? '' : '/' . ltrim( $path, '/' ) );
				},
				'admin_url'             => static function ( $path = '' ) {
					return 'https://www.example.com/wp-admin/' . ltrim( $path, '/' );
				},
				'is_email'              => static function ( $email ) {
					return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
				},
				'is_wp_error'           => static function ( $thing ) {
					return $thing instanceof \WP_Error;
				},
				'is_multisite'          => false,
				'is_subdomain_install'  => true,
				'is_main_site'          => true,
				'get_current_user_id'   => 1,
				'current_user_can'      => true,
				'plugin_basename'       => 'codo-security-txt/codo-security-txt.php',
				'check_admin_referer'   => true,
				'wp_specialchars_decode' => static function ( $text ) {
					return (string) $text;
				},
				'checked'               => static function ( $checked, $current = true, $display = true ) {
					$result = (string) $checked === (string) $current ? ' checked="checked"' : '';
					if ( $display ) {
						echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					return $result;
				},
				'wp_nonce_field'        => static function () {
					echo '<input type="hidden" name="_wpnonce" value="nonce">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				},
				'submit_button'         => static function ( $text = '' ) {
					echo '<button>' . $text . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				},
				'get_option'            => function ( $name, $default = false ) {
					return array_key_exists( $name, $this->options ) ? $this->options[ $name ] : $default;
				},
				'update_option'         => function ( $name, $value ) {
					$this->options[ $name ] = $value;
					return true;
				},
				'delete_option'         => function ( $name ) {
					unset( $this->options[ $name ] );
					return true;
				},
				'get_transient'         => function ( $name ) {
					return array_key_exists( $name, $this->transients ) ? $this->transients[ $name ] : false;
				},
				'set_transient'         => function ( $name, $value ) {
					$this->transients[ $name ] = $value;
					return true;
				},
				'delete_transient'      => function ( $name ) {
					unset( $this->transients[ $name ] );
					return true;
				},
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Settings as stored, with overrides.
	 *
	 * @param array<string, mixed> $overrides Values to change.
	 * @return array<string, mixed>
	 */
	protected function settings( array $overrides = array() ) {
		return array_merge(
			\CodoDigital\SecurityTxt\Settings\Settings::defaults(),
			array(
				'enabled'  => true,
				'contacts' => array( 'mailto:security@example.com' ),
				'expires'  => '2027-06-01T23:59:59Z',
			),
			$overrides
		);
	}

	/**
	 * Store settings in the in-memory option.
	 *
	 * @param array<string, mixed> $overrides Values to change.
	 * @return void
	 */
	protected function store( array $overrides = array() ) {
		$this->options[ \CodoDigital\SecurityTxt\Settings\Settings::OPTION ] = $this->settings( $overrides );
	}

	/**
	 * A valid PGP clear-signed security.txt.
	 *
	 * @param string $expires Expires value.
	 * @return string
	 */
	protected function signed( $expires = '2027-06-01T23:59:59Z' ) {
		return "-----BEGIN PGP SIGNED MESSAGE-----\nHash: SHA512\n\nContact: mailto:security@example.com\nExpires: {$expires}\n- -----not a marker\n-----BEGIN PGP SIGNATURE-----\n\niQEzBAEBCgAdFiEE\n=abcd\n-----END PGP SIGNATURE-----";
	}
}
