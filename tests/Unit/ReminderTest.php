<?php
/**
 * Tests for Reminder.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use CodoDigital\SecurityTxt\Cron\Reminder;
use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Status;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Cron\Reminder
 */
final class ReminderTest extends TestCase {

	/**
	 * Sent mails.
	 *
	 * @var array<int, array<int, string>>
	 */
	private $mails = array();

	protected function setUp(): void {
		parent::setUp();
		$this->mails                 = array();
		$this->options['admin_email'] = 'admin@example.com';
		$this->options['blogname']    = 'My Site';
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $message ) {
				$this->mails[] = array( $to, $subject, $message );
				return true;
			}
		);
	}

	private function reminder() {
		$settings = new Settings();
		return new Reminder( $settings, new Status( $settings ) );
	}

	public function test_it_registers_its_cron_hook() {
		$reminder = $this->reminder();
		Actions\expectAdded( Reminder::HOOK )->once()->with( array( $reminder, 'run' ) );

		$reminder->register();
		$this->assertTrue( true );
	}

	public function test_a_healthy_file_sends_nothing_and_resets() {
		$this->store();
		$this->options[ Reminder::OPTION ] = 2;

		$this->assertFalse( $this->reminder()->run( $this->now ) );
		$this->assertArrayNotHasKey( Reminder::OPTION, $this->options );
		$this->assertSame( array(), $this->mails );
	}

	public function test_run_defaults_to_the_current_time() {
		$this->store();

		$this->assertFalse( $this->reminder()->run() );
	}

	public function test_an_expiring_file_sends_one_early_warning() {
		$this->store( array( 'expires' => '2026-12-01T23:59:59Z' ) );

		$this->assertTrue( $this->reminder()->run( $this->now ) );
		$this->assertFalse( $this->reminder()->run( $this->now ) );

		$this->assertCount( 1, $this->mails );
		$this->assertSame( 'admin@example.com', $this->mails[0][0] );
		$this->assertSame( '[My Site] Your security.txt expires soon', $this->mails[0][1] );
		$this->assertStringContainsString( '2026-12-01', $this->mails[0][2] );
		$this->assertStringContainsString( 'Extend by one year', $this->mails[0][2] );
		$this->assertSame( 1, $this->options[ Reminder::OPTION ] );
	}

	public function test_it_escalates_to_urgent_then_expired() {
		$this->store( array( 'expires' => '2026-11-12T23:59:59Z' ) );
		$this->options[ Reminder::OPTION ] = 1;
		$this->assertTrue( $this->reminder()->run( $this->now ) );

		$this->store( array( 'expires' => '2026-11-01T23:59:59Z' ) );
		$this->assertTrue( $this->reminder()->run( $this->now ) );

		$this->assertCount( 2, $this->mails );
		$this->assertStringContainsString( 'expires soon', $this->mails[0][1] );
		$this->assertStringContainsString( 'has expired', $this->mails[1][1] );
		$this->assertSame( 3, $this->options[ Reminder::OPTION ] );
	}

	public function test_a_signed_file_gets_signing_instructions() {
		$this->store( array( 'signed' => $this->signed( '2026-12-01T23:59:59Z' ) ) );

		$this->reminder()->run( $this->now );

		$this->assertStringContainsString( 'PGP-signed', $this->mails[0][2] );
	}

	public function test_reminders_can_be_switched_off() {
		$this->store( array( 'expires' => '2026-12-01T23:59:59Z', 'reminders' => false ) );

		$this->assertFalse( $this->reminder()->run( $this->now ) );
		$this->assertSame( array(), $this->mails );
	}

	public function test_the_recipient_can_be_filtered_and_must_be_valid() {
		$this->store( array( 'expires' => '2026-12-01T23:59:59Z' ) );
		Filters\expectApplied( 'codo_security_txt_reminder_recipient' )->once()->andReturn( 'nope' );

		$this->assertFalse( $this->reminder()->run( $this->now ) );
		$this->assertSame( array(), $this->mails );
	}

	public function test_a_failed_mail_is_retried_next_time() {
		$this->store( array( 'expires' => '2026-12-01T23:59:59Z' ) );
		Functions\when( 'wp_mail' )->justReturn( false );

		$this->assertFalse( $this->reminder()->run( $this->now ) );
		$this->assertArrayNotHasKey( Reminder::OPTION, $this->options );
	}
}
