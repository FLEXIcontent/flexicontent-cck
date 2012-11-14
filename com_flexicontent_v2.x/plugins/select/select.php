<?php
/**
 * @version 1.0 $Id: select.php 1251 2012-04-16 02:36:00Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.select
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.event.plugin');

class plgFlexicontent_fieldsSelect extends JPlugin
{
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsSelect( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_select', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'select') return;
		
		$field->label = JText::_($field->label);

		// some parameter shortcuts
		$sql_mode				= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements	= $field->parameters->get( 'field_elements' ) ;
		$default_value	= $field->parameters->get( 'default_value' ) ;
		
		$firstoptiontext	= $field->parameters->get( 'firstoptiontext', 'Please Select' ) ;
		$usefirstoption		= $field->parameters->get( 'usefirstoption', 1 ) ;
		
		$required 	= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';

		// initialise property
		if($item->version < 2 && $default_value) {
			$field->value = array();
			$field->value[0] = $default_value;
		} else if (!$field->value) {
			$field->value = array();
			$field->value[0] = '';
		}
		
		$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']' : $field->name;
		$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
		
		// Get indexed element values
		$elements = FlexicontentFields::indexedField_getElements($field, $item, $extra_props=array());
		if ($elements==false && $sql_mode) {
			$field->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			return;
		}
		
		// Create field's HTML display for item form
		$options = array();
		if ($usefirstoption) $options[] = JHTML::_('select.option', '', JText::_($firstoptiontext));
		foreach ($elements as $element) {
			$options[] = JHTML::_('select.option', $element->value, JText::_($element->text));
		}
		$field->html	= JHTML::_('select.genericlist', $options, $fieldname, 'class="'.$required.'"', 'value', 'text', $field->value, $elementid);
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'select') return;

		$field->label = JText::_($field->label);
		
		$values = $values ? $values : $field->value;

		// some parameter shortcuts
		$remove_space	= $field->parameters->get( 'remove_space', 0 ) ;
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$sql_mode			= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements = $field->parameters->get( 'field_elements', '' ) ;
		$text_or_value= $field->parameters->get( 'text_or_value', 1 ) ;
		
		if($pretext) 	{ $pretext 	= $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext	= $remove_space ? $posttext : ' ' . $posttext; }
		
		if ( !$values ) { $field->{$prop}=''; return; }
		
		// Get indexed element values
		$elements = FlexicontentFields::indexedField_getElements($field, $item, $extra_props=array());
		if ($elements==false && $sql_mode) {
			$field->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			return;
		}
		
		// Create display of field
		$display = '';
		$display_index = '';
		if ( count($values) ) {
			$element = @$elements[ $values[0] ];
			if ($element) {
				$display	= $pretext . JText::_($text_or_value ? $element->text : $element->value) . $posttext;
				$display_index = $values[0];
			}
		}
		$field->{$prop}	= $display;
		$field->display_index	= $display_index;
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'select') return;
		if(!is_array($post) && !strlen($post)) return;
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		foreach ($post as $n=>$v)
		{
			if ($post[$n] !== '')
			{
				$newpost[$new] = $post[$n];
			}
			$new++;
		}
		$post = $newpost;
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
	function onAdvSearchDisplayField(&$field, &$item)
	{
		if($field->field_type != 'select') return;
		
		plgFlexicontent_fieldsSelect::onDisplayField($field, $item);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'select') return;

		// ** some parameter shortcuts
		$field_elements		= $filter->parameters->get( 'field_elements' ) ;
		$sql_mode			= $filter->parameters->get( 'sql_mode', 0 ) ;
		$label_filter 		= $filter->parameters->get( 'display_label_filter', 0 ) ;
		if ($label_filter == 2) $text_select = $filter->label; else $text_select = JText::_('FLEXI_ALL');
		$filter->html = '';
		
		
		// *** Retrieve values
		if ($sql_mode) {  // CASE 1: SQL mode
			
			$db =& JFactory::getDBO();
			$jAp=& JFactory::getApplication();
			
			// !! CHECK: The field depends on item data so it cannot be used as filter in category
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			preg_match_all("/{item->[^}]+}/", $query, $matches);
			if (count($matches[0])) {
				$filter->html = sprintf( JText::_('FLEXI_WARNING_ITEM_SPECIFIC_AS_CATEGORY_FILTER'), $filter->label );
				return;
			}
			
			// Execute SQL query to retrieve the field value - label pair
			$db->setQuery($query);
			$results = $db->loadObjectList('value');
			
			// !! CHECK: DB query had an error, set a message to warn the user
			if ($db->getErrorNum()) {
				JError::raiseWarning($db->getErrorNum(), $db->getErrorMsg(). "<br />".$query."<br />");
				$filter->html	 = "<br />Filter for : $field->label cannot be displayed, error during db query, please correct field configuration<br />";
				return;
			}
			
			// !! CHECK: DB query produced no data, do not create the filter
			if (!$results) {
				$filter->html = '';
				return;
			}

		} else { // CASE 2: Elements mode

			$listelements = preg_split("/[\s]*%%[\s]*/", $field_elements);
			if (empty($listelements[count($listelements)-1])) {
				unset($listelements[count($listelements)-1]);
			}

			$listarrays = array();
			foreach ($listelements as $listelement) {
				list($val, $label) = explode("::", $listelement);
				$results[$val] = new stdClass();
				$results[$val]->value = $val;
				$results[$val]->text  = $label;
			}
			
		}
		
		
		// *** Limit values, show only allowed values according to category configuration parameter 'limit_filter_values'
		$results = array_intersect_key($results, flexicontent_cats::getFilterValues($filter));
		
		
		// *** Create the select form field used for filtering
		$options = array();
		$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
		
		foreach($results as $result) {
			if (!trim($result->value)) continue;
			$options[] = JHTML::_('select.option', $result->value, JText::_($result->text));
		}
		if ($label_filter == 1) $filter->html  .= $filter->label.': ';
		$filter->html	.= JHTML::_('select.genericlist', $options, 'filter_'.$filter->id, ' class="fc_field_filter" onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
		
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item) {
		if ($field->field_type != 'select') return;
		if ( !$field->isadvsearch ) return;
		
		if ($post===null) $post = & FlexicontentFields::searchIndex_getFieldValues($field,$item);
		$elements = FlexicontentFields::indexedField_getElements($field, $item, $extra_props=array());
		$values = FlexicontentFields::indexedField_getValues($field, $elements, $post, $prepost_prop='text');
		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array(), $search_properties=array('text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ($field->field_type != 'select') return;
		if ( !$field->issearch ) return;
		
		if ($post===null) $post = & FlexicontentFields::searchIndex_getFieldValues($field,$item);
		$elements = FlexicontentFields::indexedField_getElements($field, $item, $extra_props=array());
		$values = FlexicontentFields::indexedField_getValues($field, $elements, $post, $prepost_prop='text');
		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array(), $search_properties=array('text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
		
	// Method to get ALL items that have matching search values for the current field id
	function onFLEXIAdvSearch(&$field)
	{
		if ($field->field_type != 'select') return;
		
		FlexicontentFields::onFLEXIAdvSearch($field);
	}
	
}
