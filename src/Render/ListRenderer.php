<?php
/**
 * The HTML of the list.
 *
 * @package WXP\EventsFeed
 */

declare( strict_types=1 );

namespace WXP\EventsFeed\Render;

/**
 * Turns checked feed items into markup. Everything that comes from the feed is escaped here, as late as possible.
 *
 * Options: image (bool), image_size (thumbnail|medium), description (bool), time (bool), date (site|source),
 * heading (h2 to h6 or p) and class (extra CSS class).
 */
final class ListRenderer {

	/**
	 * Builds the list.
	 *
	 * @param array<int, array<string, mixed>> $items   Items from FeedParser.
	 * @param array<string, mixed>             $options Options, see the class description.
	 */
	public function render( array $items, array $options ): string {
		$class = 'wxp-ef';
		if ( 'medium' === $options['image_size'] && $options['image'] ) {
			$class .= ' wxp-ef--medium';
		}
		if ( '' !== (string) $options['class'] ) {
			$class .= ' ' . implode( ' ', array_map( 'sanitize_html_class', explode( ' ', (string) $options['class'] ) ) );
		}

		if ( array() === $items ) {
			return sprintf(
				'<div class="%s"><p class="wxp-ef__empty">%s</p></div>',
				esc_attr( $class ),
				esc_html__( 'There are no upcoming events at the moment.', 'wxp-events-feed' )
			);
		}

		$html = '';
		foreach ( $items as $item ) {
			$html .= $this->item( $item, $options );
		}
		return sprintf(
			'<div class="%s"><ul class="wxp-ef__list">%s</ul></div>',
			esc_attr( $class ),
			$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts below.
		);
	}

	/**
	 * One list item.
	 *
	 * @param array<string, mixed> $item    Item.
	 * @param array<string, mixed> $options Options.
	 */
	private function item( array $item, array $options ): string {
		$image   = '';
		$sizes   = 'medium' === $options['image_size'] ? array( 'medium', 'thumbnail' ) : array( 'thumbnail', 'medium' );
		$picture = '' !== (string) $item[ $sizes[0] ] ? (string) $item[ $sizes[0] ] : (string) $item[ $sizes[1] ];
		if ( $options['image'] && '' !== $picture ) {
			// The title link below is the real link; the picture only repeats it, so keyboard and screen reader skip it.
			$image = sprintf(
				'<a class="wxp-ef__image" href="%s" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true"><img src="%s" alt="" loading="lazy" decoding="async" /></a>',
				esc_url( (string) $item['url'] ),
				esc_url( $picture )
			);
		}

		$tag   = in_array( $options['heading'], array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ), true ) ? (string) $options['heading'] : 'h3';
		$title = sprintf(
			'<%1$s class="wxp-ef__title"><a href="%2$s" target="_blank" rel="noopener">%3$s<span class="screen-reader-text"> %4$s</span></a></%1$s>',
			$tag,
			esc_url( (string) $item['url'] ),
			esc_html( (string) $item['title'] ),
			esc_html__( '(opens in a new tab)', 'wxp-events-feed' )
		);

		$description = '';
		if ( $options['description'] && '' !== (string) $item['description'] ) {
			$description = '<p class="wxp-ef__description">' . esc_html( (string) $item['description'] ) . '</p>';
		}

		return sprintf(
			'<li class="wxp-ef__item%s">%s<div class="wxp-ef__body">%s%s%s</div></li>',
			'' !== $image ? ' has-image' : '',
			$image, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
			$this->date( $item, $options ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in date().
			$title, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
			$description // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
		);
	}

	/**
	 * The date line. In the time zone of the event, so a visitor elsewhere still sees the time at the venue.
	 *
	 * @param array<string, mixed> $item    Item.
	 * @param array<string, mixed> $options Options.
	 */
	private function date( array $item, array $options ): string {
		$start = new \DateTimeImmutable( (string) $item['start'] );

		if ( 'source' === $options['date'] && '' !== (string) $item['date_label'] ) {
			$text = (string) $item['date_label'];
			if ( $options['time'] && '' !== (string) $item['time_label'] ) {
				$text .= ', ' . $item['time_label'];
			}
		} else {
			$zone = $start->getTimezone();
			$text = wp_date( (string) get_option( 'date_format' ), $start->getTimestamp(), $zone );
			if ( $options['time'] ) {
				$time_format = (string) get_option( 'time_format' );
				$text       .= ', ' . wp_date( $time_format, $start->getTimestamp(), $zone );
				if ( '' !== (string) $item['end'] ) {
					$end = new \DateTimeImmutable( (string) $item['end'] );
					if ( $end > $start && $end->setTimezone( $zone )->format( 'Y-m-d' ) === $start->format( 'Y-m-d' ) ) {
						$text .= ' – ' . wp_date( $time_format, $end->getTimestamp(), $zone );
					}
				}
			}
		}

		return sprintf(
			'<time class="wxp-ef__date" datetime="%s">%s</time>',
			esc_attr( (string) $item['start'] ),
			esc_html( $text )
		);
	}
}
