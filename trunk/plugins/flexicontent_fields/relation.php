<?php
/**
 * @version 1.0 $Id: relation.php
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.relation
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

class plgFlexicontent_fieldsRelation extends JPlugin
{
	// ***********
	// CONSTRUCTOR
	// ***********
	static $field_types = array('relation');
	
	function plgFlexicontent_fieldsRelation( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_relation', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		$field->label = JText::_($field->label);
		
		// Get some api objects
		$db   = JFactory::getDBO();
		$user = JFactory::getUser();
		$document = JFactory::getDocument();
		
		
		// ******************
		// SCOPE PARAMETERS
		// ******************
		
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
		
		
		// ******************
		// EDITING PARAMETERS
		// ******************
		
		// some parameters shortcuts
		$size				= $field->parameters->get( 'size', 12 ) ;
		$size	 	= $size ? ' size="'.$size.'"' : '';
		$prepend_item_state = $field->parameters->get( 'prepend_item_state', 1 ) ;
		$maxtitlechars 	= $field->parameters->get( 'maxtitlechars', 40 ) ;
		$title_filter = $field->parameters->get( 'title_filter', 1 ) ;
		$required 	= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';
		
		
		// ************************************************************************
		// Initialise values and split them into: (a) item ids and (b) category ids
		// ************************************************************************
		$default_values		= '';
		if( $item->version == 0 && $default_values) {
			$field->value = explode(",", $default_values);
		} else if (!$field->value) {
			$field->value = array();
		} else {
			// Compatibility with old values, we no longer serialize all values to one, this way the field can be reversed more easily !!!
			$field->value = ( $field_data = @unserialize($field->value[0]) ) ? $field_data : $field->value;
		}
		
		$_itemids_catids = array();
		foreach($field->value as $i => $val) {
			list ($itemid,$catid) = explode(":", $val);
			$_itemids_catids[$itemid] = new stdClass();
			$_itemids_catids[$itemid]->itemid = $itemid;
			$_itemids_catids[$itemid]->catid  = $catid;
			$_itemids_catids[$itemid]->value  = $val;
		}
		
		
		// ***********************************************
		// Get & check Global category related permissions
		// ***********************************************
		
		require_once (JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
		$viewallcats	= FlexicontentHelperPerm::getPerm()->ViewAllCats;
		$viewtree			= FlexicontentHelperPerm::getPerm()->ViewTree;
		if (!$viewtree) {
			$field->html = '<div class="fc_mini_note_box">'. JText::_('FLEXI_NO_ACCESS_LEVEL_TO_VIEW_CATEGORY_TREE') . '</div>';
			return;
		}
		
		
		// ****************************************************
		// Calculate categories to use for retrieving the items
		// ****************************************************
		$allowed_cats = $disallowed_cats = false;
		
		// Get user allowed categories
		$usercats = (FLEXI_J16GE || FLEXI_ACCESS) ?
			FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed=array('core.create', 'core.edit', 'core.edit.own'), $require_all=false, $check_published = true) :
			FlexicontentHelperPerm::returnAllCats($check_published=true, $specific_catids=null);
		
		// Find (if configured) , descendants of the categories
		if ($usesubcats) {
			global $globalcats;
			$_catids = array();
			foreach ($catids as $catid) {
				$subcats = array_map('trim',explode(",",$globalcats[$catid]->descendants));
				foreach ($subcats as $subcat) $_catids[(int)$subcat] = 1;
			}
			$catids = array_keys($_catids);
		}
		
		if ( $method_cat == 3 )      $allowed_cats = ($viewallcats) ? $catids : array_intersect($usercats, $catids);  // include method
		else if ( $method_cat == 2 ) $disallowed_cats = ($viewallcats) ? $catids : array_diff($usercats, $catids);    // exclude method
		else if (!$viewallcats)      $allowed_cats = $usercats;
		
		if ( $allowed_cats && ( !count($allowed_cats) || empty($allowed_cats[0]) ) ) $allowed_cats = false;
		if ( $disallowed_cats && ( !count($disallowed_cats) || empty($disallowed_cats[0]) ) ) $disallowed_cats = false;
		
		
		// ... TODO: retrieve items via AJAX
		
		// *********************************************
		// Item retrieving query ... CREATE WHERE CLAUSE
		// *********************************************
		$where = array();
		
		// CATEGORY SCOPE
		if ( $allowed_cats )    $where[] = " rel.catid IN (".implode(',',$allowed_cats ).") ";
		if ( $disallowed_cats ) $where[] = " rel.catid NOT IN (".implode(',',$disallowed_cats ).") ";
		
		// TYPE SCOPE
		if ( ($method_types == 2 || $method_types == 3) && ( !count($types) || empty($types[0]) ) ) {
			$field->html = 'Content Type scope is set to include/exclude but no Types are selected in field configuration, please set to "ALL" or select types to include/exclude'; 
			return;
		}
		if ($method_types == 2)       $where[] = ' ie.type_id NOT IN (' . implode(',', $types) . ')';   // exclude method
		else if ($method_types == 3)  $where[] = ' ie.type_id IN (' . implode(',', $types) . ')';       // include method
		
		// OTHER SCOPE LIMITS
		if ($samelangonly)  $where[] = $item->language=='*' ? " ie.language='*' " : " (ie.language='{$item->language}' OR ie.language='*') ";
		if ($onlypublished) $where[] = " i.state IN (1, -5) ";
		
		$where = !count($where) ? "" : " WHERE " . implode(" AND ", $where);
		
		
		// ***********************************************
		// Item retrieving query ... CREATE ORDERBY CLAUSE
		// ***********************************************
		$order = $field->parameters->get( 'orderby_form', 'alpha' );
		$orderby = flexicontent_db::buildItemOrderBy($field->parameters, $order, $request_var='', $config_param='', $item_tbl_alias = 'i', $relcat_tbl_alias = 'rel');
		
		
		// *****************************************************
		// Item retrieving query ... put together and execute it
		// *****************************************************
		$query = "SELECT i.title, i.id, i.catid, i.state, GROUP_CONCAT(rel.catid SEPARATOR ',') as catlist, i.alias FROM #__content AS i "
			. (($samelangonly || $method_types>1) ? " LEFT JOIN #__flexicontent_items_ext AS ie on i.id=ie.item_id " : "")
			. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel on i.id=rel.itemid '
			. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			. $where
			. " GROUP BY rel.itemid "
			. $orderby
			;
		$db->setQuery($query);
		$items_arr = $db->loadObjectList();
		
		if($db->getErrorNum()) {
			echo $db->getErrorMsg();
			$filter->html = '';
			return false;
		}
		
		
		// *******************************************************
		// Create category tree to use for selecting related items
		// *******************************************************
		require_once(JPATH_ROOT.DS."components".DS."com_flexicontent".DS."classes".DS."flexicontent.categories.php");
		$tree = flexicontent_cats::getCategoriesTree();  // Get categories without filtering
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
		
		
		// *************************************************
		// Create the HTML for editing/entering field values
		// *************************************************
		static $select2_added = false;
	  if ( !$select2_added )
	  {
			$select2_added = true;
			flexicontent_html::loadFramework('select2');
		}
		
		$ri_field_name  = str_replace('-','_',$field->name);
		
		$css = '.'.$ri_field_name.'_fccats { min-width:500px !important; }';
		if ($css) $document->addStyleDeclaration($css);
		
		$field->html .= "<div style='float:none;margin-bottom:12px;'>";
		$field->html .= flexicontent_cats::buildcatselect(
			$allowedtree, $ri_field_name.'_fccats', $catvals="",
			$top=2, // (adds first option "please select") Important otherwise single entry in select can initiate onchange event
			' class="use_select2_lib inputbox '.$ri_field_name.'_fccats" ',
			$check_published = true, $check_perms = true,
			$actions_allowed=array('core.create', 'core.edit', 'core.edit.own'), $require_all=false
		);
		$field->html .= "</div>\n";
		
		$field->html  .= "&nbsp;&nbsp;&nbsp;";
		
		$field->html .= "<div style='float:left;clear:left;margin-right:16px;'>Category Items:<br>\n";
		$field->html .= '<select id="'.$ri_field_name.'_visitems" name="'.$ri_field_name.'_visitems[]" multiple="multiple" style="min-width:180px;" class="fcfield_selectmulval" '.$size.' >'."\n";
		$field->html .= '</select>'."\n";
		$field->html .= "</div>\n";
		
		$field->html .= "<div style='float:left;margin-right:16px; text-align:center;'><br>\n";
		$field->html .= '<a href="JavaScript:void(0);" id="btn-add_'.$ri_field_name.'" class="fcfield-button" >Add &raquo;</a><br>'."\n";
    $field->html .= '<a href="JavaScript:void(0);" id="btn-remove_'.$ri_field_name.'" class="fcfield-button" >&laquo; Remove</a><br>'."\n";
    
    if ($title_filter)
    {
			$document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/filterlist.js' );
			$field->html.=	'<br />
				<br /><input class="fcfield_textval" id="'.$ri_field_name.'_regexp" name="'.$ri_field_name.'_regexp" onKeyUp="'.$ri_field_name.'_titlefilter.set(this.value)" size="30" />
				<br /><input style="margin-left:0px!important;" class="fcfield-button" type="button" onClick="'.$ri_field_name.'_titlefilter.set(this.form.'.$ri_field_name.'_regexp.value)" value="'.JText::_('FLEXI_RIFLD_FILTER').'" style="margin-top:6px;" />
				<input style="margin-left:0px!important;" class="fcfield-button" type="button" onClick="'.$ri_field_name.'_titlefilter.reset();this.form.'.$ri_field_name.'_regexp.value=\'\'" value="'.JText::_('FLEXI_RIFLD_RESET').'" style="margin-top:6px;" />
				
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
			if ( isset($_itemids_catids[$itemid]) ) {
				$items_options .= '<option class="'.$classes_str.'" value="'.$_itemids_catids[$itemid]->value.'" >'.$itemtitle.'</option>'."\n";
				$items_options_select .= '<option selected="selected" class="'.$classes_str.'" value="'.$_itemids_catids[$itemid]->value.'" >'.$itemtitle.'</option>'."\n";
			} else {
				$items_options_unused .= '<option class="'.$classes_str.'" value="'.$itemid.'" >'.$itemtitle.'</option>'."\n";
			}
		}
		
		$fieldname = FLEXI_J16GE ? 'custom['.$ri_field_name.'][]' : $ri_field_name.'[]';
		
		$field->html .= "<div style='float:left;margin-right:16px;'>Related Items<br>\n";
		
		$field->html .= '<select id="'.$ri_field_name.'" name="'.$fieldname.'" multiple="multiple" class="'.$required.'" style="min-width:180px;display:none;" '.$size.' >';
		$field->html .= $items_options_select;
		$field->html .= '</select>'."\n";
		
		$field->html .= '<select id="'.$ri_field_name.'_selitems" name="'.$ri_field_name.'_selitems[]" multiple="multiple" style="min-width:180px;" class="fcfield_selectmulval" '.$size.' >';
		$field->html .= $items_options;
		$field->html .= '</select>'."\n";
		
		$field->html .= "</div>\n";
		
		$field->html .= '<select id="'.$ri_field_name.'_hiditems" name="'.$ri_field_name.'_hiditems" style="display:none;" >';
		$field->html .= $items_options_unused;
		$field->html .= '</select>'."\n";
		
		
		$js= "
		
jQuery(document).ready(function() {
	
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

jQuery(document).ready(function() {
	
	jQuery('#".$ri_field_name."_fccats').change(function() {
		
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
		
		$document->addScriptDeclaration( $js );
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$field->{$prop} = '';
		$values = $values ? $values : $field->value;
		
		if ($field->field_type == 'relation_reverse')
		{
			$reverse_field = $field->parameters->get( 'reverse_field', 0) ;
			if ( !$reverse_field ) {
				$field->{$prop} = 'Field [id:'.$field->id.'] : '.JText::_('FLEXI_FIELD_NO_FIELD_SELECTED');
				return;
			}
			$_itemids_catids = null;  // Always ignore passed items, the DB query will determine the items
		}
		else  // $field->field_type == 'relation')
		{
			// Compatibility with old values, we no longer serialize all values to one, this way the field can be reversed !!!
			$values = ( $field_data = @unserialize($values) ) ? $field_data : $field->value;
			// No related items, just return empty display
			if ( !$values || !count($values) ) return;
			
			$_itemids_catids = array();
			foreach($values as $i => $val) {
				list ($itemid,$catid) = explode(":", $val);
				$_itemids_catids[$itemid] = new stdClass();
				$_itemids_catids[$itemid]->itemid = $itemid;
				$_itemids_catids[$itemid]->catid = $catid;
				$_itemids_catids[$itemid]->value  = $val;
			}
		}
		
		$field->{$prop} = FlexicontentFields::getItemsList($field->parameters, $_itemids_catids, $isform=0, @ $reverse_field, $field, $item);
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
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
		if ( !in_array($field->field_type, self::$field_types) ) return;
		// some parameter shortcuts
		
		$field_id = $filter->id;
		
		$db = JFactory::getDBO();
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
	
}
