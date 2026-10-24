<?php
/**
 * Tests for reading a GitHub release.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WXP\EventsFeed\Update\GitHubUpdater;

final class GitHubReleaseTest extends TestCase {

	/**
	 * A release as GitHub returns it, with some fields replaced.
	 *
	 * @param array<string, mixed> $override Fields to replace.
	 * @return array<string, mixed>
	 */
	private static function release( array $override = array() ): array {
		return array_merge(
			array(
				'tag_name'   => 'v0.2.0',
				'html_url'   => 'https://github.com/wordxpression/wxp-events-feed/releases/tag/v0.2.0',
				'body'       => 'Fixes.',
				'draft'      => false,
				'prerelease' => false,
				'assets'     => array(
					array(
						'name'                 => 'wxp-events-feed-0.2.0.zip',
						'browser_download_url' => 'https://github.com/wordxpression/wxp-events-feed/releases/download/v0.2.0/wxp-events-feed-0.2.0.zip',
					),
				),
			),
			$override
		);
	}

	public function test_a_good_release(): void {
		$read = GitHubUpdater::read( self::release() );
		$this->assertSame( '0.2.0', $read['version'] );
		$this->assertStringEndsWith( 'wxp-events-feed-0.2.0.zip', $read['package'] );
	}

	public function test_drafts_and_prereleases_are_ignored(): void {
		$this->assertNull( GitHubUpdater::read( self::release( array( 'draft' => true ) ) ) );
		$this->assertNull( GitHubUpdater::read( self::release( array( 'prerelease' => true ) ) ) );
	}

	public function test_the_tag_must_be_a_version(): void {
		$this->assertNull( GitHubUpdater::read( self::release( array( 'tag_name' => 'latest' ) ) ) );
		$this->assertNull( GitHubUpdater::read( self::release( array( 'tag_name' => 'v1.2' ) ) ) );
	}

	public function test_a_release_without_the_zip_is_ignored(): void {
		$this->assertNull( GitHubUpdater::read( self::release( array( 'assets' => array() ) ) ) );
		$this->assertNull( GitHubUpdater::read( self::release( array( 'assets' => array( array( 'name' => 'source.zip', 'browser_download_url' => 'https://github.com/x/y/a.zip' ) ) ) ) ) );
	}

	public function test_the_package_must_come_from_github(): void {
		$assets = array( array( 'name' => 'wxp-events-feed-0.2.0.zip', 'browser_download_url' => 'https://evil.example.org/wxp-events-feed-0.2.0.zip' ) );
		$this->assertNull( GitHubUpdater::read( self::release( array( 'assets' => $assets ) ) ) );
	}

	public function test_garbage(): void {
		$this->assertNull( GitHubUpdater::read( null ) );
		$this->assertNull( GitHubUpdater::read( 'text' ) );
		$this->assertNull( GitHubUpdater::read( array() ) );
	}
}
