<?php
if ($params->get('display_favlist', 0))
{
	$document->addScript(JUri::base(true).'/modules/mod_flexicontent/tmpl_common/js/favlist.js');

	echo '
	<p class="news_favs_head">' . JText::_('FLEXI_MOD_RECENTLY_ADDED_FAVOURITES') . '</p>
	<div id="mod_fc_favlist"></div>
	';

	$layouts_path = null; //realpath(JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.DS.'core');
	$item = (object) array('id' => '__ITEM_ID__', 'title' => '__ITEM_TITLE__', 'url' => '__ITEM_URL__');

	$displayData = array(
		'field' => null,
		'item' => $item,
		'favoured' => ($favoured = -1),
		'favourites' => '',
		'type' => 'item'
	);

	echo '
	<div class="mod_fc_favlist_favblock_template">
		<div class="fav-block">
			' . JLayoutHelper::render('flexicontent_fields.favourites.favicon', $displayData, $layouts_path) .'
			<div class="fcitem_readon">
				<a href="javascript:;" data-href="__ITEM_URL__" class="readon">
					__ITEM_TITLE__
				</a>
			</div>
		</div>
	</div>
	';

	$fav_tip_title	= JText::_( 'FLEXI_ADDREMOVE_FAVOURITE' );
	$fav_tip_hover	= JText::_( 'FLEXI_ADDREMOVE_FAVOURITE_TIP' );
	$fav_show_item	= JText::_( 'READ MORE...' );
	$forced_itemid = $params->get('forced_itemid');
	$alt_add = JText::_( 'FLEXI_FAVOURE' );
	$alt_delete = JText::_( 'FLEXI_REMOVE_FAVOURITE' );
	$js = '
		if (typeof jQuery != "undefined")
		{
			jQuery(document).ready(function()
			{
				var fcfavs_list = new fclib_createCookieList("fcfavs_recent");
				var fcfavs_box = jQuery("div#mod_fc_favlist");
				fcfavs_list_update(fcfavs_list, fcfavs_box);

				if (jQuery.ui)
				{
					fcfavs_box.sortable();
				}
				jQuery("span.fcfav-toggle-btn").bind("click", favicon_clicked);
			});
		}
  ';
	$document->addScriptDeclaration($js);
}