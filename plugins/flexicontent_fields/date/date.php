<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2020, FLEXIcontent team, All Rights Reserved
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

	public function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = $field->parameters->get('label_form') ? JText::_($field->parameters->get('label_form')) : JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;

		/**
		 * Check if using 'auto_value_code', clear 'auto_value', if function not set
		 */
		$auto_value = (int) $field->parameters->get('auto_value', 0);
		if ($auto_value === 2)
		{
			$auto_value_code = $field->parameters->get('auto_value_code', '');
			$auto_value_code = preg_replace('/^<\?php(.*)(\?>)?$/s', '$1', $auto_value_code);
		}
		$auto_value = $auto_value === 2 && !$auto_value_code ? 0 : $auto_value;

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

		// Initialize framework objects and other variables
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
		$font_icon_class .= FLEXI_J40GE ? ' icon icon- ' : '';


		/**
		 * Number of values
		 */

		$multiple     = $use_ingroup || (int) $field->parameters->get('allow_multiple', 0);
		$max_values   = $use_ingroup ? 0 : (int) $field->parameters->get('max_values', 0);
		$required     = (int) $field->parameters->get('required', 0);
		$add_position = (int) $field->parameters->get('add_position', 3);

		// Classes for marking field required
		$required_class = $required ? ' required' : '';

		// If we are multi-value and not inside fieldgroup then add the control buttons (move, delete, add before/after)
		$add_ctrl_btns = !$use_ingroup && $multiple;
		$fields_box_placing = (int) $field->parameters->get('fields_box_placing', 0);


		/**
		 * Value handling
		 */

		// Default value(s)
		$default_values = $this->getDefaultValues($isform = true);
		$default_value  = reset($default_values);


		/**
		 * Form field display parameters
		 */

		// Usage information
		$show_usage  = (int) $field->parameters->get( 'show_usage', 0 ) ;
		$field_notes = '';

		// Input field display size & max characters
		$size = (int) $field->parameters->get( 'size', 30 ) ;

		$display_label_form = (int) $field->parameters->get( 'display_label_form', 1 ) ;
		$placeholder        = $display_label_form==-1 ? $field->label : JText::_($field->parameters->get( 'placeholder', '' )) ;

		// Input field display limitations
		$minyear = $field->parameters->get('minyear', '');
		$maxyear = $field->parameters->get('maxyear', '');

		$minyear = strlen($minyear) ? (int) $minyear : '';
		$maxyear = strlen($maxyear) ? (int) $maxyear : '';

		// Create extra HTML TAG parameters for the form field
		$classes = '';
		$disable_keyboardinput = (int) $field->parameters->get('disable_keyboardinput', 0);
		$onChange = $field->parameters->get('onchange', '');


		/**
		 * Find timezone and create user instructions,
		 * both according to given configuration
		 */

		$date_allowtime = (int) $field->parameters->get( 'date_allowtime', 1 ) ;

		$timeFormatsMap = array('0'=>'', '1'=>' %H:%M', '2'=>' 00:00');
		$timeformat     = $timeFormatsMap[$date_allowtime];

		$use_editor_tz  = (int) $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz  = $date_allowtime ? $use_editor_tz : 0;  // Timezone IS disabled, if time usage is disabled

		$dateformat = $field->parameters->get('date_format_form', '%Y-%m-%d');
		$dateformat = JText::_($dateformat);

		$timezone = 'UTC'; // Default is not to use TIMEZONE
		$tz_info  = '';    // Default is not to show TIMEZONE info


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
			$date_now_str = $date_now->format(
				str_replace('%', '', JText::_($dateformat)),
				$local = true
			);
		}

		$field_notes = $field_notes ? '<b>'.JText::_('FLEXI_NOTES').'</b>: '.$field_notes : '';

		// Initialise property with default value
		if (!$field->value || (count($field->value) === 1 && reset($field->value) === null))
		{
			$field->value = $default_values;
		}

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$value_classes .= $fields_box_placing ? ' floated' : '';

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);

		// JS & CSS of current field
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
					cancel: false,
					/*containment: 'parent',*/
					tolerance: 'pointer'
					".($fields_box_placing ? "
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

				// New element's field name and id
				var uniqueRowN = uniqueRowNum" . $field->id . ";
				var element_id = '" . $elementid . "_' + uniqueRowN;
				var fname_pfx  = '" . $fieldname . "[' + uniqueRowN + ']';
				";

			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			$js .= "
				// Update the new (date) input field
				var theInput = newField.find('input.fcfield_date').first();
				theInput.attr('data-alt-value', '');
				theInput.attr('value', '');
				theInput.attr('name', fname_pfx);
				theInput.attr('id', element_id);

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
			if ($disable_keyboardinput)
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
						ifFormat:		'" . $dateformat . $timeformat . "',
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

		// We may comment out some classes so that layout decides these
		$classes .= /*' fcfield_textval' .*/ $required_class;

		// Set field to 'Automatic' on successful validation'
		if ($auto_value)
		{
			$classes = ' fcfield_auto_value ';
		}


		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();
		$n = 0;

		$skipped_vals = array();
		$per_val_js   = '';

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
				$attribs_arr = array(
					'class' => 'fcfield_date use_fcfield_box input-medium ' . $classes,
				);

				if ($placeholder)
				{
					$attribs_arr['hint'] = $placeholder;
				}

				if ($auto_value)
				{
					$attribs_arr['readonly'] = 'readonly';
				}

				if($onChange)
				{
					$attribs_arr['onChange'] = $onChange;
				}

				if (strlen($minyear))
				{
					$attribs_arr['minYear'] = $minyear;
				}

				if (strlen($maxyear))
				{
					$attribs_arr['maxYear'] = $maxyear;
				}

				$html = FlexicontentFields::createCalendarField($value, $date_allowtime, $fieldname_n, $elementid_n, $attribs_arr, $skip_on_invalid=false, $timezone, $dateformat);

				if (!$html)
				{
					$skipped_vals[] = $value;
					if (!$use_ingroup)
					{
						continue;
					}
					$html = FlexicontentFields::createCalendarField('', $date_allowtime, $fieldname_n, $elementid_n, $attribs_arr, $skip_on_invalid=false, $timezone, $dateformat);
				}
			}

			$field->html[] = '
				<div class="fcfield_box' .($required ? ' required_box' : ''). ' fc-iblock" data-label_text="'.$field->label.'">
					' . $html . '
				</div>
				' . (!$add_ctrl_btns || $auto_value ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				');

			if ($disable_keyboardinput || $auto_value)
			{
				$per_val_js = "
					jQuery('#".$elementid_n."').on('keydown keypress keyup', false);
				";
			}

			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}

		// Add per value JS
		if ($per_val_js)
		{
			$js .= "
			jQuery(document).ready(function()
			{
				" . $per_val_js . "
			});
			";
		}

		// Add field's CSS / JS
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
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">
				' . (isset($field->html[-1]) ? $field->html[-1] : '') . $field->html[0] . '
			</div>';
		}

		if (!$use_ingroup)
		{
			$field->html = ($show_usage && $field_notes
				? ' <div class="alert alert-info fc-small fc-iblock">'.$field_notes.'</div><div class="fcclear"></div>'
				: ''
			) . $field->html;
		}

		if (count($skipped_vals))
		{
			$app->enqueueMessage( JText::sprintf('FLEXI_FIELD_DATE_EDIT_VALUES_SKIPPED', $field->label, implode(',',$skipped_vals)), 'notice' );
		}
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);


		/**
		 * One time initialization
		 */

		static $initialized = null;
		static $app, $document, $option, $format, $realview;

		if ($initialized === null)
		{
			$initialized = 1;

			$app       = JFactory::getApplication();
			$document  = JFactory::getDocument();
			$option    = $app->input->getCmd('option', '');
			$format    = $app->input->getCmd('format', 'html');
			$realview  = $app->input->getCmd('view', '');
		}

		// Current view variable
		$view = $app->input->getCmd('flexi_callview', ($realview ?: 'item'));
		$sfx = $view === 'item' ? '' : '_cat';

		// Check if field should be rendered according to configuration
		if (!$this->checkRenderConds($prop, $view))
		{
			return;
		}

		// The current view is a full item view of the item
		$isMatchedItemView = static::$itemViewId === (int) $item->id;

		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$config = JFactory::getConfig();
		$user = JFactory::getUser();

		// Value handling parameters
		$lang_filter_values = 0;
		$date_source = (int) $field->parameters->get('date_source', 0);


		/**
		 * Get field values
		 */

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
			$values = $this->getDefaultValues($isform = false);

			if (!count($values))
			{
				$field->{$prop} = $is_ingroup ? array() : '';
				return;
			}
		}


		// Timezone configuration
		$date_allowtime = (int) $field->parameters->get( 'date_allowtime', 1 ) ;
		$use_editor_tz  = (int) $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz  = $date_allowtime ? $use_editor_tz : 0;  // Timezone IS disabled, if time usage is disabled

		/**
		 * Get date format for single or for multi-item views
		 */

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


		/**
		 * Get timezone to use for displaying the date, (this is a string)
		 */

		// Raw date output, ignore timezone (no timezone info is printed), NOTE: this is OLD BEHAVIOUR of this field
		if (!$use_editor_tz)
		{
			$tz_suffix_type = -1;
		}
		elseif ($user->id)
		{
			$tz_suffix_type = $display_tz_logged;
		}
		else
		{
			$tz_suffix_type = $display_tz_guests;
		}

		// Decide the timezone to use
		$tz_info = '';

		switch ($tz_suffix_type)
		{
			default:
			case 0:
				// Default, including value -1 for raw for output, see above
				$timezone = 'UTC';
				break;

			case 1:
				// ' UTC+0'
				$timezone = 'UTC';
				break;

			case 2:
				// Site's timezone
				$timezone = $config->get('offset');
				break;

			case 3:
				// User's local time
				$timezone = $user->getParam('timezone' );
				break;
		}

		// Display timezone suffix if this is enabled
		if ($display_tz_suffix && $tz_suffix_type > 0)
		{
			$tz        = new DateTimeZone($timezone);
			$tz_offset = $tz->getOffset(new JDate()) / 3600;
			$tz_info   = $tz_offset > 0 ? ' UTC +' . $tz_offset : ' UTC ' . $tz_offset;
		}


		/**
		 * Get common parameters like: itemprop, value's prefix (pretext), suffix (posttext), separator, value list open/close text (opentag, closetag)
		 * This will replace other field values and item properties, if such are found inside the parameter texts
		 */
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);

		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';

		// Create field's viewing HTML, using layout file
		$field->{$prop} = array();
		include(self::getViewPath($field->field_type, $viewlayout));

		// Do not convert the array to string if field is in a group, and do not add: FIELD's opentag, closetag, value separator
		if (!$is_ingroup)
		{
			// Apply values separator
			$field->{$prop} = implode($separatorf, $field->{$prop});

			if ($field->{$prop} !== '')
			{
				// Apply field 's opening / closing texts
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;

				// Add microdata once for all values, if field -- is NOT -- in a field group
				if ($itemprop)
				{
					$field->{$prop} = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}. '</div>';
				}
			}
			elseif ($no_value_msg !== '')
			{
				$field->{$prop} = $no_value_msg;
			}
		}
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
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

		$date_allowtime = (int) $field->parameters->get( 'date_allowtime', 1 ) ;
		$use_editor_tz  = (int) $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz  = $date_allowtime ? $use_editor_tz : 0;  // Timezone IS disabled, if time usage is disabled

		/**
		 * Optionally for logged users the date values CAN BE in user's time zone, (unlogged users will submit in site default timezone)
		 * Otherwise use raw date input, ignoring timezone (assume UTC), NOTE: this is OLD BEHAVIOUR of this field
		 *
		 *  - NOTE: For values that do not provide time we will fallback to using UTC
		 */
		$timezone = $use_editor_tz
			? $user->getParam('timezone', $config->get('offset'))  // Note: timezone is a STRING in J2.5 +
			: 'UTC';


		// ***
		// *** Reformat the posted data
		// ***

		// Make sure posted data is an array
		$post = !is_array($post) ? array($post) : $post;

		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// Check if field was cleared
			$post[$n] = $post[$n] === '0000-00-00 00:00:00' ? '' : $post[$n];

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

			/**
			 * Try to parse the date according to custom date format (for item form). Do not change if no match
			 */
			$post[$n] = $this->_customFormat_to_sqlFormat($field, $post[$n]);

			/**
			 * Verify that we have a valid date !! Try to parse the date, while considering timezone
			 */
			try {
				$dateObj  = new JDate($post[$n], ($time ? $timezone : 'UTC'));
				$post[$n] = $dateObj->toSql();
			}
			catch ( Exception $e ) {
				JFactory::getApplication()->enqueueMessage(
					'<b>' . $field->label . ' ' . JText::_('FLEXI_FIELD') . '</b>: ' .
					JText::sprintf('FLEXI_CLEARING_INVALID_CALENDAR_DATE', $post[$n])
				, 'warning');
			}

			$newpost[$new] = $post[$n];
			$new++;
		}

		$post = $newpost;
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value = '', $formName = 'searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$this->onDisplayFilter($filter, $value, $formName, $isSearchView=1);
	}


	// Method to display a category filter for the category view
	public function onDisplayFilter(&$filter, $value = '', $formName = 'adminForm', $isSearchView = 0)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$_s = $isSearchView ? '_s' : '';

		$display_filter_as = $filter->parameters->get( 'display_filter_as'.$_s, 0 );  // Filter Type of Display
		$date_filter_group = $filter->parameters->get('date_filter_group'.$_s, 'month');
		$filter_as_age     = $filter->parameters->get('filter_as_age'.$_s, 0);

		if ($date_filter_group === 'year')
		{
			$date_valformat  = '%Y';
			$filter_age_type = 'YEAR';
		}
		elseif ($date_filter_group === 'month')
		{
			$date_valformat  = '%Y-%m';
			$filter_age_type = 'MONTH';
		}
		else
		{
			$date_valformat  = '%Y-%m-%d';
			$filter_age_type = 'DAY';
		}

		if (in_array($display_filter_as, array(1, 3)))
		{
			$date_valformat = '%Y-%m-%d';
			$filter_age_type = 'DAY';
		}

		// Display date 'label' can be different than the (aggregated) date value
		$date_filter_label_format = $filter->parameters->get('date_filter_label_format'.$_s, '');
		$date_txtformat = $date_filter_label_format ? $date_filter_label_format : $date_valformat;  // If empty then same as value

		$filter->date_valformat = $date_valformat;
		$filter->date_txtformat = $date_txtformat;

		$db = JFactory::getDbo();
		$nullDate_quoted = $db->Quote($db->getNullDate());

		$filter_as_range = in_array($display_filter_as, array(2,3,8));  // We don't want null date if using a range
		$date_source = (int) $filter->parameters->get('date_source', 0);

		if ($date_source === 0 || $date_source === 3)
		{
			if (!$filter_as_age)
			{
				$valuecol = sprintf(' DATE_FORMAT(fi.value, "%s") ', $date_valformat);
				$textcol  = sprintf(' DATE_FORMAT(fi.value, "%s") ', $date_txtformat);
			}
			else
			{
				$valuecol = ' TIMESTAMPDIFF(' . $filter_age_type . ', fi.value, CURDATE())';
				$textcol  = ' TIMESTAMPDIFF(' . $filter_age_type . ', fi.value, CURDATE())';
			}
		}
		elseif ($date_source === 1 || $date_source === 2)
		{
			$_value_col = ($date_source == 1) ? 'i.publish_up' : 'i.publish_down';

			if (!$filter_as_age)
			{
				$valuecol = sprintf(' CASE WHEN %s='.$nullDate_quoted.' THEN '.(!$filter_as_range ? $nullDate_quoted : $db->Quote('')).' ELSE DATE_FORMAT(%s, "%s") END ', $_value_col, $_value_col, !in_array($display_filter_as, array(1, 3)) ? $date_valformat : $date_txtformat);
				$textcol  = sprintf(' CASE WHEN %s='.$nullDate_quoted.' THEN "'.JText::_('FLEXI_NEVER').'" ELSE DATE_FORMAT(%s, "%s") END ', $_value_col, $_value_col, $date_txtformat);
			}
			else
			{
				$valuecol = sprintf(' CASE WHEN %s='.$nullDate_quoted.' THEN '.(!$filter_as_range ? $nullDate_quoted : $db->Quote('')).' ELSE TIMESTAMPDIFF(' . $filter_age_type . ', ' . $_value_col . ', CURDATE()) END ', $_value_col);
				$textcol  = sprintf(' CASE WHEN %s='.$nullDate_quoted.' THEN "'.JText::_('FLEXI_NEVER').'" ELSE TIMESTAMPDIFF(' . $filter_age_type . ', ' . $_value_col . ', CURDATE()) END ', $_value_col);
			}
		}
		else
		{
			return "date_source: ".$date_source." not implemented";
		}

		// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
		// partial SQL clauses
		$filter->filter_valuesselect = ' '.$valuecol.' AS value, '.$textcol.' AS text';

		if ($date_source === 1 || $date_source === 2)
		{
			$filter->filter_valuesfrom = ' FROM #__content AS i ';
			$filter->filter_item_id_col = ' i.id ';
			$filter->filter_valuesjoin   = null; // use default
			$filter->filter_valueswhere  = ' ';  // space to remove default
		}
		elseif ($date_source === 0 || $date_source === 3)
		{
			$filter->filter_valuesfrom   = null;  // use default
			$filter->filter_valuesjoin   = null;  // use default
			$filter->filter_valueswhere  = null;  // use default
		}
		else
		{
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
	public function getFiltered(&$filter, $value, $return_sql = true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$display_filter_as = $filter->parameters->get( 'display_filter_as', 0 );  // Filter Type of Display
		$date_filter_group = $filter->parameters->get('date_filter_group', 'month');
		$filter_as_age     = $filter->parameters->get('filter_as_age', 0);

		$date_parseformat  = '%Y-%m-%d';

		if ($date_filter_group === 'year')
		{
			$date_valformat  = '%Y';
			$filter_age_type = 'YEAR';
		}
		elseif ($date_filter_group === 'month')
		{
			$date_valformat  = '%Y-%m';
			$filter_age_type = 'MONTH';
		}
		else
		{
			$date_valformat  = '%Y-%m-%d';
			$filter_age_type = 'DAY';
		}

		if (in_array($display_filter_as, array(1, 3)))
		{
			$date_valformat   = '%Y-%m-%d';
			$filter_age_type  = 'DAY';
		}

		// Display date 'label' can be different than the (aggregated) date value
		$date_filter_label_format = $filter->parameters->get('date_filter_label_format', '');
		$date_txtformat   = $date_filter_label_format ?: $date_valformat;  // If empty then same as value
		$date_parseformat = in_array($display_filter_as, array(1, 3)) && $date_filter_label_format
			? $date_filter_label_format
			: '%Y-%m-%d';  // Otherwise FULL date, PARSING MAY BE SKIPPED later by checking if equal to this

		$date_source = (int) $filter->parameters->get('date_source', 0);

		if ($date_source === 0 || $date_source === 3)
		{
			if (in_array($display_filter_as, array(1, 3)))
			{
				// DO NOT do any date aggregation (as year or as month) for date picker
				$filter->filter_colname = sprintf(' rel.value ');
			}
			else
			{
				$filter->filter_colname = !$filter_as_age
					? sprintf(' DATE_FORMAT(rel.value, "%s") ', $date_valformat)
					: ' TIMESTAMPDIFF(' . $filter_age_type . ', rel.value, CURDATE())';
			}
			$filter->filter_valuesjoin = null;   // use default query
		}
		elseif ($date_source === 1 || $date_source === 2)
		{
			$_value_col = ($date_source == 1) ? 'c.publish_up' : 'c.publish_down';

			if (in_array($display_filter_as, array(1, 3)))
			{
				// DO NOT do any date aggregation (as year or as month) for date picker
				$filter->filter_colname = sprintf(' %s ', $_value_col);
			}
			else
			{
				$filter->filter_colname = !$filter_as_age
					? sprintf(' DATE_FORMAT(%s, "%s") ', $_value_col, $date_valformat)
					: ' TIMESTAMPDIFF(' . $filter_age_type . ', ' . $_value_col . ', CURDATE())';
			}

			$filter->filter_valuesjoin = ' '; // a space to prevent using default query and instead use content table
		}
		else
		{
			JFactory::getApplication()->enqueueMessage( __FUNCTION__." for field: '".$filter->label.", date_source: ".$date_source." not implemented" , 'notice' );
		}

		// Format of given values must be same as format of the value-column, for filter as age  posted filter value is already a number
		if (in_array($display_filter_as, array(1, 3)))
		{
				// DO NOT do any date aggregation (as year or as month) for date picker
				// Only parse custom date format
			$filter->filter_valueformat = sprintf(' STR_TO_DATE(__filtervalue__, "%s") ', $date_parseformat);
		}
		else
		{
			/**
			 * Typically the date values are full date because to only-year dates we have appended '-1-1'
			 * and to only-year-month dates we have appended '-1', so $date_parseformat ... must be '%Y-%m-%d'  (a FULL date)
			 */
			if ($filter_as_age)
			{
				$filter->filter_valueformat = null;
			}
			else
			{
				$filter->filter_valueformat = $date_parseformat != '%Y-%m-%d'
					? sprintf(' DATE_FORMAT(STR_TO_DATE(__filtervalue__, "%s"), "%s") ', $date_parseformat, $date_valformat)
					: sprintf(' DATE_FORMAT(__filtervalue__, "%s") ', $date_valformat);
			}
		}

		return FlexicontentFields::getFiltered($filter, $value, $return_sql);
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	public function getFilteredSearch(&$filter, $value, $return_sql = true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}



	// ***
	// *** SEARCH INDEX METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	public function onIndexAdvSearch(&$field, &$post, &$item)
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
	public function onIndexSearch(&$field, &$post, &$item)
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


	/**
	 * Method to verify current value(s) according to field configuration, and return fixed values
	 */
	function onVerifyValues($field, $values)
	{
		// Indicate to caller that verification is supported by this field
		if (!$field)
		{
			return true;
		}

		$db = JFactory::getDbo();

		foreach ($values as $v)
		{
			/**
			 * Try to parse the date according to custom date format (for item form). Do not change if no match
			 */
			$date_string = $this->_customFormat_to_sqlFormat($field, $v->value);

			/**
			 * Verify that we have a valid date !! Try to parse the date.
			 * But use 'UTC' timezone !! we are not in item form. We should not shift the dates !!
			 */
			try {
				/**
				 * Use UTC timezone, aka do not do any shifting of dates during fixing !!
				 * This is not an edit or an import operation
				 */
				$dateObj = new JDate($date_string, 'UTC');
				$date_string = $dateObj->toSql();

				// We produced a different date string, update the database !!
				if ($date_string != $v->value)
				{
					$query = $db->getQuery(true)
						->update('#__flexicontent_fields_item_relations')
						->set($db->qn('value') . ' = ' . $db->Quote($date_string))
						->set($db->qn('value_integer') . ' = CAST(' . $db->Quote($date_string) . ' AS SIGNED)')
						->set($db->qn('value_decimal') . ' = CAST(' . $db->Quote($date_string) . ' AS DECIMAL(65,15))')
						->set($db->qn('value_datetime') . ' = CAST(' . $db->Quote($date_string) . ' AS DATETIME)')
						->where('item_id = ' . (int) $v->item_id .
							' AND field_id = ' . (int) $v->field_id .
							' AND valueorder = ' . (int) $v->valueorder .
							' AND suborder = ' . (int) $v->suborder
						);
					$db->setQuery($query)->execute();
				}
			}
			catch ( Exception $e ) {
			}
		}
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	// Method to convert date to SQL format for storing in DB
	private function _customFormat_to_sqlFormat(&$field, $value)
	{
		// Get date format
		$dateformat = $field->parameters->get('date_format_form', '%Y-%m-%d');
		$dateformat = str_replace('%', '', JText::_($dateformat));

		// Get timeformat
		$date_allowtime = (int) $field->parameters->get( 'date_allowtime', 1 ) ;
		$timeFormatsMap = array('0'=>'', '1'=>' H:i', '2'=>'');
		$timeformat     = $timeFormatsMap[$date_allowtime];

		$dt =        DateTime::createFromFormat($dt_format = $dateformat . $timeformat, $value);
		$dt = $dt ?: DateTime::createFromFormat($dt_format = $dateformat, $value);
		$dt = $dt ?: DateTime::createFromFormat($dt_format = $dateformat . ' H:i', $value);
		$dt = $dt ?: DateTime::createFromFormat($dt_format = 'Y-m-d H:i', $value);
		$dt = $dt ?: DateTime::createFromFormat($dt_format = 'Y-m-d', $value);

		if ($dt)
		{
			$value = $dt->format('Y-m-d' . $timeformat);
		}

		//JFactory::getApplication()->enqueueMessage('dt_format: ' . $dt_format . ' - input: ' . $value . ' - toSql(): ' .  $v, 'notice');

		return $value;
	}

	// Method to prepare for indexing, either preparing SQL query (if post is null) or formating/preparing given $post data for usage bu index
	private function _prepareForSearchIndexing(&$field, &$item, &$post, $for_advsearch=0)
	{
		$_s = $for_advsearch ? '_s' : '';

		$date_source       = (int) $field->parameters->get('date_source', 0);

		$display_filter_as = $field->parameters->get( 'display_filter_as'.$_s, 0 );  // Filter Type of Display
		$date_filter_group = $field->parameters->get('date_filter_group'.$_s, 'month');
		$filter_as_age     = $field->parameters->get('filter_as_age'.$_s, 0);

		if ($date_filter_group === 'year')
		{
			$date_valformat  = '%Y';
			$filter_age_type = 'YEAR';
		}
		elseif ($date_filter_group === 'month')
		{
			$date_valformat  = '%Y-%m';
			$filter_age_type = 'MONTH';
		}
		else
		{
			$date_valformat  = '%Y-%m-%d';
			$filter_age_type = 'DAY';
		}

		if (in_array($display_filter_as, array(1, 3)))
		{
			$date_valformat = '%Y-%m-%d';
			$filter_age_type = 'DAY';
		}

		// Display date 'label' can be different than the (aggregated) date value
		$date_filter_label_format = $field->parameters->get('date_filter_label_format'.$_s, '');
		$date_txtformat = $date_filter_label_format ? $date_filter_label_format : $date_valformat;  // If empty then same as value

		if ($post === null)
		{
			// null indicates that indexer is running, values is set to NULL which means retrieve data from the DB
			$_value_col = !$date_source || $date_source==3 ? 'fi.value' : ($date_source == 1 ? 'i.publish_up' : 'i.publish_down');

			if (!$filter_as_age)
			{
				$valuecol = sprintf(' DATE_FORMAT('.$_value_col.', "%s") ', !in_array($display_filter_as, array(1, 3)) ? $date_valformat : $date_txtformat);
				$textcol  = sprintf(' DATE_FORMAT('.$_value_col.', "%s") ', $date_txtformat);
			}
			else
			{
				$valuecol = sprintf(' TIMESTAMPDIFF(' . $filter_age_type . ',  DATE_FORMAT('.$_value_col.', "%s") , CURDATE()) ', !in_array($display_filter_as, array(1, 3)) ? $date_valformat : $date_txtformat);
				$textcol  = sprintf(' TIMESTAMPDIFF(' . $filter_age_type . ',  DATE_FORMAT('.$_value_col.', "%s") , CURDATE()) ', $date_txtformat);
			}

			$field->field_valuesselect = ' '.$valuecol.' AS value_id, '.$textcol.' AS value';

			if ($date_source === 1 || $date_source === 2)
			{
				$field->field_valuesfrom = ' FROM #__content AS i ';
				$field->field_item_id_col = ' i.id ';
				$field->field_valueswhere = ' ';  // a space to remove default
			}

			$field->field_groupby = ' GROUP BY '.$valuecol;
			$values = null;
		}
		else
		{
			$values = array();
			$db = JFactory::getDbo();

			if ($date_source === 1 || $date_source === 2)
			{
				$post = array($date_source == 1 ? $item->publish_up : $item->publish_down);
			}

			if ($post)
			{
				foreach ($post as $v)
				{
					$valuecol = sprintf(' DATE_FORMAT("%s", "%s") ', $v, $date_valformat);
					$textcol = sprintf(' DATE_FORMAT("%s", "%s") ', $v, $date_txtformat);
					$query = 'SELECT  '.$valuecol.' AS value_id, '.$textcol.' AS value';
					$db->setQuery($query);
					$value = $db->loadObjectList();
					$values[$value[0]->value_id] = $value[0]->value;
				}
			}
		}

		return $values;
	}

}
