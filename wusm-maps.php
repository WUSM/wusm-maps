<?php
/*
Plugin Name: WUSM Maps
Plugin URI: 
Description: Add maps to WUSM sites
Author: Aaron Graham
Version: 15.05.27.0
Author URI: 
*/

class wusm_maps_plugin {
	private $maps_text;

	public function __construct() {
		add_shortcode( 'wusm_map', array( $this, 'maps_shortcode' ) );
		add_action( 'MY_AJAX_HANDLER_show_location', array( $this, 'get_location_window' ) ); // ajax for logged in users
		add_action( 'MY_AJAX_HANDLER_nopriv_show_location', array( $this, 'get_location_window' ) ); // ajax for not logged in users
		add_action( 'init', array( $this, 'register_maps_location_post_type') );

	}

	function register_maps_location_post_type() {
		$menu_position = apply_filters('wusm-maps_menu_position', 9);

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
			'not_found' =>	'No Map Locations found',
			'not_found_in_trash' => 'No Map Locations found in Trash', 
			'parent_item_colon' => '',
			'menu_name' => 'Map Locations'
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
			'supports' => array(
				'title',
				'editor',
				'thumbnail',
				'revisions',
				'page-attributes',
			)
		); 

		register_post_type( 'location', $args );

		add_image_size( 'map-img', 220, 220, true );

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
	}

	function maps_shortcode() {
		wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false' );
		wp_enqueue_script( 'maps-js', plugins_url('maps.js', __FILE__) );
		wp_register_style( 'maps-styles', plugins_url('maps.css', __FILE__) );
		wp_enqueue_style( 'maps-styles' );

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
			'post_type'    => 'location'
		);

		$output .= "<ul data-max_height='$max_height' id='location-list'>";
		$output .= "<li class='title-li'>Find a Location<span id='map-reset'>RESET</span></li>";
		$output .= wp_list_pages( $args );
		$output .= "</ul>";
		$output .= "</div>";
		
		return $output;
	}

	function get_location_window() {
		$loc_id = $_POST['id'];
		$loc_post = get_post($loc_id);
		
		$coord_fields = get_field('location', $loc_id);

		if( isset( $coord_fields['coordinates'] ) ) {
			$coord = $coord_fields['coordinates'];
		} elseif( isset( $coord_fields['lat'] ) && isset( $coord_fields['lng'] ) ) {
			$coord = implode(  ",", array( $coord_fields['lat'], $coord_fields['lng'] ) );
		} else {
			$coord = "38.6354379, -90.2644422";
		}

		$img_id = get_field('location_image', $loc_id);
		$size = "map-img"; // (thumbnail, medium, large, full or custom size)
		$image = wp_get_attachment_image_src( $img_id, $size );
		$img = $image[0];

		$content = wpautop($loc_post->post_content);
		
		$location_array = array(
			'address' => $coord_fields['address'],
			'coords'  => $coord,
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
	function start_el(&$output, $page, $depth = 0, $args = Array(), $current_page = 0) {
		
		$meta = get_post_meta( $page->ID, 'location' );
		
		$coord_fields =  get_field('location', $page->ID);
		if( isset($coord_fields['coordinates']) ) {
			$coord = explode(',', $coord_fields['coordinates']);
		} elseif( isset($coord_fields['lat']) && isset($coord_fields['lng']) ) {
			$coord = array($coord_fields['lat'], $coord_fields['lng']);
		} else {
			$coord = array( 38.6354379, -90.2644422);
		}
		
		$loc_id = get_post_meta( $page->ID, 'num' );
		if ( $depth )
			$indent = str_repeat("\t", $depth);
		else
			$indent = '';

		extract($args, EXTR_SKIP);
		
		$count_children = count( get_pages( array( 'post_type' => 'location', 'parent' => $page->ID ) ) );
		$class = ( $count_children > 0 ) ? " class='parent'" : "";

		$title = apply_filters( 'the_title', $page->post_title, $page->ID );

		$output .= $indent . "<li$class>";
		if(isset($loc_id[0]) && ($loc_id[0] != ''))
			$link_after .= " (" . $loc_id[0] . ")";
		if($meta[0] != '')
			$output .= '<a data-xcoord="' . $coord[0] . '" data-ycoord="' . $coord[1] . '" data-page_id="' . $page->ID . '" href="javascript:false;">';
		$output .= $link_before . $title . $link_after;
		if($meta[0] != '')
			$output .= '</a>';
	}

	function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent<ul class='child'>\n";
	}
}
