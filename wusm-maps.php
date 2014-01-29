<?php
/*
Plugin Name: WUSM Maps
Plugin URI: 
Description: Add maps to WUSM sites
Author: Aaron Graham
Version: 0.5
Author URI: 
*/

class wusm_maps_plugin {
	private $maps_text;

	/**
	 *
	 */
	public function __construct() {
		add_shortcode( 'wusm_map', array( $this, 'maps_shortcode' ) );
		add_action( 'wp_ajax_show_location', array( $this, 'get_location_window' ) ); // ajax for logged in users
		add_action( 'wp_ajax_nopriv_show_location', array( $this, 'get_location_window' ) ); // ajax for not logged in users
		add_action( 'wp_enqueue_scripts', array( $this, 'maps_shortcode_scripts' ) );
	}

	/**
	 * Enqueue styles.
	 *
	 * @since 0.1.0
	 */
	function maps_shortcode_scripts() {
		wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false' );
		wp_enqueue_script( 'maps-js', plugins_url('maps.js', __FILE__) );
		wp_register_style( 'maps-styles', plugins_url('maps.css', __FILE__) );
		wp_enqueue_style( 'maps-styles' );
	}

	function maps_shortcode() {
		$map_list_walker = new Map_List_Walker();

		$output = "<div id='map-container'>";
		$output .= "<div id='map-canvas'></div>";
		$args = array(
			'title_li'     => false,
			'echo'         => 0,
			'walker'       => $map_list_walker,
			'post_type'    => 'location'
		);

		$output .= "<ul id='location-list'>";
		$output .= "<li class='title-li'>Find a Location<span id='map-reset'>RESET</span></li>";
		$output .= wp_list_pages( $args );
		$output .= "</ul>";
		$output .= "</div>";
		
		return $output;
	}

	function get_location_window() {
		if ( !wp_verify_nonce( $_REQUEST['nonce'], "wusm_nonce")) {
			  exit("Processing error");
		 }

		$loc_id = $_POST['id'];
		$loc_post = get_post($loc_id);
		
		$location = get_field('location', $loc_id);
		$img = get_field('location_image', $loc_id);
		$content = wpautop($loc_post->post_content);
		
		$location_array = array(
			'address' => $location['address'],
			'coords'  => $location['coordinates'],
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
		$nonce = wp_create_nonce("wusm_nonce");
		$meta = get_post_meta( $page->ID, 'location' );
		if ( $depth )
			$indent = str_repeat("\t", $depth);
		else
			$indent = '';

		extract($args, EXTR_SKIP);
		
		$output .= $indent . '<li>';
		if($meta[0] != '')
			$output .= '<a data-nonce="' . $nonce . '" data-page_id="' . $page->ID . '" href="' . get_permalink($page->ID) . '">';
		$output .= $link_before . apply_filters( 'the_title', $page->post_title, $page->ID ) . $link_after;
		if($meta[0] != '')
			$output .= '</a>';
	}
}