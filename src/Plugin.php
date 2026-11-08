<?php
/**
 * Plugin bootstrap.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt;

use CodoDigital\SecurityTxt\Admin\AdminPage;
use CodoDigital\SecurityTxt\Admin\ExpiryNotice;
use CodoDigital\SecurityTxt\Admin\FormHandler;
use CodoDigital\SecurityTxt\Admin\Notices;
use CodoDigital\SecurityTxt\Cron\Reminder;
use CodoDigital\SecurityTxt\Document\Builder;
use CodoDigital\SecurityTxt\Document\Validator;
use CodoDigital\SecurityTxt\Health\SiteHealth;
use CodoDigital\SecurityTxt\Http\Endpoint;
use CodoDigital\SecurityTxt\Server\FileCheck;
use CodoDigital\SecurityTxt\Server\Reachability;
use CodoDigital\SecurityTxt\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Wires services together and registers hooks.
 */
final class Plugin {

	/**
	 * Booted instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Services.
	 *
	 * @var array<string, object>
	 */
	private $services;

	/**
	 * Constructor.
	 *
	 * @param array<string, object> $services Services keyed by name.
	 */
	public function __construct( array $services ) {
		$this->services = $services;
	}

	/**
	 * A service.
	 *
	 * @param string $name Name.
	 * @return object
	 */
	public function get( $name ) {
		return $this->services[ $name ];
	}

	/**
	 * Build the default object graph.
	 *
	 * @return self
	 */
	public static function create() {
		$settings     = new Settings();
		$status       = new Status( $settings );
		$endpoint     = new Endpoint( $settings, $status, new Builder() );
		$notices      = new Notices();
		$file_check   = new FileCheck( self::document_roots() );
		$reachability = new Reachability();
		$reminder     = new Reminder( $settings, $status );

		return new self(
			array(
				'settings'      => $settings,
				'status'        => $status,
				'endpoint'      => $endpoint,
				'reminder'      => $reminder,
				'health'        => new SiteHealth( $status, $endpoint, $file_check, $reachability ),
				'handler'       => new FormHandler( $settings, new Validator(), $status, $notices, $reminder, $reachability, $endpoint ),
				'admin'         => new AdminPage( $settings, $status, $endpoint, $notices, $file_check, $reachability ),
				'expiry_notice' => new ExpiryNotice( $status ),
			)
		);
	}

	/**
	 * Directories a physical security.txt could be served from.
	 *
	 * @return string[]
	 */
	public static function document_roots() {
		$roots = array( ABSPATH, dirname( rtrim( ABSPATH, '/\\' ) ) );
		if ( isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$roots[] = sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) );
		}
		return $roots;
	}

	/**
	 * Boot once, on plugins_loaded.
	 *
	 * @return self
	 */
	public static function boot() {
		if ( null === self::$instance ) {
			self::$instance = self::create();
			self::$instance->register();
		}
		return self::$instance;
	}

	/**
	 * Forget the booted instance (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$instance = null;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		// The file is answered here, before the theme loads and init runs.
		$this->get( 'endpoint' )->maybe_handle();

		$this->get( 'reminder' )->register();
		$this->get( 'health' )->register();
		self::schedule();

		if ( is_admin() ) {
			$this->get( 'handler' )->register();
			$this->get( 'admin' )->register();
			$this->get( 'expiry_notice' )->register();
		}
	}

	/**
	 * Activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::schedule();
	}

	/**
	 * Schedule the daily reminder check if missing.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( Reminder::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Reminder::HOOK );
		}
	}

	/**
	 * Deactivation: stop scheduled work (on every site when network
	 * deactivated). Settings stay until uninstall.
	 *
	 * @param bool $network_wide Whether the plugin was network deactivated.
	 * @return void
	 */
	public static function deactivate( $network_wide = false ) {
		if ( $network_wide && is_multisite() ) {
			foreach ( get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			) as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::unschedule();
				restore_current_blog();
			}
			return;
		}
		self::unschedule();
	}

	/**
	 * Clear this site's scheduled events.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( Reminder::HOOK );
	}

	/**
	 * Uninstall: remove this site's data.
	 *
	 * @return void
	 */
	public static function uninstall() {
		delete_option( Settings::OPTION );
		delete_option( Reminder::OPTION );
		delete_transient( Reachability::TRANSIENT );
		self::unschedule();
	}
}
