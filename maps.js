jQuery(document).ready(function($) {

	var zoom_level;
	if ($(window).width() > 960) {
		zoom_level = 16;
	} else if ($(window).width() > 700) {
		zoom_level = 15;
	} else {
		zoom_level = 14;
	}

	$('#location-list li').click(function(e) { e.preventDefault(); });
	
	// Enable the visual refresh
	google.maps.visualRefresh = true;

	var map, max_height = 528,	// 600-(36*2)
		last_marker = false,
		last_window = false,
		latlng = new google.maps.LatLng( maps_vars.center.lat, maps_vars.center.lng );
	function initialize() {
		
		var mapOptions = {
			zoom: zoom_level,
			disableDefaultUI: true,
			center: latlng,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		};
		map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);
		max_height = $('#location-list').data('max_height');

		$('.child').each(function() {
			$(this).css( { 'max-height' : max_height } );
		});

		$('#location-list .child').first().show().addClass( "expanded" );

		$('#location-list li a').each(function(index) {
			
			if( maps_vars.icon != '' ) {
				image = maps_vars.icon.url;
			} else {
				image = '/wp-content/plugins/wusm-maps/map_marker_closed.png';
			}

			var id = $(this).attr('data-page_id'),
				x = $(this).attr('data-xcoord'),
				y = $(this).attr('data-ycoord'),
				myLatlng = new google.maps.LatLng( x, y ),
				marker = new google.maps.Marker({
					position: myLatlng,
					map: map,
					title: '',
					icon: image
				});

			google.maps.event.addListener(marker, 'click', function() {
				show_location_info(id);
			});
		}).on('click', function(e) {
			// We'll pass this variable to the PHP function example_ajax_request
			var id = $(this).attr('data-page_id');
			 
			show_location_info(id);
			$('.open-location-box').removeClass();
			$(this).parent().addClass('open-location-box');
		});
		
		$('.parent').on('click', function() {
			if( !$(this).children( ".child" ).hasClass( "expanded" ) ) {
				$('.expanded').slideToggle().removeClass( "expanded" );
				$(this).children( ".child" ).slideToggle().addClass( "expanded" );
			}
		}).children().click(function(e) {
			return false;
		});
	}

	if( $('#map-container')[0] ) {
		google.maps.event.addDomListener(window, 'load', initialize);
	}

	function show_location_info( i ) {
		// This does the ajax request
		$.ajax({
			type : 'post',
			url: '/wp-content/plugins/wusm-maps/locations.php',
			data: {
				action   : 'show_location',
				id       : i
			},
			success:function(data) {
				if( data !== '-1' ) {
					close_em();

					console.log(data);
					
					var location_obj = jQuery.parseJSON( data ),
						content = '',
						coords_array = location_obj.coords.split(',');

					content += "<div class='location-info'>";
					if ($(window).width() > 700)
						if( location_obj.image )
							content += "<img class='loc-image' src=" + location_obj.image + ">";
					content += "<div class='loc-div'><h3>" + location_obj.title + "</h3><div class='loc-detail'>" + location_obj.content + "</div>";
					content += "<form id='get-directions-box' action='http://maps.google.com/maps' method='get'>";
					content += "<input type='hidden' name='daddr' value='" + coords_array[0] + "," + coords_array[1] + "'>";
					content += "<button id='get-directions'>Open in Google Maps</button></form>";
					content += "</div>";
					content += "</div>";
					
					var offset_lat,
						offset_lang;

					if ($(window).width() > 960) {
						offset_lat = 0.003, offset_lang = 0.0028;
					} else if ($(window).width() > 700) {
						offset_lat = 0.006, offset_lang = 0.005;
					} else {
						offset_lat = 0.006, offset_lang = 0;
					}

					if( maps_vars.icon_open != '' ) {
						image = maps_vars.icon_open.url;
					} else {
						image = '/wp-content/plugins/wusm-maps/map_marker_open.png';
					}

					console.log(coords_array);

					var	myLatlng = new google.maps.LatLng( parseFloat(coords_array[0]), parseFloat(coords_array[1]) ),
						panTo = new google.maps.LatLng( parseFloat(coords_array[0]) + offset_lat, parseFloat(coords_array[1]) + offset_lang),
						infowindow = new google.maps.InfoWindow({
							content: content,
							disableAutoPan: true,
							maxWidth: 515
						}),
						marker = new google.maps.Marker({
							position: myLatlng,
							map: map,
							title: location_obj.title,
							icon: image
						});

					google.maps.event.addListener( infowindow, 'closeclick', function(){
						close_em();
					});
					
					map.panTo( panTo );
					infowindow.open( map, marker );
					// save marker/window so we can close them later
					last_marker = marker;
					last_window = infowindow;
				}
			}
		});
	}

	$('#map-reset').click(function() {
		map.setCenter(latlng);
		map.setZoom(zoom_level);
		close_em();
	});

	function close_em() {
		if(last_marker) {
			// close infowindow
			last_window.close();
			last_window = false;
			// remove marker from map
			last_marker.setMap(null);
			last_marker = false;
		}
	}
});