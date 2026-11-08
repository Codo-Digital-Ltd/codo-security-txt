<?php
/**
 * Tests for Notices.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use CodoDigital\SecurityTxt\Admin\Notices;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Admin\Notices
 */
final class NoticesTest extends TestCase {

	public function test_notices_are_escaped_printed_once_and_cleared() {
		$notices = new Notices();
		$notices->add( 'error', 'Bad <b>input</b>' );
		$notices->add( 'nonsense', 'Fine' );

		ob_start();
		$notices->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'notice-info', $html );
		$this->assertStringContainsString( 'Bad &lt;b&gt;input&lt;/b&gt;', $html );

		ob_start();
		$notices->render();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_a_draft_is_returned_once() {
		$notices = new Notices();

		$this->assertNull( $notices->take_draft() );

		$notices->store_draft( array( 'contacts' => 'x' ) );
		$this->assertSame( array( 'contacts' => 'x' ), $notices->take_draft() );
		$this->assertNull( $notices->take_draft() );
	}
}
