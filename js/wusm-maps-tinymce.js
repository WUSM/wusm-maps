( function( e ) {
	e( function( ) {
	tinymce.create( 'tinymce.plugins.wusm_maps_mce_button', {
		init : function( editor, url ) {
			if( editor.buttons.wusmbutton ) {
				editor.buttons.wusmbutton.menu.push(
				{
					text: 'Map',
					icon: false,
					onclick: function() {
						// if you change the short code, make sure you change this too!!!
						editor.insertContent('[wusm_map]');
					}
				});
			} else {
				url = url.substring(0, url.length - 2);
				editor.addButton( 'wusmbutton', {
					title: 'Insert',
					icon: 'icon dashicons-plus-alt',
					type: 'menubutton',
					menu: [
						{
							text: 'Map',
							icon: false,
							onclick: function() {
								// if you change the short code, make sure you change this too!!!
								editor.insertContent('[wusm_map]');
							}
						},
					]
				});
			}
		},

		createControl : function( n, cm ) {
			return null;
		},
 
		getInfo : function( ) {
			return {
				longname : 'WUSM Button',
				author : 'Medical Public Affairs',
				authorurl : 'http://medicine.wustl.edu',
				version : "1.0"
			};
		}
	} );

	tinymce.PluginManager.add( 'wusm_maps_mce_button', tinymce.plugins.wusm_maps_mce_button );
	} )
} )( jQuery );