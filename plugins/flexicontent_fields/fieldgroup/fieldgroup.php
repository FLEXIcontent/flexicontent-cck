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

class plgFlexicontent_fieldsFieldgroup extends FCField
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

		$use_ingroup = 0; // Field grouped should not be recursively grouped

		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		$compact_edit = $field->parameters->get('compact_edit', 0);
		$form_empty_fields = $field->parameters->get('form_empty_fields', 1);
		$form_empty_fields_text = JText::_($field->parameters->get('form_empty_fields_text', 'FLEXI_NA'));

		// Initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$db   = JFactory::getDbo();
		$user = JFactory::getUser();
		$app  = JFactory::getApplication();
		$isAdmin = $app->isClient('administrator');

		$tooltip_class = 'hasTooltip';
		$add_on_class    = 'btn'; //$cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = 'btn-group'; //$cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
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


		/**
		 * Value handling
		 */

		// Get fields belonging to this field group
		$grouped_fields = $this->getGroupFields($field);

		// Get values of fields making sure that also empty values are created too
		$max_count = 1;
		$this->getGroupFieldsValues($field, $item, $grouped_fields, $max_count);

		// Render Form HTML of the field
		foreach($grouped_fields as $field_id => $grouped_field)
		{
			$grouped_field->ingroup = 1;
			$grouped_field->item_id = $item->id;

			//FLEXIUtilities::call_FC_Field_Func($grouped_field->field_type, 'onDisplayField', array(&$grouped_field, &$item));
			FlexicontentFields::getFieldFormDisplay($grouped_field, $item, $user);
			unset($grouped_field->ingroup);
		}


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
			$js .= "
			jQuery(document).ready(function(){"
				.($compact_edit==2 ? "jQuery('#sortables_".$field->id."').find('.fc-toggle-group-down').data('fc_noeffect', 1).trigger('click');" : "")
				.($compact_edit==1 ? "jQuery('#sortables_".$field->id."').find('.fc-toggle-group-up').data('fc_noeffect', 1).trigger('click');" : "")
			."});
			";

			if ($max_values) JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			var uniqueRowNum".$field->id."	= ".$max_count.";  // Unique row number incremented only
			var rowCount".$field->id."	= ".$max_count.";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
			";

			// Create function call for add/deleting Field values
			$addField_pattern = "
				var fieldval_box = groupval_box.find('.fcfieldval_container__GRP_FID_');
				if (typeof addField_GRP_FID_ !== 'undefined')
				{
					fieldval_box.find('.invalid').removeClass('invalid').attr('aria-invalid', 'false');
					var newSubLabel = fieldval_box.prev('label.sub_label');
					var newLabelFor = 'custom_%s_'+uniqueRowNum".$field->id.";
					newSubLabel.attr('id', newLabelFor + '-lbl');
					newSubLabel.attr('for', newLabelFor);
					newSubLabel.attr('data-for', newLabelFor);
					addField_GRP_FID_(null, groupval_box, groupval_box.find('.fcfieldval_container__GRP_FID_'), add_params);
				}
				else {
					// Clear displayed values of other value-set
					fieldval_box.find('.fc-non-editable-value').html('-');
				}
				";
			$delField_pattern = "
				if (typeof deleteField_GRP_FID_ !== 'undefined')
				{
					if (rowCount".$field->id." == 1)
					{
						// We need to update the current grouped label of the field if this was the last element being re-added
						var fieldval_box = groupval_box.find('.fcfieldval_container__GRP_FID_');
						fieldval_box.find('.invalid').removeClass('invalid').attr('aria-invalid', 'false');
						var newSubLabel = fieldval_box.prev('label.sub_label');
						var newLabelFor = 'custom_%s_'+uniqueRowNum".$field->id.";
						newSubLabel.attr('for', newLabelFor);
						newSubLabel.attr('data-for', newLabelFor);
					}
					deleteField_GRP_FID_(null, groupval_box, groupval_box.find('.fcfieldval_container__GRP_FID_'));
				}
				";
			$addField_funcs = $delField_funcs = '';
			foreach($grouped_fields as $field_id => $grouped_field)
			{
				if ($grouped_field->formhidden == 4) continue;
				if ($isAdmin) {
					if ( $grouped_field->parameters->get('backend_hidden')  ||  (isset($grouped_field->formhidden_grp) && in_array($grouped_field->formhidden_grp, array(2,3))) ) continue;
				} else {
					if ( $grouped_field->parameters->get('frontend_hidden') ||  (isset($grouped_field->formhidden_grp) && in_array($grouped_field->formhidden_grp, array(1,3))) ) continue;
				}

				$addField_funcs .= str_replace("_GRP_FID_",  $grouped_field->id,  sprintf($addField_pattern, $grouped_field->name)  );
				$delField_funcs .= str_replace("_GRP_FID_",  $grouped_field->id,  sprintf($delField_pattern, $grouped_field->name)  );
			}

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
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().find('ul.fcfield-sortables').children().last();
				var newField  = lastField.clone();
				newField.find('.fc-has-value').removeClass('fc-has-value');

				// Need to at least change FORM field names and HTML tag IDs before adding the container to the DOM
				var theSet = newField.find('input, select, textarea, button');
				var nr = 0;
				theSet.each(function()
				{
					if (!!this.id)
					{
						this.setAttribute('data-original-id', this.id);
					}
					this.setAttribute('name', '_duplicated_".$field->id."_'+uniqueRowNum".$field->id."+'_'+nr);
					this.setAttribute('id', '_duplicated_".$field->id."_'+uniqueRowNum".$field->id."+'_'+nr);
					nr++;
				});
				";

			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				";

			// Add new element to sortable objects (if field not in group) -- NOTE: remove_previous: 2 means remove element without do any cleanup actions
			if ($add_ctrl_btns) $js .= "
				//jQuery('#sortables_".$field->id."').sortable('refresh');  // Refresh was done appendTo ?

				// Add new values for each field
				var groupval_box = newField;
				var add_params = {remove_previous: 2, scroll_visible: 0, animate_visible: 0};
				".$addField_funcs."
				";

			// Readd prettyCheckable and remove previous if so requested
			$js .="
				if (remove_previous) lastField.remove();
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


			function deleteField".$field->id."(el)
			{
				// Disable clicks on remove button, so that it is not reclicked, while we do the field value hide effect (before DOM removal of field value)
				var btn = jQuery(el);
				if (btn && rowCount".$field->id." > 1) btn.css('pointer-events', 'none').off('click');

				// Find field value container
				var row = btn.closest('li');

				// Do cleanup by calling the deleteField of each individual field, these functions will re-add last element as empty if needed
				var groupval_box = jQuery(el).closest('li');
				".$delField_funcs."
				if(rowCount".$field->id." == 1)
				{
					uniqueRowNum".$field->id."++;   // increment unique row id, since last group was re-added
				}

				// Also remove the group field values container if not last one
				if (rowCount".$field->id." > 1)
				{
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the field value hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-expand-view').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.fadeOut(420, function(){ this.remove(); });
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
			$_opener = !$compact_edit ? '' : '
				<span class="fc-toggle-group-down ' . $add_on_class . ' btn-success" style="vertical-align: top; text-shadow: unset; '.($compact_edit==2 ? 'display:none;' :'').' min-width: 120px;" onclick="fc_toggle_box_via_btn(jQuery(this).closest(\'li\').find(\'.fcfieldval_container_outer:not(.fcAlwaysVisibleField)\'), this, \'\', jQuery(this).prev(), 1); jQuery(this).prev().before(jQuery(this)); return false;">
					<i class="icon-downarrow"></i>'.JText::_( 'FLEXI_FIELD_GROUP_EDIT_DETAILS' ). '
				</span>
			';
			$_closer = !$compact_edit ? '' : '
				<span class="fc-toggle-group-up ' . $add_on_class . '" style="vertical-align: top; text-shadow: unset; '.($compact_edit==1 ? 'display:none;' :'').' min-width: 120px;" onclick="fc_toggle_box_via_btn(jQuery(this).closest(\'li\').find(\'.fcfieldval_container_outer:not(.fcAlwaysVisibleField)\'), this, \'\', jQuery(this).prev(), 0); jQuery(this).prev().before(jQuery(this)); return false;">
					<i class="icon-uparrow"></i>'.JText::_( 'FLEXI_FIELD_GROUP_HIDE_DETAILS' ). '
				</span>
			';
			$togglers = $compact_edit==1 ? $_opener . $_closer : $_closer . $_opener;
		}

		// Field not multi-value
		else
		{
			$remove_button = '';
			$move2 = '';
			$togglers = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}

		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);

		$close_btn = FLEXI_J30GE ? '<a class="close" data-dismiss="alert">&#215;</a>' : '<a class="fc-close" onclick="this.parentNode.parentNode.removeChild(this.parentNode);">&#215;</a>';
		$alert_box = FLEXI_J30GE ? '<div %s class="alert alert-%s %s">'.$close_btn.'%s</div>' : '<div %s class="fc-mssg fc-%s %s">'.$close_btn.'%s</div>';


		/**
		 * Get fields that are always visible (excluded from 'compact' edit)
		 */

		if ($compact_edit)
		{
			$compact_edit_excluded = $field->parameters->get('compact_edit_excluded', array());

			if (empty($compact_edit_excluded))
			{
				$compact_edit_excluded = array();
			}
			if (!is_array($compact_edit_excluded))
			{
				$compact_edit_excluded = preg_split("/[\|,]/", $compact_edit_excluded);
			}

			$compact_edit_excluded = array_flip($compact_edit_excluded);
		}


		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();

		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout ? 'field_'.$formlayout : 'field_list';

		include(self::getFormPath($this->fieldtypes[0], $formlayout));


		/**
		 * Non value HTML
		 */
		$non_value_html = '';
		foreach($grouped_fields as $field_id => $grouped_field)
		{
			if ($grouped_field->formhidden == 4) continue;
			if ($isAdmin)
			{
				if ( $grouped_field->parameters->get('backend_hidden')  ||  (isset($grouped_field->formhidden_grp) && in_array($grouped_field->formhidden_grp, array(2,3))) ) continue;
			}
			else
			{
				if ( $grouped_field->parameters->get('frontend_hidden') ||  (isset($grouped_field->formhidden_grp) && in_array($grouped_field->formhidden_grp, array(1,3))) ) continue;
			}

			$non_value_html .= @$grouped_field->html[-1];
		}

		// Implode form HTML as a list
		$list_classes  = "fcfield-sortables";
		$list_classes .= " fcfield-group";

		if (count($field->html))
		{
			$field->html = '<li class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">'.
				implode(
				'</li><li class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">',
					$field->html
				).
				'</li>';
			$field->html = '<div id="sortables_outer_'.$field->id.'"><ul class="'.$list_classes.'" id="sortables_'.$field->id.'">' .$field->html. '</ul></div>';
		}
		else
		{
			$field->html = '';
		}
		if (!$add_position) $field->html .= '
			<div class="input-append input-prepend fc-xpended-btns">
				<span class="fcfield-addvalue ' . $font_icon_class . ' fccleared" onclick="jQuery(this).parent().prev().prev().find(\'.fc-show-vals-btn\').data(\'fc_noeffect\', 1).trigger(\'click\'); addField'.$field->id.'(jQuery(this).closest(\'.fc-xpended-btns\').get(0));" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">'.JText::_( 'FLEXI_ADD_VALUE' ).'</span>
			</div>
		';

		// Check max allowed version
		//$manifest_path = JPATH_ADMINISTRATOR .DS. 'components' .DS. 'com_flexicontent' .DS. 'flexicontent.xml';
		//$com_xml = JInstaller::parseXMLInstallFile( $manifest_path );

		// Append non value html of fields
		$field->html =
			($non_value_html ? $non_value_html . '<div class="fcclear"></div>' : '') .
			/*(version_compare( str_replace(' ', '.', $com_xml['version']), str_replace(' ', '.', self::$prior_to_version), '>=') ?
				'<span class="alert alert-warning fc-iblock fc-small" style="margin: 0 0 8px 0;">
					<b>Warning</b>: installed version of Field: \'<b>'.$field->field_type.'</b>\' was meant for FLEXIcontent versions prior to: v'.self::$prior_to_version.' It may or may not work properly in later versions
				</span>' : '').*/
			($field->parameters->get('compact_edit_global', 0) ? '
			<div class="toggle_all_values_buttons_box">
				<span id="sortables_'.$field->id.'_hide_vals_btn" class="btn fc-hide-vals-btn" onclick="fc_toggle_box_via_btn(jQuery(\'#sortables_outer_'.$field->id.'\'), this, \'\', jQuery(this).next(), 0); return false;">
					<i class="icon-uparrow"></i>'.JText::_( 'FLEXI_FIELD_GROUP_HIDE_VALUES' ).'
				</span>
				<span id="sortables_'.$field->id.'_show_vals_btn" class="btn btn-success fc-show-vals-btn" onclick="fc_toggle_box_via_btn(jQuery(\'#sortables_outer_'.$field->id.'\'), this, \'\', jQuery(this).prev(), 1); return false;" style="display:none;">
					<i class="icon-downarrow"></i>'.JText::_( 'FLEXI_FIELD_GROUP_SHOW_VALUES' ).'
				</span>
			</div>
				' : '').'
			' . $field->html;
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


		/**
		 * Get common parameters like: itemprop, value's prefix (pretext), suffix (posttext), separator, value list open/close text (opentag, closetag)
		 * This will replace other field values and item properties, if such are found inside the parameter texts
		 */
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);

		// Use custom HTML display parameter
		$display_mode = (int) $field->parameters->get( 'display_mode', 0 ) ;

		/**
		 * Microdata (classify the field group values for search engines)
		 * we use itemtype and not itemprop as it is more appropriate for the a grouping field
		 */
		$fieldgroup_itemtype      = $field->parameters->get('fieldgroup_itemtype');
		$fieldgroup_itemtype_code = $fieldgroup_itemtype ? 'itemscope itemtype="http://schema.org/'.$fieldgroup_itemtype.'"' : '';

		if (!$pretext && !$posttext && !$display_mode)
		{
			$pretext = '<div class="fc-fieldgrp-value-box">';
			$posttext = '</div>';
		}

		if ($fieldgroup_itemtype_code)
		{
			$pretext = '<div '.$fieldgroup_itemtype_code.' style="display:inline-block;">'.$pretext;
			$posttext = $posttext.'</div>';
		}


		// Get fields belonging to this field group
		$grouped_fields = $this->getGroupFields($field);

		// Get values of fields making sure that also empty values are created too
		$max_count = 0;
		$this->getGroupFieldsValues($field, $item, $grouped_fields, $max_count);


		// ***
		// *** Create a CUSTOMIZED display of the field group
		// ***

		if ( $display_mode )
		{
			$custom_html = trim($field->parameters->get( 'custom_html', '' )) ;
			$field->{$prop} = $this->_createDisplayHTML($field, $item, $grouped_fields, $custom_html, $max_count, $pretext, $posttext);
		}


		// ***
		// *** Create the DEFAULT display of the field group
		// ***

		else
		{
			// Render HTML of fields in the group
			$method = 'display';
			$app = JFactory::getApplication();
			$view = $app->input->get('flexi_callview', $app->input->get('view', 'item', 'cmd'), 'cmd');
			foreach($grouped_fields as $_grouped_field)
			{
				// Check field is assigned to current item type, and get item's field object
				if (!isset($item->fields[$_grouped_field->name]))
				{
					continue;
				}
				$grouped_field = $item->fields[$_grouped_field->name];

				// Set 'value' and 'ingroup' properties
				$grouped_field->value = $_grouped_field->value;
				$grouped_field->ingroup = 1;  // render as array
				$_values = null;

				// Backup display method of the field in cases it is displayed outside of fieldgroup too
				if (isset($grouped_field->$method))
				{
					$grouped_field->{$method.'_non_arr'} = $grouped_field->$method;
					unset($grouped_field->$method);
				}

				// Render the display method for the given field
				//echo 'Rendering: '. $grouped_field->name . ', method: ' . $method . '<br/>';
				//FLEXIUtilities::call_FC_Field_Func($grouped_field->field_type, 'onDisplayFieldValue', array(&$grouped_field, $item, $_values, $method));
				FlexicontentFields::renderField($item, $grouped_field, $_values, $method, $view, $_skip_trigger_plgs = true);  // We will trigger only once the final result

				// Set custom display variable of field inside group
				$grouped_field->{$method.'_arr'} = isset($grouped_field->$method) ? $grouped_field->$method : null;
				unset($grouped_field->$method);
				unset($grouped_field->ingroup);

				// Restore non-fieldgroup display of the field
				if (isset($grouped_field->{$method.'_non_arr'}))
				{
					$grouped_field->$method = $grouped_field->{$method.'_non_arr'};
					unset($grouped_field->{$method.'_non_arr'});
				}
			}

			// Get labels to hide on empty values
			$hide_lbl_ifnoval = $this->getHideLabelsOnEmpty($field);

			// Get layout name
			$viewlayout = $field->parameters->get('viewlayout', '');
			$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';

			// Create field's viewing HTML, using layout file
			$field->{$prop} = array();
			include(self::getViewPath($field->field_type, $viewlayout));
		}

		if (is_string($field->{$prop})) {
			$field->{$prop}  = $opentag . $field->{$prop} . $closetag;
		}
		elseif (count($field->{$prop})) {
			$field->{$prop}  = implode($separatorf, $field->{$prop});
			$field->{$prop}  = $opentag . $field->{$prop} . $closetag;
		} else {
			$field->{$prop} = '';
		}
	}



	// Helper method to create HTML display of an item list according to replacements
	private function _createDisplayHTML(&$field, &$item, &$grouped_fields, $custom_html, $max_count, $pretext, $posttext)
	{
		if (!$custom_html)
		{
			return array('<div class="alert alert-warning">Custom HTML display for group field: ' . $field->label . ' is enabled, but configuration parameter "custom HTML" is empty. Please fill-in or switch to default layout</div>');
		}


		/**
		 * Parse and identify custom fields
		 */

		$result = preg_match_all("/\{\{([a-zA-Z_0-9-]+)(##)?([a-zA-Z_0-9-]+)?\}\}/", $custom_html, $field_matches);
		$gf_reps    = $result ? $field_matches[0] : array();
		$gf_names   = $result ? $field_matches[1] : array();
		$gf_methods = $result ? $field_matches[3] : array();

		/*foreach ($gf_names as $i => $gf_name)
		{
			$parsed_fields[] = $gf_names[$i] . ($gf_methods[$i] ? "->". $gf_methods[$i] : "");
		}
		echo "$custom_html :: Fields for Related Items List: ". implode(", ", $parsed_fields ? $parsed_fields : array() ) ."<br/>\n";*/

		$_name_to_field = array();
		foreach($grouped_fields as $i => $grouped_field)
		{
			$_name_to_field[$grouped_field->name] = $grouped_fields[$i];
		}
		//print_r(array_keys($_name_to_field)); echo "<br/>";


		/**
		 * Replace ITEM properties
		 */

		preg_match_all("/{item->([0-9a-zA-Z_]+)}/", $custom_html, $matches);

		foreach ($matches[0] as $i => $replacement_tag)
		{
			$prop_name = $matches[1][$i];
			if (isset($item->{$prop_name}))
			{
				$custom_html = str_replace($replacement_tag, $item->{$prop_name}, $custom_html);
			}
		}


		/**
		 * Replace language strings
		 */

		$result = preg_match_all("/\%\%([^%]+)\%\%/", $custom_html, $translate_matches);
		$translate_strings = $result ? $translate_matches[1] : array('FLEXI_READ_MORE_ABOUT');

		foreach ($translate_strings as $translate_string)
		{
			$custom_html = str_replace('%%'.$translate_string.'%%', JText::_($translate_string), $custom_html);
		}


		/**
		 * Render and Replace HTML display of fields
		 */

		$_rendered_fields = array();
		if ( count($gf_names) )
		{
			$app = JFactory::getApplication();
			$view = $app->input->get('flexi_callview', $app->input->get('view', 'item', 'cmd'), 'cmd');
			$gf_props = array();
			foreach($gf_names as $pos => $grp_field_name)
			{
				// Check that field exists and is assigned the fieldgroup field (needed only when using custom fieldgroup display HTML)
				if (!isset($_name_to_field[$grp_field_name]))
				{
					continue;
				}

				// Check that field is assigned to the content type
				if (!isset($item->fields[$grp_field_name]))
				{
					continue;
				}

				$_grouped_field = $_name_to_field[$grp_field_name];

				// Get item's field object, set 'value' and 'ingroup' properties
				$grouped_field = $item->fields[$_grouped_field->name];
				$grouped_field->value = $_grouped_field->value;
				$grouped_field->ingroup = 1;  // render as array
				$_values = null;
				$_rendered_fields[$pos] = $grouped_field;

				// Check if display method is 'label' aka nothing to render
				if ( $gf_methods[$pos] == 'label' ) continue;

				// Get custom display method (optional)
				$method = $gf_methods[$pos] ? $gf_methods[$pos] : 'display';

				// Backup display method of the field in cases it is displayed outside of fieldgroup too
				if (isset($grouped_field->$method))
				{
					$grouped_field->{$method.'_non_arr'} = $grouped_field->$method;
					unset($grouped_field->$method);
				}

				// SAME field with SAME method, may have been used more than ONCE, inside the custom HTML parameter, so check if field has been rendered already
				if ( isset($grouped_field->{$method.'_arr'}) && is_array($grouped_field->{$method.'_arr'}) ) continue;

				// Render the display method for the given field
				//echo 'Rendering: '. $grouped_field->name . ', method: ' . $method . '<br/>';
				//FLEXIUtilities::call_FC_Field_Func($grouped_field->field_type, 'onDisplayFieldValue', array(&$grouped_field, $item, $_values, $method));
				FlexicontentFields::renderField($item, $grouped_field, $_values, $method, $view, $_skip_trigger_plgs = true);  // We will trigger only once the final result

				// Set custom display variable of field inside group
				$grouped_field->{$method.'_arr'} = isset($grouped_field->$method) ? $grouped_field->$method : null;
				unset($grouped_field->$method);
				unset($grouped_field->ingroup);

				// Restore non-fieldgroup display of the field
				if (isset($grouped_field->{$method.'_non_arr'}))
				{
					$grouped_field->$method = $grouped_field->{$method.'_non_arr'};
					unset($grouped_field->{$method.'_non_arr'});
				}
			}
		}


		/**
		 * Render the value list of the fieldgroup, using custom HTML for each
		 * value-set of the fieldgroup, and performing the field replacements
		 */

		// Get labels to hide on empty values
		$hide_lbl_ifnoval = $this->getHideLabelsOnEmpty($field);

		$custom_display = array();
		//echo "<br/>max_count: ".$max_count."<br/>";
		for ($n = 0; $n < $max_count; $n++)
		{
			$rendered_html = $custom_html;
			foreach($_rendered_fields as $pos => $_rendered_field)
			{
				$method = $gf_methods[$pos] ? $gf_methods[$pos] : 'display';

				//echo 'Replacing: '. $_rendered_field->name . ', method: ' . $method . ', index: ' .$n. '<br/>';
				if ($method !== 'label' && $method !== 'id' && $method !== 'name')
				{
					$_html = isset($_rendered_field->{$method.'_arr'}[$n]) ? $_rendered_field->{$method.'_arr'}[$n] : '';
				}

				// Skip (hide) label for field having none display HTML (is such behaviour was configured)
				elseif ($method === 'label')
				{
					$_html = isset($hide_lbl_ifnoval[$_rendered_field->id])  &&  (!isset($_rendered_field->{$method.'_arr'}) || !isset($_rendered_field->{$method.'_arr'}[$n]) || !strlen($_rendered_field->{$method.'_arr'}[$n]))
						? ''
						: $_rendered_field->label;
				}

				// id (and in future other properties ?)
				else
				{
					$_html = ! in_array($method, array('id', 'name'))
						? ''
						: $_rendered_field->{$method};
				}

				$rendered_html = str_replace($gf_reps[$pos], $_html, $rendered_html);
			}

			// Replace value position
			$rendered_html = str_replace('{{value##count}}', $n, $rendered_html);

			$custom_display[$n] = $pretext . $rendered_html . $posttext;
		}

		return $custom_display;
	}




	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
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
	// *** VARIOUS HELPER METHODS
	// ***

	// Retrieves the fields that are part of the given 'fieldgroup' field
	function getGroupFields(&$field)
	{
		static $grouped_fields = array();
		if (isset($grouped_fields[$field->id])) return $grouped_fields[$field->id];

		$fieldids = $field->parameters->get('fields', array());
		if ( empty($fieldids) ) {
			$fieldids = array();
		}
		if ( !is_array($fieldids) ) {
			$fieldids = preg_split("/[\|,]/", $fieldids);
		}

		if ( empty($fieldids) ) {  // No assigned fields
			return $grouped_fields[$field->id] = array();
		}

		$db = JFactory::getDbo();
		$query = 'SELECT f.* '
			. ' FROM #__flexicontent_fields AS f '
			. ' WHERE f.published = 1'
			. ' AND f.id IN ('.implode(',',$fieldids).')'
			. ' ORDER BY FIELD(f.id, '.implode(',',$fieldids).')'
			;
		$db->setQuery($query);
		$grouped_fields[$field->id] = $db->loadObjectList('id');

		$_grouped_fields = array();
		foreach($grouped_fields[$field->id] as $field_id => $grouped_field)
		{
			// Create field parameters, if not already created, NOTE: for 'custom' fields loadFieldConfig() is optional
			if (empty($grouped_field->parameters)) {
				$grouped_field->parameters = new JRegistry($grouped_field->attribs);
			}

			// Check if field is not set to participate in a field group and skip it
			if ( !$grouped_field->parameters->get('use_ingroup') ) continue;
			$_grouped_fields[$field_id] = $grouped_field;
		}
		$grouped_fields[$field->id] = $_grouped_fields;

		return $grouped_fields[$field->id];
	}


	// Retrieves and add values to the given field objects
	function getGroupFieldsValues(&$field, &$item, &$grouped_fields, &$max_count)
	{
		$do_compact = true;

		// ****************
		// Get field values
		// ****************
		$max_index = 0;
		foreach($grouped_fields as $field_id => $grouped_field)
		{
			// Item viewing
			if ( isset($item->fieldvalues[$field_id]) ) {
				$grouped_field->value = is_array($item->fieldvalues[$field_id])  ?  $item->fieldvalues[$field_id]  :  array($item->fieldvalues[$field_id]);
			}
			// Item form
			else if ( isset($item->fields[$grouped_field->name]->value) ) {
				$grouped_field->value = $item->fields[$grouped_field->name]->value;
			}
			// Value not set
			else {
				$grouped_field->value = null;
			}

			// Update max value index
			$last_index = !is_array($grouped_field->value) || !count($grouped_field->value) ? 0 : max(array_keys($grouped_field->value));
			$max_index = $last_index > $max_index ? $last_index : $max_index;
		}
		//echo "<br/><br/><br/>DB DATA<br/><pre>"; foreach($grouped_fields as $field_id => $grouped_field) { echo "\n[".$grouped_field->id."] - ".$grouped_field->name; print_r($grouped_field->value); } echo "</pre>";


		// ***********************************************************************************
		// (Compatibility) For groups that have fields with non-set values, add NULL values
		// This way the field will not skip the value and instead will create an empty display
		// ***********************************************************************************
		$null_count = array();
		for ($n=0; $n <= $max_index; $n++) $null_count[$n] = 0;
		foreach($grouped_fields as $field_id => $grouped_field)
		{
			$vals = array();
			for ($n=0; $n <= $max_index; $n++) {
				if ( isset($grouped_field->value[$n]) )
				{
					$vals[$n] = $grouped_field->value[$n];
				} else {
					$vals[$n] = null;
					++$null_count[$n];
				}
			}
			$grouped_field->value = $vals;
		}
		//echo "<br/><br/><br/>NULLED<br/><pre>"; foreach($grouped_fields as $field_id => $grouped_field) { echo "\n[".$grouped_field->id."] - ".$grouped_field->name; print_r($grouped_field->value); } echo "</pre>";
		//echo "<pre>"; print_r($null_count); echo "</pre>";


		// *********************************
		// Find groups that had empty values
		// *********************************
		$grp_isempty = array();
		for($n=0; $n <= $max_index; $n++) {
			if ( isset($null_count[$n]) && $null_count[$n]==count($grouped_fields) )  $grp_isempty[$n] = 1;
		}
		//print_r($grp_isempty); exit;


		// *************************************************************************
		// Compact FIELD GROUP values by removing groups that are (ALL values) empty
		// *************************************************************************

		// Make sure we have some empty fieldgroups, if this was requested (= that is the max_count that was passed to the function)
		$start_at = $max_count + count($grp_isempty) - ($max_index+1);
		if ($start_at < 0) $start_at = 0;

		if ($do_compact) foreach($grouped_fields as $field_id => $grouped_field)
		{
			$i = $start_at;
			for ($n = $start_at; $n <= $max_index; $n++)
			{
				//echo $n." - ".$i."<br/>";
				// Move down to fill empty gaps, if current index is not in sync, meaning 1 empty group was encountered -before-, and also if current (value) group is non-empty
				if ( $n > $i && !isset($grp_isempty[$n]) )
				{
					$grouped_field->value[$i] = $grouped_field->value[$n];
					if ( isset($grouped_field->value[$n]) )
					{
						if ( isset($item->fieldvalues[$field_id]) )               $item->fieldvalues[$field_id][$i] = $grouped_field->value[$n];
						if ( isset($item->fields[$grouped_field->name]->value) )  $item->fields[$grouped_field->name]->value[$i] = $grouped_field->value[$n];
					}
				}

				// Unset moved groups or group with ALL-empty values
				if ( $n > $i || isset($grp_isempty[$n]) )
				{
					unset($grouped_field->value[$n]);
					if ( isset($item->fieldvalues[$field_id]) ) unset($item->fieldvalues[$field_id][$n]);
					if ( isset($item->fields[$grouped_field->name]->value) ) unset($item->fields[$grouped_field->name]->value[$n]);
				}

				// Increment adding position if group was not empty
				if ( !isset($grp_isempty[$n]) ) $i++;
			}
		}
		//echo "<br/><br/><br/>COMPACTED<br/><pre>"; foreach($grouped_fields as $field_id => $grouped_field) { echo "\n[".$grouped_field->id."] - ".$grouped_field->name; print_r($grouped_field->value); } echo "</pre>";

		$max_count = $max_index + 1;
		if ($do_compact) $max_count -= (count($grp_isempty) - $start_at);
		//echo $field->label.": max_count = $max_count <br/>";
	}


	// Return the fields (ids) that will hide their labels if they have no value
	function getHideLabelsOnEmpty(&$field)
	{
		static $hide_lbl_ifnoval_arr = array();
		if (isset($hide_lbl_ifnoval_arr[$field->id])) return $hide_lbl_ifnoval_arr[$field->id];

		$hide_lbl_ifnoval = $field->parameters->get('hide_lbl_ifnoval', array());
		if ( empty($hide_lbl_ifnoval) )  $hide_lbl_ifnoval = array();
		if ( !is_array($hide_lbl_ifnoval) )  $hide_lbl_ifnoval = preg_split("/[\|,]/", $hide_lbl_ifnoval);
		$hide_lbl_ifnoval_arr[$field->id] = array_flip($hide_lbl_ifnoval);

		return $hide_lbl_ifnoval_arr[$field->id];
	}

}
