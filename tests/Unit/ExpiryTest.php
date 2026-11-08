<?php
/**
 * Tests for Expiry.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use CodoDigital\SecurityTxt\Document\Expiry;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Document\Expiry
 */
final class ExpiryTest extends TestCase {

	public function test_parse_accepts_rfc3339_values() {
		$this->assertSame( 1798847999, Expiry::parse( '2027-01-01T23:59:59Z' ) );
		$this->assertSame( 1798847999, Expiry::parse( '2027-01-01T23:59:59.000Z' ) );
		$this->assertSame( 1798847999, Expiry::parse( '2027-01-02T00:59:59+01:00' ) );
		$this->assertSame( 1798847999, Expiry::parse( ' 2027-01-01t23:59:59z ' ) );
	}

	public function test_parse_rejects_bad_values() {
		$this->assertNull( Expiry::parse( null ) );
		$this->assertNull( Expiry::parse( array() ) );
		$this->assertNull( Expiry::parse( '' ) );
		$this->assertNull( Expiry::parse( '2027-01-01' ) );
		$this->assertNull( Expiry::parse( 'next year' ) );
		$this->assertNull( Expiry::parse( '2027-02-30T00:00:00Z' ) );
		$this->assertNull( Expiry::parse( '2027-13-01T00:00:00Z' ) );
		$this->assertNull( Expiry::parse( '2027-01-01T25:00:00Z' ) );
	}

	public function test_from_date_gives_the_end_of_the_day() {
		$this->assertSame( '2027-01-01T23:59:59Z', Expiry::from_date( '2027-01-01' ) );
		$this->assertSame( '2027-01-01T23:59:59Z', Expiry::from_date( ' 2027-01-01 ' ) );
	}

	public function test_from_date_rejects_bad_values() {
		$this->assertNull( Expiry::from_date( null ) );
		$this->assertNull( Expiry::from_date( '2027-01-01T00:00:00Z' ) );
		$this->assertNull( Expiry::from_date( '2027-02-30' ) );
		$this->assertNull( Expiry::from_date( 'soon' ) );
	}

	public function test_format_and_days_left() {
		$this->assertSame( '2026-11-08T12:00:00Z', Expiry::format( $this->now ) );
		$this->assertSame( 10, Expiry::days_left( $this->now + 10 * DAY_IN_SECONDS + 5, $this->now ) );
		$this->assertSame( 0, Expiry::days_left( $this->now + 100, $this->now ) );
		$this->assertSame( -1, Expiry::days_left( $this->now - 100, $this->now ) );
	}

	public function test_extended_is_a_year_ahead_at_the_end_of_the_day() {
		$this->assertSame( '2027-11-08T23:59:59Z', Expiry::extended( $this->now ) );
	}
}
