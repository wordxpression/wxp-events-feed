<?php
/**
 * Elementor integration.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Elementor;

/**
 * Registers the widget category and the widget.
 *
 * The hooks only fire when Elementor is active, and the widget class (which extends Elementor's Widget_Base) is only
 * loaded inside them, so the plugin works fine without Elementor.
 */
final class ElementorIntegration {

	/**
	 * Shared by all WXP plugins: whoever registers it first creates the panel, the others add their widgets to it.
	 */
	public const CATEGORY = 'wxp-plugins';

	/**
	 * Hooks into Elementor.
	 */
	public function register(): void {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Adds the shared "WXP Plugins" widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elements manager.
	 */
	public function register_category( $elements_manager ): void {
		$elements_manager->add_category(
			self::CATEGORY,
			array(
				'title' => 'WXP Plugins',
				'icon'  => 'eicon-apps',
			)
		);
	}

	/**
	 * Registers the widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 */
	public function register_widgets( $widgets_manager ): void {
		$widgets_manager->register( new FeedWidget() );
	}
}
