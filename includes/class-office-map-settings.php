<?php
/**
 * Plugin settings page.
 *
 * @package Office_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Office_Map_Settings
 */
class Office_Map_Settings {

	/**
	 * Singleton.
	 *
	 * @var Office_Map_Settings|null
	 */
	private static $instance = null;

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	private $slug = 'office-map';

	/**
	 * Cache of field descriptions.
	 *
	 * @var array|null
	 */
	private $fields_cache = null;

	/**
	 * Get the instance.
	 *
	 * @return Office_Map_Settings
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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_ajax_office_map_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_office_map_save', array( $this, 'ajax_save' ) );
		add_action( 'admin_post_office_map_restore', array( $this, 'handle_restore' ) );
		add_action( 'admin_post_office_map_restore_lastgood', array( $this, 'handle_restore_lastgood' ) );
		add_action( 'admin_post_office_map_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_office_map_import', array( $this, 'handle_import' ) );
	}

	/**
	 * Admin menu entry.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'PD17 Map', 'prodev17-location-map' ),
			__( 'PD17 Map', 'prodev17-location-map' ),
			'manage_options',
			$this->slug,
			array( $this, 'render_page' ),
			'dashicons-location-alt',
			30
		);
	}

	/**
	 * Assets on the settings page.
	 *
	 * @param string $hook Current hook.
	 * @return void
	 */
	public function admin_assets( $hook ) {
		if ( 'toplevel_page_' . $this->slug !== $hook ) {
			return;
		}

		Office_Map::instance()->enqueue_assets();

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'office-map-admin', OFFICE_MAP_URL . 'assets/css/office-map-admin.css', array(), OFFICE_MAP_VERSION );
		wp_enqueue_script( 'office-map-admin' );

