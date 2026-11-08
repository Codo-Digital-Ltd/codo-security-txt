<?php
/**
 * Site Health test.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Health;

use CodoDigital\SecurityTxt\Admin\AdminPage;
use CodoDigital\SecurityTxt\Http\Endpoint;
use CodoDigital\SecurityTxt\Server\FileCheck;
use CodoDigital\SecurityTxt\Server\Reachability;
use CodoDigital\SecurityTxt\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Adds "security.txt file" to Tools > Site Health.
 */
class SiteHealth {

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
	 * @param Status       $status       Status.
	 * @param Endpoint     $endpoint     Endpoint.
	 * @param FileCheck    $file_check   File check.
	 * @param Reachability $reachability Reachability.
	 */
	public function __construct( Status $status, Endpoint $endpoint, FileCheck $file_check, Reachability $reachability ) {
		$this->status       = $status;
		$this->endpoint     = $endpoint;
		$this->file_check   = $file_check;
		$this->reachability = $reachability;
	}

	/**
	 * Hook the test.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'site_status_tests', array( $this, 'add_test' ) );
	}

	/**
	 * Register the direct test.
	 *
	 * @param array<string, mixed> $tests Site Health tests.
	 * @return array<string, mixed>
	 */
	public function add_test( $tests ) {
		$tests['direct']['codo_security_txt'] = array(
			'label' => __( 'security.txt file', 'codo-security-txt' ),
			'test'  => array( $this, 'run' ),
		);
		return $tests;
	}

	/**
	 * Run the test.
	 *
	 * @return array<string, mixed>
	 */
	public function run() {
		$state  = $this->status->evaluate();
		$result = array(
			'label'       => __( 'Your security.txt file is published and up to date', 'codo-security-txt' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'codo-security-txt' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Security researchers can find out how to report a vulnerability on your site.', 'codo-security-txt' ) . '</p>',
			'actions'     => sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( admin_url( 'options-general.php?page=' . AdminPage::SLUG ) ),
				esc_html__( 'Manage security.txt', 'codo-security-txt' )
			),
			'test'        => 'codo_security_txt',
		);

		$problem = $this->problem( $state );
		if ( null !== $problem ) {
			$result['label']       = $problem['label'];
			$result['status']      = $problem['status'];
			$result['description'] = '<p>' . esc_html( $problem['description'] ) . '</p>';
		}
		return $result;
	}

	/**
	 * The most important problem, if any.
	 *
	 * @param array{state: string} $state Status.
	 * @return array{label: string, status: string, description: string}|null
	 */
	private function problem( array $state ) {
		switch ( $state['state'] ) {
			case Status::DISABLED:
				return array(
					'label'       => __( 'No security.txt file is published', 'codo-security-txt' ),
					'status'      => 'recommended',
					'description' => __( 'A security.txt file (RFC 9116) tells researchers who to contact about a vulnerability. Without one, a report may never reach you.', 'codo-security-txt' ),
				);
			case Status::INVALID:
				return array(
					'label'       => __( 'Your security.txt file is incomplete', 'codo-security-txt' ),
					'status'      => 'critical',
					'description' => __( 'The file needs at least one contact and a valid expiry date, so it is not being published.', 'codo-security-txt' ),
				);
			case Status::EXPIRED:
				return array(
					'label'       => __( 'Your security.txt file has expired', 'codo-security-txt' ),
					'status'      => 'critical',
					'description' => __( 'Researchers treat an expired file as out of date. Extend it from the settings screen.', 'codo-security-txt' ),
				);
			case Status::EXPIRING:
				return array(
					'label'       => __( 'Your security.txt file expires soon', 'codo-security-txt' ),
					'status'      => 'recommended',
					'description' => __( 'Extend it from the settings screen before researchers start treating it as out of date.', 'codo-security-txt' ),
				);
		}

		$conflicts = $this->file_check->conflicts();
		if ( ! empty( $conflicts ) ) {
			return array(
				'label'       => __( 'A physical security.txt file overrides the plugin', 'codo-security-txt' ),
				'status'      => 'recommended',
				'description' => sprintf(
					/* translators: %s: comma-separated file paths. */
					__( 'A real file exists at %s, and your web server serves it instead of the settings you manage here. Delete it.', 'codo-security-txt' ),
					implode( ', ', $conflicts )
				),
			);
		}

		$reach = $this->reachability->cached();
		if ( null === $reach ) {
			$reach = $this->reachability->run_and_store( $this->status->file_url(), $this->endpoint->body() );
		}
		if ( 'ok' !== $reach['status'] ) {
			return array(
				'label'       => __( 'Your security.txt file may not be reachable', 'codo-security-txt' ),
				'status'      => 'recommended',
				'description' => $reach['message'],
			);
		}
		return null;
	}
}
