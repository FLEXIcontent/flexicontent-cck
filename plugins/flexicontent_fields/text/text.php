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

class plgFlexicontent_fieldsText extends FCField
{
	static $field_types = array('text', 'textselect');
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

		// Check if using 'auto_value_code', clear 'auto_value', if function not set
		$auto_value = (int) $field->parameters->get('auto_value', 0);
		if ($auto_value === 2)
		{
			$auto_value_code = $field->parameters->get('auto_value_code', '');
			$auto_value_code = preg_replace('/^<\?php(.*)(\?>)?$/s', '$1', $auto_value_code);
		}
		$auto_value = $auto_value === 2 && !$auto_value_code ? 0 : $auto_value;

		// Initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );

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
		$add_position = (int) $field->parameters->get('add_position', 3);

		// If we are multi-value and not inside fieldgroup then add the control buttons (move, delete, add before/after)
		$add_ctrl_btns = !$use_ingroup && $multiple;


		// ***
		// *** Value handling
		// ***

		// Default value
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($item->version == 0 || $value_usage > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_value = strlen($default_value) ? JText::_($default_value) : '';
		$default_values= array($default_value);

		// Input field display size & max characters
		$size       = (int) $field->parameters->get( 'size', 30 ) ;
		$maxlength  = (int) $field->parameters->get( 'maxlength', 0 ) ;   // client/server side enforced
		$display_label_form = (int) $field->parameters->get( 'display_label_form', 1 ) ;
		$placeholder= $display_label_form==-1 ? $field->label : JText::_($field->parameters->get( 'placeholder', '' )) ;

		// create extra HTML TAG parameters for the form field
		$attribs = $field->parameters->get( 'extra_attributes', '' ) ;
		if ($maxlength) $attribs .= ' maxlength="'.$maxlength.'" ';
		if ($auto_value) $attribs .= ' readonly="readonly" ';

		// Attribute for default value(s)
		if (!empty($default_values))
		{
			$attribs .= ' data-defvals="'.htmlspecialchars( implode('|||', $default_values), ENT_COMPAT, 'UTF-8' ).'" ';
		}

		$attribs .= ' size="'.$size.'" ';

		// Custom HTML placed before / after form fields
		$pretext  = $field->parameters->get( 'pretext_form', '' ) ;
		$posttext = $field->parameters->get( 'posttext_form', '' ) ;


		// ***
	  // *** Create validation mask
		// ***

		$inputmask	= $field->parameters->get( 'inputmask', false ) ;
		$custommask = $field->parameters->get( 'custommask', false ) ;
		$regexmask  = $field->parameters->get( 'regexmask', false ) ;

		static $inputmask_added = false;
	  if ($inputmask && !$inputmask_added)
		{
			$inputmask_added = true;
			flexicontent_html::loadFramework('inputmask');
		}

		// Initialise property with default value
		if ( !$field->value || (count($field->value)==1 && $field->value[0] === null) )
		{
			$field->value = $default_values;
		}

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		// Name Safe Element ID
		$elementid_ns = str_replace('-', '_', $elementid);

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
			$js .= "
				// Update the new (text) input field
				var theInput = newField.find('input.fcfield_textval').first();
				var theInput_dv = theInput.attr('data-defvals');
				(theInput_dv && theInput_dv.length) ?
					theInput.val( theInput.attr('data-defvals') ) :
					theInput.val(".json_encode($default_value).") ;
				theInput.attr('name', '".$fieldname."['+uniqueRowNum".$field->id."+']');
				theInput.attr('id', '".$elementid."_'+uniqueRowNum".$field->id.");

				// Update inputmask
				var has_inputmask = newField.find('input.has_inputmask').length != 0;
				if (has_inputmask)  newField.find('input.has_inputmask').inputmask();

				// Destroy any select2 elements
				var sel2_elements = newField.find('div.select2-container');
				if (sel2_elements.length)
				{
					sel2_elements.remove();
					newField.find('select.use_select2_lib').select2('destroy').show();
				}
				";

			// Update select for textselect if it exists
			if ($field->field_type=='textselect')
				$js .= "
				newField.parent().find('select.fcfield_textselval').val('');
				";

			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();

				// Attach form validation on new element
				fc_validationAttach(newField);

				// Re-init any select2 elements
				fc_attachSelect2(newField);
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


		// Drop-Down select for textselect field type
		if ($field->field_type === 'textselect')
		{
			static $select2_added = false;
		  if ( !$select2_added )
		  {
				$select2_added = true;
				flexicontent_html::loadFramework('select2');
			}

			$sel_classes  = ' fcfield_textselval use_select2_lib ';
		  $sel_onchange = " jQuery(this).parent().find('input.fcfield_textval').val(jQuery(this).val()).trigger('blur'); jQuery(this).select2('val', ''); ";
			$sel_attribs  = ' class="'.$sel_classes.'" onchange="'.$sel_onchange.'"';

			$sel_fieldname = 'custom['.$field->name.'_sel][]';
			$sel_options = plgFlexicontent_fieldsText::buildSelectOptions($field, $item);
			$select_field = JHtml::_('select.genericlist', $sel_options, $sel_fieldname, $sel_attribs, 'value', 'text', array());
			$select_field_placement = (int) $field->parameters->get('select_field_placement', 0);
		}

		else
		{
			$select_field = '';
			$select_field_placement = -1;
		}


		// Added field's custom CSS / JS
		if ($multiple) $js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
		";
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);

