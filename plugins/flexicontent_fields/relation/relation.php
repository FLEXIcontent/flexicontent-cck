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

jimport('cms.plugin.plugin');
use Joomla\String\StringHelper;

class plgFlexicontent_fieldsRelation extends JPlugin
{
	var $task_callable = array('getCategoryItems');
	
	static $field_types = array('relation');
	
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function __construct( &$subject, $params )
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
		if ( !in_array($field->field_type, self::$field_types) ) return;
		$field->label = JText::_($field->label);
		
		// Initialize framework objects and other variables
		$db   = JFactory::getDBO();
		$user = JFactory::getUser();
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		
		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		
		$field->html = '';
		
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;
		
		// Case of autorelated item
		$autorelation_itemid = JRequest::getInt('autorelation_'.$field->id);

		if ( $autorelation_itemid )
		{
			$field->html = 'You can not auto-relate items using a relation field, please add a relation reverse field, and select to reverse this field';
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
		$_itemids = array();
		foreach($field->value as $i => $val)
		{
			list ($itemid,$catid) = explode(":", $val);
			$itemid = (int) $itemid;
			$catid  = (int) $catid;
			$_itemids_catids[$itemid] = new stdClass();
			$_itemids_catids[$itemid]->itemid = $itemid;
			$_itemids_catids[$itemid]->catid  = $catid;
			$_itemids_catids[$itemid]->value  = $val;
			$_itemids[] = $itemid;
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
		$required 	= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';
		$selected_items_label = $field->parameters->get( 'selected_items_label', 'FLEXI_RIFLD_SELECTED_ITEMS_LABEL' ) ;
		
		
		// ***********************************************
		// Get & check Global category related permissions
		// ***********************************************
		
		require_once (JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
		$viewallcats	= FlexicontentHelperPerm::getPerm()->ViewAllCats;
		
		
		// ****************************************************
		// Calculate categories to use for retrieving the items
		// ****************************************************
		
		$allowed_cats = $disallowed_cats = false;
		
		// Get user allowed categories
		$usercats = FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed=array('core.create', 'core.edit', 'core.edit.own'), $require_all=false, $check_published = true);
		//$usercats = FlexicontentHelperPerm::returnAllCats($check_published=true, $specific_catids=null);
		
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
		
		
		// *****************************************************
		// Item retrieving query ... put together and execute it
		// *****************************************************
		if ( count($_itemids) )
		{
			$query = 'SELECT i.title, i.id, i.catid, i.state, i.alias'
				.' FROM #__content AS i '
				.' WHERE i.id IN (' . implode(',', $_itemids) . ')'
				;
			$db->setQuery($query);
			$items_arr = $db->loadObjectList();
		}
		else $items_arr = array();
		
		
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
		
		
		// *****************************************
		// Create field's HTML display for item form
		// *****************************************
		
		static $common_css_js_added = false;
	  if ( !$common_css_js_added )
	  {
			$common_css_js_added = true;
			flexicontent_html::loadFramework('select2');
			
			$css = ''
				.'#s2id_'.$elementid.' .select2-search-field { display: none !important; }'
				.'#s2id_'.$elementid.'.select2-container-multi { max-width: 70% !important; }'
				;
			if ($css) $document->addStyleDeclaration($css);
		}
		
    // The split up the items
		$items_options_select = '';
		$state_shortname = array(1=>'P', 0=>'U', -1=>'A', -3=>'PE', -4=>'OQ', -5=>'IP');
		foreach($items_arr as $itemdata) {
			$itemtitle = (StringHelper::strlen($itemdata->title) > $maxtitlechars) ? StringHelper::substr($itemdata->title,0,$maxtitlechars) . "..." : $itemdata->title;
			if ($prepend_item_state) {
				$statestr = "[". @$state_shortname[$itemdata->state]."] ";
				$itemtitle = $statestr.$itemtitle." ";
			}
			$itemid = $itemdata->id;
			$items_options_select .= '<option selected="selected" value="'.$_itemids_catids[$itemid]->value.'" >'.$itemtitle.'</option>'."\n";
		}
		
		$cat_selected = count($allowedtree)==1 ? reset($allowedtree) : '';
		$cat_selecor_box_style = count($allowedtree)==1 ? 'style="display:none;" ' :'';
		
		$_cat_selector = flexicontent_cats::buildcatselect(
			$allowedtree, $elementid.'_cat_selector', $catvals=($cat_selected ? $cat_selected->id : ''),
			$top=JText::_( 'FLEXI_SELECT' ), // (adds first option "please select") Important otherwise single entry in select cannot initiate onchange event
			' class="use_select2_lib '.$elementid.'_cat_selector" ',
			$check_published = true, $check_perms = true,
			$actions_allowed=array('core.create', 'core.edit', 'core.edit.own'), $require_all=false,
			$skip_subtrees=array(), $disable_subtrees=array()
		);
		
		$field->html .= '
		<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">
		
			<div class="'.$input_grp_class.' fc-xpended-row fcrelation-field-category-selector" '.$cat_selecor_box_style.'>
				<label class="'.$add_on_class.' fc-lbl cats-selector-lbl" for="'.$elementid.'_cat_selector">'.JText::_( 'FLEXI_CATEGORY' ).'</label>
				'.$_cat_selector.'
			</div>
			
			<div class="'.$input_grp_class.' fc-xpended-row fcrelation-field-item-selector">
				<label class="'.$add_on_class.' fc-lbl item-selector-lbl" for="'.$elementid.'_item_selector">'.JText::_( 'FLEXI_RIFLD_ITEMS' ).'</label>
				<select id="'.$elementid.'_item_selector" name="'.$elementid.'_item_selector" class="use_select2_lib" onchange="return fcrelation_field_'.$elementid.'_add_related(this);">
					<option value="">-</option>
				</select>
			</div>
			
			<div class="'.$input_grp_class.' fc-xpended-row fcrelation-field-selected-items">
				<label class="'.$add_on_class.' fc-lbl selected-items-lbl" for="'.$elementid.'">'.JText::_($selected_items_label).'</label>
				<select id="'.$elementid.'" name="'.$fieldname.'[]" multiple="multiple" class="use_select2_lib fc_no_js_attach '.$required.'" '.$size.' >
					'.$items_options_select.'
				</select>
			</div>
			
		</div>
		';
		
		$js = "

function fcrelation_field_".$elementid."_add_related(el)
{
	var
		item_selector = jQuery(el),
		item_id = parseInt( item_selector.val() ),
		item_title = item_selector.find('option:selected').text();
	
	if ( !item_id ) return false;
	
	var
		cat_selector = jQuery('#".$elementid."_cat_selector'),
		cat_id = cat_selector.val();
	
	var itemid_catid = item_id+':'+cat_id;
	window.console.log(itemid_catid);
	
	var selitems_selector = jQuery('#".$elementid."');
	selitems_selector.append(jQuery('<option>', {
		value: itemid_catid,
		text: item_title,
		selected: 'selected'
	}));
	
	setTimeout(function() {
		item_selector.val('').trigger('change');   // Clear item selection
		jQuery('#".$elementid."').trigger('change');
	}, 50);
	
	return true;
}

jQuery(document).ready(function()
{
	setTimeout(function() {
		jQuery('#".$elementid."').select2('destroy').select2({
			formatNoMatches: function() {
				return '';
			},
			dropdownCssClass: 'select2-hidden',
			minimumResultsForSearch: -1
		});
	}, 100);
	
	jQuery('#".$elementid."').change(function()
	{
		var selitems_selector = jQuery('#".$elementid."');
		setTimeout(function() {
			var non_selected = selitems_selector.find('option:not(:selected)');
			if (non_selected.length) {
				non_selected.remove();
				selitems_selector.trigger('change');
			}
		}, 50);
		return true;
	});
	
	
	jQuery('#".$elementid."_cat_selector').change(function()
	{
		var cat_selector = jQuery(this);
		var ".$elementid."_cat_selector_val = cat_selector.val();
		var catid = parseInt(cat_selector.val());
		
		var item_selector = jQuery('#".$elementid."_item_selector');
		if (!catid) {
			item_selector.empty();
			item_selector.append('<option value=\"\">-</option>');
			item_selector.val('').trigger('change');  // trigger change event to update select2 display
			item_selector.show();
			return;
		}
		
		var sel2_item_selector = jQuery('#s2id_".$elementid."_item_selector');
		sel2_item_selector.hide();
		
		var loading = jQuery('<div class=\"fc_loading_msg\" style=\"position:absolute; background-color:transparent;\"></div>');
		loading.insertAfter(sel2_item_selector);
		
		jQuery.ajax({
			type: 'POST',
			url: 'index.php?option=com_flexicontent&tmpl=component&format=raw',
			dataType: 'json',
			data: {
				task: 'call_extfunc',
				omethod: 'html', /* unused */
				exttype: 'plugins',
				extfolder: 'flexicontent_fields',
				extname: 'relation',
				extfunc: 'getCategoryItems',
				field_id: ".$field->id.","
			.($item->id ? "
				item_id: ".$item->id.",
			" : "
				type_id: ".$item->type_id.",
				lang_code: '".$item->language."',"
			)."
				catid: catid
			}
		}).done( function(data) {
			//window.console.log ('Got data for:' + cat_selector.attr('id'));
			item_selector.empty();
			
			if (data=='')                   item_selector.append('<option value=\"\">".JText::_('FLEXI_RIFLD_ERROR')."</option>');
			else if (data.error!='')        item_selector.append('<option value=\"\">'+data.error+'</option>');
			else if (!data.options.length)  item_selector.append('<option value=\"\">".JText::_('FLEXI_RIFLD_NO_ITEMS')."</option>');
			else {
				item_selector.append('<option value=\"\">'+'- ".JText::_('FLEXI_RIFLD_ADD_ITEM', true)."'+' -</option>');
				var item;
				for(var i=0; i<data.options.length; i++)
				{
					item = data.options[i];
					item_selector.append(jQuery('<option>', {
						value: (item.item_id+':'+catid),
						text: item.item_title
					}));
				}
			}
			
			// Trigger change event to update select2 display
			item_selector.val('').trigger('change');
			
			// Remove loading animation and show item selector
			sel2_item_selector.next().remove();
			sel2_item_selector.show();
		});
		
	});
	". ( count($allowedtree)==1 ? "jQuery('#".$elementid."_cat_selector').trigger('change');" : "" ) . "
	
});";
		
		$document->addScriptDeclaration( $js );
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$field->{$prop} = '';
		$values = $values ? $values : $field->value;

		$user = JFactory::getUser();
		$show_total_only     = $field->parameters->get('show_total_only', 0);
		$total_show_auto_btn = $field->parameters->get('total_show_auto_btn', 0);
		$total_show_list     = $field->parameters->get('total_show_list', 0);


		// *******************************************
		// Check for special display : total info only
		// *******************************************
		
		if ($prop=='display_total')
		{
			$display_total = true;
		}
		else if ( $show_total_only==1 || ($show_total_only == 2 && count($values)) )
		{
			$app = JFactory::getApplication();
			$view = JRequest::getVar('view');
			$option = JRequest::getVar('option');
			$isItemsManager = $app->isAdmin() && $view=='items' && $option=='com_flexicontent';
			
			$total_in_view = $field->parameters->get('total_in_view', array('backend'));
			$total_in_view = FLEXIUtilities::paramToArray($total_in_view);
			$display_total = ($isItemsManager && in_array('backend', $total_in_view)) || in_array($view, $total_in_view);
		}
		else
		{
			$display_total = false;
		}


		// ***********************************************************
		// Create total info and terminate if not adding the item list
		// ***********************************************************
		
		if ($display_total)
		{
			$total_append_text = $field->parameters->get('total_append_text', '');
			$field->{$prop} .= '<span class="fcrelation_field_total">'. count($values) .' '. $total_append_text .'</span>';
			
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
			$values = ( $field_data = @unserialize($values) ) ? $field_data : $values;

			// set upper limit as $values array length
			$itemcount = count ($values);

			// change upper limit if itemcount is set and error checked
			if (is_numeric($field->parameters->get( 'itemcount', 0)) &&  
				$field->parameters->get( 'itemcount', 0) > 0 && 
				$field->parameters->get( 'itemcount', 0) < $itemcount
			) {
				$itemcount = $field->parameters->get( 'itemcount', 0);
			}

			// Limit list to desired max # items
			$_itemids_catids = array();

			for($i = 0; $i < $itemcount; $i++)
			{
				list ($itemid,$catid) = explode(":", $values[$i]);
				$_itemids_catids[$itemid] = new stdClass();
				$_itemids_catids[$itemid]->itemid = $itemid;
				$_itemids_catids[$itemid]->catid = $catid;
				$_itemids_catids[$itemid]->value  = $values[$i];
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
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$acclvl = (int) $field->parameters->get('auto_relate_acclvl', 1);
			$has_acclvl = in_array($acclvl, $aid_arr);
			
			if ($has_acclvl)
			{
				$_btn_text = new stdClass();
				$_btn_text->title = $field->parameters->get( 'auto_relate_submit_title', 'FLEXI_RIFLD_SUBMIT_NEW_RELATED');
				$_btn_text->tooltip = $field->parameters->get( 'auto_relate_submit_text', 'FLEXI_RIFLD_SUBMIT_NEW_RELATED_TIP');
	
				$auto_relations[0] = new stdClass();
				$auto_relations[0]->itemid  = $item->id;
				$auto_relations[0]->fieldid = $field->id;
	
				$category = null;
				$_show_to_unauth = $field->parameters->get( 'auto_relate_show_to_unauth', 0);
	
				$auto_rel_btn = flexicontent_html::addbutton(
					$field->parameters, $category, $auto_relate_menu_itemid, $_btn_text, $auto_relations, $_show_to_unauth
				);
			}
		}


		// *************************************************************************
		// Finally, create and add the item list if user has the needed access level
		// *************************************************************************

		$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
		$acclvl = (int) $field->parameters->get('itemslist_acclvl', 1);
		$has_acclvl = in_array($acclvl, $aid_arr);
		
		if (!$has_acclvl)
		{
			$field->{$prop} = $auto_rel_btn;   // Only show the autorelate button
		}

		else if ( !$display_total || $total_show_list )
		{
			$add_before = $auto_rel_btn && ($auto_relate_position == 0 || $auto_relate_position == 2);
			$add_after  = $auto_rel_btn && ($auto_relate_position == 1 || $auto_relate_position == 2);
			
			$field->{$prop} .= ''
				.($add_before ? $auto_rel_btn : '')
				.FlexicontentFields::getItemsList($field->parameters, $_itemids_catids, $isform=0, @ $reverse_field, $field, $item)
				.($add_after ? $auto_rel_btn : '');
		}

		else if ($auto_rel_btn)
		{
			$field->{$prop} .= $auto_rel_btn;
		}
	}



	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
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
	
	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		// No special SQL query, default query is enough since index data were formed as desired, during indexing
		$indexed_elements = true;
		FlexicontentFields::createFilter($filter, $value, $formName, $indexed_elements);
	}
	
	
	function onDisplayFilter(&$filter, $value='', $formName='adminForm', $isSearchView=0)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;

		// Create order clause dynamically based on the field settings
		$order = $filter->parameters->get( 'orderby', 'alpha' );
		$orderby = flexicontent_db::buildItemOrderBy(
			$filter->parameters,
			$order, $request_var='', $config_param='',
			$item_tbl_alias = 'ct', $relcat_tbl_alias = 'rel',
			$default_order='', $default_order_dir='', $sfx='_form', $support_2nd_lvl=false
		);
		
		// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
		// partial SQL clauses
		$filter->filter_valuesselect = ' ct.id AS value, ct.title AS text';
		$filter->filter_valuesfrom   = null;  // use default
		$filter->filter_valuesjoin   = ' JOIN #__content AS ct ON ct.id = CAST(fi.value AS UNSIGNED) AND ct.state = 1 AND ct.publish_up < UTC_TIMESTAMP() AND (ct.publish_down = "0000-00-00 00:00:00" OR ct.publish_down > UTC_TIMESTAMP())';
		$filter->filter_valueswhere  = null;  // use default
		// full SQL clauses
		$filter->filter_groupby = ' GROUP BY CAST(fi.value AS UNSIGNED) '; // * will be be appended with , fi.item_id
		$filter->filter_having  = null;  // use default
		$filter->filter_orderby = $orderby; // use field ordering setting
		
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value, $return_sql=true)
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->filter_colname     = ' CAST(rel.value AS UNSIGNED)';
		$filter->filter_valuesjoin  = null;   // use default
		$filter->filter_valueformat = null;   // use default
		
