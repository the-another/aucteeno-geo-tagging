<?php
/**
 * Geo-Tagging filter wiring.
 *
 * Composes Cloudflare_Headers and Bot_Detector to produce a filter callback
 * for aucteeno_query_loop_location. Owns the block-editor asset enqueue hook.
 *
 * @package Aucteeno_Geo_Tagging
 * @since 0.1.0
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno_Geo_Tagging;

/**
 * Wires the aucteeno_query_loop_location filter and the editor JS enqueue.
 */
final class Geo_Tagging {

	/**
	 * Cloudflare header reader.
	 *
	 * @var Cloudflare_Headers
	 */
	private Cloudflare_Headers $cf;

	/**
	 * Bot detector.
	 *
	 * @var Bot_Detector
	 */
	private Bot_Detector $bot_detector;

	/**
	 * Constructor.
	 *
	 * @param Cloudflare_Headers $cf           Cloudflare header reader.
	 * @param Bot_Detector       $bot_detector Bot detector.
	 */
	public function __construct( Cloudflare_Headers $cf, Bot_Detector $bot_detector ) {
		$this->cf           = $cf;
		$this->bot_detector = $bot_detector;
	}

	/**
	 * Register the filter callback and asset enqueue action.
	 */
	public function init(): void {
		add_filter(
			'aucteeno_query_loop_location',
			array( $this, 'filter_location' ),
			10,
			3
		);
		add_action(
			'enqueue_block_editor_assets',
			array( $this, 'enqueue_editor_assets' )
		);
	}

	/**
	 * Filter callback for aucteeno_query_loop_location.
	 *
	 * Note: $block is typed as mixed (not \WP_Block) deliberately. In production
	 * the base aucteeno plugin always passes a \WP_Block instance, but typing it
	 * here would force every unit test to construct a real WP_Block, which pulls
	 * in WordPress core. Since this method never reads from $block, mixed is
	 * strictly better — it lets tests pass \stdClass() as a stand-in.
	 *
	 * @param array $location   Two-element indexed array [ string $country, string $subdivision ].
	 * @param array $attributes Block attributes.
	 * @param mixed $block      The block instance (WP_Block in production). Unused.
	 * @return array Same shape as $location.
	 */
	public function filter_location( array $location, array $attributes, $block ): array {
		unset( $block ); // Signature only.

		// 1. Feature must be explicitly enabled on this block.
		if ( empty( $attributes['geoTaggingEnabled'] ) ) {
			return $location;
		}

		// 2. Bot gate: if visitor is a bot and bots are not opted in, skip.
		$affects_bots = ! empty( $attributes['geoTaggingAffectsBots'] );
		if ( ! $affects_bots && $this->bot_detector->is_bot() ) {
			return $location;
		}

		// 3. Read CF headers. Empty country → feature silently no-ops.
		$country = $this->cf->get_country();
		if ( '' === $country ) {
			return $location;
		}
		$subdivision = $this->cf->get_subdivision( $country );

		// 4. CF wins (§10, decision 1). Return the new values; base re-sanitizes.
		return array( $country, $subdivision );
	}

	/**
	 * Enqueue the block editor inspector script.
	 *
	 * Loads the built JS from dist/ with auto-resolved dependencies from the
	 * wp-scripts build pipeline. Silently no-ops if the build artifact
	 * is missing (e.g., plugin installed without running `npm run build`).
	 */
	public function enqueue_editor_assets(): void {
		$asset_path = AUCTEENO_GEO_TAGGING_PLUGIN_DIR . 'dist/query-loop-inspector.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = require $asset_path;
		wp_enqueue_script(
			'aucteeno-geo-tagging-inspector',
			AUCTEENO_GEO_TAGGING_PLUGIN_URL . 'dist/query-loop-inspector.js',
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? AUCTEENO_GEO_TAGGING_VERSION,
			true
		);

		wp_set_script_translations(
			'aucteeno-geo-tagging-inspector',
			'aucteeno-geo-tagging'
		);
	}
}
