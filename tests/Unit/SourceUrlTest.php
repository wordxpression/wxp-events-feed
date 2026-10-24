<?php
/**
 * Tests for the address of the events website.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WXP\EventsFeed\Feed\SourceUrl;

final class SourceUrlTest extends TestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function addresses(): array {
		return array(
			'plain'                  => array( 'https://events.example.com', 'https://events.example.com' ),
			'trailing slash'         => array( 'https://events.example.com/', 'https://events.example.com' ),
			'no scheme'              => array( 'events.example.com', 'https://events.example.com' ),
			'upper case host'        => array( 'HTTPS://Events.Example.COM', 'https://events.example.com' ),
			'subfolder'              => array( 'https://example.com/venue/', 'https://example.com/venue' ),
			'port'                   => array( 'http://localhost:8080', 'http://localhost:8080' ),
			'pasted feed address'    => array( 'https://events.example.com/wp-json/wxp-local-events/v1/feed?limit=3', 'https://events.example.com' ),
			'pasted in a subfolder'  => array( 'https://example.com/venue/wp-json/wxp-local-events/v1/feed', 'https://example.com/venue' ),
			'query and fragment'     => array( 'https://events.example.com/?a=1#top', 'https://events.example.com' ),
			'spaces around'          => array( '  https://events.example.com  ', 'https://events.example.com' ),
			'javascript'             => array( 'javascript:alert(1)', '' ),
			'ftp'                    => array( 'ftp://events.example.com', '' ),
			'user info'              => array( 'https://user:pass@events.example.com', '' ),
			'empty'                  => array( '', '' ),
			'spaces inside'          => array( 'https://events example.com', '' ),
			'bad host'               => array( 'https://exa_mple.com', '' ),
		);
	}

	#[DataProvider( 'addresses' )]
	public function test_normalize( string $typed, string $expected ): void {
		$this->assertSame( $expected, SourceUrl::normalize( $typed ) );
	}

	public function test_host_ignores_www(): void {
		$this->assertSame( 'example.com', SourceUrl::host( 'https://www.example.com/a' ) );
		$this->assertSame( 'example.com', SourceUrl::host( 'HTTP://EXAMPLE.com' ) );
		$this->assertSame( '', SourceUrl::host( 'not a url' ) );
	}

	public function test_feed_urls_have_both_forms(): void {
		$urls = SourceUrl::feed_urls( 'https://example.com/venue', array( 'limit' => 5, 'category' => 'music,jazz' ) );
		$this->assertSame( 'https://example.com/venue/wp-json/wxp-local-events/v1/feed?limit=5&category=music%2Cjazz', $urls[0] );
		$this->assertSame( 'https://example.com/venue/?rest_route=/wxp-local-events/v1/feed&limit=5&category=music%2Cjazz', $urls[1] );
	}

	public function test_empty_arguments_are_left_out(): void {
		$urls = SourceUrl::feed_urls( 'https://example.com', array( 'limit' => 5, 'category' => '' ) );
		$this->assertSame( 'https://example.com/wp-json/wxp-local-events/v1/feed?limit=5', $urls[0] );
		$urls = SourceUrl::feed_urls( 'https://example.com', array() );
		$this->assertSame( 'https://example.com/wp-json/wxp-local-events/v1/feed', $urls[0] );
	}
}
