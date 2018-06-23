if (!Array.prototype.indexOf)
{
	Array.prototype.indexOf = function(obj, start)
	{
		for (var i = (start || 0), j = this.length; i < j; i++)
		{
			if (this[i] === obj) { return i; }
		}
		return -1;
	}
}



function toggleFCFavCookie(id, type)
{
	var fcfavs = fc_getCookie('fcfavs');

	try {
		fcfavs = JSON.parse(fcfavs);
	} catch(e) {
		fcfavs = {};
	}

	if (!fcfavs.hasOwnProperty(type))
	{
		fcfavs[type] = [];
	}

	var index = fcfavs[type].indexOf(id);
	index === -1
		? fcfavs[type].push(id)
		: fcfavs[type].splice(index, 1);

	fcfavs = JSON.stringify(fcfavs);
	fc_setCookie('fcfavs', fcfavs, 365);
}



function FCFav(id, type, add_counter)
{
	//var url = location.href;  // entire url including querystring - also: window.location.href;
	//var live_site = url.substring(0, url.indexOf('/', 14)) + fc_root_uri + '/';
	type = (typeof type === "undefined" || type === null ) ? 'item' : type;
	add_counter = (typeof add_counter === "undefined" || add_counter === null ) ? false : add_counter;

	// We will no do a client side updating of favourites cookie, the updated
	// cookie will be received via the HTTP response of the AJAX server call
	//toggleFCFavCookie(id, type);

	var currentURL = window.location;
	var live_site = currentURL.protocol + '//' + currentURL.host + fc_root_uri;
	
	var favurl = live_site + '/index.php?option=com_flexicontent&format=raw&task=ajaxfav&id=' + id + '&type=' + type;

	var onote_msg_box_start = '<div class="fc-mssg fc-note fc-iblock fc-nobgimage fcfavs-result-mssg" style="position: relative; margin: 1px 2px;">';
	var osucc_msg_box_start = '<div class="fc-mssg fc-success fc-iblock fc-nobgimage fcfavs-result-mssg" style="z-index:1000; position: relative; margin: 1px 2px;">';
	var _box_start = '<div class="fc-mssg fc-info fc-iblock fc-nobgimage';
	
	
	var divs = jQuery('.fcfav-reponse_'+type+'_'+id);
	if (divs.length)
	{
		divs.html(_box_start + ' fcfavs-loading">' + '<img src="'+live_site+'/components/com_flexicontent/assets/images/ajax-loader.gif" border="0" align="absmiddle" /> ' + Joomla.JText._('FLEXI_LOADING') + '</div>');
	}

	jQuery.ajax({
		url: favurl,
		dataType: "text",
		data: {
			lang: (typeof fc_sef_lang != 'undefined' ? fc_sef_lang : '')
		},
		success: function( response ) {
			var links = jQuery('.favlink_' + type + '_' + id + '.fcfav-toggle-btn');
			if (!divs) return;

			response = response.trim();

			var div, link;

			if (response=='login')  div = onote_msg_box_start + Joomla.JText._('FLEXI_YOU_NEED_TO_LOGIN') + '</div>';
			else if (response > 0 || response == 'added')   div = osucc_msg_box_start + Joomla.JText._('FLEXI_ADDED_TO_YOUR_FAVOURITES') + '</div>';
			else if (response < 0 || response == 'removed') div = osucc_msg_box_start + Joomla.JText._('FLEXI_REMOVED_FROM_YOUR_FAVOURITES') + '</div>';
			else if (isNaN(parseFloat(response)))           div = onote_msg_box_start + response + '</div>'; // some custom text

			jQuery.each( divs, function( i, box)
			{
				jQuery(box).html(div);
			});

			if (response == 'added')
			{
				//link='<img alt="'+Joomla.JText._('FLEXI_REMOVE_FAVOURITE')+'" src="'+live_site+'/components/com_flexicontent/assets/images/heart_delete.png" border="0" />';
				div = _box_start + ' fcfavs-is-subscriber">' + Joomla.JText._('FLEXI_FAVS_YOU_HAVE_SUBSCRIBED') + '</div>';
				link='<span class="icon-heart fcfav_icon_on"></span>';
			}
			else if (response == 'removed')
			{
				//link='<img alt="'+Joomla.JText._('FLEXI_FAVOURE')+'" src="'+live_site+'/components/com_flexicontent/assets/images/heart_add.png" border="0" />';
				link='<span class="icon-heart fcfav_icon_off"></span>';
				div = _box_start + ' fcfavs-isnot-subscriber">' + Joomla.JText._('FLEXI_FAVS_CLICK_TO_SUBSCRIBE') + '</div>';
			}
			else if (response > 0)
			{
				var newtotal = Math.abs(response);
				//link='<img alt="'+Joomla.JText._('FLEXI_REMOVE_FAVOURITE')+'" src="'+live_site+'/components/com_flexicontent/assets/images/heart_delete.png" border="0" />';
				link='<span class="icon-heart fcfav_icon_on"></span>';
				var newfavs=newtotal+' '+Joomla.JText._('FLEXI_USERS');
				div = _box_start + ' fcfavs-is-subscriber">' + Joomla.JText._('FLEXI_FAVS_YOU_HAVE_SUBSCRIBED') + '</div>'
					+(add_counter ? ' '+ _box_start + ' fcfavs-subscribers-count">' + Joomla.JText._('FLEXI_TOTAL') + ': ' + newfavs + '</div>' : '');
			}
			else if (response < 0)
			{
				var newtotal = Math.abs(response);
				//link='<img alt="'+Joomla.JText._('FLEXI_FAVOURE')+'" src="'+live_site+'/components/com_flexicontent/assets/images/heart_add.png" border="0" />';
				link='<span class="icon-heart fcfav_icon_off"></span>';
				var newfavs=newtotal+' '+Joomla.JText._('FLEXI_USERS');
				div = _box_start + ' fcfavs-isnot-subscriber">' + Joomla.JText._('FLEXI_FAVS_CLICK_TO_SUBSCRIBE') + '</div>'
					+(add_counter ? ' '+ _box_start + ' fcfavs-subscribers-count">' + Joomla.JText._('FLEXI_TOTAL') + ': ' + newfavs + '</div>' : '');
			}

			jQuery.each( links, function( i, box )
			{
				jQuery(box).html(link);
			});

			// Update text with some delay
			setTimeout(function()
			{
				jQuery.each( divs, function( i, box )
				{
					jQuery(box).html(div);
				});
			}, 2000);
		},
		error: function (xhr, ajaxOptions, thrownError) {
			alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
		}
	});
}