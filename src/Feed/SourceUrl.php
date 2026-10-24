<?php
/**
 * The address of the website that publishes the events.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Feed;

/**
 * Pure helpers for the source address: cleaning what the admin typed, and the addresses of the feed.
 */
final class SourceUrl {

	/**
	 * Path of the feed route on the events website.
	 */
	public const ROUTE = '/wxp-local-events/v1/feed';

	/**
	 * Cleans the address of the events website: http or https, no query, no trailing slash, and without the
	 * `/wp-json/...` part when someone pasted the address of the feed itself.
	 *
	 * @param string $value What was typed.
	 * @return string The address, or an empty string when it is not usable.
	 */
	public static function normalize( string $value ): string {
		$value = trim( $value );
		if ( '' === $value || preg_match( '/[\s\x00-\x1f]/', $value ) ) {
			return '';
		}
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $value ) ) {
			$value = 'https://' . $value;
		}
		$parts = parse_url( $value );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}
		$host = strtolower( (string) $parts['host'] );
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $host ) && ! str_starts_with( $host, '[' ) ) {
			return '';
		}

		$path = (string) ( $parts['path'] ?? '' );
		$cut  = strpos( $path, '/wp-json' );
		if ( false !== $cut ) {
			$path = substr( $path, 0, $cut );
		}
		$path = rtrim( $path, '/' );

		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return $scheme . '://' . $host . $port . $path;
	}

	/**
	 * The host of an address without a leading "www.", for comparing the host of a link with the source.
	 *
	 * @param string $url Address.
	 */
	public static function host( string $url ): string {
		$host = parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			return '';
		}
		$host = strtolower( $host );
		return str_starts_with( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/**
	 * The addresses to try for the feed: the pretty one, then the one that works without pretty permalinks.
	 *
	 * @param string               $source Normalised address of the events website.
	 * @param array<string, mixed> $query  Query arguments (limit, category).
	 * @return string[]
	 */
	public static function feed_urls( string $source, array $query ): array {
		$args = http_build_query( array_filter( $query, static fn ( $value ): bool => '' !== $value && null !== $value ), '', '&', PHP_QUERY_RFC3986 );
		return array(
			$source . '/wp-json' . self::ROUTE . ( '' !== $args ? '?' . $args : '' ),
			$source . '/?rest_route=' . self::ROUTE . ( '' !== $args ? '&' . $args : '' ),
		);
	}
}
