<?php
/**
 * Stored settings.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the single settings option.
 */
class Settings {

	const OPTION = 'codo_security_txt_settings';

	/**
	 * Default values. The type of each default decides how stored values are cast.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'             => false,
			'legacy_path'         => true,
			'reminders'           => true,
			'auto_canonical'      => true,
			'contacts'            => array(),
			'encryption'          => array(),
			'canonical'           => array(),
			'expires'             => '',
			'acknowledgments'     => '',
			'preferred_languages' => '',
			'policy'              => '',
			'hiring'              => '',
			'csaf'                => '',
			'signed'              => '',
		);
	}

	/**
	 * All settings, with defaults filled in and types enforced.
	 *
	 * @return array<string, mixed>
	 */
	public function all() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$out    = self::defaults();

		foreach ( $out as $key => $default ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				continue;
			}
			$value = $stored[ $key ];
			if ( is_bool( $default ) ) {
				$out[ $key ] = (bool) $value;
			} elseif ( is_array( $default ) ) {
				$out[ $key ] = is_array( $value ) ? array_values( array_map( 'strval', $value ) ) : $default;
			} else {
				$out[ $key ] = is_string( $value ) ? $value : $default;
			}
		}

		return $out;
	}

	/**
	 * Store already-validated settings.
	 *
	 * @param array<string, mixed> $clean Output of Validator::validate().
	 * @return void
	 */
	public function save( array $clean ) {
		update_option( self::OPTION, array_intersect_key( $clean, self::defaults() ) );
	}

	/**
	 * Whether wp-config.php switches the file off.
	 *
	 * @return bool
	 */
	public function disabled_by_constant() {
		return defined( 'CODO_SECURITY_TXT_DISABLE' ) && CODO_SECURITY_TXT_DISABLE;
	}
}
