<?php
if ($params->get('display_favlist', 0))
{
	$wa->registerAndUseScript('mod_flexicontent.favlist', 'modules/mod_flexicontent/tmpl_common/js/favlist.js', [], [], ['core']);

	echo '
	<p class="news_favs_head">' . \Joomla\CMS\Language\Text::_('FLEXI_MOD_RECENTLY_ADDED_FAVOURITES') . '</p>
	<div id="mod_fc_favlist_' . (int) $module->id . '" class="mod_fc_favlist"></div>
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
			' . \Joomla\CMS\Layout\LayoutHelper::render('flexicontent_fields.favourites.favicon', $displayData, $layouts_path) .'
			<div class="fcitem_readon">
				<a href="javascript:;" data-href="__ITEM_URL__" class="readon">
					__ITEM_TITLE__
				</a>
			</div>
		</div>
	</div>
	';

	$fav_tip_title	= \Joomla\CMS\Language\Text::_( 'FLEXI_ADDREMOVE_FAVOURITE' );
	$fav_tip_hover	= \Joomla\CMS\Language\Text::_( 'FLEXI_ADDREMOVE_FAVOURITE_TIP' );
	$fav_show_item	= \Joomla\CMS\Language\Text::_( 'READ MORE...' );
	$forced_itemid = $params->get('forced_itemid');
	$alt_add = \Joomla\CMS\Language\Text::_( 'FLEXI_FAVOURE' );
	$alt_delete = \Joomla\CMS\Language\Text::_( 'FLEXI_REMOVE_FAVOURITE' );

	// Native cookie rendering and Sortable use no jQuery assets.
	flexicontent_html::loadFramework('flexi-lib');
	flexicontent_html::loadFramework('sortable');
}
