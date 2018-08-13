	var fcfield_image = {};
	
	fcfield_image.debugToConsole = [];
	fcfield_image.use_native_apis = [];
	fcfield_image.currElement = [];
	fcfield_image.dialog_handle = [];

	fcfield_image.toggleMediaURL = function(element_id_n, config_name)
	{
		var url = jQuery("#"+element_id_n+"_mediaurl").val();
		var msg_box = jQuery("#fcfield_message_box_"+element_id_n);

		if (url != "" && jQuery("#"+element_id_n+"_mediaurl").closest('.fcfield-image-mediaurl-box').is(":visible"))
		{
			msg_box.html("<span class=\"alert alert-warning fc-iblock fcpadded\">" + Joomla.JText._('FLEXI_FIELD_IMAGE_CLEAR_MEDIA_URL_FIRST') + "</span>");
			return;
		}

		jQuery("#"+element_id_n+"_mediaurl").closest('.fcfield-image-mediaurl-box').slideToggle();
	}

	fcfield_image.clearData = function(element_id_n, config_name)
	{
		var mediaurl = jQuery("#"+element_id_n+"_mediaurl").val('');
		var msg_box = jQuery("#fcfield_message_box_"+element_id_n);
		
		mediaurl.val('');
		msg_box.html('');

		// Clear existing value
		fcfield_image.clearField(jQuery("#"+element_id_n+"_mediaurl").get(0), {}, config_name);
	}

	fcfield_image.fetchData = function(element_id_n, config_name)
	{
		var msg_box = jQuery("#fcfield_message_box_"+element_id_n);

		// if URL field is empty then nothing to do, else continue with creating the fetch URL
		var url = jQuery("#"+element_id_n+"_mediaurl").val();

		if (url == "")
		{
			msg_box.html("<span class=\"alert alert-warning fc-iblock fcpadded\">" + Joomla.JText._('FLEXI_FIELD_IMAGE_ENTER_MEDIA_URL') + "</span>");
			return;
		}
		else
		{
			// Clear any existing message
			msg_box.html("");
		}

		fcfield_image.debugToConsole[config_name] ? window.console.log("Fetching "+url) : "";

		var ajax_url;
		var ajax_type;

		// try noembed.com
		ajax_url = "https://noembed.com/embed?url="+encodeURIComponent(url); // TODO check if needed to add more URL vars
		ajax_type = "json";

		var mediaID = "";
		var apiType = "";

		//mediathumburl

		// Make AJAX call
		jQuery.ajax({
			url: ajax_url,
			dataType: ajax_type,
			success: function(data) {
				fcfield_image.debugToConsole[config_name] ? window.console.log("Received Server response") : "";
				var response;
				try {
					response = typeof data !== "object" ? jQuery.parseJSON( data ) : data;
					fcfield_image.debugToConsole[config_name] ? window.console.log("Calling callback "+(apiType ? apiType : "embedly")+" function on data:") : "";
					fcfield_image.debugToConsole[config_name] ? window.console.log(response) : "";

					if (mediaID && apiType)
					{
						switch(apiType)
						{
							case "youtube"     : fcfield_image.youtubeCallback(element_id_n, response, mediaID, config_name);  break;
							case "vimeo"       : fcfield_image.vimeoCallback(element_id_n, response, mediaID, config_name);  break;
							case "dailymotion" : fcfield_image.dailymotionCallback(element_id_n, response, mediaID, config_name);  break;
						}
					}
					else
					{
						if (typeof response.error !== "undefined")
						{
							msg_box.html("<span class=\"alert alert-info fc-iblock fcpadded\">" + response.error + " <br>This seems link a non-media URL</span>");
						}
						else
						{
							//fcfield_image.noembedCallback(element_id_n, response, config_name);
							jQuery("#"+element_id_n+"_mediathumburl").val(response.thumbnail_url);

							// Clear existing value
							fcfield_image.clearField(jQuery("#"+element_id_n+"_mediaurl").get(0), {}, config_name);

							// Assign fetched preview URL
							var filename = response.thumbnail_url;
							var preview_url = response.thumbnail_url;
							var preview_caption = Joomla.JText._('FLEXI_FIELD_MEDIA_URL');
							fcfield_image.assignImage(element_id_n, filename, preview_url, 2, preview_caption, config_name);
						}
					}
				} catch(err) {
					msg_box.html("<span class=\"alert alert-error alert-danger fc-iblock fcpadded\">" + Joomla.JText._(/*'PLG_FLEXICONTENT_' + */'FIELDS_IMAGE_RESPONSE_PARSING_FAILED') + ": " + err.message + "</span>");
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				fcfield_image.debugToConsole[config_name] ? window.console.log("Error, responseText is:") : "";
				fcfield_image.debugToConsole[config_name] ? window.console.log(jqXHR.responseText) : "";
				var response;
				try {
					response = jQuery.parseJSON( jqXHR.responseText );
				} catch(err) {
					response = jqXHR.responseText;
				}
				var errorText = typeof response !== "object" ? response : (mediaID && (apiType=="dailymotion" || apiType=="youtube")  ? response.error.message : response.error_message);
				if (apiType=="youtube" && typeof response == "object") errorText += " Reason: "  +response.error.errors[0].reason;
				msg_box.html("<span class=\"alert alert-error alert-danger fc-iblock fcpadded\"><i>" + Joomla.JText._(/*'PLG_FLEXICONTENT_' + */'FIELDS_IMAGE_SERVER_RESPONDED_WITH_ERROR') + "</i><br/><br/>" + errorText + "</span>");
			}
		});
	}


	fcfield_image.incrementValCnt = function(config_name)
	{
		var box = jQuery('#'+fcfield_image.currElement[config_name]).closest('.fcfieldval_container');
		var hasValue = box.find('.hasvalue').val();
		box.find('.hasvalue').val('1');
		var valcounter = document.getElementById('custom_' + config_name);
		if (hasValue=='')
		{
			valcounter.value = valcounter.value==''  ?  '1'  :  parseInt(valcounter.value) + 1;
		}
		//if (window.console) window.console.log('valcounter: ' + valcounter.value);
	}


	fcfield_image.clearFieldUploader = function(box, config_name)
	{
		var upload_container = box.find('.fc_file_uploader');
		var upload_instance = upload_container.data('plupload_instance');

		var upBTN = box.find('.fc_files_uploader_toggle_btn');
		if (upload_instance)
		{
			jQuery(upload_instance).data('fc_plupload_instance').clearUploader(upBTN.data('rowno'));
		}
		upBTN.removeClass('active');
		upload_container.hide();
	}


	fcfield_image.clearField = function(el, options, config_name)
	{
		var box = jQuery(el).closest('.fcfieldval_container');
		var hasValue = box.find('.hasvalue').val();
		var valcounter = document.getElementById('custom_' + config_name);
		//if (window.console) window.console.log('valcounter: ' + valcounter.value);

		options = options || {};
		options.hide_image = options.hide_image || false;
		options.keep_props = options.keep_props || false;

		if (options.hide_image)
		{
			box.find('.fcimg_preview_box').hide();
		}
		else
		{
			fcfield_image.clearFieldUploader(box, config_name);

			box.find('.originalname').val('');
			box.find('.existingname').val('');
			box.find('.hasvalue').val('');
			box.find('.preview_image').attr('src', 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
			box.find('.fcimg_preview_msg').html(' ');
			box.find('.fcimg_preview_box').show();

			if (hasValue) valcounter.value = ( valcounter.value=='' || valcounter.value=='1' )  ?  ''  :  parseInt(valcounter.value) - 1;
			//if (window.console) window.console.log('valcounter: ' + valcounter.value);
		}
		if (options.keep_props)
		{
			box.find('input, textarea').val('');
		}
	}


	fcfield_image.assignImage = function(tagid, file, preview_url, keep_modal, preview_caption, config_name)
	{
		// Get TAG ID of the main form element of this field
		var ff_suffix = '_existingname';
		var elementid_n = tagid.replace(ff_suffix, '');

		// Get current has-value Flag and also set new value to the flag
		var valcounter = document.getElementById('custom_' + config_name);
		var hasvalue_obj = jQuery('#' + elementid_n);
		var hasValue = hasvalue_obj.val();
		hasvalue_obj.val(file ? '1' : '');

		// Increment/Make non-empty the form field used as value counter, so that is-required validation works
		if (file && !hasValue)
		{
			valcounter.value = valcounter.value==''  ?  1  :  parseInt(valcounter.value) + 1;
		}

		// Decrement/Make empty the form field used as value counter, so that is-required validation works
		else if (!file && hasValue)
		{
			valcounter.value = ( valcounter.value=='' || valcounter.value=='1' )  ?  ''  :  parseInt(valcounter.value) - 1;
		}
		//if (window.console) window.console.log('valcounter: ' + valcounter.value);

		// Set existing value & Clear original value for both DB-mode & Folder-mode(s)
		jQuery('#' + elementid_n + '_existingname').val(file);
		jQuery('#' + elementid_n + '_originalname').val('');

		// Replace old preview image
		var preview_img_OLD = jQuery('#' + elementid_n + '_preview_image');
		if (preview_img_OLD)
		{
			var box = preview_img_OLD.closest('.fcfieldval_container');
			var preview_img_NEW = preview_url != ''
				? '<img class=\"preview_image\" id=\"' + elementid_n + '_preview_image\" src=\"'+preview_url+'\" alt=\"Preview image\" />'
				: '<img class=\"preview_image\" id=\"' + elementid_n + '_preview_image\" src=\"data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=\" alt=\"Preview image\" />';

			preview_img_NEW = jQuery(preview_img_NEW);
			preview_img_NEW.insertAfter( preview_img_OLD );
			preview_img_OLD.remove();

			if (keep_modal != 2)
			{
				preview_img_NEW.closest('.fcimg_preview_box').show();
				fcfield_image.clearFieldUploader(box, config_name);
			}

			// Set new preview text too (a 'title')
			if (file)
			{
				jQuery('#' + elementid_n + '_fcimg_preview_msg' ).html(!!preview_caption ? preview_caption : file);
				jQuery('#' + elementid_n + '_fcimg_preview_msg' ).attr('title', !!preview_caption ? file : '')
			}

			jQuery('#' + elementid_n + '_remove').removeAttr('checked').trigger('change');
		}

		// Close file select modal dialog
		if (!keep_modal && !!fcfield_image.dialog_handle[config_name])
		{
			fcfield_image.dialog_handle[config_name].dialog('close');
		}

		// Re-validate
		jQuery(valcounter).trigger('blur');
		//if (window.console) window.console.log('valcounter: ' + valcounter.value);
	}


	fcfield_image.fileFiltered = function(uploader, file, config_name)
	{
	}


	fcfield_image.fileUploaded = function(uploader, file, result, config_name)
	{
		// Get 'fc_plupload' class instance from uploader
		var _this = jQuery(uploader).data('fc_plupload_instance');
		try {
			var response = eval(result.response);
		} catch(err) {
			var response = eval('(' + result.response + ')');
		}

		if (!!response.error)
		{
			alert(response.error.message);
			return;
		}

		//window.console.log(response.data);
		var file = response.data;
		file.targetid    = jQuery(uploader.settings.container).closest('.fcfieldval_container').find('.existingname').attr('id');
		file.preview_url = jQuery(uploader.settings.container).find('.plupload_img_preview > img').attr('src');
		fcfield_image.assignImage(file.targetid, file.filename, file.preview_url, 2, file.filename_original, config_name);
	}