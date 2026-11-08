<?php
/**
 * Tests for Reachability.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use Brain\Monkey\Functions;
use CodoDigital\SecurityTxt\Server\Reachability;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Server\Reachability
 */
final class ReachabilityTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs(
			array(
				'wp_remote_retrieve_response_code' => static function ( $r ) {
					return $r['code'];
				},
				'wp_remote_retrieve_body'          => static function ( $r ) {
					return $r['body'];
				},
				'wp_remote_retrieve_header'        => static function ( $r, $h ) {
					return $r['headers'][ $h ] ?? '';
				},
			)
		);
	}

	private function respond( $response ) {
		Functions\when( 'wp_remote_get' )->justReturn( $response );
	}

	public function test_a_transport_error_is_reported() {
		$this->respond( new \WP_Error( 'http_request_failed', 'cURL error 28' ) );

		$result = ( new Reachability() )->check( 'https://x/', 'body' );

		$this->assertSame( 'error', $result['status'] );
		$this->assertStringContainsString( 'cURL error 28', $result['message'] );
	}

	public function test_a_non_200_is_an_error() {
		$this->respond( array( 'code' => 404, 'body' => '', 'headers' => array() ) );

		$result = ( new Reachability() )->check( 'https://x/', 'body' );

		$this->assertSame( 'error', $result['status'] );
		$this->assertStringContainsString( '404', $result['message'] );
	}

	public function test_different_content_is_a_warning() {
		$this->respond( array( 'code' => 200, 'body' => 'old', 'headers' => array( 'content-type' => 'text/plain' ) ) );

		$this->assertSame( 'warning', ( new Reachability() )->check( 'https://x/', 'new' )['status'] );
	}

	public function test_the_wrong_content_type_is_a_warning() {
		$this->respond( array( 'code' => 200, 'body' => 'a', 'headers' => array( 'content-type' => 'text/html' ) ) );

		$result = ( new Reachability() )->check( 'https://x/', "a\n" );

		$this->assertSame( 'warning', $result['status'] );
		$this->assertStringContainsString( 'Content-Type', $result['message'] );
	}

	public function test_a_correct_response_is_ok() {
		$this->respond( array( 'code' => 200, 'body' => "a\n", 'headers' => array( 'content-type' => 'text/plain; charset=utf-8' ) ) );

		$this->assertSame( 'ok', ( new Reachability() )->check( 'https://x/', 'a' )['status'] );
	}

	public function test_results_are_cached_and_forgotten() {
		$this->respond( array( 'code' => 200, 'body' => 'a', 'headers' => array( 'content-type' => 'text/plain' ) ) );
		$reach = new Reachability();

		$this->assertNull( $reach->cached() );
		$result = $reach->run_and_store( 'https://x/', 'a' );
		$this->assertSame( $result, $reach->cached() );

		$reach->forget();
		$this->assertNull( $reach->cached() );
	}

	public function test_a_malformed_cache_entry_is_ignored() {
		$this->transients[ Reachability::TRANSIENT ] = array( 'status' => 'ok' );

		$this->assertNull( ( new Reachability() )->cached() );
	}
}
