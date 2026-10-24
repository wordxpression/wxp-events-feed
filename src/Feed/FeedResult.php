<?php
/**
 * What the client found for one list.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Feed;

/**
 * The items to show, and where they came from: `fresh` (just fetched or still valid in the cache), `stale` (an older
 * copy shown because the events website did not answer), or `error` (nothing to show; `error` says why).
 */
final class FeedResult {

	public const FRESH = 'fresh';
	public const STALE = 'stale';
	public const ERROR = 'error';

	/**
	 * Constructor.
	 *
	 * @param string                           $status  fresh, stale or error.
	 * @param array<int, array<string, mixed>> $items   Checked items.
	 * @param string                           $error   Error code (see FeedClient), empty without error.
	 * @param int                              $fetched Time the items were fetched.
	 * @param string                           $detail  Extra detail for the error message, such as an HTTP status.
	 */
	public function __construct(
		public readonly string $status,
		public readonly array $items = array(),
		public readonly string $error = '',
		public readonly int $fetched = 0,
		public readonly string $detail = ''
	) {}

	/**
	 * Whether there is something to show.
	 */
	public function usable(): bool {
		return self::ERROR !== $this->status;
	}
}
