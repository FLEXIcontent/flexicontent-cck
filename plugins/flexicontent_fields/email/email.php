<?php
/**
 * @version 1.0 $Id: email.php 1883 2014-04-09 17:49:21Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.email
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

class plgFlexicontent_fieldsEmail extends JPlugin
{
	static $field_types = array('email');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsEmail( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_email', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		
		
		// *************
		// Email address
		// *************
		
		// Default value
		$addr_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_addr = ($item->version == 0 || $addr_usage > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_addr = $default_addr ? JText::_($default_addr) : '';
		
		// Input field display size & max characters
		$size       = (int) $field->parameters->get( 'size', 30 ) ;
		$maxlength  = (int) $field->parameters->get( 'maxlength', 0 ) ;   // client/server side enforced
		$inputmask	= $field->parameters->get( 'inputmask', 'email' ) ;
		
		// create extra HTML TAG parameters for the form field
		$attribs = $field->parameters->get( 'extra_attributes', '' ) ;
		if ($maxlength) $attribs .= ' maxlength="'.$maxlength.'" ';
		$attribs .= ' size="'.$size.'" ';
		$classes = $required;
		
		static $inputmask_added = false;
	  if ($inputmask && !$inputmask_added) {
			$inputmask_added = true;
			flexicontent_html::loadFramework('inputmask');
		}
		if ($inputmask) {
			$attribs .= " data-inputmask=\" 'alias': 'email' \" ";
			$classes .= ' has_inputmask';
		}
		$classes .= ' validate-email';
		
		
		// *************************************
		// Email title & linking text (optional)
		// *************************************
		
		// Default value
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0]['addr'] = $default_addr;
			$field->value[0]['text'] = $default_title;
			$field->value[0] = serialize($field->value[0]);
		}
		
		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		
		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;
		
		$js = "";
		$css = "";
		
		if ($multiple) // handle multiple records
		{
			// Add the drag and drop sorting feature
			if (!$use_ingroup) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
			});
			";
			
			if ($max_values) JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
			
			function addField".$field->id."(el, groupval_box, fieldval_box, params)
			{
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;
				
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				";
			
			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			// Update the new email address
			$js .= "
				var theInput = newField.find('input.emailaddr').first();
				theInput.val('".$default_addr."');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][addr]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_addr');
				newField.find('.emailaddr-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_addr');
				";
				
			// Update the new email linking text
			if ($usetitle) $js .= "
				var theInput = newField.find('input.emailtext').first();
				theInput.val('".$default_title."');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][text]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_text');
				newField.find('.emailtext-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_text');
				
				// Update inputmask
				var has_inputmask = newField.find('input.has_inputmask').length != 0;
				if (has_inputmask)  newField.find('input.has_inputmask').inputmask();
				";
			
			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();
				";
			
			// Add new element to sortable objects (if field not in group)
			if (!$use_ingroup) $js .= "
				//jQuery('#sortables_".$field->id."').sortable('refresh');  // Refresh was done appendTo ?
				";
			
			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800);
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');
				
				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 1)
					addField".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});
				
				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if(rowCount".$field->id." > 1) {
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ this.remove(); });
					rowCount".$field->id."--;
				}
			}
			";
			
			$css .= '';
			
			$remove_button = '<span class="fcfield-delvalue" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="fcfield-drag-handle" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="fcfield-insertvalue fc_before" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="fcfield-insertvalue fc_after"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		} else {
			$remove_button = '';
			$move2 = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		
		// *****************************************
		// Create field's HTML display for item form
		// *****************************************
		
		$field->html = array();
		$n = 0;
		//if ($use_ingroup) {print_r($field->value);}
		foreach ($field->value as $value)
		{
			// Compatibility for unserialized values (e.g. reload user input after form validation error) or for NULL values in a field group
			if ( !is_array($value) )
			{
				$v = !empty($value) ? @unserialize($value) : false;
				$value = ( $v !== false || $v === 'b:0;' ) ? $v :
					array('addr' => $value, 'text' => '');
			}
			if ( empty($value['addr']) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group
			
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			
			$value['addr'] = !empty($value['addr']) ? $value['addr'] : '';
			$value['addr'] = htmlspecialchars(
				(FLEXI_J30GE ? JStringPunycode::emailToUTF8($value['addr']) : $value['addr']),
				ENT_COMPAT, 'UTF-8'
			);
			$addr = '
				<div class="nowrap_box">
					<label class="label emailaddr-lbl" for="'.$elementid_n.'_addr">'.JText::_( 'FLEXI_FIELD_EMAILADDRESS' ).'</label>
					<input class="emailaddr fcfield_textval '.$classes.'" name="'.$fieldname_n.'[addr]" id="'.$elementid_n.'_addr" type="text" value="'.$value['addr'].'" '.$attribs.' />
				</div>';
			
			$text = '';
			if ($usetitle) {
				$value['text'] = !empty($value['text']) ? $value['text'] : $default_title;
				$value['text'] = isset($value['text']) ? htmlspecialchars($value['text'], ENT_COMPAT, 'UTF-8') : '';
				$text = '
				<div class="nowrap_box">
					<label class="label emailtext-lbl" for="'.$elementid_n.'_text">'.JText::_( 'FLEXI_FIELD_EMAILTITLE' ).'</label>
					<input class="emailtext fcfield_textval" name="'.$fieldname_n.'[text]"  id="'.$elementid_n.'_text" type="text" size="'.$size.'" value="'.$value['text'].'" />
				</div>';
			}
			
			$field->html[] = '
				'.$addr.'
				'.$text.'
				'.($use_ingroup ? '' : $move2).'
				'.($use_ingroup ? '' : $remove_button).'
				'.($use_ingroup || !$add_position ? '' : $add_here).'
				';
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html =
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$view = JRequest::getVar('flexi_callview', JRequest::getVar('view', FLEXI_ITEMVIEW));
		$format = JRequest::getCmd('format', null);
		
		// Value handling parameters
		$lang_filter_values = 0;//$field->parameters->get( 'lang_filter_values', 1);
		
		// Email address
		$addr_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_addr = ($addr_usage == 2) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_addr = $default_addr ? JText::_($default_addr) : '';
		
		// Email title & linking text (optional)
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';
		
		// Get field values
		$values = $values ? $values : $field->value;
		
		// Load default value
		if ( empty($values) ) {
			if (!strlen($default_addr)) {
				$field->{$prop} = $is_ingroup ? array() : '';
				return;
			}
			$values = array();
			$values[0]['addr'] = JText::_($default_addr);
			$values[0]['text'] = JText::_($default_title);
			$values[0] = serialize($values[0]);
		}
		
		// (* BECAUSE OF THIS, the value display loop expects unserialized values)
		foreach ($values as &$value)
		{
			// Compatibility for unserialized values or for NULL values in a field group
			if ( !is_array($value) )
			{
				$v = !empty($value) ? @unserialize($value) : false;
				$value = ( $v !== false || $v === 'b:0;' ) ? $v :
					array('addr' => $value, 'text' => '');
			}
		}
		unset($value); // Unset this or you are looking for trouble !!!, because it is a reference and reusing it will overwrite the pointed variable !!!
		
		
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
			if ( empty($value['addr']) && !$is_ingroup ) continue; // Skip empty if not in field group
			if ( empty($value['addr']) ) {
				$field->{$prop}[$n++]	= '';
				continue;
			}
			
			// If not using property or property is empty, then use default property value
			// NOTE: default property values have been cleared, if (propertyname_usage != 2)
			$addr = $value['addr'];
			$text = @$value['text'];
			$text = ($usetitle && strlen($text))  ?  $text  :  $default_title;
			
			if ( !strlen($text) || !$usetitle ) {
				$text = FLEXI_J30GE ? JStringPunycode::emailToUTF8($addr) : $addr;  // email in Punycode to UTF8, for the purpose of displaying it
				$text_is_email = 1;
			} else {
				$text_is_email = strpos($text,'@') !== false;
			}
			
			// Create field's display
			// A cloacked email address with custom linking text
			$html = $format != 'feed' ?
				JHTML::_('email.cloak', $addr, $addr!=$text, $text, $text_is_email) :
				'<a href="mailto:'.$addr.'" target="_blank" itemprop="email">' .$text. '</a>';
			
			// Add prefix / suffix
			$field->{$prop}[$n]	= $pretext . $html . $posttext;
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if (!$is_ingroup)  // do not convert the array to string if field is in a group
		{
			// Apply separator and open/close tags
			$field->{$prop} = implode($separatorf, $field->{$prop});
			if ( $field->{$prop}!=='' ) {
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;
			} else {
				$field->{$prop} = '';
			}
		}
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;
		
		$is_importcsv = JRequest::getVar('task') == 'importcsv';
		
		// Server side validation
		//$validation = $field->parameters->get( 'validation', 'EMAIL' ) ;
		$maxlength  = (int) $field->parameters->get( 'maxlength', 0 ) ;
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// support for basic CSV import / export
			if ( $is_importcsv && !is_array($post[$n]) ) {
				if ( @unserialize($post[$n])!== false || $post[$n] === 'b:0;' ) {  // support for exported serialized data)
					$post[$n] = unserialize($post[$n]);
				} else {
					$post[$n] = array('addr' => $post[$n], 'text' => '');
				}
			}
			
			
			// **************************************************************
			// Validate data, skipping values that are empty after validation
			// **************************************************************
			
			$addr = flexicontent_html::dataFilter($post[$n]['addr'], $maxlength, 'EMAIL', 0);  // Clean bad text/html
			if (!strlen($addr) && !$use_ingroup) continue; // Skip empty values
			
			$newpost[$new] = array();
			$newpost[$new]['addr'] = $addr;
			$newpost[$new]['text'] = flexicontent_html::dataFilter(@$post[$n]['text'], 0, 'STRING', 0);
			
			$new++;
		}
		$post = $newpost;
		
		// Serialize multi-property data before storing them into the DB
		foreach($post as $i => $v) {
			$post[$i] = serialize($v);
		}
		/*if ($use_ingroup) {
			$app = JFactory::getApplication();
			$app->enqueueMessage( print_r($post, true), 'warning');
		}*/
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
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
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
		
		// a. Each of the values of $values array will be added to the advanced search index as searchable text (column value)
		// b. Each of the indexes of $values will be added to the column 'value_id',
		//    and it is meant for fields that we want to be filterable via a drop-down select
		// c. If $values is null then only the column 'value' will be added to the search index after retrieving 
		//    the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('addr'), $search_properties=array('addr','text'), $properties_spacer=' ', $filter_func=null);
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
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('addr'), $search_properties=array('addr','text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
}
