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
	static $field_types = array('relation', 'relation_reverse', 'autorelationfilters');
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
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;


		// ***
		// *** Case of autorelated item
		// ***

		if (JFactory::getApplication()->input->get('autorelation_'.$field->id, 0, 'int'))
		{
			$field->html = '<div class="alert alert-warning">' . $field->label . ': ' . 'You can not auto-relate items using a relation field, please add a relation reverse field, and select to reverse this field</div>';
			return;
		}

		// Initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$app  = JFactory::getApplication();
		$db   = JFactory::getDbo();
		$user = JFactory::getUser();

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';


		// ***
		// *** Number of values
		// ***

		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		// Name Safe Element ID
		$elementid_ns = str_replace('-', '_', $elementid);

		$js = "";
		$css = "";


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
			' class="use_select2_lib '.$elementid.'_cat_selector" '
				. ' onchange="return fcfield_relation.cat_selector_change(\'' . $elementid . '\', ' . $item->id . ', ' . $field->id . ', ' . $item->type_id . ', \'' . $item->language . '\');" ',
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

			JText::script('FLEXI_RIFLD_ERROR', false);
			JText::script('FLEXI_RIFLD_NO_ITEMS', false);
			JText::script('FLEXI_RIFLD_ADD_ITEM', false);
			$document->addScript(JUri::root(true) . '/plugins/flexicontent_fields/relation/js/form.js');	
		}

		$_classes = 'use_select2_lib fc_select2_no_check fc_select2_noselect' . ($required ? ' required' : '') . ($selected_items_sortable ? ' fc_select2_sortable' : '');


		// ***
		// *** Create field's HTML display for item form
		// ***

		$field->html = array();
		$n = 0;
		//if ($use_ingroup) {print_r($field->value);}
		$field->html[] = '
			<div class="'.$input_grp_class.' fc-xpended-row fcrelation-field-category-selector" '.$cat_selecor_box_style.'>
				<label class="' . $add_on_class . ' fc-lbl cats-selector-lbl" for="'.$elementid.'_cat_selector">'.JText::_( 'FLEXI_CATEGORY' ).'</label>
				'.$_cat_selector.'
			</div>

			<div class="'.$input_grp_class.' fc-xpended-row fcrelation-field-item-selector">
				<label class="' . $add_on_class . ' fc-lbl item-selector-lbl" for="'.$elementid.'_item_selector">'.JText::_( 'FLEXI_RIFLD_ITEMS' ).'</label>
				<select id="'.$elementid.'_item_selector" name="'.$elementid.'_item_selector" class="use_select2_lib" onchange="return fcfield_relation.add_related(this, \'' . $elementid . '\');">
					<option value="">-</option>
				</select>
			</div>

			<div class="'.$input_grp_class.' fc-xpended-row fcrelation-field-selected-items">
				<label class="' . $add_on_class . ' fc-lbl selected-items-lbl" for="'.$elementid.'">'.JText::_($selected_items_label).'</label>
				<select id="'.$elementid.'" name="'.$fieldname.'[]" multiple="multiple" class="'.$_classes.'" '.$size.' onchange="return fcfield_relation.selected_items_modified(\'' . $elementid . '\');" >
					'.$items_options_select.'
				</select>
				'.($selected_items_sortable ? '
				<span class="add-on"><span class="icon-info hasTooltip" title="'.JText::_('FLEXI_FIELD_ALLOW_SORTABLE_INFO').'"></span>' . JText::_('FLEXI_ORDER') . '</span>' : '').'
			</div>
		';

		// If using single category then trigger loading the items selector
		if (count($allowedtree) === 1)
		{
			$js .= "
			jQuery(document).ready(function()
			{
				jQuery('#" . $elementid . "_cat_selector').trigger('change');
			});
			";
		}

		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);

		// Do not convert the array to string if field is in a group
		if ($use_ingroup);

		// Handle multiple records
		elseif ($multiple)
		{
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '
				<div class="input-append input-prepend fc-xpended-btns">
					<span class="fcfield-addvalue ' . $font_icon_class . ' fccleared" onclick="addField'.$field->id.'(jQuery(this).closest(\'.fc-xpended-btns\').get(0));" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">
						'.JText::_( 'FLEXI_ADD_VALUE' ).'
					</span>
				</div>';
		}

		// Handle single values
		else
		{
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
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
		$total_show_auto_btn = $field->field_type !== 'relation' ? 0 : (int) $field->parameters->get('total_show_auto_btn', 0);
		$total_show_list     = $field->field_type !== 'relation' ? 0 : (int) $field->parameters->get('total_show_list', 0);

		if ($prop=='display_total')  // Explicitly requested
		{
			$disp->total_info = true;
		}
		elseif ( $show_total_only==1 || ($show_total_only == 2 && (count($values) || $field->field_type === 'relation_reverse')) )  // For relation reverse we will count items inside the layout
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
		// NOTE: this is not applicable for 'relation_reverse' & 'autorelationfilters' field, and parameter does not exist for this field,
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

		$relation_field_id = $field->parameters->get('reverse_field', 0);

		if ($field->field_type === 'relation_reverse')
		{
			// Check that relation field to be reversed was configured
			if (!$relation_field_id)
			{
				$field->{$prop} = '<div class="alert alert-warning">' . $field->label . ': ' . JText::_('FLEXI_RIFLD_NO_FIELD_SELECTED_TO_BE_REVERSED').'</div>';
				return;
			}

			// Always ignore passed items, the DB query will determine the items
			$related_items = null;
		}

		else  // $field->field_type === 'autorelationfilters' || $field->field_type === 'relation'
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

			for ($i = 0; $i < $itemcount; $i++)
			{
				list ($itemid, $catid) = explode(":", $values[$i]);
				$related_items[$itemid] = new stdClass();
				$related_items[$itemid]->itemid = $itemid;
				$related_items[$itemid]->catid  = $catid;
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
			// 0: return string with related items HTML,
			// 1: return related items array,
			// 2: same as 1 but also means do not create HTML display,
			// 3: same as 2 but also do not get any item data
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


	// Method to display a category filter for the category view
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

		// If we are filtering via a relation-reverse field, then get the ID of relation field
		if ($filter->field_type === 'relation_reverse')
		{
			$relation_field_id = (int) $filter->parameters->get('reverse_field', 0);

			if (!$relation_field_id)
			{
				echo '<div class="alert alert-warning">' . $filter->label . ': ' . JText::_('FLEXI_RIFLD_NO_FIELD_SELECTED_TO_BE_REVERSED').'</div>';
				return null;
			}
		}
		elseif ($filter->field_type === 'relation')
		{
			$relation_field_id = $filter->id;
		}
		else
		{
			echo '<div class="alert alert-warning">Field type : ' . $filter->field_type . ' is not filterable </div>';
			return null;
		}

		$is_relation = $filter->field_type === 'relation';
		$ritem_field_id = key($value);
		$ritem_field_id = is_int($ritem_field_id) && $ritem_field_id < 0
			? - $ritem_field_id
			: 0;

		if (!$ritem_field_id)
		{
			if ($is_relation)
			{
				$filter->filter_colname     = ' rel.value_integer';
				$filter->filter_valuesjoin  = null;   // use default
				$filter->filter_valueformat = null;   // use default
			}
			else
			{
				return null;
			}
		}

		else
		{
			$rel = 'relv';
			$c = 'c';
			$join_field_filters = '';

			// Find items that are directly / indirectly related via a RELATION / REVERSE RELATION field
			$match_rel_items = $is_relation
				? $c . '.id = ' . $rel . '.item_id'
				: $c . '.id = ' . $rel . '.value_integer';
			$join_field_filters .= ' JOIN #__flexicontent_fields_item_relations AS ' . $rel . ' ON ' . $match_rel_items . ' AND ' . $rel . '.field_id = ' . $relation_field_id;

			$val_tbl = $rel . '_ritems';
			$val_field_id = $ritem_field_id;

			// RELATED / REVERSE RELATED Items must have given values
			$val_on_items = $is_relation
				? $val_tbl . '.item_id = ' . $rel . '.value_integer'
				: $val_tbl . '.item_id = ' . $rel . '.item_id';

			// Join with values table 'ON' the current filter field id and 'ON' the items at interest ... below we will add an extra 'ON' clause to limit to the given field values
			$join_field_filters .= ' JOIN #__flexicontent_fields_item_relations AS ' . $val_tbl . ' ON ' . $val_on_items . ' AND ' . $val_tbl . '.field_id = ' . $val_field_id;

			$filter->filter_colname     = ' ' . $val_tbl . '.value';
			$filter->filter_valuesjoin  = $join_field_filters;
			$filter->filter_valueformat = null;   // use default
			$filter->filter_valuewhere = null;   // use default
		}

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


	/**
	 * Method called via AJAX to get dependent values
	 */

	function getCategoryItems()
	{
		// Get API objects / data
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$db    = JFactory::getDbo();

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


		if (!$field_id)
		{
			$response['error'] = 'Invalid field_id';
		}

		elseif (!$catid)
		{
			$response['error'] = 'Invalid catid';
		}

		if ($response['error'])
		{
			jexit(json_encode($response));
		}



		/**
		 * Load and check field
		 */

		$field = JTable::getInstance( $_type = 'flexicontent_fields', $_prefix = '', $_config = array() );

		if (!$field->load($field_id))
		{
			$response['error'] = 'field not found';
		}

		elseif ($field->field_type !== 'relation')
		{
			$response['error'] = 'field id is not a relation field';
		}

		else
		{
			$is_editable = !$field->valueseditable || $user->authorise('flexicontent.editfieldvalues', 'com_flexicontent.field.' . $field->id);

			if ( !$is_editable )
			{
				$response['error'] = 'you do not have permission to edit lthis field';
			}
		}

		if ($response['error'])
		{
			jexit(json_encode($response));
		}


		/**
		 * Load and check item
		 */

		$item = JTable::getInstance( $_type = 'flexicontent_items', $_prefix = '', $_config = array() );

		if (!$item_id)
		{
			$item->type_id = $type_id;
			$item->language = $lang_code;
			$item->created_by = $user->id;
		}

		elseif (!$item->load($item_id))
		{
			$response['error'] = 'content item not found';
		}

		else
		{
			$asset = 'com_content.article.' . $item->id;
			$isOwner = $item->created_by == $user->get('id');
			$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);

			if (!$canEdit)
			{
				$response['error'] = 'content item has non-allowed access level';
			}
		}

		if ($response['error'])
		{
			jexit(json_encode($response));
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
		$method_types = (int) $field->parameters->get('method_types', 1);
		$types = $field->parameters->get('types');

		if (empty($types))
		{
			$types = array();
		}

		elseif (!is_array($types))
		{
			$types = explode('|', $types);
		}

		// other limits of scope parameters
		$samelangonly  = (int) $field->parameters->get('samelangonly', 1);
		$onlypublished = (int) $field->parameters->get('onlypublished', 1);
		$ownedbyuser   = (int) $field->parameters->get('ownedbyuser', 0);


		/**
		 * Item retrieving query ... CREATE WHERE CLAUSE according to configured limitations SCOPEs
		 */
		$where = array();

		// Exclude currently edited item
		if ($item_id)
		{
			$where[] = ' i.id <> ' . (int) $item_id;
		}


		/**
		 * CATEGORY SCOPE
		 */

		$allowed_cats = self::getAllowedCategories($field);

		if (empty($allowed_cats))
		{
			jexit(json_encode(array(
				'error' => JText::_('FLEXI_CANNOT_EDIT_FIELD') .': <br/> '. JText::_('FLEXI_NO_ACCESS_TO_USE_CONFIGURED_CATEGORIES')
			)));
		}

		// Check given category (-ies) is in the allowed categories
		$request_catids = array($catid);
		$catids = array_intersect($allowed_cats, $request_catids);

		if (empty($catids))
		{
			jexit(json_encode(array(
				'error' => JText::_('FLEXI_RIFLD_CATEGORY_NOT_ALLOWED')
			)));
		}

		// Also include subcategory items
		$subcat_items = (int) $field->parameters->get('subcat_items', 1 );

		if ($subcat_items)
		{
			global $globalcats;
			$_catids = array();

			foreach ($catids as $catid)
			{
				$subcats = $globalcats[$catid]->descendantsarray;

				foreach ($subcats as $subcat)
				{
					$_catids[(int)$subcat] = 1;
				}
			}
			$catids = array_keys($_catids);
		}

		$where[] = ' rel.catid IN (' . implode(',', $catids ) . ') ';


		/**
		 * TYPE SCOPE
		 */

		if (($method_types === 2 || $method_types === 3) && (!count($types) || empty($types[0])))
		{
			jexit(json_encode(array(
				'error' => 'Content Type scope is set to include/exclude but no Types are selected in field configuration, please set to "ALL" or select types to include/exclude'
			)));
		}

		// exclude method
		if ($method_types === 2)
		{
			$where[] = ' ie.type_id NOT IN (' . implode(',', $types) . ')';
		}

		// include method
		elseif ($method_types === 3)
		{
			$where[] = ' ie.type_id IN (' . implode(',', $types) . ')';
		}


		/**
		 * OTHER SCOPE LIMITATIONS
		 */

		if ($samelangonly)
		{
			$where[] = !$item->language || $item->language === '*'
				? ' i.language = ' . $db->Quote('*')
				: ' (i.language = ' . $db->Quote($item->language) . ' OR i.language = ' . $db->Quote('*') . ') ';
		}

		if ($onlypublished)
		{
			$where[] = ' i.state IN (1, -5) ';
		}

		if ($ownedbyuser === 1)
		{
			$where[] = ' i.created_by = ' . (int) $user->id;
		}
		elseif ($ownedbyuser === 2)
		{
			$where[] = ' i.created_by = ' . (int) $item->created_by;
		}


		// Create the WHERE clause
		$where = !count($where)
			? ''
			: ' WHERE ' . implode(' AND ', $where);


		/**
		 * Item retrieving query ... CREATE ORDERBY CLAUSE
		 */

		$order = $field->parameters->get( 'orderby_form', 'alpha' );;   // TODO: add more orderings: commented, rated
		$orderby = flexicontent_db::buildItemOrderBy(
			$field->parameters,
			$order, $request_var='', $config_param='',
			$item_tbl_alias = 'i', $relcat_tbl_alias = 'rel',
			$default_order='', $default_order_dir='', $sfx='_form', $support_2nd_lvl=false
		);

		// Create JOIN for ordering items by a most rated
		$orderby_join = in_array('author', $order) || in_array('rauthor', $order)
			? ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			: '';

		// Create JOIN for getting item types
		$types_join = $method_types > 1
			? ' LEFT JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id '
			: '';

		/**
		 * Item retrieving query ... put together and execute it
		 */

		$query = 'SELECT i.title, i.id, i.catid, i.state, i.alias, GROUP_CONCAT(rel.catid SEPARATOR \',\') as catlist'
			. ' FROM #__content AS i '
			. $types_join
			. ' JOIN #__flexicontent_cats_item_relations AS rel on i.id=rel.itemid '
			. $orderby_join
			. $where
			. ' GROUP BY rel.itemid '
			. $orderby
			;
		$items_arr = $db->setQuery($query)->loadObjectList();

		// Some configuration
		$prepend_item_state = (int) $field->parameters->get('prepend_item_state', 1);

		foreach($items_arr as $itemdata)
		{
			$itemtitle = StringHelper::strlen($itemdata->title) > $maxtitlechars
				? StringHelper::substr($itemdata->title, 0, $maxtitlechars) . '...'
				: $itemdata->title;

			/*if ($prepend_item_state)
			{
				$statestr = '[' . (isset($state_shortname[$itemdata->state]) ? $state_shortname[$itemdata->state] : 'U') . '] ';
				$itemtitle = $statestr.$itemtitle." ";
			}*/

			$itemcat_arr = explode(',', $itemdata->catlist);
			$itemid = $itemdata->id;

			$response['options'][] = array('item_id' => $itemid, 'item_title' => $itemtitle);
		}

		//jexit(print_r($response, true));

		// Output the field
		jexit(json_encode($response));
	}
}
