<?php
/*
Plugin Name: WUSM Maps
Plugin URI:
Description: Add maps to WUSM sites
Author: Aaron Graham
2016.12.08.0
Author URI:
*/

class wusm_maps_plugin {
	private $maps_text;

	public function __construct() {

		add_action( 'admin_init', array( $this, 'wusm_maps_helper_admin_init' ) );

		if ( file_exists( plugin_dir_path( __FILE__ ) . 'acf-json/group_acf_locations.json' ) ) {
			unlink( plugin_dir_path( __FILE__ ) . 'acf-json/group_acf_locations.json' );
		}

		// Settings page for the plugin
		acf_add_options_sub_page(array(
			'menu'   => 'Maps Settings',
			'parent' => 'edit.php?post_type=location',
		));

		add_shortcode( 'wusm_map', array( $this, 'maps_shortcode' ) );

		add_action( 'init', array( $this, 'register_maps_location_post_type' ) );
		add_action( 'rest_api_init', array( $this, 'location_register_coords' ) );

		// Using JSON to sync fields instead of PHP includes
		add_filter( 'acf/settings/load_json', array( $this, 'wusm_maps_load_acf_json' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'wusm_maps_enqueue_scripts_and_styles' ) );

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

	}

	function location_register_coords() {
		register_rest_field( 'location',
			'location',
			array(
				'get_callback'    => array( $this, 'location_get_coords' ),
				'update_callback' => null,
				'schema'          => null,
			)
		);

		register_rest_field( 'location',
			'image',
			array(
				'get_callback'    => array( $this, 'location_get_image' ),
				'update_callback' => null,
				'schema'          => null,
			)
		);
	}

	/**
	 * get the value of the "coords" field
	 *
	 * @param array           $object details of current post.
	 * @param string          $field_name name of field.
	 * @param wp_rest_request $request current request
	 *
	 * @return mixed
	 */
	function location_get_image( $object, $field_name, $request ) {

		$img_id = get_post_meta( $object['id'], 'location_image', true );
		$size = 'map-img'; // (thumbnail, medium, large, full or custom size)
		$image = wp_get_attachment_image_src( $img_id, $size );
		$img = $image[0];

		return $img;
	}

	/**
	 * get the value of the "coords" field
	 *
	 * @param array           $object details of current post.
	 * @param string          $field_name name of field.
	 * @param wp_rest_request $request current request
	 *
	 * @return mixed
	 */
	function location_get_coords( $object, $field_name, $request ) {
		$location = get_post_meta( $object['id'], 'location', true );

		if ( $location == null ) {

			$location = get_post_meta( $object['id'], 'wusm_map_location', true );

		}

		return $location;
	}

	/**
	 * Adds the functionality to the WUSM maps button to TinyMCE
	 *
	 * @param  array $plugin_array array of TinyMCE plugins
	 * @return array               array with our plugin added to it
	 */
	public function wusm_maps_add_buttons( $plugin_array ) {

		// http://codex.wordpress.org/TinyMCE
		$plugin_array['wusm_maps_mce_button'] = plugins_url( 'js/wusm-maps-tinymce.js', __FILE__ );
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
		
		$wusm_maps_js_vars = array(
			'center'    => get_field( 'wusm_map_center', 'option' ),
			'icon'      => get_field( 'wusm_map_icon', 'option' ),
			'icon_open' => get_field( 'wusm_map_icon_open', 'option' ),
		);

		if ( $wusm_maps_js_vars['center'] == false ) {
			$wusm_maps_js_vars['center'] = array(
				'lat' => '38.6350726',
				'lng' => '-90.2644749',
			);
		}

		if ( strpos( site_url(), 'wustl.edu' ) ) {
			wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyCjJ28lFJ8KIaQBJ32JQypx3PfGANtN5YY&sensor=false' );
		} else {
			wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?sensor=false' );
		}

		wp_register_script( 'maps-js', plugin_dir_url( __FILE__ ) . 'maps.js' );
		wp_enqueue_script( 'maps-js' );

		wp_localize_script( 'maps-js', 'maps_vars', $wusm_maps_js_vars );

		wp_register_style( 'maps-styles', plugins_url( 'maps.css', __FILE__ ) );
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
		$paths[] = plugin_dir_path( __FILE__ ) . 'acf-json';

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

		add_image_size( 'map-img', 220, 220, true );

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
							'label'    => 'Select Location(s) - If no locations are selected, ALL locations will be shown',
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

		$map_list_walker = new Map_List_Walker();

		$count_pages = count( get_pages( array( 'post_type' => 'location', 'parent' => 0 ) ) );
		// Total height is 600px, each entry is 36px
		// We have to add one for the "Finad a Location" header
		$max_height = 600 - ( 36 * ( $count_pages + 1 ) );

		$output = "<div id='map-container'>";
		$output .= "<div id='map-canvas'></div>";
		
		$args = array(
			'title_li'     => false,
			'echo'         => 0,
			'walker'       => $map_list_walker,
			'post_type'    => 'location',
		);

		if ( $atts[ 'ids' ] ) {
			$args[ 'include' ] = $atts[ 'ids' ];
		}

		$output .= "<ul data-max_height='$max_height' id='location-list'>";
		$output .= "<li class='title-li'>Find a Location<span id='map-reset'>RESET</span></li>";
		$output .= wp_list_pages( $args );
		$output .= '</ul>';
		$output .= '</div>';

		return $output;
	}
}
$wusm_maps = new wusm_maps_plugin();

class Map_List_Walker extends Walker_page {
	function start_el( &$output, $page, $depth = 0, $args = array(), $current_page = 0 ) {

		$meta = get_post_meta( $page->ID, 'wusm_map_location' );

		if ( sizeof( $meta ) === 0 ) {
			$meta = get_post_meta( $page->ID, 'location' );
		}

		$coord_fields = $meta[0];

		if ( isset( $coord_fields['coordinates'] ) ) {
			$coord = explode( ',', $coord_fields['coordinates'] );
		} elseif ( isset( $coord_fields['lat'] ) && isset( $coord_fields['lng'] ) ) {
			$coord = array( $coord_fields['lat'], $coord_fields['lng'] );
		} else {
			$coord = array( 38.6354379, -90.2644422 );
		}

		$loc_id = get_post_meta( $page->ID, 'num' );
		if ( $depth ) {
			$indent = str_repeat( "\t", $depth );
		} else { 			$indent = ''; }

		extract( $args, EXTR_SKIP );

		$count_children = count( get_pages( array( 'post_type' => 'location', 'parent' => $page->ID ) ) );
		$class = ( $count_children > 0 ) ? " class='parent'" : '';

		$title = apply_filters( 'the_title', $page->post_title, $page->ID );

		$output .= $indent . "<li$class>";
		if ( isset( $loc_id[0] ) && ($loc_id[0] != '') ) {
			$link_after .= ' (' . $loc_id[0] . ')'; }
		if ( $meta[0] != '' ) {
			$output .= '<a data-xcoord="' . $coord[0] . '" data-ycoord="' . $coord[1] . '" data-page_id="' . $page->ID . '" href="javascript:false;">'; }
		$output .= $link_before . $title . $link_after;
		if ( $meta[0] != '' ) {
			$output .= '</a>'; }
	}

	function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "$indent<ul class='child'>\n";
	}
}