		return FlexicontentFields::getFiltered($filter, $value, $return_sql);
	}
	
	
	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->isindexed = true;
		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		if ($post===null) {
			$values = null;
			$field->field_valuesselect = ' CAST(fi.value AS UNSIGNED) AS value_id, ct.title AS value';
			$field->field_valuesjoin   = ' JOIN #__content AS ct ON ct.id = CAST(fi.value AS UNSIGNED)';
			$field->field_groupby      = ' GROUP BY CAST(fi.value AS UNSIGNED) ';
		} else if (!empty($post)) {
			$_ids = array();
			foreach($post as $_id) $_ids[] = (int)$_id;  // convert itemID:catID to itemID
			$db = JFactory::getDBO();
			$query = 'SELECT i.id AS value_id, i.title AS value FROM #__content AS i WHERE i.id IN ('.implode(',', $_ids).')';
			$db->setQuery($query);
			$_values = $db->loadAssocList();
			$values = array();
			foreach ($_values as $v)  $values[$v['value_id']] = $v['value'];
		}
		
		//JFactory::getApplication()->enqueueMessage('ADV: '.print_r($values, true), 'notice');
		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		if ($post===null) {
			$values = null;
			$field->field_valuesselect = ' CAST(fi.value AS UNSIGNED) AS value_id, ct.title AS value';
			$field->field_valuesjoin   = ' JOIN #__content AS ct ON ct.id = CAST(fi.value AS UNSIGNED)';
			$field->field_groupby      = ' GROUP BY CAST(fi.value AS UNSIGNED) ';
		} else if (!empty($post)) {
			$_ids = array();
			foreach($post as $_id) $_ids[] = (int)$_id;  // convert itemID:catID to itemID 
			$db = JFactory::getDBO();
			$query = 'SELECT i.id AS value_id, i.title AS value FROM #__content AS i WHERE i.id IN ('.implode(',', $_ids).')';
			$db->setQuery($query);
			$_values = $db->loadAssocList();
			$values = array();
			foreach ($_values as $v)  $values[$v['value_id']] = $v['value'];
		}
		
		//JFactory::getApplication()->enqueueMessage('BAS: '.print_r($values, true), 'notice');
		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	
	// Method called via AJAX to get dependent values
	function getCategoryItems()
	{
		// Get API objects / data
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$user   = JFactory::getUser();
		
		// Get Access Levels of user
		$uacc = array_flip(JAccess::getAuthorisedViewLevels($user->id));
		
		
		// Get request variables
		$field_id = $jinput->get('field_id', 0, 'int');
		$item_id  = $jinput->get('item_id',  0, 'int');
		$type_id  = $jinput->get('type_id',  0, 'int');
		$lang_code= $jinput->get('lang_code',  0, 'cmd');
		$catid    = $jinput->get('catid',    0, 'int');
		
		
		// Basic checks
		$response = array();
		$response['error'] = '';
		$response['options'] = array();
		
		if (!$field_id)    $response['error'] = 'Invalid field_id';
		else if (!$catid)  $response['error'] = 'Invalid catid';
		
		if ( $response['error'] )
		{
			exit( json_encode($response) );
		}
		
		
		
		// ********************
		// Load and check field
		// ********************
		
		$field = JTable::getInstance( $_type = 'flexicontent_fields', $_prefix = '', $_config = array() );
		
		if ( !$field->load( $field_id ) )           $response['error'] = 'relation field not found';
		else if ( $field->field_type!='relation' )  $response['error'] = 'relation field is not a relation field';
		else if ( !isset($uacc[$field->access]) )   $response['error'] = 'relation field has non-allowed access level';
		
		if ( $response['error'] )
		{
			exit( json_encode($response) );
		}
		
		
		// *******************
		// Load and check item
		// *******************
		
		$item = JTable::getInstance( $_type = 'flexicontent_items', $_prefix = '', $_config = array() );
		if ( !$item_id )
		{
			$item->type_id = $type_id;
			$item->language = $lang_code;
			$item->created_by = $user->id;
		}
		else if ( !$item->load( $item_id ) )       $response['error'] = 'content item not found';
		else if ( !isset($uacc[$item->access]) )   $response['error'] = 'content item has non-allowed access level';
		
		if ( $response['error'] )
		{
			exit( json_encode($response) );
		}
		
		
		// ************************
		// Load field configuration
		// ************************
		
		FlexicontentFields::loadFieldConfig($field, $item);
		$field->item_id = $item_id;
		
		// Some needed parameters
		$maxtitlechars 	= $field->parameters->get( 'maxtitlechars', 40 ) ;
		
		
		// ***********************************************
		// Get & check Global category related permissions
		// ***********************************************
		
		require_once (JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
		$viewallcats	= FlexicontentHelperPerm::getPerm()->ViewAllCats;
		
		
		// ****************
		// SCOPE PARAMETERS
		// ****************
		
		// categories scope parameters
		$method_cat = $field->parameters->get('method_cat', 1);
		$usesubcats = $field->parameters->get('usesubcats', 0 );
		
		$catids = array($catid);  //$field->parameters->get('catids');
		
		// types scope parameters
		$method_types = $field->parameters->get('method_types', 1);
		
		$types = $field->parameters->get('types');
		if ( empty($types) )							$types = array();
		else if ( ! is_array($types) )		$types = !FLEXI_J16GE ? array($types) : explode("|", $types);
		
		// other limits of scope parameters
		$samelangonly  = $field->parameters->get( 'samelangonly', 1 );
		$onlypublished = $field->parameters->get( 'onlypublished', 1 );
		$ownedbyuser   = $field->parameters->get( 'ownedbyuser', 0 );
		
		
		// ****************************************************
		// Calculate categories to use for retrieving the items
		// ****************************************************
		
		$allowed_cats = $disallowed_cats = false;
		
		// Get user allowed categories
		$usercats = FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed=array('core.create', 'core.edit', 'core.edit.own'), $require_all=false, $check_published = true);
		//$usercats = FlexicontentHelperPerm::returnAllCats($check_published=true, $specific_catids=null);
		
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
				echo json_encode( array('error' => JText::_('FLEXI_CANNOT_EDIT_FIELD') .' - '. JText::_('FLEXI_NO_ACCESS_TO_USE_CONFIGURED_CATEGORIES')) );
				exit;
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
				echo json_encode( array('error' => JText::_('FLEXI_CANNOT_EDIT_FIELD') .' - '. JText::_('FLEXI_NO_ACCESS_TO_USE_ANY_CATEGORIES')) );
				exit;
			}
		}
		
		
		// TYPE SCOPE
		if ( ($method_types == 2 || $method_types == 3) && ( !count($types) || empty($types[0]) ) ) {
			echo json_encode( array('error' => 'Content Type scope is set to include/exclude but no Types are selected in field configuration, please set to "ALL" or select types to include/exclude') );
			exit;
		}
		if ($method_types == 2)       $where[] = ' ie.type_id NOT IN (' . implode(',', $types) . ')';   // exclude method
		else if ($method_types == 3)  $where[] = ' ie.type_id IN (' . implode(',', $types) . ')';       // include method
		
		// OTHER SCOPE LIMITS
		if ($samelangonly)  $where[] = !$item->language || $item->language=='*' ? " ie.language='*' " : " (ie.language='{$item->language}' OR ie.language='*') ";
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
		$db = JFactory::getDBO();
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
		
		// Some configuration
		$prepend_item_state = $field->parameters->get( 'prepend_item_state', 1 ) ;
		
		foreach($items_arr as $itemdata)
		{
			$itemtitle = (StringHelper::strlen($itemdata->title) > $maxtitlechars) ? StringHelper::substr($itemdata->title,0,$maxtitlechars) . "..." : $itemdata->title;
			if (0 && $prepend_item_state) {
				$statestr = "[". @$state_shortname[$itemdata->state]."] ";
				$itemtitle = $statestr.$itemtitle." ";
			}
			$itemcat_arr = explode(",", $itemdata->catlist);
			$itemid = $itemdata->id;
			
			$response['options'][] = array('item_id'=>$itemid, 'item_title'=>$itemtitle);
		}
		
		/*echo "<pre>";
		print_r($response);
		echo "</pre>";
		exit;*/
		
		// Output the field
		echo json_encode($response);
		exit;
	}
}
