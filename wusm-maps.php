<?php
/*
Plugin Name: WUSM Maps
Plugin URI:  http://medicine.wustl.edu
Description: Add maps to WUSM sites.  Use shortcode [wusm_map] to add map to page/post.
Author:      Aaron Graham
Author URI:  http://coderaaron.com
Version:     15.04.29.2
*/


class wusm_maps_plugin {
	private $maps_text,
			$location_post_type,
			$map_meta_field        = 'wusm_map_location';


	public function __construct() {
		$this->location_post_type = get_field( 'wusm_map_post_type' , 'option' );

		add_shortcode( 'wusm_map', array( $this, 'maps_shortcode' ) );
		
		add_action( 'wusm-maps_ajax_show_location', array( $this, 'get_location_window' ) ); // ajax for logged in users
		add_action( 'wusm-maps_ajax_nopriv_show_location', array( $this, 'get_location_window' ) ); // ajax for not logged in users
		
		add_filter( 'mce_external_plugins', array( $this, 'add_tinymce_wusm_map_button' ) );

		add_action( 'init', array( $this, 'register_maps_location_post_type') );
	}

	// Declare script for new button
	public function add_tinymce_wusm_map_button( $plugin_array ) {
		$plugin_array['wusm_maps_button'] = plugins_url('maps-button.js', __FILE__);
		return $plugin_array;
	}

	public function register_maps_location_post_type() {
		if( !WP_DEBUG ) {
			// Include exported ACF fields
			require_once( dirname(__FILE__) . '/acf-fields.php' );
		}


		if( function_exists( 'shortcode_ui_register_for_shortcode' ) ) {
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
							'query'    => array( 'post_type' => 'office-location' ),
							'multiple' => true,
						),
					),
				)
			);
		}

		if( function_exists('acf_add_options_page') ) {
	
			acf_add_options_sub_page(array(
				'page_title' 	=> 'WUSM Maps settings',
				'menu_title'	=> 'WUSM Maps',
				'parent_slug'	=> 'options-general.php',
			));

		}

		$menu_position = apply_filters('wusm-maps_menu_position',5);

		add_image_size( 'map-img', 220, 220, true );

	}

	function maps_shortcode( $atts, $content = '' ) {
		// WP_Query arguments
		$atts = shortcode_atts( array(
				'ids' => null,
		), $atts, 'wusm_map' );

		wp_enqueue_script( 'google-maps', '//maps.googleapis.com/maps/api/js?v=3.exp' );
		
		$map_icon = get_field( 'wusm_map_icon' , 'option' );
		$map_center = get_field( 'wusm_map_center' , 'option' );

		$wusm_map_params = array(
			'icon'      => $map_icon['url'],
			'width'     => $map_icon['width'],
			'height'    => $map_icon['height'],
			'lat'       => $map_center['lat'],
			'lng'       => $map_center['lng'],
			'loc_count' => -1
		);

		if( $atts['ids'] !== null ) {
			$wusm_map_params['include'] = substr_count( $atts['ids'], "," ) + 1;
		}

		wp_enqueue_script( 'maps-js', plugins_url('maps.js', __FILE__) );
		wp_localize_script( 'maps-js', 'WUSMMapParams', $wusm_map_params );

		wp_register_style( 'maps-styles', plugins_url('maps.css', __FILE__) );
		wp_enqueue_style( 'maps-styles' );

		$map_list_walker = new Map_List_Walker( $this->location_post_type, $this->map_meta_field );

		$count_pages = count( get_pages( array( 'post_type' => $this->location_post_type, 'parent' => 0 ) ) );
		// Total height is 600px, each entry is 36px
		// We have to add one for the "Find a Location" header
		$max_height = 600 - ( 36 * ( $count_pages + 1 ) );

		ob_start();

		echo "<div id='map-container'>";
		echo  "<div id='map-canvas'></div>";

		$args = array(
			'title_li'     => false,
			'echo'         => 0,
			'walker'       => $map_list_walker,
			'post_type'    => $this->location_post_type
		);

		if( $atts['ids'] !== null ) {
			$args['include'] = $atts['ids'];
		}

		echo  "<ul data-max_height='$max_height' id='location-list'>";
		//echo  "<li class='title-li'>Find a Location<span id='map-reset'>RESET</span></li>";
		echo  wp_list_pages( $args );
		echo  "</ul>";
		echo  "</div>";

		return ob_get_clean();
	}

	function get_location_window() {
		$loc_id   = $_POST['id'];
		$loc_post = get_post($loc_id);
		
		$location = get_field($this->map_meta_field, $loc_id);
		
		$img_id   = get_field('location_image', $loc_id);
		$size     = "map-img";
		$image    = wp_get_attachment_image_src( $img_id, $size );
		$img      = $image[0];
		
		$content  = wpautop($loc_post->post_content);
		
		$location_array = array(
			'address' => $location['address'],
			'lat'     => $location['lat'],
			'lng'     => $location['lng'],
			'image'   => $img,
			'title'   => $loc_post->post_title,
			'content' => $content
		);

		echo json_encode($location_array);

		die(); // stop executing script
	}

}
new wusm_maps_plugin();

