<?php
/**
 * Plugin core: settings, assets, shortcode, geocoder.
 *
 * @package Office_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Office_Map
 */
class Office_Map {

	/**
	 * Singleton.
	 *
	 * @var Office_Map|null
	 */
	private static $instance = null;

	/**
	 * Counter of maps rendered on a page.
	 *
	 * @var int
	 */
	private static $counter = 0;

	/**
	 * Whether the script localization has been output.
	 *
	 * @var bool
	 */
	private static $localized = false;

	/**
	 * Get the instance.
	 *
	 * @return Office_Map
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_shortcode( 'office_map', array( $this, 'shortcode' ) );
		add_action( 'wp_ajax_office_map_geocode', array( $this, 'ajax_geocode' ) );
		add_action( 'wp_ajax_nopriv_office_map_geocode', array( $this, 'ajax_geocode' ) );
	}

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'lat'             => '53.900600',
			'lng'             => '27.559000',
			'zoom'            => '16',
			'height'          => '420',
			'width_preset'    => 'block',
			'width_value'     => '100',
			'width_unit'      => '%',
			'title'           => __( 'Our office', 'prodev17-location-map' ),
			'address'         => __( 'Add the office address', 'prodev17-location-map' ),
			'base_style'      => 'liberty',
			'palette'         => 'original',
			'zoom_mode'       => 'manual',
			'map_lang'        => 'auto',
			'marker'          => 1,
			'marker_color'    => '#e63946',
			'marker_image'    => '',
			'popup'           => 1,
			'scroll'          => 1,
			'fullscreen'      => 0,
			'search'          => 0,
			'points'          => array(),
			'style_colors'    => self::default_style_colors(),
			'search_bg'       => '#ffffff',
			'search_text'     => '#333333',
			'search_border'   => '#cccccc',
			'search_btn_bg'   => '#1a73e8',
			'search_btn_text' => '#ffffff',
			'search_radius'   => '4',
			'ui_open'         => null,
			'hide_poi'        => 0,
			'hide_buildings'  => 0,
			'hide_3d'         => 0,
			'hide_terrain'    => 0,
			'hide_boundary'   => 0,
			'hide_rail'       => 0,
			'hide_labels'     => 0,
			'terrain_opacity' => '100',
		);
	}

	/**
	 * Saved settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_options() {
		$options = get_option( 'office_map_options', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		return wp_parse_args( $options, self::defaults() );
	}

	/**
	 * Available OpenFreeMap base styles.
	 *
	 * @return array
	 */
	public static function base_styles() {
		return array(
			'liberty'  => __( 'Liberty (default, detailed)', 'prodev17-location-map' ),
			'bright'   => __( 'Bright (vivid, detailed)', 'prodev17-location-map' ),
			'positron' => __( 'Positron (light, minimal)', 'prodev17-location-map' ),
			'dark'     => __( 'Dark (dark)', 'prodev17-location-map' ),
			'fiord'    => __( 'Fiord (cool, muted)', 'prodev17-location-map' ),
		);
	}

	/**
	 * Available palettes.
	 *
	 * @return array
	 */
	public static function palettes() {
		return array(
			'original' => __( 'Original (as in OpenFreeMap)', 'prodev17-location-map' ),
			'google'   => __( 'Bright and detailed', 'prodev17-location-map' ),
			'yandex'   => __( 'Warm and muted', 'prodev17-location-map' ),
		);
	}

	/**
	 * Width presets.
	 *
	 * @return array
	 */
	public static function width_presets() {
		return array(
			'block'  => __( 'Block (container width)', 'prodev17-location-map' ),
			'full'   => __( 'Full width (window width)', 'prodev17-location-map' ),
			'custom' => __( 'Custom (value and unit)', 'prodev17-location-map' ),
		);
	}

	/**
	 * Zoom modes.
	 *
	 * @return array
	 */
	public static function zoom_modes() {
		return array(
			'manual' => __( 'Fixed zoom', 'prodev17-location-map' ),
			'fit'    => __( 'Fit all markers (auto)', 'prodev17-location-map' ),
		);
	}

