<?php
/**
 * Tests for AdminPage.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use CodoDigital\SecurityTxt\Admin\AdminPage;
use CodoDigital\SecurityTxt\Admin\Notices;
use CodoDigital\SecurityTxt\Document\Builder;
use CodoDigital\SecurityTxt\Http\Endpoint;
use CodoDigital\SecurityTxt\Server\FileCheck;
use CodoDigital\SecurityTxt\Server\Reachability;
use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Status;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Admin\AdminPage
 */
final class AdminPageTest extends TestCase {

	/**
	 * Notices.
	 *
	 * @var Notices
	 */
	private $notices;

	/**
	 * File conflicts to report.
	 *
	 * @var string[]
	 */
	private $conflicts = array();

	/**
	 * Cached delivery result.
	 *
	 * @var array<string, string>|null
	 */
	private $cached = null;

	protected function setUp(): void {
		parent::setUp();
		$this->conflicts = array();
		$this->cached    = null;
		Functions\stubs(
			array(
				'_n'                => static function ( $single, $plural, $number ) {
					return 1 === $number ? $single : $plural;
				},
				'esc_textarea'      => static function ( $text ) {
					return htmlspecialchars( (string) $text );
				},
			)
		);
	}

	private function page() {
		$settings        = new Settings();
		$status          = new Status( $settings );
		$this->notices   = new Notices();
		$file_check      = \Mockery::mock( FileCheck::class );
		$file_check->shouldReceive( 'conflicts' )->andReturnUsing(
			function () {
				return $this->conflicts;
			}
		);
		$reach = \Mockery::mock( Reachability::class );
		$reach->shouldReceive( 'cached' )->andReturnUsing(
			function () {
				return $this->cached;
			}
		);

		return new AdminPage( $settings, $status, new Endpoint( $settings, $status, new Builder() ), $this->notices, $file_check, $reach );
	}

	private function html() {
		ob_start();
		$this->page()->render();
		return ob_get_clean();
	}

	public function test_it_registers_its_hooks() {
		$page = $this->page();
		Actions\expectAdded( 'admin_menu' )->once()->with( array( $page, 'add_menu' ) );
		Filters\expectAdded( 'plugin_action_links_codo-security-txt/codo-security-txt.php' )->once();

		$page->register();
		$this->assertTrue( true );
	}

	public function test_it_adds_the_settings_page() {
		Functions\expect( 'add_options_page' )->once()->with( 'Security.txt', 'Security.txt', 'manage_options', 'codo-security-txt', \Mockery::type( 'array' ) );

		$this->page()->add_menu();
		$this->assertTrue( true );
	}

	public function test_it_adds_a_settings_link() {
		$links = $this->page()->action_links( array( '<a href="x">Deactivate</a>' ) );

		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'page=codo-security-txt', $links[0] );
	}

	public function test_nothing_renders_without_the_capability() {
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertSame( '', $this->html() );
	}

	public function test_an_unpublished_page_shows_the_form_only() {
		$html = $this->html();

		$this->assertStringContainsString( 'Not published.', $html );
		$this->assertStringContainsString( 'name="codo_security_txt[contacts]"', $html );
		$this->assertStringContainsString( 'value="codo_security_txt_save"', $html );
		$this->assertStringNotContainsString( 'codo_security_txt_extend', $html );
		$this->assertStringNotContainsString( 'What is published', $html );
		$this->assertStringContainsString( 'name="codo_security_txt[legacy_path]" value="1" checked="checked"', $html );
	}

	public function test_an_invalid_file_says_so() {
		$this->store( array( 'contacts' => array() ) );

		$this->assertStringContainsString( 'the file is incomplete', $this->html() );
	}

	public function test_a_published_page_shows_status_checks_extend_and_preview() {
		$this->store( array( 'expires' => '2099-01-01T23:59:59Z' ) );
		$this->cached = array(
			'status'  => 'ok',
			'message' => 'Delivered fine',
		);

		$html = $this->html();

		$this->assertStringContainsString( 'Published and valid.', $html );
		$this->assertStringContainsString( 'target="_blank"', $html );
		$this->assertStringContainsString( 'Last delivery check:', $html );
		$this->assertStringContainsString( 'Delivered fine', $html );
		$this->assertStringContainsString( 'codo_security_txt_verify', $html );
		$this->assertStringContainsString( 'Extend by one year', $html );
		$this->assertStringContainsString( 'What is published', $html );
		$this->assertStringContainsString( 'Contact: mailto:security@example.com', $html );
		$this->assertStringContainsString( 'value="2099-01-01"', $html );
		$this->assertStringContainsString( 'Expires 2099-01-01', $html );
	}

	public function test_expiry_states_are_labelled() {
		$this->store( array( 'expires' => '2020-01-01T00:00:00Z' ) );
		$this->assertStringContainsString( 'Published, but expired.', $this->html() );

		$this->store( array( 'expires' => gmdate( 'Y-m-d\T23:59:59\Z', time() + 3 * DAY_IN_SECONDS ) ) );
		$this->assertStringContainsString( 'expiring soon', $this->html() );
	}

	public function test_a_signed_message_hides_the_extend_button() {
		$this->store( array( 'signed' => $this->signed( '2099-01-01T00:00:00Z' ) ) );

		$html = $this->html();

		$this->assertStringContainsString( 'sign a new message', $html );
		$this->assertStringNotContainsString( 'codo_security_txt_extend', $html );
	}

	public function test_problems_are_listed() {
		$this->store( array( 'expires' => '2099-01-01T23:59:59Z' ) );
		$this->conflicts = array( '/security.txt' );
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_subdomain_install' )->justReturn( false );
		Functions\when( 'is_main_site' )->justReturn( false );
		Functions\when( 'home_url' )->justReturn( 'http://www.example.com/blog' );

		$html = $this->html();

		$this->assertStringContainsString( 'A real file exists at /security.txt', $html );
		$this->assertStringContainsString( 'subdirectory subsite', $html );
		$this->assertStringContainsString( 'installed in a subdirectory', $html );
		$this->assertStringContainsString( 'not on https', $html );
	}

	public function test_a_failed_save_restores_the_draft() {
		$this->store();
		$page = $this->page();
		$this->notices->store_draft(
			array(
				'contacts' => '<bad>',
				'enabled'  => '1',
				'expires'  => 'soon',
				'policy'   => array( 'not a string' ),
			)
		);

		ob_start();
		$page->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( '&lt;bad&gt;', $html );
		$this->assertStringContainsString( 'value="soon"', $html );
		$this->assertStringContainsString( 'name="codo_security_txt[enabled]" value="1" checked="checked"', $html );
		$this->assertStringNotContainsString( 'name="codo_security_txt[legacy_path]" value="1" checked', $html );
	}

	public function test_an_unparseable_stored_expiry_defaults_to_a_year_ahead() {
		$this->store( array( 'enabled' => false, 'expires' => '' ) );

		$this->assertStringContainsString( 'value="' . gmdate( 'Y-m-d', time() + 365 * DAY_IN_SECONDS ) . '"', $this->html() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_disable_constant_is_explained() {
		define( 'CODO_SECURITY_TXT_DISABLE', true );
		$this->store();

		$this->assertStringContainsString( 'CODO_SECURITY_TXT_DISABLE', $this->html() );
	}
}
