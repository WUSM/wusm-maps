<?php
/*
Plugin Name: 	WUSM Maps
Plugin URI:		https://medicine.wustl.edu
Description:	Add maps to WUSM sites
Author:			Aaron Graham
Version:	    2018.10.17.1
Author URI: 	https://medicine.wustl.edu
*/

class wusm_maps_plugin {
	private $google_maps_api_key;
	
	public function __construct() {
		$this->setup_constants();

		acf_update_setting('select2_version', 4);

		add_action( 'admin_init', array( $this, 'wusm_maps_helper_admin_init' ) );

		add_shortcode( 'wusm_map', array( $this, 'maps_shortcode' ) );

		add_action( 'init', array( $this, 'register_maps_location_post_type' ) );
	
		// Using JSON to sync fields instead of PHP includes
		add_filter( 'acf/settings/load_json', array( $this, 'wusm_maps_load_acf_json' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'wusm_maps_enqueue_scripts_and_styles' ) );

		add_action('acf/init', array( $this, 'wusm_maps_google_maps_api_key' ) );
		
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access private
	 * @since 2016.06.01.0
	 * @return void
	 */
	private function setup_constants() {

		// Plugin Folder Path.
		if ( ! defined( 'WUSM_MAPS_PLUGIN_DIR' ) ) {
			define( 'WUSM_MAPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Folder URL.
		if ( ! defined( 'WUSM_MAPS_PLUGIN_URL' ) ) {
			define( 'WUSM_MAPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File.
		if ( ! defined( 'WUSM_MAPS_PLUGIN_FILE' ) ) {
			define( 'WUSM_MAPS_PLUGIN_FILE', __FILE__ );
		}

	}

	function wusm_maps_google_maps_api_key() {
		if ( strpos( site_url(), 'wustl.edu' ) ) {
			$this->google_maps_api_key = 'AIzaSyCjJ28lFJ8KIaQBJ32JQypx3PfGANtN5YY';
		} else {
			$this->google_maps_api_key = 'AIzaSyDKU0kyjVfq6FYc44VHc5k1gkhZ_Q9jmis';
		}

		acf_update_setting('google_api_key', $this->google_maps_api_key );
	}

	/**
	 * All the admin things
	 *
	 * @since  2016.06.01.0
	 */
	public function wusm_maps_helper_admin_init() {	
	
		// Register the TinyMCE JS that adds the button
		add_filter( 'mce_external_plugins', array( $this, 'wusm_maps_add_buttons' ) );

		// Actually insert the button registered above to TinyMCE
		add_filter( 'mce_buttons', array( $this, 'wusm_maps_register_buttons' ) );

		add_editor_style( WUSM_MAPS_PLUGIN_URL . 'maps.css' );
	}

	/**
	 * Adds the functionality to the WUSM maps button to TinyMCE
	 *
	 * @param  array $plugin_array array of TinyMCE plugins
	 * @return array               array with our plugin added to it
	 */
	public function wusm_maps_add_buttons( $plugin_array ) {

		// http://codex.wordpress.org/TinyMCE
		$plugin_array['wusm_maps_mce_button'] = WUSM_MAPS_PLUGIN_URL . 'js/wusm-maps-tinymce.js';
		return $plugin_array;

	}

	/**
	 * Add the actual button to TinyMCE
	 *
	 * @param  array $buttons TinyMCE buttons
	 * @return array          TinyMCE buttons with our button added to it
	 */
	public function wusm_maps_register_buttons( $buttons ) {

		// The ID value of the button we are creating from the JS file
		if( ! in_array( 'wusmbutton', $buttons) ) {
			array_push( $buttons, 'wusmbutton' );
		}
		return $buttons;
	

	}


	function wusm_maps_enqueue_scripts_and_styles() {
		
		wp_register_style( 'maps-styles', WUSM_MAPS_PLUGIN_URL . 'maps.css' );
		wp_enqueue_style( 'maps-styles' );

	}

	/**
	 * Tells ACF where to load local JSON from
	 *
	 * @param  array $paths paths ACF is currently looking
	 * @return array        paths with our directory added
	 */
	function wusm_maps_load_acf_json( $paths ) {
		// append path
		$paths[] = WUSM_MAPS_PLUGIN_DIR . 'acf-json';

		// return
		return $paths;

	}

	function register_maps_location_post_type() {
		$menu_position = apply_filters( 'wusm-maps_menu_position', 9 );

		$labels = array(
			'name' => 'Map Location',
			'singular_name' => 'Map Locations',
			'add_new' => 'Add New',
			'add_new_item' => 'Add New Map Location',
			'edit_item' => 'Edit Map Location',
			'new_item' => 'New Map Location',
			'all_items' => 'All Map Locations',
			'view_item' => 'View Map Location',
			'search_items' => 'Search Map Locations',
			'not_found' => 'No Map Locations found',
			'not_found_in_trash' => 'No Map Locations found in Trash',
			'parent_item_colon' => '',
			'menu_name' => 'Map Locations',
		);

		$args = array(
			'labels' => $labels,
			'menu_icon' => 'dashicons-location-alt',
			'public' => false,
			'publicly_queryable' => false,
			'show_ui' => true,
			'show_in_menu' => true,
			'query_var' => true,
			'capability_type' => 'post',
			'has_archive' => false,
			'hierarchical' => true,
			'menu_position' => $menu_position,
			'show_in_rest' => true,
			'supports' => array(
				'title',
				'editor',
				'thumbnail',
				'revisions',
				'page-attributes',
			),
		);

		register_post_type( 'location', $args );

		if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) {
			shortcode_ui_register_for_shortcode(
				'wusm_map',
				array(
					 // Display label. String. Required.
					'label' => 'WUSM Map',

					 // Icon/image for shortcode. Optional. src or dashicons-$icon. Defaults to carrot.
					'listItemImage' => 'dashicons-location-alt',

					// Available shortcode attributes and default values. Required. Array.
					// Attribute model expects 'attr', 'type' and 'label'
					// Supported field types: text, checkbox, textarea, radio, select, email, url, number, and date.
					'attrs' => array(
						array(
							'label'    => 'Select Location(s)',
							'description' => 'If no locations are selected, ALL locations will be shown',
							'attr'     => 'ids',
							'type'     => 'post_select',
							'query'    => array( 'post_type' => 'location' ),
							'multiple' => true,
						),
					),
				)
			);
		}
	}

	function maps_shortcode( $atts ) {
		$default_atts = array(
			// Locations to display
			'ids'     => false,
		);

		$atts = shortcode_atts( $default_atts, $atts, 'wusm_map' );
	
		// WP_Query arguments
		$args = array(
			'post_type'      => array( 'location' ),
			'order'          => 'ASC',
			'orderby'        => 'menu_order',
			'posts_per_page' => '-1',
		);

		if ( $atts[ 'ids' ] ) {
			$args[ 'post__in' ] = array_map( 'intval', explode( ',', $atts[ 'ids' ] ) );
		}
		
		// The Query
		$query = new WP_Query( $args );

		ob_start();

		// The Loop
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$location_array = get_field( 'wusm_map_location' );

				if ( $location_array == null ) {
					$location_array = get_field( 'location' );
				}

				if ( $location_array == null ) {

					echo "<h2 class='wusm-map-title'>" . get_the_title() . "</h2>";

				} else {

					$address = $location_array[ 'address' ];
					$google_maps_string = str_replace( ' ', '+', $address );
					
					$lat = $location_array[ 'lat' ];
					$lng = $location_array[ 'lng' ];
					
					$map_url = "https://maps.googleapis.com/maps/api/staticmap?";
					if ( strpos( site_url(), '.test' ) || strpos( site_url(), '-test' ) ) {
						$marker_icon = "https://medicine.wustl.edu/wp-content/uploads/location.png";
					} else {
						$marker_icon = WUSM_MAPS_PLUGIN_URL . "location.png";
					}
					$map_options = "center=$lat,$lng&zoom=15&size=300x220&markers=icon:$marker_icon%7C$lat,$lng";
					
					$map_styling = "&format=png&maptype=roadmap&style=element:geometry%7Ccolor:0xf5f5f5&style=element:labels.icon%7Cvisibility:off&style=element:labels.text.fill%7Ccolor:0x616161&style=element:labels.text.stroke%7Ccolor:0xf5f5f5&style=feature:administrative.land_parcel%7Celement:labels.text.fill%7Ccolor:0xbdbdbd&style=feature:poi%7Celement:geometry%7Ccolor:0xeeeeee&style=feature:poi%7Celement:labels.text.fill%7Ccolor:0x757575&style=feature:poi.park%7Celement:geometry%7Ccolor:0xe5e5e5&style=feature:poi.park%7Celement:labels.text.fill%7Ccolor:0x9e9e9e&style=feature:road%7Celement:geometry%7Ccolor:0xffffff&style=feature:road.arterial%7Celement:labels.text.fill%7Ccolor:0x757575&style=feature:road.highway%7Celement:geometry%7Ccolor:0xdadada&style=feature:road.highway%7Celement:labels.text.fill%7Ccolor:0x616161&style=feature:road.local%7Celement:labels.text.fill%7Ccolor:0x9e9e9e&style=feature:transit.line%7Celement:geometry%7Ccolor:0xe5e5e5&style=feature:transit.station%7Celement:geometry%7Ccolor:0xeeeeee&style=feature:water%7Celement:geometry%7Ccolor:0xc9c9c9&style=feature:water%7Celement:labels.text.fill%7Ccolor:0x9e9e9e";

					echo "<div class='wusm-maps-section'>";
					echo "<a href='https://www.google.com/maps/search/$google_maps_string'><img class='wusm-maps-static-map' src='$map_url$map_options$map_styling&key={$this->google_maps_api_key}'></a>";

					echo  ( get_field( 'wusm_map_practice_name' ) == '' ) ? "<h2 class='wusm-map-title'>" . get_the_title() . "</h2>" : "<h2 class='wusm-map-title'>" . get_field( 'wusm_map_practice_name' ) . "</h2>";
					if ( get_field( 'wusm_map_location_name' ) != '' ) { echo get_field( 'wusm_map_location_name' ). "</br>"; }
					if ( get_field( 'wusm_map_street_address_1' ) != '' ) { echo get_field( 'wusm_map_street_address_1' ). "</br>"; }
					if ( get_field( 'wusm_map_street_address_2' ) != '' ) { echo get_field( 'wusm_map_street_address_2' ). "</br>"; }
					
					if ( get_field( 'wusm_map_city' ) != '' &&
						 get_field( 'wusm_map_state' ) != '' &&
						 get_field( 'wusm_map_zip_code' ) != '' ) {
						echo get_field( 'wusm_map_city' ) . ", " . get_field( 'wusm_map_state' ) . " " . get_field( 'wusm_map_zip_code' ) . "</br>";
					}
					if ( get_field( 'wusm_map_phone' ) != '' ) { echo "<strong>Phone</strong>: " . get_field( 'wusm_map_phone' ). "</br>"; }
					if ( get_field( 'wusm_map_fax' ) != '' ) { echo "<strong>Fax</strong> : " . get_field( 'wusm_map_fax' ). "</br>"; }
					
					echo "<form class='wusm-maps-get-directions-form' id='get-directions-box' action='https://maps.google.com/maps' method='get'>";
					echo "<input type='hidden' name='daddr' value='$lat,$lng'>";
					echo "<button class='wusm-button'>Get Directions</button>";
					echo "</form>";

					the_content();
				
					echo "</div>";
				}
			}
		} else {
			// no posts found
		}

		// Restore original Post Data
		wp_reset_postdata();

		return ob_get_clean();
	}
}
$wusm_maps = new wusm_maps_plugin();
