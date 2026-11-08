<?php
/**
 * Admin form handlers.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Admin;

use CodoDigital\SecurityTxt\Cron\Reminder;
use CodoDigital\SecurityTxt\Document\Expiry;
use CodoDigital\SecurityTxt\Document\Validator;
use CodoDigital\SecurityTxt\Http\Endpoint;
use CodoDigital\SecurityTxt\Server\Reachability;
use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Every handler checks the capability and a nonce, then redirects back.
 */
class FormHandler {

	const SAVE   = 'codo_security_txt_save';
	const EXTEND = 'codo_security_txt_extend';
	const VERIFY = 'codo_security_txt_verify';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Validator.
	 *
	 * @var Validator
	 */
	private $validator;

	/**
	 * Status.
	 *
	 * @var Status
	 */
	private $status;

	/**
	 * Notices.
	 *
	 * @var Notices
	 */
	private $notices;

	/**
	 * Reminder.
	 *
	 * @var Reminder
	 */
	private $reminder;

	/**
	 * Reachability.
	 *
	 * @var Reachability
	 */
	private $reachability;

	/**
	 * Endpoint.
	 *
	 * @var Endpoint
	 */
	private $endpoint;

	/**
	 * Constructor.
	 *
	 * @param Settings     $settings     Settings.
	 * @param Validator    $validator    Validator.
	 * @param Status       $status       Status.
	 * @param Notices      $notices      Notices.
	 * @param Reminder     $reminder     Reminder.
	 * @param Reachability $reachability Reachability.
	 * @param Endpoint     $endpoint     Endpoint.
	 */
	public function __construct( Settings $settings, Validator $validator, Status $status, Notices $notices, Reminder $reminder, Reachability $reachability, Endpoint $endpoint ) {
		$this->settings     = $settings;
		$this->validator    = $validator;
		$this->status       = $status;
		$this->notices      = $notices;
		$this->reminder     = $reminder;
		$this->reachability = $reachability;
		$this->endpoint     = $endpoint;
	}

	/**
	 * Hook the form actions.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_' . self::SAVE, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::EXTEND, array( $this, 'handle_extend' ) );
		add_action( 'admin_post_' . self::VERIFY, array( $this, 'handle_verify' ) );
	}

	/**
	 * Save the settings.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! $this->authorised( self::SAVE ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked in authorised(); every value is validated and sanitised by Validator::validate().
		$input  = isset( $_POST['codo_security_txt'] ) && is_array( $_POST['codo_security_txt'] ) ? wp_unslash( $_POST['codo_security_txt'] ) : array();
		$result = $this->validator->validate( $input, time() );

		if ( ! empty( $result['errors'] ) ) {
			foreach ( $result['errors'] as $field => $messages ) {
				foreach ( $messages as $message ) {
					$this->notices->add( 'error', Validator::label( $field ) . ': ' . $message );
				}
			}
			$this->notices->store_draft( $input );
			$this->finish();
			return;
		}

		$this->settings->save( $result['settings'] );
		$this->after_change();
		$this->notices->add( 'success', __( 'Settings saved.', 'codo-security-txt' ) );
		foreach ( $result['warnings'] as $field => $messages ) {
			foreach ( $messages as $message ) {
				$this->notices->add( 'warning', Validator::label( $field ) . ': ' . $message );
			}
		}
		$this->finish();
	}

	/**
	 * Push the expiry date one year ahead.
	 *
	 * @return void
	 */
	public function handle_extend() {
		if ( ! $this->authorised( self::EXTEND ) ) {
			return;
		}

		$settings = $this->settings->all();
		if ( '' !== $settings['signed'] ) {
			$this->notices->add( 'error', __( 'The file is a PGP-signed message, so changing its date would break the signature. Sign a new message with a later Expires date and paste it below.', 'codo-security-txt' ) );
			$this->finish();
			return;
		}

		$settings['expires'] = Expiry::extended( time() );
		$this->settings->save( $settings );
		$this->after_change();
		$this->notices->add(
			'success',
			sprintf(
				/* translators: %s: new expiry date (YYYY-MM-DD). */
				__( 'Extended. The file now expires on %s (UTC).', 'codo-security-txt' ),
				substr( $settings['expires'], 0, 10 )
			)
		);
		$this->finish();
	}

	/**
	 * Fetch the file from the public URL and report what came back.
	 *
	 * @return void
	 */
	public function handle_verify() {
		if ( ! $this->authorised( self::VERIFY ) ) {
			return;
		}

		$state = $this->status->evaluate();
		if ( ! Status::is_published( $state['state'] ) ) {
			$this->notices->add( 'warning', __( 'Nothing to check: the file is not published yet.', 'codo-security-txt' ) );
			$this->finish();
			return;
		}

		$result = $this->reachability->run_and_store( $this->status->file_url(), $this->endpoint->body() );
		$types  = array(
			'ok'      => 'success',
			'warning' => 'warning',
			'error'   => 'error',
		);
		$this->notices->add( isset( $types[ $result['status'] ] ) ? $types[ $result['status'] ] : 'info', $result['message'] );
		$this->finish();
	}

	/**
	 * Capability and nonce check shared by every handler.
	 *
	 * @param string $action Nonce action.
	 * @return bool False when the request was refused.
	 */
	private function authorised( $action ) {
		if ( ! current_user_can( Capability::site() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage security.txt.', 'codo-security-txt' ), '', array( 'response' => 403 ) );
			return false;
		}
		check_admin_referer( $action );
		return true;
	}

	/**
	 * Housekeeping after the published text changed.
	 *
	 * @return void
	 */
	private function after_change() {
		$this->reminder->reset();
		$this->reachability->forget();

		/**
		 * Fires after the security.txt settings change. Purge any CDN or page
		 * cache that holds /.well-known/security.txt here.
		 */
		do_action( 'codo_security_txt_changed' );
	}

	/**
	 * Redirect back to the settings screen and stop.
	 *
	 * @codeCoverageIgnore Terminates the request.
	 *
	 * @return void
	 */
	protected function finish() {
		wp_safe_redirect( admin_url( 'options-general.php?page=' . AdminPage::SLUG ) );
		exit;
	}
}
