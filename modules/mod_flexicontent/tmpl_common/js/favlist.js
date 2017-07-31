
var fcfavs_list;

var favicon_clicked = function()
{
	fcfavs_list = fcfavs_list_init();

	var favbox = jQuery(this).parent();

	var isADD = favbox.find('.fcfav_icon_off').length > 0;
	var isDEL = favbox.find('.fcfav_icon_on, .fcfav_icon_delete').length > 0;

	var item_id = favbox.find('.fav_item_id').html();
	var item_title = favbox.find('.fav_item_title').html();

	var icon_onclick = 'javascript:FCFav('+item_id+', \"item\", 0);';
	var item_link =	fl_item_link + item_id;

	if (isADD)
	{
		//window.console.log("adding favourite");
		var box_html = jQuery("div#mod_fc_favlist").next().html();
		jQuery("div#mod_fc_favlist").prepend(
			"<div class='fcfav_item_"+item_id+"'>"
				+ box_html.replace(/__ITEM_ID__/g, item_id).replace(/__ITEM_TITLE__/g, item_title)
			+"</div>"
		);
		fcfavs_list.ids.add(item_id);
		fcfavs_list.titles.add(item_title);

		// Bind click to new item of the list
		var newBox = jQuery(".fcfav_item_"+item_id);
		newBox.find('span.fcfav-delete-btn').bind("click", favicon_clicked);
		newBox.find('.hasTooltip').tooltip({html: true, container: newBox});
		newBox.find('.hasPopover').popover({html: true, container: newBox, trigger : 'hover focus'});
	}

	if (isDEL)
	{
		// If a favorite field exists for this item remove it
		//window.console.log("removing favourite");
		jQuery("div#mod_fc_favlist").find("div.fcfav_item_"+item_id).remove();
		fcfavs_list.ids.remove(item_id);
		fcfavs_list.titles.remove(item_title);
	}
}

var fcfavs_list_init = function()
{
	return {
		"ids" : new fclib_createCookieList("fcfavs_fl_item_ids"),
		"titles" : new fclib_createCookieList("fcfavs_fl_item_titles")
	}
}