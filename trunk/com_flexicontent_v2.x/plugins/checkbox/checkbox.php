<?php
/**
 * @version 1.0 $Id: checkbox.php 1227 2012-04-02 15:14:11Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.checkbox
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

class plgFlexicontent_fieldsCheckbox extends JPlugin
{
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsCheckbox( &$subject, $params )
	{
		parent::__construct( $subject, $params );
        	JPlugin::loadLanguage('plg_flexicontent_fields_checkbox', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'checkbox') return;
		
		$field->label = JText::_($field->label);

		// some parameter shortcuts
		$sql_mode				= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements	= $field->parameters->get( 'field_elements' ) ;
		$separator			= $field->parameters->get( 'separator' ) ;
		$default_values	= $field->parameters->get( 'default_values', '' ) ;
		
		$required 	= $field->parameters->get( 'required', 0 ) ;
		//$required 	= $required ? ' required validate-checkbox' : '';

		$max_values		= $field->parameters->get( 'max_values', 0 ) ;
		$min_values		= $field->parameters->get( 'min_values', 0 ) ;
		$exact_values	= $field->parameters->get( 'exact_values', 0 ) ;
		if ($required && !$min_values) $min_values = 1;
		if ($exact_values) $max_values = $min_values = 0;
		$js_popup_err	= $field->parameters->get( 'js_popup_err', 0 ) ;
		
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
		if($item->version < 2 && $default_values) {
			$field->value = explode(",", $default_values);
		} else if (!$field->value) {
			$field->value = array();
			$field->value[0] = '';
		}
		
		$fieldname = FLEXI_J16GE ? 'custom['.$field->name.'][]' : $field->name.'[]';
		$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
		
		// Get indexed element values
		$elements = FlexicontentFields::indexedField_getElements($field, $item, $extra_props=array('image'));
		if ($elements==false && $sql_mode) {
			$field->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			return;
		}
		
		$class = '';//$required;
		$attribs = '';
		if ($max_values)    $attribs .= ' max_values="'.$max_values.'" ';
		if ($min_values)    $attribs .= ' min_values="'.$min_values.'" ';
		if ($exact_values)  $attribs .= ' exact_values="'.$exact_values.'" ';
		if ($js_popup_err)  $attribs .= ' js_popup_err="'.$js_popup_err.'" ';
		if ($max_values || $min_values || $exact_values)  $class .= ' validate-cboxlimitations ';
		if ($class)  $attribs .= ' class="'.$class.'" ';
		
		// Create field's HTML display for item form
		$i = 0;
		$options = "";
		foreach ($elements as $element) {
			$checked  = in_array($element->value, $field->value)  ?  ' checked="checked"'  :  '';
			$options .= '<label><input type="checkbox" id="'.$elementid.'_'.$i.'" name="'.$fieldname.'" '.$attribs.' value="'.$element->value.'" '.$checked.' />'.JText::_($element->text).'</label>'.$separator;
			$i++;
		}
		$field->html = $options;
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'checkbox') return;

		$field->label = JText::_($field->label);
		
		$values = $values ? $values : $field->value;

		// some parameter shortcuts
		$sql_mode			= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements = $field->parameters->get( 'field_elements', '' ) ;
		$separatorf		= $field->parameters->get( 'separatorf' ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;

		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br />';
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

			default:
			$separatorf = '&nbsp;';
			break;
		}
		
		if ( !$values ) { $field->{$prop}=''; return; }
		
		// Get indexed element values
		$elements = FlexicontentFields::indexedField_getElements($field, $item, $extra_props=array('image'));
		if ($elements==false && $sql_mode) {
			$field->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			return;
		}
		
		// Create display of field
		$display = array();
		$display_index = array();
		for($n=0, $c=count($values); $n<$c; $n++) {
			$element = @$elements[ $values[$n] ];
			if ( $element ) {
				$display[] = JText::_($element->text);
				$display_index[] = $element->value;
			}
		}
		$field->{$prop} = implode($separatorf, $display);
		$field->{$prop} = $opentag . $field->{$prop} . $closetag;
		$field->display_index = implode($separatorf, $display_index);
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'checkbox') return;
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
		if($field->field_type != 'checkbox') return;
		
		plgFlexicontent_fieldsCheckbox::onDisplayField($field, $item);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'checkbox') return;

		// ** some parameter shortcuts
		$field_elements		= $filter->parameters->get( 'field_elements' ) ;
		$sql_mode			= $filter->parameters->get( 'sql_mode', 0 ) ;
		$label_filter 		= $filter->parameters->get( 'display_label_filter', 0 ) ;
		if ($label_filter == 2) $text_select = $filter->label; else $text_select = JText::_('FLEXI_ALL');
		$field->html = '';
		
		
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
				$field->html = '';
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
		$filter->html	.= JHTML::_('select.genericlist', $options, 'filter_'.$filter->id, 'onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
		
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item) {
		if ($field->field_type != 'checkbox') return;
		if ( !$field->isadvsearch ) return;
		
		if ($post===null) $post = & FlexicontentFields::searchIndex_getFieldValues($field,$item);
		$elements = FlexicontentFields::indexedField_getElements($field, $item, $extra_props=array('image'));
		$values = FlexicontentFields::indexedField_getValues($field, $elements, $post, $prepost_prop='text');
		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array(), $search_properties=array('text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ($field->field_type != 'checkbox') return;
		if ( !$field->issearch ) return;
		
		if ($post===null) $post = & FlexicontentFields::searchIndex_getFieldValues($field,$item);
		$elements = FlexicontentFields::indexedField_getElements($field, $item, $extra_props=array('image'));
		$values = FlexicontentFields::indexedField_getValues($field, $elements, $post, $prepost_prop='text');
		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array(), $search_properties=array('text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
		
	// Method to get ALL items that have matching search values for the current field id
	function onFLEXIAdvSearch(&$field)
	{
		if ($field->field_type != 'checkbox') return;
		
		FlexicontentFields::onFLEXIAdvSearch($field);
	}
	
}