		wp_localize_script(
			'office-map-admin',
			'OfficeMapAdmin',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'office_map_preview' ),
				'i18n'    => array(
					'mediaTitle'        => __( 'Marker image', 'prodev17-location-map' ),
					'mediaButton'       => __( 'Use image', 'prodev17-location-map' ),
					'searchPlaceholder' => __( 'Search address…', 'prodev17-location-map' ),
					'searchButton'      => __( 'Find', 'prodev17-location-map' ),
					'searching'         => __( 'Searching…', 'prodev17-location-map' ),
					'nothingFound'      => __( 'Nothing found', 'prodev17-location-map' ),
					'searchError'       => __( 'Search error', 'prodev17-location-map' ),
					'copied'            => __( 'Copied!', 'prodev17-location-map' ),
					'defaultTitle'      => __( 'Our office', 'prodev17-location-map' ),
					'defaultAddress'    => __( 'Add the office address', 'prodev17-location-map' ),
				),
			)
		);
	}

	/**
	 * Register the option (saved through the Settings API).
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'office_map_group',
			'office_map_options',
			array( 'sanitize_callback' => array( $this, 'sanitize' ) )
		);
	}

	/**
	 * All field descriptions.
	 *
	 * @return array
	 */
	private function fields() {
		if ( null !== $this->fields_cache ) {
			return $this->fields_cache;
		}

		$units = array(
			'%'  => __( 'Percent (%)', 'prodev17-location-map' ),
			'px' => __( 'Pixels (px)', 'prodev17-location-map' ),
		);

		$this->fields_cache = array(
			// Location.
			'search_address'  => array(
				'label' => __( 'Search by address', 'prodev17-location-map' ),
				'type'  => 'search',
				'help'  => __( 'Type an address — the coordinates will be filled in automatically.', 'prodev17-location-map' ),
			),
			'lat'             => array(
				'label' => __( 'Latitude (lat)', 'prodev17-location-map' ),
				'type'  => 'number',
				'help'  => __( 'For example 53.900600.', 'prodev17-location-map' ),
			),
			'lng'             => array(
				'label' => __( 'Longitude (lng)', 'prodev17-location-map' ),
				'type'  => 'number',
				'help'  => __( 'For example 27.559000.', 'prodev17-location-map' ),
			),

			// Size and zoom.
			'height'          => array(
				'label' => __( 'Height, px', 'prodev17-location-map' ),
				'type'  => 'number',
				'help'  => '',
			),
			'zoom'            => array(
				'label' => __( 'Zoom', 'prodev17-location-map' ),
				'type'  => 'number',
				'help'  => __( '1–20; for an office usually 15–18.', 'prodev17-location-map' ),
				'cond'  => array(
					'field' => 'zoom_mode',
					'value' => 'manual',
				),
			),
			'width_preset'    => array(
				'label'   => __( 'Width', 'prodev17-location-map' ),
				'type'    => 'select',
				'choices' => Office_Map::width_presets(),
			),
			'width_value'     => array(
				'label' => __( 'Value', 'prodev17-location-map' ),
				'type'  => 'number',
				'help'  => __( 'For the “Custom” preset.', 'prodev17-location-map' ),
			),
			'width_unit'      => array(
				'label'   => __( 'Unit', 'prodev17-location-map' ),
				'type'    => 'select',
				'choices' => $units,
			),
			'zoom_mode'       => array(
				'label'   => __( 'Zoom mode', 'prodev17-location-map' ),
				'type'    => 'select',
				'choices' => Office_Map::zoom_modes(),
			),

			// Marker and popup.
			'title'           => array(
				'label' => __( 'Marker title', 'prodev17-location-map' ),
				'type'  => 'text',
				'help'  => '',
			),
			'address'         => array(
				'label' => __( 'Address (HTML allowed)', 'prodev17-location-map' ),
				'type'  => 'textarea',
				'help'  => __( 'Allowed HTML tags: <br>, <strong>, <em>, <a href="…">, <span style="…"> and others. Line breaks are preserved.', 'prodev17-location-map' ),
			),
			'marker_image'    => array(
				'label' => __( 'Custom marker', 'prodev17-location-map' ),
				'type'  => 'image',
				'help'  => __( 'PNG/SVG with the tip at the bottom. Used by default for all markers; each extra point can have its own icon.', 'prodev17-location-map' ),
			),
			'marker_color'    => array(
				'label' => __( 'Default marker color', 'prodev17-location-map' ),
				'type'  => 'color',
				'help'  => __( 'Applied to markers without their own color.', 'prodev17-location-map' ),
			),

			// Additional markers.
			'points'          => array(
				'label' => __( 'Additional markers', 'prodev17-location-map' ),
				'type'  => 'points',
				'help'  => '',
			),

			// Appearance.
			'base_style'      => array(
				'label'   => __( 'Base style', 'prodev17-location-map' ),
				'type'    => 'select',
				'choices' => Office_Map::base_styles(),
			),
			'palette'         => array(
				'label'   => __( 'Palette', 'prodev17-location-map' ),
				'type'    => 'select',
				'choices' => Office_Map::palettes(),
			),
			'map_lang'        => array(
				'label'   => __( 'Map language', 'prodev17-location-map' ),
				'type'    => 'select',
				'choices' => Office_Map::map_languages(),
				'help'    => __( 'Language of feature labels and geocoder responses. “Auto” keeps the labels as they are in the tiles (usually the local language).', 'prodev17-location-map' ),
			),

			// Map styling.
			'style_colors'    => array(
				'label' => __( 'Layer colors', 'prodev17-location-map' ),
				'type'  => 'style_colors',
				'help'  => __( 'Enable the layers you need and set their color and opacity. Applied on top of the base style and palette.', 'prodev17-location-map' ),
			),
			'hide_poi'        => array(
				'label' => __( 'Hide POI (points of interest)', 'prodev17-location-map' ),
				'type'  => 'checkbox',
				'help'  => '',
			),
			'hide_buildings'  => array(
				'label' => __( 'Hide buildings (2D)', 'prodev17-location-map' ),
				'type'  => 'checkbox',
				'help'  => '',
			),
			'hide_3d'         => array(
				'label' => __( 'Hide 3D buildings', 'prodev17-location-map' ),
				'type'  => 'checkbox',
				'help'  => '',
			),
			'hide_terrain'    => array(
				'label' => __( 'Hide terrain (raster layer)', 'prodev17-location-map' ),
				'type'  => 'checkbox',
				'help'  => '',
			),
			'hide_boundary'   => array(
				'label' => __( 'Hide borders', 'prodev17-location-map' ),
				'type'  => 'checkbox',
				'help'  => '',
			),
			'hide_rail'       => array(
				'label' => __( 'Hide rail and transit', 'prodev17-location-map' ),
				'type'  => 'checkbox',
				'help'  => '',
			),
			'hide_labels'     => array(
				'label' => __( 'Hide all labels', 'prodev17-location-map' ),
				'type'  => 'checkbox',
				'help'  => '',
			),
			'terrain_opacity' => array(
				'label' => __( 'Terrain opacity, %', 'prodev17-location-map' ),
				'type'  => 'number',
				'help'  => __( '0 — terrain is invisible, 100 — fully visible.', 'prodev17-location-map' ),
			),

			// On-map search styling.
			'search_bg'       => array(
				'label' => __( 'Search field background', 'prodev17-location-map' ),
				'type'  => 'color',
				'help'  => '',
			),
			'search_text'     => array(
				'label' => __( 'Search field text', 'prodev17-location-map' ),
				'type'  => 'color',
				'help'  => '',
			),
			'search_border'   => array(
				'label' => __( 'Search field border', 'prodev17-location-map' ),
				'type'  => 'color',
				'help'  => '',
			),
			'search_btn_bg'   => array(
				'label' => __( '“Find” button — background', 'prodev17-location-map' ),
				'type'  => 'color',
				'help'  => '',
			),
			'search_btn_text' => array(
				'label' => __( '“Find” button — text', 'prodev17-location-map' ),
				'type'  => 'color',
				'help'  => '',
			),
			'search_radius'   => array(
				'label' => __( 'Search field corner radius, px', 'prodev17-location-map' ),
				'type'  => 'number',
				'help'  => __( '0–24.', 'prodev17-location-map' ),
			),

			// Behaviour.
			'marker'          => array(
				'label' => __( 'Show markers', 'prodev17-location-map' ),
				'type'  => 'checkbox',
				'help'  => '',
			),
			'popup'           => array(
				'label' => __( 'Open the popup right away', 'prodev17-location-map' ),
				'type'  => 'checkbox',
				'help'  => '',
			),
			'scroll'          => array(
				'label' => __( 'Zoom with the mouse wheel', 'prodev17-location-map' ),
				'type'  => 'checkbox',
				'help'  => '',
			),
			'fullscreen'      => array(
				'label' => __( '“Fullscreen” button', 'prodev17-location-map' ),
				'type'  => 'checkbox',
				'help'  => '',
			),
			'search'          => array(
				'label' => __( 'Search on the map', 'prodev17-location-map' ),
				'type'  => 'checkbox',
				'help'  => __( 'Uses the OpenStreetMap Nominatim geocoder. Off by default for privacy.', 'prodev17-location-map' ),
			),
		);

		return $this->fields_cache;
	}

	/**
	 * Sections and their rows (compact grid).
	 *
	 * @return array
	 */
	private function sections() {
		return array(
			array(
				'key'   => 'location',
				'title' => __( 'Main address', 'prodev17-location-map' ),
				'rows'  => array(
					array( 'search_address' ),
					array( 'lat', 'lng' ),
					array( 'heading' => __( 'Marker and popup', 'prodev17-location-map' ) ),
					array( 'title', 'marker_color' ),
					array( 'address' ),
					array( 'marker_image' ),
				),
			),
			array(
				'key'   => 'points',
				'title' => __( 'Additional markers', 'prodev17-location-map' ),
				'rows'  => array(
					array( 'points' ),
				),
			),
			array(
				'key'   => 'size',
				'title' => __( 'Size and zoom', 'prodev17-location-map' ),
				'rows'  => array(
					array( 'height', 'zoom' ),
					array( 'width_preset', 'zoom_mode' ),
					array(
						'fields' => array( 'width_value', 'width_unit' ),
						'cond'   => 'custom',
					),
				),
			),
			array(
				'key'   => 'appearance',
				'title' => __( 'Appearance', 'prodev17-location-map' ),
				'rows'  => array(
					array( 'base_style', 'palette' ),
					array( 'map_lang' ),
				),
			),
			array(
				'key'   => 'styling',
				'title' => __( 'Map styling', 'prodev17-location-map' ),
				'rows'  => array(
					array( 'style_colors' ),
					array( 'heading' => __( 'Layer visibility', 'prodev17-location-map' ) ),
					array( 'hide_poi', 'hide_buildings' ),
					array( 'hide_3d', 'hide_terrain' ),
					array( 'hide_boundary', 'hide_rail' ),
					array( 'hide_labels', 'terrain_opacity' ),
					array( 'heading' => __( 'Search on the map', 'prodev17-location-map' ) ),
					array( 'search_bg', 'search_text' ),
					array( 'search_border', 'search_radius' ),
					array( 'search_btn_bg', 'search_btn_text' ),
				),
			),
			array(
				'key'   => 'behaviour',
				'title' => __( 'Behaviour', 'prodev17-location-map' ),
				'rows'  => array(
					array( 'marker', 'popup' ),
					array( 'scroll', 'fullscreen' ),
					array( 'search' ),
				),
			),
		);
	}

	/**
	 * Section keys (in order).
	 *
	 * @return array
	 */
	private function section_keys() {
		return array_map(
			function ( $s ) {
				return $s['key'];
			},
			$this->sections()
		);
	}

	/**
	 * Which sections are open. First time — only the first one.
	 *
	 * @return array
	 */
	private function open_sections() {
		$keys = $this->section_keys();
		if ( empty( $keys ) ) {
			return array();
		}
		$opt = Office_Map::get_options();
		if ( ! array_key_exists( 'ui_open', $opt ) || null === $opt['ui_open'] ) {
			return array( $keys[0] );
		}
		$open = is_array( $opt['ui_open'] ) ? $opt['ui_open'] : array();
		return array_values( array_intersect( $open, $keys ) );
	}

	/**
	 * Render a section.
	 *
	 * @param array $section Section.
	 * @return void
	 */
	private function render_section( $section ) {
		$collapsed = ! in_array( $section['key'], $this->open_sections(), true );
		?>
		<div class="office-map-section<?php echo $collapsed ? ' is-collapsed' : ''; ?>" data-key="<?php echo esc_attr( $section['key'] ); ?>">
			<h2 class="office-map-section__toggle">
				<button type="button" aria-expanded="<?php echo $collapsed ? 'false' : 'true'; ?>">
					<span class="office-map-section__caret" aria-hidden="true">▾</span>
					<?php echo esc_html( $section['title'] ); ?>
				</button>
			</h2>
			<div class="office-map-section__body">
				<?php
				foreach ( $section['rows'] as $row ) {
					$this->render_row( $row );
				}
				?>
				<p class="office-map-section__actions">
					<button type="submit" class="button button-primary office-map-save"><?php esc_html_e( 'Save', 'prodev17-location-map' ); ?></button>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one grid row.
	 *
	 * @param array $row Row: a list of keys or an array ['fields'=>[], 'cond'=>string].
	 * @return void
	 */
	private function render_row( $row ) {
		if ( isset( $row['heading'] ) ) {
			echo '<h3 class="office-map-subhead">' . esc_html( $row['heading'] ) . '</h3>';
			return;
		}

		if ( isset( $row['fields'] ) ) {
			$keys     = $row['fields'];
			$cond_for = isset( $row['cond'] ) ? $row['cond'] : '';

			$hidden = true;
			if ( '' !== $cond_for ) {
				$opt    = Office_Map::get_options();
				$hidden = ( $opt['width_preset'] !== $cond_for );
			}

			echo '<div class="office-map-grid"' .
				( $hidden ? ' style="display:none"' : '' ) .
				' data-cond-width="1">';
			foreach ( $keys as $key ) {
				$this->render_item( $key );
			}
			echo '</div>';
			return;
		}

		echo '<div class="office-map-grid">';
		foreach ( $row as $key ) {
			$this->render_item( $key );
		}
		echo '</div>';
	}

	/**
	 * Render one grid item.
	 *
	 * @param string $key Field key.
	 * @return void
	 */
	private function render_item( $key ) {
		$f     = isset( $this->fields()[ $key ] ) ? $this->fields()[ $key ] : array(
			'label' => $key,
			'type'  => 'text',
		);
		$opt   = Office_Map::get_options();
		$value = isset( $opt[ $key ] ) ? $opt[ $key ] : '';
		$name  = 'office_map_options[' . $key . ']';
		$id    = 'office_map_' . $key;
		$type  = $f['type'];
		$help  = isset( $f['help'] ) ? $f['help'] : '';

		if ( 'checkbox' === $type ) {
			printf(
				'<div class="office-map-grid__item"><label class="office-map-checkbox"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label></div>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( $value, 1, false ),
				esc_html( $f['label'] )
			);
			return;
		}

		$cond_attr = '';
		if ( isset( $f['cond'] ) && is_array( $f['cond'] ) && isset( $f['cond']['field'], $f['cond']['value'] ) ) {
			$cf        = $f['cond']['field'];
			$cv        = (string) $f['cond']['value'];
			$cur       = isset( $opt[ $cf ] ) ? (string) $opt[ $cf ] : '';
			$cond_attr = ' data-cond-field="' . esc_attr( $cf ) . '" data-cond-value="' . esc_attr( $cv ) . '"'
				. ( $cur !== $cv ? ' style="display:none"' : '' );
		}

		echo '<div class="office-map-grid__item' . ( in_array( $type, array( 'search', 'points', 'textarea', 'style_colors' ), true ) ? ' office-map-grid__item--full' : '' ) . '"' . $cond_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $cond_attr is built with esc_attr().
		if ( ! in_array( $type, array( 'search', 'image', 'points', 'style_colors' ), true ) ) {
			printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $f['label'] ) );
		} else {
			echo '<div class="office-map-grid__label">' . esc_html( $f['label'] ) . '</div>';
		}
		$this->render_control( $type, $name, $id, $value, $f );
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Render a control.
	 *
	 * @param string $type  Type.
	 * @param string $name  `name` attribute.
	 * @param string $id    `id` attribute.
	 * @param mixed  $value Value.
	 * @param array  $f     Field description.
	 * @return void
	 */
	private function render_control( $type, $name, $id, $value, $f ) {
		switch ( $type ) {
			case 'color':
				printf(
					'<input type="text" class="office-map-color" id="%1$s" name="%2$s" value="%3$s" data-default-color="#e63946">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'image':
				$url = Office_Map::resolve_image_url( $value );
				echo '<div class="office-map-image-field">';
				printf( '<input type="hidden" class="office-map-image-input" id="%1$s" name="%2$s" value="%3$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				echo '<div class="office-map-marker-preview">';
				if ( $url ) {
					printf( '<img src="%s" alt="">', esc_url( $url ) );
				}
				echo '</div>';
				printf( '<button type="button" class="button office-map-upload" data-target="#%s">%s</button> ', esc_attr( $id ), esc_html__( 'Choose image', 'prodev17-location-map' ) );
				printf( '<button type="button" class="button office-map-remove" data-target="#%s">%s</button>', esc_attr( $id ), esc_html__( 'Remove', 'prodev17-location-map' ) );
				echo '</div>';
				break;

			case 'style_colors':
				$this->render_style_colors( is_array( $value ) ? $value : array(), $name );
				break;

			case 'search':
				echo '<div class="office-map-searchbox" data-lat="#office_map_lat" data-lng="#office_map_lng">';
				echo '<input type="text" class="office-map-search-input" placeholder="' . esc_attr__( 'For example: Minsk, Nezavisimosti Ave 1', 'prodev17-location-map' ) . '">';
				echo ' <button type="button" class="button office-map-search-btn">' . esc_html__( 'Find', 'prodev17-location-map' ) . '</button>';
				echo '<div class="office-map-search-results"></div>';
				echo '</div>';
				break;

			case 'points':
				$this->render_points( is_array( $value ) ? $value : array() );
				break;

			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $f['choices'] as $ck => $cl ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $ck ), selected( $value, $ck, false ), esc_html( $cl ) );
				}
				echo '</select>';
				break;

			case 'number':
				printf( '<input type="text" inputmode="decimal" id="%1$s" name="%2$s" value="%3$s" class="regular-text code">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;

			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="3" class="office-map-textarea">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( $value )
				);
				break;

			default:
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;
		}
	}

	/**
	 * Layer color styling table.
	 *
	 * @param array  $stored Stored values.
	 * @param string $name   Base field name.
	 * @return void
	 */
	private function render_style_colors( $stored, $name ) {
		$cats = Office_Map::style_categories();
		?>
		<div class="office-map-styles" id="office-map-style-colors">
			<table class="office-map-styles__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Layer', 'prodev17-location-map' ); ?></th>
						<th><?php esc_html_e( 'On', 'prodev17-location-map' ); ?></th>
						<th><?php esc_html_e( 'Color', 'prodev17-location-map' ); ?></th>
						<th><?php esc_html_e( 'Opacity, %', 'prodev17-location-map' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $cats as $key => $cat ) :
					$v     = ( isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ) ? $stored[ $key ] : array();
					$on    = ! empty( $v['on'] );
					$color = ( isset( $v['color'] ) && $v['color'] ) ? $v['color'] : $cat['color'];
					$op    = isset( $v['opacity'] ) ? (int) $v['opacity'] : 100;
					$base  = $name . '[' . $key . ']';
					$id    = 'office_map_style_colors_' . $key;
					?>
					<tr>
						<td class="office-map-styles__name"><?php echo esc_html( $cat['label'] ); ?></td>
						<td><input type="checkbox" class="office-map-style-on" name="<?php echo esc_attr( $base ); ?>[on]" value="1" <?php checked( $on, true ); ?>></td>
						<td><input type="text" class="office-map-color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $base ); ?>[color]" value="<?php echo esc_attr( $color ); ?>" data-default-color="<?php echo esc_attr( $cat['color'] ); ?>"></td>
						<td>
							<?php if ( ! empty( $cat['opacity'] ) ) : ?>
								<div class="office-map-opacity">
									<input type="range" min="0" max="100" step="1" class="office-map-opacity-range" value="<?php echo esc_attr( $op ); ?>" aria-label="<?php esc_attr_e( 'Opacity', 'prodev17-location-map' ); ?>">
									<input type="text" inputmode="numeric" class="small-text office-map-opacity-num" name="<?php echo esc_attr( $base ); ?>[opacity]" value="<?php echo esc_attr( $op ); ?>">
								</div>
							<?php else : ?>
								<span class="office-map-styles__na">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Tick “On” for a layer to apply its color. Changing a color or opacity turns the layer on automatically. Changes take effect after you press “Save” at the bottom of the block.', 'prodev17-location-map' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Repeater for additional points.
	 *
	 * @param array $points Points.
	 * @return void
	 */
	private function render_points( $points ) {
		$points = is_array( $points ) ? $points : array();

		echo '<div class="office-map-points" id="office-map-points">';
		foreach ( $points as $index => $point ) {
			$this->render_point_row( $index, $point );
		}
		echo '</div>';

		echo '<p><button type="button" class="button button-secondary" id="office-map-add-point">' . esc_html__( '+ Add marker', 'prodev17-location-map' ) . '</button></p>';

		echo '<div class="office-map-point-template" style="display:none">';
		$this->render_point_row( '__i__', array() );
		echo '</div>';
	}

	/**
	 * One point row.
	 *
	 * @param int|string $index Index (or __i__ for the template).
	 * @param array      $point Point data.
	 * @return void
	 */
	private function render_point_row( $index, $point ) {
		$lat     = isset( $point['lat'] ) ? $point['lat'] : '';
		$lng     = isset( $point['lng'] ) ? $point['lng'] : '';
		$title   = isset( $point['title'] ) ? $point['title'] : '';
		$address = isset( $point['address'] ) ? $point['address'] : '';
		$image   = isset( $point['image'] ) ? $point['image'] : '';
		$color   = isset( $point['color'] ) ? $point['color'] : '';
		$img_url = Office_Map::resolve_image_url( $image );
		$base    = 'office_map_options[points][' . $index . ']';
		$pid     = 'office_map_points_' . $index;
		?>
		<div class="office-map-point">
			<div class="office-map-point__coords">
				<input type="text" class="office-map-point-lat code" name="<?php echo esc_attr( $base ); ?>[lat]" value="<?php echo esc_attr( $lat ); ?>" placeholder="<?php esc_attr_e( 'Latitude', 'prodev17-location-map' ); ?>" inputmode="decimal">
				<input type="text" class="office-map-point-lng code" name="<?php echo esc_attr( $base ); ?>[lng]" value="<?php echo esc_attr( $lng ); ?>" placeholder="<?php esc_attr_e( 'Longitude', 'prodev17-location-map' ); ?>" inputmode="decimal">
			</div>
			<div class="office-map-searchbox">
				<input type="text" class="office-map-search-input" placeholder="<?php esc_attr_e( 'Search by address…', 'prodev17-location-map' ); ?>">
				<button type="button" class="button office-map-search-btn"><?php esc_html_e( 'Find', 'prodev17-location-map' ); ?></button>
				<div class="office-map-search-results"></div>
			</div>
			<input type="text" class="office-map-point-title" name="<?php echo esc_attr( $base ); ?>[title]" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Marker title', 'prodev17-location-map' ); ?>">
			<textarea class="office-map-point-address office-map-textarea" rows="2" name="<?php echo esc_attr( $base ); ?>[address]" placeholder="<?php esc_attr_e( 'Address in the popup (HTML allowed)', 'prodev17-location-map' ); ?>"><?php echo esc_textarea( $address ); ?></textarea>

			<div class="office-map-point__appearance">
				<div class="office-map-image-field office-map-point__icon">
					<div class="office-map-grid__label"><?php esc_html_e( 'Custom icon (optional)', 'prodev17-location-map' ); ?></div>
					<input type="hidden" class="office-map-image-input" id="<?php echo esc_attr( $pid ); ?>_image" name="<?php echo esc_attr( $base ); ?>[image]" value="<?php echo esc_attr( $image ); ?>">
					<div class="office-map-marker-preview">
						<?php if ( $img_url ) : ?>
							<img src="<?php echo esc_url( $img_url ); ?>" alt="">
						<?php endif; ?>
					</div>
					<button type="button" class="button office-map-upload" data-target="#<?php echo esc_attr( $pid ); ?>_image"><?php esc_html_e( 'Choose icon', 'prodev17-location-map' ); ?></button>
					<button type="button" class="button office-map-remove" data-target="#<?php echo esc_attr( $pid ); ?>_image"><?php esc_html_e( 'Remove', 'prodev17-location-map' ); ?></button>
				</div>

				<div class="office-map-point__color">
					<label class="office-map-grid__label" for="<?php echo esc_attr( $pid ); ?>_color"><?php esc_html_e( 'Marker color', 'prodev17-location-map' ); ?></label>
					<input type="text" class="office-map-color" id="<?php echo esc_attr( $pid ); ?>_color" name="<?php echo esc_attr( $base ); ?>[color]" value="<?php echo esc_attr( $color ); ?>" data-default-color="#e63946" placeholder="<?php esc_attr_e( 'default', 'prodev17-location-map' ); ?>">
				</div>
			</div>

			<button type="button" class="button office-map-point-remove"><?php esc_html_e( 'Delete', 'prodev17-location-map' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Sanitize the settings.
	 *
	 * @param array $input Input data.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? wp_unslash( $input ) : array();

		// Guard against empty/broken form submissions: do not overwrite saved settings.
		$existing = get_option( 'office_map_options', array() );
		$has_form = isset( $input['lat'] ) || isset( $input['lng'] ) || isset( $input['base_style'] )
			|| ( isset( $input['points'] ) && is_array( $input['points'] ) && count( $input['points'] ) );
		if ( ! $has_form && is_array( $existing ) && ! empty( $existing ) ) {
			return $existing;
		}

		// Back up the previous settings (for rollback).
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			update_option( 'office_map_options_backup', $existing, false );
			if ( ! empty( $existing['points'] ) ) {
				update_option( 'office_map_options_lastgood', $existing, false );
			}
		}

		$defaults    = Office_Map::defaults();
		$base_styles = Office_Map::base_styles();
		$palettes    = Office_Map::palettes();
		$width_units = Office_Map::width_presets();

		$out = array();

		$out['lat']  = isset( $input['lat'] ) ? (string) floatval( str_replace( ',', '.', $input['lat'] ) ) : $defaults['lat'];
		$out['lng']  = isset( $input['lng'] ) ? (string) floatval( str_replace( ',', '.', $input['lng'] ) ) : $defaults['lng'];
		$out['zoom'] = isset( $input['zoom'] ) ? (string) max( 1, min( 20, (int) $input['zoom'] ) ) : $defaults['zoom'];

		$height        = isset( $input['height'] ) ? (int) $input['height'] : (int) $defaults['height'];
		$out['height'] = (string) max( 120, min( 2000, $height ) );

		$out['width_preset'] = ( isset( $input['width_preset'] ) && isset( $width_units[ $input['width_preset'] ] ) ) ? $input['width_preset'] : $defaults['width_preset'];
		$wv                  = isset( $input['width_value'] ) ? (float) str_replace( ',', '.', $input['width_value'] ) : (float) $defaults['width_value'];
		$out['width_value']  = (string) max( 1, min( 10000, $wv ) );
		$out['width_unit']   = ( isset( $input['width_unit'] ) && in_array( $input['width_unit'], array( '%', 'px' ), true ) ) ? $input['width_unit'] : $defaults['width_unit'];

		$zoom_modes       = Office_Map::zoom_modes();
		$out['zoom_mode'] = ( isset( $input['zoom_mode'] ) && isset( $zoom_modes[ $input['zoom_mode'] ] ) ) ? $input['zoom_mode'] : $defaults['zoom_mode'];

		$out['title']   = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : $defaults['title'];
		$out['address'] = isset( $input['address'] ) ? wp_kses_post( $input['address'] ) : $defaults['address'];

		$out['base_style'] = ( isset( $input['base_style'] ) && isset( $base_styles[ $input['base_style'] ] ) ) ? $input['base_style'] : $defaults['base_style'];
		$out['palette']    = ( isset( $input['palette'] ) && isset( $palettes[ $input['palette'] ] ) ) ? $input['palette'] : $defaults['palette'];

		$languages       = Office_Map::map_languages();
		$out['map_lang'] = ( isset( $input['map_lang'] ) && isset( $languages[ $input['map_lang'] ] ) ) ? $input['map_lang'] : $defaults['map_lang'];

		$color               = isset( $input['marker_color'] ) ? sanitize_hex_color( $input['marker_color'] ) : '';
		$out['marker_color'] = $color ? $color : $defaults['marker_color'];

		$out['marker_image'] = $this->sanitize_image( isset( $input['marker_image'] ) ? $input['marker_image'] : '' );

		$out['points'] = Office_Map::normalize_points( isset( $input['points'] ) ? $input['points'] : array() );

		$out['marker']     = empty( $input['marker'] ) ? 0 : 1;
		$out['popup']      = empty( $input['popup'] ) ? 0 : 1;
		$out['scroll']     = empty( $input['scroll'] ) ? 0 : 1;
		$out['fullscreen'] = empty( $input['fullscreen'] ) ? 0 : 1;
		$out['search']     = empty( $input['search'] ) ? 0 : 1;

		// Map styling: layer colors.
		$cats  = Office_Map::style_categories();
		$in_sc = ( isset( $input['style_colors'] ) && is_array( $input['style_colors'] ) ) ? $input['style_colors'] : array();
		$sc    = array();
		foreach ( $cats as $key => $cat ) {
			$v          = ( isset( $in_sc[ $key ] ) && is_array( $in_sc[ $key ] ) ) ? $in_sc[ $key ] : array();
			$col        = isset( $v['color'] ) ? sanitize_hex_color( $v['color'] ) : '';
			$op         = ( isset( $v['opacity'] ) && '' !== $v['opacity'] && is_numeric( $v['opacity'] ) )
				? max( 0, min( 100, (int) $v['opacity'] ) )
				: 100;
			$sc[ $key ] = array(
				'on'      => empty( $v['on'] ) ? 0 : 1,
				'color'   => $col ? $col : $cat['color'],
				'opacity' => $op,
			);
		}
		$out['style_colors'] = $sc;

		// Map styling: layer visibility.
		foreach ( Office_Map::style_toggles() as $key => $label ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}
		$out['terrain_opacity'] = isset( $input['terrain_opacity'] )
			? (string) max( 0, min( 100, (int) $input['terrain_opacity'] ) )
			: $defaults['terrain_opacity'];

		// On-map search styling.
		foreach ( array( 'search_bg', 'search_text', 'search_border', 'search_btn_bg', 'search_btn_text' ) as $key ) {
			$sc          = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
			$out[ $key ] = $sc ? $sc : $defaults[ $key ];
		}
		$out['search_radius'] = isset( $input['search_radius'] )
			? (string) max( 0, min( 24, (int) $input['search_radius'] ) )
			: $defaults['search_radius'];

		// Open/closed state of the blocks.
		if ( ! isset( $input['ui_open'] ) ) {
			$out['ui_open'] = ( is_array( $existing ) && array_key_exists( 'ui_open', $existing ) ) ? $existing['ui_open'] : null;
		} else {
			$in_open = $input['ui_open'];
			if ( is_array( $in_open ) ) {
				$open = $in_open;
			} else {
				$open = array_filter( array_map( 'trim', explode( ',', (string) $in_open ) ) );
			}
			$out['ui_open'] = array_values( array_intersect( $open, $this->section_keys() ) );
		}

		return $out;
	}

	/**
	 * Sanitize the marker value (attachment ID or URL).
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function sanitize_image( $value ) {
		return Office_Map::sanitize_image_value( $value );
	}

	/**
	 * Read and clean the settings from the request.
	 *
	 * @return array
	 */
	private function read_clean_input() {
		// The data is cleaned in sanitize() (which also removes slashes).
		$raw = isset( $_POST['options'] ) ? $_POST['options'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.NonceVerification.Missing -- Nonce checked by the callers; slashes removed and data sanitized in sanitize().
		$raw = is_array( $raw ) ? $raw : array();
		return $this->sanitize( $raw );
	}

	/**
	 * Build the map config from cleaned settings (for AJAX).
	 *
	 * @param array $clean Cleaned settings.
	 * @return array
	 */
	private function config_from_clean( $clean ) {
		return Office_Map::make_config(
			array(
				'lat'             => $clean['lat'],
				'lng'             => $clean['lng'],
				'zoom'            => $clean['zoom'],
				'height'          => $clean['height'],
				'width_preset'    => $clean['width_preset'],
				'width_value'     => $clean['width_value'],
				'width_unit'      => $clean['width_unit'],
				'zoom_mode'       => $clean['zoom_mode'],
				'title'           => $clean['title'],
				'address'         => $clean['address'],
				'style'           => $clean['base_style'],
				'palette'         => $clean['palette'],
				'lang'            => $clean['map_lang'],
				'marker'          => $clean['marker'] ? 'yes' : 'no',
				'popup'           => $clean['popup'] ? 'yes' : 'no',
				'scroll'          => $clean['scroll'] ? 'yes' : 'no',
				'color'           => $clean['marker_color'],
				'image'           => $clean['marker_image'],
				'fullscreen'      => $clean['fullscreen'] ? 'yes' : 'no',
				'search'          => $clean['search'] ? 'yes' : 'no',
				'points'          => $clean['points'],
				'style_colors'    => $clean['style_colors'],
				'hide_poi'        => $clean['hide_poi'],
				'hide_buildings'  => $clean['hide_buildings'],
				'hide_3d'         => $clean['hide_3d'],
				'hide_terrain'    => $clean['hide_terrain'],
				'hide_boundary'   => $clean['hide_boundary'],
				'hide_rail'       => $clean['hide_rail'],
				'hide_labels'     => $clean['hide_labels'],
				'terrain_opacity' => $clean['terrain_opacity'],
			)
		);
	}

	/**
	 * AJAX: return the config for the live preview (without saving).
	 *
	 * @return void
	 */
	public function ajax_preview() {
		check_ajax_referer( 'office_map_preview', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}

		$clean = $this->read_clean_input();
		wp_send_json_success( $this->config_from_clean( $clean ) );
	}

	/**
	 * AJAX: save the settings and return the updated config.
	 *
	 * @return void
	 */
	public function ajax_save() {
		check_ajax_referer( 'office_map_preview', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}

		$clean = $this->read_clean_input();
		update_option( 'office_map_options', $clean );

		wp_send_json_success( $this->config_from_clean( $clean ) );
	}

	/**
	 * Restore the previous saved settings from the backup.
	 *
	 * @return void
	 */
	public function handle_restore() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		check_admin_referer( 'office_map_restore' );

		$backup = get_option( 'office_map_options_backup', array() );
		if ( is_array( $backup ) && ! empty( $backup ) ) {
			update_option( 'office_map_options', $backup );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=office-map&office_map_restored=1' ) );
		exit;
	}

	/**
	 * Restore the markers from the last state that had them.
	 *
	 * @return void
	 */
	public function handle_restore_lastgood() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		check_admin_referer( 'office_map_restore_lastgood' );

		$good = get_option( 'office_map_options_lastgood', array() );
		if ( is_array( $good ) && ! empty( $good['points'] ) ) {
			$cur           = Office_Map::get_options();
			$cur['points'] = $good['points'];
			update_option( 'office_map_options', $cur );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=office-map&office_map_restored=1' ) );
		exit;
	}

	/**
	 * Prepare the settings for export: attachment images become absolute URLs
	 * so that the settings can be moved to another site.
	 *
	 * @param array $options Settings.
	 * @return array
	 */
	private function portable_options( $options ) {
		if ( ! is_array( $options ) ) {
			return array();
		}
		if ( ! empty( $options['marker_image'] ) ) {
			$options['marker_image'] = Office_Map::resolve_image_url( $options['marker_image'] );
		}
		if ( ! empty( $options['points'] ) && is_array( $options['points'] ) ) {
			foreach ( $options['points'] as $i => $p ) {
				if ( is_array( $p ) && ! empty( $p['image'] ) ) {
					$options['points'][ $i ]['image'] = Office_Map::resolve_image_url( $p['image'] );
				}
			}
		}
		return $options;
	}

	/**
	 * Export the settings to a JSON file.
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		check_admin_referer( 'office_map_export' );

		$options = $this->portable_options( Office_Map::get_options() );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="office-map-settings.json"' );
		echo wp_json_encode( $options, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Import the settings from JSON (file or text).
	 *
	 * @return void
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		check_admin_referer( 'office_map_import' );

		$json = '';
		$file = isset( $_FILES['office_map_file']['tmp_name'] ) ? $_FILES['office_map_file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Temporary uploaded file path; validated below with is_uploaded_file().
		if ( '' !== $file && is_uploaded_file( $file ) ) {
			$json = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a temporary uploaded file.
		} elseif ( isset( $_POST['office_map_json'] ) ) {
			$json = wp_unslash( $_POST['office_map_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded and sanitized below.
		}

		$data = json_decode( (string) $json, true );

		$ok = false;
		if ( is_array( $data ) ) {
			// wp_slash compensates for the wp_unslash() inside sanitize().
			$clean = $this->sanitize( wp_slash( $data ) );
			if ( is_array( $clean ) && ! empty( $clean ) ) {
				update_option( 'office_map_options', $clean );
				$ok = true;
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=office-map&office_map_imported=' . ( $ok ? '1' : '0' ) ) );
		exit;
	}

	/**
	 * Settings page markup.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap office-map-wrap">
			<h1><?php esc_html_e( 'PD17 Map', 'prodev17-location-map' ); ?></h1>
			<?php
			// Read-only status flags shown after a redirect.
			$om_imported = isset( $_GET['office_map_imported'] ) ? sanitize_text_field( wp_unslash( $_GET['office_map_imported'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag.
			$om_restored = isset( $_GET['office_map_restored'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag.
			?>
			<?php if ( '' !== $om_imported ) : ?>
				<div class="notice notice-<?php echo '1' === $om_imported ? 'success' : 'error'; ?> is-dismissible"><p>
					<?php
					if ( '1' === $om_imported ) {
						esc_html_e( 'Settings imported.', 'prodev17-location-map' );
					} else {
						esc_html_e( 'Could not import: invalid JSON.', 'prodev17-location-map' );
					}
					?>
				</p></div>
			<?php endif; ?>
			<?php if ( $om_restored ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Previous settings restored.', 'prodev17-location-map' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'A map on MapLibre GL JS and OpenStreetMap: no Google, no Yandex, no API keys and no ads.', 'prodev17-location-map' ); ?></p>

			<div class="office-map-wrap__cols">
				<form action="options.php" method="post" class="office-map-form">
					<?php
					settings_fields( 'office_map_group' );
					printf(
						'<input type="hidden" id="office-map-ui-open" name="office_map_options[ui_open]" value="%s">',
						esc_attr( implode( ',', $this->open_sections() ) )
					);
					foreach ( $this->sections() as $section ) {
						$this->render_section( $section );
					}
					submit_button( __( 'Save changes', 'prodev17-location-map' ) );
					?>
				</form>

				<div class="office-map-side">
					<h2><?php esc_html_e( 'Preview', 'prodev17-location-map' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Updates after you press “Save” in any block.', 'prodev17-location-map' ); ?></p>
					<?php echo do_shortcode( '[office_map preview="yes"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the shortcode. ?>

					<p>
						<button type="button" class="button" id="office-map-copy-shortcode"><?php esc_html_e( 'Copy shortcode with current settings', 'prodev17-location-map' ); ?></button>
					</p>

					<?php if ( get_option( 'office_map_options_backup', array() ) ) : ?>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return confirm( '<?php echo esc_js( __( 'Restore the previous saved settings?', 'prodev17-location-map' ) ); ?>' );">
						<input type="hidden" name="action" value="office_map_restore">
						<?php wp_nonce_field( 'office_map_restore' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Roll back the previous settings', 'prodev17-location-map' ); ?></button>
					</form>
					<?php endif; ?>

					<?php
					$om_good = get_option( 'office_map_options_lastgood', array() );
					$om_cur  = Office_Map::get_options();
					if ( empty( $om_cur['points'] ) && is_array( $om_good ) && ! empty( $om_good['points'] ) ) :
						?>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return confirm( '<?php echo esc_js( __( 'Restore the markers from the last version that had them?', 'prodev17-location-map' ) ); ?>' );">
						<input type="hidden" name="action" value="office_map_restore_lastgood">
						<?php wp_nonce_field( 'office_map_restore_lastgood' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Restore markers', 'prodev17-location-map' ); ?></button>
					</form>
					<?php endif; ?>

					<h2><?php esc_html_e( 'Import / export settings', 'prodev17-location-map' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Export is a JSON file with all settings that you can import on another site (address, markers, colors, styling).', 'prodev17-location-map' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-bottom:10px">
						<input type="hidden" name="action" value="office_map_export">
						<?php wp_nonce_field( 'office_map_export' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Download settings (JSON)', 'prodev17-location-map' ); ?></button>
					</form>

					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data" onsubmit="return confirm( '<?php echo esc_js( __( 'Import settings? The current settings will be overwritten (you can roll back).', 'prodev17-location-map' ) ); ?>' );">
						<input type="hidden" name="action" value="office_map_import">
						<?php wp_nonce_field( 'office_map_import' ); ?>
						<p><input type="file" name="office_map_file" accept=".json,application/json"></p>
						<p><textarea name="office_map_json" rows="4" class="large-text code" placeholder="<?php esc_attr_e( '…or paste the JSON content here', 'prodev17-location-map' ); ?>"></textarea></p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'prodev17-location-map' ); ?></button>
					</form>

					<h2><?php esc_html_e( 'How to place the map', 'prodev17-location-map' ); ?></h2>
					<p><?php esc_html_e( 'Shortcode — put it in a text/code block or any content area:', 'prodev17-location-map' ); ?></p>
					<p><code>[office_map]</code></p>
					<p><?php esc_html_e( 'Widget — “Appearance → Widgets”, the “PD17 Map” block.', 'prodev17-location-map' ); ?></p>
					<p><?php esc_html_e( 'Moving to another site: copy the wp-content/plugins/office-map/ folder and activate the plugin.', 'prodev17-location-map' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}
