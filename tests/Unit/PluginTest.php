<?php
/**
 * Tests for Plugin.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use CodoDigital\SecurityTxt\Cron\Reminder;
use CodoDigital\SecurityTxt\Plugin;
use CodoDigital\SecurityTxt\Server\Reachability;
use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Plugin
 */
final class PluginTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Plugin::reset();
		Functions\stubs(
			array(
				'is_admin'  => false,
				'add_options_page' => true,
			)
		);
		$_SERVER['DOCUMENT_ROOT'] = '/var/www/html';
		unset( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] );
	}

	protected function tearDown(): void {
		Plugin::reset();
		unset( $_SERVER['DOCUMENT_ROOT'] );
		parent::tearDown();
	}

	public function test_create_wires_every_service() {
		$plugin = Plugin::create();

		foreach ( array( 'settings', 'status', 'endpoint', 'reminder', 'health', 'handler', 'admin', 'expiry_notice' ) as $name ) {
			$this->assertIsObject( $plugin->get( $name ) );
		}
	}

	public function test_document_roots_include_the_server_document_root() {
		$roots = Plugin::document_roots();

		$this->assertContains( '/var/www/html', $roots );
		$this->assertContains( ABSPATH, $roots );

		unset( $_SERVER['DOCUMENT_ROOT'] );
		$this->assertCount( 2, Plugin::document_roots() );
	}

	public function test_boot_registers_the_front_end_hooks_once() {
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\expect( 'wp_schedule_event' )->once()->with( \Mockery::type( 'int' ), 'daily', Reminder::HOOK );
		Actions\expectAdded( Reminder::HOOK )->once();
		Filters\expectAdded( 'site_status_tests' )->once();
		Actions\expectAdded( 'admin_menu' )->never();

		$first  = Plugin::boot();
		$second = Plugin::boot();

		$this->assertSame( $first, $second );
	}

	public function test_boot_registers_the_admin_hooks_in_the_dashboard() {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'wp_next_scheduled' )->justReturn( 12345 );
		Actions\expectAdded( 'admin_post_codo_security_txt_save' )->once();
		Actions\expectAdded( 'admin_menu' )->once();
		Actions\expectAdded( 'admin_notices' )->once();

		Plugin::boot();
		$this->assertTrue( true );
	}

	public function test_activation_schedules_the_reminder() {
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\expect( 'wp_schedule_event' )->once();

		Plugin::activate();
		$this->assertTrue( true );
	}

	public function test_an_already_scheduled_event_is_not_duplicated() {
		Functions\when( 'wp_next_scheduled' )->justReturn( 99 );
		Functions\expect( 'wp_schedule_event' )->never();

		Plugin::schedule();
		$this->assertTrue( true );
	}

	public function test_deactivation_clears_the_schedule() {
		Functions\expect( 'wp_clear_scheduled_hook' )->once()->with( Reminder::HOOK );

		Plugin::deactivate();
		$this->assertTrue( true );
	}

	public function test_network_deactivation_clears_every_site() {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_sites' )->justReturn( array( 1, 2, 3 ) );
		Functions\expect( 'switch_to_blog' )->times( 3 );
		Functions\expect( 'restore_current_blog' )->times( 3 );
		Functions\expect( 'wp_clear_scheduled_hook' )->times( 3 );

		Plugin::deactivate( true );
		$this->assertTrue( true );
	}

	public function test_network_flag_on_a_single_site_clears_once() {
		Functions\expect( 'wp_clear_scheduled_hook' )->once();

		Plugin::deactivate( true );
		$this->assertTrue( true );
	}

	public function test_uninstall_removes_everything() {
		$this->options[ Settings::OPTION ]  = array( 'enabled' => true );
		$this->options[ Reminder::OPTION ]  = 2;
		$this->transients[ Reachability::TRANSIENT ] = array();
		Functions\expect( 'wp_clear_scheduled_hook' )->once();

		Plugin::uninstall();

		$this->assertSame( array(), $this->options );
		$this->assertSame( array(), $this->transients );
	}
}
