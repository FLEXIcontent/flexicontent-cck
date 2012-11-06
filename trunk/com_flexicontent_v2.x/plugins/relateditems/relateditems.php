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
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsRelateditems( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_relateditems', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		global $globalcats;
		
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems') return;
		
		// SCOPE PARAMETERS
		
		// categories scope parameters
		$method_cat = $field->parameters->get('method_cat', 1);
		$usesubcats = $field->parameters->get('usesubcats', 0 );
		
		if ( empty($catids) )							$catids = array();
		else if ( ! is_array($catids) )		$catids = !FLEXI_J16GE ? array($catids) : explode("|", $catids);
				
		// types scope parameters
		$method_types = $field->parameters->get('method_types', 1);
		
		$types = $field->parameters->get('types');
		if ( empty($types) )							$types = array();
		else if ( ! is_array($types) )		$types = !FLEXI_J16GE ? array($types) : explode("|", $types);
		
		// other limits of scope parameters
		$samelangonly  = $field->parameters->get( 'samelangonly', 1 );
		$onlypublished = $field->parameters->get( 'onlypublished', 1 );
		
		// EDITING OPTIONS
		// Ordering
		$order = $field->parameters->get( 'orderby', 'alpha' );
		
		// Field height
		$size				= $field->parameters->get( 'size', 12 ) ;
		$size	 	= $size ? ' size="'.$size.'"' : '';
		$prepend_item_state = $field->parameters->get( 'prepend_item_state', 1 ) ;
		$maxtitlechars 	= $field->parameters->get( 'maxtitlechars', 40 ) ;
		$title_filter = $field->parameters->get( 'title_filter', 1 ) ;
		$required 	= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';
		
		// initialise property
		$default_values		= '';
		if( !$field->value && $item->version < 2 && $default_values) {
			$field->value = explode(",", $default_values);
		} else if (!$field->value) {
			$field->value = array();
		} else {
			// Compatibility with old values, we no longer serialize all values to one, this way the field can be reversed !!!
			$field->value = ( $field_data = @unserialize($field->value[0]) ) ? $field_data : $field->value;
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
		
		// CATEGORY SCOPE
		$allowed_cats = $disallowed_cats = false;
		
		if(!is_array($catids)) $catids = explode(",", $catids);
		if ($usesubcats) {
			// Find descendants of the categories
			$subcats = array();
			foreach ($catids as $catid) {
				$subcats = array_merge($subcats, array_map('trim',explode(",",$globalcats[$catid]->descendants)) );
			}
			$catids = array_unique($subcats);
		}
		
		if ( $method_cat == 3 ) {  // include method
			$allowed_cats = ($viewallcats) ? $catids : array_intersect($usercats, $catids);
		} else if ( $method_cat == 2 ) {  // exclude method
			$disallowed_cats = ($viewallcats) ? $catids : array_diff($usercats, $catids);
		} else if (!$viewallcats) {
			$allowed_cats = $usercats;
		}
		if ( $allowed_cats && ( !count($allowed_cats) || empty($allowed_cats[0]) ) ) $allowed_cats = false;
		if ( $disallowed_cats && ( !count($disallowed_cats) || empty($disallowed_cats[0]) ) ) $disallowed_cats = false;
		
		if ( $allowed_cats ) {
			$where .= ($where=="") ? "" : " AND ";
			$where .= " rel.catid IN (".implode(',',$allowed_cats ).") ";
		}
		if ( $disallowed_cats ) {
			$where .= ($where=="") ? "" : " AND ";
			$where .= " rel.catid NOT IN (".implode(',',$disallowed_cats ).") ";
		}
		
		// TYPE SCOPE
		if ( $types && ( !count($types) || empty($types[0]) ) ) $types = false;
		if ($types) {
			if ($method_types == 2) { // exclude method
				$where .= ($where=="") ? "" : " AND ";
				$where .= ' ie.type_id NOT IN (' . implode(',', $types) . ')';		
			} else if ($method_types == 3) { // include method
				$where .= ($where=="") ? "" : " AND ";
				$where .= ' ie.type_id IN (' . implode(',', $types) . ')';		
			}
		} else if ($method_types == 2 || $method_types == 3) {
			$field->html = 'Content Type scope is set to include/exclude but no Types are selected in field configuration, please set to "ALL" or select types to include/exclude'; 
			return;
		}
		
		// OTHER SCOPE LIMITS
		if ($samelangonly) {
			if ($item->language!='*') {  // for J2.5, but harmless for J1.5
				$where .= ($where=="") ? "" : " AND ";
				$where .= " (ie.language='{$item->language}' OR ie.language='*') ";
			}
		}
		if ($onlypublished) {
			$where .= ($where=="") ? "" : " AND ";
			$where .= " c.state IN (1, -5) ";
		}
		
		if ($where!="") $where = " WHERE " . $where;
		
		$orderby 	= $this->_buildItemOrderBy($order);
		
		$query = "SELECT c.title, c.id, c.catid, c.state, GROUP_CONCAT(rel.catid SEPARATOR ',') as catlist, c.alias FROM #__content AS c "
			. (($samelangonly || $method_types>1) ? " LEFT JOIN #__flexicontent_items_ext AS ie on c.id=ie.item_id " : "")
			. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel on c.id=rel.itemid '
			. ' LEFT JOIN #__users AS u ON u.id = c.created_by'
			. $where
			. " GROUP BY rel.itemid "
			. $orderby
			;
		$db->setQuery($query);
		//echo $query; //exit();
		$items_arr = $db->loadObjectList();
		if (!$items_arr) $items_arr = array();
		if($db->getErrorNum()) {
			echo $db->getErrorMsg();
			$field->html = '';
			return false;
		}
		
		require_once(JPATH_ROOT.DS."components".DS."com_flexicontent".DS."classes".DS."flexicontent.categories.php");
		$tree = flexicontent_cats::getCategoriesTree();
		if ($allowed_cats) {
			foreach ($allowed_cats as $catid) {
				$allowedtree[$catid] = $tree[$catid];
			}
		}
		if ($disallowed_cats) {
			foreach ($disallowed_cats as $catid) {
				unset($tree[$catid]);
			}
			$allowedtree = & $tree;
		}
		if (!$allowed_cats && !$disallowed_cats) {
			$allowedtree = & $tree;
		}
		
		//echo "<pre>"; foreach ($tree as $index => $cat) echo "$index\n";
		//exit();
		
		$ri_field_name  = str_replace('-','_',$field->name);
		$field->html .= "<div style='float:left;margin-right:16px;'>Select Category:<br>\n";
		$field->html .= flexicontent_cats::buildcatselect($allowedtree, $ri_field_name.'_fccats', $catvals="", false, ' class="inputbox" '.$size, true);
		$field->html .= "</div>\n";
		
		$field->html  .= "&nbsp;&nbsp;&nbsp;";
		
		$field->html .= "<div style='float:left;margin-right:16px;'>Category Items:<br>\n";
		$field->html .= '<select id="'.$ri_field_name.'_visitems" name="'.$ri_field_name.'_visitems[]" multiple="multiple" style="min-width:140px;" class="" '.$size.' >'."\n";
		$field->html .= '</select>'."\n";
		$field->html .= "</div>\n";
		
		$field->html .= "<div style='float:left;margin-right:16px;'><br>\n";
		$field->html .= '<a href="JavaScript:void(0);" id="btn-add_'.$ri_field_name.'">Add &raquo;</a><br>'."\n";
    $field->html .= '<a href="JavaScript:void(0);" id="btn-remove_'.$ri_field_name.'">&laquo; Remove</a><br>'."\n";
    
    if ($title_filter) {
			$document = &JFactory::getDocument();
			$document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/filterlist.js' );

			$field->html.=	'
				<br /><input id="'.$ri_field_name.'_regexp" name="'.$ri_field_name.'_regexp" onKeyUp="'.$ri_field_name.'_titlefilter.set(this.value)" size="20" />
				<br /><input type="button" onClick="'.$ri_field_name.'_titlefilter.set(this.form.'.$ri_field_name.'_regexp.value)" value="'.JText::_('FLEXI_RIFLD_FILTER').'" style="margin-top:6px;" />
				<input type="button" onClick="'.$ri_field_name.'_titlefilter.reset();this.form.'.$ri_field_name.'_regexp.value=\'\'" value="'.JText::_('FLEXI_RIFLD_RESET').'" style="margin-top:6px;" />
				
				<script type="text/javascript">
				<!--
				var filteredfield = document.getElementById("'.$ri_field_name.'_visitems");
				var '.$ri_field_name.'_titlefilter = new filterlist( filteredfield );
				//-->
				</script>
				';
    }
    
		$field->html .= "</div>\n";
    
    // The split up the items
		$items_options = '';
		$items_options_select = '';
		$items_options_unused = '';
		$state_shortname = array(1=>'P', 0=>'U', -1=>'A', -3=>'PE', -4=>'OQ', -5=>'IP');
		foreach($items_arr as $itemdata) {
			$itemtitle = (mb_strlen($itemdata->title) > $maxtitlechars) ? mb_substr($itemdata->title,0,$maxtitlechars) . "..." : $itemdata->title;
			if ($prepend_item_state) {
				$statestr = "[". @$state_shortname[$itemdata->state]."] ";
				$itemtitle = $statestr.$itemtitle." ";//.$itemdata->catlist;
			}
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
		
		$fieldname = FLEXI_J16GE ? 'custom['.$ri_field_name.'][]' : $ri_field_name.'[]';
		
		$field->html .= "<div style='float:left;margin-right:16px;'>Related Items<br>\n";
		
		$field->html .= '<select id="'.$ri_field_name.'" name="'.$fieldname.'" multiple="multiple" class="'.$required.'" style="min-width:140px;display:none;" '.$size.' >';
		$field->html .= $items_options_select;
		$field->html .= '</select>'."\n";
		
		$field->html .= '<select id="'.$ri_field_name.'_selitems" name="'.$ri_field_name.'_selitems[]" multiple="multiple" style="min-width:140px;" '.$size.' >';
		$field->html .= $items_options;
		$field->html .= '</select>'."\n";
		
		$field->html .= "</div>\n";
		
		$field->html .= '<select id="'.$ri_field_name.'_hiditems" name="'.$ri_field_name.'_hiditems" style="display:none;" >';
		$field->html .= $items_options_unused;
		$field->html .= '</select>'."\n";
		
		
		$js= "
		
window.addEvent( 'domready', function() {
 
    jQuery('#btn-add_".$ri_field_name."').click(function(){
        jQuery('#".$ri_field_name."_visitems option:selected').each( function() {
            jQuery('#".$ri_field_name."_selitems').append(\"<option class='\"+jQuery(this).attr('class')+\"' value='\"+jQuery(this).val()+\"'>\"+jQuery(this).text()+\"</option>\");
            jQuery('#".$ri_field_name."').append(\"<option selected='selected' class='\"+jQuery(this).attr('class')+\"' value='\"+jQuery(this).val()+\"'>\"+jQuery(this).text()+\"</option>\");
            jQuery(this).remove();
        });
    });
    jQuery('#btn-remove_".$ri_field_name."').click(function(){
        jQuery('#".$ri_field_name."_selitems option:selected').each( function() {
            jQuery('#".$ri_field_name."_visitems').append(\"<option class='\"+jQuery(this).attr('class')+\"' value='\"+jQuery(this).val()+\"'>\"+jQuery(this).text()+\"</option>\");
            jQuery(\"#".$ri_field_name." option[value='\"+jQuery(this).val()+\"']\").remove();
            jQuery(this).remove();
        });
    });

});

window.addEvent( 'domready', function() {
	$('".$ri_field_name."_fccats').addEvent( 'change', function() {
		
		". ( $title_filter ? $ri_field_name."_titlefilter.reset(); this.form.".$ri_field_name."_regexp.value='';" : "" ) . "
		
	  jQuery('#".$ri_field_name."_visitems option').each( function() {
	  	var data = jQuery(this).val().split(':'); 
	  	var itemid = data[0];
	  	jQuery('#".$ri_field_name."_hiditems').append(\"<option class='\"+jQuery(this).attr('class')+\"' value='\"+itemid+\"'>\"+jQuery(this).text()+\"</option>\");
	  	jQuery(this).remove();
		});
		
	  jQuery('#".$ri_field_name."_hiditems option').each( function() {
	  	if ( jQuery(this).hasClass('cat_' + jQuery('#".$ri_field_name."_fccats').attr('value') ) ) {
			  jQuery('#".$ri_field_name."_visitems').append(\"<option class='\"+jQuery(this).attr('class')+\"'value='\"+jQuery(this).val()+\":\"+jQuery('#".$ri_field_name."_fccats').val()+\"'>\"+jQuery(this).text()+\"</option>\");
				jQuery(this).remove();
	  	}
		});
		
		". ( $title_filter ? $ri_field_name."_titlefilter.init();" : "" ) . "
		
	});
});";
		
		$doc = & JFactory::getDocument();
		$doc->addScriptDeclaration( $js );
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems') return;
		
		global $globalcats;
		global $globalnoroute;
		if (!is_array($globalnoroute)) $globalnoroute = array();
		
		$values = $values ? $values : $field->value ;
		// Compatibility with old values, we no longer serialize all values to one, this way the field can be reversed !!!
		$values = ( $field_data = @unserialize($values) ) ? $field_data : $field->value;

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
			$separatorf = ' ';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = ' | ';
			break;

			case 3:
			$separatorf = ', ';
			break;

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			default:
			$separatorf = ' ';
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
		$publish_where = '';
		if ($field->parameters->get('use_publish_dates', 1 )) {
			$nullDate	= $db->getNullDate();
			$mainframe =& JFactory::getApplication();
			$now		= $mainframe->get('requestTime');
			$publish_where  = ' AND ( c.publish_up = '.$db->Quote($nullDate).' OR c.publish_up <= '.$db->Quote($now).' )'; 
			$publish_where .= ' AND ( c.publish_down = '.$db->Quote($nullDate).' OR c.publish_down >= '.$db->Quote($now).' )';
		}
		
		$order = $field->parameters->get('orderby_frontend');
		$orderby 	= $this->_buildItemOrderBy($order);
		
		$where = " WHERE c.id IN (". implode(",", array_keys($fieldval)) .")";
		
		$query = "SELECT c.title, c.id, c.alias, c.state, c.catid as maincatid, "
			." GROUP_CONCAT(cat.id SEPARATOR  ',') AS catidlist, "
			." GROUP_CONCAT(cat.alias SEPARATOR  ',') AS  cataliaslist "
			." FROM #__content AS c "
			." LEFT JOIN #__flexicontent_cats_item_relations AS rel ON c.id=rel.itemid "
			." LEFT JOIN #__categories AS cat ON rel.catid=cat.id "
			. $where
			. $publish_where
			. " GROUP BY c.id "
			. $orderby
			;
		$db->setQuery($query);
		if (count($values))
			$results = $db->loadObjectList();
		else
			$results = array();
			
		if($db->getErrorNum()) {
			echo $db->getErrorMsg();
			$field->{$prop} = '';
			return false;
		}
		
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
				$display[] = trim($pretext . $link . $posttext);
			}
			if ($values) {
				$field->{$prop} = implode($separatorf, $display);
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;
			} else {
				$field->{$prop} = '';
			}
		}
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems') return;
		if(!is_array($post) && !strlen($post)) return;
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	//  Build the order clause
	function _buildItemOrderBy($order)
	{
		$params = & $field->parameters;
		$filter_order		= '';
		$filter_order_dir	= '';
		
		if ($order) {
			switch ($order) {
				case 'date' :
				$filter_order		= 'c.created';
				$filter_order_dir	= 'ASC';
				break;
				case 'rdate' :
				$filter_order		= 'c.created';
				$filter_order_dir	= 'DESC';
				break;
				case 'modified' :
				$filter_order		= 'c.modified';
				$filter_order_dir	= 'DESC';
				break;
				case 'alpha' :
				$filter_order		= 'c.title';
				$filter_order_dir	= 'ASC';
				break;
				case 'ralpha' :
				$filter_order		= 'c.title';
				$filter_order_dir	= 'DESC';
				break;
				case 'author' :
				$filter_order		= 'u.name';
				$filter_order_dir	= 'ASC';
				break;
				case 'rauthor' :
				$filter_order		= 'u.name';
				$filter_order_dir	= 'DESC';
				break;
				case 'hits' :
				$filter_order		= 'c.hits';
				$filter_order_dir	= 'ASC';
				break;
				case 'rhits' :
				$filter_order		= 'c.hits';
				$filter_order_dir	= 'DESC';
				break;
				case 'order' :
				$filter_order		= 'rel.ordering';
				$filter_order_dir	= 'ASC';
				break;
			}
			
		}

		if ($filter_order)
			$orderby = ' ORDER BY '.$filter_order.' '.$filter_order_dir.', c.title';
		else
			$orderby = ' ORDER BY c.title';

		return $orderby;
	}
	
}
