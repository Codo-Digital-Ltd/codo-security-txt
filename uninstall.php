<?php
/**
 * Removes all Codo Security.txt data when the plugin is deleted.
 *
 * @package CodoDigital\SecurityTxt
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'CODO_SECURITY_TXT_FILE' ) ) {
	define( 'CODO_SECURITY_TXT_FILE', __DIR__ . '/codo-security-txt.php' );
}

require_once __DIR__ . '/src/Autoloader.php';
\CodoDigital\SecurityTxt\Autoloader::register( __DIR__ . '/src' );

/*
 * Multisite: every site has its own options and scheduled event.
 */
if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $codo_security_txt_site_id ) {
		switch_to_blog( (int) $codo_security_txt_site_id );
		\CodoDigital\SecurityTxt\Plugin::uninstall();
		restore_current_blog();
	}
} else {
	\CodoDigital\SecurityTxt\Plugin::uninstall();
}