	/**
	 * Map style color categories.
	 *
	 * The key is the category identifier (matching the JS override key), `label`
	 * is the human label, `color` is the default color and `opacity` controls
	 * whether the opacity control is shown.
	 *
	 * @return array
	 */
	public static function style_categories() {
		return array(
			'land'           => array(
				'label'   => __( 'Map background / land', 'prodev17-location-map' ),
				'color'   => '#f5f5f5',
				'opacity' => true,
			),
			'water'          => array(
				'label'   => __( 'Water', 'prodev17-location-map' ),
				'color'   => '#aadaff',
				'opacity' => true,
			),
			'waterway'       => array(
				'label'   => __( 'Rivers and canals', 'prodev17-location-map' ),
				'color'   => '#a5d5f7',
				'opacity' => true,
			),
			'park'           => array(
				'label'   => __( 'Parks and greenery', 'prodev17-location-map' ),
				'color'   => '#c9f0c4',
				'opacity' => true,
			),
			'forest'         => array(
				'label'   => __( 'Forest', 'prodev17-location-map' ),
				'color'   => '#b4e6a8',
				'opacity' => true,
			),
			'sand'           => array(
				'label'   => __( 'Sand and beaches', 'prodev17-location-map' ),
				'color'   => '#f2e9c9',
				'opacity' => true,
			),
			'ice'            => array(
				'label'   => __( 'Ice and glaciers', 'prodev17-location-map' ),
				'color'   => '#ffffff',
				'opacity' => true,
			),
			'building'       => array(
				'label'   => __( 'Buildings (2D)', 'prodev17-location-map' ),
				'color'   => '#e4e4e4',
				'opacity' => true,
			),
			'road'           => array(
				'label'   => __( 'Roads', 'prodev17-location-map' ),
				'color'   => '#ffffff',
				'opacity' => true,
			),
			'road_casing'    => array(
				'label'   => __( 'Road casing', 'prodev17-location-map' ),
				'color'   => '#d9d9d9',
				'opacity' => true,
			),
			'highway'        => array(
				'label'   => __( 'Highways', 'prodev17-location-map' ),
				'color'   => '#ffd166',
				'opacity' => true,
			),
			'highway_casing' => array(
				'label'   => __( 'Highway casing', 'prodev17-location-map' ),
				'color'   => '#e6b84a',
				'opacity' => true,
			),
			'rail'           => array(
				'label'   => __( 'Rail and transit', 'prodev17-location-map' ),
				'color'   => '#d0d0d0',
				'opacity' => true,
			),
			'boundary'       => array(
				'label'   => __( 'Borders', 'prodev17-location-map' ),
				'color'   => '#a3a3a3',
				'opacity' => false,
			),
			'text'           => array(
				'label'   => __( 'Labels (text)', 'prodev17-location-map' ),
				'color'   => '#5f6368',
				'opacity' => false,
			),
			'text_halo'      => array(
				'label'   => __( 'Label halo', 'prodev17-location-map' ),
				'color'   => '#ffffff',
				'opacity' => false,
			),
			'poi'            => array(
				'label'   => __( 'POI icons', 'prodev17-location-map' ),
				'color'   => '#5f6368',
				'opacity' => false,
			),
		);
	}

	/**
	 * Layer visibility toggles.
	 *
	 * @return array
	 */
	public static function style_toggles() {
		return array(
			'hide_poi'       => __( 'Hide POI (points of interest)', 'prodev17-location-map' ),
			'hide_buildings' => __( 'Hide buildings (2D)', 'prodev17-location-map' ),
			'hide_3d'        => __( 'Hide 3D buildings', 'prodev17-location-map' ),
			'hide_terrain'   => __( 'Hide terrain (raster layer)', 'prodev17-location-map' ),
			'hide_boundary'  => __( 'Hide borders', 'prodev17-location-map' ),
			'hide_rail'      => __( 'Hide rail and transit', 'prodev17-location-map' ),
			'hide_labels'    => __( 'Hide all labels', 'prodev17-location-map' ),
		);
	}

