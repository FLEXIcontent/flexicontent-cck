	var fcfield_weblink = {};
	
	fcfield_weblink.debugToConsole = [];
	fcfield_weblink.use_native_apis = [];
	fcfield_weblink.currElement = [];
	fcfield_weblink.dialog_handle = [];


	fcfield_weblink.fetchData = function(element_id_n, config_name)
	{
		var msg_box = jQuery("#fcfield_message_box_"+element_id_n);

		// if URL field is empty then nothing to do, else continue with creating the fetch URL
		var url = jQuery("#"+element_id_n+"_link").val();

		if (url == "")
		{
			msg_box.html("<span class=\"alert alert-warning fc-iblock fcpadded\">" + Joomla.JText._('FLEXI_FIELD_WEBLINK_ENTER_MEDIA_URL') + "</span>");
			return;
		}
		else
		{
			// Clear any existing message
			msg_box.html("");
		}

		fcfield_weblink.debugToConsole[config_name] ? window.console.log("Fetching "+url) : "";

		var ajax_url;
		var ajax_type;
		var urlType = '';

		// Try youtube
		var myregexp = /(?:youtube(?:-nocookie)?\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i;
		if (url.match(myregexp) != null)
		{
			urlType = "youtube";
		}

		// Try vimeo
		var myregexp = /https?:\/\/(www\.)?vimeo.com\/(\d+)($|\/)/;
		if (url.match(myregexp) != null)
		{
			urlType = "vimeo";
		}
		
		if (!urlType)
		{
			msg_box.html("<span class=\"alert alert-warning fc-iblock fcpadded\">" + Joomla.JText._('FLEXI_FIELD_WEBLINK_ENTER_MEDIA_URL_WARNING') + "</span>");
			return;
		}
	
		// try noembed.com
		ajax_url = "https://noembed.com/embed?url="+encodeURIComponent(url); // TODO check if needed to add more URL vars
		ajax_type = "jsonp";

		var mediaID = "";
		var apiType = "";

		//mediathumburl

		// Make AJAX call
		jQuery.ajax({
			/*beforeSend: function(xhrObj) {
				xhrObj.setRequestHeader("Access-Control-Allow-Headers", "Origin, X-CSRF-Token, X-Requested-With, Content-Type, Accept, Authorization");
			},*/
			url: ajax_url,
			dataType: ajax_type,
			success: function(data) {
				fcfield_weblink.debugToConsole[config_name] ? window.console.log("Received Server response") : "";
				var response;
				try {
					response = typeof data !== "object" ? jQuery.parseJSON( data ) : data;
					fcfield_weblink.debugToConsole[config_name] ? window.console.log("Calling callback "+(apiType ? apiType : "embedly")+" function on data:") : "";
					fcfield_weblink.debugToConsole[config_name] ? window.console.log(response) : "";

					if (mediaID && apiType)
					{
						switch(apiType)
						{
							case "youtube"     : fcfield_weblink.youtubeCallback(element_id_n, response, mediaID, config_name);  break;
							case "vimeo"       : fcfield_weblink.vimeoCallback(element_id_n, response, mediaID, config_name);  break;
							case "dailymotion" : fcfield_weblink.dailymotionCallback(element_id_n, response, mediaID, config_name);  break;
						}
					}
					else
					{
						if (typeof response.error !== "undefined")
						{
							msg_box.html("<span class=\"alert alert-warning fc-iblock fcpadded\">Reply: " + response.error + " <br>This link is a not video / media URL ?</span>");

							// Clear existing value
							fcfield_weblink.clearImageThumb(jQuery("#"+element_id_n+"_image").get(0), {}, config_name);
						}
						else
						{
							//fcfield_weblink.noembedCallback(element_id_n, response, config_name);
							jQuery("#"+element_id_n+"_mediathumburl").val(response.thumbnail_url);

							// Clear image base path since we will use full URL
							jQuery("#"+element_id_n+"_image").data('basepath', '');

							// Assign fetched preview URL
							var filename = response.thumbnail_url;
							var preview_url = response.thumbnail_url;
							var preview_caption = Joomla.JText._('FLEXI_FIELD_MEDIA_URL');

							jInsertFieldValue(response.thumbnail_url, element_id_n + '_image');
							//fcfield_weblink.assignVideoImage(element_id_n, filename, preview_url, 2, preview_caption, config_name);
						}
					}
				} catch(err) {
					msg_box.html("<span class=\"alert alert-error alert-danger fc-iblock fcpadded\">" + Joomla.JText._(/*'PLG_FLEXICONTENT_' + */'FIELDS_IMAGE_RESPONSE_PARSING_FAILED') + ": " + err.message + "</span>");
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				fcfield_weblink.debugToConsole[config_name] ? window.console.log("Error, responseText is:") : "";
				fcfield_weblink.debugToConsole[config_name] ? window.console.log(jqXHR.responseText) : "";
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

	fcfield_weblink.update_path_tip = function(el)
	{
		var tipped_elements = jQuery(el).closest('.fc-field-props-box').find('.hasTipImgpath, .hasTipPreview');
		tipped_elements.each(function()
		{
			var title = this.get('title') || this.get('value');
			if (title)
			{
				var parts = title.split('::', 2);
				this.store('tip:title', parts[0]);
				this.store('tip:text', parts[1]);
			}
		});
	}


	fcfield_weblink.clearImageThumb = function(el, options, config_name)
	{
		var box   = jQuery(el).closest('.fcfieldval_container');
		var mm_el = box.find('.urlimage');
		var mm_id = mm_el.attr('id'); 
		mm_el.data('basepath', '');
		jInsertFieldValue('', mm_id);
	}

	fcfield_weblink.assignVideoImage = function(tagid, file, preview_url, keep_modal, preview_caption, config_name)
	{
	}
