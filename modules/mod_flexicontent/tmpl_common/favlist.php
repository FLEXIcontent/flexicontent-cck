<?php if ($params->get('display_favlist', 0)) : ?>
	<?php
	$document->addScript(JURI::base(true).'/modules/mod_flexicontent/tmpl_common/js/favlist.js');
	?>
	
	<p class="news_favs_head"><?php echo JText::_('FLEXI_MOD_RECENTLY_ADDED_FAVOURITES'); ?></p>
	<ul id="mod_fc_favlist">
	</ul>
	
	<?php
	$fav_tip_title	= JText::_( 'FLEXI_ADDREMOVE_FAVOURITE' );
	$fav_tip_hover	= JText::_( 'FLEXI_ADDREMOVE_FAVOURITE_TIP' );
	$fav_show_item	= JText::_( 'READ MORE...' );
	$forced_itemid = $params->get('forced_itemid');
	$js = '
    var fl_currentURL   = window.location;
    var fl_base_folder  = "'.JURI::base(true).'";
    var fl_live_site    = fl_currentURL.protocol+"//" + fl_currentURL.host + fl_base_folder;
    var fl_del_icon=\'<img align="top" src="\'+fl_live_site+\'/components/com_flexicontent/assets/images/heart_delete.png" border="0" />\';
    var fl_add_icon=\'<img align="top" src="\'+fl_live_site+\'/components/com_flexicontent/assets/images/heart_add.png" border="0" />\';
    var fl_icon_title="'.$fav_tip_hover.'";
    var fl_show_item="'.$fav_show_item.'";
    var fl_item_link="index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW.($forced_itemid?'&itemid='.$forced_itemid:'').'&id=";

    window.addEvent("domready", function(){
      if (typeof jQuery == "undefined" ) {
        alert("jQuery library not loaded, favorites list in universal module cannot work");
        return;
      } else {
        if (typeof jQuery.ui == "undefined")  {
          //alert("jQuery ui not loaded, favorites list in universal module cannot work");
        }
      }
      
      if (typeof jQuery != "undefined") {
        if (jQuery.ui) jQuery("ul#mod_fc_favlist").sortable();
        
        for(var i=0; i<fl_item_ids.length; i++) {
          var icon_onclick  = "javascript:FCFav("+fl_item_ids[i]+");";
          var item_link     = fl_item_link + fl_item_ids[i];
          jQuery("ul#mod_fc_favlist").prepend(
                 "<li class=\'item_"+fl_item_ids[i]+" fcfav_delete\'>"
                +" <a id=\'favlist_del_fav_"+fl_item_ids[i]+"\' href=\'javascript:void(null)\' onclick=\'"+icon_onclick+"\' title=\'"+fl_icon_title+"\'>"+fl_del_icon+"</a> "
                +" <a id=\'favlist_show_item_"+fl_item_ids[i]+"\' href=\'"+item_link+"\' title=\'"+fl_show_item+"\'>"+fl_item_titles[i]+"</a> "
                +" <span class=\'fav_item_id\' style=\'display:none;\'>"+fl_item_ids[i]+"</span>"
                +" <span class=\'fav_item_title\' style=\'display:none;\'>"+fl_item_titles[i]+"</span>"
                +"</li>");
          jQuery("a#favlist_del_fav_"+fl_item_ids[i]).bind("click", favicon_clicked);
        }

        jQuery("a.fcfav-reponse").bind("click", favicon_clicked);
      }
    });
  ';
	$document->addScriptDeclaration($js);
	?>

<?php endif; ?>
