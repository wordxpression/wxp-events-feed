<?php
/**
 * Plugin Name:       WXP Events Feed
 * Plugin URI:        https://wxpplugins.com/wxp-events-feed/
 * Description:       Show the coming events of another website that runs WXP Local Events, as a list with a block, a shortcode or an Elementor widget.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Wilko van der Ploeg
 * Author URI:        https://wxpplugins.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wxp-events-feed
 * Domain Path:       /languages
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed;

defined( 'ABSPATH' ) || exit;

/*
 * When the plugin cannot start, show an admin notice and stop. The plugin must never take the site
 * (or another plugin) down with a fatal error.
 */
if ( ! function_exists( __NAMESPACE__ . '\show_notice' ) ) {
	/**
	 * Shows a "plugin not loaded" notice to administrators.
	 *
	 * @param string $reason Reason key: php, duplicate or boot.
	 * @param string $detail Technical detail, only used for "boot".
	 */
	function show_notice( string $reason, string $detail = '' ): void {
		add_action(
			'admin_notices',
			static function () use ( $reason, $detail ): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				switch ( $reason ) {
					case 'php':
						$message = __( 'WXP Events Feed is not loaded: it needs PHP 8.1 or higher.', 'wxp-events-feed' );
						break;
					case 'duplicate':
						$message = __( 'WXP Events Feed is not loaded: another copy of this plugin is already active. Deactivate one of them.', 'wxp-events-feed' );
						break;
					default:
						/* translators: %s: technical error message */
						$message = sprintf( __( 'WXP Events Feed could not start: %s', 'wxp-events-feed' ), $detail );
				}
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
			}
		);
	}
}

// A second copy of the plugin (for example in another folder) would redeclare everything below.
if ( defined( __NAMESPACE__ . '\VERSION' ) ) {
	show_notice( 'duplicate' );
	return;
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	show_notice( 'php' );
	return;
}

const VERSION     = '0.1.0';
const PLUGIN_FILE = __FILE__;
const TEXT_DOMAIN = 'wxp-events-feed';

/*
 * PSR-4 autoloader for the plugin's own classes. Kept in-house so the plugin runs without Composer's vendor/
 * directory, which is a development-only folder.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

try {
	Plugin::instance()->boot();
} catch ( \Throwable $error ) {
	show_notice( 'boot', $error->getMessage() );
}

register_deactivation_hook(
	__FILE__,
	static function (): void {
		Plugin::deactivate();
	}
);
