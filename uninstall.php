<?php
/**
 * Runs when the plugin is deleted from the Plugins screen: removes the settings and everything cached.
 * The plugin stores nothing else; the events themselves live on the other website.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_unschedule_hook( 'wxp_ef_refresh' );

delete_option( 'wxp_events_feed_settings' );
delete_transient( 'wxp_ef_latest_release' );

global $wpdb;

// The cached lists and notices are transients with our prefix (and their timeout rows).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the table name is core's.
		$wpdb->esc_like( '_transient_wxp_ef_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wxp_ef_' ) . '%'
	)
);