		$classes  = 'fcfield_textval' . ($required ? ' required' : '');

		// Set field to 'Automatic' on successful validation'
		if ($auto_value)
		{
			$classes = ' fcfield_auto_value ';
		}

		// Create attributes for JS inputmask validation
		$validate_mask = '';
		switch ($inputmask) {
		case '__regex__':
			if ($regexmask) {
				$validate_mask = " data-inputmask-regex=\"".$regexmask."\" ";
				$classes .= ' inputmask-regex';
			}
			break;
		case '__custom__':
			if ($custommask) {
				$validate_mask = " data-inputmask=\"".$custommask."\" ";
				$classes .= ' has_inputmask';
			}
			break;
		default:
			if ($inputmask){
				$validate_mask = " data-inputmask=\" 'alias': '".$inputmask."' \" ";
				$classes .= ' has_inputmask';
			}
		}

		// Add placeholder tag parameter if not using validation mask, (if using vaildation mask then placeholder should be added a validation mask property)
		$attribs .= $placeholder ? ' placeholder="'.htmlspecialchars( $placeholder, ENT_COMPAT, 'UTF-8' ).'" ' : '';


		// ***
		// *** Create field's HTML display for item form
		// ***

		$field->html = array();
		$n = 0;
		//if ($use_ingroup) {print_r($field->value);}
		foreach ($field->value as $value)
		{
			if ( !strlen($value) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group

			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;

			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			$text_field = $pretext.
				'<input value="'.htmlspecialchars( $value, ENT_COMPAT, 'UTF-8' ).'" '
					.$validate_mask.' id="'.$elementid_n.'" name="'.$fieldname_n.'" class="'.$classes.'" type="text" '.$attribs.'
				/>'
				.$posttext;

			$field->html[] = '
				<div class="' . $input_grp_class . ' fc-xpended">
					'.($auto_value || $select_field_placement !== 0 ? '' : $select_field).'
					'.$text_field.'
					'.($auto_value || $select_field_placement !== 1 ? '' : $select_field).'
					'.($auto_value ? '<span class="fc-mssg-inline fc-info fc-nobgimage">' . JText::_('FLEXI_AUTO') . '</span>' : '').'
				</div>
				'.(!$add_ctrl_btns || $auto_value ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				');

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
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
	}


	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = JText::_($field->label);

		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;


