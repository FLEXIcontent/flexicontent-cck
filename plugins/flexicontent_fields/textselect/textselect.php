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

jimport('cms.plugin.plugin');

class plgFlexicontent_fieldsTextselect extends JPlugin
{
	static $field_types = array('textselect');
	static $extra_props = array();
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function __construct( &$subject, $params ) {
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
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onDisplayField', array(&$field, &$item));
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
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
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onBeforeSaveField', array(&$field, &$post, &$file, &$item));
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// field_type is not changed text field can handle this field type
		FLEXIUtilities::call_FC_Field_Func('text', 'onAfterSaveField', array(&$field, &$post, &$file, &$item));
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
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
		
		$this->onDisplayFilter($filter, $value, $formName, $isSearchView=1);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm', $isSearchView=0)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		// Prepare field as IF it is a using custom Query or custom elements
		plgFlexicontent_fieldsTextselect::_prepareField_as_SelectField($filter);
		$asSelect = $filter->parameters->get('filter_customize_options') != 0;
		
		// Create a 'select' or 'text' field object and render its filter display
		$dispatcher = JDispatcher::getInstance();
		$filter->field_type = $asSelect ? 'select' : 'text';
		$_fld_obj = $asSelect ? new plgFlexicontent_fieldsSelect($dispatcher, array()) : new plgFlexicontent_fieldsText($dispatcher, array());
		$_fld_obj->onDisplayFilter($filter, $value, $formName);
		$filter->field_type = 'textselect';
	}	
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		return FlexicontentFields::getFiltered($filter, $value, $return_sql);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
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
	}
	
}
