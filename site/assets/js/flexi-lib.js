	function loadImagePreview(input_id, img_id, msg_id, thumb_w, thumb_h)
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
