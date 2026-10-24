<?php
/**
 * Loads the plugin translations.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\I18n;

use const WXP\EventsFeed\PLUGIN_FILE;
use const WXP\EventsFeed\TEXT_DOMAIN;

/**
 * Loads the .mo files shipped in /languages. The plugin is not on WordPress.org, so it brings its own translations.
 */
final class TextDomain {

	/**
	 * Hooks the loader into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'load' ), 0 );
	}

	/**
	 * Loads the text domain.
	 */
	public function load(): void {
		load_plugin_textdomain( TEXT_DOMAIN, false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );
	}
}
