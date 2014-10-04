
var set_cookie = function ( name, value, exp_y, exp_m, exp_d, path, domain, secure )
{
	var cookie_string = name + "=" + escape ( value );

	if ( exp_y ) {
		var expires = new Date ( exp_y, exp_m, exp_d );
		cookie_string += "; expires=" + expires.toGMTString();
	}
	if ( path )			cookie_string += "; path=" + escape ( path );
	if ( domain )		cookie_string += "; domain=" + escape ( domain );
	if ( secure )		cookie_string += "; secure";

	document.cookie = cookie_string;
}

var delete_cookie = function (cookie_name)
{
	var cookie_date = new Date ( );  // current date & time
	cookie_date.setTime ( cookie_date.getTime() - 1 );
	document.cookie = cookie_name += "=; expires=" + cookie_date.toGMTString();
}

var get_cookie = function (cookie_name)
{
	var results = document.cookie.match ( '(^|;) ?' + cookie_name + '=([^;]*)(;|$)' );
	
	if ( results )	return ( unescape ( results[2] ) );
	else						return null;
}

// http://stackoverflow.com/questions/1959455/how-to-store-an-array-in-jquery-cookie
var cookieList = function(cookieName) {
//When the cookie is saved the items will be a comma seperated string
//So we will split the cookie by comma to get the original array
var cookie = get_cookie(cookieName);
//Load the items or a new array if null.
var items = cookie ? cookie.split(/%%%/) : new Array();

//Return a object that we can use to access the array.
//while hiding direct access to the declared items array
//this is called closures see http://www.jibbering.com/faq/faq_notes/closures.html
return {
		"add": function(val) {
				items.push(val);		//add to the items.
				set_cookie(cookieName, items.join("%%%"));		//Save the items to a cookie.
		},
		"remove": function (val) { 
				indx = items.indexOf(val); 
				if(indx!=-1) items.splice(indx, 1); 
				set_cookie(cookieName, items.join("%%%"));
		},
		"clear": function() {
				items = null;		//clear items.
				set_cookie(cookieName, null); //clear the cookie.
		},
		"items": function() {
				//Get all the items.
				return items;
		}
	}
}				

var favicon_clicked = function(){
	
	var favbox = jQuery(this).parent();
	var isADD = favbox.hasClass("fcfav_add");
	var isDELETE = favbox.hasClass("fcfav_delete");
	var item_id = favbox.find(".fav_item_id").html();
	var item_title = favbox.find(".fav_item_title").html();
	var item_link="javascript:void(null)";
	var icon_onclick = "javascript:FCFav("+item_id+");";
	var item_link =	fl_item_link + item_id;
	if (isADD) {
		//alert("adding favourite");
		jQuery("ul#mod_fc_favlist").prepend(
				 "<li class='item_"+item_id+" fcfav_delete'>"
				+" <a id='favlist_del_fav_"+item_id+"' href='javascript:void(null)' onclick='"+icon_onclick+"' title='"+fl_icon_title+"'>"+fl_del_icon+"</a> "
				+" <a id=\'favlist_show_item_"+item_id+"\' href=\'"+item_link+"\' title=\'"+fl_show_item+"\'>"+item_title+"</a> "
				+" <span class='fav_item_id' style='display:none;'>"+item_id+"</span>"
				+" <span class='fav_item_title' style='display:none;'>"+item_title+"</span>"
				+"</li>");
		favbox.removeClass("fcfav_add").addClass("fcfav_delete");
		fl_idlist.add(item_id);
		fl_titlelist.add(item_title);
		jQuery("a#favlist_del_fav_"+item_id).bind("click", favicon_clicked);
	}
	if (isDELETE) {
		//alert("removing favourite");
		jQuery("ul#mod_fc_favlist").find("li.item_"+item_id).remove();
		// If a favorite field exists for this item update it is class so if it is clicked to know what to do
		jQuery("a#favlink"+item_id).parent().removeClass("fcfav_delete").addClass("fcfav_add");
		fl_idlist.remove(item_id);
		fl_titlelist.remove(item_title);
	}
}

var fl_idlist = new cookieList("fcfavs_fl_item_ids"); 
var fl_titlelist = new cookieList("fcfavs_fl_item_titles"); 
var fl_item_ids = fl_idlist.items();
var fl_item_titles = fl_titlelist.items();
//alert(fl_item_ids);
//alert(fl_item_titles);
