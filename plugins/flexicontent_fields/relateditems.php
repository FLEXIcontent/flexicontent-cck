<?php
/**
 * @version 1.0 $Id: relateditems.php
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.relateditems
 * @copyright (C) 2011 ggppdk
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

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
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems') return;
		
		global $globalcats;
		$field->label = JText::_($field->label);
		
		// SCOPE PARAMETERS
		
		// categories scope parameters
		$method_cat = $field->parameters->get('method_cat', 1);
		$usesubcats = $field->parameters->get('usesubcats', 0 );
		
		$catids = $field->parameters->get('catids');
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
		if( $item->version == 0 && $default_values) {
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
			$field->html = '<div class="fc_mini_note_box">'. JText::_('FLEXI_NO_ACCESS_LEVEL_TO_VIEW_CATEGORY_TREE') . '</div>';
			return;
		}
		//ob_start();		print_r($field->value);		$field->html = ob_get_contents();    ob_end_clean();
		
		$where = "";
		
		// CATEGORY SCOPE
		$allowed_cats = $disallowed_cats = false;
		
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
			$where .= " i.state IN (1, -5) ";
		}
		
		if ($where!="") $where = " WHERE " . $where;
		
		$order = $field->parameters->get( 'orderby_form', 'alpha' );
		$orderby = $this->_buildItemOrderBy($order);
		
		$query = "SELECT i.title, i.id, i.catid, i.state, GROUP_CONCAT(rel.catid SEPARATOR ',') as catlist, i.alias FROM #__content AS i "
			. (($samelangonly || $method_types>1) ? " LEFT JOIN #__flexicontent_items_ext AS ie on i.id=ie.item_id " : "")
			. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel on i.id=rel.itemid '
			. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
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
			$filter->html = '';
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
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems') return;
		
		$field->label = JText::_($field->label);
		$field->{$prop} = '';
		$values = $values ? $values : $field->value;
		
		// Compatibility with old values, we no longer serialize all values to one, this way the field can be reversed !!!
		$values = ( $field_data = @unserialize($values) ) ? $field_data : $field->value;
		
		// No related items, return
		if ( !$values || !count($values) ) return;
		
		$fieldval = array();
		foreach($values as $i => $val) {
			//echo $val."<br>";
			list ($itemid,$catid) = explode(":", $val);
			$fieldval[$itemid] = new stdClass();
			$fieldval[$itemid]->itemid = $itemid;
			$fieldval[$itemid]->catid = $catid;
			$fieldval[$itemid]->val = $val;
		}
		$values = $fieldval;
		
		// Execute query to get item list data 
		$db = & JFactory::getDBO();
		$query = $this->_createItemsQuery($field, $item, $isform=0, $values);
		$db->setQuery($query);
		$item_list = & $db->loadObjectList('id');
		$field->value_item_list = & $item_list;
		//echo "<pre>"; print_r($item_list); echo "</pre>";
		
		if ($db->getErrorNum()) {
			//echo $db->getErrorMsg();
			$field->{$prop} = 'Field [id:'.$field->id.'] : SQL query error: '.$db->getErrorMsg();
			return;
		}
		
		// No published related items or SQL query failed, return
		if ( !$item_list ) return;
		
		$field->{$prop} = $this->_createItemsList($field, $item, $values, $item_list, $isform=0);
	}
	
	
	// Helper private method to create SQL query for retrieving items list data
	function &_createItemsQuery(&$field, &$item, $isform=0, $values=null)
	{
		$db = & JFactory::getDBO();
		$order = $field->parameters->get( $isform ? 'orderby_form' : 'orderby', 'alpha' );
		
		// Get data like aliases and published state
		$publish_where = '';
		if ($field->parameters->get('use_publish_dates', 1 )) {
			$nullDate	= $db->getNullDate();
			$mainframe =& JFactory::getApplication();
			$now		= $mainframe->get('requestTime');
			$publish_where  = ' AND ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$db->Quote($now).' )'; 
			$publish_where .= ' AND ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$db->Quote($now).' )';
		}
		
		$orderby = $this->_buildItemOrderBy($order);
		//$query = 'SELECT i.title, i.id, i.alias, i.state, i.catid, '
		$query = 'SELECT i.*, ext.type_id,'
			.' GROUP_CONCAT(c.id SEPARATOR  ",") AS catidlist, '
			.' GROUP_CONCAT(c.alias SEPARATOR  ",") AS  cataliaslist '
			.' FROM #__content AS i '
			.' LEFT JOIN #__flexicontent_items_ext AS ext ON i.id=ext.item_id '
			.' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON i.id=rel.itemid '
			.' LEFT JOIN #__categories AS c ON c.id=rel.catid '
			.' LEFT JOIN #__users AS u ON u.id = i.created_by'
			.' WHERE i.id IN ('. implode(",", array_keys($values)) .')'
			. $publish_where
			.' GROUP BY i.id '
			. $orderby
			;
		//echo "<pre>".$query."</pre>";
		return $query;
	}
	
	
	//  Build the order clause
	function &_createItemsList(&$field, &$item, $values, &$item_list, $isform=0)
	{
		$db = & JFactory::getDBO();
		global $globalcats;
		global $globalnoroute;
		if (!is_array($globalnoroute)) $globalnoroute = array();
		
		// Get fields of type relateditems
		static $related_items_fields = null;
		if ($related_items_fields===null) {
			$query = "SELECT name FROM #__flexicontent_fields WHERE field_type='relateditems'";
			$db->setQuery($query);
			$field_name_col = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
			$related_items_fields = !$field_name_col ? array() : array_flip($field_name_col);
		}
		
		// some parameter shortcuts
		$remove_space	= $field->parameters->get( 'remove_space', 0 ) ;
		$pretext			= $field->parameters->get( $isform ? 'pretext_form' : 'pretext', '' ) ;
		$posttext			= $field->parameters->get( $isform ? 'posttext_form' : 'posttext', '' ) ;
		$separatorf		= $field->parameters->get( $isform ? 'separator' : 'separatorf' ) ;
		$opentag			= $field->parameters->get( $isform ? 'opentag_form' : 'opentag', '' ) ;
		$closetag			= $field->parameters->get( $isform ? 'closetag_form' : 'closetag', '' ) ;
		$relitem_html = $field->parameters->get( $isform ? 'relitem_html_form' : 'relitem_html', '__display_text__' ) ;
		$displayway		= $field->parameters->get( $isform ? 'displayway_form' : 'displayway', 1 ) ;
		$addlink 			= $field->parameters->get( $isform ? 'addlink_form' : 'addlink', 1 ) ;
		$addtooltip		= $field->parameters->get( $isform ? 'addtooltip_form' : 'addtooltip', 1 ) ;
		
		// Parse and identify custom fields
		$result = preg_match_all("/\{\{([a-zA-Z_0-9]+)(##)?([a-zA-Z_0-9]+)?\}\}/", $relitem_html, $field_matches);
		if ($result) {
			$custom_field_names   = $field_matches[1];
			$custom_field_methods = $field_matches[3];
		} else {
			$custom_field_names   = array();
			$custom_field_methods = array();
		}
		
		/*echo "Fields for Related Items List: "; $sep = "";
		foreach ($custom_field_names as $i => $custom_field_name) {
			echo $sep . $custom_field_names[$i] . ($custom_field_methods[$i] ? "->". $custom_field_methods[$i] : ""); $sep = " , ";
		}
		echo "<br/>\n";*/
		
		// Parse and identify language strings and then make language replacements
		$result = preg_match_all("/\%\%([^%]+)\%\%/", $relitem_html, $translate_matches);
		if ($result) {
			$translate_strings = $translate_matches[1];
		} else {
			$translate_strings = array('FLEXI_READ_MORE_ABOUT');
		}
		foreach ($translate_strings as $translate_string) {
			$relitem_html = str_replace('%%'.$translate_string.'%%', JText::_($translate_string), $relitem_html);
		}
		
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
		if($posttext) { $posttext	= $remove_space ? $posttext : ' ' . $posttext; }
		
		
		global $fc_run_times;
		$fc_run_times['render_subfields'][$item->id."_".$field->id] = 0;
		
		foreach($item_list as $result)
		{
			// Check if related item is published and skip if not published
			if ($result->state != 1 && $result->state != -5) continue;
			
			$itemslug = $result->id.":".$result->alias;
			$catslug = "";
			
			// Check if removed from category or inside a noRoute category or inside a non-published category
			// and use main category slug or other routable & published category slug
			$catid_arr = explode(",", $result->catidlist);
			$catalias_arr = explode(",", $result->cataliaslist);
			for($i=0; $i<count($catid_arr); $i++) {
				$itemcataliases[$catid_arr[$i]] = $catalias_arr[$i];
			}
			$rel_itemid = $result->id;
			$rel_catid = isset($values[$rel_itemid]->catid) ? $values[$rel_itemid]->catid : $result->catid;
			if ( isset($itemcataliases[$rel_catid]) && !in_array($rel_catid, $globalnoroute) && $globalcats[$rel_catid]->published) {
				$catslug = $rel_catid.":".$itemcataliases[$rel_catid];
			} else if (!in_array($result->catid, $globalnoroute) && $globalcats[$result->catid]->published ) {
				$catslug = $globalcats[$result->catid]->slug;
			} else {
				foreach ($catid_arr as $catid) {
					if ( !in_array($catid, $globalnoroute) && $globalcats[$catid]->published) {
						$catslug = $globalcats[$catid]->slug;
						break;
					}
				}
			}
			$result->slug = $itemslug;
			$result->categoryslug = $catslug;
		}
		
		foreach($custom_field_names as $i => $custom_field_name) {
			if ( !isset($related_items_fields[$custom_field_name]) ) {
				$display_var = $custom_field_methods[$i] ? $custom_field_methods[$i] : 'display';
				$start_microtime = microtime(true);
				FlexicontentFields::getFieldDisplay($item_list, $custom_field_name, $custom_field_values=null, $display_var);
				$fc_run_times['render_subfields'][$item->id."_".$field->id] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			} else {
				//$custom_field_display = 'cannot replace field: "'.$custom_field_name.'" because it is of type "related_items", which can cause loop';
			}
		}
		
		$display = array();
		foreach($item_list as $result)
		{
			// Check if related item is published and skip if not published
			if ($result->state != 1 && $result->state != -5) continue;
			
			// a. Replace some custom made strings
			$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($result->slug, $result->categoryslug));
			$item_title_escaped = htmlspecialchars($result->title, ENT_COMPAT, 'UTF-8');
			$item_tooltip = ' class="hasTip relateditem" title="'. JText::_('FLEXI_READ_MORE_ABOUT').'::'.$item_title_escaped.'" ';
			
			$display_text = $displayway ? $result->title : $result->id;
			$display_text = !$addlink ? $display_text : '<a href="'.$item_url.'"'.($addtooltip ? $item_tooltip : '').' >' .$display_text. '</a>';
			
			$curr_relitem_html = $relitem_html;
			$curr_relitem_html = str_replace('__item_url__', $item_url, $curr_relitem_html);
			$curr_relitem_html = str_replace('__item_title_escaped__', $item_title_escaped, $curr_relitem_html);
			$curr_relitem_html = str_replace('__item_tooltip__', $item_tooltip, $curr_relitem_html);
			$curr_relitem_html = str_replace('__display_text__', $display_text, $curr_relitem_html);
			
			// b. Replace item properties, e.g. {item->id}, (item->title}, etc
			FlexicontentFields::doQueryReplacements($curr_relitem_html, $null_field=null, $result);
			
			// c. Replace HTML display of various item fields
			foreach($custom_field_names as $i => $custom_field_name) {
				if ( !isset($related_items_fields[$custom_field_name]) ) {
					$display_var = $custom_field_methods[$i] ? $custom_field_methods[$i] : 'display';
					$custom_field_display = $result->fields[$custom_field_name]->{$display_var};
				} else {
					$custom_field_display = 'cannot replace field: "'.$custom_field_name.'" because it is of type "related_items", which can cause loop';
				}
				$custom_field_str = $custom_field_name . ($custom_field_methods[$i] ? "##".$custom_field_methods[$i] : "");
				$curr_relitem_html = str_replace('{{'.$custom_field_str.'}}', $custom_field_display, $curr_relitem_html);
			}
			$display[] = trim($pretext . $curr_relitem_html . $posttext);
		}
		
		$display = $opentag . implode($separatorf, $display) . $closetag;
		return $display;
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
	
	
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a category filter for the category view
	/*function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'relateditems_backlinks') return;
		// some parameter shortcuts
		
		$field_id = $filter->id;
		
		$db =& JFactory::getDBO();
		$field_elements= 'SELECT DISTINCT fir.item_id as value, i.title as text'
						 .' FROM #__content as i'
						 .' LEFT JOIN #__flexicontent_fields_item_relations as fir ON i.id=fir.item_id AND fir.field_id='.$field_id
						 ;
		$db->setQuery($query);
		$results = $db->loadObjectList();
		echo $db->getErrorMsg();
		
		if (!$results) {
			$filter->html = '';
		} else {
			$options = array();
			$options[] = JHTML::_('select.option', '', '-'.JText::_('All').'-');
			foreach($results as $result) {
				$options[] = JHTML::_('select.option', $result->value, $result->text);
			}
			$filter->html	= JHTML::_('select.genericlist', $options, 'filter_'.$filter->id, ' class="fc_field_filter" onchange="document.getElementById(\''.$formName.'\').submit();"', 'value', 'text', $value);
		}

	}*/
	
	
	
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
				$filter_order		= 'i.created';
				$filter_order_dir	= 'ASC';
				break;
				case 'rdate' :
				$filter_order		= 'i.created';
				$filter_order_dir	= 'DESC';
				break;
				case 'modified' :
				$filter_order		= 'i.modified';
				$filter_order_dir	= 'DESC';
				break;
				case 'alpha' :
				$filter_order		= 'i.title';
				$filter_order_dir	= 'ASC';
				break;
				case 'ralpha' :
				$filter_order		= 'i.title';
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
				$filter_order		= 'i.hits';
				$filter_order_dir	= 'ASC';
				break;
				case 'rhits' :
				$filter_order		= 'i.hits';
				$filter_order_dir	= 'DESC';
				break;
				case 'order' :
				$filter_order		= 'rel.ordering';
				$filter_order_dir	= 'ASC';
				break;
			}
			
		}

		if ($filter_order)
			$orderby = ' ORDER BY '.$filter_order.' '.$filter_order_dir.', i.title';
		else
			$orderby = ' ORDER BY i.title';

		return $orderby;
	}
	
}
