<?php
/**
 * Settings screen (Settings > Security.txt).
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Admin;

use CodoDigital\SecurityTxt\Document\Expiry;
use CodoDigital\SecurityTxt\Http\Endpoint;
use CodoDigital\SecurityTxt\Server\FileCheck;
use CodoDigital\SecurityTxt\Server\Reachability;
use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Plain server-rendered screen: no JavaScript, so it cannot clash with other plugins' admin scripts.
 */
class AdminPage {

	const SLUG = 'codo-security-txt';

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
	 * Endpoint.
	 *
	 * @var Endpoint
	 */
	private $endpoint;

	/**
	 * Notices.
	 *
	 * @var Notices
	 */
	private $notices;

	/**
	 * File check.
	 *
	 * @var FileCheck
	 */
	private $file_check;

	/**
	 * Reachability.
	 *
	 * @var Reachability
	 */
	private $reachability;

	/**
	 * Constructor.
	 *
	 * @param Settings     $settings     Settings.
	 * @param Status       $status       Status.
	 * @param Endpoint     $endpoint     Endpoint.
	 * @param Notices      $notices      Notices.
	 * @param FileCheck    $file_check   File check.
	 * @param Reachability $reachability Reachability.
	 */
	public function __construct( Settings $settings, Status $status, Endpoint $endpoint, Notices $notices, FileCheck $file_check, Reachability $reachability ) {
		$this->settings     = $settings;
		$this->status       = $status;
		$this->endpoint     = $endpoint;
		$this->notices      = $notices;
		$this->file_check   = $file_check;
		$this->reachability = $reachability;
	}

	/**
	 * Hook the menu entry and the plugin-list link.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CODO_SECURITY_TXT_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Add Settings > Security.txt.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'Security.txt', 'codo-security-txt' ),
			__( 'Security.txt', 'codo-security-txt' ),
			Capability::site(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ),
				esc_html__( 'Settings', 'codo-security-txt' )
			)
		);
		return $links;
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capability::site() ) ) {
			return;
		}

		$now    = time();
		$state  = $this->status->evaluate( $now );
		$values = $this->form_values( $this->notices->take_draft(), $now );

		echo '<div class="wrap"><h1>' . esc_html__( 'Security.txt', 'codo-security-txt' ) . '</h1>';
		$this->notices->render();
		echo '<p>' . esc_html__( 'A security.txt file (RFC 9116) tells security researchers how to report a vulnerability on your site.', 'codo-security-txt' ) . '</p>';

		$this->render_status( $state );
		if ( Status::is_published( $state['state'] ) ) {
			$this->render_checks();
			$this->render_extend( $state );
		}
		$this->render_form( $values );
		if ( Status::is_published( $state['state'] ) ) {
			echo '<h2>' . esc_html__( 'What is published', 'codo-security-txt' ) . '</h2><pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;overflow:auto">' . esc_html( $this->endpoint->body() ) . '</pre>';
		}
		echo '</div>';
	}

	/**
	 * Values for the form: a rejected submission, or the stored settings.
	 *
	 * @param array<string, mixed>|null $draft Rejected submission.
	 * @param int                       $now   Current Unix timestamp.
	 * @return array<string, mixed>
	 */
	private function form_values( $draft, $now ) {
		if ( is_array( $draft ) ) {
			$values = array();
			foreach ( array( 'contacts', 'encryption', 'canonical', 'expires', 'acknowledgments', 'preferred_languages', 'policy', 'hiring', 'csaf', 'signed' ) as $key ) {
				$values[ $key ] = isset( $draft[ $key ] ) && is_string( $draft[ $key ] ) ? $draft[ $key ] : '';
			}
			foreach ( array( 'enabled', 'legacy_path', 'reminders', 'auto_canonical' ) as $key ) {
				$values[ $key ] = ! empty( $draft[ $key ] );
			}
			return $values;
		}

		$values = $this->settings->all();
		foreach ( array( 'contacts', 'encryption', 'canonical' ) as $key ) {
			$values[ $key ] = implode( "\n", $values[ $key ] );
		}
		$timestamp         = Expiry::parse( $values['expires'] );
		$values['expires'] = gmdate( 'Y-m-d', null === $timestamp ? $now + 365 * DAY_IN_SECONDS : $timestamp );
		return $values;
	}