	/**
	 * Default style color values (all categories disabled).
	 *
	 * @return array
	 */
	public static function default_style_colors() {
		$out = array();
		foreach ( self::style_categories() as $key => $cat ) {
			$out[ $key ] = array(
				'on'      => 0,
				'color'   => $cat['color'],
				'opacity' => 100,
			);
		}
		return $out;
	}

	/**
	 * Build the style override block for the map config.
	 *
	 * @param array $a Parameters.
	 * @return array
	 */
	public static function build_overrides( $a ) {
		$colors = array();
		$stored = isset( $a['style_colors'] ) && is_array( $a['style_colors'] ) ? $a['style_colors'] : array();

		foreach ( self::style_categories() as $key => $cat ) {
			$v = isset( $stored[ $key ] ) ? $stored[ $key ] : null;
			if ( empty( $v['on'] ) ) {
				continue;
			}
			$col = isset( $v['color'] ) ? sanitize_hex_color( $v['color'] ) : '';
			if ( ! $col ) {
				$col = $cat['color'];
			}
			$op             = isset( $v['opacity'] ) ? max( 0, min( 100, (int) $v['opacity'] ) ) : 100;
			$colors[ $key ] = array(
				'color'   => $col,
				'opacity' => $op,
			);
		}

		return array(
			'colors'         => $colors,
			'hidePoi'        => self::truthy( isset( $a['hide_poi'] ) ? $a['hide_poi'] : 0 ),
			'hideBuildings'  => self::truthy( isset( $a['hide_buildings'] ) ? $a['hide_buildings'] : 0 ),
			'hide3d'         => self::truthy( isset( $a['hide_3d'] ) ? $a['hide_3d'] : 0 ),
			'hideTerrain'    => self::truthy( isset( $a['hide_terrain'] ) ? $a['hide_terrain'] : 0 ),
			'hideBoundary'   => self::truthy( isset( $a['hide_boundary'] ) ? $a['hide_boundary'] : 0 ),
			'hideRail'       => self::truthy( isset( $a['hide_rail'] ) ? $a['hide_rail'] : 0 ),
			'hideLabels'     => self::truthy( isset( $a['hide_labels'] ) ? $a['hide_labels'] : 0 ),
			'terrainOpacity' => isset( $a['terrain_opacity'] ) ? max( 0, min( 100, (int) $a['terrain_opacity'] ) ) : 100,
		);
	}

	/**
	 * Default values for the on-map search field styling.
	 *
	 * @return array
	 */
	public static function search_style_defaults() {
		return array(
			'bg'      => '#ffffff',
			'text'    => '#333333',
			'border'  => '#cccccc',
			'btnBg'   => '#1a73e8',
			'btnText' => '#ffffff',
			'radius'  => 4,
		);
	}

	/**
	 * On-map search field styling from parameters.
	 *
	 * @param array $a Parameters.
	 * @return array
	 */
	public static function build_search_style( $a ) {
		$d = self::search_style_defaults();
		$g = function ( $key, $def ) use ( $a ) {
			$v = isset( $a[ $key ] ) ? sanitize_hex_color( $a[ $key ] ) : '';
			return $v ? $v : $def;
		};

		return array(
			'bg'      => $g( 'search_bg', $d['bg'] ),
			'text'    => $g( 'search_text', $d['text'] ),
			'border'  => $g( 'search_border', $d['border'] ),
			'btnBg'   => $g( 'search_btn_bg', $d['btnBg'] ),
			'btnText' => $g( 'search_btn_text', $d['btnText'] ),
			'radius'  => isset( $a['search_radius'] ) ? max( 0, min( 24, (int) $a['search_radius'] ) ) : $d['radius'],
		);
	}

