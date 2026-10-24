<?php
/**
 * Plugin settings.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Settings;

use WXP\EventsFeed\Feed\SourceUrl;

/**
 * One option, edited under Settings → Events Feed.
 */
final class Settings {

	public const OPTION = 'wxp_events_feed_settings';

	public const GROUP = 'wxp_events_feed_settings_group';

	/**
	 * Smallest and largest cache lifetime, in minutes.
	 */
	public const MIN_MINUTES = 5;
	public const MAX_MINUTES = 1440;

	/**
	 * Default values.
	 *
	 * @return array<string, int|string>
	 */
	public static function defaults(): array {
		return array(
			'source_url'    => '',
			'cache_minutes' => 60,
			'updates'       => 1,
			'cache_version' => 1,
		);
	}

	/**
	 * A stored value, or the default.
	 *
	 * @param string $key Setting.
	 * @return int|string
	 */
	public static function get( string $key ): int|string {
		$defaults = self::defaults();
		$stored   = get_option( self::OPTION, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$value    = $stored[ $key ] ?? ( $defaults[ $key ] ?? '' );
		return is_int( $defaults[ $key ] ?? null ) ? (int) $value : (string) $value;
	}

	/**
	 * The address of the events website, or an empty string.
	 */
	public static function source_url(): string {
		return SourceUrl::normalize( (string) self::get( 'source_url' ) );
	}

	/**
	 * Seconds a fetched feed is used without asking the events website again.
	 */
	public static function cache_seconds(): int {
		return max( self::MIN_MINUTES, min( self::MAX_MINUTES, (int) self::get( 'cache_minutes' ) ) ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Cleans the submitted settings page. Keys that are not on the form keep their stored value.
	 *
	 * @param mixed $input Raw form data.
	 * @return array<string, int|string>
	 */
	public static function sanitize( mixed $input ): array {
		$input  = is_array( $input ) ? wp_unslash( $input ) : array();
		$stored = get_option( self::OPTION, array() );
		$out    = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );

		$source = SourceUrl::normalize( (string) ( $input['source_url'] ?? '' ) );
		if ( $source !== (string) $out['source_url'] ) {
			// Another website: everything cached belongs to the old one.
			$out['cache_version'] = (int) $out['cache_version'] + 1;
		}
		$out['source_url']    = $source;
		$out['cache_minutes'] = max( self::MIN_MINUTES, min( self::MAX_MINUTES, (int) ( $input['cache_minutes'] ?? self::defaults()['cache_minutes'] ) ) );
		$out['updates']       = empty( $input['updates'] ) ? 0 : 1;
		return $out;
	}

	/**
	 * Makes everything cached so far obsolete (the old copies expire by themselves).
	 */
	public static function clear_cache(): void {
		$stored = get_option( self::OPTION, array() );
		$stored = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );

		$stored['cache_version'] = (int) $stored['cache_version'] + 1;
		update_option( self::OPTION, $stored );
	}
}
