<?php
/**
 * The shortcode, and the one place that builds the list for every output.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Shortcode;

use WXP\EventsFeed\Feed\FeedClient;
use WXP\EventsFeed\Render\ListRenderer;

use const WXP\EventsFeed\PLUGIN_FILE;
use const WXP\EventsFeed\VERSION;

/**
 * `[wxp_events_feed limit="5" category="music" image="yes" description="yes" time="yes" date="site" heading="h3"]`.
 * The block and the Elementor widget hand their settings to render(), so all three show the same markup.
 */
final class FeedShortcode {

	public const TAG = 'wxp_events_feed';

	public const STYLE = 'wxp-events-feed';

	/**
	 * The client.
	 *
	 * @var FeedClient
	 */
	private FeedClient $client;

	/**
	 * Constructor.
	 *
	 * @param FeedClient $client Client.
	 */
	public function __construct( FeedClient $client ) {
		$this->client = $client;
	}

	/**
	 * Hooks the shortcode and the stylesheet.
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'shortcode' ) );
		add_action( 'init', array( self::class, 'register_style' ) );
	}

	/**
	 * Registers the stylesheet; it loads only on pages that show a list.
	 */
	public static function register_style(): void {
		wp_register_style( self::STYLE, plugins_url( 'assets/css/events-feed.css', PLUGIN_FILE ), array(), VERSION );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public function shortcode( $atts ): string {
		return $this->render( is_array( $atts ) ? $atts : array() );
	}

	/**
	 * The settings every output understands, with their defaults.
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return array(
			'limit'       => '5',
			'category'    => '',
			'image'       => 'yes',
			'image_size'  => 'thumbnail',
			'description' => 'yes',
			'time'        => 'yes',
			'date'        => 'site',
			'heading'     => 'h3',
			'class'       => '',
		);
	}

	/**
	 * Checks the settings of one list.
	 *
	 * @param array<string, mixed> $atts Settings as given.
	 * @return array{limit: int, category: string, image: bool, image_size: string, description: bool, time: bool, date: string, heading: string, class: string}
	 */
	public static function options( array $atts ): array {
		$atts = array_merge( self::defaults(), array_filter( $atts, static fn ( $value ): bool => null !== $value && '' !== $value ) );
		$bool = static fn ( $value ): bool => ! in_array( strtolower( (string) $value ), array( 'no', 'false', '0', 'off', '' ), true );

		$slugs = array_filter( array_map( 'sanitize_title', explode( ',', (string) $atts['category'] ) ) );
		return array(
			'limit'       => max( 1, min( 20, (int) $atts['limit'] ) ),
			'category'    => implode( ',', $slugs ),
			'image'       => $bool( $atts['image'] ),
			'image_size'  => 'medium' === $atts['image_size'] ? 'medium' : 'thumbnail',
			'description' => $bool( $atts['description'] ),
			'time'        => $bool( $atts['time'] ),
			'date'        => 'source' === $atts['date'] ? 'source' : 'site',
			'heading'     => in_array( $atts['heading'], array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ), true ) ? (string) $atts['heading'] : 'h3',
			'class'       => trim( (string) preg_replace( '/[^A-Za-z0-9_ -]/', '', (string) $atts['class'] ) ),
		);
	}

	/**
	 * Builds the list.
	 *
	 * @param array<string, mixed> $atts Settings.
	 */
	public function render( array $atts ): string {
		$options = self::options( $atts );
		$result  = $this->client->get( $options['limit'], $options['category'] );

		if ( ! $result->usable() ) {
			// Visitors see nothing; people who can fix it see why.
			if ( current_user_can( 'manage_options' ) ) {
				return sprintf(
					'<div class="wxp-ef wxp-ef--error"><p><strong>WXP Events Feed:</strong> %s</p></div>',
					esc_html( $this->client->message( $result ) )
				);
			}
			return '';
		}

		wp_enqueue_style( self::STYLE );
		return ( new ListRenderer() )->render( $result->items, $options );
	}
}
