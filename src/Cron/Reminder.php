<?php
/**
 * Emails a reminder before the file expires.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Cron;

use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Daily check. Sends at most one email per stage: 30 days, 7 days and expired.
 */
class Reminder {

	const HOOK   = 'codo_security_txt_daily';
	const OPTION = 'codo_security_txt_reminded';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Status.
	 *
	 * @var Status
	 */
	private $status;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 * @param Status   $status   Status.
	 */
	public function __construct( Settings $settings, Status $status ) {
		$this->settings = $settings;
		$this->status   = $status;
	}

	/**
	 * Hook the daily task.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/**
	 * Forget which stages were already emailed (after the expiry changes).
	 *
	 * @return void
	 */
	public function reset() {
		delete_option( self::OPTION );
	}

	/**
	 * Send the reminder for the current stage if it has not been sent.
	 *
	 * @param int|null $now Current Unix timestamp (default: now).
	 * @return bool Whether an email was sent.
	 */
	public function run( $now = null ) {
		$now      = null === $now ? time() : (int) $now;
		$settings = $this->settings->all();
		$state    = $this->status->evaluate( $now );
		$stage    = $this->stage( $state );

		if ( 0 === $stage ) {
			$this->reset();
			return false;
		}
		if ( ! $settings['reminders'] || $stage <= (int) get_option( self::OPTION, 0 ) ) {
			return false;
		}

		/**
		 * Filters who receives expiry reminders.
		 *
		 * @param string $recipient Email address (default: the site admin email).
		 */
		$recipient = apply_filters( 'codo_security_txt_reminder_recipient', get_option( 'admin_email' ) );
		if ( ! is_string( $recipient ) || ! is_email( $recipient ) ) {
			return false;
		}

		$sent = wp_mail( $recipient, $this->subject( $stage ), $this->message( $state ) );
		if ( $sent ) {
			update_option( self::OPTION, $stage, false );
		}
		return (bool) $sent;
	}

	/**
	 * Reminder stage: 0 none, 1 within 30 days, 2 within 7 days, 3 expired.
	 *
	 * @param array{state: string, days_left: int|null} $state Status.
	 * @return int
	 */
	private function stage( array $state ) {
		if ( Status::EXPIRED === $state['state'] ) {
			return 3;
		}
		if ( Status::EXPIRING !== $state['state'] ) {
			return 0;
		}
		return $state['days_left'] < Status::URGENT_DAYS ? 2 : 1;
	}

	/**
	 * Email subject.
	 *
	 * @param int $stage Stage.
	 * @return string
	 */
	private function subject( $stage ) {
		$site = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		if ( 3 === $stage ) {
			/* translators: %s: site name. */
			return sprintf( __( '[%s] Your security.txt has expired', 'codo-security-txt' ), $site );
		}
		/* translators: %s: site name. */
		return sprintf( __( '[%s] Your security.txt expires soon', 'codo-security-txt' ), $site );
	}

	/**
	 * Email body.
	 *
	 * @param array{expires: int|null, signed: bool} $state Status.
	 * @return string
	 */
	private function message( array $state ) {
		$lines = array(
			sprintf(
				/* translators: %s: expiry date (YYYY-MM-DD). */
				__( 'The security.txt file for this site expires on %s (UTC).', 'codo-security-txt' ),
				gmdate( 'Y-m-d', (int) $state['expires'] )
			),
			__( 'Security researchers treat an expired file as out of date, so they may not trust the contact details in it.', 'codo-security-txt' ),
			'',
			$state['signed']
				? __( 'The file is a PGP-signed message. Sign a new one with a later Expires date and paste it into the settings.', 'codo-security-txt' )
				: __( 'Open the settings and use "Extend by one year" to renew it.', 'codo-security-txt' ),
			admin_url( 'options-general.php?page=codo-security-txt' ),
		);
		return implode( "\n", $lines ) . "\n";
	}
}
