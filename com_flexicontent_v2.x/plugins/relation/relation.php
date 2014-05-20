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
		$field->html = '';
		
		$ri_field_name = str_replace('-','_',$field->name);
		$fieldname = FLEXI_J16GE ? 'custom['.$ri_field_name.'][]' : $ri_field_name.'[]';
		
		// Case of autorelated item
		$autorelation_itemid = JRequest::getInt('autorelation_'.$field->id);
		if ( $autorelation_itemid)
		{
			// automatically related item
			$query = 'SELECT title, id, catid, state, alias '
				. ' FROM #__content '
				. ' WHERE id ='. $autorelation_itemid
				;
			$db->setQuery($query);
			$rel_item = $db->loadObject();
			
			if (!$rel_item) {
				$field->html = 'auto relating item id: '.$autorelation_itemid .' : item not found ';
				return;
			}
			
			$field->html = '<input id="'.$ri_field_name.'" name="'.$fieldname.'" type="hidden" value="'.$rel_item->id.':'.$rel_item->catid.'" />';
			$field->html .= $rel_item->title;
			return;
		}
		
		
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
			$itemid = (int) $itemid;
			$catid  = (int) $catid;
			$_itemids_catids[$itemid] = new stdClass();
			$_itemids_catids[$itemid]->itemid = $itemid;
			$_itemids_catids[$itemid]->catid  = $catid;
			$_itemids_catids[$itemid]->value  = $val;
		}
		
		$auto_relate_curritem = $field->parameters->get( 'auto_relate_curritem', 0);
		if ($auto_relate_curritem && !empty($_itemids_catids) && !FlexicontentHelperPerm::getPerm()->SuperAdmin)
		{
			$query = 'SELECT title, id, catid, state, alias '
				. ' FROM #__content '
				. ' WHERE id IN ('. implode( array_keys($_itemids_catids), ',') .')'
				;
			$db->setQuery($query);
			$rel_items = $db->loadObjectList();
			$i = 0;
			foreach ($rel_items as $rel_item) {
				$field->html .= '<input id="'.$ri_field_name.$i.'" name="'.$fieldname.'" type="hidden" value="'.$rel_item->id.':'.$rel_item->catid.'" />';
				$field->html .= $rel_item->title." <br/> \n";
				$i++;
			}
			return;
		}
		
		
		
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
		$ownedbyuser   = $field->parameters->get( 'ownedbyuser', 0 );
		
		
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
		$select_items_prompt = $field->parameters->get( 'select_items_prompt', 'FLEXI_RIFLD_SELECT_ITEMS_PROMPT' ) ;
		$selected_items_label = $field->parameters->get( 'selected_items_label', 'FLEXI_RIFLD_SELECTED_ITEMS_LABEL' ) ;
		
		
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
				$subcats = $globalcats[$catid]->descendantsarray;
				foreach ($subcats as $subcat)  $_catids[(int)$subcat] = 1;
			}
			$catids = array_keys($_catids);
		}
		
		// ... TODO: retrieve items via AJAX
		
		// *********************************************
		// Item retrieving query ... CREATE WHERE CLAUSE
		// *********************************************
		$where = array();
		
		
		// **************
		// CATEGORY SCOPE
		// **************
		
		// Include method
		if ( $method_cat == 3 ) {
			
			$allowed_cats = ($viewallcats) ? $catids : array_intersect($usercats, $catids);
			if ( !empty($allowed_cats) ) {
				$where[] = " rel.catid IN (".implode(',',$allowed_cats ).") ";
			} else {
				$field->html = JText::_('FLEXI_CANNOT_EDIT_FIELD') .': <br/> '. JText::_('FLEXI_NO_ACCESS_TO_USE_CONFIGURED_CATEGORIES');
				return;
			}
		}
		
		// Exclude method
		else if ( $method_cat == 2 ) {
			$disallowed_cats = ($viewallcats) ? $catids : array_diff($usercats, $catids);
			if ( !empty($disallowed_cats) ) {
				$where[] = " rel.catid NOT IN (".implode(',',$disallowed_cats ).") ";
			}
		}
		
		// ALL user allowed categories
		else if (!$viewallcats) {
			$allowed_cats = $usercats;
			if ( !empty($allowed_cats) ) {
				$where[] = " rel.catid IN (".implode(',',$allowed_cats ).") ";
			} else {
				$field->html = JText::_('FLEXI_CANNOT_EDIT_FIELD') .': <br/> '. JText::_('FLEXI_NO_ACCESS_TO_USE_ANY_CATEGORIES');
				return;
			}
		}
		
		
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
		if ($ownedbyuser==1) $where[] = " i.created_by = ". $user->id;
		else if ($ownedbyuser==2) $where[] = " i.created_by = ". $item->created_by;
		
		$where = !count($where) ? "" : " WHERE " . implode(" AND ", $where);
		
		
		// ***********************************************
		// Item retrieving query ... CREATE ORDERBY CLAUSE
		// ***********************************************
		$order = $field->parameters->get( 'orderby_form', 'alpha' );;   // TODO: add more orderings: commented, rated
		$orderby = flexicontent_db::buildItemOrderBy(
			$field->parameters,
			$order, $request_var='', $config_param='',
			$item_tbl_alias = 'i', $relcat_tbl_alias = 'rel',
			$default_order='', $default_order_dir='', $sfx='_form', $support_2nd_lvl=false
		);
		
		// Create JOIN for ordering items by a most rated
		if ( in_array('author', $order) || in_array('rauthor', $order) ) {
			$orderby_join = ' LEFT JOIN #__users AS u ON u.id = i.created_by';
		}
		
		
		// *****************************************************
		// Item retrieving query ... put together and execute it
		// *****************************************************
		$query = 'SELECT i.title, i.id, i.catid, i.state, i.alias'
			.", GROUP_CONCAT(rel.catid SEPARATOR ',') as catlist"
			.' FROM #__content AS i '
			. (($samelangonly || $method_types>1) ? " LEFT JOIN #__flexicontent_items_ext AS ie on i.id=ie.item_id " : "")
			. ' JOIN #__flexicontent_cats_item_relations AS rel on i.id=rel.itemid '
			. @ $orderby_join
			. $where
			. " GROUP BY rel.itemid "
			. $orderby
			;
		$db->setQuery($query);
		$items_arr = $db->loadObjectList();
		
		if($db->getErrorNum()) {
			echo $db->getErrorMsg();
			$field->html = '';
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
		
		static $common_css_js_added = false;
	  if ( !$common_css_js_added )
	  {
			$common_css_js_added = true;
			flexicontent_html::loadFramework('select2');
			
			$css = ''
				.'.fcrelation_field_filters span.label { min-width:140px !important; }'
				.'.fcrelation_field_used_items select, .fcrelation_field_unused_items select { min-width: 90% !important; }'
				.'.fcrelation_field_controls { margin: 2px 20% 6px 20%; }'
				;
			if ($css) $document->addStyleDeclaration($css);
		}
		
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
		
		
		$field->html .= "<div class='fcrelation_field_filters'>";
		
		$field->html .= " <span class='fcrelation_field_filter_by_cat'>";
		$field->html .= "  <span class='label'>".JText::_('FLEXI_RIFLD_FILTER_BY_CAT')."</span>\n";
		$field->html .= flexicontent_cats::buildcatselect(
			$allowedtree, $ri_field_name.'_fccats', $catvals="",
			$top=2, // (adds first option "please select") Important otherwise single entry in select cannot initiate onchange event
			' class="use_select2_lib inputbox '.$ri_field_name.'_fccats" ',
			$check_published = true, $check_perms = true,
			$actions_allowed=array('core.create', 'core.edit', 'core.edit.own'), $require_all=false,
			$skip_subtrees=array(), $disable_subtrees=array(), $custom_options=array('__ALL__'=>'FLEXI_RIFLD_FILTER_LIST_ALL')
		);
		$field->html .= " </span>\n";
		
		$field->html .= " <div class='fcclear'></div>";
		$field->html .= " <span class='fcrelation_field_filter_by_title'>";
		$field->html .= "  <span class='label'>".JText::_('FLEXI_RIFLD_FILTER_BY_TITLE')."</span>\n";
    
    if ($title_filter)
    {
			$document->addScript( JURI::root(true).'/components/com_flexicontent/assets/js/filterlist.js' );
			$field->html.=	''
				.'<input class="fcfield_textval" id="'.$ri_field_name.'_regexp" name="'.$ri_field_name.'_regexp" onKeyUp="'.$ri_field_name.'_titlefilter.set(this.value)" size="30" />'
				//.'<input style="margin-left:0px!important; margin-top:6px;" class="fcfield-button" type="button" onclick="'.$ri_field_name.'_titlefilter.set(this.form.'.$ri_field_name.'_regexp.value)" value="'.JText::_('FLEXI_RIFLD_FILTER').'" />'
				.'<input style="margin-left:0px!important; margin-top:6px;" class="fcfield-button" type="button" onclick="'.$ri_field_name.'_titlefilter.reset();this.form.'.$ri_field_name.'_regexp.value=\'\'" value="'.JText::_('FLEXI_RIFLD_RESET').'" />'
				;
    }
		$field->html .= " </span>\n";
		
		$field->html .= "</div>\n";  // fcrelation_field_filters
    
		
		$field->html .= "<div class='fcrelation_field_unused_items'>";
		$field->html .= "<span class='label'>".JText::_($select_items_prompt)."</span><br/>\n";
		$field->html .= '<select id="'.$ri_field_name.'_visitems" name="'.$ri_field_name.'_visitems[]" multiple="multiple" class="fcfield_selectmulval" '.$size.' >'."\n";
		$field->html .= '</select>'."\n";
		$field->html .= "</div>\n";
		
		$field->html .= "<div class='fcrelation_field_controls'>";
		$field->html .= '<a href="JavaScript:void(0);" id="btn-add_'.$ri_field_name.'" class="fcfield-button" >Add &raquo;</a>'."\n";
    $field->html .= '<a href="JavaScript:void(0);" id="btn-remove_'.$ri_field_name.'" class="fcfield-button" >&laquo; Remove</a>'."\n";
		$field->html .= "</div>\n";
    
		$field->html .= "<div class='fcrelation_field_used_items'>";
		$field->html .= "<span class='label'>".JText::_($selected_items_label)."</span><br/>\n";
		
		$field->html .= '<select id="'.$ri_field_name.'" name="'.$fieldname.'" multiple="multiple" class="'.$required.'" style="display:none;" '.$size.' >';
		$field->html .= $items_options_select;
		$field->html .= '</select>'."\n";
		
		$field->html .= '<select id="'.$ri_field_name.'_selitems" name="'.$ri_field_name.'_selitems[]" multiple="multiple" class="fcfield_selectmulval" '.$size.' >';
		$field->html .= $items_options;
		$field->html .= '</select>'."\n";
		
		$field->html .= '<select id="'.$ri_field_name.'_hiditems" name="'.$ri_field_name.'_hiditems" style="display:none;" >';
		$field->html .= $items_options_unused;
		$field->html .= '</select>'."\n";
		$field->html .= "</div>\n";
		
		
		$js= ($title_filter ? ' var filteredfield, '.$ri_field_name.'_titlefilter;' : '')."

jQuery(document).ready(function() {

".($title_filter ? '
	filteredfield = document.getElementById("'.$ri_field_name.'_visitems");
	'.$ri_field_name.'_titlefilter = new filterlist( filteredfield );
	' : '')."

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
		
		var ".$ri_field_name."_fccats_val = jQuery('#".$ri_field_name."_fccats').val();
		
		". ( $title_filter ? $ri_field_name."_titlefilter.reset(); this.form.".$ri_field_name."_regexp.value='';" : "" ) . "
		
	  jQuery('#".$ri_field_name."_visitems option').each( function() {
	  	var data = jQuery(this).val().split(':'); 
	  	var itemid = data[0];
	  	jQuery('#".$ri_field_name."_hiditems').append(\"<option class='\"+jQuery(this).attr('class')+\"' value='\"+itemid+\"'>\"+jQuery(this).text()+\"</option>\");
	  	jQuery(this).remove();
		});
		
	  jQuery('#".$ri_field_name."_hiditems option').each( function() {
	  	if ( ".$ri_field_name."_fccats_val == '__ALL__' || jQuery(this).hasClass('cat_' + ".$ri_field_name."_fccats_val ) ) {
			  jQuery('#".$ri_field_name."_visitems').append(\"<option class='\"+jQuery(this).attr('class')+\"'value='\"+jQuery(this).val()+\":\"+ ".$ri_field_name."_fccats_val+\"'>\"+jQuery(this).text()+\"</option>\");
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
		
		
		// *******************************************
		// Check for special display : total info only
		// *******************************************
		$show_total_only = $field->parameters->get('show_total_only', 0);
		
		if ($prop=='display_total') {
			$display_total = true;
		}
		
		else if ( $show_total_only==1 || ($show_total_only == 2 && count($values)) ) {
			$app = JFactory::getApplication();
			$view = JRequest::getVar('view');
			$option = JRequest::getVar('option');
			$isItemsManager = $app->isAdmin() && $view=='items' && $option=='com_flexicontent';
			
			$total_in_view = $field->parameters->get('total_in_view', array('backend'));
			$total_in_view = FLEXIUtilities::paramToArray($total_in_view);
			$display_total = ($isItemsManager && in_array('backend', $total_in_view)) || in_array($view, $total_in_view);
		}
		
		else {
			$display_total = false;
		}
		
		
		// ***********************************************************
		// Create total info and terminate if not adding the item list
		// ***********************************************************
		
		if ($display_total)
		{
			$total_append_text   = $field->parameters->get('total_append_text', '');
			$total_show_list     = $field->parameters->get('total_show_list', 0);
			$total_show_auto_btn = $field->parameters->get('total_show_auto_btn', 0);
			
			$field->{$prop} .= '<span class="fcrelation_field_total">'. count($values) .' '. $total_append_text .'<span>';
			
			// Terminate if not adding any extra information
			if ( !$total_show_list && !$total_show_auto_btn ) return;
			
			// Override the item list HTML parameter ...
			$total_relitem_html = $field->parameters->get('total_relitem_html', '');
			if ($total_relitem_html) $field->parameters->set('relitem_html', $total_relitem_html );
		}
		
		
		// ***********************************************************
		// Prepare item list data for rendering the related items list
		// ***********************************************************
		
		if ($field->field_type == 'relation_reverse')
		{
			$reverse_field = $field->parameters->get( 'reverse_field', 0) ;
			if ( !$reverse_field ) {
				$field->{$prop} .= 'Field [id:'.$field->id.'] : '.JText::_('FLEXI_FIELD_NO_FIELD_SELECTED');
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
		
		
		// **********************************************
		// Create the submit button for auto related item
		// **********************************************
		
		$auto_relate_curritem = $field->parameters->get( 'auto_relate_curritem', 0);
		$auto_relate_menu_itemid = $field->parameters->get( 'auto_relate_menu_itemid', 0);
		$auto_relate_position = $field->parameters->get( 'auto_relate_position', 0);
		$auto_rel_btn = '';
		if ( $auto_relate_curritem && $auto_relate_menu_itemid && (!$display_total || $total_show_auto_btn) )
		{
			$_submit_text = $field->parameters->get( 'auto_relate_submit_text', 'FLEXI_ADD_RELATED');
			$_show_to_unauth = $field->parameters->get( 'auto_relate_show_to_unauth', 0);
			$auto_relations[0] = new stdClass();
			$auto_relations[0]->itemid  = $item->id;
			$auto_relations[0]->fieldid = $field->id;
			$category = null;
			$auto_rel_btn = flexicontent_html::addbutton(
				$field->parameters, $category, $auto_relate_menu_itemid, $_submit_text, $auto_relations, $_show_to_unauth
			);
		}
		
		
		// *****************************
		// Finally, create the item list
		// *****************************
		
		if ( !$display_total || $total_show_list ) {
			$add_before = $auto_rel_btn && ($auto_relate_position == 0 || $auto_relate_position == 2);
			$add_after  = $auto_rel_btn && ($auto_relate_position == 1 || $auto_relate_position == 2);
			
			$field->{$prop} .= ''
				.($add_before ? $auto_rel_btn : '')
				.FlexicontentFields::getItemsList($field->parameters, $_itemids_catids, $isform=0, @ $reverse_field, $field, $item)
				.($add_after ? $auto_rel_btn : '');
		} else if ($auto_rel_btn) {
			$field->{$prop} .= $auto_rel_btn;
		}
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
	
}
