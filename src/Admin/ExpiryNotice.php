<?php
/**
 * Dashboard and Plugins screen warning.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Admin;

use CodoDigital\SecurityTxt\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Nags administrators only while the file is expiring or expired.
 */
class ExpiryNotice {

	/**
	 * Status.
	 *
	 * @var Status
	 */
	private $status;

	/**
	 * Constructor.
	 *
	 * @param Status $status Status.
	 */
	public function __construct( Status $status ) {
		$this->status = $status;
	}

	/**
	 * Hook the notice.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Print the notice.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capability::site() ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! is_object( $screen ) || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			return;
		}

		$state = $this->status->evaluate();
		if ( Status::EXPIRED === $state['state'] ) {
			$type    = 'error';
			$message = __( 'Your security.txt has expired. Security researchers will treat it as out of date.', 'codo-security-txt' );
		} elseif ( Status::EXPIRING === $state['state'] ) {
			$type    = 'warning';
			$message = sprintf(
				/* translators: %s: expiry date (YYYY-MM-DD). */
				__( 'Your security.txt expires on %s (UTC).', 'codo-security-txt' ),
				gmdate( 'Y-m-d', (int) $state['expires'] )
			);
		} else {
			return;
		}

		printf(
			'<div class="notice notice-%1$s"><p>%2$s <a href="%3$s">%4$s</a></p></div>',
			esc_attr( $type ),
			esc_html( $message ),
			esc_url( admin_url( 'options-general.php?page=' . AdminPage::SLUG ) ),
			esc_html__( 'Renew it', 'codo-security-txt' )
		);
	}
}
