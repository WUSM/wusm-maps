<?php
/*
Plugin Name: WUSM Maps
Plugin URI: 
Description: Add maps to WUSM sites
Author: Aaron Graham
Version:14.04.30.0
Author URI: 
*/

add_action( 'init', 'github_plugin_updater_wusm_maps_init' );
function github_plugin_updater_wusm_maps_init() {

		if( ! class_exists( 'WP_GitHub_Updater' ) )
			include_once 'updater.php';

		if( ! defined( 'WP_GITHUB_FORCE_UPDATE' ) )
			define( 'WP_GITHUB_FORCE_UPDATE', true );

		if ( is_admin() ) { // note the use of is_admin() to double check that this is happening in the admin

				$config = array(
						'slug' => plugin_basename( __FILE__ ),
						'proper_folder_name' => 'wusm-maps',
						'api_url' => 'https://api.github.com/repos/coderaaron/wusm-maps',
						'raw_url' => 'https://raw.github.com/coderaaron/wusm-maps/master',
						'github_url' => 'https://github.com/coderaaron/wusm-maps',
						'zip_url' => 'https://github.com/coderaaron/wusm-maps/archive/master.zip',
						'sslverify' => true,
						'requires' => '3.0',
						'tested' => '3.8',
						'readme' => 'README.md',
						'access_token' => '',
				);

				new WP_GitHub_Updater( $config );
		}

}

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
		add_action( 'init', array( $this, 'register_maps_location_post_type') );
	}

	function register_maps_location_post_type() {
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
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'capability_type' => 'post',
			'has_archive' => false, 
			'hierarchical' => true,
			'menu_position' => null,
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
		
		$location = get_field('location', $loc_id);

		$img_id = get_field('location_image', $loc_id);
		$size = "map-img"; // (thumbnail, medium, large, full or custom size)
		$image = wp_get_attachment_image_src( $img_id, $size );
		$img = $image[0];

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
		
		$meta = get_post_meta( $page->ID, 'location' );
		
		$debug =  get_field('location', $page->ID);
		if( isset($debug['coordinates']) )
			$coord = explode(',', $debug['coordinates']);
		
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
			$output .= '<a data-xcoord="' . $coord[0] . '" data-ycoord="' . $coord[1] . '" data-page_id="' . $page->ID . '" href="' . get_permalink($page->ID) . '">';
		$output .= $link_before . $title . $link_after;
		if($meta[0] != '')
			$output .= '</a>';
	}

	function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent<ul class='child'>\n";
	}
}
