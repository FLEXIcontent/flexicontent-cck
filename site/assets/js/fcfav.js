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
	//var live_site = url.substring(0, url.indexOf('/', 14)) + fcfav_rfolder + '/';
	type = (typeof type === "undefined" || type === null ) ? 'item' : type;
	add_counter = (typeof add_counter === "undefined" || add_counter === null ) ? false : add_counter;

	// We will no do a client side updating of favourites cookie, the updated
	// cookie will be received via the HTTP response of the AJAX server call
	//toggleFCFavCookie(id, type);

	var currentURL = window.location;
	var live_site = currentURL.protocol + '//' + currentURL.host + fcfav_rfolder;
	
	var favurl = live_site+"/index.php?option=com_flexicontent&format=raw&task=ajaxfav&id="+id+'&type='+type

	var onote_msg_box_start = '<div class="fc-mssg fc-note fc-iblock fc-nobgimage" style="position: relative; margin: 1px 2px;">';
	var osucc_msg_box_start = '<div class="fc-mssg fc-success fc-iblock fc-nobgimage" style="z-index:1000; position: relative; margin: 1px 2px;">';
	var _box_start = '<div class="fc-mssg fc-info fc-iblock fc-nobgimage';
	
	
	var div = document.getElementById('fcfav-reponse_'+type+'_'+id);
	if (div)
	{
		div.innerHTML = _box_start + ' fcfavs-loading">' + '<img src="'+live_site+'/components/com_flexicontent/assets/images/ajax-loader.gif" border="0" align="absmiddle" /> ' + Joomla.JText._('FLEXI_LOADING') + '</div>';
	}

	jQuery.ajax({
		url: favurl,
		dataType: "text",
		data: {
			lang: (typeof _FC_GET !="undefined" && 'lang' in _FC_GET ? _FC_GET['lang']: '')
		},
		success: function( response ) {
			var link = document.getElementById('favlink_'+type+'_'+id);
			if (div) {
				if(response=='login')  div.innerHTML = onote_msg_box_start + Joomla.JText._('FLEXI_YOU_NEED_TO_LOGIN') + '</div>';
				else if(response>0 || response=='added')   div.innerHTML = osucc_msg_box_start + Joomla.JText._('FLEXI_ADDED_TO_YOUR_FAVOURITES') + '</div>';
				else if(response<0 || response=='removed') div.innerHTML = osucc_msg_box_start + Joomla.JText._('FLEXI_REMOVED_FROM_YOUR_FAVOURITES') + '</div>';
				else if ( isNaN(parseFloat(response)) )    div.innerHTML = onote_msg_box_start + response + '</div>'; // some custom text
			}
			setTimeout(function(){
				if(response>0){
					if (div) {
						var newtotal = Math.abs(response);
						link.innerHTML='<img alt="'+Joomla.JText._('FLEXI_REMOVE_FAVOURITE')+'" src="'+live_site+'/components/com_flexicontent/assets/images/heart_delete.png" border="0" />';
						var newfavs=newtotal+' '+Joomla.JText._('FLEXI_USERS');
						div.innerHTML = _box_start + ' fcfavs-is-subscriber">' + Joomla.JText._('FLEXI_FAVS_YOU_HAVE_SUBSCRIBED') + '</div>'
							+(add_counter ? ' '+ _box_start + ' fcfavs-subscribers-count">' + Joomla.JText._('FLEXI_TOTAL') + ': ' + newfavs + '</div>' : '');
					}
				}
				else if(response<0){
					if (div) {
						var newtotal = Math.abs(response);
						link.innerHTML='<img alt="'+Joomla.JText._('FLEXI_FAVOURE')+'" src="'+live_site+'/components/com_flexicontent/assets/images/heart_add.png" border="0" />';
						var newfavs=newtotal+' '+Joomla.JText._('FLEXI_USERS');
						div.innerHTML = _box_start + ' fcfavs-isnot-subscriber">' + Joomla.JText._('FLEXI_FAVS_CLICK_TO_SUBSCRIBE') + '</div>'
							+(add_counter ? ' '+ _box_start + ' fcfavs-subscribers-count">' + Joomla.JText._('FLEXI_TOTAL') + ': ' + newfavs + '</div>' : '');
					}
				}
				else if(response=='added'){
					if (div) {
						link.innerHTML='<img alt="'+Joomla.JText._('FLEXI_REMOVE_FAVOURITE')+'" src="'+live_site+'/components/com_flexicontent/assets/images/heart_delete.png" border="0" />';
						div.innerHTML = _box_start + ' fcfavs-is-subscriber">' + Joomla.JText._('FLEXI_FAVS_YOU_HAVE_SUBSCRIBED') + '</div>';
					}
				}
				else if(response=='removed'){
					if (div) {
						link.innerHTML='<img alt="'+Joomla.JText._('FLEXI_FAVOURE')+'" src="'+live_site+'/components/com_flexicontent/assets/images/heart_add.png" border="0" />';
						div.innerHTML = _box_start + ' fcfavs-isnot-subscriber">' + Joomla.JText._('FLEXI_FAVS_CLICK_TO_SUBSCRIBE') + '</div>';
					}
				}
			},2000);
		},
		error: function (xhr, ajaxOptions, thrownError) {
			alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
		}
	});
}