<?php
/**
 * Tests for Bot_Detector.
 *
 * @package The_Another\Plugin\Aucteeno_Geo_Tagging\Tests\Unit
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno_Geo_Tagging\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno_Geo_Tagging\Bot_Detector;

final class Bot_Detector_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		unset( $_SERVER['HTTP_USER_AGENT'] );
	}

	protected function tearDown(): void {
		unset( $_SERVER['HTTP_USER_AGENT'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_empty_user_agent_parameter_is_bot(): void {
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( '' ) );
	}

	public function test_missing_user_agent_server_var_is_bot(): void {
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot() );
	}

	public function test_googlebot_is_bot(): void {
		$ua       = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( $ua ) );
	}

	public function test_bingbot_is_bot(): void {
		$ua       = 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)';
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( $ua ) );
	}

	public function test_facebook_previewer_is_bot(): void {
		$ua       = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( $ua ) );
	}

	public function test_generic_crawler_is_bot(): void {
		$ua       = 'Mozilla/5.0 (compatible; SomeNewCrawler/1.0)';
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( $ua ) );
	}

	public function test_generic_spider_is_bot(): void {
		$ua       = 'Mozilla/5.0 (compatible; SomeSpider/1.0)';
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( $ua ) );
	}

	public function test_case_insensitive_matching(): void {
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( 'GOOGLEBOT/1.0' ) );
		$this->assertTrue( $detector->is_bot( 'GoogleBot/1.0' ) );
		$this->assertTrue( $detector->is_bot( 'googlebot/1.0' ) );
	}

	public function test_desktop_chrome_is_not_bot(): void {
		$ua       = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';
		$detector = new Bot_Detector();
		$this->assertFalse( $detector->is_bot( $ua ) );
	}

	public function test_desktop_firefox_is_not_bot(): void {
		$ua       = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:132.0) Gecko/20100101 Firefox/132.0';
		$detector = new Bot_Detector();
		$this->assertFalse( $detector->is_bot( $ua ) );
	}

	public function test_desktop_safari_is_not_bot(): void {
		$ua       = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';
		$detector = new Bot_Detector();
		$this->assertFalse( $detector->is_bot( $ua ) );
	}

	public function test_mobile_safari_is_not_bot(): void {
		$ua       = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
		$detector = new Bot_Detector();
		$this->assertFalse( $detector->is_bot( $ua ) );
	}

	public function test_user_agent_parameter_takes_precedence_over_server_var(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'googlebot/1.0';
		$detector                   = new Bot_Detector();
		$this->assertFalse(
			$detector->is_bot( 'Mozilla/5.0 Chrome/130' ),
			'Explicit param should win over $_SERVER'
		);
	}

	public function test_falls_back_to_server_var_when_parameter_null(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';
		$detector                   = new Bot_Detector();
		$this->assertTrue( $detector->is_bot() );
	}
}
