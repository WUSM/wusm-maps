jQuery(document).ready(function($) {
	$('#location-list li').click(function(e) { e.preventDefault(); });
	
	// Enable the visual refresh
	google.maps.visualRefresh = true;

	var map,
		max_height   = 528,	// 600-(36*2)
		hover_marker = false,
		last_marker  = false,
		last_window  = false,
		icon_file    = WUSMMapParams.icon,
		icon_width   = parseInt( WUSMMapParams.width / 2),
		icon_height  = parseInt( WUSMMapParams.height ),
		lat          = WUSMMapParams.lat,
		lng          = WUSMMapParams.lng,
		latlng       = new google.maps.LatLng(lat,lng);

	function initialize() {
		var mapOptions = {
				zoom: 16,
				disableDefaultUI: true,
				center: latlng,
				mapTypeId: google.maps.MapTypeId.ROADMAP
			},
			max_height = $('#location-list').data('max_height');

		map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);

		$('.child').each(function() {
			$(this).css( { 'max-height' : max_height } );
		});

		$('#location-list .child').first().show().addClass( "expanded" );

		$('#location-list li a').each( function(index) {

			var image = {
				url: icon_file,
				// This marker is 20 pixels wide by 32 pixels tall.
				size: new google.maps.Size( icon_width, icon_height ),
				// The origin for this image is 0,0.
				//origin: new google.maps.Point( 0, 0 ),
				// The anchor for this image is the base of the flagpole at 0,32.
				//anchor: new google.maps.Point( icon_width / 2, icon_height )
			};

			// We'll pass this variable to the PHP function example_ajax_request
			var $this    = $(this),
				id       = $this.attr('data-page_id'),
				x        = $this.attr('data-xcoord'),
				y        = $this.attr('data-ycoord'),
				myLatlng = new google.maps.LatLng( x, y ),
				marker   = new google.maps.Marker({
					position: myLatlng,
					map:      map,
					icon:     image
				});

			google.maps.event.addListener( marker, 'click', function() {
				show_location_info( id, false );
			});

		}).on('click', function( e ) {
			
			// We'll pass this variable to the PHP function example_ajax_request
			var id = $( this ).attr( 'data-page_id' );
			
			show_location_info( id, false );
			$('.open-location-box').removeClass();
			$(this).parent().addClass('open-location-box');

		});

		$('#location-list li').on('mouseenter', function( e ) {
			var $this    = $(this).children( "a" ),
				x        = $this.attr('data-xcoord'),
				y        = $this.attr('data-ycoord'),
				myLatlng = new google.maps.LatLng( x, y ),
				image = {
					url: icon_file,
					// This marker is 20 pixels wide by 32 pixels tall.
					size: new google.maps.Size( icon_width, icon_height ),
					// The origin for this image is 0,0.
					origin: new google.maps.Point( icon_width, 0 ),
					// The anchor for this image is the base of the flagpole at 0,32.
					//anchor: new google.maps.Point( icon_width / 2, icon_height )
				};
			hover_marker = new google.maps.Marker({
				position: myLatlng,
				map: map,
				icon: image
			});
		}).on('mouseleave', function( e ) {
			hover_marker.setMap(null);
		});
		
		$('.parent').on('click', function() {
			
			if( !$(this).children( ".child" ).hasClass( "expanded" ) ) {
				$('.expanded').slideToggle().removeClass( "expanded" );
				$(this).children( ".child" ).slideToggle().addClass( "expanded" );
			}

		}).children().click(function(e) {
			return false;
		});

		if( $('#location-list').children().size() == 1 ) {
			$('#location-list').hide();
			show_location_info( $('#location-list li').children( "a" ).attr('data-page_id'), true );
		}
	}

	if( $('#map-container')[0] ) {
		google.maps.event.addDomListener(window, 'load', initialize);
	}

	function show_location_info( i, single ) {
		
		// This does the ajax request
		$.ajax({
			type : 'get',
			url: '/wp-json/posts/' + i,
			success:function( data ) {
				
				if( data.type === 'office-location' ) {
					close_em();

					var lat = parseFloat( data.meta.wusm_map_location.lat );
					var lng = parseFloat( data.meta.wusm_map_location.lng );

					var content = "<div>";
					if( data.image )
						content += "<img class='loc-image' src=" + data.image + ">";
					content += "<div class='loc-div'><h5>" + data.title + "</h5>"
					if( data.meta.wusm_map_practice_name ) {
						content += data.meta.wusm_map_practice_name;
					}
					if( data.meta.wusm_map_phone ) {
						content += "<strong>Phone:</strong> " + data.meta.wusm_map_phone;
					}
					if( data.meta.wusm_map_fax ) {
						content += "<strong>Fax:</strong> " + data.meta.wusm_map_fax;
					}
					
					if( single ) {
						content += data.meta.wusm_map_street_address_1 + "<br>";
						content += data.meta.wusm_map_street_address_2 + "<br>";
						content += data.meta.wusm_map_city + ", " + data.meta.wusm_map_state + " " + data.meta.wusm_map_zip_code;
					}

					content += "<form id='get-directions-box' action='http://maps.google.com/maps' method='get'>";
					content += "<input type='hidden' name='daddr' value='" + lat + "," + lng + "'>";
					content += "<button id='get-directions'><span class='dashicons dashicons-migrate'></span></button></form>";
					content += "</div>";
					content += "</div>";
					
					var myLatlng = new google.maps.LatLng( lat, lng );
					if( single ) {
						var panTo    = new google.maps.LatLng( lat, lng );
					} else {
						var panTo    = new google.maps.LatLng( lat + 0.003, lng - 0.003);
					}

					var	infowindow = new google.maps.InfoWindow({ content: content, maxWidth: 200 });
						
					var image = {
							url: icon_file,
							// This marker is 20 pixels wide by 32 pixels tall.
							size: new google.maps.Size( icon_width, icon_height ),
							// The origin for this image is 0,0.
							origin: new google.maps.Point( icon_width, 0 ),
							// The anchor for this image is the base of the flagpole at 0,32.
							//anchor: new google.maps.Point( icon_width / 2, icon_height )
						};

					var marker = new google.maps.Marker({
							position: myLatlng,
							map: map,
							title: data.title,
							icon: image
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