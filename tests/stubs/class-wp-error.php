<?php
/**
 * Minimal WP_Error for unit tests.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

// phpcs:ignoreFile -- Test stub.

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {

		private $code;
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}
