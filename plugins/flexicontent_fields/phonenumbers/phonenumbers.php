<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright � 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsPhonenumbers extends FCField
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

		// Initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );

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

		// Optional form elements
		$use_label = (int) $field->parameters->get( 'use_label', 1 ) ;
		$use_cc    = (int) $field->parameters->get( 'use_cc', 1 ) ;
		$use_phone = (int) $field->parameters->get( 'use_phone', 3 ) ;
		$show_part_labels = (int) $field->parameters->get( 'show_part_labels', 0 ) ;

		// Input field display size & max characters
		if ($use_label) {
			$label_size = (int) $field->parameters->get( 'label_size', 48 ) ;
			$label_maxlength = (int) $field->parameters->get( 'label_maxlength', 0 ) ;  // client/server side enforced
		}
		if ($use_cc) {
			$cc_size    = (int) $field->parameters->get( 'cc_size', 6 ) ;
			$cc_maxlength    = (int) $field->parameters->get( 'cc_maxlength', 0 ) ;     // client/server side enforced
		}
		$phone1_size = (int) $field->parameters->get( 'phone1_size', 12 ) ;
		$phone1_maxlength = (int) $field->parameters->get( 'phone1_maxlength', 0 ) ;  // client/server side enforced
		$phone2_size = (int) $field->parameters->get( 'phone2_size', 12 ) ;
		$phone2_maxlength = (int) $field->parameters->get( 'phone2_maxlength', 0 ) ;  // client/server side enforced
		$phone3_size = (int) $field->parameters->get( 'phone3_size', 12 ) ;
		$phone3_maxlength = (int) $field->parameters->get( 'phone3_maxlength', 0 ) ;  // client/server side enforced
		$allow_letters = (int) $field->parameters->get( 'allow_letters', 0 ) ? '' : ' validate-numeric';  // class for enabling javascript validation

		// Create extra HTML TAG parameters for the form field(s)
		if ($use_label) $label_attribs = ' size="'.$label_size.'"'. ($label_maxlength ? ' maxlength="'.$label_maxlength.'" ' : '');
		if ($use_cc)    $cc_attribs    = ' size="'.$cc_size.'"'.    ($cc_maxlength ? ' maxlength="'.$cc_maxlength.'" ' : '');
		$phone1_attribs = ' size="'.$phone1_size.'"'. ($phone1_maxlength ? ' maxlength="'.$phone1_maxlength.'" ' : '');
		$phone2_attribs = ' size="'.$phone2_size.'"'. ($phone2_maxlength ? ' maxlength="'.$phone2_maxlength.'" ' : '');
		$phone3_attribs = ' size="'.$phone3_size.'"'. ($phone3_maxlength ? ' maxlength="'.$phone3_maxlength.'" ' : '');


		// Initialise property with default value
		if (!$field->value || (count($field->value) === 1 && reset($field->value) === null))
		{
			$field->value = array();
			$field->value[0] = array('label'=>'', 'cc'=>'', 'phone1'=>'', 'phone2'=>'', 'phone3'=>'');
			$field->value[0] = serialize($field->value[0]);
		}



		// CET ALLOWED countries, with special check for single country
		$ac_country_default      = $field->parameters->get('ac_country_default', '');
		$ac_country_allowed_list = $field->parameters->get('ac_country_allowed_list', '');
		$ac_country_allowed_list = $ac_country_default . ',' . $ac_country_allowed_list;
		$ac_country_allowed_list = array_unique(FLEXIUtilities::paramToArray($ac_country_allowed_list, "/[\s]*,[\s]*/", false, true));
		$single_country          = count($ac_country_allowed_list)==1 && $ac_country_default ? $ac_country_default : false;


		// CREATE COUNTRY OPTIONS
		$_list = count($ac_country_allowed_list) ? array_flip($ac_country_allowed_list) : array();

		$allowed_country_names = array();
		$allowed_countries     = array('' => JText::_('FLEXI_SELECT'));

		foreach($_list as $country_code => $k)
		{
			$country_op          = new stdClass;
			$allowed_countries[] = $country_op;
			$country_op->value   = $country_code;
			$country_op->text    = JText::_('PLG_FC_PHONENUMBERS_CC_' . $country_code);

			if (count($ac_country_allowed_list))
			{
				$allowed_country_names[] = $country_op->text;
			}
		}
		//echo print_r($allowed_country_names, true);

		$countries_attribs = ''
			. ($single_country ? ' disabled="disabled" readonly="readonly"' : '')
			. ' onchange="fcfield_addrint.toggle_USA_state(this);" ';



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
				theInput = newField.find('input.phonelabel').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[label]');
				theInput.attr('id', element_id + '_label');
				newField.find('.phonelabel-lbl').first().attr('for', element_id + '_label');

				theSelect = newField.find('select.phonecc').first();
				theSelect.val('');
				theSelect.attr('name', fname_pfx + '[cc]');
				theSelect.attr('id', element_id + '_cc');
				//newField.find('.phonecc-lbl').first().attr('for', element_id + '_cc');

				theInput = newField.find('input.phonenum1').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[phone1]');
				theInput.attr('id', element_id + '_phone1');
				newField.find('.phonenum1-lbl').first().attr('for', element_id + '_phone1');

				theInput = newField.find('input.phonenum2').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[phone2]');
				theInput.attr('id', element_id + '_phone2');
				newField.find('.phonenum2-lbl').first().attr('for', element_id + '_phone2');

				theInput = newField.find('input.phonenum3').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[phone3]');
				theInput.attr('id', element_id + '_phone3');
				newField.find('.phonenum3-lbl').first().attr('for', element_id + '_phone3');
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


		// Add field's CSS / JS
		if ($multiple) $js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
		";
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);

		if ($show_part_labels)
		{
			$part1_lbl = $use_phone > 1 ? 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_AREA_CODE' : '';
			$part2_lbl = $use_phone == 2 ? 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_LOCAL_NUM' : ($use_phone == 3 ? 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_PART_2' : '' );
			$part3_lbl = $use_phone >  2 ? 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_PART_3' : '';
		}

		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();
		$n = 0;
		//if ($use_ingroup) {print_r($field->value);}
		foreach ($field->value as $value)
		{
			// Compatibility for non-serialized values (e.g. reload user input after form validation error) or for NULL values in a field group
			if ( !is_array($value) )
			{
				$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
				$value = $array ?: array(
					'label' => '', 'cc' => '', 'phone1'=> $value , 'phone2' => '', 'phone3' => ''
				);
			}
			if ( empty($value['label']) && empty($value['cc']) && empty($value['phone1']) && empty($value['phone2']) && empty($value['phone3']) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group

			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;

			$phonelabel = (!$use_label ? '' : '
				<tr><td class="key">' .JText::_( 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_LABEL' ). '</td><td>
					<input class="fcfield_textval phonelabel" name="'.$fieldname_n.'[label]" id="'.$elementid_n.'_label" type="text" value="'.@$value['label'].'" '.$label_attribs.' />
				</td></tr>');

			$phonecc = (!$use_cc ? '' : '
				<tr><td class="key">' .JText::_( 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_COUNTRY_CODE' ). '</td><td>
					' . JHtml::_('select.genericlist', $allowed_countries, $fieldname_n.'[cc]', $countries_attribs . ' class="use_select2_lib phonecc"', 'value', 'text', ($value['cc'] ? $value['cc'] : $ac_country_default), $elementid_n.'_cc') . '
				</td></tr>');

			$phone = '
				<tr><td class="key">' .JText::_( 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_NUMBER' ). '</td><td>
					<div class="nowrap_box">
						'.($show_part_labels && $part1_lbl ? '<label class="label phonenum1-lbl" for="'.$elementid_n.'_phone1" >'.JText::_($part1_lbl).'</label><br/>' : '').'
						<input class="phonenum1 fcfield_textval inlineval' . $allow_letters . $required_class . '" name="'.$fieldname_n.'[phone1]" id="'.$elementid_n.'_phone1" type="text" value="'.htmlspecialchars($value['phone1'], ENT_COMPAT, 'UTF-8').'" '.$phone1_attribs.' />
						'.($use_phone > 1 ? '-' : '').'
					</div>

					'.($use_phone >= 2 ? '
					<div class="nowrap_box">
						'.($show_part_labels && $part2_lbl ? '<label class="label phonenum2-lbl" for="'.$elementid_n.'_phone2" >'.JText::_($part2_lbl).'</label><br/>' : '').'
						<input class="phonenum2 fcfield_textval inlineval' . $allow_letters . $required_class . '" name="'.$fieldname_n.'[phone2]" id="'.$elementid_n.'_phone2" type="text" value="'.htmlspecialchars($value['phone2'], ENT_COMPAT, 'UTF-8').'" '.$phone2_attribs.' />
						'.($use_phone > 2 ? '-' : '').'
					</div>' : '').'

					'.($use_phone > 2 ? '
					<div class="nowrap_box">
						'.($show_part_labels && $part3_lbl ? '<label class="label phonenum3-lbl" for="'.$elementid_n.'_phone3" >'.JText::_($part3_lbl).'</label><br/>' : '').'
						<input class="phonenum3 fcfield_textval inlineval' . $allow_letters . $required_class . '" name="'.$fieldname_n.'[phone3]" id="'.$elementid_n.'_phone3" type="text" value="'.htmlspecialchars($value['phone3'], ENT_COMPAT, 'UTF-8').'" '.$phone3_attribs.' />
					</div>' : '').'
				</td></tr>';

			$field->html[] = '
				'.(!$add_ctrl_btns ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				').'
				'.($use_ingroup ? '' : '<div class="fcclear"></div>').'
				<table class="fc-form-tbl fcfullwidth fcinner fc-phonenumbers-field-tbl"><tbody>
				'.$phonelabel.'
				'.$phonecc.'
				'.$phone.'
				</tbody></table>
				';

			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}

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


		/**
		 * Get field values
		 */

		$values = $values ? $values : $field->value;
		$values = !is_array($values) ? array($values) : $values;     // make sure values is an array
		$isempty = !count($values) || !strlen($values[0]);           // detect empty value

		if($isempty) return;

		// Optional display
		$display_phone_label		= $field->parameters->get( 'display_phone_label', 1 ) ;
		$display_country_code		= $field->parameters->get( 'display_country_code', 1 ) ;
		$display_area_code		= $field->parameters->get( 'display_area_code', 1 ) ;
		$add_tel_link		= $field->parameters->get( 'add_tel_link', 0 ) ;

		// Property Separators
		$label_prefix = $field->parameters->get( 'label_prefix', '' ) ;
		$label_suffix = $field->parameters->get( 'label_suffix', '' ) ;
		$country_code_prefix = $field->parameters->get( 'country_code_prefix', '' ) ;
		$separator_cc_phone1 = $field->parameters->get( 'separator_cc_phone1', '' ) ;
		$separator_phone1_phone2 = $field->parameters->get( 'separator_phone1_phone2', '' ) ;
		$separator_phone2_phone3 = $field->parameters->get( 'separator_phone2_phone3', '' ) ;

		// Open/close tags (every value)
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, JText::_($field->parameters->get( 'opentag', '' )), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, JText::_($field->parameters->get( 'closetag', '' )), 'closetag' );
		// Prefix/suffix (value list)
		$field_prefix		= FlexicontentFields::replaceFieldValue( $field, $item, JText::_($field->parameters->get( 'field_prefix', '' )), 'field_prefix' );
		$field_suffix		= FlexicontentFields::replaceFieldValue( $field, $item, JText::_($field->parameters->get( 'field_suffix', '' )), 'field_suffix' );


		// initialise property
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			// Compatibility for non-serialized values or for NULL values in a field group
			if ( !is_array($value) )
			{
				$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
				$value = $array ?: array(
					'label' => '', 'cc' => '', 'phone1'=> $value , 'phone2' => '', 'phone3' => ''
				);
			}

			// Skip empty value but add empty placeholder if inside fieldgroup
			if ( empty($value['phone1']) && empty($value['phone2']) && empty($value['phone3']) )
			{
				if ($is_ingroup)
				{
					$field->{$prop}[$n++] = '';
				}
				continue;
			}

			$html = $opentag
					.($display_phone_label  ? $label_prefix.$value['label'] . $label_suffix : '');

			if ($add_tel_link) {
				$html .= '<a href="tel:'
						. ($display_country_code ? '+' . $value['cc'] : '')
						. ($display_area_code    ? $value['phone1'] : '')
						. $value['phone2'] . $value['phone3']
						. '">';
			}
			$html .= ($display_country_code ? $country_code_prefix . $value['cc'] : '')
					. ($display_country_code || $display_area_code ? $separator_cc_phone1 : '')
					. ($display_area_code ? $value['phone1'] : '')
					. $separator_phone1_phone2 . $value['phone2'] . $separator_phone2_phone3 . $value['phone3']
					. ($add_tel_link ? '</a>' : '')
					. $closetag;

			$field->{$prop}[] = $html;
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}

		if (!$is_ingroup)  // do not convert the array to string if field is in a group
		{
			// Apply separator and open/close tags
			if(count($field->{$prop})) {
				$field->{$prop}  = implode('', $field->{$prop});
				$field->{$prop}  = $field_prefix . $field->{$prop} . $field_suffix;
			} else {
				$field->{$prop} = '';
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

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;

		// Get configuration
		$app  = JFactory::getApplication();
		$is_importcsv = $app->input->get('task', '', 'cmd') == 'importcsv';
		$label_maxlength = (int) $field->parameters->get( 'label_maxlength', 0 ) ;  // client/server side enforced
		$cc_maxlength    = (int) $field->parameters->get( 'cc_maxlength', 0 ) ;     // client/server side enforced
		$phone1_maxlength = (int) $field->parameters->get( 'phone1_maxlength', 0 ) ;  // client/server side enforced
		$phone2_maxlength = (int) $field->parameters->get( 'phone2_maxlength', 0 ) ;  // client/server side enforced
		$phone3_maxlength = (int) $field->parameters->get( 'phone3_maxlength', 0 ) ;  // client/server side enforced
		$allow_letters = (int) $field->parameters->get( 'allow_letters', 0 ); // allow letters during validation


		// ***
		// *** Reformat the posted data
		// ***

		// Make sure posted data is an array
		$post = !is_array($post) ? array($post) : $post;

		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// Support for serialized user data, e.g. basic CSV import / export. (Safety concern: objects code will abort unserialization!)
			if ( /*$is_importcsv &&*/ !is_array($v) && $v )
			{
				$array = $this->unserialize_array($v, $force_array=false, $force_value=false);
				$v = $array ?: array(
					'label' => '', 'cc' => '', 'phone1'=> $v , 'phone2' => '', 'phone3' => ''
				);
			}

			// ***
			// *** Validate phone number, skipping phone number that are empty after validation
			// ***

			$regex = $allow_letters ? '/[^0-9A-Z]/' : '/[^0-9]/';	// allow letters?

			// force string to uppercase, remove any forbiden characters
			$v['phone1'] = preg_replace($regex, '', strtoupper($v['phone1']));
			$v['phone2'] = preg_replace($regex, '', strtoupper($v['phone2']));
			$v['phone3'] = preg_replace($regex, '', strtoupper($v['phone3']));

			// enforce max length
			$newpost[$new]['phone1'] = $phone1_maxlength ? $v['phone1'] : substr($v['phone1'], 0, $phone1_maxlength);
			$newpost[$new]['phone2'] = $phone2_maxlength ? $v['phone2'] : substr($v['phone2'], 0, $phone2_maxlength);
			$newpost[$new]['phone3'] = $phone3_maxlength ? $v['phone3'] : substr($v['phone3'], 0, $phone3_maxlength);

			if (!strlen($v['phone1']) && !strlen($v['phone2']) && !strlen($v['phone3']) && !$use_ingroup ) continue;  // Skip empty values if not in field group

			// Validate other value properties
			$newpost[$new]['label']  = flexicontent_html::dataFilter(@$v['label'],  $label_maxlength, 'STRING', 0);
			$newpost[$new]['cc']     = flexicontent_html::dataFilter(@$v['cc'],     $cc_maxlength,    'STRING', 0);

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
	/*function onAdvSearchDisplayFilter(&$filter, $value = '', $formName = 'searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$this->onDisplayFilter($filter, $value, $formName);
	}*/


	// Method to display a category filter for the category view
	/*function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$filter->html = '';
	}*/



	// ***
	// *** SEARCH INDEX METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	/*function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
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
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}*/


	// Method to create basic search index (added as the property field->search)
	/*function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;

		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}*/

}
