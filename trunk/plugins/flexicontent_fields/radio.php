<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.radio
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

class plgFlexicontent_fieldsRadio extends JPlugin
{
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsRadio( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_radio', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'radio') return;
		
		$field->label = JText::_($field->label);
		
		// some parameter shortcuts
		$sql_mode				= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements	= $field->parameters->get( 'field_elements' ) ;
		$separator			= $field->parameters->get( 'separator' ) ;
		$default_value	= $field->parameters->get( 'default_value', '' ) ;
		
		$required 	= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required validate-radio' : '';
		
		switch($separator)
		{
			case 0:
			$separator = '&nbsp;';
			break;

			case 1:
			$separator = '<br />';
			break;

			case 2:
			$separator = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separator = ',&nbsp;';
			break;

			//case 4:  // could cause problem in item form ?
			//$separatorf = $closetag . $opentag;
			//break;

			default:
			$separator = '&nbsp;';
			break;
		}

		// initialise property
		if (!$field->value && $default_value!=='') {
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
		if ( !$elements ) {
			if ($sql_mode)
				$field->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			else
				$field->html = JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
			return;
		}
		
		// Create field's HTML display for item form
		// Display as radio buttons
		$i = 0;
		$options = "";
		foreach ($elements as $element) {
			$checked  = in_array($element->value, $field->value)  ?  ' checked="checked"'  :  '';
			$options .= '<label><input type="radio" id="'.$elementid.'_'.$i.'" name="'.$fieldname.'" class="'.$required.'" value="'.$element->value.'" '.$checked.' />'.JText::_($element->text).'</label>'.$separator;
			$i++;
		}
		$field->html = $options;
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'radio') return;

		$field->label = JText::_($field->label);
		
		$values = $values ? $values : $field->value;

		// some parameter shortcuts
		$remove_space	= $field->parameters->get( 'remove_space', 0 ) ;
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$sql_mode			= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements = $field->parameters->get( 'field_elements', '' ) ;
		
		if($pretext) 	{ $pretext 	= $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext	= $remove_space ? $posttext : ' ' . $posttext; }
		
		if ( !$values ) { $field->{$prop}=''; return; }
		
		// Get indexed element values
		$elements = FlexicontentFields::indexedField_getElements($field, $item, $extra_props=array());
		if ( !$elements ) {
			if ($sql_mode)
				$field->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			else
				$field->html = JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
			return;
		}
		
		// Create display of field
		$display = '';
		$display_index = '';
		if ( count($values) ) {
			$element = @$elements[ $values[0] ];
			if ( $element ) {
				$display = $pretext . JText::_($element->text) . $posttext;
				$display_index = $element->value;
			}
		}
		$field->{$prop}	= $display;
		$field->display_index = $display_index;
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'radio') return;
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
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if($filter->field_type != 'radio') return;
		
		plgFlexicontent_fieldsRadio::onDisplayFilter($filter, $value, $formName);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'radio') return;

		// some parameter shortcuts
		$sql_mode				= $filter->parameters->get( 'sql_mode', 0 ) ;
		$label_filter 	= $filter->parameters->get( 'display_label_filter', 0 ) ;
		if ($label_filter == 2) $text_select = $filter->label; else $text_select = JText::_('FLEXI_ALL');
		$filter->html = '';
		
		// Get indexed element values
		$elements = FlexicontentFields::indexedField_getElements($filter, $item=null, $extra_props=array(), $item_pros=false);
		if ( !$elements ) {
			if ($sql_mode && $item_pros > 0)
				$filter->html = sprintf( JText::_('FLEXI_FIELD_ITEM_SPECIFIC_AS_FILTERABLE'), $filter->label );
			else if ($sql_mode)
				$filter->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			else
				$filter->html = JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
			return;
		}
		
		// Limit values, show only allowed values according to category configuration parameter 'limit_filter_values'
		$view = JRequest::getVar('view');
		$force_all = $view!='category';
		$results = array_intersect_key($elements, flexicontent_cats::getFilterValues($filter, $force_all));
		
		// Create the select form field used for filtering
		$options = array();
		$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
		
		foreach($results as $result) {
			if ( !strlen($result->value) ) continue;
			$options[] = JHTML::_('select.option', $result->value, JText::_($result->text));
		}
		if ($label_filter == 1) $filter->html  .= $filter->label.': ';
		$filter->html	.= JHTML::_('select.genericlist', $options, 'filter_'.$filter->id, ' class="fc_field_filter" onchange="document.getElementById(\''.$formName.'\').submit();"', 'value', 'text', $value);
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item) {
		if ($field->field_type != 'radio') return;
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
		if ($field->field_type != 'radio') return;
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
		if ($field->field_type != 'radio') return;
		
		FlexicontentFields::onFLEXIAdvSearch($field);
	}
	
}
