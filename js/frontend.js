(function( $ ) {
"use strict";


		$( ".stswproaccordion,.stswproaccordion2" ).accordion({
			collapsible: true,active: false

		});
		$( ".stswproaccordion3" ).accordion({ header: "h4",active: false, collapsible: true});


		$(".STSWooCommercenew_response").on('submit',function(e){
					e.preventDefault();
						$.ajax({
							url: window.location.href,
							data:  $(this).serialize(),
							type: 'POST',
							beforeSend: function() {
								$('.stswproaccordion').addClass('loading');
							},
							success: function(data){
								$("body").html(data);
							}
						});
			});


		$(".STSWooCommercenew_ticket").on('submit',function(e){
					e.preventDefault();
						$.ajax({
							url: window.location.href,
							data:  $(this).serialize(),
							type: 'POST',
							beforeSend: function() {
								$('.stswproaccordion').addClass('loading');
							},
							success: function(data){
								$("body").html(data);
							}
						});
			});


})( jQuery )