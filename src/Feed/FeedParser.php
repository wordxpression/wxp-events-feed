<?php
/**
 * Reads and checks the answer of the events website.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Feed;

/**
 * Nothing in the answer is trusted. Text is reduced to plain text with a maximum length, links must be http(s) and
 * point to the events website, images must be http(s), dates must be real. An item that fails a check is skipped,
 * not repaired. The result only holds what the list needs.
 */
final class FeedParser {

	/**
	 * Highest feed version this plugin understands.
	 */
	public const SUPPORTED_VERSION = 1;

	/**
	 * Most items read from one answer, whatever the answer claims.
	 */
	private const MAX_ITEMS = 50;

	/**
	 * Parses an answer.
	 *
	 * @param string $body   Body of the response.
	 * @param string $source Normalised address of the events website.
	 * @return array{site: array{name: string, url: string}, items: array<int, array<string, mixed>>}
	 * @throws FeedException When the answer is not an event feed this plugin can use.
	 */
	public static function parse( string $body, string $source ): array {
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			throw new FeedException( FeedException::NOT_JSON );
		}
		if ( ! isset( $data['version'], $data['items'] ) || ! is_int( $data['version'] ) || ! is_array( $data['items'] ) ) {
			throw new FeedException( FeedException::NOT_A_FEED );
		}
		if ( $data['version'] > self::SUPPORTED_VERSION ) {
			throw new FeedException( FeedException::NEWER );
		}

		$host  = SourceUrl::host( $source );
		$items = array();
		foreach ( array_slice( array_values( $data['items'] ), 0, self::MAX_ITEMS ) as $raw ) {
			$item = is_array( $raw ) ? self::item( $raw, $host ) : null;
			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		$site = is_array( $data['site'] ?? null ) ? $data['site'] : array();
		return array(
			'site'  => array(
				'name' => self::text( $site['name'] ?? '', 100 ),
				'url'  => self::url( $site['url'] ?? '', $host ),
			),
			'items' => $items,
		);
	}

	/**
	 * Checks one item.
	 *
	 * @param array<string, mixed> $raw  Item as received.
	 * @param string               $host Host of the events website, without "www.".
	 * @return array<string, mixed>|null Null when the item cannot be shown.
	 */
	private static function item( array $raw, string $host ): ?array {
		$title = self::text( $raw['title'] ?? '', 200 );
		$url   = self::url( $raw['url'] ?? '', $host );
		$start = self::date( $raw['start'] ?? '' );
		if ( '' === $title || '' === $url || null === $start ) {
			return null;
		}
		$end = self::date( $raw['end'] ?? '' );

		$image      = is_array( $raw['image'] ?? null ) ? $raw['image'] : array();
		$categories = array();
		foreach ( is_array( $raw['categories'] ?? null ) ? array_slice( $raw['categories'], 0, 10 ) : array() as $category ) {
			if ( is_array( $category ) && '' !== self::text( $category['name'] ?? '', 60 ) ) {
				$categories[] = array(
					'name' => self::text( $category['name'], 60 ),
					'slug' => preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) ( $category['slug'] ?? '' ) ) ),
				);
			}
		}

		return array(
			'id'          => (int) ( $raw['id'] ?? 0 ),
			'key'         => preg_replace( '/[^0-9-]/', '', (string) ( $raw['key'] ?? '' ) ),
			'title'       => $title,
			'url'         => $url,
			'start'       => $start->format( 'Y-m-d\TH:i:sP' ),
			'end'         => null === $end ? '' : $end->format( 'Y-m-d\TH:i:sP' ),
			'date_label'  => self::text( $raw['date_label'] ?? '', 100 ),
			'time_label'  => self::text( $raw['time_label'] ?? '', 60 ),
			'description' => self::text( $raw['description'] ?? '', 300 ),
			'thumbnail'   => self::url( $image['thumbnail'] ?? '', null ),
			'medium'      => self::url( $image['medium'] ?? '', null ),
			'categories'  => $categories,
		);
	}

	/**
	 * Plain text: no tags, no control characters, one line of spaces, at most $max characters.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Most characters.
	 */
	public static function text( mixed $value, int $max ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = strip_tags( $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value ) ?? '';
		$value = trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' );
		return mb_strlen( $value ) > $max ? rtrim( mb_substr( $value, 0, $max - 1 ) ) . '…' : $value;
	}

	/**
	 * A web address that is safe to put in a link or an image: http(s), a host, no user name. When $host is given,
	 * the address must be on that host (a leading "www." does not count).
	 *
	 * @param mixed       $value Raw value.
	 * @param string|null $host  Required host, or null for any host.
	 * @return string The address, or an empty string.
	 */
	public static function url( mixed $value, ?string $host ): string {
		if ( ! is_string( $value ) || '' === $value || strlen( $value ) > 2000 || preg_match( '/[\s\x00-\x1f"<>\\\\]/', $value ) ) {
			return '';
		}
		$parts = parse_url( $value );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		if ( ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		if ( null !== $host && SourceUrl::host( $value ) !== $host ) {
			return '';
		}
		return $value;
	}

	/**
	 * A date and time with a time zone offset, such as 2030-05-17T20:00:00+02:00.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function date( mixed $value ): ?\DateTimeImmutable {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i:sP', $value );
		if ( false === $date || 0 !== (int) ( \DateTimeImmutable::getLastErrors()['warning_count'] ?? 0 ) ) {
			return null;
		}
		return $date;
	}
}
