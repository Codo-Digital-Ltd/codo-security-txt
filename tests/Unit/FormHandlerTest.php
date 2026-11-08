<?php
/**
 * Tests for FormHandler.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use CodoDigital\SecurityTxt\Admin\FormHandler;
use CodoDigital\SecurityTxt\Admin\Notices;
use CodoDigital\SecurityTxt\Cron\Reminder;
use CodoDigital\SecurityTxt\Document\Builder;
use CodoDigital\SecurityTxt\Document\Validator;
use CodoDigital\SecurityTxt\Http\Endpoint;
use CodoDigital\SecurityTxt\Server\Reachability;
use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Status;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * Handler that records instead of redirecting.
 */
class RecordingHandler extends FormHandler {

	/**
	 * Number of redirects.
	 *
	 * @var int
	 */
	public $finished = 0;

	/**
	 * Record.
	 *
	 * @return void
	 */
	protected function finish() {
		++$this->finished;
	}
}

/**
 * @covers \CodoDigital\SecurityTxt\Admin\FormHandler
 */
final class FormHandlerTest extends TestCase {

	/**
	 * Notices.
	 *
	 * @var Notices
	 */
	private $notices;

	/**
	 * Reachability mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $reach;

	protected function tearDown(): void {
		$_POST = array();
		parent::tearDown();
	}

	private function handler() {
		$settings      = new Settings();
		$status        = new Status( $settings );
		$this->notices = new Notices();
		$this->reach   = \Mockery::mock( Reachability::class );
		$this->reach->shouldReceive( 'forget' )->zeroOrMoreTimes();

		return new RecordingHandler(
			$settings,
			new Validator(),
			$status,
			$this->notices,
			new Reminder( $settings, $status ),
			$this->reach,
			new Endpoint( $settings, $status, new Builder() )
		);
	}

	private function rendered() {
		ob_start();
		$this->notices->render();
		return ob_get_clean();
	}

	public function test_it_registers_the_three_actions() {
		$handler = $this->handler();
		Actions\expectAdded( 'admin_post_codo_security_txt_save' )->once();
		Actions\expectAdded( 'admin_post_codo_security_txt_extend' )->once();
		Actions\expectAdded( 'admin_post_codo_security_txt_verify' )->once();

		$handler->register();
		$this->assertTrue( true );
	}

	public function test_users_without_the_capability_get_a_403() {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'wp_die' )->times( 3 )->with( \Mockery::type( 'string' ), '', array( 'response' => 403 ) );
		$handler = $this->handler();

		$handler->handle_save();
		$handler->handle_extend();
		$handler->handle_verify();

		$this->assertSame( 0, $handler->finished );
	}

	public function test_a_valid_save_stores_the_settings() {
		$_POST['codo_security_txt'] = array(
			'enabled'  => '1',
			'contacts' => 'security@example.com',
			'expires'  => gmdate( 'Y-m-d', time() + 400 * DAY_IN_SECONDS ),
		);
		Actions\expectDone( 'codo_security_txt_changed' )->once();
		$this->options[ Reminder::OPTION ] = 2;
		$handler                           = $this->handler();

		$handler->handle_save();

		$stored = $this->options[ Settings::OPTION ];
		$this->assertTrue( $stored['enabled'] );
		$this->assertSame( array( 'mailto:security@example.com' ), $stored['contacts'] );
		$this->assertArrayNotHasKey( Reminder::OPTION, $this->options );
		$this->assertSame( 1, $handler->finished );
		$html = $this->rendered();
		$this->assertStringContainsString( 'Settings saved.', $html );
		$this->assertStringContainsString( 'notice-warning', $html );
	}

	public function test_an_invalid_save_keeps_the_old_settings_and_the_draft() {
		$this->store();
		$_POST['codo_security_txt'] = array(
			'enabled'  => '1',
			'contacts' => 'nonsense',
			'expires'  => '2027-06-01',
		);
		$handler = $this->handler();

		$handler->handle_save();

		$this->assertSame( array( 'mailto:security@example.com' ), $this->options[ Settings::OPTION ]['contacts'] );
		$this->assertSame( 'nonsense', $this->notices->take_draft()['contacts'] );
		$this->assertStringContainsString( 'notice-error', $this->rendered() );
		$this->assertSame( 1, $handler->finished );
	}

	public function test_a_missing_post_array_is_treated_as_empty() {
		$handler = $this->handler();

		$handler->handle_save();

		$this->assertFalse( $this->options[ Settings::OPTION ]['enabled'] );
		$this->assertSame( 1, $handler->finished );
	}

	public function test_extend_adds_a_year() {
		$this->store( array( 'expires' => '2026-12-01T23:59:59Z' ) );
		$handler = $this->handler();

		$handler->handle_extend();

		$this->assertGreaterThan( time() + 360 * DAY_IN_SECONDS, strtotime( $this->options[ Settings::OPTION ]['expires'] ) );
		$this->assertStringContainsString( 'Extended.', $this->rendered() );
		$this->assertSame( 1, $handler->finished );
	}

	public function test_extend_refuses_for_a_signed_message() {
		$this->store( array( 'signed' => $this->signed() ) );
		$handler = $this->handler();

		$handler->handle_extend();

		$this->assertSame( '2027-06-01T23:59:59Z', $this->options[ Settings::OPTION ]['expires'] );
		$this->assertStringContainsString( 'PGP-signed', $this->rendered() );
	}

	public function test_verify_does_nothing_for_an_unpublished_file() {
		$this->store( array( 'enabled' => false ) );
		$handler = $this->handler();
		$this->reach->shouldReceive( 'run_and_store' )->never();

		$handler->handle_verify();

		$this->assertStringContainsString( 'Nothing to check', $this->rendered() );
		$this->assertSame( 1, $handler->finished );
	}

	/**
	 * @dataProvider verify_results
	 */
	public function test_verify_reports_the_delivery_result( $status, $class ) {
		$this->store( array( 'expires' => '2099-01-01T23:59:59Z' ) );
		$handler = $this->handler();
		$this->reach->shouldReceive( 'run_and_store' )
			->once()
			->with( 'https://www.example.com/.well-known/security.txt', \Mockery::type( 'string' ) )
			->andReturn(
				array(
					'status'  => $status,
					'message' => 'Result message',
				)
			);

		$handler->handle_verify();

		$html = $this->rendered();
		$this->assertStringContainsString( $class, $html );
		$this->assertStringContainsString( 'Result message', $html );
	}

	public function verify_results() {
		return array(
			'ok'      => array( 'ok', 'notice-success' ),
			'warning' => array( 'warning', 'notice-warning' ),
			'error'   => array( 'error', 'notice-error' ),
			'unknown' => array( 'weird', 'notice-info' ),
		);
	}
}
