<?php
/**
 * Plugin Name:       Codo Security.txt
 * Plugin URI:        https://github.com/Codo-Digital-Ltd/codo-security-txt
 * Description:       Publish a valid RFC 9116 security.txt file for your site, with strict validation, expiry reminders and a Site Health check. Free, no upsells.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Codo Digital
 * Author URI:        https://codo.digital
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       codo-security-txt
 *
 * @package CodoDigital\SecurityTxt
 */

defined( 'ABSPATH' ) || exit;

define( 'CODO_SECURITY_TXT_VERSION', '1.0.0' );
define( 'CODO_SECURITY_TXT_FILE', __FILE__ );
define( 'CODO_SECURITY_TXT_DIR', __DIR__ );

require_once __DIR__ . '/src/Autoloader.php';

\CodoDigital\SecurityTxt\Autoloader::register( __DIR__ . '/src' );

register_activation_hook( __FILE__, array( \CodoDigital\SecurityTxt\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \CodoDigital\SecurityTxt\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \CodoDigital\SecurityTxt\Plugin::class, 'boot' ), 1 );
