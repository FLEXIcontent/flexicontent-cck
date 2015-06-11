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
		div.innerHTML = _box_start + ' fcfavs-loading">' + '<img src="'+live_site+'/components/com_flexicontent/assets/images/ajax-loader.gif" border="0" align="absmiddle" /> ' + fcfav_text[1] + '</div>';
	}
	
	jQuery.ajax({
		url: favurl,
		dataType: "text",
		success: function( response ) {
			var link = document.getElementById('favlink_'+type+'_'+id);
			if (div) {
				if(response=='login')  div.innerHTML = onote_msg_box_start + fcfav_text[3] + '</div>';
				else if(response>0 || response=='added')   div.innerHTML = osucc_msg_box_start + fcfav_text[2] + '</div>';
				else if(response<0 || response=='removed') div.innerHTML = osucc_msg_box_start + fcfav_text[4] + '</div>';
				else if ( isNaN(parseFloat(response)) )    div.innerHTML = onote_msg_box_start + response + '</div>'; // some custom text
			}
			setTimeout(function(){
				if(response>0){
					if (div) {
						var newtotal = Math.abs(response);
						link.innerHTML='<img alt="'+fcfav_text[7]+'" src="'+live_site+'/components/com_flexicontent/assets/images/heart_delete.png" border="0" />';
						var newfavs=newtotal+' '+fcfav_text[5];
						div.innerHTML = _box_start + ' fcfavs-isnot-subscriber">' + fcfav_text[8] + '</div>' +' '+ _box_start + ' fcfavs-subscribers-count">' + fcfav_text[10] + ': ' + newfavs + '</div>';
					}
				}
				else if(response<0){
					if (div) {
						var newtotal = Math.abs(response);
						link.innerHTML='<img alt="'+fcfav_text[7]+'" src="'+live_site+'/components/com_flexicontent/assets/images/heart_add.png" border="0" />';
						var newfavs=newtotal+' '+fcfav_text[5];
						div.innerHTML = _box_start + ' fcfavs-is-subscriber">' + fcfav_text[9] + '</div>' +' '+ _box_start + ' fcfavs-subscribers-count">' + fcfav_text[10] +': ' + newfavs + '</div>';
					}
				} 
				else if(response=='added'){
					if (div) {
						link.innerHTML='<img src="'+live_site+'/components/com_flexicontent/assets/images/heart_delete.png" border="0" />';
						div.innerHTML = _box_start + ' fcfavs-is-subscriber">' + fcfav_text[8] + '</div>';
					}
				}
				else if(response=='removed'){
					if (div) {
						link.innerHTML='<img src="'+live_site+'/components/com_flexicontent/assets/images/heart_add.png" border="0" />';
						div.innerHTML = _box_start + ' fcfavs-isnot-subscriber">' + fcfav_text[9] + '</div>';
					}
				}
			},2000);
		},
		error: function (xhr, ajaxOptions, thrownError) {
			alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
		}
	});

}