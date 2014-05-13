jQuery(document).ready(function($) {
	$('#location-list li').click(function(e) { e.preventDefault(); });
	
	// Enable the visual refresh
	google.maps.visualRefresh = true;

	var map, max_height = 528,	// 600-(36*2)
		last_marker = false,
		last_window = false,
		latlng = new google.maps.LatLng(38.635,-90.258);
	function initialize() {
		var mapOptions = {
			zoom: 16,
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
			// We'll pass this variable to the PHP function example_ajax_request
			var id = $(this).attr('data-page_id'),
				x = $(this).attr('data-xcoord'),
				y = $(this).attr('data-ycoord'),
				myLatlng = new google.maps.LatLng( x, y ),
				image = '/wp-content/plugins/wusm-maps/map_marker_closed.png',
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
			$('.open').removeClass();
			$(this).parent().addClass('open');
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

	if($('#map-container')[0]) {
		google.maps.event.addDomListener(window, 'load', initialize);
	}

	function show_location_info(i) {
		// This does the ajax request
		$.ajax({
			type : 'post',
			url: ajax_object.ajax_url,
			data: {
				action   : 'show_location',
				id       : i
			},
			success:function(data) {
				if( data !== '-1' ) {
					close_em();
					
					var location_obj = jQuery.parseJSON( data ),
						content = '',
						coords_array = location_obj.coords.split(',');
					
					if(location_obj.image)
						content += "<img class='loc-image' src=" + location_obj.image + ">";
					content += "<div class='loc-div'><h3>" + location_obj.title + "</h3>" + location_obj.content + "</div>";
					content += "<form id='get-directions-box' action='http://maps.google.com/maps' method='get'>";
					content += "<input type='text' name='saddr' placeholder='Type your address' id='address'>";
					content += "<input type='hidden' name='daddr' value='" + coords_array[0] + "," + coords_array[1] + "'>";
					content += "<button id='get-directions'>Get Directions</button></form>";

					var	myLatlng = new google.maps.LatLng( parseFloat(coords_array[0]), parseFloat(coords_array[1]) ),
						centered = new google.maps.LatLng( parseFloat(coords_array[0]+200), parseFloat(coords_array[1]-200) ),
						infowindow = new google.maps.InfoWindow({
							content: content,
							maxWidth: 515
						}),
						image = '/wp-content/plugins/wusm-maps/map_marker_open.png',
						marker = new google.maps.Marker({
							position: myLatlng,
							map: map,
							title: location_obj.title,
							icon: image
						});


					google.maps.event.addListener(infowindow,'closeclick',function(){
						close_em();
					});

					map.setCenter(centered);
					infowindow.open(map,marker);
					// save marker/window so we can close them later
					last_marker = marker;
					last_window = infowindow;
				}
			}
		});
	}

	$('#map-reset').click(function() {
		map.setCenter(latlng);
		map.setZoom(16);
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