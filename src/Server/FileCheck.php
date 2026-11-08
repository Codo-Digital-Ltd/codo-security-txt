<?php
/**
 * Detects physical security.txt files that the web server would serve instead.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Server;

defined( 'ABSPATH' ) || exit;

/**
 * A real file on disk wins over WordPress, so the plugin's settings would be ignored.
 */
class FileCheck {

	/**
	 * Directories to look in.
	 *
	 * @var string[]
	 */
	private $roots;

	/**
	 * Constructor.
	 *
	 * @param string[] $roots Candidate document roots.
	 */
	public function __construct( array $roots ) {
		$this->roots = array_values( array_unique( array_filter( array_map( 'strval', $roots ) ) ) );
	}

	/**
	 * URL paths that exist as real files.
	 *
	 * @return string[]
	 */
	public function conflicts() {
		$found = array();
		foreach ( $this->roots as $root ) {
			$root = rtrim( $root, '/\\' );
			foreach ( array( '/.well-known/security.txt', '/security.txt' ) as $path ) {
				if ( is_file( $root . $path ) && ! in_array( $path, $found, true ) ) {
					$found[] = $path;
				}
			}
		}
		return $found;
	}
}
