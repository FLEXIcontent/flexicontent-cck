	function fc_loadImagePreview(input_id, img_id, msg_id, thumb_w, thumb_h)
	{
		var input = document.getElementById(input_id);
		var input_files = input.files;
		if (input_files && input_files[0]) {
			var imageType = /image.*/;
			if (!input_files[0].type.match(imageType)) {
		  	document.getElementById(msg_id).innerHTML = Joomla.JText._('FLEXI_NOT_AN_IMAGE_FILE');
		  	jQuery('#'+img_id).hide();
		  } else {
		  	document.getElementById(msg_id).innerHTML = '';
				var reader = new FileReader();
				reader.onload = function (e) {
					jQuery('#'+img_id)
					.attr('src', e.target.result)
					.width(thumb_w).height(thumb_h)
					.show();
				};
				reader.readAsDataURL(input_files[0]);
			}
		}
	}
	
	// Load given URL in an open dialog
	function fc_showDialog(url, tagid, no_iframe, winwidth, winheight)
	{
		// Initialize popup container
		var winwidth  = typeof winwidth !== 'undefined' && winwidth ? winwidth : jQuery( window ).width() - 80;
		var winheight = typeof winheight!== 'undefined' && winheight ? winheight : jQuery( window ).height() - 100;
		winwidth  = winwidth  > (jQuery( window ).width() - 80)   ? (jQuery( window ).width() - 80)   : winwidth;
		winheight = winheight > (jQuery( window ).height() - 100) ? (jQuery( window ).height() - 100) : winheight;
		//window.console.log ('winwidth  : ' + winwidth  + ', winheight : ' + winheight );

		var winleft = (jQuery( window ).width() - winwidth) / 2;
		var wintop  = (jQuery( window ).height() - winheight) / 2 - 5;
		//window.console.log ('winleft : ' + winleft + ', wintop : ' + wintop);
		
		// Get container creating it if it does not exist
		var container = jQuery('#'+tagid);
		if (!container.length) {
			container = jQuery('<div id="'+tagid+'"></div>').appendTo(document.body);
		}
		
		// Add loading animation
		var loading = jQuery('<div id="'+tagid+'_loading" class="fc_loading_msg" style="position:absolute; background-color:transparent;">loading...</div>');
		container.prepend(loading);
		
		// Add the iframe
		var iframe;
		if (!no_iframe) {
			iframe = jQuery('<iframe id="'+tagid+'_frame" style="visibility:hidden; width:100%; height:100%; border:0; margin:0; padding:0;" src=""></iframe>');
			container.append(iframe);
			iframe.load(function() {
				iframe.show().css('visibility', 'visible');
				loading.hide();
			});
		}
		
		// Contaner CSS depending on usage of iframe
		no_iframe ?
			container.css('overflow', 'scroll') :
			container.css('overflow', 'hidden').css('padding', '0') ;
		
		container.dialog({
			autoOpen: false,
			width: winwidth,
			height: winheight,
			modal: true,
			position: [winleft, wintop],
			// Load contents (url) when dialog opens
			open: function(ev, ui){
				no_iframe ?
					container.load(url) :
					jQuery('#'+tagid+'_frame').attr('src', url);
			},
			// Clear contents after dialog closes
			close: function(ev, ui){
				no_iframe ?
					container.html('') :
					jQuery('#'+tagid+'_frame').remove();
			}
		});
		
		// Open the dialog and return a reference of the dialog to the caller
		var theDialog = container.dialog('open');
		return theDialog;
	}
	
	// Scroll into view
	function fc_scrollIntoView (elem, smooth)
	{
		var cTop = elem.offset().top;
		var cHeight = elem.outerHeight(true);
		var windowTop = jQuery(window).scrollTop();
		var visibleHeight = jQuery(window).height();
		var top_extra = 90;
		var bottom_extra = 80;
	
		//window.console.log ('cTop: ' + cTop + ' , windowTop: ' + windowTop);
		//window.console.log ('cHeight: ' + cHeight + ' , visibleHeight: ' + visibleHeight);
		if (cTop - top_extra < windowTop) {
			if (smooth) {
				jQuery('html, body').animate({scrollTop: cTop - top_extra}, 400);
			} else {
				jQuery('html, body').scrollTop(cTop - top_extra);
			}
		} else if (cTop + cHeight + bottom_extra > windowTop + visibleHeight) {
			if (smooth) {
				jQuery('html, body').animate({scrollTop: cTop - visibleHeight + cHeight + bottom_extra}, 400);
			} else {
				jQuery('html, body').scrollTop(cTop - visibleHeight + cHeight + bottom_extra);
			}
		}
	}

	// Add toggling of dependent form elements
	function fc_bind_form_togglers(container)
	{
		var noFX = 1;
		
		// select
		jQuery('#'+container+' select.fcform_toggler_element').change(function() {
			var el = jQuery('option:selected', this);
			var show_list = el.attr('show_list').split(',');
			var hide_list = el.attr('hide_list').split(',');
			jQuery.each( hide_list, function( i, val ) {  if (val) jQuery('.'+val).hide(noFX ? '' : 'fast');  });
			jQuery.each( show_list, function( i, val ) {  if (val) jQuery('.'+val).show(noFX ? '' : 'slow');  });
		});
		
		// radio
		jQuery(document).on('click', '#'+container+' .fcform_toggler_element input:radio', function(event) {
			//alert('reached');
			var el = jQuery(this);
			var show_list = el.attr('show_list').split(',');
			var hide_list = el.attr('hide_list').split(',');
			jQuery.each( hide_list, function( i, val ) {  if (val) jQuery('.'+val).hide(noFX ? '' : 'fast');  });
			jQuery.each( show_list, function( i, val ) {  if (val) jQuery('.'+val).show(noFX ? '' : 'slow');  });
		});
		
		// Update the form
		jQuery('form select.fcform_toggler_element').trigger('change');
		jQuery('form .fcform_toggler_element input[type="radio"]:checked').trigger('click');
		//setTimeout(function(){ noFx = 0; }, 200);
	}
	