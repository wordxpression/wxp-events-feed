<?php
/**
 * The block.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Block;

use WXP\EventsFeed\Shortcode\FeedShortcode;

use const WXP\EventsFeed\PLUGIN_FILE;
use const WXP\EventsFeed\TEXT_DOMAIN;

/**
 * Dynamic block (assets/block, built with `npm run build`). It only collects the settings; the output comes from
 * FeedShortcode::render(), the same code the shortcode and the Elementor widget use.
 */
final class FeedBlock {

	/**
	 * The category of the block inserter that all WXP plugins share.
	 */
	public const CATEGORY = 'wxp-plugins';

	private const SCRIPT = 'wxp-events-feed-block';

	/**
	 * The shortcode class that renders the list.
	 *
	 * @var FeedShortcode
	 */
	private FeedShortcode $shortcode;

	/**
	 * Constructor.
	 *
	 * @param FeedShortcode $shortcode Renderer.
	 */
	public function __construct( FeedShortcode $shortcode ) {
		$this->shortcode = $shortcode;
	}

	/**
	 * Hooks the registration.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_filter( 'block_categories_all', array( $this, 'add_category' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_style' ) );
	}

	/**
	 * Registers the script and the block. Skipped when the editor script has not been built.
	 */
	public function register_block(): void {
		$dir   = plugin_dir_path( PLUGIN_FILE ) . 'assets/block/';
		$asset = $dir . 'build/index.asset.php';
		if ( ! is_readable( $asset ) ) {
			return;
		}
		$meta = require $asset;
		wp_register_script( self::SCRIPT, plugins_url( 'assets/block/build/index.js', PLUGIN_FILE ), $meta['dependencies'], $meta['version'], true );
		wp_set_script_translations( self::SCRIPT, TEXT_DOMAIN, plugin_dir_path( PLUGIN_FILE ) . 'languages' );

		register_block_type( $dir . 'events-feed', array( 'render_callback' => array( $this, 'render' ) ) );
	}

	/**
	 * Adds the shared "WXP Plugins" category to the block inserter, unless another WXP plugin did.
	 *
	 * @param array<int, array<string, string>> $categories Block categories.
	 * @return array<int, array<string, string>>
	 */
	public function add_category( array $categories ): array {
		foreach ( $categories as $category ) {
			if ( self::CATEGORY === ( $category['slug'] ?? '' ) ) {
				return $categories;
			}
		}
		$categories[] = array(
			'slug'  => self::CATEGORY,
			'title' => 'WXP Plugins',
			'icon'  => null,
		);
		return $categories;
	}

	/**
	 * Gives the block editor the stylesheet, so the preview looks like the front end.
	 */
	public function enqueue_editor_style(): void {
		if ( is_admin() ) {
			wp_enqueue_style( FeedShortcode::STYLE );
		}
	}

	/**
	 * Renders the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render( array $attributes ): string {
		$html = $this->shortcode->render(
			array(
				'limit'       => $attributes['limit'] ?? null,
				'category'    => $attributes['category'] ?? null,
				'image'       => $this->flag( $attributes, 'image' ),
				'image_size'  => $attributes['imageSize'] ?? null,
				'description' => $this->flag( $attributes, 'description' ),
				'time'        => $this->flag( $attributes, 'time' ),
				'date'        => $attributes['dateFrom'] ?? null,
				'heading'     => $attributes['heading'] ?? null,
			)
		);
		if ( '' === $html ) {
			return '';
		}
		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- both parts are escaped where they are built.
	}

	/**
	 * A true/false attribute as the "yes"/"no" the shared options understand.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $name       Attribute.
	 */
	private function flag( array $attributes, string $name ): ?string {
		return array_key_exists( $name, $attributes ) ? ( $attributes[ $name ] ? 'yes' : 'no' ) : null;
	}
}
