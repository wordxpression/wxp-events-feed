<?php
/**
 * Integration check of WXP Events Feed. Run it on a site where both WXP Local Events and this plugin are active:
 *
 *   wp eval-file wp-content/plugins/wxp-events-feed/tests/Integration/feed.php
 *
 * The site reads its own event feed over HTTP (the "other website" is this site). The script switches the feed on,
 * creates a few events of its own, and removes them again; settings and transients are backed up and restored.
 *
 * @package WXP\EventsFeed
 */

use WXP\EventsFeed\Feed\FeedClient;
use WXP\EventsFeed\Feed\FeedResult;
use WXP\EventsFeed\Settings\Settings;
use WXP\EventsFeed\Shortcode\FeedShortcode;
use WXP\LocalEvents\Meta\EventMeta;
use WXP\LocalEvents\PostType\Event;
use WXP\LocalEvents\Settings\Settings as EventSettings;

$ok  = 0;
$bad = 0;
$check = function ( string $label, bool $cond, string $extra = '' ) use ( &$ok, &$bad ) {
	if ( $cond ) {
		++$ok;
	} else {
		++$bad;
		echo "FAIL $label $extra\n";
	}
};

global $wpdb;
$count_events = static fn (): int => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", Event::SLUG ) );
$events_before = $count_events();
$backup_mine   = get_option( Settings::OPTION, null );
$backup_theirs = get_option( EventSettings::OPTION, null );
$created       = array();

// This site is "local": allow the request to its own address.
add_filter( 'http_request_host_is_external', '__return_true' );
add_filter( 'block_local_requests', '__return_false' );

$make = function ( string $title, int $days ) use ( &$created ) {
	$date = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( "+$days days" )->format( 'Y-m-d' );
	$id   = wp_insert_post(
		array(
			'post_type'   => Event::SLUG,
			'post_status' => 'publish',
			'post_title'  => $title,
			'meta_input'  => array(
				EventMeta::DATE       => $date,
				EventMeta::START_TIME => '20:00',
				EventMeta::END_TIME   => '22:00',
				EventMeta::TEASER     => 'About ' . $title,
			),
		)
	);
	$created[] = $id;
	return $id;
};
$clear_transients = static function () use ( $wpdb ) {
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wxp\_ef\_%' OR option_name LIKE '\_transient\_timeout\_wxp\_ef\_%'" );
};

