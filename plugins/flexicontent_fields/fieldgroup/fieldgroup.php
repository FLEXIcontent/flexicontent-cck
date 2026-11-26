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
// Lazy class loading (was JLoader::register): loaded only if/when actually used.
spl_autoload_register(static function ($class) {
	$map = array(
		'FCField' => JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php',
	);
	if (isset($map[$class]) && is_file($map[$class])) require_once $map[$class];
}, true, true);

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

		$field->label = $field->parameters->get('label_form') ? \Joomla\CMS\Language\Text::_($field->parameters->get('label_form')) : \Joomla\CMS\Language\Text::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		$use_ingroup = 0; // Field grouped should not be recursively grouped

		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		$compact_edit = $field->parameters->get('compact_edit', 0);
		$form_empty_fields = $field->parameters->get('form_empty_fields', 1);
		$form_empty_fields_text = \Joomla\CMS\Language\Text::_($field->parameters->get('form_empty_fields_text', 'FLEXI_NA'));

		// Initialize framework objects and other variables
		$document = \Joomla\CMS\Factory::getApplication()->getDocument();
		$cparams  = \Joomla\CMS\Component\ComponentHelper::getParams( 'com_flexicontent' );
		$db   = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$user = \Joomla\CMS\Factory::getApplication()->getIdentity();
		$app  = \Joomla\CMS\Factory::getApplication();
		$isAdmin = $app->isClient('administrator');

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$btn_item_class  = $cparams->get('bootstrap_ver', 2)==2  ?  'btn' : 'btn btn-outline-secondary';
		$btn_group_class = $cparams->get('bootstrap_ver', 2)==2  ?  'btn-group' : 'btn-group';
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


		$compact_edit_global = (int) $field->parameters->get('compact_edit_global', 0);
		if ($multiple || $compact_edit_global)
		{
			flexicontent_html::loadFramework('flexi-lib');
			$assets = \Joomla\CMS\Uri\Uri::root(true) . '/plugins/flexicontent_fields/fieldgroup/';
			$document->addScript($assets . 'js/form.js', array('version' => 'auto'));
			$document->addStyleSheet($assets . 'css/form.css', array('version' => 'auto'));
		}

		$remove_button = $move2 = $togglers = $add_here = '';
		if ($multiple)
		{
			flexicontent_html::loadFramework('sortable');
			if ($max_values) \Joomla\CMS\Language\Text::script('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED', true);

			$children = array();
			foreach ($grouped_fields as $child)
			{
				// Only children rendered by the current layout will be initialized.
				$children[] = array('id' => (int) $child->id, 'name' => $child->name);
			}
			$config = json_encode(array(
				'id' => (int) $field->id,
				'initialCount' => $max_count,
				'max' => $max_values,
				'fields' => $children,
			), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
			$id = (int) $field->id;
			$document->getWebAssetManager()->addInlineScript("
				function addField{$id}(el, groupval_box, fieldval_box, params) {
					return fcfield_group.repeater({$config})?.add(fieldval_box, params);
				}
				function deleteField{$id}(el) {
					fcfield_group.repeater({$config})?.remove(el);
				}
				FlexiForm.ready(() => fcfield_group.repeater({$config}));
			");

			$remove_label = \Joomla\CMS\Language\Text::_('FLEXI_REMOVE_VALUE', true);
			$remove_button = '<button type="button" class="' . $btn_item_class . ' fcfield-delvalue ' . $font_icon_class . '" title="' . $remove_label . '" aria-label="' . $remove_label . '" onclick="deleteField' . $id . '(this);"></button>';
			$move2 = '<span class="' . $btn_item_class . ' fcfield-drag-handle ' . $font_icon_class . '" title="' . \Joomla\CMS\Language\Text::_('FLEXI_CLICK_TO_DRAG', true) . '"></span>';
			foreach (array(2 => 'before', 1 => 'after') as $position => $side)
			{
				if ($add_position != $position && $add_position != 3) continue;
				$label = \Joomla\CMS\Language\Text::_('FLEXI_ADD_' . strtoupper($side), true);
				$add_here .= '<button type="button" class="' . $btn_item_class . ' fcfield-insertvalue fc_' . $side . ' ' . $font_icon_class . '" title="' . $label . '" aria-label="' . $label . '" onclick="addField' . $id . '(this, null, this.closest(\'li\'), {insert_before: ' . (int) ($side === 'before') . '});"></button> ';
			}
			if ($compact_edit)
			{
				$open = '<button type="button" class="fc-toggle-group-down ' . $btn_item_class . ' btn-success" style="vertical-align: top; text-shadow: unset; min-width: 120px;" onclick="fcfield_group.toggleDetails(this, true);"' . ($compact_edit == 2 ? ' hidden' : '') . '>
					<i class="icon-downarrow"></i>' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_GROUP_EDIT_DETAILS') . '
				</button>';
				$close = '<button type="button" class="fc-toggle-group-up ' . $btn_item_class . '" style="vertical-align: top; text-shadow: unset; min-width: 120px;" onclick="fcfield_group.toggleDetails(this, false);"' . ($compact_edit == 1 ? ' hidden' : '') . '>
					<i class="icon-uparrow"></i>' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_GROUP_HIDE_DETAILS') . '
				</button>';
				$togglers = $compact_edit == 1 ? $open . $close : $close . $open;
			}
		}

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

		$list_style = $fields_box_placing ? ' display: flex; flex-wrap: wrap; ' : '';
		$element_style = $fields_box_placing ? ' style="width: fit-content; display:block" ' : '';
		if (count($field->html))
		{
			$row_classes = 'fcfieldval_container valuebox fcfieldval_container_' . $field->id;
			if ($multiple && $compact_edit == 1) $row_classes .= ' fc-fieldgroup-collapsed';
			$row_open = '<li class="' . $row_classes . '" ' . $element_style . '>';
			$field->html = $row_open . implode('</li>' . $row_open, $field->html) . '</li>';
			$field->html = '<div class="fc-fieldgroup-values" id="sortables_outer_' . $field->id . '"><ul class="' . $list_classes . '" style="' . $list_style . '" id="sortables_' . $field->id . '">' . $field->html . '</ul></div>';
		}
		else
		{
			$field->html = '';
		}
		if ($multiple && !$add_position) $field->html .= '
			<div class="' . $btn_group_class . ' fc-xpended-btns">
				<button type="button" class="fcfield-addvalue ' . $font_icon_class . ' ' . $btn_item_class . '" onclick="fcfield_group.toggleValues(' . $field->id . ', true); addField' . $field->id . '(this);" title="' . \Joomla\CMS\Language\Text::_('FLEXI_ADD_TO_BOTTOM', true) . '">' . \Joomla\CMS\Language\Text::_('FLEXI_ADD_VALUE') . '</button>
			</div>
		';

		// Check max allowed version
		//$manifest_path = JPATH_ADMINISTRATOR .DS. 'components' .DS. 'com_flexicontent' .DS. 'flexicontent.xml';
		//$com_xml = \Joomla\CMS\Installer\Installer::parseXMLInstallFile( $manifest_path );

		// Append non value html of fields
		$field->html =
			($non_value_html ? $non_value_html . '<div class="fcclear"></div>' : '') .
			/*(version_compare( str_replace(' ', '.', $com_xml['version']), str_replace(' ', '.', self::$prior_to_version), '>=') ?
				'<span class="alert alert-warning fc-iblock fc-small" style="margin: 0 0 8px 0;">
					<b>Warning</b>: installed version of Field: \'<b>'.$field->field_type.'</b>\' was meant for FLEXIcontent versions prior to: v'.self::$prior_to_version.' It may or may not work properly in later versions
				</span>' : '').*/
			($compact_edit_global ? '
			<div class="toggle_all_values_buttons_box">
				<button type="button" id="sortables_' . $field->id . '_hide_vals_btn" class="btn fc-hide-vals-btn" onclick="fcfield_group.toggleValues(' . $field->id . ', false, true);">
					<i class="icon-uparrow"></i>' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_GROUP_HIDE_VALUES') . '
				</button>
				<button type="button" id="sortables_' . $field->id . '_show_vals_btn" class="btn btn-success fc-show-vals-btn" onclick="fcfield_group.toggleValues(' . $field->id . ', true, true);" hidden>
					<i class="icon-downarrow"></i>' . \Joomla\CMS\Language\Text::_('FLEXI_FIELD_GROUP_SHOW_VALUES') . '
				</button>
			</div>
				' : '').'
			' . $field->html;
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = \Joomla\CMS\Language\Text::_($field->label);

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

			$app       = \Joomla\CMS\Factory::getApplication();
			$document  = \Joomla\CMS\Factory::getApplication()->getDocument();
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
			$app = \Joomla\CMS\Factory::getApplication();
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
			$custom_html = str_replace('%%'.$translate_string.'%%', \Joomla\CMS\Language\Text::_($translate_string), $custom_html);
		}


		/**
		 * Render and Replace HTML display of fields
		 */

		$_rendered_fields = array();
		if ( count($gf_names) )
		{
			$app = \Joomla\CMS\Factory::getApplication();
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

		$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
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
				$grouped_field->parameters = new \Joomla\Registry\Registry($grouped_field->attribs);
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
