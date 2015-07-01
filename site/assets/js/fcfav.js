function FCFav(id, type) {
	//var url = location.href;  // entire url including querystring - also: window.location.href;
	//var live_site = url.substring(0, url.indexOf('/', 14)) + fcfav_rfolder + '/';
	type = (typeof type === "undefined" || type === null ) ? 'item' : type;
	
	var currentURL = window.location;
	var live_site = currentURL.protocol + '//' + currentURL.host + fcfav_rfolder;
	
	var favurl = live_site+"/index.php?option=com_flexicontent&format=raw&task=ajaxfav&id="+id+'&type='+type

	var onote_msg_box_start = '<div class="fc-mssg-inline fc-note fc-iblock fc-nobgimage" style="position: relative; margin: 1px 2px;">';
	var osucc_msg_box_start = '<div class="fc-mssg-inline fc-success fc-iblock fc-nobgimage" style="z-index:1000; position: relative; margin: 1px 2px;">';
	var _box_start = '<div class="fc-mssg-inline fc-info fc-iblock fc-nobgimage';
	
	
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
						div.innerHTML = _box_start + ' fcfavs-is-subscriber">' + Joomla.JText._('FLEXI_FAVS_YOU_HAVE_SUBSCRIBED') + '</div>' +' '+ _box_start + ' fcfavs-subscribers-count">' + Joomla.JText._('FLEXI_TOTAL') + ': ' + newfavs + '</div>';
					}
				}
				else if(response<0){
					if (div) {
						var newtotal = Math.abs(response);
						link.innerHTML='<img alt="'+Joomla.JText._('FLEXI_FAVOURE')+'" src="'+live_site+'/components/com_flexicontent/assets/images/heart_add.png" border="0" />';
						var newfavs=newtotal+' '+Joomla.JText._('FLEXI_USERS');
						div.innerHTML = _box_start + ' fcfavs-isnot-subscriber">' + Joomla.JText._('FLEXI_FAVS_CLICK_TO_SUBSCRIBE') + '</div>' +' '+ _box_start + ' fcfavs-subscribers-count">' + Joomla.JText._('FLEXI_TOTAL') +': ' + newfavs + '</div>';
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