try {
	$home = untrailingslashit( home_url() );
	$op   = array( 'feed_enabled' => 1 );
	update_option( EventSettings::OPTION, is_array( $backup_theirs ) ? array_merge( $backup_theirs, $op ) : $op );
	$make( 'EFtest Alpha', 1 );
	$make( 'EFtest Beta', 2 );
	$make( 'EFtest Gamma', 3 );
	$clear_transients();

	$client = new FeedClient();

	// Not configured.
	update_option( Settings::OPTION, array() );
	$r = $client->get( 5 );
	$check( 'not configured', FeedResult::ERROR === $r->status && 'not_configured' === $r->error );
	$check( 'not configured message', str_contains( $client->message( $r ), 'Events Feed' ) );

	// The settings clean the address.
	$clean = Settings::sanitize( array( 'source_url' => $home . '/wp-json/wxp-local-events/v1/feed?limit=3', 'cache_minutes' => 1 ) );
	$check( 'address cleaned', $home === $clean['source_url'], $clean['source_url'] );
	$check( 'minutes at least 5', 5 === $clean['cache_minutes'] );
	$check( 'changing the address bumps the cache version', $clean['cache_version'] > Settings::defaults()['cache_version'] );
	update_option( Settings::OPTION, $clean );

	// First fetch: over HTTP, no cache.
	$r = $client->get( 3 );
	$check( 'first fetch is fresh', FeedResult::FRESH === $r->status, $r->status . ' ' . $r->error . ' ' . $r->detail );
	$check( 'has items', count( $r->items ) >= 1 && count( $r->items ) <= 3, (string) count( $r->items ) );
	$titles = array_column( $r->items, 'title' );

	// Cached: the second call must not touch the network.
	$requests = 0;
	$count    = static function ( $pre ) use ( &$requests ) {
		++$requests;
		return $pre;
	};
	add_filter( 'pre_http_request', $count );
	$r2 = $client->get( 3 );
	remove_filter( 'pre_http_request', $count );
	$check( 'second call from the cache', 0 === $requests && FeedResult::FRESH === $r2->status && array_column( $r2->items, 'title' ) === $titles );

	// Only our events: use a limit above what the site has of other events? Check ours are listed with limit 20.
	$client->fetch( $home, 20 );
	$all = $client->get( 20 );
	$names = array_column( $all->items, 'title' );
	$check( 'our events are in the list', in_array( 'EFtest Alpha', $names, true ) && in_array( 'EFtest Gamma', $names, true ) );

	// Rendering.
	$shortcode = new FeedShortcode( $client );
	$html      = $shortcode->render( array( 'limit' => '20' ) );
	$check( 'html: wrapper and list', str_contains( $html, 'class="wxp-ef"' ) && str_contains( $html, '<ul class="wxp-ef__list">' ) );
	$check( 'html: new tab and noopener', str_contains( $html, 'target="_blank" rel="noopener"' ) );
	$check( 'html: screen reader hint', str_contains( $html, 'screen-reader-text' ) );
	$check( 'html: title shown', str_contains( $html, 'EFtest Alpha' ) );
	$check( 'html: description shown', str_contains( $html, 'About EFtest Alpha' ) );
	$check( 'html: time element', 1 === preg_match( '/<time class="wxp-ef__date" datetime="\d{4}-\d{2}-\d{2}T20:00:00[+-]\d{2}:\d{2}">[^<]*20:00[^<]*22:00/', $html ) || str_contains( $html, '8:00' ), substr( $html, 0, 0 ) );
	$check( 'html: link to the event', str_contains( $html, esc_url( get_permalink( $created[0] ) ) ) );
	$plain = $shortcode->render( array( 'limit' => '20', 'description' => 'no', 'image' => 'no', 'time' => 'no', 'heading' => 'h2', 'class' => 'my-class' ) );
	$check( 'options: no description', ! str_contains( $plain, 'About EFtest' ) );
	$check( 'options: no image', ! str_contains( $plain, 'wxp-ef__image' ) );
	$check( 'options: heading', str_contains( $plain, '<h2 class="wxp-ef__title">' ) );
	$check( 'options: class', str_contains( $plain, 'wxp-ef my-class' ) );
	$check( 'options: bad heading falls back', str_contains( $shortcode->render( array( 'limit' => '20', 'heading' => 'script' ) ), '<h3 class="wxp-ef__title">' ) );
	$check( 'source labels', str_contains( $shortcode->render( array( 'limit' => '20', 'date' => 'source' ) ), 'wxp-ef__date' ) );
	$check( 'style enqueued', wp_style_is( FeedShortcode::STYLE, 'enqueued' ) );

	// A category that has no events: an empty list, not an error.
	$none = $shortcode->render( array( 'category' => 'no-such-category' ) );
	$check( 'empty list message', str_contains( $none, 'wxp-ef__empty' ) );

	// The events website goes down: the last good copy is shown, and a refresh is scheduled.
	$break = static fn () => new WP_Error( 'http_request_failed', 'down' );
	$expire = static function () use ( $wpdb ) {
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wxp\_ef\_f\_%' OR option_name LIKE '\_transient\_timeout\_wxp\_ef\_f\_%'" );
		wp_cache_flush();
	};
	$expire();
	add_filter( 'pre_http_request', $break );
	$r3 = $client->get( 20 );
	$check( 'stale copy while down', FeedResult::STALE === $r3->status && count( $r3->items ) === count( $all->items ), $r3->status );
	$check( 'refresh scheduled', false !== wp_next_scheduled( \WXP\EventsFeed\Plugin::REFRESH_HOOK, array( 20, '' ) ) );
	// Running the refresh while down must not throw, and sets the back-off.
	$client->refresh( 20, '' );
	remove_filter( 'pre_http_request', $break );
	wp_clear_scheduled_hook( \WXP\EventsFeed\Plugin::REFRESH_HOOK, array( 20, '' ) );

	// With nothing cached and the website down: an error, then the back-off keeps the next views quick.
	$clear_transients();
	Settings::clear_cache();
	add_filter( 'pre_http_request', $break );
	$r4 = $client->get( 7 );
	$check( 'error without a copy', FeedResult::ERROR === $r4->status && 'unreachable' === $r4->error, $r4->error );
	$requests = 0;
	add_filter( 'pre_http_request', $count );
	$r5 = $client->get( 7 );
	remove_filter( 'pre_http_request', $count );
	remove_filter( 'pre_http_request', $break );
	$check( 'back-off: no new request', 0 === $requests && 'unavailable' === $r5->error, "$requests " . $r5->error );

	// Visitors see nothing on an error, administrators see why.
	wp_set_current_user( 0 );
	add_filter( 'pre_http_request', $break );
	$check( 'visitor sees nothing', '' === $shortcode->render( array( 'limit' => '9' ) ) );
	wp_set_current_user( 1 );
	$admin = $shortcode->render( array( 'limit' => '9' ) );
	remove_filter( 'pre_http_request', $break );
	$check( 'admin sees the reason', str_contains( $admin, 'wxp-ef--error' ) );
	wp_set_current_user( 0 );

	// Wrong answers.
	$clear_transients();
	Settings::clear_cache();
	$answer = static fn ( $body, $code = 200 ) => static fn () => array( 'response' => array( 'code' => $code, 'message' => '' ), 'body' => $body, 'headers' => array() );
	$try = function ( callable $filter, int $limit ) use ( $client, $home, $clear_transients ) {
		$clear_transients();
		add_filter( 'pre_http_request', $filter );
		$result = $client->fetch( $home, $limit );
		remove_filter( 'pre_http_request', $filter );
		return $result;
	};
	$check( 'html answer = not a feed', 'not_json' === $try( $answer( '<html>' ), 11 )->error );
	$check( 'json that is not a feed', 'not_a_feed' === $try( $answer( '{"a":1}' ), 12 )->error );
	$check( 'newer version', 'newer_version' === $try( $answer( '{"version":9,"items":[]}' ), 13 )->error );
	$check( 'status 500', 'http' === $try( $answer( 'oops', 500 ), 14 )->error );
	$check( 'sharing off', 'off' === $try( $answer( '{"code":"wxp_local_events_feed_off"}', 404 ), 15 )->error );
	$check( 'messages exist', '' !== $client->message( new FeedResult( FeedResult::ERROR, array(), 'off' ) ) );

	// Clearing the cache makes the next call fetch again.
	$client->fetch( $home, 4 );
	Settings::clear_cache();
	$requests = 0;
	add_filter( 'pre_http_request', $count );
	$client->get( 4 );
	remove_filter( 'pre_http_request', $count );
	$check( 'clear cache forces a new fetch', $requests >= 1 );

	// The updater, with a pretend GitHub.
	$release = static function ( string $tag ) {
		return static function ( $pre, $args, $url ) use ( $tag ) {
			if ( ! str_contains( $url, 'api.github.com/repos/' ) ) {
				return $pre;
			}
			$body = array(
				'tag_name' => $tag,
				'html_url' => 'https://github.com/wordxpression/wxp-events-feed/releases/tag/' . $tag,
				'body'     => 'Notes',
				'assets'   => array( array( 'name' => 'wxp-events-feed-' . ltrim( $tag, 'v' ) . '.zip', 'browser_download_url' => 'https://github.com/wordxpression/wxp-events-feed/releases/download/' . $tag . '/wxp-events-feed-' . ltrim( $tag, 'v' ) . '.zip' ) ),
			);
			return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => wp_json_encode( $body ), 'headers' => array() );
		};
	};
	$basename = plugin_basename( WP_PLUGIN_DIR . '/wxp-events-feed/wxp-events-feed.php' );
	$updater  = new \WXP\EventsFeed\Update\GitHubUpdater();
	$run      = function ( string $tag ) use ( $release, $updater ) {
		delete_transient( 'wxp_ef_latest_release' );
		$filter = $release( $tag );
		add_filter( 'pre_http_request', $filter, 10, 3 );
		$transient = $updater->check( (object) array( 'response' => array(), 'no_update' => array() ) );
		remove_filter( 'pre_http_request', $filter, 10 );
		return $transient;
	};
	update_option( Settings::OPTION, array( 'updates' => 1 ) );
	$t = $run( 'v9.9.9' );
	$check( 'updater offers a newer release', isset( $t->response[ $basename ] ) && '9.9.9' === $t->response[ $basename ]->new_version && str_ends_with( $t->response[ $basename ]->package, 'wxp-events-feed-9.9.9.zip' ), wp_json_encode( $t ) );
	$t = $run( 'v' . \WXP\EventsFeed\VERSION );
	$check( 'no update for the same version', ! isset( $t->response[ $basename ] ) && isset( $t->no_update[ $basename ] ) );
	update_option( Settings::OPTION, array( 'updates' => 0 ) );
	$t = $run( 'v9.9.9' );
	$check( 'updates switched off: nothing added', array() === $t->response );
	update_option( Settings::OPTION, array( 'updates' => 1 ) );
	delete_transient( 'wxp_ef_latest_release' );
	$down = static fn ( $pre, $args, $url ) => str_contains( $url, 'api.github.com' ) ? new WP_Error( 'http_request_failed', 'down' ) : $pre;
	add_filter( 'pre_http_request', $down, 10, 3 );
	$t = $updater->check( (object) array( 'response' => array(), 'no_update' => array() ) );
	remove_filter( 'pre_http_request', $down, 10 );
	$check( 'GitHub down: no update, no error', array() === $t->response );
	$info = $updater->information( false, 'plugin_information', (object) array( 'slug' => 'other-plugin' ) );
	$check( 'details popup ignores other plugins', false === $info );

	// Sharing off at the source: the plugin says so.
	update_option( EventSettings::OPTION, array( 'feed_enabled' => 0 ) );
	$clear_transients();
	$off = $client->fetch( $home, 6 );
	$check( 'real sharing-off answer', 'off' === $off->error, $off->error . ' ' . $off->detail );
} finally {
	foreach ( $created as $id ) {
		wp_delete_post( $id, true );
	}
	$clear_transients();
	wp_clear_scheduled_hook( \WXP\EventsFeed\Plugin::REFRESH_HOOK );
	null === $backup_mine ? delete_option( Settings::OPTION ) : update_option( Settings::OPTION, $backup_mine );
	null === $backup_theirs ? delete_option( EventSettings::OPTION ) : update_option( EventSettings::OPTION, $backup_theirs );
}

$check( 'events back to the start', $events_before === $count_events() );
$check( 'my settings restored', $backup_mine === get_option( Settings::OPTION, null ) );
$check( 'event settings restored', $backup_theirs === get_option( EventSettings::OPTION, null ) );
echo "events-feed: $ok ok, $bad failed\n";
