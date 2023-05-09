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

class plgFlexicontent_fieldsEmail extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct( &$subject, $params )
	{
		$app = JFactory::getApplication(); //record action in form
		if ($app->isClient('site') && $app->input->get('emailtask', '', 'cmd') === 'plg.email.submit') {
			$this->sendEmail();
		}
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
		$fields_box_placing = (int) $field->parameters->get('fields_box_placing', 1);
		$show_values_expand_btn = (int) $field->parameters->get('show_values_expand_btn', 0);


		/**
		 * Form field display parameters
		 */

		// Usage information
		$show_usage  = (int) $field->parameters->get( 'show_usage', 0 ) ;
		$field_notes = '';

		// Input field display size & max characters
		$size = (int) $field->parameters->get( 'size', 30 ) ;

		// Max Length is enforced in both client & server sides
		$maxlength  = (int) $field->parameters->get( 'maxlength', 4000 ) ;   // client/server side enforced

		// Create extra HTML TAG parameters for the form field
		$classes = '';
		$attribs = $field->parameters->get( 'extra_attributes', '' )
			. ($maxlength ? ' maxlength="' . $maxlength . '" ' : '')
			. ($auto_value ? ' readonly="readonly" ' : '')
			. ($size ? ' size="' . $size . '" ' : '')
			;


		/**
	   * Create validation mask
		 */

		$inputmask	= $field->parameters->get( 'inputmask', '' ) ;

		static $inputmask_added = false;
	  if ($inputmask && !$inputmask_added)
		{
			$inputmask_added = true;
			flexicontent_html::loadFramework('inputmask');
		}


		// Email address
		$addr_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_addr = ($item->version == 0 || $addr_usage > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_addr = $default_addr ? JText::_($default_addr) : '';

		// Email title & linking text (optional)
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';


		// Initialise property with default value
		if (!$field->value || (count($field->value) === 1 && reset($field->value) === null))
		{
			$field->value = array();
			$field->value[0]['addr'] = $default_addr;
			$field->value[0]['text'] = $default_title;
			$field->value[0] = serialize($field->value[0]);
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
				// Update the new email address
				theInput = newField.find('input.emailaddr').first();
				theInput.attr('value', ".json_encode($default_addr).");
				theInput.attr('name', fname_pfx + '[addr]');
				theInput.attr('id', element_id + '_addr');
				newField.find('.emailaddr-lbl').first().attr('for', element_id + '_addr');

				// Update inputmask
				var has_inputmask = newField.find('input.has_inputmask').length != 0;
				if (has_inputmask)  newField.find('input.has_inputmask').inputmask();
				";

			// Update the new email linking text
			if ($usetitle) $js .= "
				theInput = newField.find('input.emailtext').first();
				theInput.val(".json_encode($default_title).");
				theInput.attr('name', fname_pfx + '[text]');
				theInput.attr('id', element_id + '_text');
				newField.find('.emailtext-lbl').first().attr('for', element_id + '_text');

				// Destroy any select2 elements
				var sel2_elements = newField.find('div.select2-container');
				if (sel2_elements.length)
				{
					sel2_elements.remove();
					newField.find('select.use_select2_lib').select2('destroy').show();
				}
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

				// Attach bootstrap event on new element
				fc_bootstrapAttach(newField);

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

		// Create attributes for JS inputmask validation
		$validate_mask = '';

		switch ($inputmask)
		{
			default:
				if ($inputmask)
				{
					$validate_mask = " data-inputmask=\" 'alias': '" . $inputmask . "' \" ";
					$classes .= ' has_inputmask';
				}
		}

		$classes .= ' validate-email';

		// Classes and attributes for the input of the input field
		$addr_classes = $classes;
		$addr_attribs = $attribs . $validate_mask ;


		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();

		// These are unused in this field
		$skipped_vals = array();
		$per_val_js   = '';

		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout ? 'field_'.$formlayout : 'field_InlineBoxes';
		$simple_form_layout = $field->parameters->get('simple_form_layout', 0);

		include(self::getFormPath($this->fieldtypes[0], $formlayout));

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

		// Add toggle button for: Compact values view (= multiple values per row)
		$show_values_expand_btn = $formlayout === 'field_InlineBoxes' ? $show_values_expand_btn : 0;
		if (!$use_ingroup && $show_values_expand_btn)
		{
			$field->html = '
			<button type="button" class="fcfield-expand-view-btn btn btn-small" data-expandedFieldState="0" aria-label="' . JText::_('FLEXI_EXPAND') . '"
				onclick="fc_toggleCompactValuesView(this, jQuery(this).closest(\'.container_fcfield\'));"
			>
				<span class="fcfield-expand-view ' . $font_icon_class . '" aria-hidden="true"></span>&nbsp; ' . JText::_('FLEXI_EXPAND', true) . '
			</button>
			' . $field->html;
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

		// Value handling parameters
		$lang_filter_values = 0;

		// Email address
		$addr_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_addr = ($addr_usage == 2) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_addr = $default_addr ? JText::_($default_addr) : '';

		// Email title & linking text (optional)
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';

		// Rendering options
		$email_cloaking = $field->parameters->get( 'email_cloaking', 1 ) ;
		$mailto_link    = $field->parameters->get( 'mailto_link', 1 ) ;
		$format = JFactory::getApplication()->input->get('format', 'html', 'cmd');

		// Get field values
		$values = $values ? $values : $field->value;

		// Check for no values and no default value, and return empty display
		if (empty($values))
		{
			if (!strlen($default_addr))
			{
				$field->{$prop} = $is_ingroup ? array() : '';
				return;
			}
			$values = array(0 => array(
				'addr' => JText::_($default_addr),
				'text' => JText::_($default_title),
			));
			$values[0] = serialize($values[0]);
		}

		$unserialize_vals = true;
		if ($unserialize_vals)
		{
			// (* BECAUSE OF THIS, the value display loop expects unserialized values)
			foreach ($values as &$value)
			{
				// Compatibility for non-serialized values or for NULL values in a field group
				if ( !is_array($value) )
				{
					$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
					$value = $array ?: array(
						'addr' => $value, 'text' => ''
					);
				}
			}
			unset($value); // Unset this or you are looking for trouble !!!, because it is a reference and reusing it will overwrite the pointed variable !!!
		}


		/**
		 * Get common parameters like: itemprop, value's prefix (pretext), suffix (posttext), separator, value list open/close text (opentag, closetag)
		 * This will replace other field values and item properties, if such are found inside the parameter texts
		 */
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);


		// CSV export: Create customized output and return
		if ($prop === 'csv_export')
		{
			$separatorf = ' | ';
			$itemprop = false;

			$csv_export_text = $field->parameters->get('csv_export_text', '{{text}} {{addr}}');
			$field_matches = null;

			$result = preg_match_all("/\{\{([a-zA-Z_0-9-]+)\}\}/", $csv_export_text, $field_matches);
			$propertyNames = $result ? $field_matches[1] : array();

			$field->{$prop} = array();

			foreach ($values as $value)
			{
				$output = $csv_export_text;

				foreach ($propertyNames as $pname)
				{
					$output = str_replace('{{' . $pname . '}}', (isset($value[$pname]) ? $value[$pname] : ''), $output);
				}

				$field->{$prop}[] = $output;
			}

			// Apply values separator, creating a non-array output regardless of fieldgrouping, as fieldgroup CSV export is not supported
			$field->{$prop} = implode($separatorf, $field->{$prop});

			return;
		}


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

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;

		// Get configuration
		$app  = JFactory::getApplication();
		$is_importcsv = $app->input->get('task', '', 'cmd') == 'importcsv';

		// Server side validation
		$maxlength  = (int) $field->parameters->get( 'maxlength', 4000 ) ;


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
					'addr' => $v, 'text' => ''
				);
			}

			// ***
			// *** Validate data, skipping values that are empty after validation
			// ***

			$addr = flexicontent_html::dataFilter($v['addr'], $maxlength, 'EMAIL', 0);  // Clean bad text/html

			// Skip empty value, but if in group increment the value position
			if (!strlen($addr))
			{
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}

			$newpost[$new] = array();
			$newpost[$new]['addr'] = $addr;
			$newpost[$new]['text'] = flexicontent_html::dataFilter(@$v['text'], 0, 'STRING', 0);

			$new++;
		}
		$post = $newpost;

		// Serialize multi-property data before storing them into the DB,
		// null indicates to increment valueorder without adding a value
		foreach($post as $i => $v)
		{
			if ($v!==null) $post[$i] = serialize($v);
		}
		/*if ($use_ingroup) {
			$app = JFactory::getApplication();
			$app->enqueueMessage( print_r($post, true), 'warning');
		}*/
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

		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	public function getFilteredSearch(&$filter, $value, $return_sql = true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
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
	public function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
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



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	/**
	 * Helper for sendemail
	 */

	public static function sendEmail()
	{
		// Load plugin language
		$lang = JFactory::getLanguage();
		$lang->load('plg_flexicontent_fields_email', JPATH_ADMINISTRATOR);

		// get the params from the plugin options
		$plugin = JPluginHelper::getPlugin('flexicontent_fields', 'email');
		$pluginParams = new JRegistry($plugin->params);

		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		// Get a handle to the Joomla! application object
		$app = JFactory::getApplication();

		//get input form
		$jinput = JFactory::getApplication()->input;
		
		// create variable for email
		global $globalcats;
		$config = JFactory::getConfig();
		$categories = & $globalcats;
		// Get the route helper
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
		$itemid   = $jinput->post->get('itemid', '', 'int');
		$title    = $jinput->post->get('itemtitle', '', 'STRING');
		$alias    = $jinput->post->get('itemalias', '');
		$maincat  = $jinput->post->get('catid', '', 'int');
		$itemauthor  = $jinput->post->get('itemauthor', '', '');
		$formid = $jinput->post->get('formid', '', '');

		// Create the non-SEF URL
		$item_url = FlexicontentHelperRoute::getItemRoute($itemid.':'.$alias, $maincat);
		// Create the SEF URL
		$item_url = $app->isClient('administrator')
			? flexicontent_html::getSefUrl($item_url)   // ..., $_xhtml= true, $_ssl=-1);
			: JRoute::_($item_url);  // ..., $_xhtml= true, $_ssl=-1);
		// Make URL absolute since this URL will be emailed
		$item_url = JUri::getInstance()->toString(array('scheme', 'host', 'port')) . $item_url;
		$sitename = $app->getCfg('sitename') . ' - ' . JUri::root();

		// set only form value in input
		$datas = $jinput->post->get($formid, array(), 'array');
		
		if (isset($datas['name'])){
			$name = $datas['name'];
		}
		if (isset($datas['firstname'])){
			$firstname = $datas['firstname'];
		}
		if (isset($datas['lastname'])){
			$lastname = $datas['lastname'];
		}
		// Create header email
		if (!empty($name)){ // check if user use 1 name field or 2 separeate field
			$fromname = $datas['name'];
		} elseif (!empty($firstname) && !empty($lastname)){
			$firstname = $datas['firstname'];
			$lastname = $datas['lastname'];
			$fromname = $firstname.' '.$lastname;
		}
		if (empty($fromname)){
			$app->enqueueMessage(JText::_('FLEXI_FIELD_EMAIL_CONFIG_ERROR'), 'error');
		}
		$fromemail   = flexicontent_html::dataFilter($datas['emailfrom'],   4000, 'STRING', '');
		$emailauthor = flexicontent_html::dataFilter($_POST['emailauthor'],   4000, 'STRING', '');
		$from = array($fromemail , $fromname);

		//subject
		if (isset($datas['subject'])){
			$subject = $datas['subject'];
		} else{
			$subject = $title;
		}
		$subjectemail = JText::sprintf('FLEXI_FIELD_EMAIL_SUBJECT_DEFAULT', $fromname, $subject);

		//body
		$body = '';
		foreach ($datas as $field => $value) {
				$body .= '<li>'.$field.' : ' . $value . '</li>';
			}
			$body = "\n\r\n\r\n" . stripslashes($body);
			$message 	= JText::sprintf('FLEXI_FIELD_EMAIL_MESSAGE_DEFAULT', $title, $body, '<a href="'.$item_url.'">'.$item_url.'</a>', $sitename);

		// Check whether email copy function activated
		$copy_email_user = $pluginParams->get( 'email_user_copy','' );
			if ($copy_email_user == true)
			{
				$messagecopy   = JText::sprintf('FLEXI_FIELD_EMAIL_MESSAGE_DEFAULT_COPY', $title, $body, '<a href="'.$item_url.'">'.$item_url.'</a>', $sitename);
				$subjectcopy =  JText::sprintf('FLEXI_FIELD_EMAIL_SUBJECT_DEFAULT_COPY', $fromname, $itemauthor, $subject);
				$mailer = JFactory::getMailer();
				$mailer->isHTML(true);
				$mailer ->setSender(array($emailauthor, $itemauthor));
				$mailer ->addRecipient($fromemail);
				$mailer ->setSubject($subjectcopy);
				$mailer ->setBody($messagecopy);
				$send = $mailer->Send();
			}
			$copy_email_admin = $pluginParams->get( 'email_admin_copy', 0 );
			$email_admin = $pluginParams->get( 'email_admin', '' ) ;
				if ($copy_email_admin == true)
				{
					$messagecopyadmin   = JText::sprintf('FLEXI_FIELD_EMAIL_MESSAGE_ADMIN_COPY', $fromname , $title, $body, '<a href="'.$item_url.'">'.$item_url.'</a>', $sitename);
					$subjectcopyadmin =  JText::sprintf('FLEXI_FIELD_EMAIL_SUBJECT_ADMIN_COPY', $itemauthor, $subject);
					$mailer = JFactory::getMailer();
					$mailer->isHTML(true);
					$mailer ->setSender($from, $fromname);
					$mailer ->addRecipient($email_admin);
					$mailer ->setSubject($subjectcopyadmin);
					$mailer ->setBody($messagecopyadmin);
					$send = $mailer->Send();
				}


			//Prepare contact email
			$mailer = JFactory::getMailer();
			$mailer->isHTML(true);
			$mailer->setSender($from, $fromname);
			$mailer->addRecipient($emailauthor);
			$mailer->setSubject($subjectemail);
			$mailer->setBody($message);

		//upload attachement
		$files = $jinput->files->get($formid);
		if (isset($files))
		{
			JFolder::create(JPATH_SITE . DS . "tmp" . DS . "upload_flexi_form". $formid);

			foreach($files as $attachements) {
				foreach ($attachements as $file){
				// Import filesystem libraries. Perhaps not necessary, but does not hurt.
				jimport('joomla.filesystem.file');

				// Clean up filename to get rid of strange characters like spaces etc.
				$filename = JFile::makeSafe($file['name']);

				// Set up the source and destination of the file
				$src = $file['tmp_name'];
				$dest = JPATH_SITE . DS . "tmp" . DS . "upload_flexi_form". $formid . DS . $filename;
					// TODO: Add security checks. FIle extension and size maybe using flexicontent helper

					if (JFile::upload($src, $dest))
						{
        			$mailer->addAttachment($dest);
						} 
					else
						{
						$app->enqueueMessage(JText::_('FLEXI_FIELD_EMAIL_MESSAGE_SEND_ERROR'), 'error');
						}
				}
			}
		}

		//Sendemail
		$send = $mailer->Send();

		//Message in front-end
		if ( $send !== true )
			{
				$app->enqueueMessage(JText::_('FLEXI_FIELD_EMAIL_MESSAGE_SEND_ERROR'), 'error');
				$destFolder= JPATH_SITE . DS . "tmp" . DS . "upload_flexi_form". $formid;
				//Deleting file
				if (is_dir($destFolder)) {
 				JFolder::delete($destFolder);
				} 
			} else {
				// Message sending
				$app->enqueueMessage(JText::_('FLEXI_FIELD_EMAIL_MESSAGE_SEND_SUCCESS'), 'message');
				$destFolder= JPATH_SITE . DS . "tmp" . DS . "upload_flexi_form". $formid;
				//Deleting file
				if (is_dir($destFolder)) {
 				JFolder::delete($destFolder);
				} 
			}
	}
}
