<?php
/**
 * Cloudflare Headers Reader
 *
 * Reads and validates Cloudflare geo-location headers from $_SERVER.
 *
 * @package Aucteeno_Nexus_Geo_Tagging
 * @since 0.1.0
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging;

/**
 * Reads and validates Cloudflare geo headers.
 */
final class Cloudflare_Headers {

	/**
	 * Get the visitor's Cloudflare-detected country as an ISO 3166-1 alpha-2 code.
	 *
	 * @return string Two-letter uppercase country code, or empty string if unavailable/invalid.
	 */
	public function get_country(): string {
		if ( ! isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) || ! is_string( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized inline via sanitize_text_field.
		$value = strtoupper( trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) ) );
		if ( ! preg_match( '/^[A-Z]{2}$/', $value ) ) {
			return '';
		}
		// Reject Cloudflare's "unknown" sentinels.
		if ( in_array( $value, array( 'XX', 'T1' ), true ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Get the visitor's Cloudflare-detected subdivision as "COUNTRY:REGION".
	 *
	 * @param string $country Two-letter country code (must be non-empty).
	 * @return string "US:KS"-style subdivision, or empty string if unavailable/invalid.
	 */
	public function get_subdivision( string $country ): string {
		if ( '' === $country ) {
			return '';
		}
		if ( ! isset( $_SERVER['HTTP_CF_REGION_CODE'] ) || ! is_string( $_SERVER['HTTP_CF_REGION_CODE'] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized inline via sanitize_text_field.
		$value = strtoupper( trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_REGION_CODE'] ) ) ) );
		if ( ! preg_match( '/^[A-Z0-9]{1,3}$/', $value ) ) {
			return '';
		}
		return $country . ':' . $value;
	}
}
