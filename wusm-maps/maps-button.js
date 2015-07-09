( function( e ) {
	tinymce.PluginManager.add( 'wusm_maps_button', function( editor, url ) {
		var menu_array = [
			{
				text: 'WUSM Map',
				icon: false,
				onclick: function( ) {
					// if you change the short code, make sure you change this too!!!
					editor.insertContent( '[wusm_map]' );
				}
			}
		];

		if( editor.buttons.wusmbutton ) {
			for(var index in menu_array) { 
				editor.buttons.wusmbutton.menu.push( menu_array[index] );
			}
		} else {
			editor.addButton( 'wusmbutton', {
				title: 'Insert',
				image: url + '/add.svg',
				type:  'menubutton',
				menu:  menu_array
			} );	
		}
	} );
} )( jQuery );