	var fcfield_sharemedia = {};
	
	fcfield_sharemedia.fc_mediaID_tmp = null;
	fcfield_sharemedia.fc_elemID_tmp = null;
	fcfield_sharemedia.debugToConsole = [];
	fcfield_sharemedia.use_native_apis = [];
	fcfield_sharemedia.youtube_key = [];


	fcfield_sharemedia.youtubeDurationToSeconds = function(duration)
	{
		var match = duration.match(/PT(\d+H)?(\d+M)?(\d+S)?/);
		var hours = (parseInt(match[1]) || 0);
		var minutes = (parseInt(match[2]) || 0);
		var seconds = (parseInt(match[3]) || 0);
		return hours * 3600 + minutes * 60 + seconds;
	}


	fcfield_sharemedia.setHeight = function(selector, factor)
	{
		jQuery(selector).each( function() {
			jQuery(this).css("height", parseInt(factor*jQuery(this).width()));
		});
	}


	fcfield_sharemedia.clearData = function(element_id_n, config_name)
	{
		// Clear embed Data and its preview HTML
		fcfield_sharemedia.setEmbedData(element_id_n, "", "", config_name);

		// Clear META Data
		fcfield_sharemedia.setMetaData(element_id_n, {title: "", author: "", duration: "", description: "", thumb: ""}, config_name);

		// Clear other data
		jQuery("#" + element_id_n + "_height").val("");
		jQuery("#" + element_id_n + "_width").val("");

		// Hide the value rows
		fcfield_sharemedia.toggleMETArows(element_id_n, -1);
	}


	fcfield_sharemedia.fetchData = function(element_id_n, config_name)
	{
    // Joomla Base URL
		var base_url = !!jbase_url_fc ? jbase_url_fc : '';

		var msg_box = jQuery("#fcfield_message_box_"+element_id_n);

		// Clear any existing message
		msg_box.html("");

		// Clear existing value
		fcfield_sharemedia.clearData(element_id_n, config_name);

		// if URL field is empty then nothing to do, else continue with creating the fetch URL
		var url = jQuery("#"+element_id_n+"_url").val();
		if (url=="") return;

		msg_box.html('<img src="'+base_url+'components/com_flexicontent/assets/images/ajax-loader.gif" style="vertical-align: middle;">');

		fcfield_sharemedia.debugToConsole[config_name] ? window.console.log("Fetching "+url) : "";

		var mediaID = "";
		var apiType = "";

		if (fcfield_sharemedia.use_native_apis[config_name])
		{
			// Try youtube
			var myregexp = /(?:youtube(?:-nocookie)?\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i;
			if (url.match(myregexp) != null)
			{
				mediaID = url.match(myregexp)[1];
				apiType = "youtube";
			}

			// Try vimeo
			var myregexp = /https?:\/\/(www\.)?vimeo.com\/(\d+)($|\/)/;
			if (url.match(myregexp) != null)
			{
				mediaID = url.match(myregexp)[2];
				apiType = "vimeo";
			}

			// Try dailymotion
			var myregexp = /^.+dailymotion.com\/(video|hub)\/([^_]+)[^#]*(#video=([^_&]+))?/;
			if (url.match(myregexp) != null)
			{
				mediaID = url.match(myregexp)[4]!== undefined ? url.match(myregexp)[4] : url.match(myregexp)[2];
				apiType = "dailymotion";
			}
		}

		// Global variables, needed if set-embed-data functions are called as callbacks, thus these can not be passed as parameters
		fcfield_sharemedia.fc_elemID_tmp = element_id_n;
		fcfield_sharemedia.fc_mediaID_tmp = mediaID;

		// Create AJAX url
		var ajax_url;
		var ajax_type;
		if (mediaID && apiType)
		{
			fcfield_sharemedia.debugToConsole[config_name] ? window.console.log("Media type: "+apiType) : "";
			fcfield_sharemedia.debugToConsole[config_name] ? window.console.log("Media ID: "+mediaID) : "";
			switch(apiType)
			{
				case "youtube"    : ajax_url = "https://www.googleapis.com/youtube/v3/videos?id="+mediaID+"&key="+fcfield_sharemedia.youtube_key[config_name]+"&part=snippet,contentDetails,statistics,status";/*&callback=fcfield_sharemedia.youtubeCallback";*/  break;
				case "vimeo"      : ajax_url = "//vimeo.com/api/v2/video/"+mediaID+".json";/*?callback=fcfield_sharemedia.vimeoCallback";*/  break;
				case "dailymotion": ajax_url = "https://api.dailymotion.com/video/"+mediaID+"?fields=description,duration,owner.screenname,thumbnail_60_url,title";/*&callback=fcfield_sharemedia.dailymotionCallback";*/  break;
			}
			ajax_type = "jsonp";
		}
		else
		{
			// try noembed.com
			ajax_url = "https://noembed.com/embed?url="+encodeURIComponent(url); // TODO check if needed to add more URL vars
			ajax_type = "jsonp";
		}

		// Make AJAX call
		jQuery.ajax({
			/*beforeSend: function(xhrObj) {
				xhrObj.setRequestHeader("Access-Control-Allow-Headers", "Origin, X-CSRF-Token, X-Requested-With, Content-Type, Accept, Authorization");
			},*/
			url: ajax_url,
			dataType: ajax_type,
			success: function(data) {
				fcfield_sharemedia.debugToConsole[config_name] ? window.console.log("Received Server response") : "";
				var response;
				try {
					if (ajax_type=="html") 	data = data.replace(/_cbfunc_\(/, "").replace(/\)?;?$/, "");
					response = typeof data !== "object" ? jQuery.parseJSON( data ) : data;
					fcfield_sharemedia.debugToConsole[config_name] ? window.console.log("Calling callback "+(apiType ? apiType : "embedly")+" function on data:") : "";
					fcfield_sharemedia.debugToConsole[config_name] ? window.console.log(response) : "";

					if (mediaID && apiType)
					{
						switch(apiType)
						{
							case "youtube"     : fcfield_sharemedia.youtubeCallback(element_id_n, response, mediaID, config_name);  break;
							case "vimeo"       : fcfield_sharemedia.vimeoCallback(element_id_n, response, mediaID, config_name);  break;
							case "dailymotion" : fcfield_sharemedia.dailymotionCallback(element_id_n, response, mediaID, config_name);  break;
						}
					}
					else
					{
						fcfield_sharemedia.noembedCallback(element_id_n, response, config_name);
					}
				}
				catch(err) {
					msg_box.html("<span class=\"alert alert-warning fc-iblock\">"
						+ Joomla.JText._('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_RESPONSE_PARSING_FAILED')
						+ ": " + err.message +
					"</span>");
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				fcfield_sharemedia.debugToConsole[config_name] ? window.console.log("Error, responseText is:") : "";
				fcfield_sharemedia.debugToConsole[config_name] ? window.console.log(jqXHR.responseText) : "";
				var response;
				try {
					response = jQuery.parseJSON( jqXHR.responseText );
				} catch(err) {
					response = jqXHR.responseText;
				}
				var errorText = typeof response !== "object"
					? response : (mediaID && (apiType=="dailymotion" || apiType=="youtube")
						? response.error.message
						: response.error_message
					);
				if (apiType=="youtube" && typeof response == "object")
				{
					errorText += '<br>' + Joomla.JText._('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_REASON') + ': ' + response.error.errors[0].reason;
				}
				msg_box.html("<span class=\"alert alert-warning fc-iblock\"><i>" + Joomla.JText._('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_SERVER_RESPONDED_WITH_ERROR') + "</i><br/><br/>" + errorText + "</span>");
			}
		});
	}


	fcfield_sharemedia.youtubeCallback = function(element_id_n, data, mediaID, config_name)
	{
		mediaID = typeof mediaID === "undefined" || !mediaID  ?  fcfield_sharemedia.fc_mediaID_tmp : mediaID;    // *** mediaID not set if called as callback
		element_id_n = typeof element_id_n === "undefined" || !element_id_n  ?  fcfield_sharemedia.fc_elemID_tmp : element_id_n;    // *** element_id_n not set if called as callback

		if (typeof data === "object" && typeof data.error === "undefined" )
		{
			if (data.items.length == 0)
			{
				jQuery("#fcfield_message_box_" + element_id_n).html("<span class=\"alert alert-warning fc-iblock\">Not found</span>");
				return;
			}
			fcfield_sharemedia.toggleMETArows(element_id_n, 1);
			fcfield_sharemedia.setEmbedData(element_id_n, "youtube", mediaID, config_name);
			fcfield_sharemedia.setMetaData(element_id_n, {
				title: data.items[0].snippet.title,
				author: data.items[0].snippet.channelTitle,
				duration: fcfield_sharemedia.youtubeDurationToSeconds(data.items[0].contentDetails.duration),
				description: data.items[0].snippet.description,
				thumb: data.items[0].snippet.thumbnails.medium.url
			}, config_name);
			jQuery("#fcfield_message_box_" + element_id_n).html("");
		}
		else
		{
			var errorText = typeof data === "object"
				? data.error.message + '<br>' + Joomla.JText._('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_REASON') + ": " + data.error.errors[0].reason
				: data;
			jQuery("#fcfield_message_box_" + element_id_n).html("<span class=\"alert alert-warning fc-iblock\"><i>" + Joomla.JText._('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_SERVER_RESPONDED_WITH_ERROR') + "</i><br/><br/>" + errorText + "</span>");
		}
	}


	fcfield_sharemedia.vimeoCallback = function(element_id_n, data, mediaID, config_name)
	{
		mediaID = typeof mediaID === "undefined" || !mediaID  ?  fcfield_sharemedia.fc_mediaID_tmp : mediaID;    // *** mediaID not set if called as callback
		element_id_n = typeof element_id_n === "undefined" || !element_id_n  ?  fcfield_sharemedia.fc_elemID_tmp : element_id_n;    // *** element_id_n not set if called as callback

		if (typeof data === "object" && data.type != "error")
		{
			fcfield_sharemedia.toggleMETArows(element_id_n, 1);
			fcfield_sharemedia.setEmbedData(element_id_n, "vimeo", mediaID, config_name);
			fcfield_sharemedia.setMetaData(element_id_n, {
				title: data[0].title,
				author: data[0].user_name,
				duration: data[0].duration,
				description: data[0].description,
				thumb: data[0].thumbnail_small
			}, config_name);
			jQuery("#fcfield_message_box_" + element_id_n).html("");
		}
		else
		{
			var errorText = typeof data === "object" ? data.error_message : data;
			jQuery("#fcfield_message_box_" + element_id_n).html("<span class=\"alert alert-warning fc-iblock\"><i>" + Joomla.JText._('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_SERVER_RESPONDED_WITH_ERROR') + "</i><br/><br/>"+errorText+"</span>");
		}
	}


	fcfield_sharemedia.dailymotionCallback = function(element_id_n, data, mediaID, config_name)
	{
		mediaID = typeof mediaID === "undefined" || !mediaID  ?  fcfield_sharemedia.fc_mediaID_tmp : mediaID;    // *** mediaID not set if called as callback
		element_id_n = typeof element_id_n === "undefined" || !element_id_n  ?  fcfield_sharemedia.fc_elemID_tmp : element_id_n;    // *** element_id_n not set if called as callback

		if (typeof data === "object" && typeof data.error === "undefined")
		{
			fcfield_sharemedia.toggleMETArows(element_id_n, 1);
			fcfield_sharemedia.setEmbedData(element_id_n, "dailymotion", mediaID, config_name);
			fcfield_sharemedia.setMetaData(element_id_n, {
				title: data.title,
				author: data["owner.screenname"],
				duration: data.duration,
				description: data.description,
				thumb: data.thumbnail_60_url
			}, config_name);
			jQuery("#fcfield_message_box_" + element_id_n).html("");
		}
		else
		{
			var errorText = typeof data === "object" ? data.error.message : data;
			jQuery("#fcfield_message_box_" + element_id_n).html("<span class=\"alert alert-warning fc-iblock\"><i>" + Joomla.JText._('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_SERVER_RESPONDED_WITH_ERROR') + "</i><br/><br/>" + errorText + "</span>");
		}
	}


	fcfield_sharemedia.noembedCallback = function(element_id_n, data, config_name)
	{
		element_id_n = typeof element_id_n === "undefined" || !element_id_n  ?  fcfield_sharemedia.fc_elemID_tmp : element_id_n;    // *** element_id_n not set if called as callback

		if (typeof data === "object" && typeof data.error === "undefined")
		{
			if (1)  // TODO Possibly add more checks
			{
				var urlregex = /(http:|ftp:|https:)?\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/;
				if (data.html.match(urlregex) != null)
				{
					fcfield_sharemedia.toggleMETArows(element_id_n, 1);
					var embed_url = data.html.match(urlregex)[0];
					fcfield_sharemedia.setEmbedData(element_id_n, "noembed:"+data.provider_name.toLowerCase(), embed_url, config_name);
					fcfield_sharemedia.setMetaData(element_id_n, {
						title: data.title,
						author: data.author_name,
						duration: data.duration,
						description: data.description,
						thumb: data.thumbnail_url
					}, config_name);
					jQuery("#fcfield_message_box_" + element_id_n).html("");
				}
				else
				{
					jQuery("#fcfield_message_box_" + element_id_n).html("IFRAME SRC parameter not found in response");
				}
			}
			else
			{
				jQuery("#fcfield_message_box_" + element_id_n).html("<div class=\"alert alert-warning\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">?</button>'. JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_URL_NOT_MEDIA').'</div>");
			}
		}
		else
		{
			var errorText = typeof data === "object" ? data.error_message : data;
			jQuery("#fcfield_message_box_" + element_id_n).html("<span class=\"alert alert-warning fc-iblock\"><i>" + Joomla.JText._('PLG_FLEXICONTENT_FIELDS_SHAREDMEDIA_SERVER_RESPONDED_WITH_ERROR') + "</i><br/><br/>" + errorText + "</span>");
		}
	}


	fcfield_sharemedia.setEmbedData = function(element_id_n, apiType, mediaID, config_name)
	{
		mediaID = typeof mediaID === "undefined" || !mediaID  ?  fcfield_sharemedia.fc_mediaID_tmp : mediaID;
		element_id_n = typeof element_id_n === "undefined" || !element_id_n  ?  fcfield_sharemedia.fc_elemID_tmp : element_id_n;

		if (apiType=="") mediaID = "";
		document.getElementById(element_id_n+"_api_type").value  = apiType;
		document.getElementById(element_id_n+"_media_id").value  = mediaID;
		document.getElementById(element_id_n+"_embed_url").value = "";

		if (apiType!="" && mediaID!="")
		{
			var preview_html = '<iframe class="sharedmedia seamless" src="';
			switch(apiType) {
				case "youtube"    :  preview_html += "//www.youtube.com/embed/";   break;
				case "vimeo"      :  preview_html += "//player.vimeo.com/video/";  break;
				case "dailymotion":  preview_html += "//www.dailymotion.com/embed/video/"; break;
				default:
					// Other embed API, e.g. noembed.com, full embed URL is inside mediaID
					document.getElementById(element_id_n+"_media_id").value   = "";
					document.getElementById(element_id_n+"_embed_url").value = mediaID;
					break;
			}
			preview_html += mediaID + '" style="width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden;" allowFullScreen></iframe>';
			document.getElementById(element_id_n+"_preview").innerHTML = preview_html;
			setTimeout(function() { fcfield_sharemedia.setHeight("iframe.sharedmedia", 2/3); }, 200);
		}
		else
		{
			document.getElementById(element_id_n+"_preview").innerHTML = "";
		}
	}


	fcfield_sharemedia.setMetaData = function(element_id_n, data, config_name)
	{
		element_id_n = typeof element_id_n === "undefined" || !element_id_n  ?  fcfield_sharemedia.fc_elemID_tmp : element_id_n;

		// If replaced with native JS then must check properties exist
		jQuery("#"+element_id_n+"_title").val(data.title);
		jQuery("#"+element_id_n+"_author").val(data.author);
		jQuery("#"+element_id_n+"_duration").val(data.duration);
		jQuery("#"+element_id_n+"_description").val(data.description);
		jQuery("#"+element_id_n+"_thumb").val(data.thumb);
	}


	fcfield_sharemedia.toggleMETArows = function(element_id_n, action)
	{
		action = typeof action === "undefined" ? 0 : action;

		var fields = ['title', 'author', 'duration', 'description', 'api_type', 'embed_url', 'media_id', 'preview', 'width', 'height'];
		var fields_selector = "#" + element_id_n + "_" + fields.join(", " + "#" + element_id_n + "_");

		switch (action)
		{
			case 1: jQuery(fields_selector).closest("tr").fadeIn(2000); break;
			case -1: jQuery(fields_selector).closest("tr").hide(); break;
			case 0: jQuery(fields_selector).closest("tr").toggle(0); break;
		}
	}


	jQuery(window).resize(function() {
		fcfield_sharemedia.setHeight("iframe.sharedmedia", 2/3);
	});


	jQuery(document).ready(function(){
		fcfield_sharemedia.setHeight("iframe.sharedmedia", 2/3);
		jQuery(document).on("mouseenter", ".fcfield_sharedmedia_valuebox", function(event) {
			fcfield_sharemedia.setHeight("iframe.sharedmedia", 2/3);
		});
	});