<?php
/**
 * One-time admin notices and form drafts shown after a form post redirects.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Stores notices and a rejected form per user in short-lived transients.
 */
class Notices {

	const PREFIX       = 'codo_security_txt_notice_';
	const DRAFT_PREFIX = 'codo_security_txt_draft_';

	/**
	 * Notices transient key for the current user.
	 *
	 * @return string
	 */
	private function key() {
		return self::PREFIX . get_current_user_id();
	}

	/**
	 * Draft transient key for the current user.
	 *
	 * @return string
	 */
	private function draft_key() {
		return self::DRAFT_PREFIX . get_current_user_id();
	}

	/**
	 * Queue a notice.
	 *
	 * @param string $type    success, warning, error or info.
	 * @param string $message Plain text.
	 * @return void
	 */
	public function add( $type, $message ) {
		$notices   = get_transient( $this->key() );
		$notices   = is_array( $notices ) ? $notices : array();
		$notices[] = array(
			'type'    => in_array( $type, array( 'success', 'warning', 'error', 'info' ), true ) ? $type : 'info',
			'message' => (string) $message,
		);
		set_transient( $this->key(), $notices, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Print and clear queued notices.
	 *
	 * @return void
	 */
	public function render() {
		$notices = get_transient( $this->key() );
		if ( ! is_array( $notices ) || empty( $notices ) ) {
			return;
		}
		delete_transient( $this->key() );
		foreach ( $notices as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['message'] )
			);
		}
	}

	/**
	 * Keep a rejected form so the user does not lose what they typed.
	 *
	 * @param array<string, mixed> $input Submitted values.
	 * @return void
	 */
	public function store_draft( array $input ) {
		set_transient( $this->draft_key(), $input, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Fetch and clear the rejected form.
	 *
	 * @return array<string, mixed>|null
	 */
	public function take_draft() {
		$draft = get_transient( $this->draft_key() );
		if ( ! is_array( $draft ) ) {
			return null;
		}
		delete_transient( $this->draft_key() );
		return $draft;
	}
}
