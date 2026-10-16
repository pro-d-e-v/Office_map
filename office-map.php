<?php
/**
 * Plugin Name: PD17 Map — OpenStreetMap Vector Maps
 * Plugin URI:  https://wordpress.org/plugins/prodev17-location-map/
 * Description: Interactive vector maps with OpenStreetMap and MapLibre GL — no API keys, no ads, no tracking. Shortcode, widget and a visual style editor, with ready-made look presets and 17 stylable layers.
 * Version:     1.9.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      prodev17
 * Author URI:  https://profiles.wordpress.org/prodev17/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: prodev17-location-map
 * Domain Path: /languages
 *
 * @package Office_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct access is forbidden.
}

define( 'OFFICE_MAP_VERSION', '1.9.2' );
define( 'OFFICE_MAP_FILE', __FILE__ );
define( 'OFFICE_MAP_DIR', plugin_dir_path( __FILE__ ) );
define( 'OFFICE_MAP_URL', plugin_dir_url( __FILE__ ) );

require_once OFFICE_MAP_DIR . 'includes/class-office-map.php';
require_once OFFICE_MAP_DIR . 'includes/class-office-map-settings.php';
require_once OFFICE_MAP_DIR . 'includes/class-office-map-widget.php';

Office_Map::instance();

if ( is_admin() ) {
	Office_Map_Settings::instance();
}

/**
 * Register the widget.
 *
 * @return void
 */
function office_map_register_widget() {
	register_widget( 'Office_Map_Widget' );
}
add_action( 'widgets_init', 'office_map_register_widget' );