	/**
	 * Map label languages (switched from OpenMapTiles fields).
	 *
	 * @return array
	 */
	public static function map_languages() {
		return array(
			'auto' => __( 'Auto — leave labels as in the tiles', 'prodev17-location-map' ),
			'ru'   => __( 'Russian', 'prodev17-location-map' ),
			'be'   => __( 'Belarusian', 'prodev17-location-map' ),
			'en'   => __( 'English', 'prodev17-location-map' ),
			'uk'   => __( 'Ukrainian', 'prodev17-location-map' ),
			'de'   => __( 'German', 'prodev17-location-map' ),
			'fr'   => __( 'French', 'prodev17-location-map' ),
			'pl'   => __( 'Polish', 'prodev17-location-map' ),
			'lt'   => __( 'Lithuanian', 'prodev17-location-map' ),
			'lv'   => __( 'Latvian', 'prodev17-location-map' ),
			'es'   => __( 'Spanish', 'prodev17-location-map' ),
			'it'   => __( 'Italian', 'prodev17-location-map' ),
			'kk'   => __( 'Kazakh', 'prodev17-location-map' ),
			'zh'   => __( 'Chinese', 'prodev17-location-map' ),
		);
	}

	/**
	 * Register assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'maplibre-gl',
			OFFICE_MAP_URL . 'assets/vendor/maplibre-gl/maplibre-gl.css',
			array(),
			'5.24.0'
		);
		wp_register_script(
			'maplibre-gl',
			OFFICE_MAP_URL . 'assets/vendor/maplibre-gl/maplibre-gl.js',
			array(),
			'5.24.0',
			true
		);

		wp_register_style(
			'office-map',
			OFFICE_MAP_URL . 'assets/css/office-map.css',
			array( 'maplibre-gl' ),
			OFFICE_MAP_VERSION
		);
		wp_register_script(
			'office-map',
			OFFICE_MAP_URL . 'assets/js/office-map.js',
			array( 'maplibre-gl' ),
			OFFICE_MAP_VERSION,
			true
		);
		wp_register_script(
			'office-map-admin',
			OFFICE_MAP_URL . 'assets/js/office-map-admin.js',
			array( 'jquery', 'office-map', 'wp-color-picker' ),
			OFFICE_MAP_VERSION,
			true
		);
	}

	/**
	 * Enqueue the map assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'maplibre-gl' );
		wp_enqueue_style( 'office-map' );
		wp_enqueue_script( 'maplibre-gl' );
		wp_enqueue_script( 'office-map' );

		if ( ! self::$localized ) {
			self::$localized = true;
			wp_localize_script(
				'office-map',
				'OfficeMapData',
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'office_map_geocode' ),
					'i18n'    => array(
						'searchPlaceholder' => __( 'Search address…', 'prodev17-location-map' ),
						'searchButton'      => __( 'Find', 'prodev17-location-map' ),
						'searching'         => __( 'Searching…', 'prodev17-location-map' ),
						'nothingFound'      => __( 'Nothing found', 'prodev17-location-map' ),
						'searchError'       => __( 'Search error', 'prodev17-location-map' ),
					),
				)
			);
		}
	}

	/**
	 * Value to bool.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function truthy( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( (string) $value ), array( '1', 'yes', 'true', 'on' ), true );
	}

	/**
	 * Attachment ID or URL to marker image URL.
	 *
	 * @param mixed $value Attachment ID or URL.
	 * @return string
	 */
	public static function resolve_image_url( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( ctype_digit( $value ) ) {
			$url = wp_get_attachment_image_url( (int) $value, 'full' );
			if ( ! $url ) {
				$url = wp_get_attachment_url( (int) $value );
			}
			return $url ? $url : '';
		}

		if ( ! preg_match( '#^https?://#i', $value ) ) {
			return '';
		}

		return esc_url_raw( $value );
	}

