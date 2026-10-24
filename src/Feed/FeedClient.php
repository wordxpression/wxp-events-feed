<?php
/**
 * Fetches the feed of the events website and keeps it in the cache.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Feed;

use WXP\EventsFeed\Plugin;
use WXP\EventsFeed\Settings\Settings;

use const WXP\EventsFeed\VERSION;

/**
 * Visitors never wait for the events website, except the very first time:
 *
 * - a copy that is still fresh is used as it is (transient, lifetime from the settings);
 * - when it has expired, the last good copy (kept for a week) is shown and a background job fetches a new one;
 * - when there is no copy at all, the feed is fetched while the page is built, with a short time-out. If that
 *   fails, the failure is remembered for five minutes so a website that is down does not slow every page.
 */
final class FeedClient {

	/**
	 * Seconds the last good copy is kept.
	 */
	private const KEEP = WEEK_IN_SECONDS;

	/**
	 * Seconds to leave a failing website alone.
	 */
	private const BACKOFF = 5 * MINUTE_IN_SECONDS;

	/**
	 * The items for a list.
	 *
	 * @param int    $limit    Most items (1 to 20).
	 * @param string $category Category slugs, comma separated; empty for all.
	 */
	public function get( int $limit, string $category = '' ): FeedResult {
		$source = Settings::source_url();
		if ( '' === $source ) {
			return new FeedResult( FeedResult::ERROR, array(), 'not_configured' );
		}

		$key   = $this->key( $source, $limit, $category );
		$fresh = get_transient( 'wxp_ef_f_' . $key );
		if ( is_array( $fresh ) ) {
			return new FeedResult( FeedResult::FRESH, (array) $fresh['items'], '', (int) $fresh['fetched'] );
		}

		$last = get_transient( 'wxp_ef_l_' . $key );
		if ( is_array( $last ) ) {
			if ( false === get_transient( 'wxp_ef_b_' . $key ) ) {
				wp_schedule_single_event( time(), Plugin::REFRESH_HOOK, array( $limit, $category ) );
			}
			return new FeedResult( FeedResult::STALE, (array) $last['items'], '', (int) $last['fetched'] );
		}

		if ( false !== get_transient( 'wxp_ef_b_' . $key ) ) {
			return new FeedResult( FeedResult::ERROR, array(), 'unavailable' );
		}
		return $this->fetch( $source, $limit, $category );
	}

	/**
	 * Fetches a list again and stores it. Used by the background job.
	 *
	 * @param int    $limit    Most items.
	 * @param string $category Category slugs.
	 */
	public function refresh( int $limit, string $category = '' ): void {
		$source = Settings::source_url();
		if ( '' !== $source ) {
			$this->fetch( $source, max( 1, min( 20, $limit ) ), $category );
		}
	}

	/**
	 * Asks the events website now, and stores the answer. The settings page uses this to test the connection.
	 *
	 * @param string $source   Normalised address of the events website.
	 * @param int    $limit    Most items.
	 * @param string $category Category slugs.
	 */
	public function fetch( string $source, int $limit, string $category = '' ): FeedResult {
		$key    = $this->key( $source, $limit, $category );
		$result = $this->request( $source, $limit, $category );

		if ( $result->usable() ) {
			$payload = array(
				'items'   => $result->items,
				'fetched' => $result->fetched,
			);
			set_transient( 'wxp_ef_f_' . $key, $payload, Settings::cache_seconds() );
			set_transient( 'wxp_ef_l_' . $key, $payload, self::KEEP );
			delete_transient( 'wxp_ef_b_' . $key );
		} else {
			set_transient( 'wxp_ef_b_' . $key, 1, self::BACKOFF );
		}
		return $result;
	}

	/**
	 * A sentence that says what went wrong.
	 *
	 * @param FeedResult $result A result with an error.
	 */
	public function message( FeedResult $result ): string {
		switch ( $result->error ) {
			case 'not_configured':
				return __( 'No events website is set yet. Fill in its address under Settings → Events Feed.', 'wxp-events-feed' );
			case 'unreachable':
				/* translators: %s: technical reason */
				return sprintf( __( 'The events website could not be reached (%s).', 'wxp-events-feed' ), $result->detail );
			case 'http':
				/* translators: %s: HTTP status code */
				return sprintf( __( 'The events website answered with status %s.', 'wxp-events-feed' ), $result->detail );
			case 'off':
				return __( 'The events website has not switched on sharing. On that website, go to Events → Settings → Share events.', 'wxp-events-feed' );
			case FeedException::NOT_JSON:
			case FeedException::NOT_A_FEED:
				return __( 'The address does not give an event feed. Is WXP Local Events (version 0.14 or newer) active on that website?', 'wxp-events-feed' );
			case FeedException::NEWER:
				return __( 'The events website uses a newer feed than this plugin understands. Update WXP Events Feed.', 'wxp-events-feed' );
			case 'unavailable':
				return __( 'The events website did not answer a moment ago. It is tried again soon.', 'wxp-events-feed' );
			default:
				return __( 'The events could not be loaded.', 'wxp-events-feed' );
		}
	}

	/**
	 * Does the request: the pretty address first, the plain one when that gives nothing usable.
	 *
	 * @param string $source   Address of the events website.
	 * @param int    $limit    Most items.
	 * @param string $category Category slugs.
	 */
	private function request( string $source, int $limit, string $category ): FeedResult {
		$args = array(
			'timeout'             => 5,
			'redirection'         => 3,
			'limit_response_size' => 512 * KB_IN_BYTES,
			'user-agent'          => 'WXP Events Feed/' . VERSION . '; ' . home_url( '/' ),
			'headers'             => array( 'Accept' => 'application/json' ),
		);

		$last = new FeedResult( FeedResult::ERROR, array(), 'unreachable' );
		foreach ( SourceUrl::feed_urls(
			$source,
			array(
				'limit'    => $limit,
				'category' => $category,
			)
		) as $url ) {
			$response = wp_safe_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				$last = new FeedResult( FeedResult::ERROR, array(), 'unreachable', 0, $response->get_error_message() );
				continue;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$body   = (string) wp_remote_retrieve_body( $response );
			if ( 200 !== $status ) {
				$data = json_decode( $body, true );
				if ( is_array( $data ) && 'wxp_local_events_feed_off' === ( $data['code'] ?? '' ) ) {
					// The route exists but sharing is off: the other address would say the same.
					return new FeedResult( FeedResult::ERROR, array(), 'off' );
				}
				$last = new FeedResult( FeedResult::ERROR, array(), 'http', 0, (string) $status );
				continue;
			}

			try {
				$parsed = FeedParser::parse( $body, $source );
			} catch ( FeedException $error ) {
				$last = new FeedResult( FeedResult::ERROR, array(), $error->reason() );
				continue;
			}
			return new FeedResult( FeedResult::FRESH, array_slice( $parsed['items'], 0, $limit ), '', time() );
		}
		return $last;
	}

	/**
	 * The cache key of a list.
	 *
	 * @param string $source   Address of the events website.
	 * @param int    $limit    Most items.
	 * @param string $category Category slugs.
	 */
	private function key( string $source, int $limit, string $category ): string {
		return md5( $source . '|' . $limit . '|' . $category . '|' . Settings::get( 'cache_version' ) );
	}
}
