<?php
/**
 * @package         FLEXIcontent
 * @version         3.2
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsDate extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;

		$date_source = (int) $field->parameters->get('date_source', 0);

		if ($date_source === 1 || $date_source === 2)
		{
			$date_source_str = 'Automatic field (shows this content \'s %s publication date)';
			$date_source_str = sprintf($date_source_str, ($date_source === 1) ? '<b>start</b>' :  '<b>end</b>');
			$_value = $date_source === 1 ? $item->publish_up : $item->publish_down;
			$field->html =
				 '<div class="fc-iblock">'
				.' <div class="alert alert-info fc-small fc-iblock">'.$date_source_str.'</div><div class="fcclear"></div>'
				. $_value
				.'</div>';
			return;
		}

		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$config   = JFactory::getConfig();
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';


		// ***
		// *** Number of values
		// ***

		$multiple   = $use_ingroup || (int) $field->parameters->get('allow_multiple', 0);
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get('max_values', 0);
		$required   = (int) $field->parameters->get('required', 0);
		$required_class = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get('add_position', 3);

		// If we are multi-value and not inside fieldgroup then add the control buttons (move, delete, add before/after)
		$add_ctrl_btns = !$use_ingroup && $multiple;

		$minyear = $field->parameters->get('minyear', '');
		$maxyear = $field->parameters->get('maxyear', '');

		$minyear = strlen($minyear) ? (int) $minyear : '';
		$maxyear = strlen($maxyear) ? (int) $maxyear : '';

		// Input field display size & max characters
		$size       = (int) $field->parameters->get( 'size', 30 ) ;
		$disable_keyboardinput = (int) $field->parameters->get('disable_keyboardinput', 0);


		// *******************************************
		// Find timezone and create user instructions,
		// both according to given configuration
		// *******************************************

		$date_format_form = ''; //$field->parameters->get( 'date_format_form', '' ) ;
		$show_usage     = $field->parameters->get( 'show_usage', 0 ) ;

		$date_allowtime = $field->parameters->get( 'date_allowtime', 1 ) ;
		$date_allowtime = !in_array($date_format_form, array(1,2)) ? $date_allowtime : 0;  // Allow time usage IS disabled, for 1: Year-only, 2:Month-only

		$time_formats_map = array('0'=>'', '1'=>' %H:%M', '2'=>' 00:00');
		$_time_format = $time_formats_map[$date_allowtime];

		$use_editor_tz  = $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz  = $date_allowtime ? $use_editor_tz : 0;  // Timezone IS disabled, if time usage is disabled

		$dateformat = $field->parameters->get('date_format', 'DATE_FORMAT_LC2');

		if ($dateformat === '_custom_')
		{
			$customdate = $field->parameters->get('custom_date', $date_source !== 3 ? 'DATE_FORMAT_LC2' : 'Y-M-d, H:i:s');
			$dateformat = (int) $field->parameters->get('lang_filter_format', 0)
				? JText::_($customdate)
				: $customdate;
		}
		else
		{
			$dateformat = JText::_($dateformat);
		}

		$timezone = 'UTC'; // Default is not to use TIMEZONE
		$tz_info  = '';    // Default is not to show TIMEZONE info
		$field_notes = '';


		// Time is allowed
		if ($date_allowtime)
		{
			if ($date_source !== 3)
			{
				$field_notes .= JText::_('FLEXI_DATE_CAN_ENTER_TIME') .($date_allowtime==2 ? '<br/>'.JText::_('FLEXI_DATE_USE_ZERO_TIME_ON_EMPTY') : '');
			}
		}

		if (!$use_editor_tz)  // ** timezone is disabled above, if time usage is disabled
		{
			// Raw date storing, ignoring timezone. NOTE: this is OLD BEHAVIOUR
			$timezone = 'UTC';
			$field_notes .= '<br/>'.JText::_('FLEXI_DATE_TIMEZONE_USAGE_DISABLED');
			$tz_info = $date_allowtime ? ' &nbsp; UTC' : '';
		}
		else
		{
			$timezone = $user->getParam('timezone', $config->get('offset'));   // Use timezone of editor, unlogged editor will use site's default timezone
			$tz = new DateTimeZone($timezone);
			$tz_offset = $tz->getOffset(new JDate()) / 3600;
			$tz_info =  $tz_offset > 0 ? ' &nbsp; UTC +'.$tz_offset : ' UTC '.$tz_offset;

			$field_notes .= '
				<br/>'.JText::_('FLEXI_DATE_TIMEZONE_USAGE_ENABLED').'
				<br/>'.JText::_($user->id ? 'FLEXI_DATE_ENTER_HOURS_IN_YOUR_TIMEZONE' : 'FLEXI_DATE_ENTER_HOURS_IN_TIMEZONE').': '.$tz_info;
		}

		// Timestamp mode (Current time), which will be displayed as user time but saved as 'UTC 0'
		if ($date_source === 3)
		{
			$tz = new DateTimeZone($timezone);
			$date_now = JFactory::getDate('now');
			$date_now->setTimezone($tz);
			$date_now_str = $date_now->format($dateformat, $local = true);
		}

		$field_notes = $field_notes ? '<b>'.JText::_('FLEXI_NOTES').'</b>: '.$field_notes : '';

		// Initialise property with default value
		if (!$field->value)
		{
			$field->value = array();
			$field->value[0] = '';
		}

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		$js = '';
		$css = '';

		// Handle multiple records
		if ($multiple)
		{
			// Add the drag and drop sorting feature
			if ($add_ctrl_btns) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					/*containment: 'parent',*/
					tolerance: 'pointer'
					".($field->parameters->get('fields_box_placing', 1) ? "
					,start: function(e) {
						//jQuery(e.target).children().css('float', 'left');
						//fc_setEqualHeights(jQuery(e.target), 0);
					}
					,stop: function(e) {
						//jQuery(e.target).children().css({'float': 'none', 'min-height': '', 'height': ''});
					}
					" : '')."
				});
			});
			";

			if ($max_values) JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			function addField".$field->id."(el, groupval_box, fieldval_box, params)
			{
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;

				if(!remove_previous && (rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}

				// Find last container of fields and clone it to create a new container of fields
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				newField.find('.fc-has-value').removeClass('fc-has-value');
				";

			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			// Update the new (date) input field
			$js .= "
				var theInput = newField.find('input.fcfield_date').first();
				theInput.val('');
				theInput.attr('name', '".$fieldname."['+uniqueRowNum".$field->id."+']');
				theInput.attr('id', '".$elementid."_'+uniqueRowNum".$field->id.");

				// Update date picker
				var newCalendar = typeof JoomlaCalendar === 'function';
				var thePicker = theInput.next();
				thePicker.attr('id', '".$elementid."_' +uniqueRowNum".$field->id." + (newCalendar ? '_btn' : '_img'));
				";

			if ($date_source === 3) $js .= "
				newField.find('.fcfield_timestamp_value_new').css('display', '');
				newField.find('.fcfield_timestamp_value_existing').html('').css('display', 'none');
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

				// Attach form validation on new element
				fc_validationAttach(newField);
				";

			if ($date_source !== 3) $js .= "
				// Add a tag id to the date field outer container to be able to select it
				var df_tag_id = 'field-calendar-fc_".$field->id."_' + (uniqueRowNum".$field->id." + 1);
				theInput.closest('.field-calendar').attr('id', df_tag_id);

				// This needs to be after field is added to DOM (unlike e.g. select2 / inputmask JS scripts)
				if (newCalendar)
				{
					theInput.parent().next().remove();
					JoomlaCalendar.init(document.getElementById(df_tag_id));
				}
				else
				{
					Calendar.setup({
						inputField:	theInput.attr('id'),
						ifFormat:		'%Y-%m-%d".$_time_format."',
						button:			thePicker.attr('id'),
						align:			'Tl',
						singleClick:	true
					});
				}
				";

			// Add new element to sortable objects (if field not in group)
			if ($add_ctrl_btns) $js .= "
				//jQuery('#sortables_".$field->id."').sortable('refresh');  // Refresh was done appendTo ?
				";

			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800, function() { jQuery(this).css('opacity', ''); });

				// Enable tooltips on new element
				newField.find('.hasTooltip').tooltip({html: true, container: newField});
				newField.find('.hasPopover').popover({html: true, container: newField, trigger : 'hover focus'});

				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Disable clicks on remove button, so that it is not reclicked, while we do the field value hide effect (before DOM removal of field value)
				var btn = fieldval_box ? false : jQuery(el);
				if (btn && rowCount".$field->id." > 1) btn.css('pointer-events', 'none').off('click');

				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');

				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 1)
					addField".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});

				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if (rowCount".$field->id." > 1)
				{
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the field value hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-expand-view').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ jQuery(this).remove(); });
					rowCount".$field->id."--;
				}
			}
			";

			$css .= '';

			$remove_button = '<span class="' . $add_on_class . ' fcfield-delvalue ' . $font_icon_class . '" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="' . $add_on_class . ' fcfield-drag-handle ' . $font_icon_class . '" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="' . $add_on_class . ' fcfield-insertvalue fc_before ' . $font_icon_class . '" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="' . $add_on_class . ' fcfield-insertvalue fc_after ' . $font_icon_class . '"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		}

		// Field not multi-value
		else
		{
			$remove_button = '';
			$move2 = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}


		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();
		$n = 0;
		$skipped_vals = array();
		$per_val_js = '';

		foreach ($field->value as $value)
		{
			if ( !strlen($value) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group

			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;

			if ($date_source === 3)
			{
				// Use indexes of existing values to keep track of field values being re-ordered, this (partly) avoids form tampering
				if ($value)
				{
					try {
						$timestamp = '<span class="alert alert-info fc-small fc-iblock fcfield_timestamp_value_existing">'. JHtml::_('date', $value, $dateformat, $timezone ).$tz_info.'</span> ';
					} catch ( Exception $e ) {
						$timestamp = '';
					}
				}
				else
				{
					$timestamp = '';
				}

				$timestamp .=
						'<span class="alert alert-info fc-small fc-iblock fcfield_timestamp_value_new" style="'.($timestamp ? 'display:none;' : '').'">'.JText::_('FLEXI_FIELD_DATE_NOW').', '. // ' - '$date_now_str.' - '
						'<span class="fcfield_timestamp_note">'.JText::_( 'FLEXI_AUTO' ).'</span></span>';

				$html = $timestamp .'
					<input type="hidden" class="fcfield_date" value="'.($value ? $n : '').'" id="'.$elementid_n.'" name="'.$fieldname_n.'" />
				';
			}
			else
			{
				$attribs_arr = array('class'=>'fcfield_date use_fcfield_box input-medium' . $required_class);

				if (strlen($minyear))
				{
					$attribs_arr['minYear'] = $minyear;
				}

				if (strlen($maxyear))
				{
					$attribs_arr['maxYear'] = $maxyear;
				}

				$html = FlexicontentFields::createCalendarField($value, $date_allowtime, $fieldname_n, $elementid_n, $attribs_arr, $skip_on_invalid=true, $timezone);

				if (!$html)
				{
					$skipped_vals[] = $value;
					if (!$use_ingroup)
					{
						continue;
					}
					$html = FlexicontentFields::createCalendarField('', $date_allowtime, $fieldname_n, $elementid_n, $attribs_arr, $skip_on_invalid=true, $timezone);
				}
			}

			$field->html[] = '
				<div class="fcfield_box' .($required ? ' required_box' : ''). ' fc-iblock" data-label_text="'.$field->label.'">
					' . $html . '
				</div>
				'.(!$add_ctrl_btns ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				');

			if ($disable_keyboardinput)
			{
				$per_val_js = "
					jQuery('#".$elementid_n."').on('keydown keypress keyup', false);
				";
			}

			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}

		if ($per_val_js)
		{
			$js .= "
			jQuery(document).ready(function()
			{
				" . $per_val_js . "
			});
			";
		}

		// Added field's custom CSS / JS
		if ($multiple) $js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
		";
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);

		// Do not convert the array to string if field is in a group
		if ($use_ingroup);

		// Handle multiple records
		elseif ($multiple)
		{
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '
				<div class="input-append input-prepend fc-xpended-btns">
					<span class="fcfield-addvalue ' . $font_icon_class . ' fccleared" onclick="addField'.$field->id.'(jQuery(this).closest(\'.fc-xpended-btns\').get(0));" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">
						'.JText::_( 'FLEXI_ADD_VALUE' ).'
					</span>
				</div>';
		}

		// Handle single values
		else
		{
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}

		if (!$use_ingroup)
			$field->html = (($show_usage && $field_notes) ? ' <div class="alert alert-info fc-small fc-iblock">'.$field_notes.'</div><div class="fcclear"></div>' : '')  .  $field->html;

		if ( count($skipped_vals) )
			$app->enqueueMessage( JText::sprintf('FLEXI_FIELD_DATE_EDIT_VALUES_SKIPPED', $field->label, implode(',',$skipped_vals)), 'notice' );
	}


	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		$field->label = JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$config = JFactory::getConfig();
		$user = JFactory::getUser();

		// Value handling parameters
		$lang_filter_values = 0;//$field->parameters->get( 'lang_filter_values', 1);
		$date_source = (int) $field->parameters->get('date_source', 0);
		$show_no_value  = $field->parameters->get( 'show_no_value', 0) ;
		$no_value_msg   = $field->parameters->get( 'no_value_msg', 'FLEXI_NO_VALUE') ;
		$no_value_msg   = $show_no_value ? JText::_($no_value_msg) : '';

		// Get field values
		$values = $values ? $values : $field->value;

		// Load publish_up/publish_down values if so configured
		if ($date_source === 1 || $date_source === 2)
		{
			static $nullDate, $never_date;
			if ($nullDate == null)
			{
				$nullDate = JFactory::getDbo()->getNullDate();
				$never_date = ''; //JText::_('FLEXI_NEVER');
			}

			$_value = $date_source === 1
				? $item->publish_up
				: $item->publish_down;
			if ($_value == $nullDate)
			{
				$field->{$prop} = $date_source === 2 ? $never_date : '';
				return;
			}

			$values = array($_value);
		}

		// Check for no values and no default value, and return empty display
		if (empty($values))
		{
			$field->{$prop} = $is_ingroup ? array() : '';
			return;
		}

		
		// ***
		// *** One time initialization
		// ***

		static $initialized = null;
		static $app, $document, $option, $format, $realview;

		if ($initialized === null)
		{
			$initialized = 1;

			$app       = JFactory::getApplication();
			$document  = JFactory::getDocument();
			$option    = $app->input->get('option', '', 'cmd');
			$format    = $app->input->get('format', 'html', 'cmd');
			$realview  = $app->input->get('view', '', 'cmd');
		}

		// Current view variable
		$view = $app->input->get('flexi_callview', ($realview ?: 'item'), 'cmd');
		$sfx = $view === 'item' ? '' : '_cat';

		// Timezone configuration
		$date_allowtime = $field->parameters->get( 'date_allowtime', 1 ) ;
		$use_editor_tz  = $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz  = $date_allowtime ? $use_editor_tz : 0;  // Timezone IS disabled, if time usage is disabled

		// Get date format for single or for multi-item views
		$dateformat = $field->parameters->get('date_format', 'DATE_FORMAT_LC2');
		$dateformat = $sfx
			? $field->parameters->get('date_format' . $sfx, $dateformat)
			: $dateformat;

		if ($dateformat === '_custom_')
		{
			$customdate = $field->parameters->get('custom_date' . $sfx, $date_source !== 3 ? 'DATE_FORMAT_LC2' : 'Y-M-d, H:i:s');
			$dateformat = (int) $field->parameters->get('lang_filter_format' . $sfx, 0)
				? JText::_($customdate)
				: $customdate;
		}
		else
		{
			$dateformat = JText::_($dateformat);
		}

		$display_tz_logged   = $field->parameters->get( 'display_tz_logged', 2) ;
		$display_tz_guests   = $field->parameters->get( 'display_tz_guests', 2) ;
		$display_tz_suffix   = $field->parameters->get( 'display_tz_suffix', 1) ;

		// Microdata (classify the field values for search engines)
		$itemprop    = $field->parameters->get('microdata_itemprop');

		// Prefix - Suffix - Separator parameters - Other common parameters
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);

		// Get timezone to use for displaying the date,  this is a string for J2.5 and an (offset) number for J1.5
		if ( !$use_editor_tz ) {
			// Raw date output, ignore timezone (no timezone info is printed), NOTE: this is OLD BEHAVIOUR of this field
			$tz_suffix_type = -1;
		} else if ($user->id) {
			$tz_suffix_type = $display_tz_logged;
		} else {
			$tz_suffix_type = $display_tz_guests;
		}

		// Decide the timezone to use
		$tz_info = '';
		switch ($tz_suffix_type)
		{
			default: // including value -1 for raw for output, see above
			case 0:
				$timezone = 'UTC';   // ''
				break;
			case 1:
				$timezone = 'UTC';   // ' UTC+0'
				break;
			case 2:
				$timezone = $config->get('offset');  // Site's timezone
				break;
			case 3:
				$timezone = $user->getParam('timezone' );  // User's local time
				break;
		}

		// Display timezone suffix if this is enabled
		if ($display_tz_suffix && $tz_suffix_type > 0)
		{
			$tz = new DateTimeZone($timezone);
			$tz_offset = $tz->getOffset(new JDate()) / 3600;
			$tz_info =  $tz_offset > 0 ? ' UTC +'.$tz_offset : ' UTC '.$tz_offset;
		}

		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';

		// Create field's HTML, using layout file
		$field->{$prop} = array();
		include(self::getViewPath($field->field_type, $viewlayout));

		// Do not convert the array to string if field is in a group, and do not add: FIELD's opentag, closetag, value separator
		if (!$is_ingroup)
		{
			// Apply values separator
			$field->{$prop} = implode($separatorf, $field->{$prop});
			if ( $field->{$prop}!=='' )
			{
				// Apply field 's opening / closing texts
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;

				// Add microdata once for all values, if field -- is NOT -- in a field group
				if ( $itemprop )
				{
					$field->{$prop} = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}. '</div>';
				}
			} else {
				$field->{$prop} = $no_value_msg;
			}
		}
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$date_source = (int) $field->parameters->get('date_source', 0);

		// Timestamp mode (Current time), which will be displayed as user time but saved as 'UTC 0'
		if ($date_source === 3)
		{
			// Dates are always stored using 'UTC 0' timezone
			$tz = new DateTimeZone('UTC');

			$date_now = JFactory::getDate('now');
			$date_now->setTimezone($tz);

			$date_now_value = $date_now->toSql();
		}

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;

		$config = JFactory::getConfig();
		$user = JFactory::getUser();

		$date_allowtime = $field->parameters->get( 'date_allowtime', 1 ) ;
		$use_editor_tz  = $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz  = $date_allowtime ? $use_editor_tz : 0;  // Timezone IS disabled, if time usage is disabled

		if ($use_editor_tz == 0) {
			// Raw date input, ignore timezone, NOTE: this is OLD BEHAVIOUR of this field
			$timezone = 'UTC';
		} else {
			// For logged users the date values are in user's time zone, (unlogged users will submit in site default timezone)
			$timezone = $user->getParam('timezone', $config->get('offset'));  // this is numeric offset in J1.5 and timezone STRING in J2.5
		}


		// ***
		// *** Reformat the posted data
		// ***

		// Make sure posted data is an array
		$post = !is_array($post) ? array($post) : $post;

		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			if ($date_source === 3)
			{
				// New timestamp
				if (!strlen($v))
				{
					$newpost[$new++] = $date_now_value;
				}

				// Existing timestamps
				else
				{
					$v = (int) $v;
					$newpost[$new++] = isset($item->fieldvalues[$field->id][$v])
						? $item->fieldvalues[$field->id][$v]
						: $date_now_value;
				}

				continue;
			}

			// ***
			// *** Validate data, skipping values that are empty after validation
			// ***

			$post[$n] = flexicontent_html::dataFilter($post[$n], 200, 'STRING', 0);

			// Skip empty value, but if in group increment the value position
			if (!strlen($post[$n]))
			{
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}

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
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$this->onDisplayFilter($filter, $value, $formName, $isSearchView=1);
	}


	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm', $isSearchView=0)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$_s = $isSearchView ? '_s' : '';
		$date_filter_group = $filter->parameters->get('date_filter_group'.$_s, 'month');
		if ($date_filter_group=='year') { $date_valformat='%Y'; }
		else if ($date_filter_group=='month') { $date_valformat='%Y-%m'; }
		else { $date_valformat='%Y-%m-%d'; }

		// Display date 'label' can be different than the (aggregated) date value
		$date_filter_label_format = $filter->parameters->get('date_filter_label_format'.$_s, '');
		$date_txtformat = $date_filter_label_format ? $date_filter_label_format : $date_valformat;  // If empty then same as value

		$filter->date_valformat = $date_valformat;
		$filter->date_txtformat = $date_txtformat;

		$db = JFactory::getDbo();
		$nullDate_quoted = $db->Quote($db->getNullDate());

		$display_filter_as = $filter->parameters->get( 'display_filter_as'.$_s, 0 );  // Filter Type of Display
		$filter_as_range = in_array($display_filter_as, array(2,3,8));  // We don't want null date if using a range
		$date_source = $filter->parameters->get('date_source', 0);

		if ( !$date_source || $date_source==3 )
		{
			$valuecol = sprintf(' DATE_FORMAT(fi.value, "%s") ', $date_valformat);
			$textcol  = sprintf(' DATE_FORMAT(fi.value, "%s") ', $date_txtformat);
		}
		else if ( $date_source==1 || $date_source==2 )
		{
			$_value_col = ($date_source == 1) ? 'i.publish_up' : 'i.publish_down';
			$valuecol = sprintf(' CASE WHEN %s='.$nullDate_quoted.' THEN '.(!$filter_as_range ? $nullDate_quoted : $db->Quote('')).' ELSE DATE_FORMAT(%s, "%s") END ', $_value_col, $_value_col, $date_valformat);
			$textcol  = sprintf(' CASE WHEN %s='.$nullDate_quoted.' THEN "'.JText::_('FLEXI_NEVER').'" ELSE DATE_FORMAT(%s, "%s") END ', $_value_col, $_value_col, $date_txtformat);
		}
		else
		{
			return "date_source: ".$date_source." not implemented";
		}

		// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
		// partial SQL clauses
		$filter->filter_valuesselect = ' '.$valuecol.' AS value, '.$textcol.' AS text';
		if ( $date_source==1 || $date_source==2 ) {
			$filter->filter_valuesfrom = ' FROM #__content AS i ';
			$filter->filter_item_id_col = ' i.id ';
			$filter->filter_valuesjoin   = null; // use default
			$filter->filter_valueswhere  = ' ';  // space to remove default
		} else if ( !$date_source || $date_source==3 ) {
			$filter->filter_valuesfrom   = null;  // use default
			$filter->filter_valuesjoin   = null;  // use default
			$filter->filter_valueswhere  = null;  // use default
		} else {
			return "date_source: ".$date_source." not implemented";
		}

		// full SQL clauses
		$filter->filter_groupby = ' GROUP BY '.$valuecol;
		$filter->filter_having  = null;  // use default

		if ($isSearchView)
			$filter->filter_orderby_adv = ' ORDER BY value_id';  // we can use a date type cast here, but it is not needed due to the format of value_id
		else
			$filter->filter_orderby = ' ORDER BY '.$valuecol;

		FlexicontentFields::createFilter($filter, $value, $formName);
	}


 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$date_filter_group = $filter->parameters->get('date_filter_group', 'month');
		if ($date_filter_group=='year') { $date_valformat='%Y'; }
		else if ($date_filter_group=='month') { $date_valformat='%Y-%m';}
		else { $date_valformat='%Y-%m-%d'; }

		$date_source = $filter->parameters->get('date_source', 0);

		if ( !$date_source || $date_source==3 ) {
			$filter->filter_colname    = sprintf(' DATE_FORMAT(rel.value, "%s") ', $date_valformat);
			$filter->filter_valuesjoin = null;   // use default query
		} else if ( $date_source==1 || $date_source==2 ) {
			$_value_col = ($date_source == 1) ? 'c.publish_up' : 'c.publish_down';
			$filter->filter_colname    = sprintf(' DATE_FORMAT(%s, "%s") ', $_value_col, $date_valformat);
			$filter->filter_valuesjoin = ' '; // a space to prevent using default query and instead use content table
		} else {
			JFactory::getApplication()->enqueueMessage( __FUNCTION__." for field: '".$filter->label.", date_source: ".$date_source." not implemented" , 'notice' );
		}

		$filter->filter_valueformat = sprintf(' DATE_FORMAT(__filtervalue__, "%s") ', $date_valformat);   // format of given values must be same as format of the value-column

		return FlexicontentFields::getFiltered($filter, $value, $return_sql);
	}


 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$date_source = $filter->parameters->get('date_source', 0);
		/*if ( $date_source==1 || $date_source==2 ) {
			JFactory::getApplication()->enqueueMessage( "Field: '".$filter->label."' is using start/end publication dates and cannot be used as filter in search view" , 'notice' );
			return;
		}*/

		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}



	// ***
	// *** SEARCH / INDEXING METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;

		$values = $this->_prepareForSearchIndexing($field, $item, $post, $for_advsearch=1);

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
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;

		$values = $this->_prepareForSearchIndexing($field, $item, $post, $for_advsearch=0);

		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	// Method to prepare for indexing, either preparing SQL query (if post is null) or formating/preparing given $post data for usage bu index
	function _prepareForSearchIndexing(&$field, &$item, &$post, $for_advsearch=0)
	{
		$date_source = (int) $field->parameters->get('date_source', 0);

		$date_filter_group = $field->parameters->get( $for_advsearch ? 'date_filter_group_s' : 'date_filter_group', 'month');
		if ($date_filter_group=='year') { $date_valformat='%Y'; }
		else if ($date_filter_group=='month') { $date_valformat='%Y-%m'; }
		else { $date_valformat='%Y-%m-%d'; }

		// Display date 'label' can be different than the (aggregated) date value
		$date_filter_label_format = $field->parameters->get('date_filter_label_format_s', '');
		$date_txtformat = $date_filter_label_format ? $date_filter_label_format : $date_valformat;  // If empty then same as value

		if ($post===null) {
			// null indicates that indexer is running, values is set to NULL which means retrieve data from the DB
			$_value_col = !$date_source || $date_source==3 ? 'fi.value' : ($date_source == 1 ? 'i.publish_up' : 'i.publish_down');
			$valuecol = sprintf(' DATE_FORMAT('.$_value_col.', "%s") ', $date_valformat);
			$textcol  = sprintf(' DATE_FORMAT('.$_value_col.', "%s") ', $date_txtformat);

			$field->field_valuesselect = ' '.$valuecol.' AS value_id, '.$textcol.' AS value';
			if ( $date_source==1 || $date_source==2 ) {
				$field->field_valuesfrom = ' FROM #__content AS i ';
				$field->field_item_id_col = ' i.id ';
				$field->field_valueswhere = ' ';  // a space to remove default
			}
			$field->field_groupby = ' GROUP BY '.$valuecol;
			$values = null;
		} else {
			$values = array();
			$db = JFactory::getDbo();
			if ( $date_source==1 || $date_source==2 ) {
				$post = array($date_source == 1 ? $item->publish_up : $item->publish_down);
			}
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
