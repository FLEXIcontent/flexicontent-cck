<?php
/**
 * @version 1.0 $Id: text.php 623 2011-06-30 14:29:28Z enjoyman@gmail.com $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.text
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

class plgFlexicontent_fieldsTextSelect extends JPlugin
{
	static $field_types = array('textselect');
	static $extra_props = array();
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsTextSelect( &$subject, $params ) {
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_text', JPATH_ADMINISTRATOR);
		JPlugin::loadLanguage('plg_flexicontent_fields_select', JPATH_ADMINISTRATOR);
		JPlugin::loadLanguage('plg_flexicontent_fields_textselect', JPATH_ADMINISTRATOR);
		JPluginHelper::importPlugin('flexicontent_fields', 'text' );
		JPluginHelper::importPlugin('flexicontent_fields', 'select' );
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item) {
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onDisplayField', array(&$field, &$item));
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onDisplayFieldValue', array(&$field, $item, $values, $prop));
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onBeforeSaveField', array(&$field, &$post, &$file, &$item));
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onAfterSaveField', array(&$field, &$post, &$file, &$item));
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onBeforeDeleteField', array(&$field, &$item));
	}
	
	
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		plgFlexicontent_fieldsTextselect::onDisplayFilter($filter, $value, $formName);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		// Prepare field as IF it is a select field
		plgFlexicontent_fieldsTextselect::_prepareField_as_SelectField($filter);
		
		// Create a select field object
		$dispatcher = JDispatcher::getInstance();
		$sel_fld_obj = new plgFlexicontent_fieldsSelect($dispatcher, array());
		
		// Get the FILTER's display ... by changing field type to 'select'
		$filter->field_type = 'select';
		$sel_fld_obj->onDisplayFilter($filter, $value, $formName);
		$filter->field_type = 'textselect';
	}	
	
	
	// Get item ids having the value(s) of filter
	function getFiltered(&$filter, $value)
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		return FlexicontentFields::getFiltered($filter, $value, $return_sql=true);
	}
	
		
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$field, $value)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		return FlexicontentFields::getFilteredSearch($field, $value, $return_sql=true);
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item) {
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		FLEXIUtilities::call_FC_Field_Func('text', 'onIndexAdvSearch', array(&$field, &$post, &$item));
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		FLEXIUtilities::call_FC_Field_Func('text', 'onIndexSearch', array(&$field, &$post, &$item));
		return true;
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	function _prepareField_as_SelectField(&$field)
	{
		// Field parameters meant to be used by select field are prefixed with 'select_'
		// *** THESE include only parameters used for creating filter's display ***
		$arrays = $field->parameters->toArray();
		foreach($arrays as $k=>$a) {
			$select_ = substr($k, 0, 7);
			if($select_=='select_') {
				$keyname = $select_ = substr($k, 7);
				$field->parameters->set($keyname, $field->parameters->get($k));
			}
		}
		
		if ( !$field->parameters->get('sql_mode_override') ) {
			// Default is to use all text's field values
			$query = "SELECT value, value as text FROM #__flexicontent_fields_item_relations as fir WHERE field_id='{field_id}' AND value != '' GROUP BY value";
		} else {
			// Custom query for value retrieval
			$query = $field->parameters->set('sql_mode_query');
		}
		$query = str_replace('{field_id}', $field->id, $query);
		
		// Set remaining parameters needed for Select Field
		$field->parameters->set('sql_mode', 1);
		$field->parameters->set('field_elements', $query);		
	}
	
}
