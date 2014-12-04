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
	function fc_showDialog(url, tagid){
		// Initialize popup container
		var winwidth = jQuery( window ).width() - 80;
		var winheight= jQuery( window ).height() - 80;
		// Get container creating it if it does not exist
		var container = jQuery('#'+tagid);
		if (!container.length) {
			container = jQuery('<div id="'+tagid+'"></div>').appendTo(document.body);
		}
		container.dialog({
		   autoOpen: false,
		   width: winwidth,
		   height: winheight,
		   modal: true,
		   position: [40, 40]
		});
		
		jQuery('#'+tagid).load(url);
		var theDialog = jQuery('#'+tagid).dialog('open');
		return theDialog;  // Return the dialog element for usage by the caller
	}
