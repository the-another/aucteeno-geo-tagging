<?php
/**
 * Tests for Geo_Tagging::filter_location().
 *
 * Uses real Cloudflare_Headers and Bot_Detector instances driven by $_SERVER,
 * since both classes are pure and trivially controllable via the superglobal.
 *
 * @package The_Another\Plugin\Aucteeno_Geo_Tagging\Tests\Unit
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno_Geo_Tagging\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno_Geo_Tagging\Bot_Detector;
use The_Another\Plugin\Aucteeno_Geo_Tagging\Cloudflare_Headers;
use The_Another\Plugin\Aucteeno_Geo_Tagging\Geo_Tagging;

final class Geo_Tagging_Test extends TestCase {

	private Geo_Tagging $subject;

	/**
	 * Minimal stub for WP_Block — the filter signature requires a WP_Block instance
	 * but filter_location() does not read from it.
	 */
	private object $block_stub;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();

		unset(
			$_SERVER['HTTP_CF_IPCOUNTRY'],
			$_SERVER['HTTP_CF_REGION_CODE'],
			$_SERVER['HTTP_USER_AGENT']
		);

		$this->subject    = new Geo_Tagging( new Cloudflare_Headers(), new Bot_Detector() );
		$this->block_stub = new \stdClass();
	}

	protected function tearDown(): void {
		unset(
			$_SERVER['HTTP_CF_IPCOUNTRY'],
			$_SERVER['HTTP_CF_REGION_CODE'],
			$_SERVER['HTTP_USER_AGENT']
		);
		Monkey\tearDown();
		parent::tearDown();
	}

	private function human_ua(): void {
		$_SERVER['HTTP_USER_AGENT'] =
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';
	}

	private function bot_ua(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';
	}

	public function test_feature_disabled_returns_input_unchanged(): void {
		$this->human_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';

		$result = $this->subject->filter_location(
			array( '', '' ),
			array( 'geoTaggingEnabled' => false ),
			$this->block_stub
		);

		$this->assertSame( array( '', '' ), $result );
	}

	public function test_bot_without_opt_in_returns_input_unchanged(): void {
		$this->bot_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';

		$result = $this->subject->filter_location(
			array( 'CA', '' ),
			array(
				'geoTaggingEnabled'     => true,
				'geoTaggingAffectsBots' => false,
			),
			$this->block_stub
		);

		$this->assertSame( array( 'CA', '' ), $result );
	}

	public function test_bot_with_opt_in_applies_cf(): void {
		$this->bot_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY']   = 'US';
		$_SERVER['HTTP_CF_REGION_CODE'] = 'KS';

		$result = $this->subject->filter_location(
			array( '', '' ),
			array(
				'geoTaggingEnabled'     => true,
				'geoTaggingAffectsBots' => true,
			),
			$this->block_stub
		);

		$this->assertSame( array( 'US', 'US:KS' ), $result );
	}

	public function test_human_enabled_cf_wins_over_input(): void {
		$this->human_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY']   = 'US';
		$_SERVER['HTTP_CF_REGION_CODE'] = 'KS';

		$result = $this->subject->filter_location(
			array( 'CA', 'CA:ON' ),
			array( 'geoTaggingEnabled' => true ),
			$this->block_stub
		);

		$this->assertSame( array( 'US', 'US:KS' ), $result );
	}

	public function test_human_enabled_cf_country_empty_returns_input(): void {
		$this->human_ua();
		// No HTTP_CF_IPCOUNTRY set.

		$result = $this->subject->filter_location(
			array( 'CA', 'CA:ON' ),
			array( 'geoTaggingEnabled' => true ),
			$this->block_stub
		);

		$this->assertSame( array( 'CA', 'CA:ON' ), $result );
	}

	public function test_cf_country_present_subdivision_empty_returns_country_only(): void {
		$this->human_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
		// No region code header.

		$result = $this->subject->filter_location(
			array( '', '' ),
			array( 'geoTaggingEnabled' => true ),
			$this->block_stub
		);

		$this->assertSame( array( 'US', '' ), $result );
	}

	public function test_cf_sentinel_country_returns_input(): void {
		$this->human_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'XX';

		$result = $this->subject->filter_location(
			array( 'CA', 'CA:ON' ),
			array( 'geoTaggingEnabled' => true ),
			$this->block_stub
		);

		$this->assertSame( array( 'CA', 'CA:ON' ), $result );
	}

	public function test_affects_bots_missing_attribute_treats_as_false(): void {
		$this->bot_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';

		$result = $this->subject->filter_location(
			array( 'CA', '' ),
			array( 'geoTaggingEnabled' => true ), // no geoTaggingAffectsBots key.
			$this->block_stub
		);

		$this->assertSame( array( 'CA', '' ), $result );
	}

	public function test_enabled_attribute_missing_treats_as_false(): void {
		$this->human_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';

		$result = $this->subject->filter_location(
			array( 'CA', '' ),
			array(), // no geoTaggingEnabled key.
			$this->block_stub
		);

		$this->assertSame( array( 'CA', '' ), $result );
	}

	public function test_init_registers_filter_and_action(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'aucteeno_query_loop_location', array( $this->subject, 'filter_location' ), 10, 3 );
		Functions\expect( 'add_action' )
			->once()
			->with( 'enqueue_block_editor_assets', array( $this->subject, 'enqueue_editor_assets' ) );

		$this->subject->init();

		// Brain Monkey's Functions\expect assertions are verified in Monkey\tearDown(),
		// which PHPUnit doesn't count as regular assertions. Add two explicit assertion
		// count increments so the test is not flagged as risky by failOnRisky="true".
		$this->addToAssertionCount( 2 );
	}
}
