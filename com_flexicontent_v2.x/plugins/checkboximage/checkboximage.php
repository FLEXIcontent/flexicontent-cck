<?php
/**
 * @version 1.0 $Id: checkboximage.php 1629 2013-01-19 08:45:07Z ggppdk $
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
	static $field_types = array('checkboximage');
	static $extra_props = array('image');
	
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
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// some parameter shortcuts
		$sql_mode				= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements	= $field->parameters->get( 'field_elements' ) ;
		$default_values	= $field->parameters->get( 'default_values', '' ) ;
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$pretext			= $field->parameters->get( 'pretext_form', '' ) ;
		$posttext			= $field->parameters->get( 'posttext_form', '' ) ;
		$separator		= $field->parameters->get( 'separator', 0 ) ;
		$opentag			= $field->parameters->get( 'opentag_form', '' ) ;
		$closetag			= $field->parameters->get( 'closetag_form', '' ) ;
		
		$required = $field->parameters->get( 'required', 0 ) ;
		//$required = $required ? ' required validate-checkbox' : '';
		$max_values		= $field->parameters->get( 'max_values', 0 ) ;
		$min_values		= $field->parameters->get( 'min_values', 0 ) ;
		$exact_values	= $field->parameters->get( 'exact_values', 0 ) ;
		if ($required && !$min_values) $min_values = 1;
		if ($exact_values) $max_values = $min_values = $exact_values;
		$js_popup_err	= $field->parameters->get( 'js_popup_err', 0 ) ;
		
		// image specific variables
		$prefix   = JFactory::getApplication()->isAdmin() ? '../':'';
		$imagedir = preg_replace('#^(/)*#', '', $field->parameters->get( 'imagedir' ) );
		$imgpath  = $prefix . $imagedir;
		
		// when field is displayed as drop-down select (item edit form only)
		$firstoptiontext = $field->parameters->get( 'firstoptiontext', 'FLEXI_SELECT' ) ;
		$usefirstoption  = $field->parameters->get( 'usefirstoption', 1 ) ;
		$size  = $field->parameters->get( 'size', 6 ) ;
		$size  = $size ? ' size="'.$size.'"' : '';
		
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

			case 4:
			$separator = $closetag . $opentag;
			break;

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
		$elements = FlexicontentFields::indexedField_getElements($field, $item, self::$extra_props);
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
		// Display as drop-down (multiple) select
		if ( $field->parameters->get( 'display_as_select', 0 ) ) {
			$options = array();
			if ($usefirstoption) $options[] = JHTML::_('select.option', '', JText::_($firstoptiontext));
			foreach ($elements as $element) {
				$options[] = JHTML::_('select.option', $element->value, JText::_($element->text));
			}
			$field->html	= JHTML::_('select.genericlist', $options, $fieldname, 'multiple="multiple" class="'.$required.'"'.$size, 'value', 'text', $field->value, $elementid);
			return;
		} // else ...
		
		// Display as checkboxes with images
		$i = 0;
		$options = array();
		foreach ($elements as $element) {
			$checked  = in_array($element->value, $field->value)  ?  ' checked="checked"'  :  '';
			$img = '<img src="'.$imgpath . $element->image .'"  alt="'.JText::_($element->text).'" />';
			$options[] = '<label class="hasTip" style="white-space:nowrap;" title="'.$field->label.'::'.JText::_($element->text).'"><input type="checkbox" id="'.$elementid.'_'.$i.'" name="'.$fieldname.'" '.$attribs.' value="'.$element->value.'" '.$checked.' />'.$img.'</label>'.' '.$separator.' ';
			$i++;
		}
		
		// Apply values separator
		$field->html = implode($separator, $options);
		
		// Apply field 's opening / closing texts
		if ($field->html)
			$field->html = $opentag . $field->html . $closetag;
		
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
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// Get field values
		$values = $values ? $values : $field->value;
		if ( empty($values) ) { $field->{$prop} = ''; return; }
		
		// Check for no values and not displaying ALL elements
    $display_all = $field->parameters->get( 'display_all', 0 ) ;
		if ( !$values && !$display_all ) { $field->{$prop} = ''; $field->display_index = ''; return; }
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
		// some parameter shortcuts
		$sql_mode			= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements = $field->parameters->get( 'field_elements', '' ) ;
		$text_or_value= $field->parameters->get( 'text_or_value', 2 ) ;
		
		// image specific variables
		$prefix   = JFactory::getApplication()->isAdmin() ? '../':'';
		$imagedir = preg_replace('#^(/)*#', '', $field->parameters->get( 'imagedir' ) );
		$imgpath  = $prefix . $imagedir;
		
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

			case 5:
			$separatorf = '';
			break;

			default:
			$separatorf = '&nbsp;';
			break;
		}
		
		
		// Get indexed element values
		$elements = FlexicontentFields::indexedField_getElements($field, $item, self::$extra_props);
		if ( !$elements ) {
			if ($sql_mode)
				$field->{$prop} = JText::_('FLEXI_FIELD_INVALID_QUERY');
			else
				$field->{$prop} = JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
			return;
		}
		// Check for no elements found
		if ( empty($elements) )  { $field->{$prop} = ''; $field->display_index = ''; return; }
		
		// Create display of field
		$display = array();
		$display_index = array();
		
		// Prepare for looping
		if ( $display_all ) {
			$indexes = array_flip($values);
			
			// non-selected value shortcuts
	    $ns_pretext			= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'ns_pretext', '' ), 'ns_pretext' );
  	  $ns_posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'ns_posttext', '' ), 'ns_posttext' );
  	  $ns_pretext  = $ns_pretext . '<span class="fc_field_unsused_val">';
  	  $ns_posttext = '</span>' . $ns_posttext;
    	$ns_pretext  = $remove_space ? $ns_pretext : $ns_pretext . ' ';
	    $ns_posttext = $remove_space ? $ns_posttext : ' ' . $ns_posttext;
		}
		
		// CASE a. Display ALL elements (selected and NON-selected)
		if ( $display_all ) foreach ($elements as $val => $element)
		{
			if ($text_or_value == 0) $disp = $element->value;
			else if ($text_or_value == 1) $disp =JText::_($element->text);
			else $disp = '<img src="'.$imgpath . $element->image .'" class="hasTip" title="'.$field->label.'::'.$element->text.'" alt="'.JText::_($element->text).'" />';
			
			$is_selected = isset($indexes[$val]);
			
			$display[] = $is_selected ?  $pretext.$disp.$posttext : $ns_pretext.$disp.$ns_posttext;
			if ( $is_selected ) $display_index[] = $element->value;
		}
		
		// CASE b. Display only selected elements
		else foreach ($values as $n => $val)
		{
			$element = @$elements[ $val ];
			if ( !$element ) continue;
			
			if ($text_or_value == 0) $disp = $element->value;
			else if ($text_or_value == 1) $disp =JText::_($element->text);
			else $disp = '<img src="'.$imgpath . $element->image .'" class="hasTip" title="'.$field->label.'::'.$element->text.'" alt="'.JText::_($element->text).'" />';
			
			$display[] = $pretext.$disp.$posttext;
			$display_index[] = $element->value;
		}
		
		// Apply values separator
		$field->{$prop} = implode($separatorf, $display);
		$field->display_index = implode($separatorf, $display_index);
		
		// Apply field 's opening / closing texts
		if ($field->{$prop})
			$field->{$prop} = $opentag . $field->{$prop} . $closetag;
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !is_array($post) && !strlen($post) ) return;
		
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
		// execute the code only if the field type match the plugin type
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		self::onDisplayFilter($filter, $value, $formName, $elements=true);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($filter->field_type, self::$field_types) ) return;

		
		// Get indexed element values
		$item_pros = false;
		$elements = FlexicontentFields::indexedField_getElements($filter, $item=null, self::$extra_props, $item_pros, $create_filter=true);
		
		// Check for error during getting indexed field elements
		if ( !$elements ) {
			$filter->html = '';
			$sql_mode = $filter->parameters->get( 'sql_mode', 0 );  // must retrieve variable here, and not before retrieving elements !
			if ($sql_mode && $item_pros > 0)
				$filter->html = sprintf( JText::_('FLEXI_FIELD_ITEM_SPECIFIC_AS_FILTERABLE'), $filter->label );
			else if ($sql_mode)
				$filter->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			else
				$filter->html = JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
			return;
		}
		
		FlexicontentFields::createFilter($filter, $value, $formName, $elements);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
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
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		$field->isindexed = true;
		$field->extra_props = self::$extra_props;
		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array(), $search_properties=array('text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		$field->isindexed = true;
		$field->extra_props = self::$extra_props;
		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array(), $search_properties=array('text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
}
