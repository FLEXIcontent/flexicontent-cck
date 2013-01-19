<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.checkboximage
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

class plgFlexicontent_fieldsCheckboximage extends JPlugin
{
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsCheckboximage( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_checkboximage', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'checkboximage') return;
		
		$field->label = JText::_($field->label);
		
		$app =& JFactory::getApplication();
		$prefix = $app->isAdmin() ? '../':'';
		
		// Import the file system library
		jimport('joomla.filesystem.file');
		
		// some parameter shortcuts
		$sql_mode				= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements	= $field->parameters->get( 'field_elements' ) ;
		$imagedir 			= preg_replace('#^(/)*#', '', $field->parameters->get( 'imagedir' ) );
		$imgpath				= $prefix . $imagedir;
		$separator			= $field->parameters->get( 'separator' ) ;
		$default_values	= $field->parameters->get( 'default_values', '' ) ;
		
		$firstoptiontext	= $field->parameters->get( 'firstoptiontext', 'Please Select' ) ;
		$usefirstoption		= $field->parameters->get( 'usefirstoption', 1 ) ;
		
		$required 	= $field->parameters->get( 'required', 0 ) ;
		//$required 	= $required ? ' required validate-checkbox' : '';
		
		$size		= $field->parameters->get( 'size', 6 ) ;
		$size		= $size ? ' size="'.$size.'"' : '';
		
		$max_values		= $field->parameters->get( 'max_values', 0 ) ;
		$min_values		= $field->parameters->get( 'min_values', 0 ) ;
		$exact_values	= $field->parameters->get( 'exact_values', 0 ) ;
		if ($required && !$min_values) $min_values = 1;
		if ($exact_values) $max_values = $min_values = $exact_values;
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
		if (!$field->value && $default_values!=='') {
			$field->value = explode(",", $default_values);
		} else if (!$field->value) {
			$field->value = array();
			$field->value[0] = '';
		}
		
		$fieldname = FLEXI_J16GE ? 'custom['.$field->name.'][]' : $field->name.'[]';
		$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
		
		// Get indexed element values
		$elements = FlexicontentFields::indexedField_getElements($field, $item, $extra_props=array('image'));
		if ( !$elements ) {
			if ($sql_mode)
				$field->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			else
				$field->html = JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
			return;
		}
		
		$class = '';//$required;
		$attribs = '';
		if ($exact_values)  {
			$attribs .= ' exact_values="'.$exact_values.'" ';
		} else {
			if ($max_values)    $attribs .= ' max_values="'.$max_values.'" ';
			if ($min_values)    $attribs .= ' min_values="'.$min_values.'" ';
		}
		if ($js_popup_err)  $attribs .= ' js_popup_err="'.$js_popup_err.'" ';
		if ($max_values || $min_values || $exact_values)  $class .= ' validate-cboxlimitations ';
		if ($class)  $attribs .= ' class="'.$class.'" ';
		
		// Create field's HTML display for item form
		// (a) Display as drop-down select ...
		if ( $field->parameters->get( 'display_as_select', 0 ) ) {
			$options = array();
			if ($usefirstoption) $options[] = JHTML::_('select.option', '', JText::_($firstoptiontext));
			foreach ($elements as $element) {
				$options[] = JHTML::_('select.option', $element->value, JText::_($element->text));
			}
			$field->html	= JHTML::_('select.genericlist', $options, $fieldname, 'multiple="multiple" class="'.$required.'"'.$size, 'value', 'text', $field->value, $elementid);
			return;
		} // else ...
		
		// (b) Display as checkbox images
		$i = 0;
		$options = "";
		foreach ($elements as $element) {
			$checked  = in_array($element->value, $field->value)  ?  ' checked="checked"'  :  '';
			$img = '<img src="'.$imgpath . $element->image .'"  alt="'.JText::_($element->text).'" />';
			$options .= '<label class="hasTip" title="'.$field->label.'::'.JText::_($element->text).'"><input type="checkbox" id="'.$elementid.'_'.$i.'" name="'.$fieldname.'" '.$attribs.' value="'.$element->value.'" '.$checked.' />'.$img.'</label>'.$separator;
			$i++;
		}
		$field->html = $options;
		
		// Add message box about allowed # values
		if ($exact_values) {
			$field->html = '<div class="fc_mini_note_box">'.JText::sprintf('FLEXI_FIELD_NUM_VALUES_EXACTLY', $exact_values) .'</div><div class="clear"></div>'. $field->html;
		} else if ($max_values || $min_values > 1) {
			$field->html = '<div class="fc_mini_note_box">'.JText::sprintf('FLEXI_FIELD_NUM_VALUES_BETWEEN', $min_values, $max_values) .'</div><div class="clear"></div>'. $field->html;
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'checkboximage') return;

		$field->label = JText::_($field->label);
		
		$app =& JFactory::getApplication();
		$prefix = $app->isAdmin() ? '../':'';
		
		$values = $values ? $values : $field->value;

		// some parameter shortcuts
		$remove_space	= $field->parameters->get( 'remove_space', 0 ) ;
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$sql_mode			= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements = $field->parameters->get( 'field_elements', '' ) ;
		
		$imagedir 		= preg_replace('#^(/)*#', '', $field->parameters->get( 'imagedir' ) );
		$imgpath			= $prefix . $imagedir;
		$separatorf		= $field->parameters->get( 'separatorf' ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
		
		if($pretext) 	{ $pretext 	= $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext	= $remove_space ? $posttext : ' ' . $posttext; }

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
		if ( !$elements ) {
			if ($sql_mode)
				$field->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			else
				$field->html = JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
			return;
		}
		
		// Create display of field
		$display = array();
		$display_index = array();
		for($n=0, $c=count($values); $n<$c; $n++) {
			$element = @$elements[ $values[$n] ];
			if ( $element ) {
				$display[] = $pretext . '<img src="'.$imgpath . $element->image .'" class="hasTip" title="'.$field->label.'::'.$element->text.'" alt="'.JText::_($element->text).'" />' . $posttext;
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
		if($field->field_type != 'checkboximage') return;
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
		if($filter->field_type != 'checkboximage') return;
		
		plgFlexicontent_fieldsCheckboximage::onDisplayFilter($filter, $value, $formName);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'checkboximage') return;

		// some parameter shortcuts
		$sql_mode				= $filter->parameters->get( 'sql_mode', 0 ) ;
		$label_filter 	= $filter->parameters->get( 'display_label_filter', 0 ) ;
		if ($label_filter == 2) $text_select = $filter->label; else $text_select = JText::_('FLEXI_ALL');
		$filter->html = '';
		
		// Get indexed element values
		$elements = FlexicontentFields::indexedField_getElements($filter, $item=null, $extra_props=array('image'), $item_pros=false);
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
		if ($field->field_type != 'checkboximage') return;
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
		if ($field->field_type != 'checkboximage') return;
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
		if ($field->field_type != 'checkboximage') return;
		
		FlexicontentFields::onFLEXIAdvSearch($field);
	}
	
}
