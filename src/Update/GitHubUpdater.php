<?php
/**
 * Updates from GitHub releases.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Update;

use WXP\EventsFeed\Settings\Settings;

use const WXP\EventsFeed\PLUGIN_FILE;
use const WXP\EventsFeed\TEXT_DOMAIN;
use const WXP\EventsFeed\VERSION;

/**
 * The plugin is not in the WordPress plugin directory, so WordPress cannot update it by itself. This class tells
 * WordPress when the newest release on GitHub is newer than the installed version, using WordPress' own update
 * check (twice a day) and update screen.
 *
 * The release must have a zip attached whose name starts with "wxp-events-feed" and that holds the folder
 * `wxp-events-feed`. Nothing but the request is sent to GitHub, and the answer is cached for twelve hours. It can be
 * switched off in the settings.
 */
final class GitHubUpdater {

	/**
	 * The repository, "owner/name". Change it with the filter `wxp_events_feed_update_repo`.
	 */
	private const REPO = 'wordxpression/wxp-events-feed';

	private const CACHE = 'wxp_ef_latest_release';

	/**
	 * Hooks into the update check.
	 */
	public function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check' ) );
		add_filter( 'plugins_api', array( $this, 'information' ), 10, 3 );
	}

	/**
	 * Adds this plugin to the list of updates when GitHub has a newer release.
	 *
	 * @param mixed $transient The update_plugins transient.
	 * @return mixed
	 */
	public function check( $transient ) {
		if ( ! is_object( $transient ) || 1 !== Settings::get( 'updates' ) ) {
			return $transient;
		}
		$release = $this->latest();
		if ( null === $release ) {
			return $transient;
		}

		$basename = plugin_basename( PLUGIN_FILE );
		$item     = (object) array(
			'id'          => $basename,
			'slug'        => TEXT_DOMAIN,
			'plugin'      => $basename,
			'new_version' => $release['version'],
			'url'         => $release['url'],
			'package'     => $release['package'],
		);
		if ( version_compare( $release['version'], VERSION, '>' ) ) {
			$transient->response[ $basename ] = $item;
		} else {
			$transient->no_update[ $basename ] = $item;
		}
		return $transient;
	}

	/**
	 * Fills the "View details" popup on the Plugins screen.
	 *
	 * @param mixed  $result Result so far.
	 * @param string $action Requested action.
	 * @param object $args   Arguments, with the slug.
	 * @return mixed
	 */
	public function information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || TEXT_DOMAIN !== ( $args->slug ?? '' ) || 1 !== Settings::get( 'updates' ) ) {
			return $result;
		}
		$release = $this->latest();
		if ( null === $release ) {
			return $result;
		}
		return (object) array(
			'name'          => 'WXP Events Feed',
			'slug'          => TEXT_DOMAIN,
			'version'       => $release['version'],
			'author'        => '<a href="https://wxpplugins.com">Wilko van der Ploeg</a>',
			'homepage'      => 'https://wxpplugins.com/wxp-events-feed/',
			'requires'      => '6.5',
			'requires_php'  => '8.1',
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => esc_html__( 'Show the coming events of another website that runs WXP Local Events.', 'wxp-events-feed' ),
				'changelog'   => '<pre>' . esc_html( $release['notes'] ) . '</pre>',
			),
		);
	}

	/**
	 * The newest release, from the cache or from GitHub.
	 *
	 * @return array{version: string, url: string, package: string, notes: string}|null Null when there is none or GitHub does not answer.
	 */
	private function latest(): ?array {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return array() === $cached ? null : $cached;
		}

		/**
		 * Filters the GitHub repository ("owner/name") that holds the releases of this plugin.
		 *
		 * @param string $repository Repository.
		 */
		$repository = (string) apply_filters( 'wxp_events_feed_update_repo', self::REPO );
		if ( ! preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository ) ) {
			return null;
		}

		$response = wp_safe_remote_get(
			'https://api.github.com/repos/' . $repository . '/releases/latest',
			array(
				'timeout' => 5,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);
		$release  = null;
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$release = self::read( json_decode( (string) wp_remote_retrieve_body( $response ), true ) );
		}

		// A failure is remembered for an hour, a success for twelve.
		set_transient( self::CACHE, $release ?? array(), null === $release ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS );
		return $release;
	}

	/**
	 * Takes what is needed from the answer of GitHub.
	 *
	 * @param mixed $data Decoded answer.
	 * @return array{version: string, url: string, package: string, notes: string}|null
	 */
	public static function read( mixed $data ): ?array {
		if ( ! is_array( $data ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}
		$version = ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			return null;
		}

		$package = '';
		foreach ( is_array( $data['assets'] ?? null ) ? $data['assets'] : array() as $asset ) {
			$name = is_array( $asset ) ? (string) ( $asset['name'] ?? '' ) : '';
			$link = is_array( $asset ) ? (string) ( $asset['browser_download_url'] ?? '' ) : '';
			if ( preg_match( '/^wxp-events-feed[\w.-]*\.zip$/', $name ) && str_starts_with( $link, 'https://github.com/' ) ) {
				$package = $link;
				break;
			}
		}
		if ( '' === $package ) {
			return null;
		}

		$url = (string) ( $data['html_url'] ?? '' );
		return array(
			'version' => $version,
			'url'     => str_starts_with( $url, 'https://github.com/' ) ? $url : 'https://wxpplugins.com/wxp-events-feed/',
			'package' => $package,
			'notes'   => mb_substr( (string) ( $data['body'] ?? '' ), 0, 5000 ),
		);
	}
}
