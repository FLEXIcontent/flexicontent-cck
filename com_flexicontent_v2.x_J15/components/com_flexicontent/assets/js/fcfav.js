function FCFav(id) {
	//var url = location.href;  // entire url including querystring - also: window.location.href;
	//var live_site = url.substring(0, url.indexOf('/', 14)) + fcfav_rfolder + '/';
	
	var currentURL = window.location;
	var live_site = currentURL.protocol + '//' + currentURL.host + fcfav_rfolder;
	var lsXmlHttp;
	
	var div = document.getElementById('fcfav-reponse_'+id);
	if (div) {
		div.innerHTML='<img src="'+live_site+'/components/com_flexicontent/assets/images/ajax-loader.gif" border="0" align="absmiddle" /> '+'<small>'+fcfav_text[1]+'</small>';
	}
	
	try	{
		lsXmlHttp=new XMLHttpRequest();
	} catch (e) {
		try	{ lsXmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try { lsXmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e) {
				alert(fcfav_text[0]);
				return false;
			}
		}
	}
	lsXmlHttp.onreadystatechange=function() {
		var response;
		var link = document.getElementById('favlink'+id);
		if(lsXmlHttp.readyState==4){
			setTimeout(function(){ 
				if (div) {
					response = lsXmlHttp.responseText; 
					if(response>0 || response=='added') div.innerHTML='<small>'+fcfav_text[2]+'</small>';
					if(response=='login') div.innerHTML='<small>'+fcfav_text[3]+'</small>';
					if(response<0 || response=='removed') div.innerHTML='<small>'+fcfav_text[4]+'</small>';
				}
			},500);
			setTimeout(function(){
				if(response>0){
					if (div) {
						var newtotal = Math.abs(response);
						link.innerHTML='<img src="'+live_site+'/components/com_flexicontent/assets/images/heart_delete.png" border="0" />';
						var newfavs=newtotal+' '+fcfav_text[5];
						div.innerHTML='<small>['+newfavs+']</small>';
					}
				}
				else if(response<0){
					if (div) {
						var newtotal = Math.abs(response);
						link.innerHTML='<img src="'+live_site+'/components/com_flexicontent/assets/images/heart_add.png" border="0" />';
						var newfavs=newtotal+' '+fcfav_text[5];
						div.innerHTML='<small>['+newfavs+']</small>';
					}
				} 
				else if(response=='added'){
					if (div) {
						link.innerHTML='<img src="'+live_site+'/components/com_flexicontent/assets/images/heart_delete.png" border="0" />';
						div.innerHTML='';
					}
				}
				else if(response=='removed'){
					if (div) {
						link.innerHTML='<img src="'+live_site+'/components/com_flexicontent/assets/images/heart_add.png" border="0" />';
						div.innerHTML='';
					}
				}
				
			},2000);
		}
	}
	lsXmlHttp.open("GET",live_site+"/index.php?option=com_flexicontent&format=raw&task=ajaxfav&id="+id,true);
	lsXmlHttp.send(null);
}