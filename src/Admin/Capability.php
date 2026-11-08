<?php
/**
 * Who may manage the file.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Capability check, filterable.
 */
final class Capability {

	/**
	 * Capability required to manage this site's security.txt.
	 *
	 * @return string
	 */
	public static function site() {
		/**
		 * Filters the capability required to manage security.txt.
		 *
		 * @param string $capability Capability.
		 */
		$capability = apply_filters( 'codo_security_txt_capability', 'manage_options' );
		return is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
	}
}
