<?php
/**
 * @version 1.0 $Id: termlist.php 1862 2014-03-07 03:29:42Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.termlist
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

class plgFlexicontent_fieldsTermlist extends JPlugin
{
	static $field_types = array('termlist');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsTermlist( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_termlist', JPATH_ADMINISTRATOR);
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
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		
		
		// Create the editor object of editor prefered by the user,
		// this will also add the needed JS to the HTML head
		$editor_name = $user->getParam('editor', $app->getCfg('editor'));
		$editor  = JFactory::getEditor($editor_name);
		$editor_plg_params = array();  // Override parameters of the editor plugin, nothing yet
		$ifilter = JFilterInput::getInstance(null, null, 1, 1);
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		
		
		// ******************************
		// Term title (optional property)
		// ******************************
		
		// Label
		$title_label = JText::_($field->parameters->get('title_label', 'FLEXI_FIELD_TERMTITLE'));
		
		// Default value
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$title_size      = $field->parameters->get( 'title_size', 80 ) ;
		
		// Input validation & editing
		$title_maxlength = $field->parameters->get( 'title_size', 0 ) ;
		
		
		// ***********************
		// Term text (description)
		// ***********************
		
		// Label
		$value_label = JText::_($field->parameters->get('value_label', 'FLEXI_FIELD_TERMTEXT'));
		
		// Default value
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($item->version == 0 || $value_usage > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		
		// Input validation & editing
		$maxlength = (int) $field->parameters->get( 'maxlength', 0 ) ;   // client/server side enforced when using textarea, otherwise this will depend on the HTML editor (and only will be client size only)
		$validation= $field->parameters->get( 'validation', $field->field_type == 'maintext' ? 2 : 1 ) ;  // server side enforced
		$use_html  = $field->field_type == 'maintext' ? !$field->parameters->get( 'hide_html', 0 ) : $field->parameters->get( 'use_html', 1 );  // load HTML editor
		
		// *** Simple Textarea configuration  ***
		$cols  = $field->parameters->get( 'cols', 75 ) ;
		$rows  = $field->parameters->get( 'rows', 20 ) ;
		
		// *** HTML Editor configuration  ***
		
		$height = $field->parameters->get( 'height', ($field->field_type == 'textarea') ? '300px' : '400px' ) ;
		if ($height != (int)$height) $height .= 'px';
		
		// Decide editor plugin buttons to SKIP
		$show_buttons = $field->parameters->get( 'show_buttons', 1 ) ;
		$skip_buttons = $field->parameters->get( 'skip_buttons', '' ) ;
		$skip_buttons = is_array($skip_buttons) ? $skip_buttons : explode('|',$skip_buttons);
		
		// Clear empty value
		if (empty($skip_buttons[0]))  unset($skip_buttons[0]);
		
		// Force skipping pagebreak and readmore for CUSTOM textarea fields
		if ($field->field_type == 'textarea') {
			if ( !in_array('pagebreak', $skip_buttons) ) $skip_buttons[] = 'pagebreak';
			if ( !in_array('readmore',  $skip_buttons) )  $skip_buttons[] = 'readmore';
		}
		
		$skip_buttons_arr = ($show_buttons && $editor_name=='jce' && count($skip_buttons)) ? $skip_buttons : (boolean) $show_buttons;   // JCE supports skipping buttons
		
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0]['title'] = JText::_($default_title);
			$field->value[0]['text']  = JText::_($default_value);
			$field->value[0] = serialize($field->value[0]);
		}
		
		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;
		
		$js = "";
		$css = "";
		
		if ($multiple) // handle multiple records
		{
			// Add the drag and drop sorting feature
			if (!$use_ingroup) $js .="
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
			
			function addField".$field->id."(el, groupval_box, fieldval_box, params)
			{
				remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;
				
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				
				//newField.find('label.labeltitle').text('".$title_label." '+parseInt(rowCount".$field->id."+1)+':');
				//newField.find('label.labeltitle').text('".$value_label." '+parseInt(rowCount".$field->id."+1)+':');
				newField.find('label.labeltitle').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_title');
				newField.find('label.labeltext').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_text');
				";
				
			$js .= "
				newField.find('input.termtitle').val('');
				newField.find('input.termtitle').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][title]');
				newField.find('input.termtitle').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_title');
				";
				
			$js .= "
				// Create a new textarea
				var boxClass = 'termtext';
				var container = newField.find('.fc_'+boxClass);
				container.after('<div class=\"fc_'+boxClass+'\"></div>');  // Append a new container box
				container.find('textarea').show().appendTo(container.next()); // Copy only the textarea (first make it visible) into the new container
				container.remove(); // Remove old (cloned) container box along with all the contents
				
				// Prepare the new textarea for attaching the HTML editor
				theArea = newField.find('.fc_'+boxClass).find('textarea');
				theArea.val('');
				theArea.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][text]');
				theArea.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_text');
				theArea.removeClass(); // Remove all classes from the textarea
				theArea.addClass(boxClass);
				";
			
			// Add to new field to DOM
			$js .= "
				newField.insertAfter( lastField );
				if (remove_previous) lastField.remove();
				
				// Attach a new JS HTML editor object
				tinyMCE.execCommand('mceAddControl', false, '".$elementid."_'+uniqueRowNum".$field->id."+'_text');
			";
			
			// Add new element to sortable objects (if field not in group)
			if (!$use_ingroup) $js .="
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag',
					containment: 'parent',
					tolerance: 'pointer'
				});
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
					addField".$field->id."(null, groupval_box, fieldval_box, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});
				
				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if(rowCount".$field->id." > 1) {
					// Destroy the remove button, so that it is not reclicked again, while we do the hide effect (before DOM removal)
					if (el) jQuery(el).remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ this.remove(); });
					rowCount".$field->id."--;
				}
			}
			";
			
			$css .= '
			#sortables_'.$field->id.' { float:left; margin: 0px; padding: 0px; list-style: none; white-space: normal; }
			#sortables_'.$field->id.' li {
				clear: both;
				float: left;
				display: block;
				list-style: none;
				height: auto;
				position: relative;
				background: white!important;
				border-radius:5px !important;
				margin-bottom:10px !important;
				padding:5px !important;
				border:1px dashed #444 !important;
			}
			#sortables_'.$field->id.' li.sortabledisabled {
				background : transparent url(components/com_flexicontent/assets/images/move3.png) no-repeat 0px 1px;
			}
			#sortables_'.$field->id.' li input { cursor: text;}
			#add'.$field->name.' { margin-top: 5px; clear: both; display:block; }
			#sortables_'.$field->id.' li .admintable { text-align: left; }
			#sortables_'.$field->id.' li:only-child span.fcfield-drag, #sortables_'.$field->id.' li:only-child input.fcfield-button { display:none; }
			#sortables_'.$field->id.' label.label, #sortables_'.$field->id.' .termtitle, #sortables_'.$field->id.' .termtext, #sortables_'.$field->id.' input.fcfield-button {
				float: none;
				display: inline-block;
			}
			';
			
			$remove_button = '<input class="fcfield-button" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" />';
			$move2 	= '<span class="fcfield-drag">'.JHTML::image( JURI::base().'components/com_flexicontent/assets/images/move2.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) ) .'</span>';
		} else {
			$remove_button = '';
			$move2 = '';
			$js .= '';
			$css .= '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		$field->html = array();
		$n = 0;
		foreach ($field->value as $value)
		{
			// Compatibility for unserialized values
			if ( @unserialize($value)!== false || $value === 'b:0;' ) {
				$value = unserialize($value);
			} else {
				$value = array('title' => $value, 'text' => '');
			}
			// Validate the value, this should have been done by save task already ... but
			$value['text'] = $validation ?
				($validation==2 ? JComponentHelper::filterText($value['text']) : $ifilter->clean($value['text'], 'string')) :  // SAFE HTML
				flexicontent_html::striptagsandcut($value['text'], $maxlength) ;  // PLAIN TEXT ... OR PLAIN TEXT VIA ... JFilterOutput::cleanText($value['text']) ;
			
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			
			$title = '
				<label class="label label-info labeltitle" for="'.$elementid_n.'_title">'.$title_label./*' '.($multiple?($n+1):'').*/'</label><br/>
				<div class="fc_termtitle">
					<input class="fcfield_textval termtitle" id="'.$elementid_n.'_title" name="'.$fieldname_n.'[title]" type="text" size="'.$title_size.'" maxlength="'.$title_maxlength.'" value="'.htmlspecialchars( @$value['title'], ENT_COMPAT, 'UTF-8' ).'" /><br/>
				</div>
			';
			
			$text = !$use_html ? '
				<textarea class="fcfield_textval termtext" id="'.$elementid_n.'_text" name="'.$fieldname_n.'[text]" cols="'.$cols.'" rows="'.$rows.'">'
					.htmlspecialchars( @$value['text'], ENT_COMPAT, 'UTF-8' ).
				'</textarea>
				' : ''
					.$editor->display($fieldname_n.'[text]', htmlspecialchars( @$value['text'], ENT_COMPAT, 'UTF-8' ), $width='100%', $height='100%', $cols, $rows, $show_buttons, $elementid_n.'_text').
				'';
			
			$text = '
				<label class="label label-info labeltext" for="'.$elementid_n.'_text">'.$value_label.' './*($multiple?($n+1):'').*/'</label>
				<div class="fc_termtext">
					'.$text.'
				</div>
				';
			
			$field->html[] = '
				'.($use_ingroup ? '' : $move2).'
				'.($use_ingroup ? '' : $remove_button).'
				<div class="clear"></div>
				'.$title.'
				'.$text.'
				';
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html =
				'<li class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">'.
					implode('</li><li class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			$field->html .= '<input type="button" class="fcfield-addvalue" style="float:left; clear:both;" onclick="addField'.$field->id.'(this);" value=" -- '.JText::_( 'FLEXI_ADD_VALUE' ).' -- " />';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$ifilter = JFilterInput::getInstance(null, null, 1, 1);
		$maxlength = $field->parameters->get( 'maxlength', 0 ) ;   // client/server side enforced when using textarea, otherwise this will depend on the HTML editor (and only will be client size only)
		$validation= $field->parameters->get( 'validation', $field->field_type == 'maintext' ? 2 : 1 ) ;  // server side enforced
		
		// Term Title
		$title_label = JText::_($field->parameters->get('title_label', 'FLEXI_FIELD_TERMTITLE'));
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		
		// Term (description) Text
		$value_label = JText::_($field->parameters->get('value_label', 'FLEXI_FIELD_TERMTEXT'));
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($value_usage == 2) ? $field->parameters->get( 'default_value', '' ) : '';
		
		// Get field values, do not terminate yet if value is empty, since a default value on empty may have been defined
		$values = $values ? $values : $field->value;
		
		// Load default value
		if ( empty($values) ) {
			if (!strlen($default_value)) {
				$field->{$prop} = '';
				return;
			}
			$values = array();
			$values[0]['title'] = JText::_($default_title);
			$values[0]['text'] = JText::_($default_value);
			$values[0] = serialize($values[0]);
		}
		// Clean output
		else {
			foreach ($values as & $value) {
				if ( empty($value) ) continue;
				
				// Compatibility for unserialized values
				if ( @unserialize($value)!== false || $value === 'b:0;' ) {
					$value = unserialize($value);
				} else {
					$value = array('title' => $value, 'text' => '');
				}
				
				$value['title'] = $ifilter->clean($value['title'], 'string');
				$value['text']  = $ifilter->clean($value['text'], 'string');
			}
		}
		
		// Value handling parameters
		$multiple       = $field->parameters->get( 'allow_multiple', 0 ) ;
		
		// Language filter the values
		//$lang_filter_values = $field->parameters->get( 'lang_filter_values', 1);
		
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
			if ( empty($value) ) continue;
			
			$title = '<label class="fc_termtitle label label-success">'.$value['title'].'</label>';
			$text  = '<div class="fc_termdesc">'.$value['text'].'</div>';
			
			// Add prefix / suffix
			$field->{$prop}[]	= $pretext. $title . $text . $posttext;
			
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
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;
		
		$is_importcsv = JRequest::getVar('task') == 'importcsv';
		$ifilter = JFilterInput::getInstance(null, null, 1, 1);
		$maxlength = $field->parameters->get( 'maxlength', 0 ) ;   // client/server side enforced when using textarea, otherwise this will depend on the HTML editor (and only will be client size only)
		$validation= $field->parameters->get( 'validation', $field->field_type == 'maintext' ? 2 : 1 ) ;  // server side enforced
		
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
					$post[$n] = array('title' => $post[$n], 'text' => '');
				}
			}
			
			if ($post[$n]['title'] !== '' || $use_ingroup)
			{
				$newpost[$new] = $post[$n];
				$newpost[$new]['title'] = $post[$n]['title'];  // NO TAG STRIPPING, this should be encoded when displayed !!!
				$newpost[$new]['text']  = $validation ?
					($validation==2 ? JComponentHelper::filterText($post[$n]['text']) : $ifilter->clean($post[$n]['text'], 'string')) :  // SAFE HTML
					flexicontent_html::striptagsandcut($post[$n]['text'], $maxlength) ;  // PLAIN TEXT ... OR PLAIN TEXT VIA ... JFilterOutput::cleanText($post[$n]) ;
				$new++;
			}
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
	function getFilteredSearch(&$field, $value)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
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
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('title'), $search_properties=array('title','text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('title'), $search_properties=array('title','text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
}
