(function( $ ) { 'use strict';

    $( document ).ready( function() {
        var BOSEDD_Admin = {
            init: function() {
                this.settingTabs();
            },

            /**
             * Admin Script
             */
            settingTabs: function() {
                $( "#setting_tabs" ).tabs().parents(".bosedd-settings-wrapper").show();
                $( ".bosedd-notice" ).removeClass( "hidden" );
            },
        };

        //BOSEDD_Admin.init();
    });
})( jQuery );

		
		