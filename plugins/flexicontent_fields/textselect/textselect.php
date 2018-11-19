<?php
/**
 * @package         FLEXIcontent
 * @version         3.2
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');
JLoader::register('plgFlexicontent_fieldsText', JPATH_SITE . '/plugins/flexicontent_fields/text/text.php');
JLoader::register('plgFlexicontent_fieldsSelect', JPATH_SITE . '/plugins/flexicontent_fields/select/select.php');

class plgFlexicontent_fieldsTextselect extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_select', JPATH_ADMINISTRATOR);
		JPlugin::loadLanguage('plg_flexicontent_fields_text', JPATH_ADMINISTRATOR);
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onDisplayField', array(&$field, &$item));
	}


	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onDisplayFieldValue', array(&$field, $item, $values, $prop));
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onBeforeSaveField', array(&$field, &$post, &$file, &$item));
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onAfterSaveField', array(&$field, &$post, &$file, &$item));
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onBeforeDeleteField', array(&$field, &$item));
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$this->onDisplayFilter($filter, $value, $formName, $isSearchView=1);
	}


	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm', $isSearchView=0)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		// Prepare field as IF it is a using custom Query or custom elements
		plgFlexicontent_fieldsTextselect::_prepareField_as_SelectField($filter);
		$asSelect = $filter->parameters->get('filter_customize_options') != 0;

		// Create a 'select' or 'text' field object and render its filter display
		$dispatcher = JEventDispatcher::getInstance();
		$filter->field_type = $asSelect ? 'select' : 'text';
		$_fld_obj = $asSelect ? new plgFlexicontent_fieldsSelect($dispatcher, array()) : new plgFlexicontent_fieldsText($dispatcher, array());
		$_fld_obj->onDisplayFilter($filter, $value, $formName);
		$filter->field_type = 'textselect';
	}


 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		return FlexicontentFields::getFiltered($filter, $value, $return_sql);
	}


 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

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

		FLEXIUtilities::call_FC_Field_Func('text', 'onIndexAdvSearch', array(&$field, &$post, &$item));
		return true;
	}


	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;

		FLEXIUtilities::call_FC_Field_Func('text', 'onIndexSearch', array(&$field, &$post, &$item));
		return true;
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	function _prepareField_as_SelectField(&$field)
	{
		// Field parameters meant to be used by select field are prefixed with 'select_'
		// *** THESE include only parameters used for creating filter's display ***
		$arrays = $field->parameters->toArray();
		foreach($arrays as $k=>$a)
		{
			$select_ = substr($k, 0, 7);
			if ($select_=='select_')
			{
				$keyname = $select_ = substr($k, 7);
				$field->parameters->set($keyname, $field->parameters->get($k));
			}
		}
	}

}
