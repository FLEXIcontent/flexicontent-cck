
var favicon_clicked = function()
{
	var fcfavs_list = new fclib_createCookieList("fcfavs_recent");
	var fcfavs_box = jQuery("div#mod_fc_favlist");

	var favicon_box = jQuery(this).parent();

	var isADD = favicon_box.find('.fcfav_icon_off').length > 0;
	var isDEL = favicon_box.find('.fcfav_icon_on, .fcfav_icon_delete').length > 0;

	var item_id    = favicon_box.find('.fav_item_id').html();
	var item_title = favicon_box.find('.fav_item_title').html();
	var item_url   = favicon_box.find('.fav_item_url').html();

	if (isADD)
	{
		var item = {};
		item.id = item_id;
		item.title = item_title;
		item.url = item_url;
		fcfavs_list.add(item_id, item);
	}

	if (isDEL)
	{
		// If a favorite field exists for this item remove it
		fcfavs_box.find("div.fcfav_item_"+item_id).remove();
		fcfavs_list.remove(item_id);
	}

	fcfavs_list_update(fcfavs_list, fcfavs_box);
}

var fcfavs_list_update = function(fcfavs_list, fcfavs_box)
{
	fcfavs_box.html('');

	var items = fcfavs_list.items();
	for (var i in items)
	{
		if (!items.hasOwnProperty(i)) continue;

		var box_html = fcfavs_box.next().html();
		fcfavs_box.prepend(
			"<div class='fcfav_item_"+items[i].id+"'>"
				+ box_html.replace(/__ITEM_ID__/g, items[i].id).replace(/__ITEM_TITLE__/g, items[i].title).replace(/__ITEM_URL__/g, items[i].url)
			+"</div>"
		);

		var newBox = jQuery(".fcfav_item_"+items[i].id);

		// Set correct link (we could not use a replacement inside href directly, because Joomla SEF prepends ... juri base path)
		var link = newBox.find('.fcitem_readon a');
		link.attr('href', link.attr('data-href'));

		// Bind click to new item of the list
		newBox.find('span.fcfav-delete-btn').bind("click", favicon_clicked);

		// Add tooltips
		//newBox.find('.hasTooltip').tooltip({html: true, container: newBox});
		//newBox.find('.hasPopover').popover({html: true, container: newBox, trigger : 'hover focus'});
	}
}
