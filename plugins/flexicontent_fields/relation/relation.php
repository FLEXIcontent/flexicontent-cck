<?php
/**
 * @package         FLEXIcontent
 * @version         3.2
 * 
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            http://www.flexicontent.com
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsRelation extends FCField
{
	static $field_types = array('relation', 'relation_reverse');
	var $task_callable = array('getCategoryItems');  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}
	
	
	
	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		$field->label = JText::_($field->label);
		
		// Initialize framework objects and other variables
		$db   = JFactory::getDbo();
		$user = JFactory::getUser();
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		
		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';


		$field->html = '';

		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;


		// ***
		// *** Case of autorelated item
		// ***

		$autorelation_itemid = JFactory::getApplication()->input->get('autorelation_'.$field->id, 0, 'int');

		if ( $autorelation_itemid )
		{
			$field->html = '<div class="alert alert-warning">You can not auto-relate items using a relation field, please add a relation reverse field, and select to reverse this field</div>';
			return;
		}


		// ***
		// *** Initialise values and split them into: (a) item ids and (b) category ids
		// ***

		if (!$field->value)
		{
			$field->value = array();
		}
		else
		{
			// Compatibility with old values, we no longer serialize all values to one, this way the field can be reversed !!!
			if ( !is_array($field->value) )
			{
				$field->value = array($field->value);
			}
			$array = $this->unserialize_array(reset($field->value), $force_array=false, $force_value=false);
			$field->value = $array ?: $field->value;
		}


		$related_items = array();
		$_itemids = array();
		foreach($field->value as $i => $val)
		{
			list ($itemid,$catid) = explode(":", $val);
			$itemid = (int) $itemid;
			$catid  = (int) $catid;
			$related_items[$itemid] = new stdClass();
			$related_items[$itemid]->itemid = $itemid;
			$related_items[$itemid]->catid  = $catid;
			$related_items[$itemid]->value  = $val;
			$_itemids[] = $itemid;
		}


		// ***
		// *** EDITING PARAMETERS
		// ***
		
		// some parameters shortcuts
		$size				= $field->parameters->get( 'size', 12 ) ;
		$size	 	= $size ? ' size="'.$size.'"' : '';
		$prepend_item_state = $field->parameters->get( 'prepend_item_state', 1 ) ;
		$maxtitlechars 	= $field->parameters->get( 'maxtitlechars', 40 ) ;
		$required 	= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';
		$selected_items_label = $field->parameters->get( 'selected_items_label', 'FLEXI_RIFLD_SELECTED_ITEMS_LABEL' ) ;
		$selected_items_sortable = $field->parameters->get( 'selected_items_sortable', 0 ) ;


		// ***
		// *** Item retrieving query ... put together and execute it
		// ***

		if ( count($_itemids) )
		{
			$query = 'SELECT i.title, i.id, i.catid, i.state, i.alias'
				.' FROM #__content AS i '
				.' WHERE i.id IN (' . implode(',', $_itemids) . ')'
				.' ORDER BY FIELD(i.id, '. implode(',', $_itemids) .')'
				;
			$db->setQuery($query);
			$items_arr = $db->loadObjectList();
		}
		else $items_arr = array();
		
		
		// ***
		// *** Create category tree to use for selecting related items
		// ***

		// Get categories without filtering
		require_once(JPATH_ROOT.DS."components".DS."com_flexicontent".DS."classes".DS."flexicontent.categories.php");
		$tree = flexicontent_cats::getCategoriesTree();

		// Get allowed categories
		$allowed_cats = self::getAllowedCategories($field);
		if (empty($allowed_cats))
		{
			$field->html = JText::_('FLEXI_CANNOT_EDIT_FIELD') .': <br/> '. JText::_('FLEXI_NO_ACCESS_TO_USE_CONFIGURED_CATEGORIES');
			return;
		}

		// Add categories that will be used by the category selector
		foreach ($allowed_cats as $catid)
		{
			$allowedtree[$catid] = $tree[$catid];
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


		// ***
		// *** Create the selected items field (items selected as 'related')
		// ***

		$items_options_select = '';
		$state_shortname = array(1=>'P', 0=>'U', -1=>'A', -3=>'PE', -4=>'OQ', -5=>'IP');
		foreach($items_arr as $itemdata)
		{
			$itemtitle = (StringHelper::strlen($itemdata->title) > $maxtitlechars) ? StringHelper::substr($itemdata->title,0,$maxtitlechars) . "..." : $itemdata->title;
			if ($prepend_item_state)
			{
				$statestr = "[". @$state_shortname[$itemdata->state]."] ";
				$itemtitle = $statestr.$itemtitle." ";
			}
			$itemid = $itemdata->id;
			$items_options_select .= '<option selected="selected" value="'.$related_items[$itemid]->value.'" >'.$itemtitle.'</option>'."\n";
		}


		// ***
		// *** Add needed JS
		// ***

		static $common_css_js_added = false;
	  if ( !$common_css_js_added )
	  {
			$common_css_js_added = true;
			flexicontent_html::loadFramework('select2');
			
			$css = '';
			if ($css) $document->addStyleDeclaration($css);
		}


		// ***
		// *** Create field's HTML display for item form
		// ***

		$_classes = 'use_select2_lib fc_select2_no_check fc_select2_noselect' . $required . ($selected_items_sortable ? ' fc_select2_sortable' : '');
		$field->html .= '
		<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">
		
			<div class="'.$input_grp_class.' fc-xpended-row fcrelation-field-category-selector" '.$cat_selecor_box_style.'>
				<label class="' . $add_on_class . ' fc-lbl cats-selector-lbl" for="'.$elementid.'_cat_selector">'.JText::_( 'FLEXI_CATEGORY' ).'</label>
				'.$_cat_selector.'
			</div>
			
			<div class="'.$input_grp_class.' fc-xpended-row fcrelation-field-item-selector">
				<label class="' . $add_on_class . ' fc-lbl item-selector-lbl" for="'.$elementid.'_item_selector">'.JText::_( 'FLEXI_RIFLD_ITEMS' ).'</label>
				<select id="'.$elementid.'_item_selector" name="'.$elementid.'_item_selector" class="use_select2_lib" onchange="return fcrelation_field_'.$elementid.'_add_related(this);">
					<option value="">-</option>
				</select>
			</div>
			
			<div class="'.$input_grp_class.' fc-xpended-row fcrelation-field-selected-items">
				<label class="' . $add_on_class . ' fc-lbl selected-items-lbl" for="'.$elementid.'">'.JText::_($selected_items_label).'</label>
				<select id="'.$elementid.'" name="'.$fieldname.'[]" multiple="multiple" class="'.$_classes.'" '.$size.' >
					'.$items_options_select.'
				</select>
				'.($selected_items_sortable ? '
				<span class="add-on"><span class="icon-info hasTooltip" title="'.JText::_('FLEXI_FIELD_ALLOW_SORTABLE_INFO').'"></span>' . JText::_('FLEXI_ORDER') . '</span>' : '').'
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

		// Remove any previous error message
		item_selector.parent().find('.fc-relation-field-error').remove();

		// Check for empty category
		if (!catid)
		{
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
			else if (data.error!='')        item_selector.append('<option value=\"\">-</option>');
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
			
			// Remove loading animation
			sel2_item_selector.next().remove();

			// Show the item selector or display the error message
			if (data && data.error!='')
				jQuery('<span class=\"add-on fc-relation-field-error\"> <span class=\"icon-warning\"></span> '+data.error+'</span>').insertAfter(item_selector);
			else
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
		if ( !in_array($field->field_type, static::$field_types) ) return;
		$field->label = JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		$values = $values ? $values : $field->value;
		if ( !is_array($values) )
		{
			$values = array($values);
		}


		// ***
		// *** Calculate access for item list and auto relation button
		// ***

		static $has_itemslist_access = array();
		if ( !isset($has_itemslist_access[$field->id]) )
		{
			$aid_arr = JAccess::getAuthorisedViewLevels(JFactory::getUser()->id);
			$acclvl = (int) $field->parameters->get('itemslist_acclvl', 1);
			$has_itemslist_access[$field->id] = in_array($acclvl, $aid_arr);
		}

		static $has_auto_relate_access = array();
		if ( !isset($has_auto_relate_access[$field->id]) )
		{
			$aid_arr = JAccess::getAuthorisedViewLevels(JFactory::getUser()->id);
			$acclvl = (int) $field->parameters->get('auto_relate_acclvl', 1);
			$has_auto_relate_access[$field->id] = in_array($acclvl, $aid_arr);
		}


		// ***
		// *** Decide what to display
		// ***  - total information
		// ***  - item list
		// ***  - auto relation button
		// ***

		$disp = new stdClass();
		$HTML = new stdClass();

		// Total information
		$show_total_only     = (int) $field->parameters->get('show_total_only', 0);
		$total_show_auto_btn = $field->field_type != 'relation' ? 0 : (int) $field->parameters->get('total_show_auto_btn', 0);
		$total_show_list     = $field->field_type != 'relation' ? 0 : (int) $field->parameters->get('total_show_list', 0);
		
		if ($prop=='display_total')  // Explicitly requested
		{
			$disp->total_info = true;
		}
		elseif ( $show_total_only==1 || ($show_total_only == 2 && (count($values) || $field->field_type == 'relation_reverse')) )  // For relation reverse we will count items inside the layout
		{
			$app = JFactory::getApplication();
			$option = $app->input->get('option', '', 'cmd');
			$realview = $app->input->get('view', 'item', 'cmd');
			$view = $app->input->get('flexi_callview', $realview, 'cmd');
			$isItemsManager = $app->isAdmin() && $realview=='items' && $option=='com_flexicontent';

			$total_in_view = $field->parameters->get('total_in_view', array('backend'));
			$total_in_view = FLEXIUtilities::paramToArray($total_in_view);
			$disp->total_info = ($isItemsManager && in_array('backend', $total_in_view)) || in_array($view, $total_in_view);
		}
		else
		{
			$disp->total_info = false;
		}

		// Auto-relate submit button
		$submit_related_curritem    = $field->parameters->get( 'auto_relate_curritem', 0);
		$submit_related_menu_itemid = $field->parameters->get( 'auto_relate_menu_itemid', 0);
		$submit_related_position    = $field->parameters->get( 'auto_relate_position', 0);

		$disp->submit_related_btn = $submit_related_curritem && $submit_related_menu_itemid
			&& $has_auto_relate_access[$field->id] && (!$disp->total_info || $total_show_auto_btn);

		// Item list
		$disp->item_list = $has_itemslist_access[$field->id] && (!$disp->total_info || $total_show_list);


		// ***
		// *** Prepare item list data for rendering the related items list
		// ***

		$reverse_field_id = $field->parameters->get('reverse_field', 0);

		if ($field->field_type == 'relation_reverse')
		{
			// Check that relation field to be reversed was configured
			if ( !$reverse_field_id )
			{
				$field->{$prop} = '<div class="alert alert-warning">'.JText::_('FLEXI_RIFLD_NO_FIELD_SELECTED_TO_BE_REVERSED').'</div>';
				return;
			}

			// Always ignore passed items, the DB query will determine the items
			$related_items = null;
		}
		else  // $field->field_type == 'relation')
		{
			// Compatibility with old values, we no longer serialize all values to one, this way the field can be reversed !!!
			$array = $this->unserialize_array(reset($values), $force_array=false, $force_value=false);
			$values = $array ?: $values;

			// set upper limit as $values array length
			$itemcount = count($values);

			// change upper limit if itemcount is set and error checked
			if (is_numeric($field->parameters->get( 'itemcount', 0)) &&  
				$field->parameters->get( 'itemcount', 0) > 0 && 
				$field->parameters->get( 'itemcount', 0) < $itemcount
			) {
				$itemcount = $field->parameters->get( 'itemcount', 0);
			}

			// Limit list to desired max # items
			$related_items = array();

			for($i = 0; $i < $itemcount; $i++)
			{
				list ($itemid,$catid) = explode(":", $values[$i]);
				$related_items[$itemid] = new stdClass();
				$related_items[$itemid]->itemid = $itemid;
				$related_items[$itemid]->catid = $catid;
				$related_items[$itemid]->value  = $values[$i];
			}
		}


		// ***
		// *** Get related items data and their display HTML as an array of items
		// *** NOTE: this is not moved to layout because in future it could be optimized
		// ***       to retrieve related items for all items in category view with single query
		// ***

		$options = new stdClass();
		if ($disp->item_list || $disp->total_info)
		{
			// 0: return string with related items HTML, 1: return related items array,
			// 2: same as 1 but also means do not create HTML display, 3: same as 2 but also do not get any item data
			$options->return_items_array = $disp->item_list ? 1 : 3;

			// Override the item list HTML parameter ... with the one meant to be used when showing total
			if ($disp->total_info && $field->parameters->get('total_relitem_html', null))
			{
				$field->parameters->set('relitem_html_override', 'total_relitem_html');
			}

			// Get related items data and also create the item's HTML display per item (* see above)
			$related_items = FlexicontentFields::getItemsList($field->parameters, $related_items, $field, $item, $options);
		}


		// ***
		// *** Create output
		// ***

		// Prefix - Suffix - Separator parameters - Other common parameters
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);

		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';

		// Create field's HTML, using layout file
		$field->{$prop} = '';
		include(self::getViewPath($field->field_type, $viewlayout));
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if(!is_array($post) && !strlen($post)) return;
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;
		
		// No special SQL query, default query is enough since index data were formed as desired, during indexing
		$indexed_elements = true;
		FlexicontentFields::createFilter($filter, $value, $formName, $indexed_elements);
	}
	
	
	function onDisplayFilter(&$filter, $value='', $formName='adminForm', $isSearchView=0)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

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
		$filter->filter_valuesjoin   = ' JOIN #__content AS ct ON ct.id = fi.value_integer AND ct.state = 1 AND ct.publish_up < UTC_TIMESTAMP() AND (ct.publish_down = "0000-00-00 00:00:00" OR ct.publish_down > UTC_TIMESTAMP())';
		$filter->filter_valueswhere  = null;  // use default
		// full SQL clauses
		$filter->filter_groupby = ' GROUP BY fi.value_integer '; // * will be be appended with , fi.item_id
		$filter->filter_having  = null;  // use default
		$filter->filter_orderby = $orderby; // use field ordering setting
		
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value, $return_sql=true)
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($filter->field_type, static::$field_types) ) return;
		
		$filter->filter_colname     = ' rel.value_integer';
		$filter->filter_valuesjoin  = null;   // use default
		$filter->filter_valueformat = null;   // use default
		
		return FlexicontentFields::getFiltered($filter, $value, $return_sql);
	}
	
	
	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;
		
		$filter->isindexed = true;
		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}
	
	
	
	// ***
	// *** SEARCH / INDEXING METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		if ($post===null) {
			$values = null;
			$field->field_valuesselect = ' fi.value_integer AS value_id, ct.title AS value';
			$field->field_valuesjoin   = ' JOIN #__content AS ct ON ct.id = fi.value_integer';
			$field->field_groupby      = ' GROUP BY fi.value_integer ';
		} else if (!empty($post)) {
			$_ids = array();
			foreach($post as $_id) $_ids[] = (int)$_id;  // convert itemID:catID to itemID
			$db = JFactory::getDbo();
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
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		if ($post===null) {
			$values = null;
			$field->field_valuesselect = ' fi.value_integer AS value_id, ct.title AS value';
			$field->field_valuesjoin   = ' JOIN #__content AS ct ON ct.id = fi.value_integer';
			$field->field_groupby      = ' GROUP BY fi.value_integer ';
		} else if (!empty($post)) {
			$_ids = array();
			foreach($post as $_id) $_ids[] = (int)$_id;  // convert itemID:catID to itemID 
			$db = JFactory::getDbo();
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


	static function getAllowedCategories(& $field)
	{
		// Get API objects / data
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();

		// categories scope parameters
		$use_cat_acl = $field->parameters->get('use_cat_acl', 1);
		$method_cat = $field->parameters->get('method_cat', 1);
		$usesubcats = $field->parameters->get('usesubcats', 0 );
		
		$catids = $field->parameters->get('catids');
		if ( empty($catids) )							$catids = array();
		else if ( ! is_array($catids) )		$catids = !FLEXI_J16GE ? array($catids) : explode("|", $catids);


		// ***
		// *** Get & check Global category related permissions
		// ***
		
		require_once (JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
		$viewallcats	= FlexicontentHelperPerm::getPerm()->ViewAllCats;
		
		
		// ***
		// *** Calculate categories to use for retrieving the items
		// ***
		
		$allowed_cats = $disallowed_cats = false;
		
		// Get user allowed categories
		$usercats = $use_cat_acl && !$viewallcats ?
			FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed=array('core.create', 'core.edit', 'core.edit.own'), $require_all=false, $check_published = true) :
			FlexicontentHelperPerm::returnAllCats($check_published=true, $specific_catids=null) ;
		
		// Find (if configured) , descendants of the categories
		if ($usesubcats)
		{
			global $globalcats;
			$_catids = array();
			foreach ($catids as $catid)
			{
				$subcats = $globalcats[$catid]->descendantsarray;
				foreach ($subcats as $subcat)  $_catids[(int)$subcat] = 1;
			}
			$catids = array_keys($_catids);
		}


		// ***
		// *** Decided allowed categories according to method of CATEGORY SCOPE
		// ***
		
		// Include method
		if ( $method_cat == 3 )
		{
			$allowed = array_intersect($usercats, $catids);
			return $allowed;
		}

		// Exclude method
		else if ( $method_cat == 2 )
		{
			$allowed = array_diff($usercats, $catids);
			return $allowed;
		}

		// Neither INCLUDE / nor EXCLUDE method, return all user 's allowed categories
		else
		{
			return $usercats;
		}
	}


	// Method called via AJAX to get dependent values
	function getCategoryItems()
	{
		// Get API objects / data
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		
		// Get Access Levels of user
		$uacc = array_flip(JAccess::getAuthorisedViewLevels($user->id));
		
		
		// Get request variables
		$field_id = $app->input->get('field_id', 0, 'int');
		$item_id  = $app->input->get('item_id',  0, 'int');
		$type_id  = $app->input->get('type_id',  0, 'int');
		$lang_code= $app->input->get('lang_code',  0, 'cmd');
		$catid    = $app->input->get('catid',    0, 'int');
		
		
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
		$request_catids = array($catid);
		
		
		
		// ***
		// *** Load and check field
		// ***
		
		$field = JTable::getInstance( $_type = 'flexicontent_fields', $_prefix = '', $_config = array() );
		

		if ( !$field->load( $field_id ) )           $response['error'] = 'field not found';
		else if ( $field->field_type!='relation' )  $response['error'] = 'field id is not a relation field';
		else
		{
			$is_editable = !$field->valueseditable || $user->authorise('flexicontent.editfieldvalues', 'com_flexicontent.field.' . $field->id);
			if ( !$is_editable )
			{
				$response['error'] = 'you do not have permission to edit lthis field';
			}
		}
		
		if ( $response['error'] )
		{
			exit( json_encode($response) );
		}
		
		
		// ***
		// *** Load and check item
		// ***
		
		$item = JTable::getInstance( $_type = 'flexicontent_items', $_prefix = '', $_config = array() );
		if ( !$item_id )
		{
			$item->type_id = $type_id;
			$item->language = $lang_code;
			$item->created_by = $user->id;
		}
		else if ( !$item->load( $item_id ) )       $response['error'] = 'content item not found';
		else
		{
			$asset = 'com_content.article.' . $item->id;
			$isOwner = $item->created_by == $user->get('id');
			$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);
			if ( !$canEdit )
			{
				$response['error'] = 'content item has non-allowed access level';
			}
		}

		if ( $response['error'] )
		{
			exit( json_encode($response) );
			exit;
		}
		
		
		// ***
		// *** Load field configuration
		// ***
		
		FlexicontentFields::loadFieldConfig($field, $item);
		$field->item_id = $item_id;
		
		// Some needed parameters
		$maxtitlechars 	= $field->parameters->get( 'maxtitlechars', 40 ) ;


		// ***
		// *** SCOPE PARAMETERS
		// ***

		// NOTE: categories scope parameters ... not used here, since category scope is checked by calling getAllowedCategories()

		// types scope parameters
		$method_types = $field->parameters->get('method_types', 1);

		$types = $field->parameters->get('types');
		if ( empty($types) )							$types = array();
		else if ( ! is_array($types) )		$types = !FLEXI_J16GE ? array($types) : explode("|", $types);

		// other limits of scope parameters
		$samelangonly  = $field->parameters->get( 'samelangonly', 1 );
		$onlypublished = $field->parameters->get( 'onlypublished', 1 );
		$ownedbyuser   = $field->parameters->get( 'ownedbyuser', 0 );
		
		
		// ***
		// *** Item retrieving query ... CREATE WHERE CLAUSE
		// ***
		$where = array();


		// CATEGORY SCOPE
		$allowed_cats = self::getAllowedCategories($field);
		if (empty($allowed_cats))
		{
			echo json_encode( array('error' => JText::_('FLEXI_CANNOT_EDIT_FIELD') .': <br/> '. JText::_('FLEXI_NO_ACCESS_TO_USE_CONFIGURED_CATEGORIES')) );
			exit;
		}

		// Check given category (-ies) is in the allowed categories
		$catids = array_intersect($allowed_cats, $request_catids);
		if ( empty($catids) )
		{
			echo json_encode( array('error' => JText::_('FLEXI_RIFLD_CATEGORY_NOT_ALLOWED')) );
			exit;
		}

		// Also include subcategory items
		$subcat_items = $field->parameters->get('subcat_items', 1 );
		if ($subcat_items)
		{
			global $globalcats;
			$_catids = array();
			foreach ($catids as $catid)
			{
				$subcats = $globalcats[$catid]->descendantsarray;
				foreach ($subcats as $subcat)  $_catids[(int)$subcat] = 1;
			}
			$catids = array_keys($_catids);
		}
		$where[] = ' rel.catid IN (' . implode(',', $catids ) . ') ';


		// TYPE SCOPE
		if ( ($method_types == 2 || $method_types == 3) && ( !count($types) || empty($types[0]) ) )
		{
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
		
		
		// ***
		// *** Item retrieving query ... CREATE ORDERBY CLAUSE
		// ***

		$order = $field->parameters->get( 'orderby_form', 'alpha' );;   // TODO: add more orderings: commented, rated
		$orderby = flexicontent_db::buildItemOrderBy(
			$field->parameters,
			$order, $request_var='', $config_param='',
			$item_tbl_alias = 'i', $relcat_tbl_alias = 'rel',
			$default_order='', $default_order_dir='', $sfx='_form', $support_2nd_lvl=false
		);
		
		// Create JOIN for ordering items by a most rated
		if ( in_array('author', $order) || in_array('rauthor', $order) )
		{
			$orderby_join = ' LEFT JOIN #__users AS u ON u.id = i.created_by';
		}
		
		
		// ***
		// *** Item retrieving query ... put together and execute it
		// ***

		$db = JFactory::getDbo();
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
			if (0 && $prepend_item_state)
			{
				$statestr = "[". @$state_shortname[$itemdata->state]."] ";
				$itemtitle = $statestr.$itemtitle." ";
			}
			$itemcat_arr = explode(",", $itemdata->catlist);
			$itemid = $itemdata->id;
			
			$response['options'][] = array('item_id'=>$itemid, 'item_title'=>$itemtitle);
		}

		// echo "<pre>"; print_r($response); echo "</pre>"; exit;

		// Output the field
		echo json_encode($response);
		exit;
	}
}
