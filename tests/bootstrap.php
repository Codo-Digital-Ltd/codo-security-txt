<?php
/**
 * PHPUnit bootstrap: unit tests run without WordPress, with core functions
 * mocked by Brain Monkey.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

define( 'ABSPATH', __DIR__ . '/fixtures/wordpress/' );
define( 'WPINC', 'wp-includes' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'CODO_SECURITY_TXT_VERSION', '1.0.0-test' );
define( 'CODO_SECURITY_TXT_FILE', dirname( __DIR__ ) . '/codo-security-txt.php' );
define( 'CODO_SECURITY_TXT_DIR', dirname( __DIR__ ) );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/stubs/class-wp-error.php';
require_once dirname( __DIR__ ) . '/src/Autoloader.php';

\CodoDigital\SecurityTxt\Autoloader::register( dirname( __DIR__ ) . '/src' );
