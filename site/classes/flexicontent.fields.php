<?php
/**
 * @version 1.5 stable $Id: flexicontent.fields.php 1990 2014-10-14 02:17:49Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

// Include constants file
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

class FlexicontentFields
{
	/**
	 * Function to render the field display variables for the given items
	 *
	 * @param 	int 		$item_id
	 * @return 	string  : the HTML of the item view, also the CSS / JS file would have been loaded
	 * @since 1.5
	 */
	static function renderFields( $item_per_field=true, $item_ids=array(), $field_names=array(), $view=FLEXI_ITEMVIEW, $methods=array(), $cfparams=array() )
	{
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');


		// ***************************
		// Check if no data were given
		// ***************************

		if ( empty($item_ids) || empty($field_names) ) return false;

		// Get item data, needed for rendering fields
		$db = JFactory::getDbo();

		$unique_item_ids = array_unique(array_map('intval', $item_ids));
		$item_ids_list = implode("," , $unique_item_ids) ;

		$query = 'SELECT i.id, i.*, ie.*, '
			. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
			. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ' FROM #__content AS i'
			. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
			. ' WHERE i.id IN ('. $item_ids_list .')'
			//. ' GROUP BY i.id'
			;
		$db->setQuery($query);
		$items = $db->loadObjectList();
		if (empty($items)) return false;

		foreach ($items as $i => $item) $_item_id_map[$item->id] = & $items[$i];

		$return = array();
		foreach ($field_names as $i => $field_name)
		{
			$method = isset( $methods[$i] ) ? $methods[$i] : 'display';
			if ( $item_per_field )
			{
				if ( !isset( $_item_id_map[ $item_ids[$i] ] ) )  { /*echo "not found item: ".$item_ids[$i] ." <br/>";*/ continue;}

				// Render Display variable of Field for respective item
				$_item = & $_item_id_map[$item_ids[$i]];
				FlexicontentFields::getFieldDisplay($_item, $field_name, $values=null, $method, $view);

				// Add to return array
				$return[$_item->id][$field_name][$method] = isset($_item->fields[$field_name]->$method)
					? $_item->fields[$field_name]->$method
					: null;
			}
			else
			{
				// Render Display variable of Field for all items
				FlexicontentFields::getFieldDisplay($items, $field_name, $values=null, $method, $view);

				// Add to return array
				foreach ($items as $item)
				{
					$return[$item->id][$field_name][$method] = isset($item->fields[$field_name]->$method)
						? $item->fields[$field_name]->$method
						: null;
				}
			}
		}
		return $return;
	}


	/**
	 * Method to bind fields to an items object
	 *
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	static function & getFields(&$_items, $view = FLEXI_ITEMVIEW, $params = null, $aid = false, $use_tmpl = true)
	{
		static $expired_cleaned = false;

		if (!$_items) return $_items;
		if (!is_array($_items))  $items = array( & $_items );  else  $items = & $_items ;

		$jinput    = JFactory::getApplication()->input;
		$user      = JFactory::getUser();
		$cparams   = JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $cparams->get('print_logging_info');

		if ( $print_logging_info ) {
			global $fc_run_times;
			$start_microtime = microtime(true);
		}

		// Calculate access for current user if it was not given or if given access is invalid
		$aid = is_array($aid) ? $aid : JAccess::getAuthorisedViewLevels($user->id);

		$vars = null;
		FlexicontentFields::getItemFields($items, $vars, $view, $aid);

		if ( $print_logging_info )  @$fc_run_times['field_values_params'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		$_rendered = array();
		if ($params)  // NULL/empty parameters mean only retrieve field values
		{
			$always_create_fields_display = $cparams->get('always_create_fields_display',0);
			$request_view = $jinput->get('view', '', 'cmd');

			// CHECK if 'always_create_fields_display' enabled and create the display for all item's fields
			// *** This should be normally set to ZERO (never), to avoid a serious performance penalty !!!

			// 0: never, 1: always, 2: only in item view, 3: never unless in a template position,  this effects function:  renderPositions()
			if ($always_create_fields_display==1 || ($always_create_fields_display==2 && $request_view==FLEXI_ITEMVIEW && $view==FLEXI_ITEMVIEW) )
			{
				$field_names = array();
				foreach ($items as $i => $item)
				{
					if ($items[$i]->fields)
					{
						foreach ($items[$i]->fields as $field)
						{
							$values = isset($items[$i]->fieldvalues[$field->id]) ? $items[$i]->fieldvalues[$field->id] : array();
							$field 	= FlexicontentFields::renderField($items[$i], $field, $values, $method='display', $view);
							$field_names[$field->name] = 1;
						}
					}
				}
				foreach ($field_names as $field_name => $_ignore) {
					$_rendered['ALL'][$field_name] = 1;
				}
			}

			// Render field positions
			$items = FlexicontentFields::renderPositions($items, $view, $params, $use_tmpl, $_rendered);
		}
		return $items;
	}


	/**
	 * Method to get fields configuration data by field ids
	 *
	 * @access private
	 * @return object
	 * @since 3
	 */
	static function & indexFieldsByIds($fields, $item=null, $force=false)
	{
		if ( $item && !$force && isset($item->fieldsByIds) )  return $item->fieldsByIds;

		$byIds = array();
		foreach($fields as $_field)
		{
			$byIds[$_field->id] = $_field;
		}
		if ($item) $item->fieldsByIds = & $byIds;

		return $byIds;
	}


	/**
	 * Method to get fields configuration data by field ids
	 *
	 * @access private
	 * @return object
	 * @since 3
	 */
	static function & getFieldsByIds($field_ids, $check_access=true)
	{
		if (!count($field_ids))
		{
			$fields = array();
			return $fields;
		}

		$db   = JFactory::getDbo();
		$user = JFactory::getUser();

		$field_ids = ArrayHelper::toInteger($field_ids);

		// Field's has_access flag
		if ($check_access)
		{
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$select_access = ', CASE WHEN fi.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_access';
		}
		else
			$select_access = '';

		$query 	= 'SELECT fi.*'
			. $select_access
			. ' FROM #__flexicontent_fields AS fi'
			. ' WHERE fi.id IN ('.implode(",", $field_ids).') '
			;
		$db->setQuery($query);
		$fields = $db->loadObjectList('id');

		return $fields;
	}


	/**
	 * Method to get fields values data by field ids + item ids
	 *
	 * @access private
	 * @return object
	 * @since 3
	 */
	static function & getFieldValsById($field_ids, $item_ids, $version=0)
	{
		$db = JFactory::getDbo();
		$field_ids = ArrayHelper::toInteger($field_ids);
		$item_ids  = ArrayHelper::toInteger($item_ids);

		$query = 'SELECT item_id, field_id, value, valueorder, suborder'
			. ($version ? ' FROM #__flexicontent_items_versions':' FROM #__flexicontent_fields_item_relations')
			. ' WHERE item_id IN ('.implode(",", $item_ids).') '
			. ($field_ids ? ' AND field_id IN ('.implode(",", $field_ids).') ' : '')
			. ($version ? ' AND version=' . (int)$version:'')
			. ' AND value > "" '
			. ' ORDER BY field_id, valueorder, suborder'
			;
		$db->setQuery($query);
		$values = $db->loadObjectList();

		$fieldvalues = array();
		if ($values) foreach ($values as $v)
		{
			$fieldvalues[$v->item_id][$v->field_id][$v->valueorder - 1][$v->suborder - 1] = $v->value;
		}

		foreach ($fieldvalues as & $iv)
		{
			foreach ($iv as & $fv)
			{
				foreach ($fv as & $ov)
				{
					// Maybe examine parameters and avoid this if either of 'allow_multiple' , 'use_ingroup' is set
					if (count($ov) === 1)
					{
						$ov = reset($ov);
					}
				}
				unset($ov);
			}
			unset($fv);
		}
		unset($iv);

		return $fieldvalues;
	}


	/**
	 * Method to fetch the fields from an item object
	 *
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	static function & getItemFields(&$items, &$vars=null, $view=FLEXI_ITEMVIEW, $aid=false)
	{
		if (empty($items))
		{
			return $items;
		}

		static $type_fields = array();

		$dispatcher = JEventDispatcher::getInstance();
		$db   = JFactory::getDbo();
		$user = JFactory::getUser();
		$nullDate = $db->getNulldate();

		// This is optimized regarding the use of SINGLE QUERY to retrieve the core item data
		if ($vars==null)
		{
			$vars['tags']       = FlexicontentFields::_getTags($items, $view);
			$vars['cats']       = FlexicontentFields::_getCategories($items, $view);
			$vars['favourites'] = FlexicontentFields::_getFavourites($items, $view);
			$vars['favoured']   = FlexicontentFields::_getFavoured($items, $view);
			$vars['authors']    = FlexicontentFields::_getAuthors($items, $view);
			$vars['modifiers']  = FlexicontentFields::_getModifiers($items, $view);
			$vars['typenames']  = FlexicontentFields::_getTypenames($items, $view);
			$vars['votes']      = FlexicontentFields::_getVotes($items, $view);
			$vars['custom']     = FlexicontentFields::_getCustomValues($items, $view);
		}

		foreach ($items as $i => $item)
		{
			if (!FLEXI_J16GE && $item->sectionid != FLEXI_SECTION) continue;

			$item_id = $item->id;

			$cats      = isset($vars['cats'][$item_id])      ? $vars['cats'][$item_id]             : array();
			$tags      = isset($vars['tags'][$item_id])      ? $vars['tags'][$item_id]             : array();
			$favourites= isset($vars['favourites'][$item_id])? $vars['favourites'][$item_id]->favs : 0;
			$favoured  = isset($vars['favoured'][$item_id])  ? $vars['favoured'][$item_id]->fav    : 0;
			$author    = isset($vars['authors'][$item_id])   ? $vars['authors'][$item_id]          : '';
			$modifier  = isset($vars['modifiers'][$item_id]) ? $vars['modifiers'][$item_id]        : '';
			$typename  = isset($vars['typenames'][$item_id]) ? $vars['typenames'][$item_id]        : '';
			$vote      = isset($vars['votes'][$item_id])     ? $vars['votes'][$item_id]            : '';
			$custom    = isset($vars['custom'][$item_id])    ? $vars['custom'][$item_id]           : array();

			if ( empty($item->type_id) )
			{
				if (JDEBUG) JFactory::getApplication()->enqueueMessage('Item with id: ' .$item->id. ' has empty type, please edit it and set a type', 'warning');
			}

			// ONCE per Content Item Type (skip this if item has no type)
			else if ( $item->type_id && !isset($type_fields[$item->type_id]) )
			{
				// Field's has_access flag
				$aid_arr = is_array($aid) ? $aid : JAccess::getAuthorisedViewLevels($user->id);
				$aid_list = implode(",", $aid_arr);
				$select_access = ', CASE WHEN fi.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_access';

				$query 	= 'SELECT fi.*'
					. $select_access
					. ' FROM #__flexicontent_fields AS fi'
					. ' JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id AND ftrel.type_id = '.$item->type_id
					. ' WHERE fi.published = 1'
					. ' GROUP BY fi.id'
					. ' ORDER BY ftrel.ordering, fi.ordering, fi.name'
					;
				$db->setQuery($query);
				$type_fields[$item->type_id] = $db->loadObjectList('name');
				//echo "<pre>";  print_r( array_keys($type_fields[$item->type_id]) ); exit;
			}

			// Add custom fields, if these were found
			$item->fields = array();
			if (!empty($type_fields[$item->type_id]))
			{
				foreach($type_fields[$item->type_id] as $field_name => $field_data)
				{
					$item->fields[$field_name] = clone($field_data);
				}
			}

			if (!isset($item->parameters))
			{
				try
				{
					$item->parameters = new JRegistry($item->attribs);
				}
				catch (Exception $e)
				{
					$item->parameters = flexicontent_db::check_fix_JSON_column('attribs', 'content', 'id', $item->id, $item->attribs);
				}
			}

			// Property 'params' is an alias of property 'parameters'
			$item->params = $item->parameters;

			$item->text  = $item->introtext . chr(13) . chr(13) . $item->fulltext;
			$item->tags  = $tags;
			$item->cats  = $cats;
			$item->favs  = $favourites;
			$item->fav   = $favoured;

			$item->creator 	= !empty($author->alias) ? $author->alias : (!empty($author->name) ? $author->name : '');
			$item->author		= & $item->creator;  // An alias ... of creator
			$item->modifier	= !empty($modifier->name)      ? $modifier->name  : $item->creator;   // If never modified, set modifier to be the creator
			$item->modified	= $item->modified != $nullDate ? $item->modified  : $item->created;   // If never modified, set modification date to be the creation date

			$item->cmail 		= !empty($author->email)     ? $author->email     : '' ;
			$item->cuname 	= !empty($author->username)  ? $author->username  : '' ;
			$item->mmail		= !empty($modifier->email)   ? $modifier->email   : $item->cmail;
			$item->muname		= !empty($modifier->muname)  ? $modifier->muname  : $item->cuname;

			$item->typename	= !empty($typename->name)    ? $typename->name 	: JText::_('Article');
			$item->vote			= !empty($vote) ? $vote : '';

			// some aliases to much CORE field names
			$item->categories    = & $item->cats;
			$item->favourites    = & $item->favs;
			$item->document_type = & $item->typename;
			$item->voting        = & $item->vote;

			// custom field values
			$item->fieldvalues = $custom;
		}

		return $items;
	}


	/*
	* Create editing HTML of a field
	*/
	static function getFieldFormDisplay($field, $item, $user)
	{
		// ***
		// *** Apply CONTENT TYPE customizations to CORE FIELDS, e.g a type-specific label & description
		// *** for CUSTOM fields only do basic initialization like language filtering on label & description
		// ***

		FlexicontentFields::loadFieldConfig($field, $item);

		if ($field->iscore)
		{

			// Special case: create MAINTEXT field (description field), by calling the display function of the textarea field (will also check for tabs)
			switch ($field->field_type)
			{
				case 'maintext':
					if (isset($item->item_translations))
					{
						$shortcode = substr($item->language ,0,2);

						foreach ($item->item_translations as $lang_id => $t)
						{
							if ($shortcode == $t->shortcode) continue;
							$field->name = array('jfdata',$t->shortcode,'text');
							$field->value[0] = html_entity_decode($t->fields->text->value, ENT_QUOTES, 'UTF-8');
							FLEXIUtilities::call_FC_Field_Func('textarea', 'onDisplayField', array(&$field, &$item) );
							$t->fields->text->tab_labels = $field->tab_labels;
							$t->fields->text->html = $field->html;
							unset( $field->tab_labels );
							unset( $field->html );
						}
					}

					// NOTE: We use the text created by the model and not the text retrieved by the CORE plugin code, which maybe overwritten with JoomFish/Falang data
					$field->name = 'text';
					$field->value[0] = $item->text; // do not decode special characters this was handled during saving !

					// Render the field's (form) HTML
					FLEXIUtilities::call_FC_Field_Func('textarea', 'onDisplayField', array(&$field, &$item) );
					break;

				default:
					// Render the field's (form) HTML (if implemented)
					FLEXIUtilities::call_FC_Field_Func('core', 'onDisplayField', array(&$field, &$item) );
					break;
			}

			return;
		}

		// Check field is assigned to current item type
		else if (!isset($item->fields[$field->name]))
		{
			$field->html = null; //array('<span class="alert alert-info">' . JText::_('FLEXI_NOT_ASSIGNED'). ' ' . JText::_('FLEXI_TO') . ' ' . JText::_('FLEXI_TYPE') . '</span>');
			return;
		}


		// ***
		// *** Create editing HTML of the field NOTE: this is DONE only for CUSTOM fields, since form
		// *** field html is created by the form itself for all CORE fields, (except for 'text' field)
		// ***

		// Check for field configured to be inside a field group and skip it
		if ($field->parameters->get('use_ingroup', 0) && empty($field->ingroup))
		{
			$field->formhidden = 3;
			return;
		}

		$is_editable = !$field->valueseditable || $user->authorise('flexicontent.editfieldvalues', 'com_flexicontent.field.' . $field->id);
		if ($is_editable)
		{
			FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayField', array( &$field, &$item ));
			if ($field->untranslatable)
			{
				$msg = !isset($field->html) ?
					'<div class="alert alert-info fc-warning fc-iblock" style="margin:0 2px 6px 2px; max-width: unset;">'.JText::_( 'FLEXI_PLEASE_PUBLISH_THIS_PLUGIN' ).'</div>' :
					'<div class="alert alert-info fc-small fc-iblock" style="margin:0 2px 6px 2px; max-width: unset;">'. JText::_('FLEXI_FIELD_VALUE_IS_NON_TRANSLATABLE') . '</div>' ;

				if (!is_array($field->html))
				{
					$field->html = $msg .' <div class="fcclear"></div> '. $field->html;
				}
				else
				{
					foreach($field->html as $i => & $field_html)  $field->html[$i] = $msg .' <div class="fcclear"></div> '. $field_html;
					unset($field_html);
				}
			}
		}

		// Non-editable message only
		else if ($field->valueseditable==1)
		{
			$msg = '<div class="alert alert-info fc-small fc-iblock">' . JText::_($field->parameters->get('no_acc_msg_form') ? $field->parameters->get('no_acc_msg_form') : 'FLEXI_NO_ACCESS_LEVEL_TO_EDIT_FIELD') . '</div>';

			// Handle non-editable field inside fieldgroup
			if (!empty($field->ingroup))
			{
				$field->html = array();
				foreach($field->value as $i => $v) $field->html[$i]= $msg;
			}
			else
			{
				$field->html = $msg;
			}
		}

		// Non-editable message only + display values
		else if ($field->valueseditable==2)
		{
			FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayFieldValue', array( &$field, $item ));

			$msg = '<div class="alert alert-info fc-small fc-iblock">' . JText::_($field->parameters->get('no_acc_msg_form') ? $field->parameters->get('no_acc_msg_form') : 'FLEXI_NO_ACCESS_LEVEL_TO_EDIT_FIELD') . '</div>';
			if (!is_array($field->display))
			{
				$field->html = $msg .' <div class="fcclear"></div> <div class="fc-non-editable-value">'. $field->display .'</div>';
			}
			else
			{
				$field->html = array();
				foreach($field->display as $i => & $field_display)  $field->html[$i] = $msg .' <div class="fcclear"></div> <div class="fc-non-editable-value">'. $field_display .'</div>';
				unset($field_display);
			}
		}

		else if ($field->valueseditable==3)
		{
			FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayFieldValue', array( &$field, $item ));
			if (!is_array($field->display))
			{
				$field->html = '<div class="fc-non-editable-value">'. $field->display .'</div>';
			}
			else
			{
				$field->html = array();
				foreach($field->display as $i => & $field_display)  $field->html[$i] = '<div class="fc-non-editable-value">'. $field_display .'</div>';
				unset($field_display);
			}
		}

		else if ($field->valueseditable==4)
		{
			$field->html = '';
			$field->formhidden = 4;
		}
	}


	/**
	 * Method to render (display method) a field on demand and return the display
	 *
	 * @access public
	 * @return object
	 * @since 1.5.5
	 */
	static function getFieldDisplay(&$item_arr, $fieldname, $single_item_vals=null, $method='display', $view = FLEXI_ITEMVIEW)
	{
		// 1. Convert to array of items if not an array already
		if ( empty($item_arr) ) {
			$err_msg = __FUNCTION__."(): empty item data given";
			return $err_msg;
		}
		else if ( !is_array($item_arr) )
		{
			$items = array( & $item_arr );
		}
		else
		{
			$items = & $item_arr;
		}

		// 2. Make sure that fields have been created for all given items
		$_items = array();
		foreach ($items as $i => $item)  if (!isset($item->fields))  $_items[] = & $items[$i];
		if ( count($_items) )  FlexicontentFields::getFields($_items, $view);

		// 3. Check and create HTML display for the given field name
		$_method_html = array();
		foreach ($items as $item)
		{
			// Check if we have already created the display and skip current item
			if ( isset($item->onDemandFields[$fieldname]->{$method}) )
			{
				$_method_html[$item->id] = $item->onDemandFields[$fieldname]->{$method};
				continue;
			}

			// Find the field inside item
			foreach ($item->fields as $field)
			{
				if ( !empty($field->name) && $field->name==$fieldname ) break;
			}

			// Check for not found field, and skip it, this is either due to no access or wrong name ...
			$item->onDemandFields[$fieldname] = new stdClass();
			if ( empty($field->name) || $field->name!=$fieldname)
			{
				$item->onDemandFields[$fieldname]->label = '';
				$item->onDemandFields[$fieldname]->noaccess = true;
				$item->onDemandFields[$fieldname]->errormsg = 'field not assigned to this type of item or current user has no access';
				$item->onDemandFields[$fieldname]->{$method} = '';
				$_method_html[$item->id] = '';
				continue;
			}

			// Get field's values if they were custom values were not given
			if ( $single_item_vals!==null && count($items) == 1 ) {
				// $values is used only if rendering a single item
				$values = $single_item_vals;
			} else {
				$values = isset($item->fieldvalues[$field->id]) ? $item->fieldvalues[$field->id] : array();
			}

			// Set other field data like label and field itself !!!
			$item->onDemandFields[$fieldname]->label = $field->label;
			$item->onDemandFields[$fieldname]->noaccess = false;
			$item->onDemandFields[$fieldname]->field = & $field;

			// Render the (display) method of the field
			if (!isset($field->{$method}))
			{
				$field = FlexicontentFields::renderField($item, $field, $values, $method, $view);
			}
			if (!isset($field->{$method}))
			{
				$field->{$method} = '';
			}

			// Only cache the result, only if using no values were given, thus DB values were used
			if ( $single_item_vals===null )
			{
				$item->onDemandFields[$fieldname]->{$method} = $field->{$method};
				$_method_html[$item->id] = $field->{$method};
			}
		}

		// Return field(s) HTML (in case of multiple items this will be an array indexable by item ids
		if ( !is_array($item_arr) )
		{
	   // Not isset should occur only when fieldname was not found
			$_method_html = isset($_method_html[$item_arr->id]) ? $_method_html[$item_arr->id] : '';
		}
		return $_method_html;
	}



	/**
	 * Method to render a field
	 *
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	static function renderField(&$_item, &$_field, &$values, $method='display', $view=FLEXI_ITEMVIEW, $skip_trigger_plgs = false, $event_row = false)
	{
		static $_trigger_plgs_ft = array();
		static $_created = array();
		$app = JFactory::getApplication();
		$request_view = $app->input->get('view', '', 'cmd');

		// Field's source code, can use this HTTP request variable, to detect who rendered the fields (e.g. they can detect rendering from 'module')
		$app->input->set('flexi_callview', $view);

		static $cparams = null;
		if ($cparams === null)
		{
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		}

		static $aid;
		if ($aid === null)
		{
			$aid = JAccess::getAuthorisedViewLevels(JFactory::getUser()->id);
		}

		if (is_array($_item) && is_string($_field)) ;  // ok
		else if (is_object($_item) && is_object($_field)) ; // ok
		else
		{
			$field->$method = '<div class="alert alert-warning">renderField() must be called with: renderField(array of items, field_name, ...) or renderField(item object, field object, ...)</div>';
			return null;
		}

		// If $method (e.g. display method) is already created,
		// then return the field object without recreating the method
		if ( is_object($_field) && isset($_field->{$method}) ) return $_field;

		// Single item call
		if (!is_array($_item))
		{
			$all_items = array( & $_item );
			$field_name = $_field->name;
			$first_item_field = & $_field;
		}

		// Multi-item call
		else
		{
			$all_items = & $_item ;
			$field_name = $_field;

			$first_item_field = false;
			foreach($all_items as $item)
			{
				if (isset($item->fields[$field_name]))
				{
					$first_item_field = & $item->fields[$field_name];
					break;   // found break out, field found
				}
			}
			if (!$first_item_field) return null;  // none of the items has the field, return
		}


		// Skip items that have already created the given 'method' for this 'field' and for the given view
		// we also use VIEW so that we can reder different displays of the field e.g. item VIEW and module view
		$items = array();
		foreach ($all_items as $_item_)
		{
			// Commented out, TODO: examine if we can return cached value here !!
			//if (isset($_created[$view][$method][$field_name][$_item_->id])) continue;  // Skip this item
			$items[] = $_item_;
			$_created[$view][$method][$field_name][$_item_->id] = 1;
		}

		// Check if item array is empty (all items already rendered)
		if (empty($items))
		{
			return !is_object($_field) ? null : $_field;
		}


		// ***********************************************************************************************************
		// Create field parameters (and values) in an optimized way, and also apply Type Customization for CORE fields
		// ***********************************************************************************************************
		foreach($items as $item)
		{
			// Only rendering 1 item the field object was given, skip current item if it does not have the desired field
			if (is_object($_field))
				$field = $_field;
			else if ( isset($item->fields[$field_name]) )
				$field = $item->fields[$field_name];
			else
				continue;

			$field->item_id = (int)$item->id;  // Some code may make use of this

			// CHECK IF only rendering single field object for a single item  -->  thus we need to use custom values if these were given !
			// NOTE: values are overwritten by onDisplayCoreFieldValue() of CORE fields, and only used by onDisplayFieldValue() of CUSTOM fields

			// CUSTOM VALUEs give for single field rendering, TODO (maybe): in future we may make values an array indexed by item ID
			if ( is_object($_field) && $values!==null )
			{
				$field->value = $values;
			}

			// CUSTOM VALUEs not given or rendering multiple items
			else if (!isset($field->value))
			{
				$field->value = isset($item->fieldvalues[$field->id]) ? $item->fieldvalues[$field->id] : array();
			}

			FlexicontentFields::loadFieldConfig($field, $item);
		}


		// **********************************************
		// Return no access message if user has no ACCESS
		// **********************************************

		// Calculate has_access flag if it is missing ... FLEXI_ACCESS ... no longer supported here ...
		if ( !isset($first_item_field->has_access) ) {
			$first_item_field->has_access = in_array($first_item_field->access, $aid);
		}
		if ( !$first_item_field->has_access )
		{
			// Get configuration out of the field of the first item, any CONFIGURATION that is different
			// per content TYPE, must not use this, instead it must be retrieved inside the item loops
			$show_acc_msg = $first_item_field->parameters->get('show_acc_msg', 0);
			$no_acc_msg = $first_item_field->parameters->get('no_acc_msg');
			$no_acc_msg = JText::_( $no_acc_msg ? $no_acc_msg : 'FLEXI_FIELD_NO_ACCESS');
			foreach($items as $item)
			{
				// Only rendering 1 item the field object was given, skip current item if it does not have the desired field
				if (is_object($_field))
					$field = $_field;
				else if ( isset($item->fields[$field_name]) )
					$field = $item->fields[$field_name];
				else
					continue;

				// Only add no access message if field has a value
				if (!empty($field->value))
					$field->$method = $show_acc_msg ? '<span class="fc-noauth fcfield_inaccessible_'.$field->id.'">'.$no_acc_msg.'</span>' : '';
				else
					$field->$method = '';
			}

			// Return field only if single item was given (with a field object)
			return !is_object($_field) ? null : $_field;
		}


		// ***************************************************************************************************
		// Create field HTML by calling the appropriate DISPLAY-CREATING field plugin method.
		// NOTE 1: We will not pass the 'values' method parameter to the display-creating field method,
		//         instead we have set it above as the 'value' field property
		// NOTE 2: For CUSTOM fields the 'values' method parameter is prefered over the 'value' field property
		//         For CORE field, both the above ('values' method parameter and 'value' field property) are
		//         ignored and instead the other method parameters are used, along with the ITEM properties
		// ****************************************************************************************************
		// Log content plugin and other performance information

		$print_logging_info = $cparams->get('print_logging_info');
		if ($print_logging_info)  global $fc_run_times;
		if ($print_logging_info)  $start_microtime = microtime(true);

		if ($first_item_field->iscore == 1)  // CORE field
		{
			//$results = $dispatcher->trigger('onDisplayCoreFieldValue', array( &$_field, $_item, &$_item->parameters, $_item->tags, $_item->cats, $_item->favs, $_item->fav, $_item->vote ));
			//FLEXIUtilities::call_FC_Field_Func('core', 'onDisplayCoreFieldValue', array( &$_field, & $_item, &$_item->parameters, $_item->tags, $_item->cats, $_item->favs, $_item->fav, $_item->vote, null, $method ) );
			$items_params = null;
			FLEXIUtilities::call_FC_Field_Func('core', 'onDisplayCoreFieldValue', array( &$_field, &$items, &$items_params, false, false, false, false, false, null, $method ) );
		}
		else                      // NON CORE field
		{
			// DOES NOT support multiple items YET, do it 1 at a time
			foreach($items as $item)
			{
				// Only rendering 1 item the field object was given, skip current item if it does not have the desired field
				if (is_object($_field))
					$field = $_field;
				else if ( isset($item->fields[$field_name]) )
					$field = $item->fields[$field_name];
				else
					continue;

				//$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $item ));
				FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayFieldValue', array(&$field, $item, null, $method) );
				if ($field->parameters->get('use_ingroup', 0) && empty($field->ingroup) && is_array($field->$method)) $field->$method = implode('', $field->$method);
			}
		}
		if ($print_logging_info) {
			$field_render_time = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			if ( isset($fc_run_times['render_subfields'][$item->id."_".$field->id]) ) {
				$field_render_time = $field_render_time - $fc_run_times['render_subfields'][$item->id."_".$field->id];
				@$fc_run_times['render_subfields'][$field->field_type] += $fc_run_times['render_subfields'][$item->id."_".$field->id];
				unset($fc_run_times['render_subfields'][$item->id."_".$field->id]);
			}
			@$fc_run_times['render_field'][$field->field_type] += $field_render_time;
		}


		// *****************************************
		// Trigger content plugins on the field text
		// *****************************************

		$skip_trigger_plgs = $method === 'csv_export' ? true : $skip_trigger_plgs;

		if ( !$skip_trigger_plgs && !isset($_trigger_plgs_ft[$field_name]) )
		{
			$_t = $first_item_field->parameters->get('trigger_onprepare_content', 0);
			if ($request_view=='category' && $view=='category') $_t = $_t && $first_item_field->parameters->get('trigger_plgs_incatview', 1);
			$_trigger_plgs_ft[$field_name] = $_t;
		}

		// DOES NOT support multiple items, do it 1 at a time
		if ( !$skip_trigger_plgs && $_trigger_plgs_ft[$field_name] )
		{
			foreach($items as $item)
			{
				// Only rendering 1 item the field object was given, skip current item if it does not have the desired field
				if (is_object($_field))
					$field = $_field;
				else if ( isset($item->fields[$field_name]) )
					$field = $item->fields[$field_name];
				else
					continue;

				if ($print_logging_info)  $start_microtime = microtime(true);
				FlexicontentFields::triggerContentPlugins($field, $item, $method, $view, $event_row);
				if ( $print_logging_info ) @$fc_run_times['content_plg'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			}
		}

		// Return field only if single item was given (with a field object)
		return !is_object($_field) ? null : $_field;
	}


	/**
	 * Method to selectively trigger content plugins for the text of the specified field
	 *
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	static function triggerContentPlugins(&$field, &$item, $method, $view=FLEXI_ITEMVIEW, $event_row = false)
	{
		$debug = false;
		static $_plgs_loaded = array();
		static $_fields_plgs = array();

		static $_initialize = false;
		static $_view, $_option;
		static $dispatcher, $fcdispatcher;

		$jinput = JFactory::getApplication()->input;

		//$flexiparams = JComponentHelper::getParams('com_flexicontent');
		//$print_logging_info = $flexiparams->get('print_logging_info');
		// Log content plugin and other performance information
		//if ($print_logging_info) 	global $fc_run_times;

		if (!$_initialize)
		{
			// Include com_content helper files, these are needed by some content plugins
			require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
			FLEXI_J40GE
				? require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'Helper'.DS.'QueryHelper.php')
				: require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'query.php');

			// some request and other variables
			$_view   = $jinput->get('view', '', 'cmd');
			$_option = $jinput->get('option', '', 'cmd');
			$_initialize = true;

			// ***********************************************************************
			// We use a custom Dispatcher to allow selective Content Plugin triggering
			// ***********************************************************************
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'dispatcher.php');
			$dispatcher   = JEventDispatcher::getInstance();
			$fcdispatcher = FCDispatcher::getInstance_FC($debug);
		}

		// Use limitstart only for maintext core field
		$is_maintext = $field->iscore && $field->field_type === 'maintext';
		$limitstart = $is_maintext
			? $jinput->get('limitstart', 0, 'int')
			: null;

		// CASE: FLEXIcontent item view:
		// Set triggering 'context' to 'com_content.article', (and also set the 'view' request variable)
		if ($view == 'item')
		{
			$jinput->set('view', 'article');
		  $context = 'com_content.article';

			// Needed by legacy non-updated plugins
			!FLEXI_J40GE ? JRequest::setVar('view', 'article') : null;
		}

		// ALL OTHER CASES: (FLEXIcontent category, FLEXIcontent module, etc),
		// Set triggering 'context' to 'com_content.category', (and also set the 'view' request variable)
		else
		{
			$jinput->set('view', 'category');
		  $context = 'com_content.category';

			// Needed by legacy non-updated plugins
			!FLEXI_J40GE ? JRequest::setVar('view', 'category') : null;
		}

		// Set the 'option' to 'com_content' but set a flag 'isflexicontent' to indicate triggering from inside FLEXIcontent ... code
		$jinput->set('option', 'com_content');
		$jinput->set('isflexicontent', 'yes');

		// Needed by legacy non-updated plugins
		!FLEXI_J40GE ? JRequest::setVar('option', 'com_content') : null;


		if ($debug) echo "<br><br>Executing plugins for <b>".$field->name."</b>:<br>";

		if (empty($_fields_plgs[$field->name]))
		{
			// Make sure the necessary plugin are already loaded, but do not try to load them again since this will harm performance
			if (!$field->parameters->get('plugins'))
			{
				$_plgs = null;

				if (empty($_plgs_loaded['__ALL__']))
				{
					JPluginHelper::importPlugin('content', $plugin = null, $autocreate = true, $dispatcher);
					$_plgs_loaded['__ALL__'] = 1;
				}
			}
			else
			{
				$_plgs = $field->parameters->get('plugins');
				$_plgs = $_plgs ? $_plgs : array();

				// Compatibility because old versions did not JSON encode the parameters
				$_plgs = is_array($_plgs) ? $_plgs : explode('|', $_plgs);

				if (empty($_plgs_loaded['__ALL__']))
				{
					foreach ($_plgs as $_plg)
					{
						if (empty($_plgs_loaded[$_plg]))
						{
							JPluginHelper::importPlugin('content', $_plg, $autocreate = true, $dispatcher);
							$_plgs_loaded[$_plg] = 1;
						}
					}
				}
			}

			$_fields_plgs[$field->name] = $_plgs;
		}

		$plg_arr = $_fields_plgs[$field->name];


		/**
		 * Create record object to be used for plugin triggering
		 */

		// A row was given by 3rd party extension, use its data !
		if ($event_row)
		{
			$record = clone($event_row);
		}

		// Create a new record object
		else
		{
			$record = new stdClass;
		}


		/**
		 * Initialize Record object  to be used for plugin triggering
		 */

		// Field's display HTML will be used as text of plugin triggering
		$record->text = isset($field->{$method}) ? $field->{$method} : '';

		// Needed by some plugins that do not use or clear ->text property
		$record->introtext = $record->text;

		// Some plugins expect this
		if (isset($item->readmore_link))
		{
			$record->readmore_link = $item->readmore_link;
		}


		/**
		 * Set needed record properties, expected by plugins
		 */
		$record->title = $item->title;
		$record->language = $item->language;
		$record->created_by = $item->created_by;

		$record->id = $item->id;
		$record->slug = isset($item->slug)
			? $item->slug
			: (isset($record->slug) ? $record->slug : $item->id);

		$record->catid = $item->catid;
		$record->catslug = isset($item->categoryslug)
			? $item->categoryslug
			: (isset($record->catslug) ? $record->catslug : $item->catid);

		$record->state = $item->state;
		$record->access = $item->access;
		$record->sectionid = 0;

		$record->fieldid = $field->id;
		$record->type_id = $item->type_id;


		/**
		 * Trigger content plugins on field's HTML display, as if they were a "joomla article"
		 */
		$results = $fcdispatcher->trigger('onContentPrepare', array ($context, &$record, &$item->parameters, $limitstart), $plg_arr);

		// Get pluing triggering result
		$field->{$method} = $record->text;


		/**
		 * Restore state
		 */

		// Restore 'view' and 'option' request variables
		$jinput->set('view', $_view);
		$jinput->set('option', $_option);

		// Needed by legacy non-updated plugins
		!FLEXI_J40GE ? JRequest::setVar('view', $_view) : null;
		!FLEXI_J40GE ? JRequest::setVar('option', $_option) : null;
	}


	/**
	 * Method to get the fields in their positions
	 *
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	static function &renderPositions(&$items, $view = FLEXI_ITEMVIEW, $params = null, $use_tmpl = true, & $_rendered = array())
	{
		if (!$items) return;
		if (!$params) return $items;

		if ($view == 'category')			$layout = 'clayout';
		if ($view == FLEXI_ITEMVIEW)	$layout = 'ilayout';

		$app = JFactory::getApplication();
		$request_view = $app->input->get('view', '', 'cmd');

		// Field's source code, can use this HTTP request variable, to detect who rendered the fields (e.g. they can detect rendering from 'module')
		$app->input->set('flexi_callview', $view);

		if ( $use_tmpl && ($view == 'category' || $view == FLEXI_ITEMVIEW) )
		{
		  $fbypos = flexicontent_tmpl::getFieldsByPositions($params->get($layout, 'default'), $view);
		  //$onDemandOnly = false;
		}

		// $view == 'module', or other
		else {
			// Create a fake template position, for fields defined via parameters
		  $fbypos[0] = new stdClass();
		  $fbypos[0]->fields = explode(',', $params->get('fields'));
		  $fbypos[0]->methods = explode(',', $params->get('methods'));
		  $fbypos[0]->position = $view;
		  //$onDemandOnly = true;
		}

		$always_create_fields_display = $params->get('always_create_fields_display',0);

		// Render some fields by default, this is done for compatibility reasons, but avoid rendering these fields again (2nd time),
		// since some of them may also be in template positions. NOTE: this is not needed since renderField() should detect this case
		if ( /*!$onDemandOnly &&*/  $always_create_fields_display != 3) { // value 3 means never create for any view (blog template incompatible)

			$item = reset($items); // get the first item ... so that we can get the name of CORE fields out of it

		  // 'description' item field is implicitly used by category layout of some templates (blog), render it
		  $custom_values = null;
		  if ($view == 'category') {
		    if (isset($item->fields['text']) && !isset($_rendered['ALL']['text'])) {
		    	$_field_name_ = 'text';
		    	FlexicontentFields::renderField($items, $_field_name_, $custom_values, $method='display', $view);
		    }
		    $_rendered['ALL']['text'] = 1;
		  }
			// 'core' item fields are IMPLICITLY used by some item layout of some templates (blog), render them
			else if ($view == FLEXI_ITEMVIEW) {
				foreach ($item->fields as $field) {
					if ($field->iscore && !isset($_rendered['ALL'][$field->name])) {
						$_field_name_ = $field->name;
						FlexicontentFields::renderField($items, $_field_name_, $custom_values, $method='display', $view);
					}
				}
		    $_rendered['ALL']['core'] = 1;
			}
		}


		// *** RENDER fields on DEMAND, (if present in template positions)
		foreach ($fbypos as $pos) {
		  // RENDER fields if they are present in a template position (or in a dummy template position ... e.g. when called by module)
			foreach ($pos->fields as $c => $f) {

				// CORE/CUSTOM: Render field (if already rendered above, the function will return result immediately)
				$method = (isset($pos->methods[$c]) && $pos->methods[$c]) ? $pos->methods[$c] : 'display';

				// Render ANY CORE field with single call for all items, CORE fields are assigned to ALL types,
				// try to get field out of first item, if it does not exist, then field is a CUSTOM field
				$item = reset($items);
				$field = @ $item->fields[$f];

				if ($field && $field->iscore)
				{
					// Check if already rendered
					if ( !isset($_rendered['ALL']['core']) && !isset($_rendered['ALL'][$f]) )
					{
						// No custom values for CORE fields, values are decided inside the CORE field
						$values = null;
						FlexicontentFields::renderField($items, $f, $values, $method, $view);
					}
					$_rendered['ALL'][$f] = 1;
				}

				// Render ANY CUSTOM field with per item call
				// *** TODO: (future optimization) render a field at once for ALL ITEMs of SAME content type
				else foreach ($items as $item)
				{
					// Check that field with given name: $f exists for current item (AKA, that it is assigned to the item's type)
					if ( !isset($item->fields[$f]) )  continue;

					// Check if already rendered
					if ( isset($_rendered['ALL'][$f]) || isset($_rendered[$item->id][$f]) ) continue;

					// Get field and field values, currently, custom field values can be passed only for CUSTOM fields, OR versioned CORE/CUSTOM fields too ...
					$field  = $item->fields[$f];
					$values = isset($item->fieldvalues[$field->id]) ? $item->fieldvalues[$field->id] : array();

					// Render the field's display
					$field 	= FlexicontentFields::renderField($item, $field, $values, $method, $view);
					$_rendered[$item->id][$f] = 1;
				}

				foreach ($items as $item)
				{
					// Check that field with given name: $f exists for current item (AKA, that it is assigned to the item's type)
					if ( !isset($item->fields[$f]) )  continue;
					$field = $item->fields[$f];

					// Skip field if empty display was produced
					if ( !isset($field->display) || !strlen($field->display) ) continue;

					// Set field display HTML/data in the template position,
					if (!isset($item->positions[$pos->position]))
						$item->positions[$pos->position] = new stdClass();
					$item->positions[$pos->position]->{$f} = new stdClass();

					$item->positions[$pos->position]->{$f}->id				= $field->id;
					$item->positions[$pos->position]->{$f}->id				= $field->id;
					$item->positions[$pos->position]->{$f}->name			= $field->name;
					$item->positions[$pos->position]->{$f}->label		= $field->parameters->get('display_label') ? $field->label : '';
					$item->positions[$pos->position]->{$f}->display	= $field->display;
				}
			}
		}
		return $items;
	}


	/**
	 * Method to get the values of the fields for multiple items at once
	 *
	 * @access private
	 * @return object
	 * @since 1.5
	 */
	static function _getCustomValues(&$items, $view = FLEXI_ITEMVIEW)
	{
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->created_by);
		$version = $versioned_item ? $items[0]->version_id : 0;

		$item_ids = array();
		foreach ($items as $item) $item_ids[] = $item->id;

		$db = JFactory::getDbo();
		$query = 'SELECT field_id, value, item_id, valueorder, suborder'
				.( $version ? ' FROM #__flexicontent_items_versions':' FROM #__flexicontent_fields_item_relations')
				.' WHERE item_id IN (' . implode(',', $item_ids) .')'
				.( $version ? ' AND version=' . (int)$version:'')
				.' AND value > "" '
				.' ORDER BY item_id, field_id, valueorder, suborder'  // first 2 parts are not needed ...
				;
		$db->setQuery($query);
		$values = $db->loadObjectList();

		$fieldvalues = array();
		if ($values) foreach ($values as $v) {
			$fieldvalues[$v->item_id][$v->field_id][$v->valueorder - 1][$v->suborder - 1] = $v->value;
		}
		foreach ($fieldvalues as & $iv) {
			foreach ($iv as & $fv) {
				foreach ($fv as & $ov) {
					if (count($ov) == 1) $ov = reset($ov);
				}
				unset($ov);
			}
			unset($fv);
		}
		unset($iv);
		return $fieldvalues;
	}


	/**
	 * Method to get the custom field values for multiple items at once
	 *
	 * @access public
	 * @return array indexed by item IDs, and then index by field names
	 * @since 3.1.2
	 */
	static function getCustomFieldValues(&$items, $view = FLEXI_ITEMVIEW)
	{
		$db = JFactory::getDbo();

		$query = $db->getQuery(true)
			->select('*')
			->from('#__flexicontent_fields');

		$fields = $db->setQuery($query)->loadObjectList('id');
		$custom = FlexicontentFields::_getCustomValues($items, $view);

		$data = array();

		foreach($custom as $item_id => $fdata)
		{
			foreach($fdata as $fid => $fvalues)
			{
				if ( !isset($fields[$fid]) ) continue;

				// Make sure field values is an array
				if (!is_array($fvalues))
				{
					$fvalues = strlen($fvalues) ? array($fvalues) : array();
				}

				// Unserialize values already serialized values
				foreach ($fvalues as $i => $val)
				{
					$array = flexicontent_db::unserialize_array($val, $force_array=false, $force_value=false);

					if ($array !== false)
					{
						$fvalues[$i] = $array;
					}
				}

				$data[$item_id][$fields[$fid]->name] = $fvalues;
			}
		}

		return $data;
	}


	/**
	 * Method to get the tags
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getTags(&$items, $view = FLEXI_ITEMVIEW)
	{
		$db = JFactory::getDbo();

		// ***************************************************************
		// SPECIAL CASE for versioned fields in items view when previewing
		// ***************************************************************

		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->tags);
		if ($versioned_item)
		{
			$item = $items[0];
			if ( !count($item->tags) ) return array();
			$item->tags = ArrayHelper::toInteger($item->tags);

			$query 	= 'SELECT DISTINCT t.id, t.name, CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
				. ' FROM #__flexicontent_tags AS t'
				. ' WHERE t.id IN (' . implode(',', $item->tags) . ')'
				. ' AND t.published = 1';

			$db->setQuery( $query );
			$tags = $db->loadObjectList();

			$taglists[$item->id] = array_reverse( $tags );
			return $taglists;
		}


		// *************************
		// Get itemid to tagid pairs
		// *************************

		$cids = array();
		foreach ($items as $item)
		{
			$cids[] = $item->id;
		}

		if (empty($cids)) return array();
		$cids = ArrayHelper::toInteger($cids);

		$query = 'SELECT t.tid, t.itemid'
			. ' FROM #__flexicontent_tags_item_relations AS t'
			. ' WHERE t.itemid IN (' . implode(',', $cids) .')';
		$db->setQuery( $query );
		$item_tagids = $db->loadObjectList();

		if ( empty($item_tagids) ) return array();


		// ***************************
		// Get single copy of tag data
		// ***************************

		$query = 'SELECT DISTINCT t.id, t.name, CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
			. ' FROM #__flexicontent_tags AS t'
			. ' JOIN #__flexicontent_tags_item_relations AS i ON i.tid = t.id'
			. ' WHERE i.itemid IN (' . implode(',', $cids) . ')'
			. ' AND t.published = 1';

		$db->setQuery( $query );
		$tags = $db->loadObjectList('id');

		// Create an array of every item's tag data
		$taglists = array();
		foreach ($item_tagids as $it)
		{
			if ( !empty($tags[$it->tid]) )
			{
				$taglists[$it->itemid][] = $tags[$it->tid];
			}
		}

		// Workaround for not having "order" column in tags assignments table (should work in MySql, but no guarantee)
		foreach ($taglists as $itemid => $taglist)
		{
			$taglists[$itemid] = array_reverse($taglists[$itemid]);
		}

		return $taglists;
	}

	/**
	 * Method to get the categories
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getCategories(&$items, $view = FLEXI_ITEMVIEW)
	{
		$db = JFactory::getDbo();

		// ***************************************************************
		// SPECIAL CASE for versioned fields in items view when previewing
		// ***************************************************************

		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->categories);
		if ($versioned_item)
		{
			$item = $items[0];
			$item->categories = ArrayHelper::toInteger($item->categories);

			$query = 'SELECT DISTINCT c.id, c.title, CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
				. ' FROM #__categories AS c'
				. ' WHERE c.id IN (' . implode(',', $item->categories) . ')'
				//. ' AND c.published = 1'   // Get unpublished cats too
				;
			$db->setQuery( $query );
			$cats = $db->loadObjectList();

			$catlists[$item->id] = array_reverse( $cats );
			return $catlists;
		}

		// *************************
		// Get itemid to tagid pairs
		// *************************

		$cids = array();
		foreach ($items as $item)
		{
			$cids[] = $item->id;
		}

		if (empty($cids)) return array();
		$cids = ArrayHelper::toInteger($cids);

		$query = 'SELECT c.catid, c.itemid'
			. ' FROM #__flexicontent_cats_item_relations AS c'
			. ' WHERE c.itemid IN (' . implode(',', $cids) .')';
		$db->setQuery( $query );
		$item_catids = $db->loadObjectList();

		if ( empty($item_catids) ) return array();


		// ***************************
		// Get single copy of cat data
		// ***************************

		$query = 'SELECT DISTINCT c.id, c.title, CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
			. ' FROM #__categories AS c'
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
			. ' WHERE rel.itemid IN (' . implode(',', $cids) . ')'
			//. ' AND c.published = 1'   // Get unpublished cats too
			;

		$db->setQuery( $query );
		$cats = $db->loadObjectList('id');

		// Create an array of every item's cat data
		$catlists = array();
		foreach ($item_catids as $ic)
		{
			if ( !empty($cats[$ic->catid]) )
			{
				$catlists[$ic->itemid][] = $cats[$ic->catid];
			}
		}
		return $catlists;
	}

	/**
	 * Method to get the nr of favourites
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getFavourites(&$items, $view = FLEXI_ITEMVIEW)
	{
		$db = JFactory::getDbo();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }

		// Favourites via DB
		$query 	= 'SELECT DISTINCT itemid, 1 AS favs FROM #__flexicontent_favourites'
				. " WHERE type = 0 AND itemid IN ('" . implode("','", $cids) . "')"
				;
		$db->setQuery($query);
		$favourites = $db->loadObjectList('itemid');

		// Also add favourites via cookie (Only current user is considered)
		$favs = flexicontent_favs::getInstance()->getRecords($_favs_type = 'item');

		foreach($favs as $itemid => $f)
		{
			$favourites[(int)$itemid] = (object) array('itemid' => (int) $itemid, 'favs' => 1);
		}

		return $favourites;
	}

	/**
	 * Method to get the favourites of an user
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getFavoured(&$items, $view = FLEXI_ITEMVIEW)
	{
		$user = JFactory::getUser();
		$db = JFactory::getDbo();

		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }

		// Favourites via DB
		$query 	= 'SELECT DISTINCT itemid, 1 AS fav FROM #__flexicontent_favourites'
			. ' WHERE type = 0 AND itemid IN (' . implode(',', $cids) . ')'
			. ' AND userid = ' . ((int)$user->id)
			;
		$db->setQuery($query);
		$favoured = $db->loadObjectList('itemid');

		// Also add favourites via cookie
		$favs = flexicontent_favs::getInstance()->getRecords($_favs_type = 'item');

		foreach($favs as $itemid => $f)
		{
			$favoured[(int)$itemid] = (object) array('itemid' => (int) $itemid, 'fav' => 1);
		}

		return $favoured;
	}

	/**
	 * Method to get the modifiers of the items
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getModifiers(&$items, $view = FLEXI_ITEMVIEW)
	{
		// This is fix for versioned field of modifier in items view when previewing
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->modified_by);

		$db = JFactory::getDbo();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }

		$query 	= 'SELECT i.id, u.name, u.username, u.email FROM #__content AS i'
			. ' LEFT JOIN #__users AS u ON '  .  ( $versioned_item ? 'u.id = '.$items[0]->modified_by : 'u.id = i.modified_by' )
			. " WHERE i.id IN ('" . implode("','", $cids) . "')"
			;
		$db->setQuery($query);
		$modifiers = $db->loadObjectList('id');

		return $modifiers;
	}

	/**
	 * Method to get the authors of the items
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getAuthors(&$items, $view = FLEXI_ITEMVIEW)
	{
		// This is fix for versioned fields in items view when previewing
		$versioned_item = count($items)==1 && !empty($items[0]->version_id) && !empty($items[0]->created_by);

		$db = JFactory::getDbo();
		$cids = array();
		foreach ($items as $item) { array_push($cids, $item->id); }

		$query 	= 'SELECT i.id, u.name, i.created_by_alias as alias, u.username, u.email FROM #__content AS i'
				. ' LEFT JOIN #__users AS u ON '  .  ( $versioned_item ? 'u.id = '.$items[0]->created_by : 'u.id = i.created_by' )
				. " WHERE i.id IN ('" . implode("','", $cids) . "')"
				;
		$db->setQuery($query);
		$authors = $db->loadObjectList('id');

		return $authors;
	}

	/**
	 * Method to get the types names of the items
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getTypenames(&$items, $view = FLEXI_ITEMVIEW)
	{
		$db = JFactory::getDbo();

		$type_ids = array();
		foreach ($items as $item) { $type_ids[$item->type_id]=1; }
		$type_ids = array_keys($type_ids);

		$query 	= 'SELECT id, name FROM #__flexicontent_types'
				. " WHERE id IN ('" . implode("','", $type_ids) . "')"
				;
		$db->setQuery($query);
		$types = $db->loadObjectList('id');

		$typenames = array();
		foreach ($items as $item) {
			$typenames[$item->id] = new stdClass();
			$typenames[$item->id]->name = isset($types[$item->type_id]) ? $types[$item->type_id]->name : 'without type';
		}

		return $typenames;
	}

	/**
	 * Method to get the votes of the items
	 *
	 * @access	private
	 * @return	object
	 * @since	1.5
	 */
	static function _getVotes(&$items, $view = FLEXI_ITEMVIEW)
	{
		$db = JFactory::getDbo();
		$cids = array();

		foreach ($items as $item)
		{
			array_push($cids, $item->id);
		}

		$query 	= 'SELECT * FROM #__content_rating'
				. " WHERE content_id IN ('" . implode("','", $cids) . "')"
				;
		$db->setQuery($query);
		$votes = $db->loadObjectList('content_id');

		$query 	= 'SELECT *, field_id as extra_id FROM #__flexicontent_items_extravote'
				. " WHERE content_id IN ('" . implode("','", $cids) . "')"
				;
		$db->setQuery($query);
		$extra_votes= $db->loadObjectList();

		// Assign each item 's extra votes to the item's votes as member variable "extra"
		foreach ($extra_votes as $extra_vote)
		{
			$votes[$extra_vote->content_id]->extra[$extra_vote->extra_id] = $extra_vote;
		}

		return $votes;
	}



	// ***********************************************************
	// Methods for creating field configuration in an OPTMIZED way
	// ***********************************************************

	// Method to create field parameters in an optimized way, and also apply Type Customization for CORE fields
	static function loadFieldConfig(&$field, &$item, $name='', $field_type='', $label='', $desc='', $iscore=1)
	{
		$app = JFactory::getApplication();
		$db = JFactory::getDbo();

		static $tparams = array();
		static $tinfo   = array();
		static $fdata   = array();

		static $no_typeparams = null;
		if ($no_typeparams)
		{
			$no_typeparams = new JRegistry();
		}

		static $is_form=null;

		if ($is_form===null)
		{
			$is_form = (
				in_array($app->input->getCmd('task', ''), array('add', 'edit')) &&
				$app->input->getCmd('view', '') === 'item' &&
				$app->input->getCmd('option', '') === 'com_flexicontent'
			) || (
				$app->input->getCmd('layout', '') === 'form' &&
				$app->input->getCmd('view', '') === 'article' &&
				$app->input->getCmd('option', '') === 'com_content'
			);
		}

		// Create basic field data if no field given
		if (!empty($name))
		{
			$field->iscore = $iscore;
			$field->name = $name;
			$field->field_type = $field_type;
			$field->label = $label;
			$field->description = $desc;
			$field->attribs = '';
		}

		// Get Content Type parameters if not already retrieved
		$type_id = $item
			? $item->type_id
			: 0;

		if ($type_id && ( !isset($tinfo[$type_id]) || !isset($tparams[$type_id]) ) )
		{
			$tinfo[$type_id] = $tparams[$type_id] = null;
			FlexicontentFields::_getTypeToCoreFieldParams ($type_id, $tinfo[$type_id], $tparams[$type_id]);
		}

		// Set Content Type parameters otherwise set empty defaults (e.g. new item form with not typeid set)
		$type_data_exist = $type_id && $tinfo[$type_id] && $tparams[$type_id] ;

		$typename   = $type_data_exist  ?  $tinfo[$type_id]['typename']    :  '';
		$typealias  = $type_data_exist  ?  $tinfo[$type_id]['typealias']   :  '';
		$tindex     = $type_data_exist  ?  $typename.'_'.$typealias        :  'no_type';
		if ($type_data_exist)  $typeparams = & $tparams[$type_id];  else  $typeparams = & $no_typeparams;


		// Create the (CREATED ONCE per field) SHARED object that will contain: (a) label, (b) description, (c) all (merged) field parameters
		// Create parameters once per custom field OR once per pair of:  Core FIELD type - Item CONTENT type
		if ( !isset($fdata[$tindex][$field->name]) )
		{
			if ( !$field->iscore || !$type_id )
			{
				// CUSTOM field or CORE field with no type
				$fdata[$tindex][$field->name] = new stdClass();
				$fdata[$tindex][$field->name]->parameters = new JRegistry($field->attribs);
				if ($field->field_type=='maintext' && $fdata[$tindex][$field->name]->parameters->get('trigger_onprepare_content', '')==='')
				{
					$fdata[$tindex][$field->name]->parameters->set('trigger_onprepare_content', 1);  // Default for maintext (description field) is to trigger plugins
				}
			}

			else
			{
				$pn_prefix = $field->field_type!='maintext' ? $field->name : $field->field_type;

				// Initialize an empty object, and create parameters object of the field
				$fdata[$tindex][$field->name] = new stdClass();
				$fdata[$tindex][$field->name]->parameters = new JRegistry($field->attribs);

				// SET a type specific label, description for the current CORE  field (according to current language)
				$field_label_type = $tparams[$type_id]->get($pn_prefix.'_label', '');
				$field_desc_type = $tparams[$type_id]->get($pn_prefix.($is_form ? '_desc' : '_viewdesc'), '');
				FlexicontentFields::_getLangSpecificValue ($type_id, $field_label_type, 'label', $fdata[$tindex][$field->name]);
				FlexicontentFields::_getLangSpecificValue ($type_id, $field_desc_type, 'description', $fdata[$tindex][$field->name]);

				// Override field parameters with Type specific Parameters
				if ( isset($tinfo[$type_id]['params'][$pn_prefix]) )
				{
					foreach ($tinfo[$type_id]['params'][$pn_prefix] as $param_name => $param_value)
					{
						$fdata[$tindex][$field->name]->parameters->set( $param_name, $param_value) ;
					}
				}

				// SPECIAL CASE: check if it exists a FAKE (custom) field that customizes CORE field per Content Type
				$query = "SELECT attribs, published FROM #__flexicontent_fields WHERE name=".$db->Quote($field->name."_".$typealias);
				$db->setQuery($query);  //echo $query;
				$data = $db->loadObject(); //print_r($data);
				if ($data && $data->published)
				{
					JFactory::getApplication()->enqueueMessage(__FUNCTION__."(): Please unpublish plugin with name: ".$field->name."_".$typealias." it is used for customizing a core field",'error');
				}

				// Finally merge custom field parameters with the type specific parameters ones
				if ($data)
				{
					$ts_params = new JRegistry($data->attribs);
					$fdata[$tindex][$field->name]->parameters->merge($ts_params);
				}
			}
		}

		// Set custom label, description or maintain default
		$field->label       =  isset($fdata[$tindex][$field->name]->label)        ?  $fdata[$tindex][$field->name]->label        :  $field->label;
		$field->description =  isset($fdata[$tindex][$field->name]->description)  ?  $fdata[$tindex][$field->name]->description  :  $field->description;
		$field->label       = JText::_($field->label);
		$field->description = JText::_($field->description);

		// Finally set field's parameters, but to clone ... or not to clone, better clone to allow customizations for individual item fields ...
		$field->parameters = clone($fdata[$tindex][$field->name]->parameters);

		return $field;
	}


	// Method to override PARAMETER VALUES with their Type Specific values
	static function _getTypeToCoreFieldParams ($type_id, & $tinfo, & $tparams) {

		static $core_field_names = null;
		if ( $core_field_names == null ) {
			$query = "SELECT field_type FROM #__flexicontent_fields WHERE iscore=1";
			//echo $query;
			$db = JFactory::getDbo();
			$db->setQuery($query);
			$core_field_names = $db->loadColumn();

			$core_field_names[] = 'maintext';
			$core_field_names = array_flip($core_field_names);
			unset($core_field_names['text']);
		}

		$query = 'SELECT t.attribs, t.name, t.alias FROM #__flexicontent_types AS t WHERE t.id = ' . $type_id;
		$db = JFactory::getDbo();
		$db->setQuery($query);
		$typedata = $db->loadObject();
		if ( $typedata ) {
			$tinfo['typename']  = $typedata->name;
			$tinfo['typealias'] = $typedata->alias;
			$tparams = new JRegistry($typedata->attribs);

			$_tparams = $tparams->toArray();
			$tinfo['params'] = array();

			foreach ($_tparams as $param_name => $param_value) {
				$res = preg_split('/_/', $param_name, 2);
				if ( count($res) < 2 ) continue;

				$o_field_type = $res[0];  $o_param_name = $res[1];
				if ( !isset($core_field_names[$o_field_type]) ) continue;

				//echo "$o_field_type _ $o_param_name = $param_value <br>\n";
				$skipparam = false;

				if ( strlen($param_value) ) {
					/*$skipparam = in_array($o_param_name, array('label','desc','viewdesc'));
					if ($skipparam) continue;*/
					$tinfo['params'][$o_field_type][$o_param_name] = $param_value;
					//echo "$o_field_type _ $o_param_name = $param_value <br>\n";
				}
			}
			//echo "<pre>"; print_r($tinfo['params']); echo "</pre>";
		}
	}


	// Method get a language specific value from given Content Type (or other) Data
	static function _getLangSpecificValue ($type_id, $type_prop_value, $prop_name, & $fdata)
	{
		//--. Get a 2 character language tag
		static $lang = null;
		$lang = substr(JFactory::getLanguage()->getTag(), 0,2);

		// --. SET a type specific label for the current field

		// a. Try field label to get for current language
		$result = preg_match("/(\[$lang\])=([^[]+)/i", $type_prop_value, $matches);
		if ($result)
		{
			$fdata->{$prop_name} = $matches[2];
		}

		else if ($type_prop_value)
		{
			// b. Try to get default for all languages
			$result = preg_match("/(\[default\])=([^[]+)/i", $type_prop_value, $matches);
			if ($result)
			{
				$fdata->{$prop_name} = $matches[2];
			}

			// c. Check that no languages specific string are defined
			else
			{
				$result = preg_match("/(\[??\])=([^[]+)/i", $type_prop_value, $matches);
				if (!$result) {
					$fdata->{$prop_name} = $type_prop_value;
				}
			}
		}

		// d. Maintain field 's default label
		else ;
	}



	// **************************************************************************************
	// Methods (a) for INDEXED FIELDs, and (b) for replacement field values / item properties
	// **************************************************************************************

	// Common method to get the column expressions used to create the field's elements
	static function indexedField_getColsExprs($field, $item, $field_elements)
	{
		$q = preg_replace('/\b(as\s+)(value|text|image|valgrp|state)\b\s*(,)?\s*/i', 'AS \2\3 ', $field_elements);
		$q = preg_replace('/^\s*(select)\b\s*/i', '', $q);
		$q = substr($q, 0, stripos($q, 'from'));
		$q = preg_split('/(AS value,?\s*|AS text,?\s*|AS image,?\s*|AS valgrp,?\s*|AS state,?\s*)/i', $q, -1, PREG_SPLIT_DELIM_CAPTURE);
		array_pop($q);

		$cols = array();
		$step = 0;
		$prev = '';
		foreach($q as $d)
		{
			if ($step % 2 == 1)
			{
				$d = preg_replace('/\b(as\s+)(value|text|image|valgrp|state)\b\s*(,)?\s*/i', '\2', $d);
				$cols[$d] = $prev;
			}
			$prev = $d;
			$step++;
		}
		return $cols;
	}


	// Common method to get the allowed element values (field values with index,label,... properties) for fields that use indexed values
	static function indexedField_getElements($field, $item, $extra_props=array(), &$item_pros=true, $is_filter=false, $and_clause=false)
	{
		static $_elements_cache = array();

		// For fields that use this parameter
		$sql_mode = (int) $field->parameters->get('sql_mode', 0);
		$canCache = ! $field->parameters->get('nocache');

		if ($canCache && isset($_elements_cache[$field->id][$is_filter]))
		{
			return $_elements_cache[$field->id][$is_filter];
		}

		$field_elements = $field->parameters->get('field_elements', '') ;
		$lang_filter_values = $field->parameters->get('lang_filter_values', 1);

		$default_extra_props = array('image', 'valgrp', 'state');

		if ($is_filter)
		{
			$filter_customize_options = (int) $field->parameters->get('filter_customize_options', 0);
			$filter_custom_options    = $field->parameters->get('filter_custom_options', '');

			// Custom query for value retrieval
			if ($filter_customize_options && $filter_custom_options)
			{
				$sql_mode = $filter_customize_options === 1;
				$field_elements = $filter_custom_options;
			}

			// Default query for value retrieval
			elseif (!$field_elements)
			{
				$sql_mode = 1;
				$field_elements = 'SELECT DISTINCT value, value as text '
					. ' FROM #__flexicontent_fields_item_relations '
					. ' WHERE field_id={field->id} AND value != ""'
				;
			}

			// Set parameters may be used later
			$field->parameters->set('sql_mode', $sql_mode);
			$field->parameters->set('field_elements', $field_elements);
		}

		// TODO: examine this in combination with canCache
		//$field_elements = FlexicontentFields::replaceFieldValue( $field, $item, $field_elements, 'field_elements' );


		// SQL mode, parameter field_elements contains an SQL query
		if ($sql_mode)
		{
			$db = JFactory::getDbo();

			// Get/verify query string, check if item properties and other replacements are allowed and replace them
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			$query = FlexicontentFields::doQueryReplacements($field_elements, $field, $item, $item_pros, $canCache);

			if ($query)
			{
				$query = preg_replace('/_valgrp_in_/ui', ($and_clause ? $and_clause : ' 1 '), $query);
			}

			// Execute SQL query to retrieve the field value - label pair, and any other extra properties
			$results = false;

			if ($query)
			{
				$results = $db->setQuery($query)->loadObjectList('value');
			}

			if ($results && $lang_filter_values)
			{
				foreach ($results as $val=>$result)
				{
					$results[$val]->text  = JText::_($result->text);
				}
			}

			// !! CHECK: DB query failed or produced an error (AN EMPTY ARRAY IS NOT AN ERROR)
			if (!$query || !is_array($results))
			{
				if ($canCache && !$and_clause)
				{
					$_elements_cache[$field->id][$is_filter] = false;
				}

				return false;
			}
		}


		// Elements mode, parameter field_elements contain list of allowed values
		else
		{
			// Parse the elements used by field unsetting last element if empty
			$listelements = preg_split("/[\s]*%%[\s]*/", $field_elements);

			if (empty($listelements[count($listelements)-1]))
			{
				unset($listelements[count($listelements)-1]);
			}

			$props_needed = 2 + count($extra_props);

			// Split elements into their properties: value, label, extra_prop1, extra_prop2
			$listarrays = array();
			$results = array();

			foreach ($listelements as $listelement)
			{
				$listelement_props  = preg_split("/[\s]*::[\s]*/", $listelement);

				// Compatibility with previously stored elements, ignore missing 'valgrp' and 'state'
				if (count($listelement_props) < $props_needed && count($listelement_props)==3 && $extra_props[1]=='valgrp')  $listelement_props[] = null;
				if (count($listelement_props) < $props_needed && count($listelement_props)==4 && $extra_props[2]=='state')   $listelement_props[] = null;

				if (count($listelement_props) < $props_needed)
				{
					echo "Error in field: ".$field->label." while splitting element: ".$listelement." properties needed: ".$props_needed." properties found: ".count($listelement_props);
					return ($_elements_cache[$field->id][$is_filter] = false);
				}

				$val = $listelement_props[0];
				$results[$val] = new stdClass();
				$results[$val]->value = $listelement_props[0];
				$results[$val]->text  = $lang_filter_values ? JText::_($listelement_props[1]) : $listelement_props[1];
				$el_prop_count = 2;
				$_props = !empty($extra_props) ? $extra_props : $default_extra_props;

				// Optional extra properties for fields that use them
				foreach ($_props as $extra_prop)
				{
					$results[$val]->{$extra_prop} = isset($listelement_props[$el_prop_count]) ? $listelement_props[$el_prop_count] : null;
					$el_prop_count++;
				}
			}
		}

		// Return found elements, caching them if possible (if no item specific elements are used)
		if ($canCache && !$and_clause)
		{
			$_elements_cache[$field->id][$is_filter] = & $results;
		}

		return $results;
	}


	// Common method to map element value INDEXES to value objects for fields that use indexed values
	static function indexedField_getValues(&$field, &$elements, $value_indexes, $prepost_prop='text')
	{
		// Check for empty values
		if ( !is_array($value_indexes) && !strlen($value_indexes) )
		{
			return array();
		}

		// Make sure indexes is an array
		$value_indexes = !is_array($value_indexes)
			? array($value_indexes)
			: $value_indexes;

		$pretext='';
		$posttext='';
		if ( $prepost_prop )
		{
			$pretext  = $field->parameters->get( 'pretext', '' ) ;
			$posttext = $field->parameters->get( 'posttext', '' ) ;
			$remove_space = $field->parameters->get( 'remove_space', 0 ) ;

			$pretext 	= $remove_space ? $pretext  : $pretext . ' ';
			$posttext	= $remove_space ? $posttext : ' ' . $posttext;
		}

		// Handle multiple sub-values per value
		if (is_array(reset($value_indexes)))
		{
			$_v = array();
			foreach($value_indexes as $v) $_v[] = $v;
			$value_indexes = $v;
		}

		// Get the labels of used values in an display[] array
		$values = array();
		foreach($value_indexes as $val_index)
		{
			if ( !strlen($val_index) ) continue;
			if ( !isset($elements[$val_index]) ) continue;

			$values[$val_index] = get_object_vars($elements[$val_index] );
			if ($prepost_prop)
			{
				$values[$val_index][$prepost_prop] = $pretext . $values[$val_index][$prepost_prop] . $posttext;
			}
		}

		return $values;
	}


	// Helper method to replace item properties for the SQL value mode for various fields
	static function doQueryReplacements(&$query, &$field, &$item, &$item_pros=true, &$canCache=null)
	{
		// ***
		// *** Replace item properties
		// ***

		preg_match_all("/{item->[0-9a-zA-Z_]+}/", $query, $matches);

		$canCache = count($matches[0]) == 0;
		if ( !$item_pros && count($matches[0]) )
		{
			$item_pros = count($matches[0]);
			return '';
		}

		// If needed replace item properties, loading the item if not already loaded
		if (count($matches[0]) && !$item)
		{
			if ( empty($field->item_id) ) return;

			$item = JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
			$item->load( $field->item_id );
		}

		foreach ($matches[0] as $replacement_tag)
		{
			$replacement_value = '$'.substr($replacement_tag, 1, -1);
			eval ("\$replacement_value = \"$replacement_value\";");
			$query = str_replace($replacement_tag, $replacement_value, $query);
		}


		// ***
		// *** Replace field properties
		// ***

		if ($field)
		{
			preg_match_all("/{field->[0-9a-zA-Z_]+}/", $query, $matches);
			foreach ($matches[0] as $replacement_tag)
			{
				$replacement_value = '$'.substr($replacement_tag, 1, -1);
				eval ("\$replacement_value = \" $replacement_value\";");
				$query = str_replace($replacement_tag, $replacement_value, $query);
			}
		}

		// ***
		// *** Other replacements: current user language
		// ***

		$query = str_replace("{curr_userlang_shorttag}", flexicontent_html::getUserCurrentLang($short_tag=true), $query);
		$query = str_replace("{curr_userlang_fulltag}", flexicontent_html::getUserCurrentLang($short_tag=false), $query);

		return $query;
	}


	// Helper method to replace a field value inside a given named variable of a given item/field pair
	static function replaceFieldValue( &$field, &$item, $variable, $varname, & $cacheable = false )
	{
		static $parsed = array();
		static $d;
		static $c;

		if (JFactory::getApplication()->isAdmin()) return '';

		// Parse field variable if not already parsed
		if ( !isset($parsed[$field->id][$varname]) )
		{
			$parsed[$field->id][$varname] = true;

			$result = preg_match_all("/\{\{([a-zA-Z_0-9-]+)(##)?([0-9]+)?(##)?([a-zA-Z_0-9-]+)?\}\}/", $variable, $field_matches);
			if ($result)
			{
				$d[$field->id][$varname]['fulltxt']   = $field_matches[0];
				$d[$field->id][$varname]['fieldname'] = $field_matches[1];
				$d[$field->id][$varname]['valueno']   = $field_matches[3];
				$d[$field->id][$varname]['propname']  = $field_matches[5];
			}
			else
			{
				$d[$field->id][$varname]['fulltxt']   = array();
				$d[$field->id][$varname]['valueno']   = false;
			}

			$result = preg_match_all("/\{\{(item->)([a-zA-Z_0-9]+)\}\}/", $variable, $field_matches);
			if ($result)
			{
				$c[$field->id][$varname]['fulltxt']   = $field_matches[0];
				$c[$field->id][$varname]['propname']  = $field_matches[2];
			}
			else
			{
				$c[$field->id][$varname]['fulltxt']   = array();
			}

			if ( !count($d[$field->id][$varname]['fulltxt']) && !count($c[$field->id][$varname]['fulltxt']) ) {
				$cacheable = true;
			}
		}


		// ***
		// *** Replace field values / field properties
		// ***

		foreach($d[$field->id][$varname]['fulltxt'] as $i => $fulltxt)
		{
			$fieldname = $d[$field->id][$varname]['fieldname'][$i];
			$valueno   = $d[$field->id][$varname]['valueno'][$i] ? (int) $d[$field->id][$varname]['valueno'][$i] : 0;
			$propname  = $d[$field->id][$varname]['propname'][$i] ? $d[$field->id][$varname]['propname'][$i] : '';

			$fieldid = @ $item->fields[$fieldname]->id;
			$value   = @ $item->fieldvalues[$fieldid][$valueno];

			if ( !$fieldid )
			{
				$value = 'Field with name: '.$fieldname.' not found';
				$variable = str_replace($fulltxt, $value, $variable);
				continue;
			}

			$is_indexable = $propname && preg_match("/^_([a-zA-Z_0-9]+)_$/", $propname, $prop_matches) && ($propname = $prop_matches[1]);
			if ($fieldid <= 14 )
			{
				if ($fieldid==13)
				{
					$value = @ $item->categories[$valueno]->{$propname};
				}
				else if ($fieldid==14)
				{
					$value = @ $item->tags[$valueno]->{$propname};
				}
			}

			else if ( $is_indexable )
			{
				if ( $propname!='value' ) // no need for value to retrieve custom elements
				{
					$extra_props = $propname!='text' ? array($propname) : array();  // this will work only if field has a single extra property
					$extra_props = array();
					if ( !isset($item->fields[$fieldname]->parameters) )
					{
						FlexicontentFields::loadFieldConfig($item->fields[$fieldname], $item);
					}
					$elements = FlexicontentFields::indexedField_getElements( $item->fields[$fieldname], $item, $extra_props );
					$value = @ $elements[$value]->{$propname};
				}
			}

			else if ( $propname )
			{
				$value = flexicontent_db::unserialize_array($value, $force_array=false, $force_value=false);
				$value = $value && isset($value[$propname]) ? $value[$propname] : '';
			}

			$variable = str_replace($fulltxt, $value, $variable);
			//echo "<pre>"; print_r($item->fieldvalues[$fieldid]); echo "</pre>"; echo "Replaced $fulltxt with ITEM field VALUE: $value <br>";
		}


		// ***
		// *** Replace item properties
		// ***

		foreach($c[$field->id][$varname]['fulltxt'] as $i => $fulltxt)
		{
			$propname = $c[$field->id][$varname]['propname'][$i];

			if ( !isset($item->{$propname}) )
			{
				$value = 'Item property with name: '.$propname.' not found';
				$variable = str_replace($fulltxt, $value, $variable);
				continue;
			}
			$value = $item->{$propname};

			$variable = str_replace($fulltxt, $value, $variable);
			//echo "<pre>"; echo "</pre>"; echo "Replaced $fulltxt with ITEM property VALUE: $value <br>";
		}

		// Return variable after all replacements
		return $variable;
	}





	// *********************************************************************
	// Methods for getting fields that support BASIC / ADVANCED search modes
	// *********************************************************************

	// Method to get various - SETs - of search fields, according to given limitations
	// Param 'search_type' : search, filter, all-search, dirty-search, dirty-nosupport, non-search
	static function getSearchFields($key='name', $indexer='advanced', $search_fields=null, $content_types=null, $load_params=true, $item_id=0, $search_type='all-search')
	{
		$db = JFactory::getDbo();
		static $sp, $nsp;

		switch ($search_type)
		{
			// All fields marked as text-searchable (also are published)
			case 'search':
				$where = $indexer === 'basic' ? ' f.issearch IN (1,2)' : ' f.isadvsearch IN (1,2) ';
				$where = '('.$where.' AND f.published = 1)';
				break;

			// All fields marked as filterable (also are published)
			case 'filter':
				$where = $indexer === 'basic' ? ' f.isfilter IN (1,2)' : ' f.isadvfilter IN (1,2) ';
				$where = '('.$where.' AND f.published = 1)';
				break;

			// ALL fields that must enter values in search index (also are published)
			case 'all-search':
				$where = $indexer === 'basic' ? ' f.issearch IN (1,2)' : ' ( f.isadvsearch IN (1,2) OR f.isadvfilter IN (1,2) )';
				$where = '('.$where.' AND f.published = 1)';
				break;

			// ONLY 'dirty' search fields (also are published)
			case 'dirty-search':
			case 'dirty-nosupport':
				$where = $indexer === 'basic' ? ' f.issearch = 2' : ' ( f.isadvsearch = 2 OR f.isadvfilter = 2 )';
				$where = '('.$where.' AND f.published = 1)';
				break;

			// ALL non-search fields (either OFF or unpublished)
			case 'non-search':
				$where = $indexer === 'basic' ? ' f.issearch IN (-1,0)' : ' ( f.isadvsearch IN (-1,0) AND f.isadvfilter IN (-1,0) )';
				$where = '('.$where.' OR f.published <> 0)';
				break;

			default:
				die(__FUNCTION__ . "(): unknown value for 'search_type' parameter"); // nothing to TODO
		}

		$where .=
			// Limit to given search fields list
			(!empty($search_fields) && is_array($search_fields) ? " AND f.name IN (" . implode('","', $search_fields) . ") " : "") .

			// Limit to given search fields list
			(!empty($search_fields) && is_string($search_fields) ? " AND f.name IN (" . $search_fields . ") " : "") .

			// Limit to given contnt types list
			(!empty($content_types) ? " AND ftr.type_id IN (" . implode(',', $content_types) . ") " : "");

		$query = 'SELECT f.*'
			. ' FROM #__flexicontent_fields AS f'
			. ' JOIN #__flexicontent_fields_type_relations AS ftr ON ftr.field_id = f.id'
			. ' WHERE '. $where
			. ' GROUP BY f.id'

			// If single type given then retrieve ordering for fields of this type
			. ' ORDER BY ' . ($content_types && count($content_types) === 1
				? ' ftr.ordering, f.name'
				: ' f.ordering, f.name'
			)
		;

		if (!isset($sp[$query]))
		{
			$fields = $db->setQuery($query)->loadObjectList($key);

			$sp_fields = array();
			$nsp_fields = array();

			foreach ($fields as $field_id => $field)
			{
				// Skip fields not being capable of advanced/basic search
				if ($indexer === 'basic')
				{
					if (! FlexicontentFields::getPropertySupport($field->field_type, $field->iscore, $search_type === 'filter' ? 'supportfilter' : 'supportsearch'))
					{
						$nsp_fields[$field_id] = $field;
						continue;
					}
				}

				elseif ($search_type !== 'non-search')
				{
					$no_supportadvsearch = ! FlexicontentFields::getPropertySupport($field->field_type, $field->iscore, 'supportadvsearch');
					$no_supportadvfilter = ! FlexicontentFields::getPropertySupport($field->field_type, $field->iscore, 'supportadvfilter');
					$skip_field = ($no_supportadvsearch && $search_type=='search')  ||  ($no_supportadvfilter && $search_type=='filter') ||
						($no_supportadvsearch && $no_supportadvfilter && in_array($search_type, array('all-search', 'dirty-nosupport') ) );

					if ($skip_field)
					{
						$nsp_fields[$field_id] = $field;
						continue;
					}
				}

				$field->item_id = $item_id;
				$field->value   = false;

				if ($load_params)
				{
					$field->parameters = new JRegistry($field->attribs);
				}

				$sp_fields[$field_id] = $field;
			}

			$sp[$query]  = $sp_fields;
			$nsp[$query] = $nsp_fields;
		}

		return $indexer === 'advanced' && $search_type === 'dirty-nosupport'
			? $nsp[$query]
			: $sp[$query];
	}


	// Method to get properties support for CORE fields
	static function getPropertySupport_BuiltIn()
	{
		static $info = null;
		if ($info!==null) return $info;

		$info = new stdClass();
		$info->core_search= array('title', 'maintext', 'tags', 'categories'   // CORE fields as text searchable
			, 'created', 'modified', 'createdby','modifiedby', 'type'
		);
		$info->core_filters = array('tags', 'categories'   // CORE fields as filters
			, 'created', 'modified', 'createdby', 'modifiedby', 'type', 'state'
		);
		$info->core_advsearch = array('title', 'maintext', 'tags', 'categories'   // CORE fields as text searchable for search view
			, 'created', 'modified', 'createdby','modifiedby', 'type'
		);
		$info->core_advfilters = array('title', 'maintext', 'tags', 'categories'   // CORE fields as filters for search view
			, 'created', 'modified', 'createdby', 'modifiedby', 'type', 'state'
		);
		$info->indexable_fields = array('categories', 'tags', 'type', 'select', 'selectmultiple', 'checkbox', 'checkboximage', 'radio', 'radioimage');

		return $info;
	}


	// Method to get used the properties supported by given field_type
	static function getPropertySupport($field_type, $iscore, $spname=null)
	{
		static $fi = null;
		if ($fi === null) $fi = FlexicontentFields::getPropertySupport_BuiltIn();

		static $cparams = null;
		if ($cparams === null) $cparams = JComponentHelper::getParams( 'com_flexicontent' );

		static $support_ft = array();
		if ( isset( $support_ft[$field_type] ) ) return !$spname ? $support_ft[$field_type] : $support_ft[$field_type]->{$spname};

		// Existing fields with field type
		if ($field_type)
		{
			// Make sure that the Joomla plugin that implements the type of current flexi field, has been imported
			//JPluginHelper::importPlugin('flexicontent_fields', $field_type);
			FLEXIUtilities::call_FC_Field_Func($iscore ? 'core' : $field_type, null, null);

			// Get Methods implemented by the field
			$classname	= 'plgFlexicontent_fields'.($iscore ? 'core' : $field_type);
			$classmethods	= get_class_methods($classname);

			// SEARCH/FILTER related properties
			$supportsearch    = $iscore ? in_array($field_type, $fi->core_search)     : in_array('onIndexSearch', $classmethods);
			$supportfilter    = $iscore ? in_array($field_type, $fi->core_filters)    : in_array('onDisplayFilter', $classmethods);
			$supportadvsearch = $iscore ? in_array($field_type, $fi->core_advsearch)  : in_array('onIndexAdvSearch', $classmethods);
			$supportadvfilter = $iscore ? in_array($field_type, $fi->core_advfilters) : in_array('onAdvSearchDisplayFilter', $classmethods);

			// ITEM FORM related properties
			$supportuntranslatable = !$iscore || $field_type=='maintext';
			$supportvalueseditable = !$iscore || $field_type=='maintext';
			$supportformhidden     = !$iscore || $field_type=='maintext';
			$supportedithelp       = !$iscore || $field_type=='maintext';

		// New fields without field type
		} else {

			// SEARCH/FILTER related properties
			$supportsearch    = false;
			$supportfilter    = false;
			$supportadvsearch = false;
			$supportadvfilter = false;

			// ITEM FORM related properties
			$supportuntranslatable = !$iscore;
			$supportvalueseditable = !$iscore;
			$supportformhidden     = !$iscore;
			$supportedithelp       = !$iscore;
		}

		// This property is usable only when Translation Groups are enabled
		$supportuntranslatable = $supportuntranslatable && flexicontent_db::useAssociations();

		$support_ft[$field_type] = new stdClass();
		$support_ft[$field_type]->supportsearch = $supportsearch;
		$support_ft[$field_type]->supportfilter = $supportfilter;
		$support_ft[$field_type]->supportadvsearch = $supportadvsearch;
		$support_ft[$field_type]->supportadvfilter = $supportadvfilter;
		$support_ft[$field_type]->supportuntranslatable = $supportuntranslatable;
		$support_ft[$field_type]->supportvalueseditable = $supportvalueseditable;
		$support_ft[$field_type]->supportformhidden = $supportformhidden;
		$support_ft[$field_type]->supportedithelp = $supportedithelp;

		return !$spname ? $support_ft[$field_type] : $support_ft[$field_type]->{$spname};
	}





	// *****************************************************************************
	// Common methods for - populating - the BASIC and ADVANCED search INDEX records
	// *****************************************************************************

	// Common method to create (insert) advanced search index DB records for various fields,
	// this can be called by fields or copied inside the field to allow better customization
	static function onIndexAdvSearch(&$field, &$values, &$item, $required_props=array(), $search_props=array(), $props_spacer=' ', $filter_func='strip_tags')
	{
		FlexicontentFields::createIndexRecords($field, $values, $item, $required_props, $search_props, $props_spacer, $filter_func, $for_advsearch=1);
	}


	// Common method to create basic text search index for various fields (added as the property field->search),
	// this can be called by fields or copied inside the field to allow better customization
	static function onIndexSearch(&$field, &$values, &$item, $required_props=array(), $search_props=array(), $props_spacer=' ', $filter_func='strip_tags')
	{
		FlexicontentFields::createIndexRecords($field, $values, $item, $required_props, $search_props, $props_spacer, $filter_func, $for_advsearch=0);
	}


	// Get a pdf parser for parsing the text of CSV files to be added to the search index
	static function getCSVParser()
	{
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$index_csv_files = $cparams->get('index_csv_files', 0);
		$pdfparser_path = 'helpers';

		static $parser = null;
		if ($parser !== null) return $parser;
		$parser = false;

		if (!$index_csv_files || !$pdfparser_path) return $parser;

		// Create parser
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'csv.php');
		$parser = new flexicontent_csv();

		return $parser;
	}


	// Get a pdf parser for parsing the text of PDF files to be added to the search index
	static function getPDFParser()
	{
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$index_pdf_files = $cparams->get('index_pdf_files', 0);
		$pdfparser_path = $cparams->get('pdfparser_path', '');

		static $parser = null;
		if ($parser !== null) return $parser;
		$parser = false;

		if (!$index_pdf_files || !$pdfparser_path) return $parser;

		jimport('joomla.filesystem.path' );
		$pdfparser_path = JPATH::clean($pdfparser_path);

		if (! is_dir($pdfparser_path) || ! is_readable($pdfparser_path))
		{
			// Try relative to Joomla path
			$_pdfparser_path = JPATH::clean(JPATH_SITE.DS.$pdfparser_path);
			if (! is_dir($_pdfparser_path) || ! is_readable($_pdfparser_path))
			{
				JFactory::getApplication()->enqueueMessage('PDF parser path does not seem to be exist and to be readable: '. $pdfparser_path .' please correct path');
				return $parser;
			}
			$pdfparser_path = $_pdfparser_path;
		}

		$vendor_path = JPATH::clean($pdfparser_path.DS.'vendor');
		if (! is_dir($vendor_path) || ! is_readable($vendor_path))
		{
			JFactory::getApplication()->enqueueMessage('PDF parser path does not seem to have installed dependent libraries in vendor subfolder: '. $vendor_path .' please run composer');
			return $parser;
		}

		// Create paser
		//require_once(JPATH::clean($vendor_path.DS.'autoload.php'));
		//require_once(JPATH::clean($pdfparser_path.DS.'src'.DS.'Smalot'.DS.'PdfParser'.DS.'Parser.php'));
		//$parser = new \Smalot\PdfParser\Parser();

		// Create parser
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'pdf.php');
		$parser = new flexicontent_pdf();

		return $parser;
	}


	// Get a language specific handler for parsing the text to be added to the search index
	// e.g. doing word segmentation for a language that does not space-separate the words
	static function getLangHandler($language)
	{
		if ($language != 'th-TH') return false;

		$cparams = JComponentHelper::getParams('com_flexicontent');
		$filter_word_like_any = $cparams->get('filter_word_like_any', 0);

		if ($filter_word_like_any != 0) return false;

		jimport('joomla.filesystem.file');
		$segmenter_path = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'THSplitLib'.DS.'segment.php';

		if (! JFile::exists($segmenter_path)) return false;

		require_once ($segmenter_path);
		// Apply caching to dictionary parsing regardless of cache setting ...
		$handlercache = JFactory::getCache('com_flexicontent_lang_handlers');  // Get Joomla Cache of '... lang_handlers' Caching Group
		$handlercache->setCaching(1);         // Force cache ON
		$handlercache->setLifeTime(24*3600);  // Set expire time (hard-code this to 1 day), since it is costly
		$dictionary = $handlercache->get(
			array('Segment', 'loadDictionary'),
			array()
		);
		Segment::setDictionary($dictionary);
		$handler = new Segment();

		return $handler;
	}


	// Common method to create basic/advanced search index for various fields
	static function createIndexRecords(&$field, &$values, &$item, $required_props=array(), $search_props=array(), $props_spacer=' ', $filter_func='strip_tags', $for_advsearch=0)
	{
		$fi = FlexicontentFields::getPropertySupport($field->field_type, $field->iscore);
		$db = JFactory::getDbo();

		// * Per language handlers e.g. word segmenter objects (add spaces between words for language without spaces)
		static $lang_handlers = array();
		static $pdf_parser = null;
		static $csv_parser = null;
		static $search_prefix = null;
		static $indexed_pdfs = array();
		static $pdf_count = 0;

		// Get search prefix
		if ($search_prefix === null)
		{
			$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		}

		// Get PDF parser for indexing PDF files
		if ($pdf_parser === null && !empty($field->field_isfile))
		{
			$pdf_parser = FlexicontentFields::getPDFParser();
		}

		// Get CSV parser for indexing CSV / Excel (TODO) files
		if ($csv_parser === null && !empty($field->field_isfile))
		{
			$csv_parser = FlexicontentFields::getCSVParser();
		}


		if (!$for_advsearch)
		{
			// Check if field type supports text search, this will also skip fields wrongly marked as text searchable
			if (!$fi->supportsearch || !$field->issearch)
			{
				$field->search = array();
				return;
			}
		}

		else
		{
			$field->ai_query_vals = array();

			// Check if field type supports advanced search text searchable or filterable, this will also skip fields wrongly marked
			if ( !($fi->supportadvsearch && $field->isadvsearch) && !($fi->supportadvfilter && $field->isadvfilter) )
			{
				return;
			}
		}

		// A null indicates that we do not have posted data,
		// instead indexer is running and we should retrieve values from the DB executing an SQL query
		if ($values === null)
		{
			$items_values = FlexicontentFields::searchIndex_getFieldValues($field, $item, $for_advsearch);
		}
		else
		{
			$items_values = !is_array($values) ? array($values) : $values;
			$items_values = array($field->item_id => $items_values);
		}

		// Make sure posted data is an array
		$unserialize = isset($field->unserialize)
			? $field->unserialize
			: (count($required_props) || count($search_props));

		// Create the new search data
		foreach($items_values as $itemid => $item_values)
		{
			// Get item language: (a) multi-item indexing via the search indexer or (b) single item indexing via the item save task (e.g. item form)
			$language = isset($field->items_data) ? $field->items_data[$itemid]->language : $item->language;
			if ( !isset($lang_handlers[$language]) )
			{
				$lang_handlers[$language] = FlexicontentFields::getLangHandler($language);
			}
			$lang_handler = $lang_handlers[$language];

			if ( !empty($field->isindexed) && !$field->iscore )
			{
				// Get Elements of the field these will be cached if they do not depend on the item ...
				$field->item_id = $itemid;   // in case it needs to be loaded to replace item properties in a SQL query
				$item_pros = false;
				$elements = FlexicontentFields::indexedField_getElements($field, $item, $field->extra_props, $item_pros);
				// Map index field vlaues to their real properties
				$item_values = FlexicontentFields::indexedField_getValues($field, $elements, $item_values, $prepost_prop='');
			}

			$searchindex = array();
			foreach($item_values as $vi => $v)
			{
				// Make sure multi-property data are unserialized
				if ($unserialize && !is_array($v))
				{
					$array = flexicontent_db::unserialize_array($v, $force_array=false, $force_value=false);
					if ( $array!==false )
					{
						$v = $array;
					}
				}

				// Check value is not empty
				if ( !is_array($v) && !strlen($v) ) continue;

				// If has field 'required/search' properties, then check field is multi-property (value is array)
				if ( !is_array($v) && (count($required_props) || count($search_props)) ) continue;

				// Skip multi-property fields if search properties are not specified
				if ( is_array($v) && !count($search_props) ) continue;

				// Check required properties were specified
				$required_exists = true;
				foreach ($required_props as $cp)
				{
					if ( !strlen(@$v[$cp]) ) $required_exists = false;
				}
				if (!$required_exists) continue;

				// Create search value
				$search_value = array();
				foreach ($search_props as $sp)
				{
					if ( isset($v[$sp]) && strlen($v[$sp]) ) $search_value[] = $v[$sp];
				}

				// Support for indexing text in PDF files
				$err_msg = '';
				$pdf_indexing_aborted = false;
				if ( $pdf_parser && !empty($field->field_isfile) && strtolower(flexicontent_upload::getExt($v['filename'])) == 'pdf' )
				{
					$abspath = JPath::clean( ($v['secure'] ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH) .DS. $v['filename'] );  //echo $abspath . "<br/>";  					//echo "<pre>"; print_r($v); echo "</pre>";
					if ( isset($indexed_pdfs[$abspath]) )
					{
						if (strlen($indexed_pdfs[$abspath])) $search_value[] = $indexed_pdfs[$abspath];
					}
					else
					{
						try {
							//JFactory::getApplication()->enqueueMessage(($for_advsearch ? 'Parsing (ADV Index)' : 'Parsing (BASIC Index)') . ': ' . $abspath, 'message');
							$pdf_count++;
							if ($pdf_count % 5 == 0)  gc_collect_cycles();   // Call garbage collector every nnn PDF file parsings
							$pdf_data = @ $pdf_parser->parseFile($abspath);
							$search_value[] = $indexed_pdfs[$abspath] = @ $pdf_data->getText();
						}
						catch (Exception $e) {
							$pdf_indexing_aborted = true;
							$indexed_pdfs[$abspath] = '';
							$err_msg = '';
							if (JFactory::getApplication()->isAdmin() && ($last_error = error_get_last()))
							{
								$err_msg .= implode(' ', error_get_last()) . ' <br/> ';
								if (function_exists('error_clear_last')) error_clear_last();
							}
							$err_msg .= $e->getMessage();
						}
					}
				}

				if ($pdf_indexing_aborted)
				{
					JFactory::getApplication()->enqueueMessage('<b>' . $v['filename'] . '</b> : ' . JText::_('FLEXI_PATH_PDF_PARSING_NOT_SUPPORTED_BY_SEARCH_INDEXING'), 'notice');
				}
				if ($err_msg && JDEBUG)
				{
					JFactory::getApplication()->enqueueMessage($err_msg, 'warning');
				}

				// Support for indexing text in CSV / Excel files
				$err_msg = '';
				$csv_indexing_aborted = false;
				if ( $csv_parser && !empty($field->field_isfile) && strtolower(flexicontent_upload::getExt($v['filename'])) == 'csv' )
				{
					$abspath = JPath::clean( ($v['secure'] ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH) .DS. $v['filename'] );  //echo $abspath . "<br/>";  					//echo "<pre>"; print_r($v); echo "</pre>";
					if ( isset($indexed_csvs[$abspath]) )
					{
						if (strlen($indexed_csvs[$abspath])) $search_value[] = $indexed_csvs[$abspath];
					}
					else
					{
						try {
							//JFactory::getApplication()->enqueueMessage(($for_advsearch ? 'Parsing (ADV Index)' : 'Parsing (BASIC Index)') . ': ' . $abspath, 'message');
							$csv_count++;
							if ($csv_count % 5 == 0)  gc_collect_cycles();   // Call garbage collector every nnn CSV file parsings
							$csv_data = @ $csv_parser->parseFile($abspath);
							$search_value[] = $indexed_csvs[$abspath] = @ $csv_data->getText();
						}
						catch (Exception $e) {
							$csv_indexing_aborted = true;
							$indexed_csvs[$abspath] = '';
							$err_msg = '';
							if (JFactory::getApplication()->isAdmin() && ($last_error = error_get_last()))
							{
								$err_msg .= implode(' ', error_get_last()) . ' <br/> ';
								if (function_exists('error_clear_last')) error_clear_last();
							}
							$err_msg .= $e->getMessage();
						}
					}
				}
				if ($csv_indexing_aborted)
				{
					JFactory::getApplication()->enqueueMessage('<b>' . $v['filename'] . '</b> : ' . JText::_('FLEXI_PATH_CSV_PARSING_NOT_SUPPORTED_BY_SEARCH_INDEXING'), 'notice');
				}
				if ($err_msg && JDEBUG)
				{
					JFactory::getApplication()->enqueueMessage($err_msg, 'warning');
				}

				if (count($search_props) && !count($search_value)) continue;  // all search properties were empty, skip this value
				$searchindex[$vi] = (count($search_props))  ?  implode($props_spacer, $search_value)  :  $v;

				// Do a custom stripping of tags on the text
				if ($filter_func == 'strip_tags')
				{
					$searchindex[$vi] = flexicontent_html::striptagsandcut( $searchindex[$vi] );
				}
				else
				{
					$searchindex[$vi] = $filter_func ? $filter_func($searchindex[$vi]) : $searchindex[$vi];
				}
			}

			// if (!empty($pdf_data)) { echo "<pre>"; print_r($searchindex); exit; }
			// if (!empty($csv_data)) { echo "<pre>"; print_r($searchindex); exit; }


			// * Use word segmenter (if it was created) to add spaces between words
			if ($lang_handler)
			{
				foreach($searchindex as $i => $_searchindex)
				{
					$searchindex[$i] = implode(' ', $lang_handler->get_segment_array($clear_previous = true, $_searchindex));
				}
			}

			if ( !$for_advsearch )
			{
				$field->search[$itemid] = implode(' | ', $searchindex);
			}

			else {
				$n = 0;
				foreach ($searchindex as $vi => $search_text)
				{
					if ($search_prefix)
						$search_text = preg_replace('/(\b[^\s,\.]+\b)/u', $search_prefix.'$0', $search_text);
					// Add new search value into the DB
					$query_val = "( "
						.$field->id. "," .$itemid. "," .($n++). "," .$db->Quote($search_text). "," .$db->Quote($vi).
					")";
					$field->ai_query_vals[] = $query_val;
				}
			}
		}

		//if ($field->id==NN) echo $field->name . ": " . print_r($values, true) . "<br/>";
		//if ($field->id==NN) if ( !empty($searchindex) ) echo implode(' | ', $searchindex) ."<br/><br/>";
	}


	// Method to retrieve field values to be used for creating search indexes
	static function searchIndex_getFieldValues(&$field, &$item, $for_advsearch=0)
	{
		$db = JFactory::getDbo();
		$_s = $for_advsearch ? '_s' : '';

		static $nullDate = null;
		static $valCols = array();
		static $txtCols = array();
		static $state_names = null;
		if (!$state_names) $state_names = array(
			1=>JText::_('FLEXI_PUBLISHED'), -5=>JText::_('FLEXI_IN_PROGRESS'), 0=>JText::_('FLEXI_UNPUBLISHED'), -3=>JText::_('FLEXI_PENDING'),
			-4=>JText::_('FLEXI_TO_WRITE'), 2=>JText::_('FLEXI_ARCHIVED'), -2=>JText::_('FLEXI_TRASHED')
		);

		// Create DB query to retrieve field values
		$values = null;
		switch ($field->field_type)
		{
		case 'title':
			$query  = 'SELECT i.title AS value, i.id AS itemid'
				.' FROM #__content AS i'
				.' WHERE i.id IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
			break;

		case 'maintext':
			$query  = 'SELECT CONCAT_WS(\' \', i.introtext, i.fulltext) AS value, i.id AS itemid'
				.' FROM #__content AS i'
				.' WHERE i.id IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
			break;

		case 'categories':
			$query  = 'SELECT c.id AS value_id, c.title AS value, rel.itemid AS itemid'
				.' FROM #__categories AS c'
				.' JOIN #__flexicontent_cats_item_relations AS rel ON c.id=rel.catid'
				.' WHERE c.id<>0 AND rel.itemid IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
			break;

		case 'tags':
			$query  = 'SELECT t.id AS value_id, t.name AS value, rel.itemid AS itemid'
				.' FROM #__flexicontent_tags AS t'
				.' JOIN #__flexicontent_tags_item_relations AS rel ON t.id=rel.tid'
				.' WHERE t.id<>0 AND rel.itemid IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
			break;

		case 'type':
			$textcol = ', t.name AS value';
			$query 	= ' SELECT t.id AS value_id '.$textcol.', ext.item_id AS itemid'
				. ' FROM #__flexicontent_types AS t'
				.' JOIN #__flexicontent_items_ext AS ext ON t.id=ext.type_id '
				.' WHERE t.id<>0 AND ext.item_id IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
			break;

		case 'state':
			$textcol = ', CASE i.state';
			foreach($state_names as $_id => $_name)  $textcol .= ' WHEN '.$_id.' THEN '.$db->Quote($_name);
			$textcol .= ' ELSE '.$db->Quote("unknown").' END AS value';
			$query 	= ' SELECT i.state AS value_id '.$textcol.', i.id AS itemid'
				.' FROM #__content AS i'
				.' WHERE i.id IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
			break;

		case 'created': case 'modified':
			if ($nullDate===null) $nullDate	= $db->getNullDate();

			if (!isset($valCols[$field->field_type]))
			{
				$date_filter_group = $field->parameters->get('date_filter_group'.$_s, 'month');
				if ($date_filter_group=='year') { $date_valformat='%Y'; }
				else if ($date_filter_group=='month') { $date_valformat='%Y-%m'; }
				else { $date_valformat='%Y-%m-%d'; }

				// Display date 'label' can be different than the (aggregated) date value
				$date_filter_label_format = $field->parameters->get('date_filter_label_format'.$_s, '');
				$date_txtformat = $date_filter_label_format ? $date_filter_label_format : $date_valformat;  // If empty then same as value

				$valCols[$field->field_type] = sprintf(' DATE_FORMAT(i.%s, "%s") ', $field->field_type, $date_valformat);
				$txtCols[$field->field_type]  = sprintf(' DATE_FORMAT(i.%s, "%s") ', $field->field_type, $date_txtformat);
			}

			$valuecol = $valCols[$field->field_type];
			$textcol  = $txtCols[$field->field_type];

			$query 	= 'SELECT '.$valuecol.' AS value_id, '.$textcol.' AS value, i.id AS itemid'
				.' FROM #__content AS i'
				.' WHERE i.'.$field->name.'<>'.$db->Quote($nullDate).' AND i.id IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
			break;

		case 'createdby': case 'modifiedby':
			$textcol = ', u.name AS value';
			$query 	= ' SELECT u.id AS value_id '.$textcol.', i.id AS itemid'
				. ' FROM #__users AS u'
				.' JOIN #__content AS i ON i.'.$field->name.' = u.id'
				.' WHERE u.id<>0 AND i.id IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')';
			break;

		default:
			if ($field->iscore) $values=array(); //die('Field Type: '.$field->field_type.' does not support FLEXIcontent Advanced Search Indexing');
			$valuesselect = @$field->field_valuesselect ? $field->field_valuesselect : ' fi.value AS value ';
			$valuesjoin   = @$field->field_valuesjoin   ? $field->field_valuesjoin : '';
			$valueswhere  = @$field->field_valueswhere  ? $field->field_valueswhere  : ' AND fi.field_id ='.$field->id;

			$item_id_col = @$field->field_item_id_col ? $field->field_item_id_col : ' fi.item_id ';
			$groupby     = @$field->field_groupby ? $field->field_groupby .', '.$item_id_col : ' GROUP BY fi.value, '.$item_id_col;

			$valuesfrom = !empty($field->field_valuesfrom)
				? $field->field_valuesfrom
				:	' FROM #__flexicontent_fields_item_relations as fi ' .
					' JOIN #__content as i ON i.id=fi.item_id ';

			$query = 'SELECT '.$valuesselect.', '.$item_id_col.' AS itemid'
				. $valuesfrom
				. $valuesjoin
				.' WHERE 1 '
				. $valueswhere
				.' AND '.$item_id_col.' IN ('.(@$field->query_itemids ? implode(',', $field->query_itemids) : $field->item_id) .')'
				. $groupby;
			break;
		}

		//if ($field->id==NN) echo $query;
		//if ($field->id==NN) exit;

		// Execute query if not already done to load a single value column with no value id
		$_raw = !empty($field->field_rawvalues);
		if ($values === null)
		{
			$db->setQuery($query);
			$_values = $db->loadObjectList();
			$values = array();
			if ($_values) foreach($_values as $v)
			{
				if (isset($v->value_id))
					$values[$v->itemid][$v->value_id] = $_raw ? (array) $v : (isset($v->value) ? $v->value : $v->value_id);
				else
					$values[$v->itemid][] = $_raw ? (array) $v : $v->value;
			}
		}

		return $values;
	}





	// ********************************************************************************************
	// Methods for - MATCHING - Field Filters of FC views, (thus limiting the current ITEM LISTING)
	// ********************************************************************************************

	// Private Method to create a generic matching of filter
	static function createFilterValueMatchSQL(&$filter, &$value, $is_full_text=0, $is_search=0, $colname='')
	{
		static $search_prefix = null;
		if ($search_prefix === null) $search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		$_search_prefix = $colname=='fs.search_index' ? $search_prefix : '';


		// ***
		// *** Filter out zero-length values
		// ***

		// *** Force array
		if (!is_array($value))
		{
			$value = array($value);
		}

		$_value = array();

		foreach ($value as $i => $v)
		{
			if (is_array($v))
			{
				// Indirect fitering ... for filtering on values of fields of related items
				if ($i < 0)
				{
					$_value = $v;
				}
				break;
			}
			else
			{
				$v = trim($v);

				if (strlen($v))
				{
					$_value[$i] = $v;
				}
			}
		}
		$value = $_value;

		// No values were given
		if (!count($value))
		{
			return '';
		}

		$db = JFactory::getDbo();
		$display_filter_as = (int) $filter->parameters->get( $is_search ? 'display_filter_as_s' : 'display_filter_as', 0 );
		$filter_compare_type = (int) $filter->parameters->get( 'filter_compare_type', 0 );

		$isDate = in_array($filter->field_type, array('date','created','modified')) || $filter->parameters->get('isdate',0);
		$isRange = in_array( $display_filter_as, array(2,3,8) );
		$isTextInput = $display_filter_as==1 || $display_filter_as==3;
		$quoted = false;

		if (!isset($value[0]) && (isset($value[1]) || isset($value[1])))
		{
			$display_filter_as = $isRange ? $display_filter_as : 2;
			$isRange = true;
		}

		$require_all_param = $filter->parameters->get( 'filter_values_require_all', 0 );
		$require_all_values = is_array($value) && count($value) > 1 && !$isRange   // prevent require_all for known ranges
			? $require_all_param
			: 0;
		//echo "createFilterValueMatchSQL : filter name: ".$filter->name." Filter Type: ".$display_filter_as." Values: "; print_r($value); echo "<br>";


		// ***
		// *** Extra preparation date filter range, to support date offsets
		// ***

		if ($isDate && !isset($value[0]))
		{
			if (
				(isset($value[1]) && strpos(trim($value[1]), 'now') === 0) ||
				(isset($value[2]) && strpos(trim($value[2]), 'now') === 0)
			) {
				require_once (JPATH_SITE.DS.'modules'.DS.'mod_flexicontent'.DS.'classes'.DS.'datetime.php');
				$v = array();

				if (!empty($value[1]))
				{
					$value[1] = preg_replace('/now\s*/', '', $value[1]);
					$shift = array(
						0 => preg_replace("/[^-+0-9\s]/", "", $value[1]),
						1 => preg_replace("/[0-9-+\s]/", "", $value[1])
					);
					$value[1] = date_time::shift_dates('', $shift[0], $shift[1]);
				}

				if (!empty($value[2]))
				{
					$value[2] = preg_replace('/now\s*/', '', $value[2]);
					$shift = array(
						0 => preg_replace("/[^-+0-9\s]/", "", $value[2]),
						1 => preg_replace("/[0-9-+\s]/", "", $value[2])
					);
					$value[2] = date_time::shift_dates('', $shift[0], $shift[1]);
				}

				// Update filter value to calculated value
				JFactory::getApplication()->input->set('filter_'.$filter->id, $value);
			}
		}


		// ***
		// *** Extra preparation date / text search inputs with custom value formats
		// ***

		if (isset($filter->filter_valueformat))
		{
			$date_suffix = '';

			if ($isDate)
			{
				switch($filter->parameters->get('date_filter_group', 'month'))
				{
					case 'year':
						$date_suffix = '-1-1';
						break;
					case 'month':
						$date_suffix = '-1';
						break;
				}
			}

			foreach($value as $i => $val)
			{
				$typecasted_val = !$filter_compare_type
					? $db->Quote($value[$i] . $date_suffix)
					: ($filter_compare_type==1 ? intval($value[$i]) : floatval($value[$i]));

				$value[$i] = str_replace('__filtervalue__', $typecasted_val, $filter->filter_valueformat);
			}
			$quoted = true;
		}


		// ***
		// *** Create the VALUEs WHERE clause
		// ***

		$valueswhere = '';
		if ($isRange)
		{
			// RANGE cases: 2, 3, 8
			if ( empty($quoted) )
			{
				foreach($value as $i => $v)
				{
					$value[$i] = !$filter_compare_type
						? $db->Quote( preg_replace('(\w+)', $_search_prefix.'$0', $v) )
						: ($filter_compare_type==1 ? intval($v) : floatval($v));
				}
			}
			$reverse_values = $filter->parameters->get( 'reverse_filter_order', 0) && $display_filter_as == 8;
			$value1 = $reverse_values ? @$value[2] : @$value[1];
			$value2 = $reverse_values ? @$value[1] : @$value[2];
			$value_empty = !strlen(@$value[1]) && strlen(@$value[2]) ? ' OR _v_="" OR _v_ IS NULL' : '';
			if ( strlen($value1) ) $valueswhere .= ' AND (_v_ >=' . $value1 . ')';
			if ( strlen($value2) ) $valueswhere .= ' AND (_v_ <=' . $value2 . $value_empty . ')';
		}

		// non-text, aka EXACT value cases: 0, 4, 5, 6, 7, * -OR- isDate
		elseif ($display_filter_as !== 1 || $isDate)
		{
			$value_clauses = array();

			if (!$require_all_values)
			{
				foreach ($value as $val)
				{
					$value_clauses[] = $quoted
						? '_v_=' . $val
						: '_v_=' . $db->Quote( preg_replace('(\w+)', $_search_prefix.'$0', $val) );
				}
				$valueswhere .= ' AND ('.implode(' OR ', $value_clauses).') ';
			}
			else
			{
				foreach ($value as $val)
				{
					$value_clauses[] = $quoted
						? $val
						: $db->Quote( preg_replace('(\w+)', $_search_prefix.'$0', $val) );
				}
				$valueswhere = ' AND _v_ IN ('. implode(',', $value_clauses) .')';
			}
		}

		// SINGLE TEXT select value cases
		else
		{
			if (!empty($filter->filter_valueexact))
			{
				$valueswhere .= ' AND _v_=' . $db->Quote( preg_replace('(\w+)', $_search_prefix.'$0', $value[0]) );
			}
			else
			{
				// DO NOT put % in front of the value since this will force a full table scan instead of indexed column scan
				$_value_like = preg_replace('(\w+)', $_search_prefix.'$0'.($is_full_text ? '*' : '%'), $value[0]);
				if (empty($quoted))
				{
					$_value_like = $db->Quote($_value_like);
				}
				$valueswhere .= $is_full_text
					? ' AND  MATCH (_v_) AGAINST ('.$_value_like.' IN BOOLEAN MODE)'
					: ' AND _v_ LIKE ' . $_value_like;
			}
		}

		//echo $valueswhere . "<br>";
		return $valueswhere;
	}


	// Method to get the active filter result for Content Lists Views (an SQL where clause part OR an array of item ids, matching field filter)
	static function getFiltered( &$filter, $value, $return_sql=true )
	{
		$db = JFactory::getDbo();

		// Check if field type supports advanced search
		$support = FlexicontentFields::getPropertySupport($filter->field_type, $filter->iscore);
		if ( ! $support->supportfilter )  return null;

		$display_filter_as = $filter->parameters->get( 'display_filter_as', 0 );
		$is_full_text = !empty($filter->is_full_text) ? $filter->is_full_text : $display_filter_as == 1;

		$valueswhere = !empty($filter->filter_valuewhere)
			? $filter->filter_valuewhere
			: FlexicontentFields::createFilterValueMatchSQL($filter, $value, $is_full_text, $is_search=0);

		if (!$valueswhere)
		{
			return;
		}

		$idname = !empty($filter->filter_valuesjoin)
			? 'c.id'
			: 'rel.item_id';
		$colname = !empty($filter->filter_colname)
			? $filter->filter_colname
			: 'value';
		$valueswhere = str_replace('_v_', $colname, $valueswhere);

		$valuesfrom = !empty($filter->filter_valuesfrom)
			? $filter->filter_valuesfrom
			: null;
		$valuesjoin  = !empty($filter->filter_valuesjoin)
			? $filter->filter_valuesjoin
			: null;

		// Decide to require all values

		$isRange = in_array( $display_filter_as, array(2,3,8) );
		$require_all_param = $filter->parameters->get( 'filter_values_require_all', 0 );
		$require_all_values = is_array($value) && count($value) > 1 && !$isRange   // prevent require_all for known ranges
			? $require_all_param
			: 0;

		$query = 'SELECT '.($require_all_values ? $idname : 'DISTINCT ' . $idname);

		// We have a special values join, by default, select from the content table
		if ($valuesjoin)
		{
			$query .= ($valuesfrom ?: ' FROM #__content c')
				. $valuesjoin
				. ' WHERE 1'
				. $valueswhere ;
		}

		// No special values join, by default, select from the values table
		else
		{
			$query .= ($valuesfrom ?: ' FROM #__flexicontent_fields_item_relations as rel')
				. ' WHERE rel.field_id=' . $filter->id
				. $valueswhere ;
		}

		if ($require_all_values && count($value) > 1)
		{
			// Do not use distinct on column, it makes it is very slow, despite column having an index !!
			// e.g. HAVING COUNT(DISTINCT colname) = ...
			// Instead the field code should make sure that no duplicate values are saved in the DB !!
			$query .= ''
				. ' GROUP BY ' . $idname . ' HAVING COUNT(*) >= ' . count($value)
				. ' ORDER BY NULL';  // THIS should remove filesort in MySQL, and improve performance issue of REQUIRE ALL
		}

		//$query .= ' GROUP BY id';   // BAD PERFORMANCE ?

		if ( !$return_sql )
		{
			//echo "<br>GET FILTERED Items (helper func) -- [".$filter->name."] using in-query ids :<br>". $query."<br>\n";
			$db->setQuery($query);
			$filtered = $db->loadColumn();
			return $filtered;
		}

		else if ($return_sql===2)
		{
			static $iids_tblname  = array();
			if ( !isset($iids_tblname[$filter->id]) )
			{
				$iids_tblname[$filter->id] = 'fc_filter_iids_'.$filter->id;
			}
			$tmp_tbl = $iids_tblname[$filter->id];

			try {
				// Use sub-query on temporary table
				$db->setQuery('CREATE TEMPORARY TABLE IF NOT EXISTS '.$tmp_tbl.' (id INT, KEY(`id`))');
				$db->execute();
				$db->setQuery('TRUNCATE TABLE '.$tmp_tbl);
				$db->execute();
				$db->setQuery('INSERT INTO '.$tmp_tbl.' '.$query);
				//echo $query; exit;
				$db->execute();
				$_query = $query;
				$query = 'SELECT id FROM '.$tmp_tbl;   //echo "<br/><br/>GET FILTERED Items (helper func) -- [".$filter->name."] using temporary table: ".$query." for :".$_query ." <br/><br/>";
				/*$db->setQuery($query);
				$data = $db->loadObjectList();
				echo "<pre>";
				print_r($data);
				exit;*/
			}
			catch (Exception $e) {
				// Ignore table creation error
				//if ($db->getErrorNum())  echo 'SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
				//echo "<br/><br/>GET FILTERED Items (helper func) -- [".$filter->name."] using subquery: ".$query." <br/><br/>";
			}
		}
		else
		{
			//echo "<br/><br/>GET FILTERED Items (helper func) -- [".$filter->name."] using subquery: ".$query." <br/><br/>";
		}
		return ' AND i.id IN ('. $query .')';
	}


	// Method to get the active filter result Search View (an SQL where clause part OR an array of item ids, matching field filter)
	static function getFilteredSearch( &$filter, $value, $return_sql=true )
	{
		$app = JFactory::getApplication();
		$db  = JFactory::getDbo();

		// Check if field type supports advanced search
		$support = FlexicontentFields::getPropertySupport($filter->field_type, $filter->iscore);
		if ( ! $support->supportadvsearch && ! $support->supportadvfilter )  return null;

		// Decide to require all values
		$display_filter_as = $filter->parameters->get( 'display_filter_as_s', 0 );

		$isDate = in_array($filter->field_type, array('date','created','modified')) || $filter->parameters->get('isdate',0);
		$isRange = in_array( $display_filter_as, array(2,3,8) );
		$isTextInput = $display_filter_as==1 || $display_filter_as==3;

		$require_all_param = $filter->parameters->get( 'filter_values_require_all', 0 );
		$require_all_values = is_array($value) && count($value) > 1 && !$isRange   // prevent require_all for known ranges
			? $require_all_param
			: 0;

		$colname = (!empty($filter->isindexed) && !$isTextInput) || $isDate
			? 'fs.value_id'
			: 'fs.search_index';

		// Create where clause for matching the filter's values
		$valueswhere = FlexicontentFields::createFilterValueMatchSQL($filter, $value, $is_full_text=1, $is_search=1, $colname);
		if ( !$valueswhere )  return;
		$valueswhere = str_replace('_v_', $colname, $valueswhere);

		$field_tbl = 'flexicontent_advsearch_index_field_'.$filter->id;
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . $field_tbl . '"';
		$db->setQuery($query);
		$tbl_exists = (boolean) count($db->loadObjectList());
		$field_tbl = $tbl_exists ? $field_tbl : 'flexicontent_advsearch_index';

		// Get ALL items that have such values for the given field
		$query = 'SELECT '.($require_all_values ? 'fs.item_id' : 'DISTINCT fs.item_id')
			.' FROM #__'.$field_tbl.' AS fs'
			.' WHERE fs.field_id=' . $filter->id
			. $valueswhere ;
		if ($require_all_values && count($value) > 1)
		{
			// Do not use distinct on column, it makes it is very slow, despite column having an index !!
			// e.g. HAVING COUNT(DISTINCT colname) = ...
			// Instead the field code should make sure that no duplicate values are saved in the DB !!
			$query .= ''
				. ' GROUP BY fs.item_id ' . ' HAVING COUNT(*) >= ' . count($value)
				. ' ORDER BY NULL';  // THIS should remove filesort in MySQL, and improve performance issue of REQUIRE ALL
		}
		//echo 'Filter ['. $filter->label .']: '. $query."<br/><br/>\n";

		if ( !$return_sql ) {
			//echo "<br>GET FILTERED Items (helper func) -- [".$filter->name."] using in-query ids : ". $query."<br>\n";
			$db->setQuery($query);
			$filtered = $db->loadColumn();
			return $filtered;
		}
		else if ($return_sql===2)
		{
			static $iids_tblname  = array();
			if ( !isset($iids_tblname[$filter->id]) )
			{
				$iids_tblname[$filter->id] = 'fc_filter_iids_'.$filter->id;
			}
			$tmp_tbl = $iids_tblname[$filter->id];

			try {
				// Use sub-query on temporary table
				$db->setQuery('CREATE TEMPORARY TABLE IF NOT EXISTS '.$tmp_tbl.' (id INT, KEY(`id`))');
				$db->execute();
				$db->setQuery('TRUNCATE TABLE '.$tmp_tbl);
				$db->execute();
				$db->setQuery('INSERT INTO '.$tmp_tbl.' '.$query);
				//echo $query; exit;
				$db->execute();
				$_query = $query;
				$query = 'SELECT id FROM '.$tmp_tbl;   //echo "<br/><br/>GET FILTERED Items (helper func) -- [".$filter->name."] using temporary table: ".$query." for :".$_query ." <br/><br/>";
				/*$db->setQuery($query);
				$data = $db->loadObjectList();
				echo "<pre>";
				print_r($data);
				exit;*/
			}
			catch (Exception $e) {
				// Ignore table creation error
				//if ($db->getErrorNum())  echo 'SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
				//echo "<br/><br/>GET FILTERED Items (helper func) -- [".$filter->name."] using subquery: ".$query." <br/><br/>";
			}
		} else {
			//echo "<br/><br/>GET FILTERED Items (helper func) -- [".$filter->name."] using subquery: ".$query." <br/><br/>";
		}
		return ' AND i.id IN ('. $query .')';
	}



	// **********************************************
	// Methods for creating Field Filters of FC views
	// **********************************************

	// Method to create a category (content list) or search filter
	static function createFilter(&$filter, $value='', $formName='adminForm', $indexed_elements=false, $search_prop='')
	{
		static $faceted_overlimit_msg = null;

		$app = JFactory::getApplication();
		$user = JFactory::getUser();
		$cparams = JComponentHelper::getParams('com_flexicontent');  // createFilter maybe called in backend too ...
		$print_logging_info = $cparams->get('print_logging_info');

		$option = $app->input->get('option', '', 'cmd');
		$view   = $app->input->get('view', '', 'cmd');

		$isFC = $option === 'com_flexicontent';
		$isCategoryView = $isFC && $view === 'category';
		$isSearchView   = $isFC && $view === 'search';

		if ( $print_logging_info )
		{
			global $fc_run_times;
			$start_microtime = microtime(true);
		}

		// Apply caching to filters regardless of cache setting ...
		if (FLEXI_CACHE)
		{
			$itemcache = JFactory::getCache('com_flexicontent_filters');  // Get Joomla Cache of '...items' Caching Group
			$itemcache->setCaching(1); 		              // Force cache ON
			$itemcache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expire time (default is 1 hour)
		}

		$isDate = in_array($filter->field_type, array('date','created','modified')) || $filter->parameters->get('isdate',0);
		$_s = $isSearchView ? '_s' : '';

		// Some parameter shortcuts
		$label_filter = $filter->parameters->get( 'display_label_filter'.$_s, 0 ) ;   // How to show filter label
		$faceted_filter = $filter->parameters->get( 'faceted_filter'.$_s, 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as'.$_s, 0 );  // Filter Type of Display

		$isSlider = $display_filter_as == 7 || $display_filter_as == 8;
		$slider_display_config = $filter->parameters->get( 'slider_display_config'.$_s, 1 );  // Slider found values: 1 or custom values/labels: 2

		// Make sure the current filtering values match the field filter configuration to single or multi-value
		if (in_array($display_filter_as, array(2,3,5,6,8)))
		{
			if (!is_array($value)) $value = strlen($value) ? array($value) : array();
		}
		else
		{
			if (is_array($value)) $value = isset($value[0]) ? $value[0] : null;
		}

		$isRange = in_array( $display_filter_as, array(2,3,8) );
		$require_all_param = $filter->parameters->get( 'filter_values_require_all', 0 );
		$require_all_values = is_array($value) && count($value) > 1 && !$isRange   // prevent require_all for known ranges
			? $require_all_param
			: 0;

		$show_matching_items = $filter->parameters->get( 'show_matching_items'.$_s, 1 );
		$show_matches = $isRange || !$faceted_filter ?  0  :  $show_matching_items;
		$hide_disabled_values = $filter->parameters->get( 'hide_disabled_values'.$_s, 0 );
		$get_filter_vals = in_array($display_filter_as, array(0,2,4,5,6)) || ($isSlider && $slider_display_config==1);

		$pretext_filter  = $filter->parameters->get( 'pretext_filter'.$_s, '' );
		$posttext_filter = $filter->parameters->get( 'posttext_filter'.$_s, '' );
		$opentag_filter  = $filter->parameters->get( 'opentag_filter'.$_s, '' );
		$closetag_filter = $filter->parameters->get( 'closetag_filter'.$_s, '' );

		$filter_ffname = 'filter_'.$filter->id;
		$filter_ffid   = $formName.'_'.$filter->id.'_val';

		// Escape values for output, moved to HTML code, because it causes problem with value matching (==)
		//if (!is_array($value)) $value = htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
		//else foreach($value as $i => $v) $value[$i] = htmlspecialchars($value[$i], ENT_COMPAT, 'UTF-8');

		// Alter search property name (indexed fields only), remove underscore _ at start & end of it
		if ($indexed_elements && $search_prop)
		{
			preg_match("/^_([a-zA-Z_0-9]+)_$/", $search_prop, $prop_matches);
			$search_prop = @ $prop_matches[1];
		}

		// Get filtering values, this can be cached if not filtering according to current category filters
		if ($get_filter_vals)
		{
			$view_join = '';
			$view_join_n_text = '';
			$view_where = '';
			$filters_where = array();
			$text_search = '';
			$view_total = 0;

			// ***
			// *** Limiting of displayed filter values according to current category filtering, but show all field values if filter is active
			// ***

			// Category view, use parameter to decide if limitting filter values
			if ( $isCategoryView )
			{
				global $fc_catview;

				if ($faceted_filter)
				{
					$view_join = @ $fc_catview['join_clauses'];
					$view_join_n_text = @ $fc_catview['join_clauses_with_text'];
					$view_where = @ $fc_catview['where_conf_only'];
					$filters_where = @ $fc_catview['filters_where'];
					$text_search = $fc_catview['search'];
					$view_total = isset($fc_catview['view_total']) ? $fc_catview['view_total'] : 0;
				}
			}

			// Search view, use parameter to decide if limitting filter values
			else if ( $isSearchView )
			{
				global $fc_searchview;

				if (empty($fc_searchview))
				{
					return array();  // search view plugin disabled ?
				}

				if ($faceted_filter)
				{
					$view_join = $fc_searchview['join_clauses'];
					$view_join_n_text = $fc_searchview['join_clauses_with_text'];
					$view_where = $fc_searchview['where_conf_only'];
					$filters_where = $fc_searchview['filters_where'];
					$text_search = $fc_searchview['search'];
					$view_total = isset($fc_searchview['view_total']) ? $fc_searchview['view_total'] : 0;
				}
			}

			$createFilterValues = !$isSearchView ? 'createFilterValues' : 'createFilterValuesSearch';

			// Decide if filter display depends on language too
			$lang_code = $isDate && !empty($filter->date_txtformat)? JFactory::getLanguage()->getTag() : null;

			// This is hack for filter core properties to be filterable in search view without being added to the adv search index
			if ($filter->field_type == 'coreprops' &&  $view=='search')
			{
				$createFilterValues = 'createFilterValues';
			}

			// Get filter values considering PAGE configuration (regardless of ACTIVE filters)
			if (isset($filter->filter_options))
			{
				$results_page = $filter->filter_options;
			}
			elseif (FLEXI_CACHE)
			{
				$results_page = $itemcache->get(
					array('FlexicontentFields', $createFilterValues),
					array($filter, $view_join, $view_where, array(), $indexed_elements, $search_prop, $lang_code)
				);
			}
			elseif (!$isSearchView)
			{
				$results_page = FlexicontentFields::createFilterValues($filter, $view_join, $view_where, array(), $indexed_elements, $search_prop, $lang_code);
			}
			else
			{
				$results_page = FlexicontentFields::createFilterValuesSearch($filter, $view_join, $view_where, array(), $indexed_elements, $search_prop, $lang_code);
			}

			// Get filter values considering ACTIVE filters, but only if there is at least ONE filter active
			$faceted_max_item_limit = 10000;
			if ( $faceted_filter==2 )
			{
				if (isset($filter->filter_options))
				{
					$faceted_filter = 0;
					$results_active = $filter->filter_options;
				}

				elseif ($view_total <= $faceted_max_item_limit)
				{
					// DO NOT cache at this point the filter combinations are endless, so they will produce big amounts of cached data, that will be rarely used ...
					// but if only a single filter is active we can get the cached result of it ... because its own filter_where is not used for the filter itself
					if ( !$text_search && (count($filters_where)==0 || (count($filters_where)==1 && isset($filters_where[$filter->id]))) )
						$results_active = $results_page;
					else if (!$isSearchView)
						$results_active = FlexicontentFields::createFilterValues($filter, $view_join_n_text, $view_where, $filters_where, $indexed_elements, $search_prop, $lang_code);
					else
						$results_active = FlexicontentFields::createFilterValuesSearch($filter, $view_join_n_text, $view_where, $filters_where, $indexed_elements, $search_prop, $lang_code);
				}

				elseif ($faceted_overlimit_msg === null)
				{
					// Set a notice message about not counting item per filter values and instead showing item TOTAL of current category / view
					$faceted_overlimit_msg = 1;
					$filter_messages = array();
					$filter_messages[] = JText::sprintf('FLEXI_FACETED_ITEM_LIST_OVER_LIMIT', $faceted_max_item_limit, $view_total);
					$app->setUserState('filter_messages', $filter_messages);
				}
			}

			// Decide which results to show those based: (a) on active filters or (b) on page configuration
			// This depends if hiding disabled values (for FACETED: 2) AND if active filters exist
			$use_active_vals = $hide_disabled_values && isset($results_active);
			$results_shown = $use_active_vals ? $results_active : $results_page;
			$update_found = !$use_active_vals && isset($results_active);

			// Set usage counters
			$add_usage_counters = $faceted_filter==2 && $show_matches;
			$results = array();
			foreach ($results_shown as $i => $result)
			{
				$results[$i] = $result;

				// FACETED: 0,1 or NOT showing usage
				// Set usage to non-zero value e.g. -1 ... which maybe used (e.g. disabling values) but not be displayed
				if (!$show_matches || $faceted_filter<2)
					$results[$i]->found = -1;

				// FACETED: 2 and SHOWING PAGE VALUES (not hiding values or no active filters),
				// Set usage of filter values that was calculated according to active filters
				// 1. this overrides value usage calculated for page's configuration (faceted: 1)
				// 2. we set zero if value was not found
				else if ($update_found)
					$results[$i]->found = isset($results_active[$i]->found) ? (int) $results_active[$i]->found : null;

				// FACETED: 1 or hiding unavailable values ... leave value unchanged (if it has been calculated)
				else ;

				// Prepend prefix to value's label
				if ($pretext_filter)
				{
					$results[$i]->text = $pretext_filter . ' ' . $results[$i]->text;
				}

				// Append suffix to value's label
				if ($posttext_filter)
				{
					$results[$i]->text = $results[$i]->text . ' ' . $posttext_filter;
				}

				// Append value usage to value's label
				if ($add_usage_counters && $results[$i]->found)
				{
					$results[$i]->text .= ' ('.$results[$i]->found.')';  // THESE for indexed fields should have been cloned, so it is ok to modify
				}
			}
		}

		else
		{
			$add_usage_counters = false;
			$faceted_filter = 0; // clear faceted filter flag
		}

		$displayData = array(
			'filter' => & $filter,
			'filter_ffid' => $filter_ffid,
			'filter_ffname' => $filter_ffname,
			'results' => & $results,
			'value' => $value,
			'require_all_values' => $require_all_values,
			'isSearchView' => $isSearchView,
		);

		$layouts_path = $app->isSite() ? null : JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'layouts';

		// *** Do not create any HTML just return empty string to indicate a filter that should be skipped
		if ($hide_disabled_values && empty($results))
		{
			$filter->html = '';
		}

		/**
		 * Create the form field(s) used for filtering, note output is captured into filter->html, but we do 'echo JLayoutHelper::render()' for debugging
		 * Place override at: /templates/TEMPLATENAME/html/layouts/com_flexicontent/items_list_filters/
		 */
		else
		{
			$filter->html = '';

			switch ($display_filter_as)
			{
				// 0: Select (single value selectable), 2: Dual select (value range), 6: Multi Select (multiple values selectable)
				case 0: case 2: case 6:
					echo JLayoutHelper::render('items_list_filters.select_selectmul', $displayData, $layouts_path);
					break;

				// (TODO: autocomplete) ... 1: Text input, 3: Dual text input (value range), both of these can be JS date calendars, 7: Slider, 8: Slider range
				case 1: case 3: case 7: case 8:
					echo JLayoutHelper::render('items_list_filters.txtsearch_date_slider', $displayData, $layouts_path);
					break;

				case 4: case 5:  // 4: radio (single value selectable), 5: checkbox (multiple values selectable)

					echo JLayoutHelper::render('items_list_filters.radio_checkbox', $displayData, $layouts_path);
					break;

				default:
					$filter->html = 'Case ' . $display_filter_as . ' not implemented';
					break;
			}
		}
		//$last_error = error_get_last();
		//echo '<pre>'; print_r($last_error); exit;

		// Prepend opening Text to filter's HTML
		if ($opentag_filter)
		{
			$filter->html = $opentag_filter . ' ' . $filter->html;
		}

		// Append closing Text to filter's HTML
		if ($closetag_filter)
		{
			$filter->html = $filter->html . ' ' . $closetag_filter;
		}

		if ($print_logging_info)
		{
			$current_filter_creation = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			$flt_active_count = isset($filters_where) ? count($filters_where) : 0;
			$faceted_str = array(0=>'non-FACETED ', 1=>'FACETED: current view &nbsp; (cacheable) ', 2=>'FACETED: current filters:'." (".$flt_active_count.' active) ');

			$fc_run_times['create_filter'][$filter->name] = $current_filter_creation + (!empty($fc_run_times['create_filter'][$filter->name]) ? $fc_run_times['create_filter'][$filter->name] : 0);

			if (isset($fc_run_times['_create_filter_init']))
			{
				$fc_run_times['create_filter'][$filter->name] -= $fc_run_times['_create_filter_init'];
				$fc_run_times['create_filter_init'] = $fc_run_times['_create_filter_init'] + (!empty($fc_run_times['create_filter_init']) ? $fc_run_times['create_filter_init'] : 0);
				unset($fc_run_times['_create_filter_init']);
			}

			$fc_run_times['create_filter_type'][$filter->name] = $faceted_str[$faceted_filter];
		}

		//$filter_display_typestr = array(0=>'Single Select', 1=>'Single Text', 2=>'Range Dual Select', 3=>'Range Dual Text', 4=>'Radio Buttons', 5=>'Checkbox Buttons');
		//echo "FIELD name: <b>". $filter->name ."</b> Field Type: <b>". $filter->field_type."</b> Filter Type: <b>". $filter_display_typestr[$display_filter_as] ."</b> (".$display_filter_as.") ".sprintf(" %.2f s",$current_filter_creation/1000000)." <br/>";
	}


	// Method to create a calendar form field according to a given configuation, e.g. called during Filter Creation of FC views
	static function createCalendarField($value, $date_allowtime, $fieldname, $elementid, $attribs=array(), $skip_on_invalid=false, $timezone=false, $date_format='%Y-%m-%d')
	{
		@list($date, $time) = preg_split('#\s+#', $value, $limit=2);
		$time = ($date_allowtime==2 && !$time) ? '00:00' : $time;

		try {
			// we check if date has no SYNTAX error (=being invalid) so use $gregorian = true,
			// to avoid it being change according to CALENDAR of current user
			// because user already entered the date in his/her calendar
			if ( !$value ) {
				$date = '';
			} else if (!$date_allowtime || !$time) {
				$date = JHtml::_('date',  $date, 'Y-m-d', $timezone, $gregorian = true);
			} else {
				$date = JHtml::_('date',  $value, 'Y-m-d H:i', $timezone, $gregorian = true);
			}
		} catch ( Exception $e ) {
			if (!$skip_on_invalid) return '';
			else $date = '';
		}

		// Create JS calendar
		$time_formats_map = array('0'=>'', '1'=>' %H:%M', '2'=>' 00:00');
		$date_time_format = $date_format . $time_formats_map[$date_allowtime];
		$attribs['showTime'] = $date_allowtime ? 1 : 0;
		return JHtml::_('calendar', $date, $fieldname, $elementid, $date_time_format, $attribs);
	}


	// Method to create filter values for a field filter to be used in content lists views (category, etc)
	static function createFilterValues($filter, $view_join, $view_where, $filters_where, $indexed_elements, $search_prop, $lang_code)
	{
		$faceted_filter = $filter->parameters->get( 'faceted_filter', 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as', 0 );  // Filter Type of Display
		$isRange = in_array( $display_filter_as, array(2,3,8) );
		$lang_filter_values = $filter->parameters->get( 'lang_filter_values', 1);

		$format_output = (int) $filter->parameters->get('format_output', 0);
		if ($format_output > 0)  // 1: decimal, 2: integer
		{
			$decimal_digits_displayed = $format_output === 2 ? 0 : (int)$filter->parameters->get('decimal_digits_displayed', 2);
			$decimal_digits_sep    = $filter->parameters->get('decimal_digits_sep', '.');
			$decimal_thousands_sep = $filter->parameters->get('decimal_thousands_sep', ',');
			$output_prefix = JText::_($filter->parameters->get('output_prefix', ''));
			$output_suffix = JText::_($filter->parameters->get('output_suffix', ''));
		}
		else if ($format_output === -1)
		{
			$output_custom_func = $filter->parameters->get('output_custom_func', '');
			$format_output = !$output_custom_func ? 0 : $format_output;
		}

		$show_matching_items = $filter->parameters->get( 'show_matching_items', 1 );
		$show_matches = $isRange || !$faceted_filter ?  0  :  $show_matching_items;

		//echo "<b>FILTER NAME</b>: ". $filter->label ."<br/>\n";
		//echo "<b> &nbsp; view_join</b>: <br/>". $view_join ."<br/>\n";
		//echo "<b> &nbsp;view_where</b>: <br/>". $view_where ."<br/>\n";
		//echo "<b> &nbsp;filters_where</b>: <br/>". print_r($filters_where, true) ."<br/><br/>\n";
		//exit;

		if ($faceted_filter || !$indexed_elements)
		{
			$_results = FlexicontentFields::getFilterValues($filter, $view_join, $view_where, $filters_where, $lang_code);
			//if ($filter->id==NN) echo "<pre>". $filter->label.": ". print_r($_results, true) ."\n\n</pre>";
		}


		// Support of value-indexed fields
		if ($indexed_elements)
		{
			// non-FACETED filter
			if (!$faceted_filter)
			{
				// Clone 'indexed_elements' because they maybe modified
				$results = array();
				foreach ($indexed_elements as $i => $result)
				{
					if (isset($result->state) && $result->state < 1)
					{
						continue;
					}
					$results[$i] = clone($result);
				}
			}

			// FACETED filter
			else
			{
				// Limit indexed element according to DB results found
				$results = array_intersect_key($indexed_elements, $_results);
				//echo "<pre>". $filter->label.": ". print_r($results, true) ."\n\n</pre>";
				if ($faceted_filter==2 && $show_matches) foreach ($results as $i => $result)
				{
					if (isset($result->state) && $result->state < 1)
					{
						unset($results[$i]);
						continue;
					}
					$result->found = $_results[$i]->found;
					// Clone 'indexed_elements' because they maybe modified
					$results[$i] = clone($result);
				}
			}
		}

		// Support for multi-property fields
		else if ($search_prop)
		{
			// Check and unserialize values
			foreach ($_results as $i => $result)
			{
				$array = flexicontent_db::unserialize_array($result->value, $force_array=false, $force_value=false);
				if ( $array!==false )
				{
					$_results[$i] = $array;
				}
			}

			// Index values via the search property
			$results = array();
			foreach ($_results as $i => $result)
			{
				$_results[$i] = is_array($_results[$i])
					? (object) $_results[$i]
					: (object) array($search_prop=>$_results[$i]);
				if ( isset($_results[$i]->$search_prop) )
				{
					$results[ $_results[$i]->$search_prop ] = $_results[$i];
				}
			}
		}

		// non-indexable or single property field
		else
		{
			$results = & $_results;
		}

		if (empty($results)) $results = array();

		// Language filter values/labels (for indexed fields this is already done)
		if ( ($lang_filter_values || $format_output) && !$indexed_elements )
		{
			foreach ($results as $i => $result)
			{
				if ($format_output > 0)  // 1: decimal, 2: integer
				{
					$results[$i]->text = @ number_format($results[$i]->text, $decimal_digits_displayed, $decimal_digits_sep, $decimal_thousands_sep);
					$results[$i]->text = $results[$i]->text === NULL ? 0 : $results[$i]->text;
					$results[$i]->text = $output_prefix .$results[$i]->text. $output_suffix;
				}
				else if ($format_output === -1)
				{
					$value = & $results[$i]->text;
					$results[$i]->text = eval( "\$value= \"{$value}\";" . $output_custom_func);
				}

				if ($lang_filter_values)
				{
					$results[$i]->text = JText::_($result->text);
				}
			}
			unset($value);
		}

		// Skip sorting for indexed elements, DB query or element entry is responsible
		// for ordering indexable fields, also skip if ordering is done by the filter
		if ( !$indexed_elements && empty($filter->filter_orderby) )
		{
			uksort($results, 'strnatcasecmp');
			if ($filter->parameters->get( 'reverse_filter_order', 0)) $results = array_reverse($results, true);
		}

		return $results;
	}


	// Method to create filter values for a field filter to be used in search view
	static function createFilterValuesSearch($filter, $view_join, $view_where, $filters_where, $indexed_elements, $search_prop, $lang_code)
	{
		$faceted_filter = $filter->parameters->get( 'faceted_filter_s', 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as_s', 0 );  // Filter Type of Display
		$isRange = in_array( $display_filter_as, array(2,3,8) );
		$lang_filter_values = $filter->parameters->get( 'lang_filter_values', 1);

		$format_output = (int) $filter->parameters->get('format_output', 0);
		if ($format_output > 0)  // 1: decimal, 2: integer
		{
			$decimal_digits_displayed = $format_output==2 ? 0 : (int)$filter->parameters->get('decimal_digits_displayed', 2);
			$decimal_digits_sep    = $filter->parameters->get('decimal_digits_sep', '.');
			$decimal_thousands_sep = $filter->parameters->get('decimal_thousands_sep', ',');
			$output_prefix = JText::_($filter->parameters->get('output_prefix', ''));
			$output_suffix = JText::_($filter->parameters->get('output_suffix', ''));
		}
		else if ($format_output === -1)
		{
			$output_custom_func = $filter->parameters->get('output_custom_func', '');
			$format_output = !$output_custom_func ? 0 : $format_output;
		}

		$show_matching_items = $filter->parameters->get( 'show_matching_items_s', 1 );
		$show_matches = $isRange || !$faceted_filter ?  0  :  $show_matching_items;

		$filter->filter_isindexed = (boolean) $indexed_elements;
		if ($faceted_filter || !$indexed_elements)
		{
			$_results = FlexicontentFields::getFilterValuesSearch($filter, $view_join, $view_where, $filters_where, $lang_code);
			//echo "<pre>". $filter->label.": ". print_r($_results, true) ."\n\n</pre>";
		}

		// Support of value-indexed fields
		if ( !$faceted_filter && $indexed_elements)
		{
			// Clone 'indexed_elements' because they maybe modified
			$results = array();
			foreach ($indexed_elements as $i => $result)
			{
				$results[$i] = clone($result);
			}
		}

		// Limit indexed element according to DB results found
		else if ( $indexed_elements && is_array($indexed_elements) )
		{
			$results = array_intersect_key($indexed_elements, $_results);
			//echo "<pre>". $filter->label.": ". print_r($indexed_elements, true) ."\n\n</pre>";
			if ($faceted_filter==2 && $show_matches) foreach ($results as $i => $result)
			{
				$result->found = $_results[$i]->found;
				// Clone 'indexed_elements' because they maybe modified
				$results[$i] = clone($result);
			}
		}
		else
		{
			$results = & $_results;
		}

		// Language filter values/labels (for indexed fields this is already done)
		if ( ($lang_filter_values || $format_output) && !$indexed_elements )
		{
			foreach ($results as $i => $result)
			{
				if ($format_output > 0)  // 1: decimal, 2: integer
				{
					$results[$i]->text = @ number_format($results[$i]->text, $decimal_digits_displayed, $decimal_digits_sep, $decimal_thousands_sep);
					$results[$i]->text = $results[$i]->text === NULL ? 0 : $results[$i]->text;
					$results[$i]->text = $output_prefix .$results[$i]->text. $output_suffix;
				}
				else if ($format_output === -1)
				{
					$value = & $results[$i]->text;
					$results[$i]->text = eval( "\$value= \"{$value}\";" . $output_custom_func);
				}

				if ($lang_filter_values)
				{
					$results[$i]->text = JText::_($result->text);
				}
			}
			unset($value);
		}

		// Skip sorting for indexed elements, DB query or element entry is responsible
		// for ordering indexable fields, also skip if ordering is done by the filter
		if ( !$indexed_elements && empty($filter->filter_orderby_adv) )
		{
			uksort($results, 'strnatcasecmp');
			if ($filter->parameters->get( 'reverse_filter_order', 0)) $results = array_reverse($results, true);
		}

		return $results;
	}


	// Retrieves all available filter values of the given field according to the given VIEW'S FILTERING (Content Lists)
	static function getFilterValues(&$filter, &$view_join, &$view_where, &$filters_where, &$lang_code)
	{
		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
		$db = JFactory::getDbo();

		$filter_where_curr = '';
		foreach ($filters_where as $filter_id => $filter_where)
		{
			if ($filter_id != $filter->id)  $filter_where_curr .= ' ' . $filter_where;
		}
		//echo "filter_where_curr : ". $filter_where_curr ."<br/>";

		// partial SQL clauses
		$valuesselect = !empty($filter->filter_valuesselect)
			? $filter->filter_valuesselect
			: ' fi.value AS value, fi.value AS text';

		$valuesfrom = !empty($filter->filter_valuesfrom)
			? $filter->filter_valuesfrom
			: ($filter->iscore || $filter->field_type=='coreprops' ? ' FROM #__content AS i' : ' FROM #__flexicontent_fields_item_relations AS fi ');

		$valuesjoin = !empty($filter->filter_valuesjoin)
			? $filter->filter_valuesjoin
			: ' ';

		$valueswhere = !empty($filter->filter_valueswhere)
			? $filter->filter_valueswhere
			: ' AND fi.field_id ='.$filter->id;

		// full SQL clauses
		$groupby = !empty($filter->filter_groupby) ? $filter->filter_groupby : ' GROUP BY value ';
		$having  = !empty($filter->filter_having)  ? $filter->filter_having  : '';
		$orderby = !empty($filter->filter_orderby) ? $filter->filter_orderby : '';

		if ($filter->parameters->get( 'reverse_filter_order', 0) && $orderby)
		{
			$replace_count = null;
			$orderby = str_ireplace( ' ASC', ' DESC', $orderby, $replace_count);
			if (!$replace_count) $orderby .= ' DESC';
		}

		$faceted_filter = $filter->parameters->get( 'faceted_filter', 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as', 0 );  // Filter Type of Display
		$isRange = in_array( $display_filter_as, array(2,3,8) );

		$show_matching_items = $filter->parameters->get( 'show_matching_items', 1 );
		$show_matches = $isRange || !$faceted_filter ?  0  :  $show_matching_items;

		$use_tmp = true;
		static $iids_subquery = null;
		static $iids_tblname  = array();
		$view_n_text = 'SELECT DISTINCT i.id '."\n"
			. ' FROM #__'.($use_tmp ? 'flexicontent_items_tmp' : 'content').' AS i'."\n"
			. $view_join."\n"
			. $view_where."\n"
			;
		if ( !isset($iids_tblname[$view_n_text]) )
		{
			$iids_tblname[$view_n_text] = 'fc_view_iids_'.count($iids_tblname);
		}
		$tmp_tbl = $iids_tblname[$view_n_text];

		// Format string according to language
		static $lc_time_names = null;
		if ($lang_code && !empty($filter->date_txtformat))
		{
			if ($lc_time_names === null)
			{
				$db->setQuery('SELECT @@lc_time_names');
				$lc_time_names = $db->loadResult();
			}
			$language = $lc_time_names !== $lang_code ? $lang_code : null;
		}


		// ***
		// *** Find items belonging to current view
		// ***

		// FACETED FILTER
		if ( $faceted_filter > 1 )
		{
			if ( !isset($iids_subquery[$view_n_text]) && empty($view_where) )
			{
				$iids_subquery[$view_n_text] = '';  // current view has not limits in where clause
			}

			if ( !isset($iids_subquery[$view_n_text]) )
			{
				global $fc_run_times, $fc_jprof, $fc_catview;
				$start_microtime = microtime(true);

				try {
					// Use sub-query on temporary table
					$db->setQuery('CREATE TEMPORARY TABLE IF NOT EXISTS '.$tmp_tbl.' (id INT, KEY(`id`))');
					$db->execute();
					$db->setQuery('TRUNCATE TABLE '.$tmp_tbl);
					$db->execute();
					$db->setQuery('INSERT INTO '.$tmp_tbl.' '.$view_n_text);
					$db->execute();
					$iids_subquery[$view_n_text] = 'SELECT id FROM '.$tmp_tbl;   //echo "<br/><br/> FILTER INITIALIZATION - using temporary table: ".$iids_subquery[$view_n_text]." for :".$view_n_text ." <br/><br/>";
				}
				catch (Exception $e) {
					// Repeat sub-query if creating temporary table failed
					//if ($db->getErrorNum())  echo 'SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
					$iids_subquery[$view_n_text] = $view_n_text;   //echo "<br/><br/> FILTER INITIALIZATION - using subquery: ".$iids_subquery[$view_n_text]." <br/><br/>";
					/*if ($fc_catview['search']) {
						$db->setQuery($view_n_text);
						$item_ids = $db->loadColumn();
						$iids_subquery[$view_n_text] = implode(',', $item_ids);   //echo "<br/><br/> FILTER INITIALIZATION - using item ID list: ".$iids_subquery[$view_n_text]." <br/><br/>";
					}*/
				}
				$fc_run_times['_create_filter_init'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			}
			//if ($filter->id==NN) echo "<br/><br/> FILTER INITIALIZATION - using temporary table: ".$iids_subquery[$view_n_text]." for :".$view_n_text ." <br/><br/>";

			$item_id_col = !empty($filter->filter_item_id_col)
				? $filter->filter_item_id_col
				: ($filter->iscore || $filter->field_type=='coreprops' ? 'i.id' : 'fi.item_id');

			$filter_where_curr = preg_replace('/\bi.id\b/', $item_id_col, $filter_where_curr);
			$query = 'SELECT '. $valuesselect .($faceted_filter && $show_matches ? ', COUNT(DISTINCT '.$item_id_col.') as found ' : '')."\n"
				//.', GROUP_CONCAT('.$item_id_col.' SEPARATOR ",") AS idlist '   // enable FOR DEBUG purposes only
				. $valuesfrom."\n"
				. $valuesjoin."\n"
				. ' WHERE 1 '."\n"
				. (empty($iids_subquery[$view_n_text]) ? '' : ' AND '.$item_id_col.' IN('.$iids_subquery[$view_n_text].')'."\n")
				. $filter_where_curr."\n"
				. $valueswhere."\n"
				. $groupby."\n"
				. $having."\n"
				. $orderby
				;
			//if ($filter->id==NN) echo $query."<br/><br/>";
		}

		// Non FACETED filter (according to view but without acounting for filtering and without counting items)
		else
		{
			$query = 'SELECT DISTINCT '. $valuesselect ."\n"
				. $valuesfrom."\n"
				. $valuesjoin."\n"
				. ' WHERE 1 '."\n"
				. $valueswhere."\n"
				//. $groupby."\n"  // replaced by distinct, when not counting items
				. $having."\n"
				. $orderby
				;
		}
		//if ( in_array($filter->field_type, array('tags','created','modified')) ) echo nl2br($query);

		$db->setQuery($query);
		try {
			$results = $db->loadObjectList('value');
			//if ($filter->id==NN) { echo "<pre>"; print_r($results); echo "</pre>"; }
		}
		catch (Exception $e) {
			JFactory::getApplication()->enqueueMessage(__FUNCTION__."() Filter for : ".$filter->label." cannot be displayed, SQL QUERY ERROR:<br />" .nl2br(JDEBUG ? $e->getMessage() . '<br/>' . $query : 'Joomla Debug is OFF'), 'warning');
			$results = array();
		}

		// Format string according to language
		if (!empty($language))
		{
			$date_txtformat = str_replace('%', '', $filter->date_txtformat);
			$nullDate = $db->getNullDate();
			$is_year_group = $filter->parameters->get('date_filter_group', 'month') === 'year';
			foreach($results as &$r)
			{
				if ($r->value && $r->value !== $nullDate)
				{
					$date = new JDate($is_year_group ? $r->value . '-1-1' : $r->value);   // JDate can not handle just year (YYYY) so we use YYYY-1-1
					$r->text = $date->format($date_txtformat);
				}
			}
			unset($r);
		}

		return $results;
	}


	// Retrieves all available filter values of the given field according to the given VIEW'S FILTERING (Search view)
	static function getFilterValuesSearch(&$filter, &$view_join, &$view_where, &$filters_where, &$lang_code)
	{
		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
		$app = JFactory::getApplication();
		$db  = JFactory::getDbo();

		$filter_where_curr = '';
		foreach ($filters_where as $filter_id => $filter_where)
		{
			if ($filter_id != $filter->id)  $filter_where_curr .= ' ' . $filter_where;
		}

		$isDate = in_array($filter->field_type, array('date','created','modified')) || $filter->parameters->get('isdate',0);
		$using_value_id = $isDate || @$filter->filter_isindexed;
		$valuesselect = $using_value_id ? ' ai.value_id as value, ai.search_index as text ' : ' ai.search_index as value, ai.search_index as text';
		$orderby = @$filter->filter_orderby_adv ? $filter->filter_orderby_adv : '';
		if ($filter->parameters->get( 'reverse_filter_order', 0) && $orderby)
		{
			$replace_count = null;
			$orderby = str_ireplace( ' ASC', ' DESC', $orderby, $replace_count);
			if (!$replace_count) $orderby .= ' DESC';
		}

		$faceted_filter = $filter->parameters->get( 'faceted_filter_s', 2);
		$display_filter_as = $filter->parameters->get( 'display_filter_as_s', 0 );  // Filter Type of Display
		$isRange = in_array( $display_filter_as, array(2,3,8) );

		$show_matching_items = $filter->parameters->get( 'show_matching_items_s', 1 );
		$show_matches = $isRange || !$faceted_filter ?  0  :  $show_matching_items;

		$field_tbl = 'flexicontent_advsearch_index_field_'.$filter->id;
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . $field_tbl . '"';
		$db->setQuery($query);
		$tbl_exists = (boolean) count($db->loadObjectList());
		$field_tbl = $tbl_exists ? $field_tbl : 'flexicontent_advsearch_index';


		static $iids_subquery = null;
		static $iids_tblname  = array();

		$view_n_text = 'SELECT DISTINCT i.id '."\n"
			.' FROM #__content i '."\n"
			. $view_join."\n"
			. $view_where."\n"
			;
		if ( !isset($iids_tblname[$view_n_text]) )
		{
			$iids_tblname[$view_n_text] = 'fc_view_iids_'.count($iids_tblname);
		}
		$tmp_tbl = $iids_tblname[$view_n_text];


		// Format string according to language
		static $lc_time_names = null;
		if ($lang_code && !empty($filter->date_txtformat))
		{
			if ($lc_time_names === null)
			{
				$db->setQuery('SELECT @@lc_time_names');
				$lc_time_names = $db->loadResult();
			}
			$language = $lang_code;  // Search view always needs recreation of display, because the field configuration may have changed, since the Search Index was created
		}


		// ***
		// *** Find items belonging to current view
		// ***

		// FACETED FILTER
		if ( $faceted_filter > 1 )
		{
			if ( !isset($iids_subquery[$view_n_text]) && empty($view_where) )  $iids_subquery[$view_n_text] = '';  // current view has not limits in where clause

			if ( !isset($iids_subquery[$view_n_text]) )
			{
				global $fc_run_times, $fc_jprof, $fc_searchview;

				$start_microtime = microtime(true);
				try {
					// Use sub-query on temporary table
					$db->setQuery('CREATE TEMPORARY TABLE IF NOT EXISTS '.$tmp_tbl.' (id INT, KEY(`id`))');
					$db->execute();
					$db->setQuery('TRUNCATE TABLE '.$tmp_tbl);
					$db->execute();
					$db->setQuery('INSERT INTO '.$tmp_tbl.' '.$view_n_text);
					$db->execute();
					$iids_subquery[$view_n_text] = 'SELECT id FROM '.$tmp_tbl;   //echo "<br/><br/> FILTER INITIALIZATION - using temporary table: ".$iids_subquery[$view_n_text]." for :".$view_n_text ." <br/><br/>";
				}
				catch (Exception $e) {
					// Repeat sub-query if creating temporary table failed
					//if ($db->getErrorNum())  echo 'SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
					$iids_subquery[$view_n_text] = $view_n_text;   //echo "<br/><br/> FILTER INITIALIZATION - using subquery: ".$iids_subquery[$view_n_text]." <br/><br/>";
					/*if ($fc_searchview['search']) {
						$db->setQuery($view_n_text);
						$item_ids = $db->loadColumn();
						$iids_subquery[$view_n_text] = implode(',', $item_ids);   //echo "<br/><br/> FILTER INITIALIZATION - using item ID list: ".$iids_subquery[$view_n_text]." <br/><br/>";
					}*/
				}
				$fc_run_times['_create_filter_init'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			}

			// Get ALL records that have such values for the given field
			$query = 'SELECT '. $valuesselect .($faceted_filter && $show_matches ? ', COUNT(DISTINCT ai.item_id) as found ' : '')."\n"
				. ' FROM #__'.$field_tbl.' AS ai'."\n"
				. ' WHERE ai.field_id='.(int)$filter->id."\n"
				. (empty($iids_subquery[$view_n_text]) ? '' : ' AND ai.item_id IN('.$iids_subquery[$view_n_text].')'."\n")
				.  str_replace('i.id', 'ai.item_id', $filter_where_curr)."\n"
				. ' GROUP BY ai.search_index, ai.value_id'."\n"
				. $orderby
				;
			//if ($filter->id==NN) echo $query."<br/><br/>";
		}

		// Non FACETED filter (according to view but without acounting for filtering and without counting items)
		else {
			$query = 'SELECT DISTINCT '. $valuesselect."\n"
				. ' FROM #__'.$field_tbl.' AS ai'."\n"
				. ' WHERE ai.field_id='.(int)$filter->id."\n"
				. (empty($iids_subquery[$view_n_text]) ? '' : ' AND ai.item_id IN('.$iids_subquery[$view_n_text].')'."\n")
				.  str_replace('i.id', 'ai.item_id', $filter_where_curr)."\n"
				//. ' GROUP BY ai.search_index, ai.value_id'."\n"  // replaced by distinct, when not counting items
				. $orderby
				;
		}
		//echo $query."<br/><br/>";

		$db->setQuery($query);
		try {
			$results = $db->loadObjectList('value');
			//if ($filter->id==NN) { echo "<pre>"; print_r($results); echo "</pre>"; }
		}
		catch (Exception $e) {
			JFactory::getApplication()->enqueueMessage(__FUNCTION__."() Filter for : ".$filter->label." cannot be displayed, SQL QUERY ERROR:<br />" .nl2br(JDEBUG ? $e->getMessage() . '<br/>' . $query : 'Joomla Debug is OFF'), 'warning');
			$results = array();
		}

		// Format string according to language
		if (!empty($language))
		{
			$date_txtformat = str_replace('%', '', $filter->date_txtformat);
			$nullDate = $db->getNullDate();
			$is_year_group = $filter->parameters->get('date_filter_group_s', 'month') === 'year';
			foreach($results as &$r)
			{
				if ($r->value && $r->value !== $nullDate)
				{
					$date = new JDate($is_year_group ? $r->value . '-1-1' : $r->value);   // JDate can not handle just year (YYYY) so we use YYYY-1-1
					$r->text = $date->format($date_txtformat);
				}
			}
			unset($r);
		}

		static $search_prefix = null;
		if ($search_prefix === null) $search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		if ($search_prefix) foreach ($results as $i => $result)
		{
			$result->text = preg_replace('/\b'.$search_prefix.'/u', '', $result->text);
			if (!$using_value_id) $result->value = $result->text;
		}

		return $results;
	}


	/**
	 * Method to set custom filters values VIA configuration parameters
	 * -- CASE 1: CONTENT LISTS (component / category / menu items / filtering module)
	 *    these are set as HTTP Request variables to be used by the filtering mechanism of the category model (content lists)
	 * -- CASE 2: Custom Fields SCOPE of Universal Content MODULE
	 *    these are returned as an array to be used directly into the SQL query
	 *
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	static function setFilterValues( &$cparams, $mfilter_name='persistent_filters', $is_persistent=1, $set_method="httpReq" )
	{
		$jinput = JFactory::getApplication()->input;

		$field_filters = array();   // Used when set_method is 'array' instead of 'httpReq'
		$is_persistent =            // Non-httpReq method does not have initial filters
			$set_method!="httpReq" ? 1 : $is_persistent;

		// Get configuration parameter holding the custom field filtering and abort if empty
		$mfilter_data = $cparams->get($mfilter_name, '');
		if (!$mfilter_data)
		{
			$cparams->set($mfilter_name, '');  // Set to empty string for J1.5 compatibility, otherwise this could be empty array too
			return array();
		}

		// Parse configuration parameter into individual fields
		$mfilter_arr = preg_split("/[\s]*%%[\s]*/", $mfilter_data);
		if ( empty($mfilter_arr[count($mfilter_arr)-1]) )
		{
			unset($mfilter_arr[count($mfilter_arr)-1]);
		}

		// This array contains the field (filter) ID that were parsed without errors
		$filter_ids = array();

		foreach ($mfilter_arr as $mfilter)
		{
			// a. Split elements into their properties: filter_id, filter_value
			$_data  = preg_split("/[\s]*##[\s]*/", $mfilter, 2);  //print_r($_data);
			$filter_id = (int) $_data[0];
			$filter_value = @$_data[1];
			//echo "filter_".$filter_id.": "; print_r( $filter_value ); echo "<br/>";

			// b. Basic parsing error check: a non numeric field id
			if ( !$filter_id ) continue;

			// c. Add field (filter) ID into those that are valid
			$filter_ids[] = $filter_id;

			// d. Skip field filter, if it is not persistent and user user has overriden it
			if ( !$is_persistent && $jinput->get('filter_'.$filter_id, false, 'raw') !== false ) continue;

			// ***
			// *** FILTER FOR FIELD OF RELATED ITEM: rel_field_id##rel_item_field_id~~value
			// ***
			$relitem_field_id = 0;
			if (strpos($filter_value, '~~') !== false)
			{
				list($relitem_field_id, $rel_field_value) = explode('~~', $filter_value, 2);
				$relitem_field_id = (int) $relitem_field_id;
				if ($relitem_field_id)
				{
					$filter_value = $rel_field_value;
				}
				//print_r($filter_value);
			}

			// CASE: range values:  value01---value02
			if (strpos($filter_value, '---') !== false)
			{
				$filter_value = explode('---', $filter_value);
				$filter_value[2] = $filter_value[1];
				$filter_value[1] = $filter_value[0];
				unset($filter_value[0]);
			}

			// CASE: multiple values:  value01+++value02+++value03+++value04
			else if (strpos($filter_value, '+++') !== false)
			{
				$filter_value = explode('+++', $filter_value);
			}

			// CASE: specific value:  value01
			else {}

			// *** Add filter for field of related item
			if ($relitem_field_id)
			{
				$filter_value = array(- $relitem_field_id => $filter_value);
			}

			// INDIRECT method of using field filter (via HTTP request)
			if ($set_method=='httpReq')
			{
				$jinput->set('filter_'.$filter_id, $filter_value);
			}

			// DIRECT method of using field filter (via a returned array)
			else
			{
				$field_filters[$filter_id] = $filter_value;
			}
		}

		// INDIRECT method of using field filter (via HTTP request),
		// NOTE: we overwrite the above configuration parameter of custom field filters with an ARRAY OF VALID FILTER IDS, to
		// indicate to category/search model security not to skip these if they are not IN category/search configured filters list
		if ($set_method=='httpReq')
		{
			count($filter_ids) ?
				$cparams->set($mfilter_name, $filter_ids) :
				$cparams->set($mfilter_name, false );  // FALSE means do not retrieve ALL
		}

		// DIRECT method filter values, return an array of filter values (for direct usage into an SQL query)
		else {
			return $field_filters;
		}
	}


	/**
	 * Method to get data of filters
	 *
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	static function & getFilters($filt_param='filters', $usage_param='use_filters', & $params = null, $check_access=true)
	{
		// Parameter that controls using these filters
		$filters = array();
		if ( $usage_param!='__ALL_FILTERS__' && $params && !$params->get($usage_param,0) ) return $filters;

		// Get Filter IDs, false means do retrieve any filter
		$filter_ids = $params
			? $params->get($filt_param, array())
			: array();

		if ($filter_ids === false)
		{
			return $filters;
		}

		// Check if array or comma separated list
		if (!is_array($filter_ids))
		{
			$filter_ids = preg_split("/\s*,\s*/u", $filter_ids);

			if (empty($filter_ids[0]))
			{
				unset($filter_ids[0]);
			}
		}

		// Sanitize the given filter_ids ... just in case
		$filter_ids = array_filter($filter_ids, 'is_numeric');

		// array_flip to get unique filter ids as KEYS (due to flipping) ... and then array_keys to get filter_ids in 0,1,2, ... array
		$filter_ids = array_keys(array_flip($filter_ids));

		$user = JFactory::getUser();
		$db   = JFactory::getDbo();

		// None selected filters means ALL
		$and_scope = $usage_param!='__ALL_FILTERS__' && count($filter_ids) ? ' AND fi.id IN (' . implode(',', $filter_ids) . ')' : '';

		// Use ACCESS Level, usually this is only for shown filters
		$and_access = '';

		if ($check_access)
		{
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$and_access = ' AND fi.access IN (0,'.$aid_list.') ';
		}

		// Create and execute SQL query for retrieving filters
		$query  = 'SELECT fi.*'
			. ' FROM #__flexicontent_fields AS fi'
			. ' WHERE fi.published = 1'
			. ' AND fi.isfilter = 1'
			. $and_access
			. $and_scope
			. ' ORDER BY fi.ordering, fi.name';

		$filters = $db->setQuery($query)->loadObjectList('id');

		if (!$filters)
		{
			// Create variable to return a reference (also can not return false here as it will mean an error)
			$filters = array();
			return $filters;
		}

		// Order filters according to given order
		$filters_tmp = array();

		if ($params->get('filters_order', 0) && !empty($filter_ids) && $usage_param!='__ALL_FILTERS__')
		{
			foreach( $filter_ids as $filter_id) {
				if ( empty($filters[$filter_id]) ) continue;
				$filter = $filters[$filter_id];
				$filters_tmp[$filter->name] = $filter;
			}
		}

		// Not re-ordering, but index them via fieldname in this case too (for consistency)
		else
		{
			foreach ($filters as $filter)
			{
				$filters_tmp[$filter->name] = $filter;
			}
		}

		$filters = $filters_tmp;

		// Create filter parameters, language filter label, etc
		foreach ($filters as $filter)
		{
			$filter->parameters = new JRegistry($filter->attribs);
			$filter->label = JText::_($filter->label);
		}

		// Return found filters
		return $filters;
	}


	/**
	 * Method to creat the HTML of filters
	 *
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	static function renderFilters( &$params, &$filters, $form_name )
	{
		// Make the filter compatible with Joomla standard cache
		$jinput = JFactory::getApplication()->input;

		$filter_prefix = ($form_name == 'item_form' ? 'iform_' : '') .'filter_';

		$display_label_filter_override = (int) $params->get('show_filter_labels', 0);
		foreach ($filters as $filter_name => $filter)
		{
			$filtervalue = $jinput->get($filter_prefix.$filter->id, '', 'raw');
			//print_r($filtervalue);

			// make sure filter HTML is cleared, and create it
			$display_label_filter_saved = $filter->parameters->get('display_label_filter');
			if ( $display_label_filter_override ) $filter->parameters->set('display_label_filter', $display_label_filter_override); // suppress labels inside filter's HTML (hide or show all labels externally)

			// else ... filter default label behavior
			$filter->html = '';  // make sure filter HTML display is cleared
			$field_type = $filter->iscore ? 'core' : $filter->field_type;
			//$results 	= $dispatcher->trigger('onDisplayFilter', array( &$filter, $filtervalue ));
			FLEXIUtilities::call_FC_Field_Func($field_type, 'onDisplayFilter', array( &$filter, $filtervalue, $form_name ) );
			$filter->parameters->set('display_label_filter', $display_label_filter_saved);
		}
	}



	// ***
	// *** Helper methods to create GENERIC ITEM LISTs which also includes RENDERED display of fields and custom HTML
	// ***

	/*
	 * Helper method to perform HTML replacements on given list of item ids (with optional catids too), the items of the item
	 * is either given as parameter or the list is created via a field / item pair (field is relation or relation reverse)
	 *
	 * @param 	object 		$params     some parameters, typically the parameters of a relation field
	 * @param 	string    $itemIDs    the item IDs as index of an array of some item data
	 * @param 	object 		$field      a field that is a relation or relation reverse field
	 * @param 	object 		$item       an item having a relation or relation reverse field
	 * @param 	string    $options    some options like 'return_items_array' meaning to return an items array and not the final HTML
	 *
	 * @return  a string with the rendered display HTML of the calculated items list, or an array of item objects, with their display HTML as a property
	 * @since 2.1
	 *
	 */
	static function getItemsList($params, $itemIDs, $field, $item, $options = null)
	{
		if (!is_object($field) || !is_object($item))  return 'getItemsList : Legacy call not possible, please review your custom code';
		if (!$options) $options = new stdClass();

		// 0: return imploded display HTML
		// 1: return items array with display HTML per item
		// 2: return items array without rendering display HTML
		$return_items_array = isset($options->return_items_array) ? (int) $options->return_items_array : 0;

		// Create the SQL query to retrieve item list data array,
		// ... and check for empty SQL query, e.g. no valid related items for relation field
		$query = FlexicontentFields::createItemsListSQL($params, $itemIDs, $field, $item, $options);
		if (!$query)
		{
			return $return_items_array ? array() : '';
		}

		// Execute SQL query to get item list data array,
		// ... and check for none found items (e.g. none published)
		$db = JFactory::getDbo();
		try {
			$rows = flexicontent_db::directQuery($query);
			$item_list = array();

			if ($return_items_array != 3)
			{
				foreach ($rows as $row)
				{
					$item_list[$row->id] = $row;
				}
				$db->setQuery("SELECT FOUND_ROWS()");
				$total = $db->loadResult();
			}
			else
			{
				// instead of items array contains the total
				$total = $rows ? $rows[0]->total : 0;
			}
			$options->total = $total;
		}
		catch (Exception $e) {
			$item_list = $db->setQuery($query)->loadObjectList('id');
		}

		// Check for empty array
		if ( !$item_list )
		{
			return $return_items_array ? array() : '';
		}

		// Get Original content ids for creating some untranslatable fields that have share data (like shared folders)
		flexicontent_db::getOriginalContentItemids($item_list);

		// Only return the items array without creating their HTML
		if ( $return_items_array == 2)
		{
			return $item_list;
		}

		// Finally create the display HTML of the items,
		// - either returning an imploded string of the items display HTML
		// - or returning the items data array, with the display HTML as property of every item object
		return FlexicontentFields::createItemsListHTML($params, $item_list, $field, $item, $itemIDs, $options);
	}


	// Helper method to create SQL query for retrieving items list data
	static function createItemsListSQL($params, $itemIDs, $field, $item, $options)
	{
		$db = JFactory::getDbo();
		$user = JFactory::getUser();

		// Options
		$reverse_field_id = (int) $params->get('reverse_field', 0);
		$isform = isset($options->isform) ? (int) $options->isform : 0;
		$states = isset($options->items_list_state)   // if isform this is ignored
			? $options->items_list_state
			: array(1,-5,2);
		$sfx = $isform ? '_form' : '';

		$scopes_where = array();

		if (!$isform)
		{
			$samelangonly = !$reverse_field_id || $params->get('samelangonly_view', 1) != -1
				? $params->get('samelangonly_view', 1)
				: $params->get('samelangonly', 1);

			if ($samelangonly)
			{
				$language = !$item->language || $item->language === '*'
					? JFactory::getLanguage()->getTag()
					: $item->language;
				$scopes_where[] = ' (ext.language = ' . $db->Quote($language) . ' OR ext.language = ' . $db->Quote('*') . ') ';
			}

			$use_publish_dates = !$reverse_field_id || $params->get('use_publish_dates_view', 1) != -1
				? $params->get('use_publish_dates_view', 1)
				: $params->get('use_publish_dates', 1);

			if ($use_publish_dates)
			{
				// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
				// thus the items are published globally at the time the author specified in his/her local clock

				//$now  = JFactory::getApplication()->requestTime;   // NOT correct behavior it should be UTC (below)
				//$now  = JFactory::getDate()->toSql();              // NOT good if string passed to function that will be cached, because string continuesly different

				$nowDate = 'UTC_TIMESTAMP()';  //$db->Quote($now);
				$nullDate = $db->getNullDate();

				$scopes_where[] = ' ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$nowDate.' )';
				$scopes_where[] = ' ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$nowDate.' )';
			}

			$onlypublished = !$reverse_field_id || $params->get('onlypublished_view', 1) != -1
				? (int) $params->get('onlypublished_view', 1)
				: (int) $params->get('onlypublished', 1);

			if ($onlypublished && count($states))
			{
				$scopes_where[] = ' i.state IN ('.implode(',',$states).')';
			}
		}


		// Item IDs via reversing a relation field
		if ($reverse_field_id)
		{
			$item_join = ' JOIN #__flexicontent_fields_item_relations AS fi_rel'
				.'  ON i.id=fi_rel.item_id AND fi_rel.field_id=' . $reverse_field_id . ' AND CAST(fi_rel.value AS SIGNED)=' . (int) $item->id;
		}

		// Indicate nothing to do, since no related items given
		elseif (empty($itemIDs))
		{
			return false;
		}

		// Item IDs via a given related items list (relation field and ... maybe other cases too)
		else
		{
			$item_where = ' AND i.id IN ('. implode(',', array_keys($itemIDs)) .')';
		}


		// category scope (reverse relation field parameter)
		if($params->get('reverse_scope_category', 0))
		{
			$cat_where = ' AND i.catid IN ('. implode(",", array_values($params->get('reverse_scope_category', 0))) .')';
		}

		// type scope (reverse relation field parameter)
		if($params->get('reverse_scope_types', 0))
		{
			$type_where = ' AND ext.type_id IN ('. implode(",", array_values($params->get('reverse_scope_types', 0))) .')';
		}

		// owner scope
		$ownedbyuser = !$reverse_field_id || $params->get('ownedbyuser_view', -1) != -1
			? (int) $params->get('ownedbyuser_view')
			: (int) $params->get('ownedbyuser', 0);

		switch ($ownedbyuser)
		{
			// Limit the related items list to items created by current user (editor or viewer)
			case 1:
				$itemowned_where = ' AND i.created_by=' . $user->id;
				break;

			// Limit the related items list to items created by the creator of current item
			case 2:
				$itemowned_where = ' AND i.created_by=' . $item->created_by;
				break;

			// case zero, do not apply any limitation to
		}


		// item count limit
		$itemcount = $isform ? (int) $params->get('itemcount_form ', 0) : (int) $params->get('itemcount', 0);
		$limit = $itemcount
			? ' LIMIT ' . $itemcount
			: '';


		// Get orderby SQL CLAUSE ('ordering' is passed by reference but no frontend user override is used (we give empty 'request_var')
		if ($params && $params->get('orderby') == 'manual')
		{
			$order = array(1 => 'manual', 2=>'');
			$orderby = ' ORDER BY FIELD(i.id, '. implode(',', array_keys($itemIDs)) .')';
		}
		else
		{
			$order = '';
			$orderby = flexicontent_db::buildItemOrderBy(
				$params,
				$order, $request_var='', $config_param='orderby',
				$item_tbl_alias = 'i', $relcat_tbl_alias = 'rel',
				$default_order='', $default_order_dir='', $sfx, $support_2nd_lvl=true
			);
		}
		$orderby_join = '';

		// Create JOIN for ordering items by a custom field (use SFX)
		if ( 'field' == $order[1] )
		{
			$orderbycustomfieldid = (int)$params->get('orderbycustomfieldid'.$sfx, 0);
			$orderbycustomfieldint = (int)$params->get('orderbycustomfieldint'.$sfx, 0);
			if ($orderbycustomfieldint == 4)
			{
				$orderby_join .= '
					LEFT JOIN (
						SELECT rf.item_id, SUM(fdat.hits) AS file_hits
						FROM #__flexicontent_fields_item_relations AS rf
						LEFT JOIN #__flexicontent_files AS fdat ON fdat.id = rf.value
				 		WHERE rf.field_id='.$orderbycustomfieldid.'
				 		GROUP BY rf.item_id
				 	) AS dl ON dl.item_id = i.id';
			}
			else $orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.$orderbycustomfieldid;
		}

		// Create JOIN for ordering items by a custom field (Level 2)
		if ( $sfx=='' && 'field' == $order[2] )
		{
			$orderbycustomfieldid_2nd = (int)$params->get('orderbycustomfieldid'.'_2nd', 0);
			$orderbycustomfieldint_2nd = (int)$params->get('orderbycustomfieldint'.'_2nd', 0);
			if ($orderbycustomfieldint_2nd == 4)
			{
				$orderby_join .= '
					LEFT JOIN (
						SELECT f2.item_id, SUM(fdat2.hits) AS file_hits2
						FROM #__flexicontent_fields_item_relations AS f2
						LEFT JOIN #__flexicontent_files AS fdat2 ON fdat2.id = f2.value
				 		WHERE f2.field_id='.$orderbycustomfieldid_2nd.'
				 		GROUP BY f2.item_id
				 	) AS dl2 ON dl2.item_id = i.id';
			}
			else $orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f2 ON f2.item_id = i.id AND f2.field_id='.$orderbycustomfieldid_2nd;
		}

		// Create JOIN for ordering items by author's name
		if ( in_array('author', $order) || in_array('rauthor', $order) ) {
			$orderby_col = '';
			$orderby_join .= ' LEFT JOIN #__users AS u ON u.id = i.created_by';
		}

		// Create JOIN for ordering items by a most commented
		if ( in_array('commented', $order) ) {
			$orderby_col   = ', COUNT(DISTINCT com.id) AS comments_total';
			$orderby_join .= ' LEFT JOIN #__jcomments AS com ON com.object_id = i.id AND com.object_group="com_flexicontent" AND com.published="1"';
		}

		// Create JOIN for ordering items by a most rated
		if ( in_array('rated', $order) )
		{
			$rating_join = null;
			$orderby_col   = ', ' . flexicontent_db::buildRatingOrderingColumn($rating_join);
			$orderby_join .= ' LEFT JOIN ' . $rating_join;
		}


		// Because query includes specific items it should be fast
		$return_items_array = isset($options->return_items_array) ? (int) $options->return_items_array : 0;

		// Only count found items
		if ($return_items_array == 3)
		{
			$query = 'SELECT COUNT(*) AS total FROM ('
				.' SELECT 1'
				.' FROM #__flexicontent_items_tmp AS i '
				.' LEFT JOIN #__flexicontent_items_ext AS ext ON i.id=ext.item_id '
				. @ $item_join
				. @ $orderby_join
				.' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON i.id=rel.itemid '  // to get info for item's categories
				.' LEFT JOIN #__categories AS c ON c.id=rel.catid '
				.' WHERE 1 '
				. @ $item_where
				. @ $type_where
				. @ $cat_where
				. @ $itemowned_where
				. ($scopes_where ? ' AND ' . implode(' AND ', $scopes_where) : '')
				.' GROUP BY i.id '
				//. $orderby
				//.@ $limit
				.') AS sq';
		}
		else
		{
			$query = 'SELECT ' . ($itemcount ? 'SQL_CALC_FOUND_ROWS' : '') . ' i.*, ext.*,'
				.' GROUP_CONCAT(c.id SEPARATOR  ",") AS catidlist, '
				.' GROUP_CONCAT(c.alias SEPARATOR  ",") AS  cataliaslist '
				. @ $orderby_col
				.' FROM #__content AS i '
				.' LEFT JOIN #__flexicontent_items_ext AS ext ON i.id=ext.item_id '
				. @ $item_join
				. @ $orderby_join
				.' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON i.id=rel.itemid '  // to get info for item's categories
				.' LEFT JOIN #__categories AS c ON c.id=rel.catid '
				.' WHERE 1 '
				. @ $item_where
				. @ $type_where
				. @ $cat_where
				. @ $itemowned_where
				. ($scopes_where ? ' AND ' . implode(' AND ', $scopes_where) : '')
				.' GROUP BY i.id '
				. $orderby
				.@ $limit
				;
		}

		//echo "<pre>".$query."</pre>";
		return $query;
	}


	// Helper method to create HTML display of an item list according to replacements
	static function createItemsListHTML($params, & $item_list, $field, $item, & $itemIDs, $options)
	{
		$isform = isset($options->isform) ? (int) $options->isform : 0;
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

		$db = JFactory::getDbo();
		global $globalcats, $globalnoroute, $fc_run_times;
		if (!is_array($globalnoroute)) $globalnoroute = array();

		// Get fields of type relation
		static $disallowed_fieldnames = null;
		$disallowed_fields = array('relation', 'relation_reverse');
		if ($disallowed_fieldnames===null)
		{
			$query = "SELECT name FROM #__flexicontent_fields WHERE field_type IN ('". implode("','", $disallowed_fields) ."')";
			$db->setQuery($query);
			$field_name_col = $db->loadColumn();
			$disallowed_fieldnames = !$field_name_col ? array() : array_flip($field_name_col);
		}

		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space	= $params->get( 'remove_space', 0 ) ;
		$pretext			= $params->get( $isform ? 'pretext_form' : 'pretext', '' ) ;
		$posttext			= $params->get( $isform ? 'posttext_form' : 'posttext', '' ) ;
		$separatorf		= $params->get( $isform ? 'separator' : 'separatorf' ) ;
		$opentag			= $params->get( $isform ? 'opentag_form' : 'opentag', '' ) ;
		$closetag			= $params->get( $isform ? 'closetag_form' : 'closetag', '' ) ;

		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }

		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br class="fcclear" />';
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

			case 5:
			$separatorf = '';
			break;

			default:
			$separatorf = '&nbsp;';
			break;
		}

		// some parameter shortcuts
		$relitem_html_param = $params->get('relitem_html_override', $isform ? 'relitem_html_form' : 'relitem_html');
		$relitem_html = $params->get( $relitem_html_param, '__display_text__' ) ;
		$displayway		= $params->get( $isform ? 'displayway_form' : 'displayway', 1 ) ;
		$addlink 			= $params->get( $isform ? 'addlink_form' : 'addlink', 1 ) ;
		$addtooltip		= $params->get( $isform ? 'addtooltip_form' : 'addtooltip', 1 ) ;

		// Parse and identify custom fields
		$result = preg_match_all("/\{\{([a-zA-Z_0-9-]+)(##)?([a-zA-Z_0-9-]+)?\}\}/", $relitem_html, $field_matches);
		$custom_field_reps    = $result ? $field_matches[0] : array();
		$custom_field_names   = $result ? $field_matches[1] : array();
		$custom_field_methods = $result ? $field_matches[3] : array();

		/*
		foreach ($custom_field_names as $i => $custom_field_name)
		{
			$parsed_fields[] = $custom_field_names[$i] . ($custom_field_methods[$i] ? "->". $custom_field_methods[$i] : "");
		}
		echo "$relitem_html :: Fields for Related Items List: ". implode(", ", $parsed_fields ? $parsed_fields : array() ) ."<br/>\n";
		*/

		// ***
		// *** Parse and identify language strings and then make language replacements
		// ***

		$result = preg_match_all("/\%\%([^%]+)\%\%/", $relitem_html, $translate_matches);
		$translate_strings = $result ? $translate_matches[1] : array('FLEXI_READ_MORE_ABOUT');
		foreach ($translate_strings as $translate_string)
		{
			$relitem_html = str_replace('%%'.$translate_string.'%%', JText::_($translate_string), $relitem_html);
		}

		foreach($item_list as $result)
		{
			$itemslug = $result->id.":".$result->alias;
			$catslug = "";

			// Check if removed from category or inside a noRoute category or inside a non-published category and use main category slug or other routable & published category slug
			$catid_arr = explode(',', $result->catidlist);
			$catalias_arr = explode(',', $result->cataliaslist);

			for($i=0; $i<count($catid_arr); $i++)
			{
				$itemcataliases[$catid_arr[$i]] = $catalias_arr[$i];
			}

			$rel_itemid = $result->id;
			$rel_catid = isset($itemIDs[$result->id]->catid)
				? $itemIDs[$result->id]->catid
				: $result->catid;

			if ( isset($itemcataliases[$rel_catid]) && !in_array($rel_catid, $globalnoroute) && $globalcats[$rel_catid]->published)
			{
				$catslug = $rel_catid.":".$itemcataliases[$rel_catid];
			}

			else if (!in_array($result->catid, $globalnoroute) && $globalcats[$result->catid]->published )
			{
				$catslug = $globalcats[$result->catid]->slug;
			}

			else
			{
				foreach ($catid_arr as $catid)
				{
					if ( !in_array($catid, $globalnoroute) && $globalcats[$catid]->published)
					{
						$catslug = $globalcats[$catid]->slug;
						break;
					}
				}
			}

			$result->slug = $itemslug;
			$result->categoryslug = $catslug;
		}


		// ***
		// *** Perform field's display replacements
		// ***

		$i_slave = $field ? $item->id . '_' . $field->id : '';
		if ( $i_slave )
		{
			$fc_run_times['render_subfields'][$i_slave] = 0;
		}

		// Treat list creation as the 'sublist' VIEW case
		$_view = 'sublist';

		foreach($custom_field_names as $i => $custom_field_name)
		{
			if ( isset($disallowed_fieldnames[$custom_field_name]) ) continue;
			if ( $custom_field_methods[$i] == 'label' ) continue;

			if ($i_slave) $start_microtime = microtime(true);

			$display_var = $custom_field_methods[$i] ? $custom_field_methods[$i] : 'display';
			FlexicontentFields::getFieldDisplay($item_list, $custom_field_name, $custom_field_values=null, $display_var, $_view);

			if ($i_slave) $fc_run_times['render_subfields'][$i_slave] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}

		$tooltip_class = ' hasTooltip';
		$display_html = array();
		$read_more_about = JText::_('FLEXI_READ_MORE_ABOUT', true);
		foreach($item_list as $result)
		{
			$url_read_more = isset($itemIDs[$result->id]->url_read_more)
				? JText::_($itemIDs[$result->id]->url_read_more, true)
				: $read_more_about;
			$url_class = isset($itemIDs[$result->id]->url_class)
				? $itemIDs[$result->id]->url_class
				: 'relateditem';

			// a. Replace some custom made strings
			$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($result->slug, $result->categoryslug, 0, $result));
			$item_title_escaped = htmlspecialchars($result->title, ENT_COMPAT, 'UTF-8');

			$tooltip_title = flexicontent_html::getToolTip($url_read_more, $item_title_escaped, $translate=0, $escape=0);
			$item_tooltip = ' class="'.$url_class.$tooltip_class.'" title="'.$tooltip_title.'" ';

			$display_text = $displayway ? $result->title : $result->id;
			$display_text = !$addlink ? $display_text : '<a href="'.$item_url.'"'.($addtooltip ? $item_tooltip : '').' >' .$display_text. '</a>';

			$curr_relitem_html = $relitem_html;
			$curr_relitem_html = str_replace('__item_url__', $item_url, $curr_relitem_html);
			$curr_relitem_html = str_replace('__item_title_escaped__', $item_title_escaped, $curr_relitem_html);
			$curr_relitem_html = str_replace('__item_tooltip__', $item_tooltip, $curr_relitem_html);
			$curr_relitem_html = str_replace('__display_text__', $display_text, $curr_relitem_html);

			// b. Replace item properties, e.g. {item->id}, (item->title}, etc
			$null_field = null;
			FlexicontentFields::doQueryReplacements($curr_relitem_html, $null_field, $result);

			// c. Replace HTML display of various item fields
			$err_mssg = 'Cannot replace field: "%s" because it is of not allowed field type: "%s", which can cause loop or other problem';
			foreach($custom_field_names as $i => $custom_field_name)
			{
				$_field = @ $result->fields[$custom_field_name];
				$custom_field_display = '';
				if ($is_disallowed_field = isset($disallowed_fieldnames[$custom_field_name]))
				{
					$custom_field_display .= sprintf($err_mssg, $custom_field_name, @ $_field->field_type);
				}
				else
				{
					$display_var = $custom_field_methods[$i] ? $custom_field_methods[$i] : 'display';
					$custom_field_display .= @ $_field->{$display_var};
				}
				$curr_relitem_html = str_replace($custom_field_reps[$i], $custom_field_display, $curr_relitem_html);
			}

			$result->ri_url  = $item_url;
			$result->ri_html = $pretext . $curr_relitem_html . $posttext;
			$display_html[] = $result->ri_html;
		}

		// Return item list data array
		$return_items_array = isset($options->return_items_array) ? (int) $options->return_items_array : 0;
		if ($return_items_array)
		{
			return $item_list;
		}

		// Return item list HTML
		else
		{
			$display_html = implode($separatorf, $display_html);
			return $display_html
				? $opentag . $display_html . $closetag
				: '';
		}
	}





	// **********************************************
	// Helper methods for handling runtime statistics
	// **********************************************

	static function getFieldRenderTimes( &$fields_render_total=0 )
	{
		global $fc_run_times;
		$fields_render = array();

		$inline_css_val = 'float:left !important; display:inline-block !important;';
		$inline_css_lbl = 'float:left !important; display:inline-block !important; margin-left:8px !important; min-width:100px; text-align:left !important;';
		foreach ($fc_run_times['render_field'] as $field_type => $field_msecs)
		{
			// Total rendering time of fields
			$fields_render_total += $field_msecs;

			// Create Log a message about current field rendering time
			$fld_msg =
				'<span class="flexi value" style="'.$inline_css_val.'">'. sprintf("%.3f s",$field_msecs/1000000) .'</span>'.
				'<span class="flexi label" style="'.$inline_css_lbl.'">'.$field_type.'</span>'
				;
			// Check if field rendered other fields as part of it's display
			if ( isset($fc_run_times['render_subfields'][$field_type]) ) {
				$fld_msg .= " <small> - Field rendered other fields. Time was (retrieval+render)= ";
				$fld_msg .= sprintf("%.3f s", $fc_run_times['render_subfields'][$field_type]/1000000).'</small>';
			}
			$fields_render[] = $fld_msg;
		}
		return $fields_render;
	}


	static function getFilterCreationTimes( &$filters_creation_total=0 )
	{
		global $fc_run_times;
		$filters_creation = array();

		if ( isset($fc_run_times['create_filter_init']) ) {
			$filters_creation_total += $fc_run_times['create_filter_init'];
		}
		$inline_css_val = 'float:left !important; display:inline-block !important;';
		$inline_css_lbl = 'float:left !important; display:inline-block !important; margin-left:8px !important; min-width:100px !important; text-align:left !important;';
		foreach ($fc_run_times['create_filter'] as $field_type => $filter_msecs)
		{
			// Total creation time of filters
			$filters_creation_total += $filter_msecs;

			// Create Log a message about current filter creation time
			$fld_msg =
				'<span class="" style="'.$inline_css_val.'">'. sprintf("%.3f s",$filter_msecs/1000000) .'</span>'.
				'<span class="flexi label" style="'.$inline_css_lbl.'">'.$field_type.'</span>'.
				'<span class="" style="'.$inline_css_val.' min-width:200px;">'.$fc_run_times['create_filter_type'][$field_type].'</span>'
				;

			$filters_creation[] = $fld_msg;
		}
		return $filters_creation;
	}



	static function & getFieldsPerGroup()
	{
		static $ginfo = null;
		if ( $ginfo!==null ) return $ginfo;

		$db = JFactory::getDbo();
		$query = 'SELECT f.* '
			. ' FROM #__flexicontent_fields AS f '
			. ' WHERE f.published = 1'
			. ' AND f.field_type = "fieldgroup" '
			;
		$db->setQuery($query);
		$field_groups = $db->loadObjectList('id');

		$grp_to_field = array();
		$field_to_grp = array();

		foreach($field_groups as $field_id => $field_group)
		{
			// Create field parameters, if not already created, NOTEL: for 'custom' fields loadFieldConfig() is optional
			$field_group->parameters = new JRegistry($field_group->attribs);

			$fieldids = $field_group->parameters->get('fields', array());

			if (empty($fieldids))
			{
				$fieldids = array();
			}

			if (!is_array($fieldids))
			{
				$fieldids = preg_split("/[\|,]/", $fieldids);
			}

			$field_group->label = JText::_($field_group->label);

			foreach ($fieldids as $grouped_fieldid)
			{
				$grp_to_field[$field_id][] = $grouped_fieldid;
				$field_to_grp[$grouped_fieldid] = $field_id;
			}
		}

		$ginfo = new stdClass;
		$ginfo->grps = $field_groups;
		$ginfo->grp_to_field = $grp_to_field;
		$ginfo->field_to_grp = $field_to_grp;

		return $ginfo;
	}

}