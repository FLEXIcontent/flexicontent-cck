<?php
/**
 * @version 1.0 $Id: text.php 1862 2014-03-07 03:29:42Z ggppdk $
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

jimport('joomla.event.plugin');

class plgFlexicontent_fieldsText extends JPlugin
{
	static $field_types = array('text', 'textselect');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsText( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_text', JPATH_ADMINISTRATOR);
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
		
		// initialize framework objects and other variables
		$document  = JFactory::getDocument();
		
		// some parameter shortcuts
		$default_value_use = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value     = ($item->version == 0 || $default_value_use > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		$maxlength	= (int)$field->parameters->get( 'maxlength', 0 ) ;
		$size       = (int) $field->parameters->get( 'size', 30 ) ;
		$multiple   = $field->parameters->get( 'allow_multiple', 1 ) ;
		$max_values = (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		
	  // add setMask function on the document.ready event
		$inputmask	= $field->parameters->get( 'inputmask', false ) ;
		$custommask = $field->parameters->get( 'custommask', false ) ;
		static $inputmask_added = false;
	  if ($inputmask && !$inputmask_added) {
			$inputmask_added = true;
			flexicontent_html::loadFramework('inputmask');
		}
		
		// create extra HTML TAG parameters for the text form field
		$attribs = $field->parameters->get( 'extra_attributes', '' ) ;
		if ($maxlength) $attribs .= ' maxlength="'.$maxlength.'" ';
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0] = JText::_($default_value);
		} else {
			for ($n=0; $n<count($field->value); $n++) {
				$field->value[$n] = htmlspecialchars( $field->value[$n], ENT_QUOTES, 'UTF-8' );
			}
		}
		
		// Field name and HTML TAG id
		$fieldname = FLEXI_J16GE ? 'custom['.$field->name.'][]' : $field->name.'[]';
		$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
		
		$js = "";
		
		if ($multiple) // handle multiple records
		{
			if (!FLEXI_J16GE) $document->addScript( JURI::root(true).'/components/com_flexicontent/assets/js/sortables.js' );
			
			// Add the drag and drop sorting feature
			$js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag',
					containment: 'parent',
					tolerance: 'pointer'
				});
			});
			";
			
			if ($max_values) FLEXI_J16GE ? JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true) : fcjsJText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";

			function addField".$field->id."(el) {
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				var thisField 	 = jQuery(el).prev().children().last();
				var thisNewField = thisField.clone();
				
				thisNewField.getFirst().setProperty('value','');  /* First element is the value input field, second is e.g remove button */

				var has_inputmask = jQuery(thisNewField).find('input.has_inputmask').length != 0;
				if (has_inputmask)  jQuery(thisNewField).find('input.has_inputmask').inputmask();
				
				var has_select2 = jQuery(thisNewField).find('div.select2-container').length != 0;
				if (has_select2) {
					jQuery(thisNewField).find('div.select2-container').remove();
					jQuery(thisNewField).find('select.use_select2_lib').select2();
				}
				
				jQuery(thisNewField).css('display', 'none');
				jQuery(thisNewField).insertAfter( jQuery(thisField) );

				var input = jQuery(thisNewField).find('input').first();
				input.attr('id', '".$elementid."_'+uniqueRowNum".$field->id.");
				";
			
			if ($field->field_type=='textselect') $js .= "
				thisNewField.getParent().getElement('select.fcfield_textselval').setProperty('value','');
				";
			
			$js .= "
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag',
					containment: 'parent',
					tolerance: 'pointer'
				});
				
				jQuery(thisNewField).show('slideDown');
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el)
			{
				if(rowCount".$field->id." <= 1) return;
				var row = jQuery(el).closest('li');
				jQuery(row).hide('slideUp', function() { this.remove(); } );
				rowCount".$field->id."--;
			}
			";
			
			$css = '
			#sortables_'.$field->id.' { float:left; margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				clear: both;
				display: block;
				list-style: none;
				height: auto;
				position: relative;
			}
			#sortables_'.$field->id.' li.sortabledisabled {
				background : transparent url(components/com_flexicontent/assets/images/move3.png) no-repeat 0px 1px;
			}
			#sortables_'.$field->id.' li input { cursor: text;}
			#add'.$field->name.' { margin-top: 5px; clear: both; display:block; }
			#sortables_'.$field->id.' li .admintable { text-align: left; }
			#sortables_'.$field->id.' li:only-child span.fcfield-drag, #sortables_'.$field->id.' li:only-child input.fcfield-button { display:none; }
			';
			
			$remove_button = '<input class="fcfield-button" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" />';
			$move2 	= '<span class="fcfield-drag">'.JHTML::image( JURI::base().'components/com_flexicontent/assets/images/move2.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) ) .'</span>';
		} else {
			$remove_button = '';
			$move2 = '';
			$js = '';
			$css = '';
		}
		
		// Drop-Down select for textselect field type
		if ($field->field_type=='textselect') {
			static $select2_added = false;
		  if ( !$select2_added )
		  {
				$select2_added = true;
				flexicontent_html::loadFramework('select2');
			}
		
			$sel_classes  = ' fcfield_textselval use_select2_lib ';
		  $sel_onchange = "this.getParent().getElement('input.fcfield_textval').setProperty('value', this.getProperty('value')); this.setProperty('value', ''); ";
			$sel_attribs  = ' class="'.$sel_classes.'" onchange="'.$sel_onchange.'"';
			
			$fieldname_sel = FLEXI_J16GE ? 'custom['.$field->name.'_sel][]' : $field->name.'_sel[]';
			$sel_ops = plgFlexicontent_fieldsText::buildSelectOptions($field, $item);
			$select_field = JHTML::_('select.genericlist', $sel_ops, $fieldname_sel, $sel_attribs, 'value', 'text', array());
		} else {
			$select_field='';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		if ($custommask && $inputmask=="__custom__") {
			$validate_mask = " data-inputmask=\" ".$custommask." \" ";
		} else {
			$validate_mask = $inputmask ? " data-inputmask=\" 'alias': '".$inputmask."' \" " : "";
		}
		
		$classes = 'fcfield_textval inputbox'.$required.($inputmask ? ' has_inputmask' : '');
		
		$field->html = array();
		$n = 0;
		foreach ($field->value as $value)
		{
			$elementid_n = $elementid.'_'.$n;
			
			$text_field = '<input '. $validate_mask .' id="'.$elementid_n.'" name="'.$fieldname.'" class="'.$classes.'" type="text" size="'.$size.'" value="'.$value.'" '.$attribs.' />';
			
			$field->html[] = '
				'.$text_field.'
				'.$select_field.'
				'.$move2.'
				'.$remove_button.'
				';
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($multiple) { // handle multiple records
			$_list = "<li>". implode("</li>\n<li>", $field->html) ."</li>\n";
			$field->html = '
				<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$_list. '</ul>
				<input type="button" class="fcfield-addvalue" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_VALUE' ).'" />
			';
		} else {  // handle single values
			$field->html = $field->html[0];
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
		// DO NOT terminate yet if value is empty since a default value on empty may have been defined
		
		// Handle default value loading, instead of empty value
		$default_value_use= $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value		= ($default_value_use == 2) ? $field->parameters->get( 'default_value', '' ) : '';
		if ( empty($values) && !strlen($default_value) ) {
			$field->{$prop} = '';
			return;
		} else if ( empty($values) && strlen($default_value) ) {
			$values = array($default_value);
		}
		
		// Value handling parameters
		$multiple       = $field->parameters->get( 'allow_multiple', 1 ) ;
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
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
		
		// initialise property
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			if ( !strlen($value) ) continue;
			
			$field->{$prop}[$n]	= $pretext.$value.$posttext;
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		// Apply separator and open/close tags
		$field->{$prop} = implode($separatorf, $field->{$prop});
		if ( $field->{$prop}!=='' ) {
			$field->{$prop} = $opentag . $field->{$prop} . $closetag;
		} else {
			$field->{$prop} = '';
		}
		
		// Add OGP Data
		$useogp     = $field->parameters->get('useogp', 0);
		$ogpinview  = $field->parameters->get('ogpinview', array());
		$ogpinview  = FLEXIUtilities::paramToArray($ogpinview);
		$ogpmaxlen  = $field->parameters->get('ogpmaxlen', 300);
		$ogpusage   = $field->parameters->get('ogpusage', 0);
		
		if ($useogp && $field->{$prop}) {
			$view = JRequest::setVar('view', JRequest::getVar('view', FLEXI_ITEMVIEW));
			if ( in_array($view, $ogpinview) ) {
				switch ($ogpusage)
				{
					case 1: $usagetype = 'title'; break;
					case 2: $usagetype = 'description'; break;
					default: $usagetype = ''; break;
				}
				if ($usagetype) {
					$content_val = flexicontent_html::striptagsandcut($field->{$prop}, $ogpmaxlen);
					JFactory::getDocument()->addCustomTag('<meta property="og:'.$usagetype.'" content="'.$content_val.'" />');
				}
			}
		}
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
		foreach ($post as $n => $v)
		{
			if ($post[$n] !== '')
			{
				$newpost[$new] = $post[$n];
				$new++;
			}
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
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		self::onDisplayFilter($filter, $value, $formName);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value)
	{
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
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		// a. Each of the values of $values array will be added to the advanced search index as searchable text (column value)
		// b. Each of the indexes of $values will be added to the column 'value_id',
		//    and it is meant for fields that we want to be filterable via a drop-down select
		// c. If $values is null then only the column 'value' will be added to the search index after retrieving 
		//    the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   containts property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   containts property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	// Method to build the options of drop-down select for field
	function buildSelectOptions(&$field, &$item)
	{
		// Drop-down select elements depend on 'select_field_mode'
		$select_field_mode = $field->parameters->get('select_field_mode', 0);
		if ( $select_field_mode == 0 ) {
			// All existing values
			$field_elements = ' SELECT DISTINCT value, value as text '
				.' FROM #__flexicontent_fields_item_relations '
				.' WHERE field_id={field->id} AND value != "" GROUP BY value';
		} else {
			// Predefined elements or Elements via an SQL query
			$field_elements = $field->parameters->get('select_field_elements');
		}
		
		// Call function that parses or retrieves element via sql
		$field->parameters->set('sql_mode', $select_field_mode==0 || $select_field_mode==2);
		$field->parameters->set('field_elements', $field_elements);
		$results = FlexicontentFields::indexedField_getElements($field, $item);
		
		$options = array();
		$default_prompt = $select_field_mode==0 ? 'FLEXI_FIELD_SELECT_EXISTING_VALUE' : 'FLEXI_FIELD_SELECT_VALUE';
		$field_prompt = $field->parameters->get('select_field_prompt', $default_prompt);
		$options[] = JHTML::_('select.option', '', '-'.JText::_($field_prompt).'-');
		
		if ($results) foreach($results as $result) {
			if ( !strlen($result->value) ) continue;
			$options[] = JHTML::_('select.option', $result->value, JText::_($result->text));
		}
		return $options;
	}
	
}
