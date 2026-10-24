<?php
/**
 * An answer that cannot be used as an event feed.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Feed;

/**
 * Thrown by FeedParser. The code says what is wrong, so the settings page can explain it in the user's language.
 */
final class FeedException extends \RuntimeException {

	public const NOT_JSON   = 'not_json';
	public const NOT_A_FEED = 'not_a_feed';
	public const NEWER      = 'newer_version';

	/**
	 * Machine-readable reason.
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * Constructor.
	 *
	 * @param string $reason One of the constants.
	 */
	public function __construct( string $reason ) {
		parent::__construct( $reason );
		$this->reason = $reason;
	}

	/**
	 * The reason.
	 */
	public function reason(): string {
		return $this->reason;
	}
}