	/**
	 * The status panel.
	 *
	 * @param array{state: string, expires: int|null, days_left: int|null, signed: bool} $state Status.
	 * @return void
	 */
	private function render_status( array $state ) {
		$labels              = array(
			Status::DISABLED => array( 'info', __( 'Not published. Fill in the form and tick "Publish" to switch it on.', 'codo-security-txt' ) ),
			Status::INVALID  => array( 'error', __( 'Not published: the file is incomplete. It needs a contact and an expiry date.', 'codo-security-txt' ) ),
			Status::EXPIRED  => array( 'error', __( 'Published, but expired. Extend it so researchers can trust it.', 'codo-security-txt' ) ),
			Status::EXPIRING => array( 'warning', __( 'Published, and expiring soon.', 'codo-security-txt' ) ),
			Status::OK       => array( 'success', __( 'Published and valid.', 'codo-security-txt' ) ),
		);
		list( $type, $text ) = $labels[ $state['state'] ];

		if ( $this->settings->disabled_by_constant() ) {
			$type = 'warning';
			$text = __( 'Switched off by CODO_SECURITY_TXT_DISABLE in wp-config.php.', 'codo-security-txt' );
		}

		printf( '<div class="notice notice-%1$s inline"><p><strong>%2$s</strong>', esc_attr( $type ), esc_html( $text ) );
		if ( null !== $state['expires'] ) {
			printf(
				' %s',
				esc_html(
					sprintf(
						/* translators: 1: expiry date (YYYY-MM-DD), 2: days left. */
						_n( 'Expires %1$s (%2$d day left).', 'Expires %1$s (%2$d days left).', max( 0, (int) $state['days_left'] ), 'codo-security-txt' ),
						gmdate( 'Y-m-d', $state['expires'] ),
						max( 0, (int) $state['days_left'] )
					)
				)
			);
		}
		echo '</p>';
		if ( Status::is_published( $state['state'] ) ) {
			$url = $this->status->file_url();
			printf( '<p><a href="%1$s" target="_blank" rel="noopener">%2$s</a></p>', esc_url( $url ), esc_html( $url ) );
		}
		echo '</div>';
	}

	/**
	 * Environment checks: physical files, subdirectory installs and delivery.
	 *
	 * @return void
	 */
	private function render_checks() {
		$problems = array();

		$conflicts = $this->file_check->conflicts();
		if ( ! empty( $conflicts ) ) {
			$problems[] = sprintf(
				/* translators: %s: comma-separated file paths. */
				__( 'A real file exists at %s. Your web server serves it instead of this plugin, so the settings below are ignored. Delete the file.', 'codo-security-txt' ),
				implode( ', ', $conflicts )
			);
		}
		if ( is_multisite() && ! is_subdomain_install() && ! is_main_site() ) {
			$problems[] = __( 'This is a subdirectory subsite. It shares its host with the main site, so only the main site can publish /.well-known/security.txt.', 'codo-security-txt' );
		}
		$path = wp_parse_url( home_url(), PHP_URL_PATH );
		if ( is_string( $path ) && '' !== trim( $path, '/' ) ) {
			$problems[] = __( 'WordPress is installed in a subdirectory. The file must be at the root of the host, so make sure your web server routes /.well-known/security.txt to WordPress.', 'codo-security-txt' );
		}
		if ( '' === $this->status->canonical_url() ) {
			$problems[] = __( 'The site is not on https, so no Canonical line is added. RFC 9116 requires https for the file and its URLs.', 'codo-security-txt' );
		}

		foreach ( $problems as $problem ) {
			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html( $problem ) );
		}

