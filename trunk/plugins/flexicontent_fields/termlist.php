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

//jimport('joomla.plugin.plugin');
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
		
		// initialize framework objects and other variables
		$document  = JFactory::getDocument();
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		
		
		// Create the editor object of editor prefered by the user,
		// this will also add the needed JS to the HTML head
		$editor_name = $user->getParam('editor', $app->getCfg('editor'));
		$editor = JFactory::getEditor($editor_name);
		
		
		// Some field parameters for the textarea
		$show_buttons = false;//(boolean) $field->parameters->get( 'show_buttons', 1 ) ;
		
		// some parameter shortcuts
		$size      = $field->parameters->get( 'size', 30 ) ;
		$cols      = $field->parameters->get( 'cols', 75 ) ;
		$rows      = $field->parameters->get( 'rows', 20 ) ;
		$multiple  = $field->parameters->get( 'allow_multiple', 1 ) ;
		$max_values= (int)$field->parameters->get( 'max_values', 0 ) ;
		
		// This is field 's MAIN value property
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($item->version == 0 || $value_usage > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		
		// Optional value properties
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0]['title'] = JText::_($default_title);
			$field->value[0]['text']  = JText::_($default_value);
			$field->value[0] = serialize($field->value[0]);
		}
		
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
			
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']' : $field->name;
			$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
			
			if ($max_values) FLEXI_J16GE ? JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true) : fcjsJText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id."		= ".$max_values.";

			function addField".$field->id."(el) {
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}

				var thisField 	 = $(el).getPrevious().getLast();
				var thisNewField = thisField.clone();
				
				jQuery(thisNewField.getElements('label.labeltitle')).text('".JText::_( 'FLEXI_FIELD_TERMTITLE' )." '+parseInt(rowCount".$field->id."+1)+':');
				jQuery(thisNewField.getElements('label.labeltext')).text('".JText::_( 'FLEXI_FIELD_TERMTEXT' )." '+parseInt(rowCount".$field->id."+1)+':');
				jQuery(thisNewField).find('label.labeltitle').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_title');
				jQuery(thisNewField).find('label.labeltext').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_text');
				";
				
			if ($usetitle) $js .= "
				jQuery(thisNewField.getElements('textarea.termtitle')).val('');
				thisNewField.getElements('textarea.termtitle').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][title]');
				thisNewField.getElements('textarea.termtitle').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_title');
				";
				
			$js .= "
				var container = jQuery(thisNewField).find('.fctextbox');
				container.after('<div class=\"fctextbox\"></div>');
				container.find('textarea').show().appendTo(container.next());
				container.remove();
				jQuery(thisNewField).find('.fctextbox').find('textarea').val('');
				jQuery(thisNewField).find('.fctextbox').find('textarea').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][text]');
				jQuery(thisNewField).find('.fctextbox').find('textarea').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_text');
				jQuery(thisNewField).find('.fctextbox').find('textarea').removeClass();
				jQuery(thisNewField).find('.fctextbox').find('textarea').addClass('termtext');
				";
				
			$js .= "
				jQuery(thisNewField).css('display', 'none');
				jQuery(thisNewField).insertAfter( jQuery(thisField) );
				
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag',
					containment: 'parent',
					tolerance: 'pointer'
				});
				
				//jQuery(thisNewField).show('slideDown');
				jQuery(thisNewField).show();
				tinyMCE.execCommand('mceAddControl', false, '".$elementid."_'+uniqueRowNum".$field->id."+'_text');
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el)
			{
				if(rowCount".$field->id." <= 1) return;
				var row = jQuery(el).closest('li');
				jQuery(row).hide('slideUp', function() { jQuery(this).remove(); } );
				rowCount".$field->id."--;
			}
			";
			
			$css = '
			#sortables_'.$field->id.' { float:left; margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				clear: both !important;
				display: block;
				list-style: none !important;
				height: auto !important;
				position: relative !important;
				background:#EAEAEA !important;
				border-radius:5px !important;
				margin-bottom:10px !important;
				padding:5px !important;
				border:1px solid #ccc !important;
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
			$move2 	= '<span class="fcfield-drag">'.JHTML::image ( JURI::base().'components/com_flexicontent/assets/images/move2.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) ) .'</span>';
		} else {
			$remove_button = '';
			$move2 = '';
			$js = '';
			$css = '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		$field->html = array();
		$n = 0;
		foreach ($field->value as $value) {
			if ( @unserialize($value)!== false || $value === 'b:0;' ) {
				$value = unserialize($value);
			} else {
				$value = array('title' => $value, 'text' => '');
			}
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']['.$n.']' : $field->name.'['.$n.']';
			$elementid = FLEXI_J16GE ? 'custom_'.$field->name.'_'.$n : $field->name.'_'.$n;
			
			if ($usetitle) $title = '
				<br/><label class="label labeltitle" for="'.$elementid.'_title">'.JText::_( 'FLEXI_FIELD_TERMTITLE' ).' '.($multiple?($n+1):'').':</label><br/>
				<textarea class="fcfield_textval termtitle'.$required.'" id="'.$elementid.'_title" name="'.$fieldname.'[title]" cols="'.$cols.'" rows="'.$rows.'">'.$value['title'].'</textarea><br/><br/>
			';
			
			/*if ($usetitle) $text = '
				<label class="label labeltext" for="'.$fieldname.'[text]">'.JText::_( 'FLEXI_FIELD_TERMTEXT' ).' '.($multiple?($n+1):'').':</label>
				<input class="fcfield_textval termtext" name="'.$fieldname.'[text]" type="text" size="'.$size.'" value="'.@$value['text'].'" />
			';*/
			$text = '
				<label class="label labeltext" for="'.$elementid.'_text">'.JText::_( 'FLEXI_FIELD_TERMTEXT' ).' '.($multiple?($n+1):'').':</label>
				'.
				//<textarea class="fcfield_textval termtext" name="'.$fieldname.'[text]" cols="'.$cols.'" rows="'.$rows.'">'.@$value['text'].'</textarea>
				'<div class="fctextbox">'.$editor->display($fieldname.'[text]', $value['text'], $width='100%', $height='100%', $cols, $rows, $show_buttons, $elementid.'_text') . '</div>
			';
			
			
			$field->html[] = '
				'.@$title.'
				'.$text.'
				<div class="clear"></div>
				'.$move2.'
				'.$remove_button.'
				<div class="clear"></div>
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
		
		// some parameter shortcuts
		
		// This is field 's MAIN value property
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($value_usage == 2) ? $field->parameters->get( 'default_value', '' ) : '';
		
		// Optional value properties
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2)  ?  JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		
		// Get field values
		$values = $values ? $values : $field->value;
		
		// Handle default value loading, instead of empty value
		if ( empty($values) && !strlen($default_value) ) {
			$field->{$prop} = '';
			return;
		} else if ( empty($values) && strlen($default_value) ) {
			$values = array();
			$values[0]['title'] = JText::_($default_title);
			$values[0]['text'] = JText::_($default_value);
			$values[0] = serialize($values[0]);
		}
		
		
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
			
			// Compatibility for old unserialized values
			$value = (@unserialize($value)!== false || $value === 'b:0;') ? unserialize($value) : $value;
			if ( is_array($value) ) {
				$title = $value['title'];
				$text = $value['text'];
			} else {
				$title = $value;
				$text = '';
			}
			
			// If not using property or property is empty, then use default property value
			// NOTE: default property values have been cleared, if (propertyname_usage != 2)
			$text = ($usetitle && strlen($title))  ?  $title  :  $default_title;
			
			$title = '<label class="label">'.JText::_( 'FLEXI_FIELD_TERMTITLE' ).' '.($n+1).':</label>'.$title;
			$text = '<div class="fcclear"></div><label class="label">'.JText::_('FLEXI_FIELD_TERMTEXT').' '.($n+1).':</label>'.$text;

			// Add prefix / suffix
			$field->{$prop}[]	= $pretext. $title . $text . $posttext;
			
			$n++;
		}

		// Apply seperator and open/close tags
		if(count($field->{$prop})) {
			$field->{$prop} = implode($separatorf, $field->{$prop});
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
		if ( !is_array($post) && !strlen($post) ) return;
		
		$is_importcsv = JRequest::getVar('task') == 'importcsv';
		
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
			
			if ($post[$n]['title'] !== '')
			{
				$newpost[$new] = $post[$n];
				$newpost[$new]['title'] = $post[$n]['title'];
				$newpost[$new]['text'] = strip_tags(@$post[$n]['text']);
				$new++;
			}
		}
		$post = $newpost;
		
		// Serialize multi-property data before storing them into the DB
		foreach($post as $i => $v) {
			$post[$i] = serialize($v);
		}
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
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('text'), $search_properties=array('title','text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('text'), $search_properties=array('title','text'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
}
