<?php
/**
 * The plugin: wires the parts together.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed;

use WXP\EventsFeed\Admin\SettingsPage;
use WXP\EventsFeed\Block\FeedBlock;
use WXP\EventsFeed\Elementor\ElementorIntegration;
use WXP\EventsFeed\Feed\FeedClient;
use WXP\EventsFeed\I18n\TextDomain;
use WXP\EventsFeed\Shortcode\FeedShortcode;
use WXP\EventsFeed\Update\GitHubUpdater;

/**
 * Entry point. The settings, the shortcode, the block and the Elementor widget all show the same list, made by
 * Shortcode\FeedShortcode from the data FeedClient fetched and cached.
 */
final class Plugin {

	/**
	 * Name of the cron event that refreshes a cached feed in the background.
	 */
	public const REFRESH_HOOK = 'wxp_ef_refresh';

	/**
	 * The one instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Returns the plugin.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks everything into WordPress.
	 */
	public function boot(): void {
		( new TextDomain() )->register();

		$client = new FeedClient();
		add_action( self::REFRESH_HOOK, array( $client, 'refresh' ), 10, 2 );

		$shortcode = new FeedShortcode( $client );
		$shortcode->register();
		( new FeedBlock( $shortcode ) )->register();
		( new ElementorIntegration() )->register();

		( new SettingsPage( $client ) )->register();
		( new GitHubUpdater() )->register();
	}

	/**
	 * Removes the scheduled refreshes when the plugin is deactivated.
	 */
	public static function deactivate(): void {
		wp_unschedule_hook( self::REFRESH_HOOK );
	}
}
