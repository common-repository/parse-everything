(function($) {

	$(function() {
		$( '#pe_send_type' ).trigger( 'change' );

		// initalise the dialog
		$('.pe_edit-dialog').dialog({
			title: 'Additional options',
			dialogId: 'wp-dialog',
			dialogClass: 'wp-dialog',
			autoOpen: false,
			draggable: false,
			width: 'auto',
			modal: true,
			resizable: false,
			closeOnEscape: true,
			show: {
				duration: 500
			},
			hide: {
				duration: 500
			},
			position: {
				my: 'center',
				at: 'center',
				of: window
			},
			open: function () {
				const dialog = $( this ).closest( '.pe_edit-dialog' )
				// close dialog by clicking the overlay behind it
				$('.ui-widget-overlay').unbind( 'click' ).bind('click', () => {
					dialog.dialog('close');
				});
				// on save
				$( '#pe_save' ).unbind( 'click' ).bind('click', () => {
					let settings = {};
					dialog.find('.pe_selector_attribute_dialog').each(function(){
						const name = $(this).prop('name');
						if ( ! $(this).is(':checkbox') || $(this).is(':checked') ) {
							settings[name] = $(this).val();
						} else {
							settings[name] = '';
						}
					});

					dialog.dialog('close');
					$('.pe_editing').find('.pe_selector_attribute').val( JSON.stringify( settings ) );
				});
			},
			create: function () {
			},
		});

		// Open selector settings dialog
		$('.pe_settings').on( 'click', '.open-pe-dialog', function(e) {
			e.preventDefault();
			const $this = $( e.currentTarget ),
				dialog = $('.' + $this.data('dialog_name') ),
				td = $this.closest('td');

			// Reset settings
			$('[name]:not(#pe_save)', dialog ).each(function() {
				if ( $(this).is(':checkbox') ) {
					$(this).prop('checked', false);
				} else {
					$(this).val('');
				}
			});
			// Set settings
			const settingsVal = td.find('.pe_selector_attribute').val();

			try {
				var settings = $.parseJSON( settingsVal );
			} catch(err) {
				var settings = {};
			}

			$.each(settings, function(i,v){
				const option = dialog.find('.pe_selector_attribute_dialog[name="' + i + '"]');
				if ( option.is(':checkbox') ) {
					option.prop( 'checked', Boolean( v ) );
				} else {
					option.val( v );
				}
			});
			$('.pe_editing').removeClass('pe_editing');
			td.addClass('pe_editing');
			dialog.dialog('open');
		});

		// Hide Body field for GET method
		$('select[name="request_args[method]"]').on( 'change', function(e) {
			const val = $(this).val(),
				bodyTr = $('textarea[name="request_args[body]"]').closest('tr');

			if ( 'POST' === val ) {
				bodyTr.show();
			} else {
				bodyTr.hide();
			}
		}).trigger( 'change' );

	});

	$( 'html' ).on( 'click', '#pe_try_to_parse', function(e) {
		var $spinner = $( this ).siblings( '.spinner' ),
			form = $( this ).closest( 'form' ),
			formData = form.serialize(),
			data = {
				action: 'pe_try_to_parse',
				_wpnonce: $( this ).data( 'nonce' ),
				data: formData
			};

		$spinner.addClass( 'is-active' );

		$.post( ajaxurl, data, function( res ) {
			$spinner.removeClass( 'is-active' );

			if ( res.success ) {
				$( '#pe_result' ).html( res.data );
			} else {
				$( '#pe_result' ).html( '<div class="pe_error">' +  res.data + '</div>' );
			}

		});
	});

	$( 'html' ).on( 'change', '#pe_send_type', function(e) {
		var val = $( this ).val();
		$( '#pe_send_settings .pe_toggle' ).addClass( 'hidden' );
		$( '#pe_send_settings .pe_toggle.pe_' + val ).removeClass( 'hidden' );
	});

	$( 'html' ).on( 'click', '#pe_try_to_send', function(e) {
		var $spinner = $( this ).siblings( '.spinner' ),
			form = $( this ).closest( 'form' ),
			formData = form.serialize(),
			data = {
				action: 'pe_try_to_send',
				_wpnonce: $( this ).data( 'nonce' ),
				data: formData
			};

		$spinner.addClass( 'is-active' );
		$("#pe_send_result ").html('').show();

		$.post( ajaxurl, data, function( res ) {
			$spinner.removeClass( 'is-active' );

			if ( res.success ) {
				$( '#pe_send_result' ).html( '<span class="pe_success">' +  res.data + '</span>' );
			} else {
				$( '#pe_send_result' ).html( '<span class="pe_error">' +  res.data + '</span>' );
			}
			$("#pe_send_result ").delay(5000).fadeOut('slow');

		});
	});

	$( 'html' ).on( 'click', '.pe_add_selector', function(e) {
		var $td = $( this ).closest( 'td' ).clone(),
			$tr = $( this ).closest( 'tr' );

		$td.find( 'input' ).attr( 'value', '');

		$tr.after( '<tr><td></td><td>' + $td.html() +'</td></tr>' );
	});

	$( 'html' ).on( 'click', '.pe_remove_selector', function(e) {
		$( this ).closest( 'tr' ).remove();
	});
	$( 'html' ).on( 'change', '#pe_url', function(e) {
		var url = $( this ).val();
		$( '.pe_goto_url' ).attr( 'href', url );
	});

})(jQuery);
