<?php
/**
 * @version 1.0 $Id: date.php 1904 2014-05-20 12:21:09Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.date
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

class plgFlexicontent_fieldsDate extends JPlugin
{
	static $field_types = array('date');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsDate( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_date', JPATH_ADMINISTRATOR);
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
		
		$date_source = $field->parameters->get('date_source', 0);
		if ( $date_source ) {
			$date_source_str = 'Automatic field (shows this content \'s %s publication date)';
			$date_source_str = sprintf($date_source_str, ($date_source == 1) ? '<b>start</b>' :  '<b>end</b>');
			$_value = ($date_source == 1) ? $item->publish_up : $item->publish_down;
			$field->html =
				 '<div style="float:left">'
				.' <div class="alert fc-small fc-iblock">'.$date_source_str.'</div>'
				. $_value
				.'</div>';
			return;
		}
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$config   = JFactory::getConfig();
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		
		
		// Input field display size & max characters
		$size       = (int) $field->parameters->get( 'size', 30 ) ;
		$disable_keyboardinput = (int) $field->parameters->get('disable_keyboardinput', 0);
		
		
		// *******************************************
		// Find timezone and create user instructions,
		// both according to given configuration
		// *******************************************
		
		$show_usage     = $field->parameters->get( 'show_usage', 0 ) ;
		$date_allowtime = $field->parameters->get( 'date_allowtime', 1 ) ;
		$use_editor_tz  = $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz  = $date_allowtime ? $use_editor_tz : 0;
		
		$timezone = FLEXI_J16GE ? 'UTC' : 0; // Default is not to use TIMEZONE
		$append_str = '';
		if ($date_allowtime)
		{
			$append_str = JText::_('FLEXI_DATE_CAN_ENTER_TIME');
			$append_str .= ($date_allowtime==2) ? '<br/>'.JText::_('FLEXI_DATE_USE_ZERO_TIME_ON_EMPTY') : '';
			
			if ($use_editor_tz == 0)
			{
				// Raw date storing, ignoring timezone. NOTE: this is OLD BEHAVIOUR
				$timezone = FLEXI_J16GE ? 'UTC' : 0;
				$append_str .= '<br/>'.JText::_('FLEXI_DATE_TIMEZONE_USAGE_DISABLED');
			}
			else
			{
				$append_str .= '<br/>'.JText::_('FLEXI_DATE_TIMEZONE_USAGE_ENABLED');
				// Use timezone of editor, unlogged editor will use site's default timezone
				$timezone = $user->getParam('timezone', $config->get('offset'));
				if (FLEXI_J16GE) {
					$tz = new DateTimeZone($timezone);
					$tz_offset = $tz->getOffset(new JDate()) / 3600;
				} else {
					$tz_offset = $timezone;
				}
				$tz_info =  $tz_offset > 0 ? ' UTC +'.$tz_offset : ' UTC '.$tz_offset;

				$append_str .= '<br/>'.JText::_($user->id ? 'FLEXI_DATE_ENTER_HOURS_IN_YOUR_TIMEZONE' : 'FLEXI_DATE_ENTER_HOURS_IN_TIMEZONE').': '.$tz_info;
			}
		}
		$append_str = $append_str ? '<b>'.JText::_('FLEXI_NOTES').'</b>: '.$append_str : '';
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0] = '';
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
			
			if ($max_values) FLEXI_J16GE ? JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true) : fcjsJText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
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
				
				// Update the new text field
				var theInput = newField.find('input.fcfield_textval').first();
				theInput.val('');
				theInput.attr('name', '".$fieldname."['+uniqueRowNum".$field->id."+']');
				theInput.attr('id', '".$elementid."_'+uniqueRowNum".$field->id.");
				
				// Update date picker
				var thePicker = theInput.next();
				thePicker.attr('id', '".$elementid."_' +uniqueRowNum".$field->id." +'_img');
				
				";
			
			// Disable keyboard input if so configured
			if($disable_keyboardinput)
				$js .= "
				theInput.on('keydown keypress keyup', false);
					";
			
			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();
				
				// This needs to be after field is added to DOM (unlike e.g. select2 / inputmask JS scripts)
				Calendar.setup({
					inputField:	theInput.attr('id'),
					ifFormat:		'%Y-%m-%d',
					button:			thePicker.attr('id'),
					align:			'Tl',
					singleClick:	true
				});
				";
			
			// Add new element to sortable objects (if field not in group)
			if (!$use_ingroup) $js .= "
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
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
		$skipped_vals = array();
		//if ($use_ingroup) {print_r($field->value);}
		foreach ($field->value as $value)
		{
			if ( !strlen($value) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group
			
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			
			$calendar = FlexicontentFields::createCalendarField($value, $date_allowtime, $fieldname_n, $elementid_n, $attribs_arr=array('class'=>'fcfield_textval'.$required), $skip_on_invalid=true, $timezone);
			if (!$calendar) {
				$skipped_vals[] = $value;
				if (!$use_ingroup) continue;
				$calendar = FlexicontentFields::createCalendarField('', $date_allowtime, $fieldname_n, $elementid_n, $attribs_arr=array('class'=>'fcfield_textval'.$required), $skip_on_invalid=true, $timezone);
			}
			
			$field->html[] = '
				'.$calendar.'
				'.($use_ingroup ? '' : $move2).'
				'.($use_ingroup ? '' : $remove_button).'
				'.($use_ingroup || !$add_position ? '' : $add_here).'
				';
			
			if($disable_keyboardinput) {
				$document->addScriptDeclaration("
					jQuery(document).ready(function(){
						jQuery('#".$elementid_n."').on('keydown keypress keyup', false);
					});
				");
			}
			
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
		
		if (!$use_ingroup) $field->html =
			 '<div style="float:left">'
			.(($show_usage && $append_str) ? ' <div class="alert fc-small fc-iblock">'.$append_str.'</div>' : '')
			.  $field->html
			.'</div>';
			
		if ( count($skipped_vals) )
			$app->enqueueMessage( JText::sprintf('FLEXI_FIELD_EDIT_VALUES_SKIPPED', $field->label, implode(',',$skipped_vals)), 'notice' );
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
		$config = JFactory::getConfig();
		$user = JFactory::getUser();
		
		// Value handling parameters
		$lang_filter_values = 0;//$field->parameters->get( 'lang_filter_values', 1);
		$date_source = $field->parameters->get('date_source', 0);
		$show_no_value  = $field->parameters->get( 'show_no_value', 0) ;
		$no_value_msg   = $field->parameters->get( 'no_value_msg', 'FLEXI_NO_VALUE') ;
		$no_value_msg   = $show_no_value ? JText::_($no_value_msg) : '';
		
		// Get field values
		$values = $values ? $values : $field->value;
		
		// Load publish_up/publish_down values if so configured
		if ( $date_source )
		{
			static $nullDate, $never_date;
			if ($nullDate == null) {
				$nullDate = JFactory::getDBO()->getNullDate();
				$never_date = ''; //JText::_('FLEXI_NEVER');
			}
			
			$_value = ($date_source == 1) ? $item->publish_up : $item->publish_down;
			if ($_value == $nullDate) {
				$field->{$prop} = $date_source==2 ? $never_date : '';
				return;
			}
			
			$values = array($_value);
		}
		
		// Timezone configuration
		$date_allowtime = $field->parameters->get( 'date_allowtime', 1 ) ;
		$use_editor_tz  = $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz  = $date_allowtime ? $use_editor_tz : 0;
		$customdate     = $field->parameters->get( 'custom_date', 'Y-m-d' ) ;
		$dateformat     = $field->parameters->get( 'date_format', '' ) ;
		$dateformat = $dateformat ? JText::_($dateformat) :
			($field->parameters->get( 'lang_filter_format', 0) ? JText::_($customdate) : $customdate);
		
		$display_tz_logged   = $field->parameters->get( 'display_tz_logged', 2) ;
		$display_tz_guests   = $field->parameters->get( 'display_tz_guests', 2) ;
		$display_tz_suffix   = $field->parameters->get( 'display_tz_suffix', 1) ;
		
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
		
		// Get timezone to use for displaying the date,  this is a string for J2.5 and an (offset) number for J1.5
		if ( !$use_editor_tz ) {
			// Raw date output, ignore timezone (no timezone info is printed), NOTE: this is OLD BEHAVIOUR of this field
			$tz_suffix_type = -1;
		} else if ($user->id) {
			$tz_suffix_type = $display_tz_logged;
		} else {
			$tz_suffix_type = $display_tz_guests;
		}
		
		$tz_info = '';
		switch ($tz_suffix_type)
		{
		default: // including value -1 for raw for output, see above
		case 0:
			$timezone = FLEXI_J16GE ? 'UTC' : 0;
			//$tz_info = '';
			break;
		case 1:
			$timezone = FLEXI_J16GE ? 'UTC' : 0;
			//$tz_info = ' UTC+0';
			break;
		case 2:
			$timezone = $config->get('offset');
			//$tz_info = ' (site's timezone)';
			break;
		case 3: 
			$timezone = $user->getParam('timezone' );
			//$tz_info = ' (local time)';
			break;
		}
		
		// display timezone suffix if this is enabled
		if ($display_tz_suffix && $tz_suffix_type > 0) {
			if (FLEXI_J16GE) {
				$tz = new DateTimeZone($timezone);
				$tz_offset = $tz->getOffset(new JDate()) / 3600;
			} else {
				// Raw date output  // FLEXI_J16GE ? 'UTC' : 0
				$tz_offset = $timezone;
			}
			$tz_info =  $tz_offset > 0 ? ' UTC +'.$tz_offset : ' UTC '.$tz_offset;
		}
		
		// initialise property
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			if ( !strlen($value) && !$is_ingroup ) continue; // Skip empty if not in field group
			if ( !strlen($value) ) {
				$field->{$prop}[$n++]	= $no_value_msg;
				continue;
			}
			
			// Check if dates are allowed to have time part
			if ($date_allowtime) $date = $value;
			else @list($date, $time) = preg_split('#\s+#', $value, $limit=2);
			
			if ( empty($date) ) continue;
			
			try {
				$date = JHTML::_('date', $date, $dateformat, $timezone ).$tz_info;
			} catch ( Exception $e ) {
				$date = '';
			}
			
			// Add prefix / suffix
			$field->{$prop}[$n]	= $pretext.$date.$posttext;
			
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
				$field->{$prop} = $no_value_msg;
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
		
		$config = JFactory::getConfig();
		$user = JFactory::getUser();
		
		$date_allowtime = $field->parameters->get( 'date_allowtime', 1 ) ;
		$use_editor_tz  = $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz = $date_allowtime ? $use_editor_tz : 0;
		
		if ($use_editor_tz == 0) {
			// Raw date input, ignore timezone, NOTE: this is OLD BEHAVIOUR of this field
			$timezone = FLEXI_J16GE ? 'UTC' : 0;
		} else {
			// For logged users the date values are in user's time zone, (unlogged users will submit in site default timezone)
			$timezone = $user->getParam('timezone', $config->get('offset'));  // this is numeric offset in J1.5 and timezone STRING in J2.5
		}
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// Do server-side validation and skip empty values
			$post[$n] = flexicontent_html::dataFilter($post[$n], 200, 'STRING', 0);
			
			if (!strlen($post[$n]) && !$use_ingroup) continue; // skip empty values

			// Check if dates are allowed to have time part
			@list($date, $time) = preg_split('#\s+#', $post[$n], $limit=2);
			$time = ($date_allowtime==2 && !$time) ? '00:00' : $time;
				
			if (!$date_allowtime)
			{
				// Time part not allowed
				$post[$n] = $date;
			}
			else if ($time)
			{
				// Time part exists
				$post[$n] = $date.' '.$time;
			}
				
			if (!$use_editor_tz || !$time)
			{
				// Dates have no timezone information, because either :
				// (a) ignoring timezone OR (b) no time given
				$newpost[$new] = $post[$n];
			}
			else
			{
				// Dates are in user's timezone, convert to UTC+0
				$date = new JDate($post[$n], $timezone);
				$newpost[$new] = $date->toSql();
			}
			$new++;
		}
		$post = $newpost;
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
		
		self::onDisplayFilter($filter, $value, $formName, $isSearchView=1);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm', $isSearchView=0)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$_s = $isSearchView ? '_s' : '';
		$date_filter_group = $filter->parameters->get('date_filter_group'.$_s, 'month');
		if ($date_filter_group=='year') { $date_valformat='%Y'; $date_txtformat='%Y'; }
		else if ($date_filter_group=='month') { $date_valformat='%Y-%m'; $date_txtformat='%Y-%b'; }
		else { $date_valformat='%Y-%m-%d'; $date_txtformat='%Y-%b-%d'; }
		
		$date_source = $filter->parameters->get('date_source', 0);
		if ( ! $date_source ) {
			$valuecol = sprintf(' DATE_FORMAT(fi.value, "%s") ', $date_valformat);
			$textcol  = sprintf(' DATE_FORMAT(fi.value, "%s") ', $date_txtformat);
		} else {
			$db = JFactory::getDBO();
			$nullDate = $db->getNullDate();
			$_value_col = ($date_source == 1) ? 'i.publish_up' : 'i.publish_down';
			$valuecol = sprintf(' CASE WHEN %s='.$db->Quote($nullDate).' THEN "'.JText::_('FLEXI_NEVER').'" ELSE DATE_FORMAT(%s, "%s") END ', $_value_col, $_value_col, $date_valformat);
			$textcol  = sprintf(' CASE WHEN %s='.$db->Quote($nullDate).' THEN "'.JText::_('FLEXI_NEVER').'" ELSE DATE_FORMAT(%s, "%s") END ', $_value_col, $_value_col, $date_txtformat);
		}
		
		// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order by
		// partial SQL clauses
		$filter->filter_valuesselect = ' '.$valuecol.' AS value, '.$textcol.' AS text';
		$filter->filter_valuesjoin   = null;  // use default
		$filter->filter_valueswhere  = null;  // use default
		// full SQL clauses
		$filter->filter_groupby = ' GROUP BY '.$valuecol;
		$filter->filter_having  = null;  // use default
		$filter->filter_orderby = ' ORDER BY '.$valuecol;
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$date_filter_group = $filter->parameters->get('date_filter_group', 'month');
		if ($date_filter_group=='year') { $date_valformat='%Y'; }
		else if ($date_filter_group=='month') { $date_valformat='%Y-%m';}
		else { $date_valformat='%Y-%m-%d'; }

		$date_source = $filter->parameters->get('date_source', 0);
		
		if ( ! $date_source ) {
			$filter->filter_colname    = sprintf(' DATE_FORMAT(rel.value, "%s") ', $date_valformat);
		} else {
			$_value_col = ($date_source == 1) ? 'c.publish_up' : 'c.publish_down';
			$filter->filter_colname    = sprintf(' DATE_FORMAT(%s, "%s") ', $_value_col, $date_valformat);
		}
		
		$filter->filter_colname    = sprintf(' DATE_FORMAT(rel.value, "%s") ', $date_valformat);
		$filter->filter_valuesjoin = null;   // use default
		$filter->filter_valueformat = sprintf(' DATE_FORMAT(__filtervalue__, "%s") ', $date_valformat);
		return FlexicontentFields::getFiltered($filter, $value, $return_sql=true);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$field, $value)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$date_source = $field->parameters->get('date_source', 0);
		if ( $date_source ) {
			JFactory::getApplication()->enqueueMessage( "Field: '".$field->label."' is using start/end publication dates and cannot be used as filter in search view" , 'notice' );
			return;
		}
		
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
		
		$values = $this->_prepareForSearchIndexing($field, $post, $for_advsearch=1);
		
		// a. Each of the values of $values array will be added to the advanced search index as searchable text (column value)
		// b. Each of the indexes of $values will be added to the column 'value_id',
		//    and it is meant for fields that we want to be filterable via a drop-down select
		// c. If $values is null then only the column 'value' will be added to the search index after retrieving 
		//    the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		$values = $this->_prepareForSearchIndexing($field, $post, $for_advsearch=0);
		
		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	// Method to prepare for indexing, either preparing SQL query (if post is null) or formating/preparing given $post data for usage bu index
	function _prepareForSearchIndexing(&$field, &$post, $for_advsearch=0)
	{
		$date_filter_group = $field->parameters->get( $for_advsearch ? 'date_filter_group_s' : 'date_filter_group', 'month');
		if ($date_filter_group=='year') { $date_valformat='%Y'; $date_txtformat='%Y'; }
		else if ($date_filter_group=='month') { $date_valformat='%Y-%m'; $date_txtformat='%Y-%b'; }
		else { $date_valformat='%Y-%m-%d'; $date_txtformat='%Y-%b-%d'; }
		
		if ($post===null) {
			$valuecol = sprintf(' DATE_FORMAT(fi.value, "%s") ', $date_valformat);
			$textcol = sprintf(' DATE_FORMAT(fi.value, "%s") ', $date_txtformat);
			
			$field->field_valuesselect = ' '.$valuecol.' AS value_id, '.$textcol.' AS value';
			$field->field_groupby = ' GROUP BY '.$valuecol;
			$values = null;
		} else {
			$values = array();
			$db = JFactory::getDBO();
			if ($post) foreach ($post as $v) {
				$valuecol = sprintf(' DATE_FORMAT("%s", "%s") ', $v, $date_valformat);
				$textcol = sprintf(' DATE_FORMAT("%s", "%s") ', $v, $date_txtformat);
				$query = 'SELECT  '.$valuecol.' AS value_id, '.$textcol.' AS value';
				$db->setQuery($query);
				$value = $db->loadObjectList();
				$values[$value[0]->value_id] = $value[0]->value;
			}
		}
		return $values;
	}
	
}