		// ***
		// *** One time initialization
		// ***

		static $initialized = null;
		static $app, $document, $option, $format, $realview;
		static $itemViewId, $isItemsManager, $isHtmlViewFE;

		if ($initialized === null)
		{
			$initialized = 1;

			$app       = JFactory::getApplication();
			$document  = JFactory::getDocument();
			$option    = $app->input->get('option', '', 'cmd');
			$format    = $app->input->get('format', 'html', 'cmd');
			$realview  = $app->input->get('view', '', 'cmd');

			$itemViewId     = $realview === 'item' && $option === 'com_flexicontent' ? $app->input->get('id', 0, 'int') : 0;
			$isItemsManager = $app->isAdmin() && $realview === 'items' && $option === 'com_flexicontent';
			$isHtmlViewFE   = $format === 'html' && $app->isSite();

		}

		// Current view variable
		$view = $app->input->get('flexi_callview', ($realview ?: 'item'), 'cmd');

		// The current view is a full item view of the item
		$isMatchedItemView = $itemViewId === (int) $item->id;

		// Value handling parameters
		$lang_filter_values = $field->parameters->get( 'lang_filter_values', 0);
		$clean_output = $field->parameters->get('clean_output', 0);
		$encode_output = $field->parameters->get('encode_output', 0);
		$format_output = (int) $field->parameters->get('format_output', 0);

		if ($format_output > 0)  // 1: decimal, 2: integer
		{
			$decimal_digits_displayed = $format_output === 2 ? 0 : (int)$field->parameters->get('decimal_digits_displayed', 2);
			$decimal_digits_sep    = $field->parameters->get('decimal_digits_sep', '.');
			$decimal_thousands_sep = $field->parameters->get('decimal_thousands_sep', ',');
			$output_prefix = JText::_($field->parameters->get('output_prefix', ''));
			$output_suffix = JText::_($field->parameters->get('output_suffix', ''));
		}
		else if ($format_output === -1)
		{
			$output_custom_func = $field->parameters->get('output_custom_func', '');
			$format_output = !$output_custom_func ? 0 : $format_output;
		}


		// ***
		// *** Default value
		// ***

		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($value_usage == 2) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_value = strlen($default_value) ? JText::_($default_value) : '';

		// Get field values
		$values = $values ? $values : $field->value;

		// Check for no values and no default value, and return empty display
		if ( empty($values) )
		{
			if (!strlen($default_value))
			{
				$field->{$prop} = $is_ingroup ? array() : '';
				return;
			}
			$values = array($default_value);
		}


		// ******************************************
		// Language filter, clean output, encode HTML
		// ******************************************