		$cached = $this->reachability->cached();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( FormHandler::VERIFY ) . '">';
		wp_nonce_field( FormHandler::VERIFY );
		echo '<p>';
		if ( null !== $cached ) {
			echo '<strong>' . esc_html__( 'Last delivery check:', 'codo-security-txt' ) . '</strong> ' . esc_html( $cached['message'] ) . ' ';
		}
		submit_button( __( 'Check delivery now', 'codo-security-txt' ), 'secondary', 'submit', false );
		echo '</p></form>';
	}

	/**
	 * The one-click renewal button.
	 *
	 * @param array{signed: bool} $state Status.
	 * @return void
	 */
	private function render_extend( array $state ) {
		if ( $state['signed'] ) {
			echo '<p>' . esc_html__( 'The file is a PGP-signed message. To renew it, sign a new message with a later Expires date and paste it below.', 'codo-security-txt' ) . '</p>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( FormHandler::EXTEND ) . '">';
		wp_nonce_field( FormHandler::EXTEND );
		submit_button( __( 'Extend by one year', 'codo-security-txt' ), 'primary', 'submit', false );
		echo '</form>';
	}

	/**
	 * The settings form.
	 *
	 * @param array<string, mixed> $values Form values.
	 * @return void
	 */
	private function render_form( array $values ) {
		echo '<h2>' . esc_html__( 'Settings', 'codo-security-txt' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( FormHandler::SAVE ) . '">';
		wp_nonce_field( FormHandler::SAVE );
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->checkbox_row( 'enabled', __( 'Publish', 'codo-security-txt' ), __( 'Serve /.well-known/security.txt', 'codo-security-txt' ), $values );
		$this->checkbox_row( 'legacy_path', __( 'Legacy location', 'codo-security-txt' ), __( 'Also serve /security.txt', 'codo-security-txt' ), $values );
		$this->checkbox_row( 'reminders', __( 'Reminders', 'codo-security-txt' ), __( 'Email the site admin 30 days and 7 days before it expires, and when it has expired', 'codo-security-txt' ), $values );

		$this->textarea_row( 'contacts', __( 'Contact (required)', 'codo-security-txt' ), 3, __( 'One per line: an email address, a mailto: or tel: link, or an https:// URL to a contact form. Most preferred first.', 'codo-security-txt' ), $values );
		$this->text_row( 'expires', __( 'Expires (required)', 'codo-security-txt' ), 'date', __( 'The file is treated as out of date after this day (UTC). RFC 9116 recommends less than a year ahead.', 'codo-security-txt' ), $values );
		$this->textarea_row( 'encryption', __( 'Encryption', 'codo-security-txt' ), 2, __( 'One per line: an https:// URL to your public PGP key, a dns: URI or an openpgp4fpr: fingerprint.', 'codo-security-txt' ), $values );
		$this->text_row( 'acknowledgments', __( 'Acknowledgments', 'codo-security-txt' ), 'url', __( 'An https:// page that thanks researchers.', 'codo-security-txt' ), $values );
		$this->text_row( 'preferred_languages', __( 'Preferred languages', 'codo-security-txt' ), 'text', __( 'Comma-separated language tags, such as en, en-GB.', 'codo-security-txt' ), $values );
		$this->checkbox_row( 'auto_canonical', __( 'Canonical', 'codo-security-txt' ), __( 'Add this site\'s own URL as Canonical when none is listed below', 'codo-security-txt' ), $values );
		$this->textarea_row( 'canonical', __( 'Canonical URLs', 'codo-security-txt' ), 2, __( 'Optional. One https:// URL per line where this file is published.', 'codo-security-txt' ), $values );
		$this->text_row( 'policy', __( 'Policy', 'codo-security-txt' ), 'url', __( 'An https:// page with your vulnerability disclosure policy.', 'codo-security-txt' ), $values );
		$this->text_row( 'hiring', __( 'Hiring', 'codo-security-txt' ), 'url', __( 'An https:// page listing security-related jobs.', 'codo-security-txt' ), $values );
		$this->text_row( 'csaf', __( 'CSAF', 'codo-security-txt' ), 'url', __( 'An https:// link to your CSAF provider-metadata.json.', 'codo-security-txt' ), $values );
		$this->textarea_row( 'signed', __( 'Signed message', 'codo-security-txt' ), 10, __( 'Advanced. Paste a PGP clear-signed security.txt to publish it exactly as signed; the fields above are then ignored. The plugin checks its structure, Contact and Expires, but cannot verify the signature itself.', 'codo-security-txt' ), $values );

		echo '</tbody></table>';
		submit_button( __( 'Save settings', 'codo-security-txt' ) );
		echo '</form>';
	}

	/**
	 * A checkbox row.
	 *
	 * @param string               $key    Field key.
	 * @param string               $label  Row label.
	 * @param string               $text   Checkbox label.
	 * @param array<string, mixed> $values Form values.
	 * @return void
	 */
	private function checkbox_row( $key, $label, $text, array $values ) {
		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="codo_security_txt[%2$s]" value="1"%3$s> %4$s</label></td></tr>',
			esc_html( $label ),
			esc_attr( $key ),
			checked( ! empty( $values[ $key ] ), true, false ),
			esc_html( $text )
		);
	}

	/**
	 * A single-line input row.
	 *
	 * @param string               $key    Field key.
	 * @param string               $label  Row label.
	 * @param string               $type   Input type.
	 * @param string               $help   Help text.
	 * @param array<string, mixed> $values Form values.
	 * @return void
	 */
	private function text_row( $key, $label, $type, $help, array $values ) {
		printf(
			'<tr><th scope="row"><label for="codo-st-%1$s">%2$s</label></th><td><input type="%3$s" class="regular-text" id="codo-st-%1$s" name="codo_security_txt[%1$s]" value="%4$s"><p class="description">%5$s</p></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( isset( $values[ $key ] ) ? (string) $values[ $key ] : '' ),
			esc_html( $help )
		);
	}

	/**
	 * A textarea row.
	 *
	 * @param string               $key    Field key.
	 * @param string               $label  Row label.
	 * @param int                  $rows   Visible rows.
	 * @param string               $help   Help text.
	 * @param array<string, mixed> $values Form values.
	 * @return void
	 */
	private function textarea_row( $key, $label, $rows, $help, array $values ) {
		printf(
			'<tr><th scope="row"><label for="codo-st-%1$s">%2$s</label></th><td><textarea class="large-text code" rows="%3$d" id="codo-st-%1$s" name="codo_security_txt[%1$s]">%4$s</textarea><p class="description">%5$s</p></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			(int) $rows,
			esc_textarea( isset( $values[ $key ] ) ? (string) $values[ $key ] : '' ),
			esc_html( $help )
		);
	}
}