	/**
	 * Attachment ID or URL to a normalized value (ID or URL) for storage.
	 *
	 * @param mixed $value Attachment ID or URL.
	 * @return string
	 */
	public static function sanitize_image_value( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ctype_digit( $value ) ) {
			return (string) absint( $value );
		}
		if ( ! preg_match( '#^https?://#i', $value ) ) {
			return '';
		}
		return esc_url_raw( $value );
	}

	/**
	 * Normalize the list of points.
	 *
	 * @param mixed $points List.
	 * @return array
	 */
	public static function normalize_points( $points ) {
		$out = array();
		if ( ! is_array( $points ) ) {
			return $out;
		}

		foreach ( $points as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$lat = isset( $p['lat'] ) ? (float) str_replace( ',', '.', $p['lat'] ) : 0.0;
			$lng = isset( $p['lng'] ) ? (float) str_replace( ',', '.', $p['lng'] ) : 0.0;
			if ( ! $lat && ! $lng ) {
				continue;
			}
			$color = isset( $p['color'] ) ? sanitize_hex_color( $p['color'] ) : '';
			$out[] = array(
				'lat'     => $lat,
				'lng'     => $lng,
				'title'   => isset( $p['title'] ) ? sanitize_text_field( $p['title'] ) : '',
				'address' => isset( $p['address'] ) ? wp_kses_post( $p['address'] ) : '',
				'image'   => isset( $p['image'] ) ? self::sanitize_image_value( $p['image'] ) : '',
				'color'   => $color ? $color : '',
			);
		}

		return $out;
	}

	/**
	 * Build the map config. Used by both the shortcode and the AJAX preview.
	 *
	 * @param array $a Normalized parameters.
	 * @return array
	 */
	public static function make_config( $a ) {
		$a = wp_parse_args(
			$a,
			array(
				'lat'             => '',
				'lng'             => '',
				'zoom'            => '16',
				'height'          => '420',
				'width_preset'    => 'block',
				'width_value'     => '100',
				'width_unit'      => '%',
				'title'           => '',
				'address'         => '',
				'style'           => 'liberty',
				'palette'         => 'original',
				'zoom_mode'       => 'manual',
				'lang'            => 'auto',
				'marker'          => 'yes',
				'popup'           => 'yes',
				'scroll'          => 'yes',
				'color'           => '',
				'image'           => '',
				'fullscreen'      => 'no',
				'search'          => 'no',
				'points'          => array(),
				'style_colors'    => array(),
				'hide_poi'        => 0,
				'hide_buildings'  => 0,
				'hide_3d'         => 0,
				'hide_terrain'    => 0,
				'hide_boundary'   => 0,
				'hide_rail'       => 0,
				'hide_labels'     => 0,
				'terrain_opacity' => '100',
				'search_bg'       => '',
				'search_text'     => '',
				'search_border'   => '',
				'search_btn_bg'   => '',
				'search_btn_text' => '',
				'search_radius'   => '4',
			)
		);

		$base_styles = self::base_styles();
		$style       = isset( $base_styles[ $a['style'] ] ) ? $a['style'] : 'liberty';

		$palettes = self::palettes();
		$palette  = isset( $palettes[ $a['palette'] ] ) ? $a['palette'] : 'original';

		$lat    = (float) $a['lat'];
		$lng    = (float) $a['lng'];
		$zoom   = max( 1, min( 20, (float) $a['zoom'] ) );
		$height = max( 120, (int) $a['height'] );

		$width_presets = self::width_presets();
		$width_preset  = isset( $width_presets[ $a['width_preset'] ] ) ? $a['width_preset'] : 'block';
		$width_value   = (float) $a['width_value'];
		if ( $width_value <= 0 ) {
			$width_value = 100;
		}
		$width_unit = ( 'px' === $a['width_unit'] ) ? 'px' : '%';

		$zoom_modes = self::zoom_modes();
		$zoom_mode  = isset( $zoom_modes[ $a['zoom_mode'] ] ) ? $a['zoom_mode'] : 'manual';

		$languages = self::map_languages();
		$lang      = isset( $languages[ $a['lang'] ] ) ? $a['lang'] : 'auto';

		$color = sanitize_hex_color( $a['color'] );
		if ( ! $color ) {
			$color = '#e63946';
		}

		$image  = self::resolve_image_url( $a['image'] );
		$marker = self::truthy( $a['marker'] );

		// Marker list: the main marker plus additional points.
		$markers = array();
		if ( $marker ) {
			if ( $lat || $lng ) {
				$markers[] = array(
					'lat'     => $lat,
					'lng'     => $lng,
					'title'   => wp_strip_all_tags( $a['title'] ),
					'address' => wp_kses_post( (string) $a['address'] ),
					'open'    => self::truthy( $a['popup'] ),
				);
			}
			foreach ( self::normalize_points( $a['points'] ) as $p ) {
				$markers[] = array(
					'lat'     => $p['lat'],
					'lng'     => $p['lng'],
					'title'   => $p['title'],
					'address' => $p['address'],
					'open'    => false,
					'image'   => $p['image'] ? self::resolve_image_url( $p['image'] ) : '',
					'color'   => $p['color'],
				);
			}
		}

		return array(
			'lat'         => $lat,
			'lng'         => $lng,
			'zoom'        => $zoom,
			'height'      => $height,
			'width'       => array(
				'preset' => $width_preset,
				'value'  => $width_value,
				'unit'   => $width_unit,
			),
			'zoomMode'    => $zoom_mode,
			'baseStyle'   => $style,
			'palette'     => $palette,
			'lang'        => $lang,
			'title'       => wp_strip_all_tags( $a['title'] ),
			'address'     => wp_kses_post( (string) $a['address'] ),
			'marker'      => $marker,
			'markerColor' => $color,
			'markerImage' => $marker ? $image : '',
			'markers'     => $markers,
			'scroll'      => self::truthy( $a['scroll'] ),
			'fullscreen'  => self::truthy( $a['fullscreen'] ),
			'search'      => self::truthy( $a['search'] ),
			'overrides'   => self::build_overrides( $a ),
			'searchStyle' => self::build_search_style( $a ),
		);
	}

	/**
	 * The [office_map] shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$opt = self::get_options();

		$a = shortcode_atts(
			array(
				'lat'        => $opt['lat'],
				'lng'        => $opt['lng'],
				'zoom'       => $opt['zoom'],
				'height'     => $opt['height'],
				'title'      => $opt['title'],
				'address'    => $opt['address'],
				'style'      => $opt['base_style'],
				'palette'    => $opt['palette'],
				'zoom_mode'  => $opt['zoom_mode'],
				'lang'       => $opt['map_lang'],
				'marker'     => $opt['marker'] ? 'yes' : 'no',
				'popup'      => $opt['popup'] ? 'yes' : 'no',
				'scroll'     => $opt['scroll'] ? 'yes' : 'no',
				'color'      => $opt['marker_color'],
				'image'      => $opt['marker_image'],
				'fullscreen' => $opt['fullscreen'] ? 'yes' : 'no',
				'search'     => $opt['search'] ? 'yes' : 'no',
				'preview'    => 'no',
			),
			$atts,
			'office_map'
		);

		$a['width_preset'] = $opt['width_preset'];
		$a['width_value']  = $opt['width_value'];
		$a['width_unit']   = $opt['width_unit'];
		$a['points']       = $opt['points'];

		$a['style_colors']    = $opt['style_colors'];
		$a['hide_poi']        = $opt['hide_poi'];
		$a['hide_buildings']  = $opt['hide_buildings'];
		$a['hide_3d']         = $opt['hide_3d'];
		$a['hide_terrain']    = $opt['hide_terrain'];
		$a['hide_boundary']   = $opt['hide_boundary'];
		$a['hide_rail']       = $opt['hide_rail'];
		$a['hide_labels']     = $opt['hide_labels'];
		$a['terrain_opacity'] = $opt['terrain_opacity'];
		$a['search_bg']       = $opt['search_bg'];
		$a['search_text']     = $opt['search_text'];
		$a['search_border']   = $opt['search_border'];
		$a['search_btn_bg']   = $opt['search_btn_bg'];
		$a['search_btn_text'] = $opt['search_btn_text'];
		$a['search_radius']   = $opt['search_radius'];

		$config     = self::make_config( $a );
		$is_preview = self::truthy( $a['preview'] );

		$this->enqueue_assets();

		$id = 'office-map-' . ( ++self::$counter );

		$classes = 'office-map';
		if ( 'full' === $config['width']['preset'] && ! $is_preview ) {
			$classes .= ' office-map--full';
		}
		if ( $is_preview ) {
			$classes .= ' office-map--preview';
		}

		return sprintf(
			'<div id="%1$s" class="%2$s" data-config="%3$s" style="%4$s"></div>',
			esc_attr( $id ),
			$classes,
			esc_attr( wp_json_encode( $config ) ),
			esc_attr( $this->container_style( $config, $is_preview ) )
		);
	}

	/**
	 * Inline style for the map container.
	 *
	 * @param array $config  Config.
	 * @param bool  $preview Preview mode (do not stretch to full width).
	 * @return string
	 */
	private function container_style( $config, $preview = false ) {
		$w      = $config['width'];
		$preset = ( $preview && 'full' === $w['preset'] ) ? 'block' : $w['preset'];

		if ( 'full' === $preset ) {
			$width = 'width:100vw;max-width:100vw;margin-left:calc(50% - 50vw);';
		} elseif ( 'custom' === $preset ) {
			$width = 'width:' . $w['value'] . $w['unit'] . ';';
		} else {
			$width = 'width:100%;';
		}

		return 'height:' . (int) $config['height'] . 'px;' . $width;
	}

	/**
	 * AJAX proxy for the Nominatim geocoder.
	 *
	 * @return void
	 */
	public function ajax_geocode() {
		check_ajax_referer( 'office_map_geocode', 'nonce' );

		$q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $q ) < 3 : strlen( $q ) < 3 ) {
			wp_send_json_success( array() );
		}

		// Rate limit: one request per second per IP and globally (Nominatim policy).
		$ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip_key    = 'office_map_geo_ip_' . md5( $ip );
		$limit_key = 'office_map_geo_limit';
		if ( get_transient( $ip_key ) || get_transient( $limit_key ) ) {
			wp_send_json_error( 'rate_limited', 429 );
		}
		set_transient( $ip_key, 1, 1 );
		set_transient( $limit_key, 1, 1 );

		// Cache the result for one day.
		$cache_key = 'office_map_geo_q_' . md5( strtolower( $q ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			wp_send_json_success( $cached );
		}

		$results = $this->geocode_query( $q );

		if ( $results ) {
			set_transient( $cache_key, $results, DAY_IN_SECONDS );
		}

		wp_send_json_success( $results );
	}

	/**
	 * Geocoder request: Nominatim (OpenStreetMap).
	 *
	 * @param string $q Search query.
	 * @return array List of ['label','lat','lng'].
	 */
	private function geocode_query( $q ) {
		// Geocoder response language: the map language, otherwise the site language.
		$opt  = self::get_options();
		$lang = ( 'auto' !== $opt['map_lang'] ) ? $opt['map_lang'] : substr( (string) get_locale(), 0, 2 );

		$contact = sanitize_email( get_option( 'admin_email' ) );
		$ua      = 'PD17 Map WordPress Plugin (' . home_url( '/' ) . ( $contact ? '; ' . $contact : '' ) . ')';
		$out     = array();

		// Primary: Nominatim (OpenStreetMap).
		$args = array(
			'q'              => $q,
			'format'         => 'jsonv2',
			'limit'          => 5,
			'addressdetails' => 1,
		);
		if ( 2 === strlen( $lang ) ) {
			$args['accept-language'] = $lang;
		}

		$response = wp_remote_get(
			add_query_arg( $args, 'https://nominatim.openstreetmap.org/search' ),
			array(
				'timeout' => 8,
				'headers' => array(
					'User-Agent' => $ua,
					'Accept'     => 'application/json',
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $body ) ) {
				foreach ( $body as $row ) {
					if ( ! isset( $row['lat'], $row['lon'] ) ) {
						continue;
					}
					$label = isset( $row['display_name'] ) ? $row['display_name'] : '';
					$out[] = array(
						'label' => $label ? $label : sprintf( '%.5f, %.5f', $row['lat'], $row['lon'] ),
						'lat'   => (float) $row['lat'],
						'lng'   => (float) $row['lon'],
					);
				}
			}
		}

		return $out;
	}
}
