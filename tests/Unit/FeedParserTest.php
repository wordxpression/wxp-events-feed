<?php
/**
 * Tests for reading and checking the feed.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WXP\EventsFeed\Feed\FeedException;
use WXP\EventsFeed\Feed\FeedParser;

final class FeedParserTest extends TestCase {

	private const SOURCE = 'https://events.example.com';

	/**
	 * A good item, with some fields replaced.
	 *
	 * @param array<string, mixed> $override Fields to replace.
	 * @return array<string, mixed>
	 */
	private static function item( array $override = array() ): array {
		return array_merge(
			array(
				'id'          => 12,
				'key'         => '2030-05-17',
				'title'       => 'Jazz night',
				'url'         => 'https://events.example.com/events/jazz/',
				'date'        => '2030-05-17',
				'start'       => '2030-05-17T20:00:00+02:00',
				'end'         => '2030-05-17T22:30:00+02:00',
				'date_label'  => 'Friday May 17, 2030',
				'time_label'  => '20:00 – 22:30',
				'description' => 'Live music.',
				'image'       => array(
					'thumbnail' => 'https://events.example.com/up/jazz-150x150.jpg',
					'medium'    => 'https://events.example.com/up/jazz-300x200.jpg',
				),
				'categories'  => array( array( 'name' => 'Music', 'slug' => 'music' ) ),
			),
			$override
		);
	}

	/**
	 * Encodes a feed.
	 *
	 * @param array<int, array<string, mixed>> $items   Items.
	 * @param array<string, mixed>             $feed    Fields of the feed to replace.
	 */
	private static function body( array $items, array $feed = array() ): string {
		return (string) json_encode(
			array_merge(
				array(
					'version' => 1,
					'site'    => array( 'name' => 'The Venue', 'url' => 'https://events.example.com/' ),
					'items'   => $items,
				),
				$feed
			)
		);
	}

	public function test_a_good_feed(): void {
		$parsed = FeedParser::parse( self::body( array( self::item() ) ), self::SOURCE );
		$this->assertSame( 'The Venue', $parsed['site']['name'] );
		$this->assertCount( 1, $parsed['items'] );
		$item = $parsed['items'][0];
		$this->assertSame( 'Jazz night', $item['title'] );
		$this->assertSame( '2030-05-17T20:00:00+02:00', $item['start'] );
		$this->assertSame( '2030-05-17T22:30:00+02:00', $item['end'] );
		$this->assertSame( 'https://events.example.com/up/jazz-150x150.jpg', $item['thumbnail'] );
		$this->assertSame( array( array( 'name' => 'Music', 'slug' => 'music' ) ), $item['categories'] );
	}

	public function test_not_json(): void {
		$this->expectException( FeedException::class );
		FeedParser::parse( '<html>', self::SOURCE );
	}

	public function test_json_that_is_not_a_feed(): void {
		try {
			FeedParser::parse( '{"hello":"world"}', self::SOURCE );
			$this->fail( 'No exception.' );
		} catch ( FeedException $error ) {
			$this->assertSame( FeedException::NOT_A_FEED, $error->reason() );
		}
	}

	public function test_a_newer_version_is_refused(): void {
		try {
			FeedParser::parse( self::body( array(), array( 'version' => 2 ) ), self::SOURCE );
			$this->fail( 'No exception.' );
		} catch ( FeedException $error ) {
			$this->assertSame( FeedException::NEWER, $error->reason() );
		}
	}

	public function test_an_empty_list_is_fine(): void {
		$this->assertSame( array(), FeedParser::parse( self::body( array() ), self::SOURCE )['items'] );
	}

	public function test_html_in_text_is_removed(): void {
		$parsed = FeedParser::parse(
			self::body( array( self::item( array( 'title' => '<script>alert(1)</script>Jazz <b>night</b>', 'description' => "Line one\n\n  <i>two</i>" ) ) ) ),
			self::SOURCE
		);
		$this->assertSame( 'alert(1)Jazz night', $parsed['items'][0]['title'] );
		$this->assertSame( 'Line one two', $parsed['items'][0]['description'] );
	}

	public function test_long_text_is_cut(): void {
		$parsed = FeedParser::parse( self::body( array( self::item( array( 'description' => str_repeat( 'word ', 200 ) ) ) ) ), self::SOURCE );
		$this->assertLessThanOrEqual( 300, mb_strlen( $parsed['items'][0]['description'] ) );
		$this->assertStringEndsWith( '…', $parsed['items'][0]['description'] );
	}

	public function test_links_must_stay_on_the_events_website(): void {
		$bad = array(
			'https://evil.example.org/events/jazz/',
			'javascript:alert(1)',
			'//events.example.com/x',
			'https://user@events.example.com/x',
			'https://events.example.com/x" onclick="y',
			'',
		);
		foreach ( $bad as $url ) {
			$parsed = FeedParser::parse( self::body( array( self::item( array( 'url' => $url ) ) ) ), self::SOURCE );
			$this->assertSame( array(), $parsed['items'], 'Accepted: ' . $url );
		}
	}

	public function test_www_makes_no_difference(): void {
		$parsed = FeedParser::parse( self::body( array( self::item( array( 'url' => 'https://www.events.example.com/a' ) ) ) ), self::SOURCE );
		$this->assertCount( 1, $parsed['items'] );
	}

	public function test_images_must_be_web_addresses(): void {
		$parsed = FeedParser::parse(
			self::body( array( self::item( array( 'image' => array( 'thumbnail' => 'javascript:alert(1)', 'medium' => 'https://cdn.example.net/a.jpg' ) ) ) ) ),
			self::SOURCE
		);
		$this->assertSame( '', $parsed['items'][0]['thumbnail'] );
		$this->assertSame( 'https://cdn.example.net/a.jpg', $parsed['items'][0]['medium'] );
	}

	public function test_bad_dates_skip_the_item(): void {
		foreach ( array( '', 'tomorrow', '2030-05-17', '2030-02-31T20:00:00+01:00', '2030-05-17 20:00:00' ) as $start ) {
			$parsed = FeedParser::parse( self::body( array( self::item( array( 'start' => $start ) ) ) ), self::SOURCE );
			$this->assertSame( array(), $parsed['items'], 'Accepted: ' . $start );
		}
	}

	public function test_a_bad_end_is_dropped_but_the_item_stays(): void {
		$parsed = FeedParser::parse( self::body( array( self::item( array( 'end' => 'later' ) ) ) ), self::SOURCE );
		$this->assertSame( '', $parsed['items'][0]['end'] );
	}

	public function test_items_without_a_title_are_skipped(): void {
		$parsed = FeedParser::parse( self::body( array( self::item( array( 'title' => '  <b></b> ' ) ), self::item() ) ), self::SOURCE );
		$this->assertCount( 1, $parsed['items'] );
	}

	public function test_garbage_items_do_not_break_the_rest(): void {
		$parsed = FeedParser::parse( self::body( array( 'text', 5, null, array(), self::item() ) ), self::SOURCE );
		$this->assertCount( 1, $parsed['items'] );
	}

	public function test_at_most_fifty_items_are_read(): void {
		$items = array_fill( 0, 80, self::item() );
		$this->assertCount( 50, FeedParser::parse( self::body( $items ), self::SOURCE )['items'] );
	}
}