		if ($clean_output)
		{
			$ifilter = $clean_output == 1 ? JFilterInput::getInstance(null, null, 1, 1) : JFilterInput::getInstance();
		}
		if ($lang_filter_values || $clean_output || $encode_output || $format_output)
		{
			// (* BECAUSE OF THIS, the value display loop expects unserialized values)
			foreach ($values as &$value)
			{
				if ( !strlen($value) ) continue;  // skip further actions

				if ($format_output > 0)  // 1: decimal, 2: integer
				{
					$value = @ number_format($value, $decimal_digits_displayed, $decimal_digits_sep, $decimal_thousands_sep);
					$value = $value === NULL ? 0 : $value;
					$value = $output_prefix .$value. $output_suffix;
				}
				else if ($format_output === -1)
				{
					$value = eval( "\$value= \"{$value}\";" . $output_custom_func);
				}

				if ($lang_filter_values) {
					$value = JText::_($value);
				}
				if ($clean_output) {
					$value = $ifilter->clean($value, 'string');
				}
				if ($encode_output) {
					$value = htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
				}
			}
			unset($value); // Unset this or you are looking for trouble !!!, because it is a reference and reusing it will overwrite the pointed variable !!!
		}


		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );

		// Microdata (classify the field values for search engines)
		$itemprop    = $field->parameters->get('microdata_itemprop');

		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }

		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br class="fcclear" />';
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

		// Cleaner output for CSV export
		if ($prop === 'csv_export')
		{
			$separatorf = ', ';
			$itemprop = false;
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
			}
		}


		/*
		 * Add OGP tags
		 */
		if ($field->parameters->get('useogp', 0) && !empty($field->{$prop}))
		{
			// The current view is frontend view with HTML format and is a full item view of current item
			if ($isHtmlViewFE && $isMatchedItemView)
			{
				$ogpmaxlen = $field->parameters->get('ogpmaxlen', 300);
				$ogpusage  = $field->parameters->get('ogpusage', 0);

				switch ($ogpusage)
				{
					case 1: $usagetype = 'title'; break;
					case 2: $usagetype = 'description'; break;
					default: $usagetype = ''; break;
				}

				if ($usagetype)
				{
					$content_val = !$is_ingroup ? flexicontent_html::striptagsandcut($field->{$prop}, $ogpmaxlen) :
						flexicontent_html::striptagsandcut($opentag.implode($separatorf, $field->{$prop}).$closetag, $ogpmaxlen) ;
					JFactory::getDocument()->addCustomTag('<meta property="og:'.$usagetype.'" content="'.$content_val.'" />');
				}
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

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;

		// Take into consideration client side validation
		$inputmask	= $field->parameters->get( 'inputmask', false ) ;

		// Server side validation
		$required   = (int) $field->parameters->get( 'required', 0 ) ;
		$validation = $field->parameters->get( 'validation', 'HTML' ) ;
		$maxlength  = (int) $field->parameters->get( 'maxlength', 0 ) ;


		// ***
		// *** Reformat the posted data
		// ***

		// Make sure posted data is an array
		$post = !is_array($post) ? array($post) : $post;

		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			$v = trim($post[$n]);

			// Unmasking is done via JS code, but try to redo it, to avoid value loss if unmasking was not done
			if ($inputmask === 'decimal')
			{
				$v = str_replace(',', '', $v);
			}
			elseif ($inputmask === 'decimal_comma')
			{
				$v = str_replace('.', '', $v);
				$v = str_replace(',', '.', $v);
			}
			elseif ($inputmask === 'currency' || $inputmask === 'currency_euro')
			{
				$v = str_replace('$', '', $v);
				$v = str_replace(chr(0xE2).chr(0x82).chr(0xAC), '', $v);
				$v = str_replace(',', '', $v);
			}

			// ***
			// *** Validate data, skipping values that are empty after validation
			// ***

			$post[$n] = $required || strlen($v)
				? flexicontent_html::dataFilter($v, $maxlength, $validation, 0)
				: '';

			// Skip empty value, but if in group increment the value position
			if (!strlen($post[$n]))
			{
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}

			$newpost[$new] = $post[$n];
			$new++;
		}
		$post = $newpost;

		//if ($use_ingroup) JFactory::getApplication()->enqueueMessage( print_r($post, true), 'warning');
	}


	// Method to do extra handling of field's values after all fields have validated their posted data, and are ready to be saved
	// $item->fields['fieldname']->postdata contains values of other fields
	// $item->fields['fieldname']->filedata contains files of other fields (normally this is empty due to using AJAX for file uploading)
	function onAllFieldsPostDataValidated( &$field, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		// Check if using 'auto_value_code', clear 'auto_value', if function not set
		$auto_value = (int) $field->parameters->get('auto_value', 0);
		if ($auto_value === 2)
		{
			$auto_value_code = $field->parameters->get('auto_value_code', '');
			$auto_value_code = preg_replace('/^<\?php(.*)(\?>)?$/s', '$1', $auto_value_code);
		}
		$auto_value = $auto_value === 2 && !$auto_value_code ? 0 : $auto_value;

		if (!$auto_value)
		{
			return;
		}

		// Check for system plugin
		$extfolder = 'system';
		$extname   = 'flexisyspro';
		$className = 'plg'. ucfirst($extfolder).$extname;
		$plgPath = JPATH_SITE . '/plugins/'.$extfolder.'/'.$extname.'/'.$extname.'.php';

		if (!file_exists($plgPath))
		{
			JFactory::getApplication()->enqueueMessage('Automatic field value for field  \'' . $field->label . '\' is only supported by FLEXIcontent PRO version, please disable this feature in field configuration', 'notice');
			return;
		}
		//require_once $plgPath;

		// Create plugin instance
		$dispatcher   = JEventDispatcher::getInstance();
		$plg_db_data  = JPluginHelper::getPlugin($extfolder, $extname);
		$plg = new $className($dispatcher, array('type'=>$extfolder, 'name'=>$extname, 'params'=>$plg_db_data->params));

		// Create automatic value
		$plg->onAllFieldsPostDataValidated($field, $item);
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

		FlexicontentFields::createFilter($filter, $value, $formName);
	}


 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		return FlexicontentFields::getFiltered($filter, $value, $return_sql);
	}


 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

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

		// a. Each of the values of $values array will be added to the advanced search index as searchable text (column value)
		// b. Each of the indexes of $values will be added to the column 'value_id',
		//    and it is meant for fields that we want to be filterable via a drop-down select
		// c. If $values is null then only the column 'value' will be added to the search index after retrieving
		//    the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}


	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;

		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	// Method to build the options of drop-down select for field
	function buildSelectOptions(&$field, &$item)
	{
		// Drop-down select elements depend on 'select_field_mode'
		$select_field_mode = (int) $field->parameters->get('select_field_mode', 0);

		$results_predefined = array();
		$results_existing   = array();

		// Predefined elements, 1: Custom List, 2: Elements via an SQL query
		if ($select_field_mode != 0)
		{
			$field_elements = $field->parameters->get('select_field_elements');

			// Call function that parses or retrieves element via sql
			$field->parameters->set('sql_mode', $select_field_mode === 2 || $select_field_mode === -2);
			$field->parameters->set('field_elements', $field_elements);
			$results_predefined = FlexicontentFields::indexedField_getElements($field, $item);
		}

		// All existing values
		if ($select_field_mode <= 0)
		{
			$field_elements = 'SELECT DISTINCT value, value as text '
				. ' FROM #__flexicontent_fields_item_relations '
				. ' WHERE field_id={field->id} AND value != ""'
			;

			// Call function that parses or retrieves element via sql
			$field->parameters->set('sql_mode', 1);
			$field->parameters->set('field_elements', $field_elements);
			$field->parameters->set('nocache', 1);
			$results_existing = FlexicontentFields::indexedField_getElements($field, $item);
			$field->parameters->set('nocache', null);
		}


		$options = array();
		$default_prompt = $select_field_mode === 0
			? 'FLEXI_FIELD_SELECT_EXISTING_VALUE'
			: 'FLEXI_FIELD_SELECT_VALUE';
		$field_prompt = $field->parameters->get('select_field_prompt', $default_prompt);
		$options[] = JHtml::_('select.option', '', JText::_($field_prompt));

		$lang_filter_values = $field->parameters->get( 'lang_filter_values', 0);

		if ($results_predefined)
		{
			foreach($results_predefined as $result)
			{
				if (strlen($result->value))
				{
					$options[] = JHtml::_('select.option',
						$result->value,
						($lang_filter_values ? JText::_($result->text) : $result->text)
					);
				}
			}
		}

		if ($results_existing)
		{
			foreach($results_existing as $result)
			{
				if (strlen($result->value) && (!$results_predefined || !isset($results_predefined[$result->value])))
				{
					$options[] = JHtml::_('select.option',
						$result->value,
						($lang_filter_values ? JText::_($result->text) : $result->text)
					);
				}
			}
		}

		return $options;
	}

}
