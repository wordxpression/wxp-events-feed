<?php
/**
 * Elementor widget: the events feed.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use WXP\EventsFeed\Feed\FeedClient;
use WXP\EventsFeed\Shortcode\FeedShortcode;

/**
 * Same output as the shortcode and the block: render() hands its settings to FeedShortcode. The style controls set
 * the CSS custom properties and colours of the list.
 */
final class FeedWidget extends Widget_Base {

	private const BOX = '{{WRAPPER}} .wxp-ef';

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'wxp-events-feed';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Events feed', 'wxp-events-feed' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon(): string {
		return 'eicon-posts-grid';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_categories(): array {
		return array( ElementorIntegration::CATEGORY );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_keywords(): array {
		return array( 'events', 'feed', 'agenda', 'evenementen', 'veranstaltungen', 'événements' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_style_depends(): array {
		return array( FeedShortcode::STYLE );
	}

	/**
	 * The list comes from the cache and changes over time, so Elementor must not cache the output.
	 */
	protected function is_dynamic_content(): bool {
		return true;
	}

	/**
	 * Registers the controls.
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'section_events', array( 'label' => __( 'Events', 'wxp-events-feed' ) ) );

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Number of events', 'wxp-events-feed' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 20,
				'default' => 5,
			)
		);
		$this->add_control(
			'category',
			array(
				'label'       => __( 'Categories', 'wxp-events-feed' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'description' => __( 'Category slugs of the events website, comma separated. Empty shows all events.', 'wxp-events-feed' ),
			)
		);
		$this->add_control(
			'image',
			array(
				'label'        => __( 'Image', 'wxp-events-feed' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'image_size',
			array(
				'label'     => __( 'Image size', 'wxp-events-feed' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'thumbnail' => __( 'Small', 'wxp-events-feed' ),
					'medium'    => __( 'Medium', 'wxp-events-feed' ),
				),
				'default'   => 'thumbnail',
				'condition' => array( 'image' => 'yes' ),
			)
		);
		$this->add_control(
			'description',
			array(
				'label'        => __( 'Short description', 'wxp-events-feed' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'time',
			array(
				'label'        => __( 'Time', 'wxp-events-feed' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_control(
			'date',
			array(
				'label'   => __( 'Date format', 'wxp-events-feed' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'site'   => __( 'Like this website', 'wxp-events-feed' ),
					'source' => __( 'Like the events website', 'wxp-events-feed' ),
				),
				'default' => 'site',
			)
		);
		$this->add_control(
			'heading',
			array(
				'label'   => __( 'Title element', 'wxp-events-feed' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
					'p'  => 'p',
				),
				'default' => 'h3',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Style', 'wxp-events-feed' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'image_width',
			array(
				'label'      => __( 'Image width', 'wxp-events-feed' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 40,
						'max' => 400,
					),
				),
				'selectors'  => array(
					self::BOX => '--wxp-ef-image-size: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->add_control(
			'gap',
			array(
				'label'      => __( 'Space between events', 'wxp-events-feed' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'max' => 80 ) ),
				'selectors'  => array(
					self::BOX => '--wxp-ef-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->add_control(
			'line',
			array(
				'label'     => __( 'Lines between events', 'wxp-events-feed' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					self::BOX => '--wxp-ef-line: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'date_color',
			array(
				'label'     => __( 'Date colour', 'wxp-events-feed' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					self::BOX . ' .wxp-ef__date' => 'color: {{VALUE}}; opacity: 1;',
				),
			)
		);
		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Title colour', 'wxp-events-feed' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					self::BOX . ' .wxp-ef__title a' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'description_color',
			array(
				'label'     => __( 'Description colour', 'wxp-events-feed' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					self::BOX . ' .wxp-ef__description' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'date_typography',
				'label'    => __( 'Date', 'wxp-events-feed' ),
				'selector' => self::BOX . ' .wxp-ef__date',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'label'    => __( 'Title', 'wxp-events-feed' ),
				'selector' => self::BOX . ' .wxp-ef__title',
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'description_typography',
				'label'    => __( 'Description', 'wxp-events-feed' ),
				'selector' => self::BOX . ' .wxp-ef__description',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Renders the widget: the shortcode class does the work.
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$renderer = new FeedShortcode( new FeedClient() );

		$html = $renderer->render(
			array(
				'limit'       => (string) ( $settings['limit'] ?? 5 ),
				'category'    => (string) ( $settings['category'] ?? '' ),
				'image'       => 'yes' === ( $settings['image'] ?? '' ) ? 'yes' : 'no',
				'image_size'  => (string) ( $settings['image_size'] ?? 'thumbnail' ),
				'description' => 'yes' === ( $settings['description'] ?? '' ) ? 'yes' : 'no',
				'time'        => 'yes' === ( $settings['time'] ?? '' ) ? 'yes' : 'no',
				'date'        => (string) ( $settings['date'] ?? 'site' ),
				'heading'     => (string) ( $settings['heading'] ?? 'h3' ),
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderer escapes all output.
	}
}
