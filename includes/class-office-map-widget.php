<?php
/**
 * The "PD17 Map" widget.
 *
 * @package Office_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Office_Map_Widget
 */
class Office_Map_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'office_map_widget',
			__( 'PD17 Map', 'prodev17-location-map' ),
			array(
				'description'                 => __( 'A location map (MapLibre + OpenStreetMap) without Google or Yandex.', 'prodev17-location-map' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Output the widget.
	 *
	 * @param array $args     Sidebar arguments.
	 * @param array $instance Widget settings.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-provided markup.

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-provided markup.
		}

		$atts = array();
		foreach ( array( 'lat', 'lng', 'zoom', 'height', 'palette' ) as $key ) {
			if ( ! empty( $instance[ $key ] ) ) {
				$atts[ $key ] = $instance[ $key ];
			}
		}

		$shortcode = '[office_map';
		foreach ( $atts as $k => $v ) {
			$shortcode .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}
		$shortcode .= ']';

		echo do_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the shortcode.
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-provided markup.
	}

	/**
	 * Widget settings form.
	 *
	 * @param array $instance Saved values.
	 * @return void
	 */
	public function form( $instance ) {
		$fields = array(
			'title'   => array( __( 'Title', 'prodev17-location-map' ), '' ),
			'lat'     => array( __( 'Latitude (lat)', 'prodev17-location-map' ), '' ),
			'lng'     => array( __( 'Longitude (lng)', 'prodev17-location-map' ), '' ),
			'zoom'    => array( __( 'Zoom', 'prodev17-location-map' ), '16' ),
			'height'  => array( __( 'Height, px', 'prodev17-location-map' ), '320' ),
			'palette' => array( __( 'Palette', 'prodev17-location-map' ), '' ),
		);

		foreach ( $fields as $key => $meta ) {
			$value = isset( $instance[ $key ] ) ? $instance[ $key ] : $meta[1];
			printf(
				'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s"></p>',
				esc_attr( $this->get_field_id( $key ) ),
				esc_html( $meta[0] ),
				esc_attr( $this->get_field_name( $key ) ),
				esc_attr( $value )
			);
		}
		echo '<p class="description">' . esc_html__( 'Empty fields use the values from the PD17 Map settings page.', 'prodev17-location-map' ) . '</p>';
	}

	/**
	 * Save the widget settings.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$out = array();
		foreach ( array( 'title', 'lat', 'lng', 'zoom', 'height', 'palette' ) as $key ) {
			$out[ $key ] = isset( $new_instance[ $key ] ) ? sanitize_text_field( $new_instance[ $key ] ) : '';
		}
		return $out;
	}
}
