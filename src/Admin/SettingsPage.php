<?php
/**
 * Settings → Events Feed.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Admin;

use WXP\EventsFeed\Feed\FeedClient;
use WXP\EventsFeed\Feed\SourceUrl;
use WXP\EventsFeed\Settings\Settings;
use WXP\EventsFeed\Shortcode\FeedShortcode;

/**
 * The address of the events website, the cache lifetime, a button that tests the connection and one that empties
 * the cache, and a short explanation of the shortcode.
 */
final class SettingsPage {

	private const PAGE = 'wxp-events-feed';

	private const TEST_ACTION = 'wxp_ef_test';

	private const CLEAR_ACTION = 'wxp_ef_clear';

	/**
	 * The client.
	 *
	 * @var FeedClient
	 */
	private FeedClient $client;

	/**
	 * Constructor.
	 *
	 * @param FeedClient $client Client.
	 */
	public function __construct( FeedClient $client ) {
		$this->client = $client;
	}

	/**
	 * Hooks the page.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( $this, 'test' ) );
		add_action( 'admin_post_' . self::CLEAR_ACTION, array( $this, 'clear' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( \WXP\EventsFeed\PLUGIN_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the page under Settings.
	 */
	public function add_page(): void {
		add_options_page( __( 'Events Feed', 'wxp-events-feed' ), __( 'Events Feed', 'wxp-events-feed' ), 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	/**
	 * Registers the option.
	 */
	public function register_setting(): void {
		register_setting(
			Settings::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Adds "Settings" to the plugin's row on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'wxp-events-feed' ) . '</a>' );
		return $links;
	}

	/**
	 * Tests the connection with the address as it is in the field, saved or not. The address is not saved, but it
	 * stays in the field when the page comes back.
	 */
	public function test(): void {
		$this->guard( self::TEST_ACTION, 'wxp_ef_nonce' );
		$typed  = isset( $_POST[ Settings::OPTION ]['source_url'] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ Settings::OPTION ]['source_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$source = '' !== $typed ? SourceUrl::normalize( $typed ) : Settings::source_url();
		if ( '' === $source ) {
			$this->notice( 'error', __( 'Fill in the address of the events website first.', 'wxp-events-feed' ), $typed );
		} else {
			$result = $this->client->fetch( $source, 5 );
			if ( $result->usable() ) {
				$this->notice(
					'success',
					sprintf(
						/* translators: %d: number of events */
						_n( 'Connected. The events website shares %d coming event.', 'Connected. The events website shares %d coming events.', count( $result->items ), 'wxp-events-feed' ),
						count( $result->items )
					),
					$typed
				);
			} else {
				$this->notice( 'error', $this->client->message( $result ), $typed );
			}
		}
		$this->back();
	}

	/**
	 * Empties the cache.
	 */
	public function clear(): void {
		$this->guard( self::CLEAR_ACTION );
		Settings::clear_cache();
		$this->notice( 'success', __( 'The cache is emptied. The next visit fetches the events again.', 'wxp-events-feed' ) );
		$this->back();
	}

	/**
	 * Renders the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Events Feed', 'wxp-events-feed' ); ?></h1>
			<?php $draft = $this->show_notice(); ?>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( Settings::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wxp-ef-source"><?php esc_html_e( 'Website with the events', 'wxp-events-feed' ); ?></label></th>
						<td>
							<input type="text" id="wxp-ef-source" class="regular-text code" name="<?php echo esc_attr( Settings::OPTION ); ?>[source_url]" value="<?php echo esc_attr( $draft ?? Settings::source_url() ); ?>" placeholder="https://events.example.com" />
							<p class="description"><?php esc_html_e( 'The address of the website that runs WXP Local Events. On that website, switch on Events → Settings → Share events.', 'wxp-events-feed' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wxp-ef-minutes"><?php esc_html_e( 'Refresh the events every (minutes)', 'wxp-events-feed' ); ?></label></th>
						<td>
							<input type="number" id="wxp-ef-minutes" class="small-text" min="<?php echo (int) Settings::MIN_MINUTES; ?>" max="<?php echo (int) Settings::MAX_MINUTES; ?>" name="<?php echo esc_attr( Settings::OPTION ); ?>[cache_minutes]" value="<?php echo (int) Settings::get( 'cache_minutes' ); ?>" />
							<p class="description"><?php esc_html_e( 'The list is kept on this website for this long, so visitors never wait for the other website. If it does not answer, the last list stays visible.', 'wxp-events-feed' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Updates', 'wxp-events-feed' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( Settings::OPTION ); ?>[updates]" value="0" />
							<label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[updates]" value="1" <?php checked( 1, Settings::get( 'updates' ) ); ?> /> <?php esc_html_e( 'Look for new versions of this plugin on GitHub', 'wxp-events-feed' ); ?></label>
							<p class="description"><?php esc_html_e( 'This plugin is not in the WordPress plugin directory. WordPress asks GitHub twice a day if a newer version exists, and sends nothing but the request itself.', 'wxp-events-feed' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<?php submit_button( null, 'primary', 'submit', false ); ?>
					<?php wp_nonce_field( self::TEST_ACTION, 'wxp_ef_nonce', false ); ?>
					<button type="submit" class="button" name="action" value="<?php echo esc_attr( self::TEST_ACTION ); ?>" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php esc_html_e( 'Test the connection', 'wxp-events-feed' ); ?></button>
				</p>
			</form>

			<h2><?php esc_html_e( 'Check and clean up', 'wxp-events-feed' ); ?></h2>
			<p>
				<?php $this->button( self::CLEAR_ACTION, __( 'Empty the cache', 'wxp-events-feed' ) ); ?>
			</p>

			<h2><?php esc_html_e( 'Show the events', 'wxp-events-feed' ); ?></h2>
			<p><?php esc_html_e( 'Add the block "Events feed" (category WXP Plugins), or the Elementor widget with the same name, or use the shortcode:', 'wxp-events-feed' ); ?></p>
			<p><code>[<?php echo esc_html( FeedShortcode::TAG ); ?> limit="5"]</code></p>
			<ul class="ul-disc">
				<li><code>limit</code> — <?php esc_html_e( 'number of events, 1 to 20 (default 5).', 'wxp-events-feed' ); ?></li>
				<li><code>category</code> — <?php esc_html_e( 'category slugs of the events website, comma separated.', 'wxp-events-feed' ); ?></li>
				<li><code>image</code>, <code>description</code>, <code>time</code> — <?php esc_html_e( 'yes or no.', 'wxp-events-feed' ); ?></li>
				<li><code>image_size</code> — <?php esc_html_e( 'thumbnail or medium.', 'wxp-events-feed' ); ?></li>
				<li><code>date</code> — <?php esc_html_e( '"site" formats dates like this website does (default), "source" uses the text of the events website.', 'wxp-events-feed' ); ?></li>
				<li><code>heading</code> — <?php esc_html_e( 'h2 to h6, or p, for the event titles (default h3).', 'wxp-events-feed' ); ?></li>
				<li><code>class</code> — <?php esc_html_e( 'an extra CSS class.', 'wxp-events-feed' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Prints a small form with one button that posts to admin-post.php.
	 *
	 * @param string $action Action name.
	 * @param string $label  Button text.
	 */
	private function button( string $action, string $label ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
		wp_nonce_field( $action );
		submit_button( $label, 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Stops when the user may not do this or the link is not genuine.
	 *
	 * @param string $action Action name, also the nonce action.
	 * @param string $field  Name of the form field that holds the nonce.
	 */
	private function guard( string $action, string $field = '_wpnonce' ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'wxp-events-feed' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action, $field );
	}

	/**
	 * Remembers a message for the next page view of this user.
	 *
	 * @param string $type    success or error.
	 * @param string $message Text.
	 * @param string $draft   The address as typed in the field, to put back there.
	 */
	private function notice( string $type, string $message, string $draft = '' ): void {
		set_transient(
			'wxp_ef_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
				'draft'   => $draft,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Shows and forgets the remembered message.
	 *
	 * @return string|null The address that was typed before the test, to show in the field again; null when there is none.
	 */
	private function show_notice(): ?string {
		$key    = 'wxp_ef_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) ) {
			return null;
		}
		delete_transient( $key );

		$message = (string) $notice['message'];
		$draft   = (string) ( $notice['draft'] ?? '' );
		if ( '' !== $draft && SourceUrl::normalize( $draft ) !== Settings::source_url() ) {
			$message .= ' ' . __( 'The address is not saved yet: press Save changes.', 'wxp-events-feed' );
		}
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			'success' === $notice['type'] ? 'success' : 'error',
			esc_html( $message )
		);
		return '' !== $draft ? $draft : null;
	}

	/**
	 * Goes back to the settings page.
	 */
	private function back(): void {
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE ) );
		exit;
	}
}