class Map_List_Walker extends Walker_page {
	private $location_post_type,
			$map_meta_field;

	public function __construct($lpt, $mmf) {
		$this->location_post_type = $lpt;
		$this->map_meta_field	  = $mmf;
	}

	function start_el(&$output, $page, $depth = 0, $args = Array(), $current_page = 0) {
		
		$meta = get_post_meta( $page->ID, $this->map_meta_field );
		
		$debug =  get_field( $this->map_meta_field, $page->ID );
		if( isset($debug['coordinates']) )
			$coord = explode(',', $debug['coordinates']);
		
		$loc_id = get_post_meta( $page->ID, 'num' );
		if ( $depth )
			$indent = str_repeat("\t", $depth);
		else
			$indent = '';

		extract($args, EXTR_SKIP);
		
		$count_children = count( get_pages( array( 'post_type' => $this->location_post_type, 'parent' => $page->ID ) ) );
		$class = ( $count_children > 0 ) ? " class='parent'" : "";

		$title = apply_filters( 'the_title', $page->post_title, $page->ID );

		$is_map_location = ( isset( $meta ) && ( sizeof( $meta ) > 0 ) );

		echo  $indent . "<li$class>";
		if(isset($loc_id[0]) && ($loc_id[0] != ''))
			$link_after .= " (" . $loc_id[0] . ")";
		if( $is_map_location )
			echo  '<a data-xcoord="' . $debug['lat'] . '" data-ycoord="' . $debug['lng'] . '" data-page_id="' . $page->ID . '" href="javascript:false;">';
		echo  $link_before . $title . $link_after;
		if( $is_map_location ) {
			echo  '</a>';
			echo "<p>";
			/*if( get_field( 'wusm_map_location', $page->ID ) ) { $loc_array = get_field( 'wusm_map_location', $page->ID ); echo $loc_array['address'] . "<br>"; }*/
			if( get_field( 'wusm_map_street_address_1', $page->ID ) ) { echo get_field( 'wusm_map_street_address_1', $page->ID ) . "<br>"; }
			if( get_field( 'wusm_map_street_address_2', $page->ID ) ) { echo get_field( 'wusm_map_street_address_2', $page->ID ) . "<br>"; }
			if( get_field( 'wusm_map_city', $page->ID ) ) { echo get_field( 'wusm_map_city', $page->ID ) . ", "; }
			if( get_field( 'wusm_map_state', $page->ID ) ) { echo get_field( 'wusm_map_state', $page->ID ) . " "; }
			if( get_field( 'wusm_map_zip_code', $page->ID ) ) { echo get_field( 'wusm_map_zip_code', $page->ID ) . "<br>"; }
			if( get_field( 'wusm_map_phone', $page->ID ) ) { echo "<strong>Phone</strong>: " . get_field( 'wusm_map_phone', $page->ID ) . "<br>"; }
			if( get_field( 'wusm_map_fax', $page->ID ) ) { echo "<strong>Fax</strong>: " . get_field( 'wusm_map_fax', $page->ID ) . "<br>"; }
			echo "</p>";
		}
	}

	function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat("\t", $depth);
		echo  "$indent<ul class='child'>\n";
	}
}