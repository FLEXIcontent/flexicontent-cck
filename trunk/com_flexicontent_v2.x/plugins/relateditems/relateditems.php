<?php
/**
 * @version 1.0 $Id: relateditems.php 687 2011-07-26 04:55:37Z enjoyman@gmail.com $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.relateditems
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsRelateditems extends JPlugin
{
	function plgFlexicontent_fieldsRelateditems( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_relateditems', JPATH_ADMINISTRATOR);
	}
	function onAdvSearchDisplayField(&$field, &$item) {
		plgFlexicontent_fieldsRelateditems::onDisplayField($field, $item);
	}
	function onDisplayField(&$field, &$item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems') return;

		// some parameter shortcuts
		$size				= $field->parameters->get( 'size', 12 ) ;
		$default_values		= '';
		
		$maxtitlechars 	= $field->parameters->get( 'maxtitlechars', 40 ) ;
		$samelangonly = $field->parameters->get( 'samelangonly', 1 ) ;
		$required 	= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';
		$size	 	= $size ? ' size="'.$size.'"' : '';
		
		// initialise property
		if($item->getValue('version', NULL, 0) < 2 && $default_values) {
			$field->value = explode(",", $default_values);
		} else if (!$field->value) {
			$field->value = array();
		} else {
			//$field->value = unserialize($field->value[0]);
		}
		
		$fieldval = array();
		foreach($field->value as $i=>$val) {
			list ($itemid,$catid) = explode(":", $val);
			$fieldval[$itemid] = new stdClass();
			$fieldval[$itemid]->itemid = $itemid;
			$fieldval[$itemid]->catid = $catid;
			$fieldval[$itemid]->val = $val;
		}

		$db =& JFactory::getDBO();
		
		$user =& JFactory::getUser();
		if (FLEXI_ACCESS) {
			$usercats 		= FAccess::checkUserCats($user->gmid);
			$viewallcats 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usercats', 'users', $user->gmid) : 1;
			$viewtree 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'cattree', 'users', $user->gmid) : 1;
		} else {
			$viewallcats	= 1;
			$viewtree		= 1;
		}
		
		if (!$viewtree) {
			$field->html = 'User not allowed to see category tree';
			return;
		}
    //ob_start();		print_r($field->value);		$field->html = ob_get_contents();    ob_end_clean();
    
    $where = "";
		if (!$viewallcats) {
			$where .= ($where=="") ? "" : " AND ";
			$where .= " catid IN (".implode(',',$usercats ).") ";
		}
		if ($samelangonly) {
			$where .= ($where=="") ? "" : " AND ";
			$where .= " ie.language='{$item->getValue('language')}' ";
		}
    if ($where!="") $where = " WHERE " . $where;
    
		$query = "SELECT c.title, c.id, c.catid, c.state, GROUP_CONCAT(cir.catid SEPARATOR ',') as catlist, c.alias FROM #__content AS c ".
			(($samelangonly) ? " LEFT JOIN #__flexicontent_items_ext AS ie on c.id=ie.item_id " : "") .
			" LEFT JOIN #__flexicontent_cats_item_relations AS cir on c.id=cir.itemid ".
			$where .
			" GROUP BY cir.itemid ".
			" ORDER BY title";
			;
		$db->setQuery($query);
		//echo $query; exit();
		$items_arr = $db->loadObjectList();
		if (!$items_arr) $items_arr = array();
		
		require_once(JPATH_ROOT.DS."components".DS."com_flexicontent".DS."classes".DS."flexicontent.categories.php");
		$tree = flexicontent_cats::getCategoriesTree();

		$field->html .= "<div style='float:left;margin-right:16px;'>Select Category:<br>\n";
		$field->html .= flexicontent_cats::buildcatselect($tree, $field->name.'_fccats', $catvals="", false, ' class="inputbox" '.$size, true);
		$field->html .= "</div>\n";
		
		$field->html  .= "&nbsp;&nbsp;&nbsp;";
		
		$field->html .= "<div style='float:left;margin-right:16px;'>Category Items:<br>\n";
		$field->html .= '<select id="'.$field->name.'_visitems" name="'.$field->name.'_visitems[]" multiple="multiple" style="min-width:140px;" class="" '.$size.' >'."\n";
		$field->html .= '</select>'."\n";
		$field->html .= "</div>\n";
		
		$field->html .= "<div style='float:left;margin-right:16px;'><br>\n";
		$field->html .= '<a href="JavaScript:void(0);" id="btn-add">Add &raquo;</a><br>'."\n";
    $field->html .= '<a href="JavaScript:void(0);" id="btn-remove">&laquo; Remove</a>'."\n";
		$field->html .= "</div>\n";
    
    // The split up the items
		$items_options = '';
		$items_options_select = '';
		$items_options_unused = '';
		$state_shortname = array(1=>'P', 0=>'U', -1=>'A', -3=>'PE', -4=>'OQ', -5=>'IP');
		foreach($items_arr as $itemdata) {
			$itemtitle = (mb_strlen($itemdata->title) > $maxtitlechars) ? mb_substr($itemdata->title,0,$maxtitlechars) . "..." : $itemdata->title;
			$statestr = "[". @$state_shortname[$itemdata->state]."] ";
			$itemtitle = $statestr.$itemtitle." ";//.$itemdata->catlist;
			$itemcat_arr = explode(",", $itemdata->catlist);
			$classes_str = "";
			$itemid = $itemdata->id;
			foreach ($itemcat_arr as $catid) $classes_str .= " "."cat_".$catid;
			if ( isset($fieldval[$itemid]) ) {
				$items_options .= '<option class="'.$classes_str.'" value="'.$fieldval[$itemid]->val.'" >'.$itemtitle.'</option>'."\n";
				$items_options_select .= '<option selected="selected" class="'.$classes_str.'" value="'.$fieldval[$itemid]->val.'" >'.$itemtitle.'</option>'."\n";
			} else {
				$items_options_unused .= '<option class="'.$classes_str.'" value="'.$itemid.'" >'.$itemtitle.'</option>'."\n";
			}
		}
		
		$field->html .= "<div style='float:left;margin-right:16px;'>Related Items<br>\n";
		
		$field->html .= '<select id="'.$field->name.'" name="custom['.$field->name.'][]" multiple="multiple" style="min-width:140px;display:none;" '.$size.' >';
		$field->html .= $items_options_select;
		$field->html .= '</select>'."\n";
		
		$field->html .= '<select id="'.$field->name.'_selitems" name="'.$field->name.'_selitems[]" multiple="multiple" style="min-width:140px;" class="'.$required.'" '.$size.' >';
		$field->html .= $items_options;
		$field->html .= '</select>'."\n";
		
		$field->html .= "</div>\n";
		
		$field->html .= '<select id="'.$field->name.'_hiditems" name="'.$field->name.'_hiditems" style="display:none;" >';
		$field->html .= $items_options_unused;
		$field->html .= '</select>'."\n";
		
		
		$js= "
		
window.addEvent( 'domready', function() {
 
    jQuery('#btn-add').click(function(){
        jQuery('#".$field->name."_visitems option:selected').each( function() {
            jQuery('#".$field->name."_selitems').append(\"<option class='\"+jQuery(this).attr('class')+\"' value='\"+jQuery(this).val()+\"'>\"+jQuery(this).text()+\"</option>\");
            jQuery('#".$field->name."').append(\"<option selected='selected' class='\"+jQuery(this).attr('class')+\"' value='\"+jQuery(this).val()+\"'>\"+jQuery(this).text()+\"</option>\");
            jQuery(this).remove();
        });
    });
    jQuery('#btn-remove').click(function(){
        jQuery('#".$field->name."_selitems option:selected').each( function() {
            jQuery('#".$field->name."_visitems').append(\"<option class='\"+jQuery(this).attr('class')+\"' value='\"+jQuery(this).val()+\"'>\"+jQuery(this).text()+\"</option>\");
            jQuery(\"#".$field->name." option[value='\"+jQuery(this).val()+\"']\").remove();
            jQuery(this).remove();
        });
    });

});

window.addEvent( 'domready', function() {
	$('".$field->name."_fccats').addEvent( 'change', function() {
		
	  jQuery('#".$field->name."_visitems option').each( function() {
	  	var data = jQuery(this).val().split(':'); 
	  	var itemid = data[0];
	  	jQuery('#".$field->name."_hiditems').append(\"<option class='\"+jQuery(this).attr('class')+\"' value='\"+itemid+\"'>\"+jQuery(this).text()+\"</option>\");
	  	jQuery(this).remove();
		});
		
	  jQuery('#".$field->name."_hiditems option').each( function() {
	  	if ( jQuery(this).hasClass('cat_' + jQuery('#".$field->name."_fccats').attr('value') ) ) {
			  jQuery('#".$field->name."_visitems').append(\"<option class='\"+jQuery(this).attr('class')+\"'value='\"+jQuery(this).val()+\":\"+jQuery('#".$field->name."_fccats').val()+\"'>\"+jQuery(this).text()+\"</option>\");
				jQuery(this).remove();
	  	}
		});
		
	});
});";
		
		$doc = & JFactory::getDocument();
		$doc->addScriptDeclaration( $js );
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems') return;
		if(!$post) return;
		
		//$post = serialize($post);
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems') return;
		
		global $globalcats;
		global $globalnoroute;
		if (!is_array($globalnoroute)) $globalnoroute = array();
		
		$values = $values ? $values : $field->value ;
		if (isset($values[0])) {
			//$values = unserialize($values[0]);
		}

		// some parameter shortcuts
		$remove_space		= $field->parameters->get( 'remove_space', 0 ) ;
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$separatorf			= $field->parameters->get( 'separatorf' ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
		$maxtitlechars 	= $field->parameters->get( 'maxtitlechars', 40 ) ;
						
		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separatorf = ',&nbsp;';
			break;

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			default:
			$separatorf = '&nbsp;';
			break;
		}
		
		if($pretext) 	{ $pretext 	= $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) 	{ $posttext	= $remove_space ? $posttext : ' ' . $posttext; }
			
		$db =& JFactory::getDBO();
		
		$fieldval = array();
		foreach($values as $i => $val) {
			//echo $val."<br>";
			list ($itemid,$catid) = explode(":", $val);
			$fieldval[$itemid] = new stdClass();
			$fieldval[$itemid]->itemid = $itemid;
			$fieldval[$itemid]->catid = $catid;
			$fieldval[$itemid]->val = $val;
		}
		
		// Get data like aliases and published state
		$query = "SELECT c.title, c.id, c.alias, c.state, c.catid as maincatid, ".
			" GROUP_CONCAT(cat.id SEPARATOR  ',') AS catidlist, ".
			" GROUP_CONCAT(cat.alias SEPARATOR  ',') AS  cataliaslist ".
			" FROM #__content AS c ".
			" LEFT JOIN #__flexicontent_cats_item_relations AS ci ON c.id=ci.itemid ".
			" LEFT JOIN #__categories AS cat ON ci.catid=cat.id ";
		$where = " WHERE c.id IN (";
		$sep = '';
		foreach($fieldval as $itemid => $itemdata) {
			$where .= $sep.$itemid;
			$sep = ',';
		}
		$where .= ")";
		$query .= $where;
		$query .= " GROUP BY c.id ";
		
		$db->setQuery($query);
		if (count($values))
			$results = $db->loadObjectList()  or die($db->getErrorMsg());
		else
			$results = array();
		
		if (!$results) {
			$field->{$prop} = '';
		} else {
			$display = array();
			foreach($results as $result) {
				// Check if related item is published and skip if not published
				if ($result->state != 1 && $result->state != -5) continue;
				
				$catslug = "";
				// Check if removed from category or inside a noRoute category or inside a non-published category
				// and use main category slug or other routable & published category slug
				$catid_arr = explode(",", $result->catidlist);
				$catalias_arr = explode(",", $result->cataliaslist);
				for($i=0; $i<count($catid_arr); $i++) {
					$itemcataliases[$catid_arr[$i]] = $catalias_arr[$i];
				}
				$rel_itemid = $result->id;
				$rel_catid = $fieldval[$rel_itemid]->catid;
				if ( isset($itemcataliases[$rel_catid]) && !in_array($rel_catid, $globalnoroute) && $globalcats[$catid]->published) {
					$catslug = $rel_catid.":".$itemcataliases[$rel_catid];
				} else if (!in_array($result->maincatid, $globalnoroute) && $globalcats[$result->maincatid]->published ) {
					$catslug = $globalcats[$result->maincatid]->slug;
				} else {
					foreach ($catid_arr as $catid) {
						if ( !in_array($catid, $globalnoroute) && $globalcats[$catid]->published) {
							$catslug = $globalcats[$catid]->slug;
							break;
						}
					}
				}
				
				$itemslug = $result->id.":".$result->alias;
				$itemtitle = (mb_strlen($result->title) > $maxtitlechars) ? mb_substr($result->title,0,$maxtitlechars) . "..." : $result->title;
				$link= "<a href='". JRoute::_(FlexicontentHelperRoute::getItemRoute($itemslug, $catslug)) ."' class='hasTip relateditem' title='". JText::_( 'FLEXI_READ_MORE_ABOUT' ) . '::' . addslashes($result->title) ."'>".$itemtitle."</a>\n";
				$display[] = $pretext . $link . $posttext;
			}
			if ($values) {
				$field->{$prop} = implode($separatorf, $display);
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;
			} else {
				$field->{$prop} = '';
			}
		}
	}

}
