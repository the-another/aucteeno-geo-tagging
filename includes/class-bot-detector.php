<?php
/**
 * Bot Detector
 *
 * User-Agent heuristic for detecting crawlers, previewers, and headless browsers.
 *
 * @package Aucteeno_Geo_Tagging
 * @since 0.1.0
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno_Geo_Tagging;

/**
 * Detects bot traffic based on a curated list of User-Agent substrings.
 */
final class Bot_Detector {

	/**
	 * Bot UA substrings. Case-insensitive match.
	 *
	 * Order matters only for micro-performance — most common patterns first.
	 *
	 * @var string[]
	 */
	private const BOT_PATTERNS = array(
		'bot',
		'crawler',
		'spider',
		'slurp',
		'googlebot',
		'bingbot',
		'yandexbot',
		'duckduckbot',
		'baiduspider',
		'facebookexternalhit',
		'twitterbot',
		'linkedinbot',
		'pinterestbot',
		'whatsapp',
		'telegrambot',
		'discordbot',
		'slackbot',
		'applebot',
		'petalbot',
		'semrushbot',
		'ahrefsbot',
		'headlesschrome',
		'phantomjs',
		'puppeteer',
		'playwright',
	);

	/**
	 * Determine whether the given User-Agent (or the one in $_SERVER) is a bot.
	 *
	 * @param string|null $user_agent Optional explicit UA. If null, falls back to $_SERVER['HTTP_USER_AGENT'].
	 *                                Missing/empty UA is treated as bot (safer default for curl, scripts).
	 * @return bool True if the UA matches a known bot pattern or is empty.
	 */
	public function is_bot( ?string $user_agent = null ): bool {
		if ( null === $user_agent ) {
			// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__ -- UA read is intentional; used only for bot-pattern comparison, never cached or output.
			if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) || ! is_string( $_SERVER['HTTP_USER_AGENT'] ) ) {
				$user_agent = '';
			} else {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__ -- sanitized inline via sanitize_text_field; UA used only for bot-pattern comparison.
				$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
			}
		}
		if ( '' === $user_agent ) {
			return true; // No UA → treat as bot.
		}
		$ua_lower = strtolower( $user_agent );
		foreach ( self::BOT_PATTERNS as $pattern ) {
			if ( false !== strpos( $ua_lower, $pattern ) ) {
				return true;
			}
		}
		return false;
	}
}
