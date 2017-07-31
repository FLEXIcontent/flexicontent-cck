<?php
if ($params->get('display_favlist', 0)) :

	$document->addScript(JURI::base(true).'/modules/mod_flexicontent/tmpl_common/js/favlist.js');

	?>	
	<p class="news_favs_head"><?php echo JText::_('FLEXI_MOD_RECENTLY_ADDED_FAVOURITES'); ?></p>
	<div id="mod_fc_favlist">
	</div>
	<?php

	$layouts_path = null; //realpath(JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.DS.'core');
	$item = (object) array('id' => '__ITEM_ID__', 'title' => '__ITEM_TITLE__');
	
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
				<a href="' . JURI::base(true) . 'index.php?option=com_flexicontent&view=item&id=__ITEM_ID__" class="readon">
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
    var fl_currentURL   = window.location;
    var fl_base_folder  = "'.JURI::base(true).'";
    var fl_live_site    = fl_currentURL.protocol+"//" + fl_currentURL.host + fl_base_folder;
    var fl_icon_title="' . $fav_tip_hover . '";
    var fl_show_item="' . $fav_show_item . '";
    var fl_item_link="index.php?option=com_flexicontent&view=item' . ($forced_itemid ? '&itemid='.$forced_itemid : '') . '&id=";

    window.addEvent("domready", function()
    {
      if (typeof jQuery != "undefined")
      {
        if (jQuery.ui) jQuery("div#mod_fc_favlist").sortable();

        var fcfavs_list = fcfavs_list_init();
				var item_ids = fcfavs_list.ids.items();
				var item_titles = fcfavs_list.titles.items();

        for(var i=0; i < item_ids.length; i++)
        {
          var icon_onclick  = "javascript:FCFav("+item_ids[i]+", \"item\", 0);";
          var item_link     = fl_item_link + item_ids[i];
          var box_html = jQuery("div#mod_fc_favlist").next().html();
					jQuery("div#mod_fc_favlist").prepend(
						"<div class=\'fcfav_item_"+item_ids[i]+"\'>"
						+ box_html.replace(/__ITEM_ID__/g, item_ids[i]).replace(/__ITEM_TITLE__/g, item_titles[i])
						+"</div>"
					);

          jQuery(".fcfav_item_"+item_ids[i]).find("span.fcfav-delete-btn").bind("click", favicon_clicked);
        }

        jQuery("span.fcfav-toggle-btn").bind("click", favicon_clicked);
      }
    });
  ';
	$document->addScriptDeclaration($js);
	?>

<?php endif;