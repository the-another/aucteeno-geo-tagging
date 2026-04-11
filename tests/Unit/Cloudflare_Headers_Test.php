<?php
/**
 * Tests for Cloudflare_Headers.
 *
 * @package The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Tests\Unit
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Cloudflare_Headers;

final class Cloudflare_Headers_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// wp_unslash and sanitize_text_field are pass-throughs for tests.
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		// Reset CF headers between tests.
		unset( $_SERVER['HTTP_CF_IPCOUNTRY'], $_SERVER['HTTP_CF_REGION_CODE'] );
	}

	protected function tearDown(): void {
		unset( $_SERVER['HTTP_CF_IPCOUNTRY'], $_SERVER['HTTP_CF_REGION_CODE'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_country_returns_empty_when_header_missing(): void {
		$cf = new Cloudflare_Headers();
		$this->assertSame( '', $cf->get_country() );
	}

	public function test_get_country_returns_valid_two_letter_code(): void {
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
		$cf                           = new Cloudflare_Headers();
		$this->assertSame( 'US', $cf->get_country() );
	}

	public function test_get_country_normalizes_lowercase_to_uppercase(): void {
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'ca';
		$cf                           = new Cloudflare_Headers();
		$this->assertSame( 'CA', $cf->get_country() );
	}

	public function test_get_country_rejects_unknown_sentinels(): void {
		$cf = new Cloudflare_Headers();

		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'XX';
		$this->assertSame( '', $cf->get_country(), 'XX should be rejected' );

		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'T1';
		$this->assertSame( '', $cf->get_country(), 'T1 should be rejected' );
	}

	public function test_get_country_rejects_malformed_values(): void {
		$cf = new Cloudflare_Headers();

		$malformed = [ 'USA', 'U', '', '12', "' OR 1=1", 'US;DROP' ];
		foreach ( $malformed as $value ) {
			$_SERVER['HTTP_CF_IPCOUNTRY'] = $value;
			$this->assertSame( '', $cf->get_country(), "Malformed value '$value' should be rejected" );
		}
	}

	public function test_get_subdivision_returns_empty_when_country_empty(): void {
		$_SERVER['HTTP_CF_REGION_CODE'] = 'KS';
		$cf                             = new Cloudflare_Headers();
		$this->assertSame( '', $cf->get_subdivision( '' ) );
	}

	public function test_get_subdivision_returns_empty_when_region_header_missing(): void {
		$cf = new Cloudflare_Headers();
		$this->assertSame( '', $cf->get_subdivision( 'US' ) );
	}

	public function test_get_subdivision_returns_country_colon_region_format(): void {
		$_SERVER['HTTP_CF_REGION_CODE'] = 'KS';
		$cf                             = new Cloudflare_Headers();
		$this->assertSame( 'US:KS', $cf->get_subdivision( 'US' ) );
	}

	public function test_get_subdivision_normalizes_lowercase_region(): void {
		$_SERVER['HTTP_CF_REGION_CODE'] = 'ks';
		$cf                             = new Cloudflare_Headers();
		$this->assertSame( 'US:KS', $cf->get_subdivision( 'US' ) );
	}

	public function test_get_subdivision_rejects_malformed_region(): void {
		$cf = new Cloudflare_Headers();

		$malformed = [ 'KANSAS', "KS'", 'KS;DROP', '!', '1234' ];
		foreach ( $malformed as $value ) {
			$_SERVER['HTTP_CF_REGION_CODE'] = $value;
			$this->assertSame( '', $cf->get_subdivision( 'US' ), "Malformed region '$value' should be rejected" );
		}
	}
}
