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
	type = (typeof type === "undefined" || type === null ) ? 'item' : type;
	add_counter = (typeof add_counter === "undefined" || add_counter === null ) ? false : add_counter;

	// We will no do a client side updating of favourites cookie, the updated
	// cookie will be received via the HTTP response of the AJAX server call
	//toggleFCFavCookie(id, type);

	// Joomla Root and Base URL
	window.root_url = !!jroot_url_fc ? jroot_url_fc : '';
	window.base_url = !!jbase_url_fc ? jbase_url_fc : '';

	var favurl = root_url + 'index.php?option=com_flexicontent&format=raw&task=ajaxfav&id=' + id + '&type=' + type;

	var onote_msg_box_start = '<div class="fc-mssg fc-note fc-iblock fc-nobgimage fcfavs-result-mssg" style="position: relative;">';
	var osucc_msg_box_start = '<div class="fc-mssg fc-success fc-iblock fc-nobgimage fcfavs-result-mssg" style="z-index:1000; position: relative;">';
	var _box_start = '<div class="fc-mssg fc-info fc-iblock fc-nobgimage';
	
	var divs = jQuery('.fcfavs-responce_'+type+'_'+id);
	if (divs.length)
	{
		//divs.html(_box_start + ' fcfavs-loading">' + '<img src="'+base_url+'components/com_flexicontent/assets/images/ajax-loader.gif" border="0" align="absmiddle" /> ' + Joomla.JText._('FLEXI_LOADING') + '</div>');
		divs.html('<span class="fcfavs-loading">' + '<img src="'+base_url+'components/com_flexicontent/assets/images/ajax-loader.gif" border="0" align="absmiddle" /> </span>');
	}

	jQuery.ajax({
		cache: false,
		url: favurl,
		dataType: "text",
		data: {
			lang: (typeof fc_sef_lang != 'undefined' ? fc_sef_lang : '')
		},
		success: function( response ) {
			var links = jQuery('.favlink_' + type + '_' + id + '.fcfavs-toggle-btn');
			if (!divs) return;

			response = response.trim();

			var div_toggle_info, div_status_info, div_user_counter, link;

			if (fcfav_toggle_info)
			{
				if (response=='login')  div_toggle_info = onote_msg_box_start + Joomla.JText._('FLEXI_YOU_NEED_TO_LOGIN') + '</div>';
				else if (response > 0 || response == 'added')   div_toggle_info = osucc_msg_box_start + Joomla.JText._('FLEXI_ADDED_TO_YOUR_FAVOURITES') + '</div>';
				else if (response < 0 || response == 'removed') div_toggle_info = osucc_msg_box_start + Joomla.JText._('FLEXI_REMOVED_FROM_YOUR_FAVOURITES') + '</div>';
				else if (isNaN(parseFloat(response)))           div_toggle_info = onote_msg_box_start + response + '</div>'; // some custom text

				jQuery.each( divs, function( i, box)
				{
					jQuery(box).html(div_toggle_info);
				});
			}
			
			div_status_info = '';
			div_user_counter = '';

			if (response == 'added')
			{
				if (!!!fcfav_toggle_style)  // Bootstrap Toggle
				{
					link = '<input data-on="&lt;i class=\'icon-heart fcfavs-icon_on\'&gt;&lt;/i&gt;" data-off="&lt;i class=\'icon-heart fcfavs-icon_off\'&gt;&lt;/i&gt;" data-toggle="toggle" type="checkbox" value="1" checked="checked" />';
				}
				else if (fcfav_toggle_style == 1)  // Icon Image
				{
					link = '<img alt="'+Joomla.JText._('FLEXI_REMOVE_FAVOURITE')+'" src="'+base_url+'components/com_flexicontent/assets/images/heart_full.png" border="0" class="fcfavs-img_icon" />';
				}
				else  // Icon CSS
				{
					link = '<span class="fcfavs-btn"><span class="fcfavs-btn-inner fcfavs-heart-fill"></span>';
				}

				div_status_info = _box_start + ' fcfavs-is-subscriber">' + Joomla.JText._('FLEXI_FAVS_YOU_HAVE_SUBSCRIBED') + '</div>';
			}
			else if (response == 'removed')
			{
				if (!!!fcfav_toggle_style)  // Bootstrap Toggle
				{
					link = '<input data-on="&lt;i class=\'icon-heart fcfavs-icon_on\'&gt;&lt;/i&gt;" data-off="&lt;i class=\'icon-heart fcfavs-icon_off\'&gt;&lt;/i&gt;" data-toggle="toggle" type="checkbox" value="1" />';
				}
				else if (fcfav_toggle_style == 1)  // Icon Image
				{
					link='<img alt="'+Joomla.JText._('FLEXI_FAVOURE')+'" src="'+base_url+'components/com_flexicontent/assets/images/heart_empty.png" border="0" class="fcfavs-img_icon" />';
				}
				else  // Icon CSS
				{
					link = '<span class="fcfavs-btn"><span class="fcfavs-btn-inner fcfavs-heart-border"></span>';
				}

				div_status_info = _box_start + ' fcfavs-isnot-subscriber">' + Joomla.JText._('FLEXI_FAVS_CLICK_TO_SUBSCRIBE') + '</div>';
			}
			else if (response > 0)
			{
				var newtotal = Math.abs(response);
				var newfavs  = newtotal; //+' '+Joomla.JText._('FLEXI_USERS');

				if (!!!fcfav_toggle_style)  // Bootstrap Toggle
				{
					link = '<input data-on="&lt;i class=\'icon-heart fcfavs-icon_on\'&gt;&lt;/i&gt;" data-off="&lt;i class=\'icon-heart fcfavs-icon_off\'&gt;&lt;/i&gt;" data-toggle="toggle" type="checkbox" value="1" checked="checked" />';
				}
				else if (fcfav_toggle_style == 1)  // Icon Image
				{
					link = '<img alt="'+Joomla.JText._('FLEXI_REMOVE_FAVOURITE')+'" src="'+base_url+'components/com_flexicontent/assets/images/heart_full.png" border="0" class="fcfavs-img_icon" />';
				}
				else  // Icon CSS
				{
					link = '<span class="fcfavs-btn"><span class="fcfavs-btn-inner fcfavs-heart-fill"></span>';
				}

				div_status_info = _box_start + ' fcfavs-is-subscriber">' + Joomla.JText._('FLEXI_FAVS_YOU_HAVE_SUBSCRIBED') + '</div>';
				//div_user_counter = (add_counter ? ' '+ _box_start + ' fcfavs-subscribers-count">' + Joomla.JText._('FLEXI_TOTAL') + ': ' + newfavs + '</div>' : '');
				div_user_counter = (add_counter ? newfavs : '');
			}
			else if (response < 0)
			{
				var newtotal = Math.abs(response);
				var newfavs  = newtotal; //+' '+Joomla.JText._('FLEXI_USERS');

				if (!!!fcfav_toggle_style)  // Bootstrap Toggle
				{
					link = '<input data-on="&lt;i class=\'icon-heart fcfavs-icon_on\'&gt;&lt;/i&gt;" data-off="&lt;i class=\'icon-heart fcfavs-icon_off\'&gt;&lt;/i&gt;" data-toggle="toggle" type="checkbox" value="1" />';
				}
				else if (fcfav_toggle_style == 1)  // Icon Image
				{
					link = '<img alt="'+Joomla.JText._('FLEXI_FAVOURE')+'" src="'+base_url+'components/com_flexicontent/assets/images/heart_empty.png" border="0" class="fcfavs-img_icon" />';
				}
				else  // Icon CSS
				{
					link = '<span class="fcfavs-btn"><span class="fcfavs-btn-inner fcfavs-heart-border"></span>';
				}

				div_status_info = _box_start + ' fcfavs-isnot-subscriber">' + Joomla.JText._('FLEXI_FAVS_CLICK_TO_SUBSCRIBE') + '</div>';
				div_user_counter = (add_counter ? newfavs : '');
			}

			jQuery.each( links, function( i, box )
			{
				jQuery(box).html(link);
				jQuery(box).find('input[type=checkbox][data-toggle^=toggle], input.fc_checkboxtoggle').bootstrapToggle();
			});

			// Update text with some delay
			setTimeout(function()
			{
				jQuery.each( divs, function( i, box )
				{
					jQuery(box).fadeOut( fcfav_status_info ? 1800 : 1, function() {
						jQuery(box).html((fcfav_status_info ? div_status_info : '')).css('display', '');
						if (add_counter && newfavs)
							jQuery(box).parent().find('.fcfavs-subscribers-count').css('display', '').find('.fcfavs-counter-num').html(div_user_counter);
						else
							jQuery(box).parent().find('.fcfavs-subscribers-count').css('display', 'none');
					});
				});
			}, fcfav_status_info ? 200 : 1);
		},
		error: function (xhr, ajaxOptions, thrownError) {
			alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
		}
	});
}