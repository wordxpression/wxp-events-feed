<?php
/**
 * The description for the Shortcode Assistant must name exactly the attributes the shortcode accepts.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Shortcode {

	if ( ! function_exists( __NAMESPACE__ . '\__' ) ) {
		/**
		 * Translation stub for the test: returns the English text.
		 *
		 * @param string $text Text.
		 */
		function __( string $text ): string {
			return $text;
		}
	}
}

namespace WXP\EventsFeed\Tests\Unit {

	use PHPUnit\Framework\TestCase;
	use WXP\EventsFeed\Shortcode\AssistantDescription;
	use WXP\EventsFeed\Shortcode\FeedShortcode;

	final class AssistantDescriptionTest extends TestCase {

		public function test_the_shortcode_is_described_completely(): void {
			$accepted = array_keys( FeedShortcode::defaults() );
			$plugins  = ( new AssistantDescription() )->add( array() );
			$fields   = $plugins[0]['shortcodes'][0]['fields'];
			$names    = array_column( $fields, 'name' );
			sort( $accepted );
			sort( $names );
			$this->assertSame( 'wxp_events_feed', $plugins[0]['shortcodes'][0]['tag'] );
			$this->assertSame( $accepted, $names );
			// The defaults of the description are the defaults of the shortcode.
			foreach ( $fields as $field ) {
				$this->assertSame( FeedShortcode::defaults()[ $field['name'] ], (string) $field['default'], $field['name'] );
			}
		}
	}
}