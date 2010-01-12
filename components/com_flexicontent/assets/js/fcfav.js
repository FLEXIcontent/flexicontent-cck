function FCFav(id){
	var currentURL = window.location;
	var live_site = currentURL.protocol+'//'+currentURL.host+sfolder;
	var lsXmlHttp;
	
	var div = document.getElementById('fcfav-reponse_'+id);
	div.innerHTML='<img src="'+live_site+'/components/com_flexicontent/assets/images/ajax-loader.gif" border="0" align="absmiddle" /> '+'<small>'+fcfav_text[1]+'</small>';
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
				response = lsXmlHttp.responseText; 
				if(response>0 || response=='added') div.innerHTML='<small>'+fcfav_text[2]+'</small>';
				if(response=='login') div.innerHTML='<small>'+fcfav_text[3]+'</small>';
				if(response<0 || response=='removed') div.innerHTML='<small>'+fcfav_text[4]+'</small>';
			},500);
			setTimeout(function(){
				if(response>0){
					var newtotal = Math.abs(response);
					link.innerHTML='<img src="'+live_site+'/components/com_flexicontent/assets/images/heart_delete.png" border="0" />';
					var newfavs=newtotal+' '+fcfav_text[5];
					div.innerHTML='<small>('+newfavs+')</small>';
				}
				else if(response<0){
					var newtotal = Math.abs(response);
					link.innerHTML='<img src="'+live_site+'/components/com_flexicontent/assets/images/heart_add.png" border="0" />';
					var newfavs=newtotal+' '+fcfav_text[5];
					div.innerHTML='<small>('+newfavs+')</small>';
				} 
				else if(response=='added'){
					link.innerHTML='<img src="'+live_site+'/components/com_flexicontent/assets/images/heart_delete.png" border="0" />';
					div.innerHTML='';
				}
				else if(response=='removed'){
					link.innerHTML='<img src="'+live_site+'/components/com_flexicontent/assets/images/heart_add.png" border="0" />';
					div.innerHTML='';
				}
				
			},2000);
		}
	}
	lsXmlHttp.open("GET",live_site+"/index.php?option=com_flexicontent&format=raw&task=ajaxfav&id="+id,true);
	lsXmlHttp.send(null);
}