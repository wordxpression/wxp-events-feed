<?php
/**
 * What the WXP Shortcode Assistant needs to know about the feed shortcode.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Shortcode;

/**
 * Describes [wxp_events_feed] for the free plugin WXP Shortcode Assistant, which builds shortcodes from a form.
 * The filter is never applied when the Assistant is not installed.
 */
final class AssistantDescription {

	/**
	 * Hooks the description.
	 */
	public function register(): void {
		add_filter( 'wxp_shortcode_assistant_plugins', array( $this, 'add' ) );
	}

	/**
	 * Adds this plugin to the list.
	 *
	 * @param array<int, array<string, mixed>> $plugins Plugins described so far.
	 * @return array<int, array<string, mixed>>
	 */
	public function add( array $plugins ): array {
		$what = __( 'What to show', 'wxp-events-feed' );
		$look = __( 'Look', 'wxp-events-feed' );

		$plugins[] = array(
			'key'        => 'wxp-events-feed',
			'label'      => __( 'Events Feed', 'wxp-events-feed' ),
			'shortcodes' => array(
				array(
					'tag'         => FeedShortcode::TAG,
					'label'       => __( 'Events from another site', 'wxp-events-feed' ),
					'description' => __( 'A list of the next events of the site you connected under Settings > Events Feed.', 'wxp-events-feed' ),
					'fields'      => array(
						array(
							'name'    => 'limit',
							'label'   => __( 'Number of events', 'wxp-events-feed' ),
							'type'    => 'number',
							'default' => '5',
							'min'     => 1,
							'max'     => 20,
							'group'   => $what,
						),
						array(
							'name'  => 'category',
							'label' => __( 'Categories', 'wxp-events-feed' ),
							'type'  => 'text',
							'group' => $what,
							'help'  => __( 'The slugs of categories on the other site, separated by commas. Empty: all events.', 'wxp-events-feed' ),
						),
						array(
							'name'    => 'image',
							'label'   => __( 'Show images', 'wxp-events-feed' ),
							'type'    => 'bool',
							'default' => 'yes',
							'group'   => $what,
						),
						array(
							'name'    => 'description',
							'label'   => __( 'Show the short description', 'wxp-events-feed' ),
							'type'    => 'bool',
							'default' => 'yes',
							'group'   => $what,
						),
						array(
							'name'    => 'time',
							'label'   => __( 'Show the time', 'wxp-events-feed' ),
							'type'    => 'bool',
							'default' => 'yes',
							'group'   => $what,
						),
						array(
							'name'    => 'date',
							'label'   => __( 'Date format', 'wxp-events-feed' ),
							'type'    => 'choice',
							'default' => 'site',
							'group'   => $what,
							'options' => array(
								'site'   => __( 'The date format of this site', 'wxp-events-feed' ),
								'source' => __( 'The date as the other site writes it', 'wxp-events-feed' ),
							),
						),
						array(
							'name'    => 'image_size',
							'label'   => __( 'Image size', 'wxp-events-feed' ),
							'type'    => 'choice',
							'default' => 'thumbnail',
							'group'   => $look,
							'options' => array(
								'thumbnail' => __( 'Small', 'wxp-events-feed' ),
								'medium'    => __( 'Medium', 'wxp-events-feed' ),
							),
							'depends' => array( 'image' => 'yes' ),
						),
						array(
							'name'    => 'heading',
							'label'   => __( 'Element of the titles', 'wxp-events-feed' ),
							'type'    => 'choice',
							'default' => 'h3',
							'group'   => $look,
							'options' => array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ),
						),
						array(
							'name'  => 'class',
							'label' => __( 'Extra CSS class', 'wxp-events-feed' ),
							'type'  => 'text',
							'group' => $look,
						),
					),
				),
			),
		);
		return $plugins;
	}
}
