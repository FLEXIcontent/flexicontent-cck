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

use Joomla\CMS\Factory;
use Joomla\Utilities\ArrayHelper;
use Joomla\String\StringHelper;
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsRelation extends FCField
{
	static $field_types = array('relation', 'relation_reverse', 'autorelationfilters');
	var $task_callable = array('getCategoryItems');  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct(&$subject, $params)
	{
		parent::__construct($subject, $params);
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if (!in_array($field->field_type, static::$field_types)) return;

		$field->label = $field->parameters->get('label_form') ? JText::_($field->parameters->get('label_form')) : JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;


		/**
		 * Case of autorelated item
		 */

		if (Factory::getApplication()->input->get('autorelation_' . $field->id, 0, 'int'))
		{
			$field->html = '<div class="alert alert-warning">' . $field->label . ': ' . 'You can not auto-relate items using a relation field, please add a relation reverse field, and select to reverse this field</div>';

			return;
		}

		// Initialize framework objects and other variables
		$document = Factory::getDocument();
		$cparams  = JComponentHelper::getParams('com_flexicontent');
		$app      = Factory::getApplication();
		$db       = Factory::getDbo();
		$user     = Factory::getUser();

		$tooltip_class   = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2) == 2 ? 'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2) == 2 ? 'input-append input-prepend' : 'input-group';
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
		$add_ctrl_btns      = !$use_ingroup && $multiple;
		$fields_box_placing = (int) $field->parameters->get('fields_box_placing', 0);


		/**
		 * Value handling
		 */

		// Default value(s)
		$default_values = $this->getDefaultValues($isform = true, $_translate = false, $_split = ',');
		//$default_value  = reset($default_values);


		/**
		 * Form field display parameters
		 */

		$size                    = (int) $field->parameters->get('size', 12);
		$prepend_item_state      = $field->parameters->get('itemselector_prepend_item_state', 0);
		$maxtitlechars           = $field->parameters->get('maxtitlechars', 40);
		$selected_items_label    = $field->parameters->get('selected_items_label', 'FLEXI_RIFLD_SELECTED_ITEMS_LABEL');
		$selected_items_sortable = $field->parameters->get('selected_items_sortable', 0);

		// Create extra HTML TAG parameters for the form field
		$classes = 'use_select2_lib fc_select2_no_check fc_select2_noselect'
			. $required_class
			. ($selected_items_sortable ? ' fc_select2_sortable' : '');
		$attribs = ''
			. ($size ? ' size="' . $size . '" ' : '');

		// Attribute for default value(s)
		if (!empty($default_values))
		{
			$attribs .= ' data-defvals="' . htmlspecialchars(implode('|||', $default_values), ENT_COMPAT, 'UTF-8') . '" ';
		}


		// Initialise property with default value
		if (!$field->value || (count($field->value) === 1 && reset($field->value) === null))
		{
			$field->value = $default_values;
		}

		// CSS classes of value container
		$value_classes = 'fcfieldval_container valuebox fcfieldval_container_' . $field->id;
		$value_classes .= $fields_box_placing ? ' floated' : '';

		// Field name and HTML TAG id
		$valueholder_nm = 'custom[_fcfield_valueholder_][' . $field->name . ']';
		$valueholder_id = 'custom__fcfield_valueholder__' . $field->name;
		$fieldname      = 'custom[' . $field->name . ']';
		$elementid      = 'custom_' . $field->name;

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);


		/**
		 * Create category tree to use for creating the category selector
		 */

		// Get categories without filtering
		require_once(JPATH_ROOT . DS . "components" . DS . "com_flexicontent" . DS . "classes" . DS . "flexicontent.categories.php");
		$tree = flexicontent_cats::getCategoriesTree();

		// Get allowed categories
		$allowed_cats = $this->getAllowedCategories($field);
		if (empty($allowed_cats))
		{
			$field->html = JText::_('FLEXI_CANNOT_EDIT_FIELD') . ': <br/> ' . JText::_('FLEXI_NO_ACCESS_TO_USE_CONFIGURED_CATEGORIES');

			return;
		}

		// Add categories that will be used by the category selector
		$allowedtree = array();
		foreach ($allowed_cats as $catid)
		{
			$allowedtree[$catid] = $tree[$catid];
		}


		// JS & CSS of current field
		$js  = '';
		$css = '';

		// Handle multiple records
		if ($multiple)
		{
			// Add the drag and drop sorting feature
			if ($add_ctrl_btns) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_" . $field->id . "').sortable({
					handle: '.fcfield-drag-handle',
					cancel: false,
					/*containment: 'parent',*/
					tolerance: 'pointer'
					" . ($fields_box_placing ? "
					,start: function(e) {
						//jQuery(e.target).children().css('float', 'left');
						//fc_setEqualHeights(jQuery(e.target), 0);
					}
					,stop: function(e) {
						//jQuery(e.target).children().css({'float': 'none', 'min-height': '', 'height': ''});
					}
					" : '') . "
				});
			});
			";

			if ($max_values) JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			function addField" . $field->id . "(el, groupval_box, fieldval_box, params)
			{
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;

				if(!remove_previous && (rowCount" . $field->id . " >= maxValues" . $field->id . ") && (maxValues" . $field->id . " != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues" . $field->id . ");
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
				// Update container
				var elem= newField.find('div.fcfield-relation-value_box').first();
				elem.attr('data-elementid', element_id);

				// Update the category selector field
				var elem= newField.find('select.fcfield-relation-cat_selector').first();
				var defvals = elem.attr('data-defvals');
				if ( defvals && defvals.length )
				{
					jQuery.each(defvals.split('|||'), function(i, val){
						elem.find('option[value=\"' + val + '\"]').attr('selected', 'selected');
					});
				}
				else elem.val('');
				elem.attr('name', element_id + '_cat_selector');
				elem.attr('id', element_id + '_cat_selector');

				// Update the items selector field
				var elem= newField.find('select.fcfield-relation-item_selector').first();
				elem.empty();
				elem.attr('name', element_id + '_item_selector');
				elem.attr('id', element_id + '_item_selector');

				// Update the value field
				var elem= newField.find('select.fcfield-relation-selected_items').first();
				elem.empty();
				elem.attr('name', fname_pfx + '[]');
				elem.attr('id', element_id);

				newField.find('label.cat_selector-lbl').attr('for', element_id + '_cat_selector');
				newField.find('label.item_selector-lbl').attr('for', element_id + '_item_selector');
				newField.find('label.selected_items-lbl').attr('for', element_id);

				newField.find('label.cat_selector-lbl').attr('id', element_id + '_cat_selector-lbl');
				newField.find('label.item_selector-lbl').attr('id', element_id + '_item_selector-lbl');
				newField.find('label.selected_items-lbl').attr('id', element_id + '-lbl');

				// Destroy any select2 elements
				var sel2_elements = newField.find('div.select2-container');
				if (sel2_elements.length)
				{
					sel2_elements.remove();
					newField.find('select.use_select2_lib').select2('destroy').show();
				}

				// Update value holder
				newField.find('.fcfield_value_holder')
					.attr('id', '" . $valueholder_id . "_'+uniqueRowNum" . $field->id . ")
					.attr('name', '" . $valueholder_nm . "['+uniqueRowNum" . $field->id . "+']');
				";

			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_" . $field->id . "') ) ;
				if (remove_previous) lastField.remove();

				// Attach form validation on new element
				fc_validationAttach(newField);

				// Re-init any select2 elements
				fc_attachSelect2(newField);

				" . (count($allowedtree) === 1 ? "
				var cat_selector = jQuery('#' + element_id + '_cat_selector');
				if (cat_selector.length)
				{
					cat_selector[0].selectedIndex = 1;
					cat_selector.trigger('change');
				}
				" : "") . "
				";

			// Add new element to sortable objects (if field not in group)
			if ($add_ctrl_btns) $js .= "
				//jQuery('#sortables_" . $field->id . "').sortable('refresh');  // Refresh was done appendTo ?
				";

			// Show new field, increment counters
			$js .= "
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800, function() { jQuery(this).css('opacity', ''); });

				// Enable tooltips on new element
				newField.find('.hasTooltip').tooltip({html: true, container: newField});
				newField.find('.hasPopover').popover({html: true, container: newField, trigger : 'hover focus'});

				rowCount" . $field->id . "++;       // incremented / decremented
				uniqueRowNum" . $field->id . "++;   // incremented only
			}


			function deleteField" . $field->id . "(el, groupval_box, fieldval_box)
			{
				// Disable clicks on remove button, so that it is not reclicked, while we do the field value hide effect (before DOM removal of field value)
				var btn = fieldval_box ? false : jQuery(el);
				if (btn && rowCount" . $field->id . " > 1) btn.css('pointer-events', 'none').off('click');

				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');

				// Add empty container if last element, instantly removing the given field value container
				if(rowCount" . $field->id . " == 1)
					addField" . $field->id . "(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});

				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if (rowCount" . $field->id . " > 1)
				{
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the field value hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-expand-view').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ jQuery(this).remove(); });
					rowCount" . $field->id . "--;
				}
			}
			";

			$css .= '';

			$remove_button = '<span class="' . $add_on_class . ' fcfield-delvalue ' . $font_icon_class . '" title="' . JText::_('FLEXI_REMOVE_VALUE') . '" onclick="deleteField' . $field->id . '(this);"></span>';
			$move2         = '<span class="' . $add_on_class . ' fcfield-drag-handle ' . $font_icon_class . '" title="' . JText::_('FLEXI_CLICK_TO_DRAG') . '"></span>';
			$add_here      = '';
			$add_here      .= $add_position == 2 || $add_position == 3 ? '<span class="' . $add_on_class . ' fcfield-insertvalue fc_before ' . $font_icon_class . '" onclick="addField' . $field->id . '(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="' . JText::_('FLEXI_ADD_BEFORE') . '"></span> ' : '';
			$add_here      .= $add_position == 1 || $add_position == 3 ? '<span class="' . $add_on_class . ' fcfield-insertvalue fc_after ' . $font_icon_class . '"  onclick="addField' . $field->id . '(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="' . JText::_('FLEXI_ADD_AFTER') . '"></span> ' : '';
		}

		// Field not multi-value
		else
		{
			$remove_button = '';
			$move2         = '';
			$add_here      = '';
			$js            .= '';
			$css           .= '';
		}


		/*
		 * Initialise values and split them into: (a) item ids and (b) category ids
		 */

		// Parse values
		//echo '<div class="alert alert-info"><h2>DB: ' . $field->label . '</h2><pre>'; print_r($field->value); echo '</pre></div>';
		$field->value = $this->parseValues($field->value);

		// No limit for used items
		$item_limit = 0;

		// Get related items IDs and their category ID
		$itemids_sets       = null;
		$related_items_sets = $this->parseRelatedItems($field->value, $item_limit, $itemids_sets);


		/**
		 * Item retrieving query ... put together and execute it
		 */

		foreach ($itemids_sets as $n => $_itemids_v)
		{
			if (count($_itemids_v))
			{
				$query = 'SELECT i.title, i.id, i.catid, i.state, i.alias'
					. ' FROM #__content AS i '
					. ' WHERE i.id IN (' . implode(',', $_itemids_v) . ')'
					. ' ORDER BY FIELD(i.id, ' . implode(',', $_itemids_v) . ')';
				$items_arr[$n] = $db->setQuery($query)->loadObjectList();
			}
			else
			{
				$items_arr[$n] = array();
			}
		}


		/**
		 * Create category selector to use for selecting related items
		 */

		$cat_selected          = count($allowedtree) == 1 ? reset($allowedtree) : '';
		$cat_selecor_box_style = count($allowedtree) === 1 ? 'style="display:none;" ' : '';
		$cat_selector_attribs  = ' class="use_select2_lib fcfield-relation-cat_selector" onchange="return fcfield_relation.cat_selector_change(this);" ';

		$cat_selector = flexicontent_cats::buildcatselect
		(
			$allowedtree,
			'__ELEMENTID___cat_selector',
			($cat_selected ? $cat_selected->id : ''),
			$top_option = JText::_('FLEXI_SELECT'),  // Important: Add a first option "Select", otherwise single entry in select cannot initiate onchange event
			$cat_selector_attribs,
			$check_published = true,
			$check_perms = true,
			$actions_allowed = array('core.create', 'core.edit', 'core.edit.own'),
			$require_all = false,
			$skip_subtrees = array(),
			$disable_subtrees = array()
		);


		/**
		 * Create the selected items field (items selected as 'related')
		 */

		$state_shortname      = array(1 => '', 0 => 'U', -1 => 'A', -3 => 'PE', -4 => 'OQ', -5 => 'IP');
		$items_options_select = array();

		foreach ($items_arr as $n => $items_arr_v)
		{
			$items_options_select[$n] = '';
			foreach ($items_arr_v as $itemdata)
			{
				$itemtitle = (StringHelper::strlen($itemdata->title) > $maxtitlechars) ? StringHelper::substr($itemdata->title, 0, $maxtitlechars) . "..." : $itemdata->title;
				if ($prepend_item_state)
				{
					$statestr = $state_shortname[$itemdata->state] ?? '';
					$statestr = $statestr ? '[' . $statestr .']' : '';
					$itemtitle = $statestr . $itemtitle . " ";
				}
				$itemid                   = $itemdata->id;
				$items_options_select[$n] .= '<option selected="selected" value="' . htmlspecialchars($related_items_sets[$n][$itemid]->value, ENT_COMPAT, 'UTF-8') . '" >' . $itemtitle . '</option>' . "\n";
			}
		}


		// ***
		// *** Add needed JS
		// ***

		static $common_css_js_added = false;
		if (!$common_css_js_added)
		{
			$common_css_js_added = true;
			flexicontent_html::loadFramework('select2');

			JText::script('FLEXI_RIFLD_ERROR', false);
			JText::script('FLEXI_RIFLD_NO_ITEMS', false);
			JText::script('FLEXI_RIFLD_ADD_ITEM', false);
			$document->addScript(JUri::root(true) . '/plugins/flexicontent_fields/relation/js/form.js', array('version' => FLEXI_VHASH));
		}

		$per_val_js = '';


		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();
		$n           = 0;

		foreach ($related_items_sets as $n => $related_items)
		{
			$fieldname_n = $fieldname . ($multiple ? '[' . $n . ']' : '') . '[]';
			$elementid_n = $elementid . ($multiple ? '_' . $n : '');

			// Skip empty if not in field group, and at least one value was added
			if (!count($related_items) && !$use_ingroup && $n) continue;

			$custom_filters_html = [];
			$this->getCustomFilts($field, $elementid);
			foreach ($this->_custom_filters as $filt)
			{
				$custom_filters_html[] = $this->getFilterDisplay(array(
					'label'             => $filt->label,
					'label_extra_class' => ($filt->value ? ' fc-lbl-inverted' : ''),
					'html'              => $filt->html,
				));
			}

			if ($custom_filters_html || $prepend_item_state)
			{
				$field->html[-1] = '';
				if ($prepend_item_state)
				{
					//array(1 => '', 0 => 'U', -1 => 'A', -3 => 'PE', -4 => 'OQ', -5 => 'IP');
					$long_names = array(
						'(empty)' => JText::_('FLEXI_PUBLISHED', true),
						'[U]' => JText::_('FLEXI_UNPUBLISHED', true),
						'[IP]' => JText::_('FLEXI_IN_PROGRESS', true),
						'[PE]' => JText::_('FLEXI_PENDING', true),
						'[OQ]' => JText::_('FLEXI_TO_WRITE', true),
						'[A]' => JText::_('FLEXI_ARCHIVED', true),
						'[T]' => JText::_('FLEXI_TRASHED', true)
					);
					$states_lengend = [];
					foreach ($long_names as $i => $v) $states_lengend[] = '<div><span class="badge long-state-name">' . $v . '</span>'
						. '<small class="short-state-name">'. $i . '</small></div>';
					$field->html[-1] = '<div class="fcfield-relation-states-legend" style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">'
						. implode('', $states_lengend). '</div>';
				}
				$field->html[-1] .= implode(' - ', $custom_filters_html);
			}
			$field->html[]   = '
				' . ($use_ingroup ? '<input type="hidden" class="fcfield_value_holder" name="' . $valueholder_nm . '[' . $n . ']" id="' . $valueholder_id . '_' . $n . '" value="-">' : '') . '
				' . (!$add_ctrl_btns ? '' : '
				<div class="' . $input_grp_class . ' fc-xpended-btns">
					' . $move2 . '
					' . $remove_button . '
					' . (!$add_position ? '' : $add_here) . '
				</div>
				') . '
				' . ($use_ingroup ? '' : '<div class="fcclear"></div>') . '

				<div class="fcfield-relation-value_box" data-elementbase="' . $elementid . '" data-elementid="' . $elementid_n . '" data-item_id="' . $item->id . '" data-field_id="' . $field->id . '" data-item_type="' . $item->type_id . '"  data-item_lang="' . $item->language . '">

					<div class="' . $input_grp_class . ' fc-xpended-row fcfield-relation-cat_selector_box" ' . $cat_selecor_box_style . '>
						<label class="' . $add_on_class . ' fc-lbl cat_selector-lbl" id="' . $elementid_n . '_cat_selector-lbl" for="' . $elementid_n . '_cat_selector">' . JText::_('FLEXI_CATEGORY') . '</label>
						' . str_replace('__ELEMENTID__', $elementid_n, $cat_selector) . '
					</div>

					<div class="' . $input_grp_class . ' fc-xpended-row fcfield-relation-item_selector_box">
						<label class="' . $add_on_class . ' fc-lbl item_selector-lbl" id="' . $elementid_n . '_item_selector-lbl" for="' . $elementid_n . '_item_selector">' . JText::_('FLEXI_RIFLD_ITEMS') . '</label>
						<select id="' . $elementid_n . '_item_selector" name="' . $elementid_n . '_item_selector" class="use_select2_lib fcfield-relation-item_selector" onchange="return fcfield_relation.add_related(this);">
							<option value="">-</option>
						</select>
					</div>

					<div class="' . $input_grp_class . ' fc-xpended-row fcfield-relation-selected_items_box">
						<label class="' . $add_on_class . ' fc-lbl selected_items-lbl" id="' . $elementid_n . '-lbl" for="' . $elementid_n . '">' . JText::_($selected_items_label) . '</label>
						<select id="' . $elementid_n . '" name="' . $fieldname_n . '" ' . ($multiple ? 'multiple="multiple" ' : '')
							. ' class="' . $classes . ' fcfield-relation-selected_items" ' . $attribs
							. ' onchange="return fcfield_relation.selected_items_modified(this);">
							' . $items_options_select[$n] . '
						</select>
						' . ($selected_items_sortable ? '
						<span class="add-on"><span class="icon-info hasTooltip" title="' . JText::_('FLEXI_FIELD_ALLOW_SORTABLE_INFO') . '"></span>' . JText::_('FLEXI_ORDER') . '</span>' : '') . '
					</div>

				</div>
				';

			// If using single category then trigger loading the items selector
			$per_val_js .= count($allowedtree) === 1 ? "
					jQuery('#" . $elementid_n . "_cat_selector').trigger('change');
				" : '';
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
			var uniqueRowNum" . $field->id . "	= " . count($field->value) . ";  // Unique row number incremented only
			var rowCount" . $field->id . "	= " . count($field->value) . ";      // Counts existing rows to be able to limit a max number of values
			var maxValues" . $field->id . " = " . $max_values . ";
		";
		$js .= "
			var sessionToken" . $field->id . " = '" . JSession::getFormToken() . "';
		";
		if ($js) $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);


		// Do not convert the array to string if field is in a group
		if ($use_ingroup) ;

		// Handle multiple records
		elseif ($multiple)
		{
			$field->html = !count($field->html) ? '' :
				'<li class="' . $value_classes . '">' .
				implode('</li><li class="' . $value_classes . '">', $field->html) .
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_' . $field->id . '">' . $field->html . '</ul>';
			if (!$add_position) $field->html .= '
				<div class="input-append input-prepend fc-xpended-btns">
					<span class="fcfield-addvalue ' . $font_icon_class . ' fccleared" onclick="addField' . $field->id . '(jQuery(this).closest(\'.fc-xpended-btns\').get(0));" title="' . JText::_('FLEXI_ADD_TO_BOTTOM') . '">
						' . JText::_('FLEXI_ADD_VALUE') . '
					</span>
				</div>';
		}

		// Handle single values
		else
		{
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_' . $field->id . '">
				' . (isset($field->html[-1]) ? $field->html[-1] : '') . $field->html[0] . '
			</div>';
		}
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if (!in_array($field->field_type, static::$field_types)) return;

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

			$app      = Factory::getApplication();
			$document = Factory::getDocument();
			$option   = $app->input->getCmd('option', '');
			$format   = $app->input->getCmd('format', 'html');
			$realview = $app->input->getCmd('view', '');
		}

		// Current view variable
		$view = $app->input->getCmd('flexi_callview', ($realview ?: 'item'));
		$sfx  = $view === 'item' ? '' : '_cat';

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
		$multiple    = $use_ingroup || (int) $field->parameters->get('allow_multiple', 0);


		/**
		 * Get field values
		 */

		$values = $values ? $values : $field->value;

		// Check for no values and no default value, and return empty display
		if (empty($values))
		{
			$values = $this->getDefaultValues($isform = false, $_translate = false, $_split = ',');
		}


		/**
		 * Calculate access for item list and auto relation button
		 */

		static $has_itemslist_access = array();
		if (!isset($has_itemslist_access[$field->id]))
		{
			$aid_arr                          = JAccess::getAuthorisedViewLevels(Factory::getUser()->id);
			$acclvl                           = (int) $field->parameters->get('itemslist_acclvl', 1);
			$has_itemslist_access[$field->id] = in_array($acclvl, $aid_arr);
		}

		static $has_auto_relate_access = array();
		if (!isset($has_auto_relate_access[$field->id]))
		{
			$aid_arr                            = JAccess::getAuthorisedViewLevels(Factory::getUser()->id);
			$acclvl                             = (int) $field->parameters->get('auto_relate_acclvl', 1);
			$has_auto_relate_access[$field->id] = in_array($acclvl, $aid_arr);
		}


		/**
		 * Decide what to display
		 *  - total information
		 *  - item list
		 *  - auto relation button
		 */

		$disp = new stdClass();
		$HTML = new stdClass();

		// Total information
		$show_total_only     = (int) $field->parameters->get('show_total_only', 0);
		$total_show_auto_btn = $field->field_type !== 'relation' ? 0 : (int) $field->parameters->get('total_show_auto_btn', 0);
		$total_show_list     = $field->field_type !== 'relation' ? 0 : (int) $field->parameters->get('total_show_list', 0);

		if ($prop == 'display_total')  // Explicitly requested
		{
			$disp->total_info = true;
		}
		elseif ($show_total_only == 1 || ($show_total_only == 2 && (empty($values) || $field->field_type === 'relation_reverse')))  // For relation reverse we will count items inside the layout
		{
			$app      = Factory::getApplication();
			$option   = $app->input->get('option', '', 'cmd');
			$realview = $app->input->get('view', 'item', 'cmd');
			$view     = $app->input->get('flexi_callview', $realview, 'cmd');

			$total_in_view    = $field->parameters->get('total_in_view', array('backend'));
			$total_in_view    = FLEXIUtilities::paramToArray($total_in_view);
			$disp->total_info = (static::$isItemsManager && in_array('backend', $total_in_view)) || in_array($view, $total_in_view);
		}
		else
		{
			$disp->total_info = false;
		}

		// Auto-relate submit button
		// NOTE: this is not applicable for 'relation_reverse' & 'autorelationfilters' field, and parameter does not exist for this field,
		$submit_related_curritem    = $field->parameters->get('auto_relate_curritem', 0);
		$submit_related_menu_itemid = $field->parameters->get('auto_relate_menu_itemid', 0);
		$submit_related_position    = $field->parameters->get('auto_relate_position', 0);

		$disp->submit_related_btn = $submit_related_curritem && $submit_related_menu_itemid
			&& $has_auto_relate_access[$field->id] && (!$disp->total_info || $total_show_auto_btn);

		// Item list
		$disp->item_list = $has_itemslist_access[$field->id] && (!$disp->total_info || $total_show_list);


		/*
		 * Prepare item list data for rendering the related items list
		 */

		$relation_field_id = $field->parameters->get('reverse_field', 0);

		if ($field->field_type === 'relation_reverse')
		{
			// Check that relation field to be reversed was configured
			if (!$relation_field_id)
			{
				$field->{$prop} = '<div class="alert alert-warning">' . $field->label . ': ' . JText::_('FLEXI_RIFLD_NO_FIELD_SELECTED_TO_BE_REVERSED') . '</div>';

				return;
			}

			// Always ignore passed items, the DB query will determine the items
			$related_items_sets = array(null);
		}

		else  // $field->field_type === 'autorelationfilters' || $field->field_type === 'relation'
		{
			// Parse values
			$values = $this->parseValues($values);

			// Get limit of displayed items
			$item_limit = (int) $field->parameters->get('itemcount', 0);

			// Get related items IDs and their category ID
			$itemids_sets       = null;
			$related_items_sets = $this->parseRelatedItems($values, $item_limit, $itemids_sets);
		}


		// ***
		// *** Get related items data and their display HTML as an array of items
		// *** NOTE: this is not moved to layout because in future it could be optimized
		// ***       to retrieve related items for all items in category view with single query
		// ***

		$options = new stdClass();

		if ($disp->item_list || $disp->total_info)
		{
			// 0: return string with related items HTML,
			// 1: return related items array,
			// 2: same as 1 but also means do not create HTML display,
			// 3: same as 2 but also do not get any item data
			$options->return_items_array = $disp->item_list ? 1 : 3;

			// Override the item list HTML parameter ... with the one meant to be used when showing total
			if ($disp->total_info && $field->parameters->get('total_relitem_html', null))
			{
				$field->parameters->set('relitem_html_override', 'total_relitem_html');
			}

			// Get related items data and also create the item's HTML display per item (* see above)
			foreach ($related_items_sets as $n => $related_items)
			{
				$related_items_sets[$n] = FlexicontentFields::getItemsList($field->parameters, $related_items, $field, $item, $options);
				//echo '<div class="alert alert-warning"><h2>related_items html: ' . $field->label . '</h2><pre>'; print_r(array_keys($related_items_sets[$n])); echo '</pre></div>';
			}
		}

		// Compatibility with legacy layouts
		$related_items = count($related_items_sets) ? reset($related_items_sets) : null;


		/**
		 * Get common parameters like: itemprop, value's prefix (pretext), suffix (posttext), separator, value list open/close text (opentag, closetag)
		 * This will replace other field values and item properties, if such are found inside the parameter texts
		 */
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);

		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_' . $viewlayout : 'value_default';

		// Create field's viewing HTML, using layout file
		$field->{$prop} = array();
		include(self::getViewPath($field->field_type, $viewlayout));

		// Do not convert the array to string if field is in a group, and do not add: FIELD's opentag, closetag, value separator
		// NOTE the 'value' list (aka in our case an item list) was created inside the LAYOUT
		// ... so the opentag, closetag, value separator were used inside the layout ALREADY
		if (!$is_ingroup)
		{
			// Implode data without adding any ... separator !!!
			$field->{$prop} = implode('', $field->{$prop});

			if ($field->{$prop} !== '')
			{
				// Add microdata once for all values, if field -- is NOT -- in a field group
				if ($itemprop)
				{
					$field->{$prop} = '<div style="display:inline" itemprop="' . $itemprop . '" >' . $field->{$prop} . '</div>';
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
	public function onBeforeSaveField(&$field, &$post, &$file, &$item)
	{
		if (!in_array($field->field_type, static::$field_types)) return;

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!is_array($post) && !strlen($post) && !$use_ingroup) return;

		$max_values          = $use_ingroup ? 0 : (int) $field->parameters->get('max_values', 0);
		$multiple            = $use_ingroup || (int) $field->parameters->get('allow_multiple', 0);
		$is_importcsv        = Factory::getApplication()->get('task', '', 'cmd') == 'importcsv';
		$field->use_suborder = $multiple;

		//Factory::getApplication()->enqueueMessage($field->label . ' (before): <pre>' . print_r($post, true) . '</pre>', 'notice');


		// ***
		// *** Reformat the posted data
		// ***

		// Make sure posted data is an array
		$post = !is_array($post) ? array($post) : $post;

		// Account for fact that ARRAY form elements are not submitted if they do not have a value
		if ($multiple)
		{
			$empty_value = array();
			$custom      = Factory::getApplication()->input->get('custom', array(), 'array');

			if (isset($custom['_fcfield_valueholder_'][$field->name]))
			{
				$holders = $custom['_fcfield_valueholder_'][$field->name];
				$vals    = array();

				foreach ($holders as $i => $v)
				{
					$vals[] = isset($post[(int) $i])
						? $post[(int) $i]
						: $empty_value;
				}

				$post = $vals;
			}
		}

		//Factory::getApplication()->enqueueMessage($field->label . ' (after): <pre>' . print_r($post, true) . '</pre>', 'notice');
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField(&$field, &$post, &$file, &$item)
	{
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item)
	{
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value = '', $formName = 'searchForm')
	{
		if (!in_array($filter->field_type, static::$field_types)) return;

		// No special SQL query, default query is enough since index data were formed as desired, during indexing
		$indexed_elements = true;
		FlexicontentFields::createFilter($filter, $value, $formName, $indexed_elements);
	}


	// Method to display a category filter for the category view
	public function onDisplayFilter(&$filter, $value = '', $formName = 'adminForm', $isSearchView = 0)
	{
		if (!in_array($filter->field_type, static::$field_types)) return;

		$db       = Factory::getDbo();
		$_nowDate = 'UTC_TIMESTAMP()';
		$nullDate = $db->getNullDate();

		// Create order clause dynamically based on the field settings
		$order   = $filter->parameters->get('orderby', 'alpha');
		$orderby = flexicontent_db::buildItemOrderBy(
			$filter->parameters,
			$order, $request_var = '', $config_param = '',
			$item_tbl_alias = 'ct', $relcat_tbl_alias = 'rel',
			$default_order = '', $default_order_dir = '', $sfx = '_form', $support_2nd_lvl = false
		);

		// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
		// partial SQL clauses
		$filter->filter_valuesselect = ' ct.id AS value, ct.title AS text';
		$filter->filter_valuesfrom   = null;  // use default
		$filter->filter_valuesjoin   = ' JOIN #__content AS ct ON ct.id = fi.value_integer AND ct.state = 1 ' .
			' AND (ct.publish_up IS NULL OR ct.publish_up = ' . $db->Quote($nullDate) . ' OR ct.publish_up <= ' . $_nowDate . ')' .
			' AND (ct.publish_down IS NULL OR ct.publish_down = ' . $db->Quote($nullDate) . ' OR ct.publish_down >= ' . $_nowDate . ')';
		$filter->filter_valueswhere  = null;  // use default
		// full SQL clauses
		$filter->filter_groupby = ' GROUP BY fi.value_integer '; // * will be be appended with , fi.item_id
		$filter->filter_having  = null;  // use default
		$filter->filter_orderby = $orderby; // use field ordering setting

		FlexicontentFields::createFilter($filter, $value, $formName);
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	public function getFiltered(&$filter, $value, $return_sql = true)
	{
		if (!in_array($filter->field_type, static::$field_types)) return;

		// If we are filtering via a relation-reverse field, then get the ID of relation field
		if ($filter->field_type === 'relation_reverse')
		{
			$relation_field_id = (int) $filter->parameters->get('reverse_field', 0);

			if (!$relation_field_id)
			{
				echo '<div class="alert alert-warning">' . $filter->label . ': ' . JText::_('FLEXI_RIFLD_NO_FIELD_SELECTED_TO_BE_REVERSED') . '</div>';

				return null;
			}
		}
		elseif ($filter->field_type === 'relation')
		{
			$relation_field_id = $filter->id;
		}
		else
		{
			echo '<div class="alert alert-warning">Field type : ' . $filter->field_type . ' is not filterable </div>';

			return null;
		}

		//$value = !is_array($value) ? array($value) : $value;

		$is_relation    = $filter->field_type === 'relation';
		$ritem_field_id = key($value);
		$ritem_field_id = is_int($ritem_field_id) && $ritem_field_id < 0
			? -$ritem_field_id
			: 0;

		if (!$ritem_field_id)
		{
			if ($is_relation)
			{
				$filter->filter_colname     = ' rel.value_integer';
				$filter->filter_valuesjoin  = null;   // use default
				$filter->filter_valueformat = null;   // use default
			}
			else
			{
				return null;
			}
		}

		else
		{
			$rel                = 'relv';
			$c                  = 'c';
			$join_field_filters = '';

			// Find items that are directly / indirectly related via a RELATION / REVERSE RELATION field
			$match_rel_items    = $is_relation
				? $c . '.id = ' . $rel . '.item_id'
				: $c . '.id = ' . $rel . '.value_integer';
			$join_field_filters .= ' JOIN #__flexicontent_fields_item_relations AS ' . $rel . ' ON ' . $match_rel_items . ' AND ' . $rel . '.field_id = ' . $relation_field_id;

			$val_tbl      = $rel . '_ritems';
			$val_field_id = $ritem_field_id;

			// RELATED / REVERSE RELATED Items must have given values
			$val_on_items = $is_relation
				? $val_tbl . '.item_id = ' . $rel . '.value_integer'
				: $val_tbl . '.item_id = ' . $rel . '.item_id';

			// Join with values table 'ON' the current filter field id and 'ON' the items at interest ... below we will add an extra 'ON' clause to limit to the given field values
			$join_field_filters .= ' JOIN #__flexicontent_fields_item_relations AS ' . $val_tbl . ' ON ' . $val_on_items . ' AND ' . $val_tbl . '.field_id = ' . $val_field_id;

			$filter->filter_colname     = ' ' . $val_tbl . '.value';
			$filter->filter_valuesjoin  = $join_field_filters;
			$filter->filter_valueformat = null;   // use default
			$filter->filter_valuewhere  = null;   // use default
		}

		return FlexicontentFields::getFiltered($filter, $value, $return_sql);
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	public function getFilteredSearch(&$filter, $value, $return_sql = true)
	{
		if (!in_array($filter->field_type, static::$field_types)) return;

		$filter->isindexed = true;

		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}



	// ***
	// *** SEARCH INDEX METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	public function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if (!in_array($field->field_type, static::$field_types)) return;
		if (!$field->isadvsearch && !$field->isadvfilter) return;

		if ($post === null)
		{
			$values                    = null;
			$field->field_valuesselect = ' fi.value_integer AS value_id, ct.title AS value';
			$field->field_valuesjoin   = ' JOIN #__content AS ct ON ct.id = fi.value_integer';
			$field->field_groupby      = ' GROUP BY fi.value_integer ';
		}
		else if (!empty($post))
		{
			$_ids = array();
			foreach ($post as $_id) $_ids[] = (int) $_id;  // convert itemID:catID to itemID
			$db    = Factory::getDbo();
			$query = 'SELECT i.id AS value_id, i.title AS value FROM #__content AS i WHERE i.id IN (' . implode(',', $_ids) . ')';
			$_values = $db->setQuery($query)->loadAssocList();
			$values  = array();
			foreach ($_values as $v) $values[$v['value_id']] = $v['value'];
		}

		//Factory::getApplication()->enqueueMessage('ADV: '.print_r($values, true), 'notice');
		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties = array(), $search_properties = array(), $properties_spacer = ' ', $filter_func = null);

		return true;
	}

	// Method to create basic search index (added as the property field->search)
	public function onIndexSearch(&$field, &$post, &$item)
	{
		if (!in_array($field->field_type, static::$field_types)) return;
		if (!$field->issearch) return;

		if ($post === null)
		{
			$values                    = null;
			$field->field_valuesselect = ' fi.value_integer AS value_id, ct.title AS value';
			$field->field_valuesjoin   = ' JOIN #__content AS ct ON ct.id = fi.value_integer';
			$field->field_groupby      = ' GROUP BY fi.value_integer ';
		}
		else if (!empty($post))
		{
			$_ids = array();
			foreach ($post as $_id) $_ids[] = (int) $_id;  // convert itemID:catID to itemID
			$db    = Factory::getDbo();
			$query = 'SELECT i.id AS value_id, i.title AS value FROM #__content AS i WHERE i.id IN (' . implode(',', $_ids) . ')';
			$_values = $db->setQuery($query)->loadAssocList();
			$values  = array();
			foreach ($_values as $v) $values[$v['value_id']] = $v['value'];
		}

		//Factory::getApplication()->enqueueMessage('BAS: '.print_r($values, true), 'notice');
		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties = array(), $search_properties = array(), $properties_spacer = ' ', $filter_func = null);

		return true;
	}


	protected function getAllowedCategories($field)
	{
		// Get API objects / data
		$app  = Factory::getApplication();
		$user = Factory::getUser();

		// categories scope parameters
		$use_cat_acl = $field->parameters->get('use_cat_acl', 1);
		$method_cat  = $field->parameters->get('method_cat', 1);
		$usesubcats  = $field->parameters->get('usesubcats', 0);

		$catids = $field->parameters->get('catids');
		if (empty($catids)) $catids = array();
		else if (!is_array($catids)) $catids = !FLEXI_J16GE ? array($catids) : explode("|", $catids);


		// ***
		// *** Get & check Global category related permissions
		// ***

		require_once(JPATH_ROOT . DS . 'components' . DS . 'com_flexicontent' . DS . 'helpers' . DS . 'permission.php');
		$viewallcats = FlexicontentHelperPerm::getPerm()->ViewAllCats;


		// ***
		// *** Calculate categories to use for retrieving the items
		// ***

		$allowed_cats = $disallowed_cats = false;

		// Get user allowed categories
		$usercats = $use_cat_acl && !$viewallcats ?
			FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed = array('core.create', 'core.edit', 'core.edit.own'), $require_all = false, $check_published = true) :
			FlexicontentHelperPerm::returnAllCats($check_published = true, $specific_catids = null);

		// Find (if configured) , descendants of the categories
		if ($usesubcats)
		{
			global $globalcats;
			$_catids = array();
			foreach ($catids as $catid)
			{
				$subcats = $globalcats[$catid]->descendantsarray;
				foreach ($subcats as $subcat) $_catids[(int) $subcat] = 1;
			}
			$catids = array_keys($_catids);
		}


		// ***
		// *** Decided allowed categories according to method of CATEGORY SCOPE
		// ***

		// Include method
		if ($method_cat == 3)
		{
			$allowed = array_intersect($usercats, $catids);

			return $allowed;
		}

		// Exclude method
		else if ($method_cat == 2)
		{
			$allowed = array_diff($usercats, $catids);

			return $allowed;
		}

		// Neither INCLUDE / nor EXCLUDE method, return all user 's allowed categories
		else
		{
			return $usercats;
		}
	}


	public function getCategoryItems()
	{
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		// Get API objects / data
		$app  = Factory::getApplication();
		$user = Factory::getUser();
		$db   = Factory::getDbo();
		$view = $app->input->get('view', '', 'CMD');

		// Get Access Levels of user
		$uacc = array_flip(JAccess::getAuthorisedViewLevels($user->id));


		// Get request variables
		$field_id    = $app->input->get('field_id', 0, 'int');
		$item_id     = $app->input->get('item_id', 0, 'int');
		$type_id     = $app->input->get('type_id', 0, 'int');
		$lang_code   = $app->input->get('lang_code', 0, 'cmd');
		$catid       = $app->input->get('catid', 0, 'int');
		$customfilts = $app->input->get('customfilts', '', 'String');

		// Basic checks
		$response            = array();
		$response['error']   = '';
		$response['options'] = array();


		if (!$field_id)
		{
			$response['error'] = 'Invalid field_id';
		}

		elseif (!$catid)
		{
			$response['error'] = 'Invalid catid';
		}

		if ($response['error'])
		{
			jexit(json_encode($response));
		}


		/**
		 * Load and check field
		 */

		$field = JTable::getInstance($_type = 'flexicontent_fields', $_prefix = '', $_config = array());

		if (!$field->load($field_id))
		{
			$response['error'] = 'field not found';
		}

		elseif ($field->field_type !== 'relation')
		{
			$response['error'] = 'field id is not a relation field';
		}

		else
		{
			$is_editable = !$field->valueseditable || $user->authorise('flexicontent.editfieldvalues', 'com_flexicontent.field.' . $field->id);

			if (!$is_editable)
			{
				$response['error'] = 'you do not have permission to edit lthis field';
			}
		}

		if ($response['error'])
		{
			jexit(json_encode($response));
		}


		/**
		 * Load and check item
		 */

		$item = JTable::getInstance($_type = 'flexicontent_items', $_prefix = '', $_config = array());

		if (!$item_id)
		{
			$item->type_id    = $type_id;
			$item->language   = $lang_code;
			$item->created_by = $user->id;
		}

		elseif (!$item->load($item_id))
		{
			$response['error'] = 'content item not found';
		}

		else
		{
			$asset   = 'com_content.article.' . $item->id;
			$isOwner = $item->created_by == $user->get('id');
			$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);

			if (!$canEdit)
			{
				$response['error'] = 'content item has non-allowed access level';
			}
		}

		if ($response['error'])
		{
			jexit(json_encode($response));
		}


		// ***
		// *** Load field configuration
		// ***

		FlexicontentFields::loadFieldConfig($field, $item);
		$field->item_id = $item_id;

		// Some needed parameters
		$maxtitlechars = $field->parameters->get('maxtitlechars', 40);


		// Custom filters
		$values = [];
		$filter_ids = $this->getFilterIds($field);
		if ($customfilts)
		{
			parse_str($customfilts,$values);
		}

		if (!empty($filter_ids))
		{
			// Use category model to load the items
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'category.php');

			$_cid_saved = $app->input->get('cid');
			$app->input->set('cid', 0);
			$catmodel = new FlexicontentModelCategory();
			$app->input->set('cid', $_cid_saved);

			$field->parameters->set('use_filters', 1);
			$shown_filters  = FlexicontentFields::getFilters('itemselector_custom_filters', 'use_filters', $field->parameters, $check_access=false);
			$static_filters = FlexicontentFields::getFilters('static_filters', 'use_static_filters', $field->parameters, $check_access=false );

			$static_filter_vals = $field->parameters->get('use_static_filters')
				? FlexicontentFields::setFilterValues( $field->parameters, 'static_filters', $is_persistent=1, $set_method="array")
				: array();


			// ***
			// *** Merge shown and locked (static) filters
			// ***

			$filters = array();
			if ($shown_filters)  foreach ($shown_filters  as $filt_name => $filt) $filters[$filt->id] = $filt;
			if ($static_filters) foreach ($static_filters as $filt_name => $filt) $filters[$filt->id] = $filt;


			// ***
			// *** Get filter values
			// ***

			// 'values' contains filter values that were shown & set via item FORM
			// also get filter values that were statically set via field's configuration
			foreach($static_filter_vals as $filt_id => $filt_vals)
			{
				$values['filter_'.$filt_id] = $filt_vals;
			}


			// ***
			// *** Get SQL of filters
			// ***

			$filters_query = array();
			foreach($values as $filt_name => $filt_vals)
			{
				$filt_id = str_replace('filter_', '', $filt_name);

				// Skip filters without value or missing filters
				$empty_filt_vals_array  = is_array($filt_vals)  && !strlen(trim(implode('',$filt_vals)));
				$empty_filt_vals_string = !is_array($filt_vals) && !strlen(trim($filt_vals));

				if ( $empty_filt_vals_array || $empty_filt_vals_string ) continue;
				if ( !isset($filters[$filt_id]) )  continue;

				// Make sure value is an array
				if (!is_array($filt_vals))
				{
					$values[$filt_name] = $filt_vals =  array($filt_vals);
				}

				//echo $filters[$filt_id]->name . "[".$filt_id."]: ";
				//echo $filt_id.": "; print_r($filt_vals);
				$filters_query[$filt_id] = $catmodel->_getFiltered($filters[$filt_id], $filt_vals);
				//echo "<br/>".$filters_query[$filt_id] . "<br/><br/>\n";
			}


			// ***
			// *** Create item state limitations
			// ***

			$state_limit = '';
			/*$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
			$nullDate = $db->getNullDate();

			// Limit by publication state. Exception: when displaying personal user items or items modified by the user
			$state_limit = ' AND i.state IN (1, -5)';

			// Limit by publish up/down dates. Exception: when displaying personal user items or items modified by the user
			$state_limit .= ' AND ( ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' ) )';
			$state_limit .= ' AND ( ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' ) )';
			*/

			// ***
			// *** Execute a filtered SQL query to get list of items
			// ***

			$itemcount = $field->parameters->get('itemcount', 20000);

			$query = 'SELECT i.id, i.catid'
				. ' FROM #__flexicontent_items_tmp AS i'
				. ' WHERE 1=1 ' . $state_limit . implode(' ', $filters_query)
			;
			$db->setQuery($query, 0, $itemcount);
			$items_arr = $db->loadObjectList('id');
			//echo $query;
			//echo "<pre>"; print_r($items_arr); echo "</pre>"; exit;
		}


		// ***
		// *** SCOPE PARAMETERS
		// ***

		// NOTE: categories scope parameters ... not used here, since category scope is checked by calling getAllowedCategories()

		// types scope parameters
		$method_types = (int) $field->parameters->get('method_types', 1);
		$types        = $field->parameters->get('types');

		if (empty($types))
		{
			$types = array();
		}

		elseif (!is_array($types))
		{
			$types = explode('|', $types);
		}

		// other limits of scope parameters
		$samelangonly  = (int) $field->parameters->get('samelangonly', 1);
		$onlypublished = (int) $field->parameters->get('onlypublished', 1);
		$ownedbyuser   = (int) $field->parameters->get('ownedbyuser', 0);


		/**
		 * Item retrieving query ... CREATE WHERE CLAUSE according to configured limitations SCOPEs
		 */
		$where = array();

		// Exclude currently edited item
		if ($item_id)
		{
			$where[] = ' i.id <> ' . (int) $item_id;
		}


		/**
		 * CATEGORY SCOPE
		 */

		$allowed_cats = $this->getAllowedCategories($field);

		if (empty($allowed_cats))
		{
			jexit(json_encode(array(
				'error' => JText::_('FLEXI_CANNOT_EDIT_FIELD') . ': <br/> ' . JText::_('FLEXI_NO_ACCESS_TO_USE_CONFIGURED_CATEGORIES')
			)));
		}

		// Check given category (-ies) is in the allowed categories
		$request_catids = array($catid);
		$catids         = array_intersect($allowed_cats, $request_catids);

		if (empty($catids))
		{
			jexit(json_encode(array(
				'error' => JText::_('FLEXI_RIFLD_CATEGORY_NOT_ALLOWED')
			)));
		}

		// Also include subcategory items
		$subcat_items = (int) $field->parameters->get('subcat_items', 1);

		if ($subcat_items)
		{
			global $globalcats;
			$_catids = array();

			foreach ($catids as $catid)
			{
				$subcats = $globalcats[$catid]->descendantsarray;

				foreach ($subcats as $subcat)
				{
					$_catids[(int) $subcat] = 1;
				}
			}
			$catids = array_keys($_catids);
		}

		$where[] = ' rel.catid IN (' . implode(',', $catids) . ') ';


		/**
		 * TYPE SCOPE
		 */

		if (($method_types === 2 || $method_types === 3) && (!count($types) || empty($types[0])))
		{
			jexit(json_encode(array(
				'error' => 'Content Type scope is set to include/exclude but no Types are selected in field configuration, please set to "ALL" or select types to include/exclude'
			)));
		}

		// exclude method
		if ($method_types === 2)
		{
			$where[] = ' ie.type_id NOT IN (' . implode(',', $types) . ')';
		}

		// include method
		elseif ($method_types === 3)
		{
			$where[] = ' ie.type_id IN (' . implode(',', $types) . ')';
		}


		/**
		 * OTHER SCOPE LIMITATIONS
		 */

		if ($samelangonly)
		{
			$where[] = !$item->language || $item->language === '*'
				? ' i.language = ' . $db->Quote('*')
				: ' (i.language = ' . $db->Quote($item->language) . ' OR i.language = ' . $db->Quote('*') . ') ';
		}

		if ($onlypublished)
		{
			$where[] = ' i.state IN (1, -5) ';
		}

		if ($ownedbyuser === 1)
		{
			$where[] = ' i.created_by = ' . (int) $user->id;
		}
		elseif ($ownedbyuser === 2)
		{
			$where[] = ' i.created_by = ' . (int) $item->created_by;
		}


		/**
		 * Item retrieving query ... CREATE ORDERBY CLAUSE
		 */

		$order = $field->parameters->get('orderby_form', 'alpha');;   // TODO: add more orderings: commented, rated
		$orderby = flexicontent_db::buildItemOrderBy(
			$field->parameters,
			$order, $request_var = '', $config_param = '',
			$item_tbl_alias = 'i', $relcat_tbl_alias = 'rel',
			$default_order = '', $default_order_dir = '', $sfx = '_form', $support_2nd_lvl = false
		);

		// Create JOIN for ordering items by a most rated
		$orderby_join = in_array('author', $order) || in_array('rauthor', $order)
			? ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			: '';

		// Create JOIN for getting item types
		$types_join = $method_types > 1
			? ' LEFT JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id '
			: '';

		/**
		 * Item retrieving query ... put together and execute it
		 */

		// IF custom filter values: $customfilts and also configured filters: $filter_ids then limit to found item ids found via filtering
		if (!empty($values) && $filter_ids)
		{
			if (!empty($items_arr))
			{
				$where[] = ' i.id IN ('. implode(',' , array_keys($items_arr)) . ')';
			}
			else
			{
				jexit(json_encode($response));
			}
		}

		// Create the WHERE clause
		$where = !count($where)
			? ''
			: ' WHERE ' . implode(' AND ', $where);

		$query = 'SELECT i.id, i.catid, i.title, i.state, i.alias'
			. ' FROM #__content AS i '
			. $types_join
			. ' JOIN #__flexicontent_cats_item_relations AS rel on i.id=rel.itemid '
			. $orderby_join
			. $where
			. ' GROUP BY rel.itemid '
			. $orderby;
		$items_arr = $db->setQuery($query)->loadObjectList('id');

		// None found
		if (!$items_arr)
		{
			jexit(json_encode($response));
		}

		// Some configuration
		$prepend_item_state = (int) $field->parameters->get('itemselector_prepend_item_state', 0);
		$append_field_ids   = $field->parameters->get('itemselector_append_fields', []);
		$append_field_ids   = FLEXIUtilities::paramToArray($append_field_ids);
		$state_shortname    = array(1 => '', 0 => 'U', -1 => 'A', -3 => 'PE', -4 => 'OQ', -5 => 'IP');


		$append_vals   = [];
		$append_fields = [];
		if ($append_field_ids)
		{
			$item_ids = array_keys($items_arr);

			// Field's has_access flag
			$aid_arr  = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);

			// Column of has_access flag
			$select_access = ', CASE WHEN fi.access IN (0,' . $aid_list . ') THEN 1 ELSE 0 END AS has_access';

			$query = ' SELECT fi.*'
				. $select_access
				. ' FROM #__flexicontent_fields AS fi'
				. ' WHERE fi.id IN ("' . implode('","', $append_field_ids) . '")'
				. ' ORDER BY FIELD(fi.id, "' . implode('","', $append_field_ids) . '")';
			$append_fields = $db->setQuery($query)->loadObjectList('id');

			$indexed_field_types = array_flip(array('select', 'selectmultiple', 'radio', 'radioimage', 'checkbox', 'checkboximage'));
			foreach ($append_fields as $fld_id => $append_field)
			{
				// Load config
				$item_instance = null;
				FlexicontentFields::loadFieldConfig($append_field, $item_instance);
				$is_indexed_field = isset($indexed_field_types[$append_field->field_type]);

				// Get elements
				if ($is_indexed_field)
				{
					$item_pros         = false;
					$extra_props       = ($append_field->field_type == 'radioimage' || $append_field->field_type == 'checkboximage') ? array('image', 'valgrp', 'state') : array();
					$elements[$fld_id] = FlexicontentFields::indexedField_getElements($append_field, $item = null, $extra_props, $item_pros, $is_filter = true);
				}
				elseif ($append_field->field_type == 'text')
				{
					$elements[$fld_id] = false;
				}

				// Get values
				$query = $db->getQuery(true)
					->select('item_id, value')
					->from('#__flexicontent_fields_item_relations AS fval')
					->where('fval.field_id = ' . (int) $fld_id . ' AND fval.item_id IN (' . implode(',', $item_ids) . ')');
				$_vals = $db->setQuery($query)->loadObjectList();
				foreach($_vals as $v)
				{
					$append_vals[$fld_id][$v->item_id][] = $v->value;
				}
			}
		}

		foreach ($items_arr as $item_id => $itemdata)
		{
			$itemtitle = StringHelper::strlen($itemdata->title) > $maxtitlechars
				? StringHelper::substr($itemdata->title, 0, $maxtitlechars) . '...'
				: $itemdata->title;
			$append_field_text = [];
			foreach ($append_fields as $fld_id => $append_field)
			{
				if (isset($append_vals[$fld_id][$item_id])) foreach($append_vals[$fld_id][$item_id] as $v)
				{
					if (isset($elements[$fld_id][$v]))
						$append_field_text[] = $elements[$fld_id][$v]->text;
					else
						$append_field_text[] = $v;
				}
			}
			$itemtitle .= $append_field_text
				? ' [' . implode(', ', $append_field_text) . ']'
				: '';

			if ($prepend_item_state)
			{
				$statestr  = $state_shortname[$itemdata->state] ?? '';
				$statestr  = $statestr ? '[' . $statestr . ']' : '';
				$itemtitle = $statestr . $itemtitle . " ";
			}

			$itemid      = $itemdata->id;

			$response['options'][] = array('item_id' => $itemid, 'item_title' => $itemtitle);
		}

		//jexit(print_r($response, true));

		// Output the field
		jexit(json_encode($response));
	}

	/**
	 * Method called via AJAX to get dependent values
	 */

	public function getCategoryItems_v1()
	{
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		// Get API objects / data
		$app  = Factory::getApplication();
		$user = Factory::getUser();
		$db   = Factory::getDbo();

		// Get Access Levels of user
		$uacc = array_flip(JAccess::getAuthorisedViewLevels($user->id));


		// Get request variables
		$field_id    = $app->input->get('field_id', 0, 'int');
		$item_id     = $app->input->get('item_id', 0, 'int');
		$type_id     = $app->input->get('type_id', 0, 'int');
		$lang_code   = $app->input->get('lang_code', 0, 'cmd');
		$catid       = $app->input->get('catid', 0, 'int');
		$customfilts = $app->input->get('customfilts', '', 'String');

		// Basic checks
		$response            = array();
		$response['error']   = '';
		$response['options'] = array();


		if (!$field_id)
		{
			$response['error'] = 'Invalid field_id';
		}

		elseif (!$catid)
		{
			$response['error'] = 'Invalid catid';
		}

		if ($response['error'])
		{
			jexit(json_encode($response));
		}


		/**
		 * Load and check field
		 */

		$field = JTable::getInstance($_type = 'flexicontent_fields', $_prefix = '', $_config = array());

		if (!$field->load($field_id))
		{
			$response['error'] = 'field not found';
		}

		elseif ($field->field_type !== 'relation')
		{
			$response['error'] = 'field id is not a relation field';
		}

		else
		{
			$is_editable = !$field->valueseditable || $user->authorise('flexicontent.editfieldvalues', 'com_flexicontent.field.' . $field->id);

			if (!$is_editable)
			{
				$response['error'] = 'you do not have permission to edit lthis field';
			}
		}

		if ($response['error'])
		{
			jexit(json_encode($response));
		}


		/**
		 * Load and check item
		 */

		$item = JTable::getInstance($_type = 'flexicontent_items', $_prefix = '', $_config = array());

		if (!$item_id)
		{
			$item->type_id    = $type_id;
			$item->language   = $lang_code;
			$item->created_by = $user->id;
		}

		elseif (!$item->load($item_id))
		{
			$response['error'] = 'content item not found';
		}

		else
		{
			$asset   = 'com_content.article.' . $item->id;
			$isOwner = $item->created_by == $user->get('id');
			$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);

			if (!$canEdit)
			{
				$response['error'] = 'content item has non-allowed access level';
			}
		}

		if ($response['error'])
		{
			jexit(json_encode($response));
		}


		// ***
		// *** Load field configuration
		// ***

		FlexicontentFields::loadFieldConfig($field, $item);
		$field->item_id = $item_id;

		// Some needed parameters
		$maxtitlechars = $field->parameters->get('maxtitlechars', 40);


		// ***
		// *** SCOPE PARAMETERS
		// ***

		// NOTE: categories scope parameters ... not used here, since category scope is checked by calling getAllowedCategories()

		// types scope parameters
		$method_types = (int) $field->parameters->get('method_types', 1);
		$types        = $field->parameters->get('types');

		if (empty($types))
		{
			$types = array();
		}

		elseif (!is_array($types))
		{
			$types = explode('|', $types);
		}

		// other limits of scope parameters
		$samelangonly  = (int) $field->parameters->get('samelangonly', 1);
		$onlypublished = (int) $field->parameters->get('onlypublished', 1);
		$ownedbyuser   = (int) $field->parameters->get('ownedbyuser', 0);


		/**
		 * Item retrieving query ... CREATE WHERE CLAUSE according to configured limitations SCOPEs
		 */
		$where = array();

		// Exclude currently edited item
		if ($item_id)
		{
			$where[] = ' i.id <> ' . (int) $item_id;
		}


		/**
		 * CATEGORY SCOPE
		 */

		$allowed_cats = $this->getAllowedCategories($field);

		if (empty($allowed_cats))
		{
			jexit(json_encode(array(
				'error' => JText::_('FLEXI_CANNOT_EDIT_FIELD') . ': <br/> ' . JText::_('FLEXI_NO_ACCESS_TO_USE_CONFIGURED_CATEGORIES')
			)));
		}

		// Check given category (-ies) is in the allowed categories
		$request_catids = array($catid);
		$catids         = array_intersect($allowed_cats, $request_catids);

		if (empty($catids))
		{
			jexit(json_encode(array(
				'error' => JText::_('FLEXI_RIFLD_CATEGORY_NOT_ALLOWED')
			)));
		}

		// Also include subcategory items
		$subcat_items = (int) $field->parameters->get('subcat_items', 1);

		if ($subcat_items)
		{
			global $globalcats;
			$_catids = array();

			foreach ($catids as $catid)
			{
				$subcats = $globalcats[$catid]->descendantsarray;

				foreach ($subcats as $subcat)
				{
					$_catids[(int) $subcat] = 1;
				}
			}
			$catids = array_keys($_catids);
		}

		$where[] = ' rel.catid IN (' . implode(',', $catids) . ') ';


		/**
		 * TYPE SCOPE
		 */

		if (($method_types === 2 || $method_types === 3) && (!count($types) || empty($types[0])))
		{
			jexit(json_encode(array(
				'error' => 'Content Type scope is set to include/exclude but no Types are selected in field configuration, please set to "ALL" or select types to include/exclude'
			)));
		}

		// exclude method
		if ($method_types === 2)
		{
			$where[] = ' ie.type_id NOT IN (' . implode(',', $types) . ')';
		}

		// include method
		elseif ($method_types === 3)
		{
			$where[] = ' ie.type_id IN (' . implode(',', $types) . ')';
		}


		/**
		 * OTHER SCOPE LIMITATIONS
		 */

		if ($samelangonly)
		{
			$where[] = !$item->language || $item->language === '*'
				? ' i.language = ' . $db->Quote('*')
				: ' (i.language = ' . $db->Quote($item->language) . ' OR i.language = ' . $db->Quote('*') . ') ';
		}

		if ($onlypublished)
		{
			$where[] = ' i.state IN (1, -5) ';
		}

		if ($ownedbyuser === 1)
		{
			$where[] = ' i.created_by = ' . (int) $user->id;
		}
		elseif ($ownedbyuser === 2)
		{
			$where[] = ' i.created_by = ' . (int) $item->created_by;
		}


		/**
		 * Item retrieving query ... CREATE ORDERBY CLAUSE
		 */

		$order = $field->parameters->get('orderby_form', 'alpha');;   // TODO: add more orderings: commented, rated
		$orderby = flexicontent_db::buildItemOrderBy(
			$field->parameters,
			$order, $request_var = '', $config_param = '',
			$item_tbl_alias = 'i', $relcat_tbl_alias = 'rel',
			$default_order = '', $default_order_dir = '', $sfx = '_form', $support_2nd_lvl = false
		);

		// Create JOIN for ordering items by a most rated
		$orderby_join = in_array('author', $order) || in_array('rauthor', $order)
			? ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			: '';

		// Create JOIN for getting item types
		$types_join = $method_types > 1
			? ' LEFT JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id '
			: '';

		// Custom filters
		$customfilts_join = '';
		$filter_ids = $this->getFilterIds($field);
		if ($filter_ids && $customfilts)
		{
			parse_str($customfilts,$values);

			$filters    = $db->setQuery($db->getQuery(true)
				->select('id, field_type')->from('#__flexicontent_fields')->where('id IN (' . implode(',', $filter_ids) . ')')
			)->loadObjectList('id');

			foreach($values as $filt_name => $filt_vals)
			{
				$filt_id = str_replace('filter_', '', $filt_name);

				// Skip filters without value or missing filters
				$empty_filt_vals_array  = is_array($filt_vals)  && !strlen(trim(implode('',$filt_vals)));
				$empty_filt_vals_string = !is_array($filt_vals) && !strlen(trim($filt_vals));

				if ( $empty_filt_vals_array || $empty_filt_vals_string ) continue;
				if ( !isset($filters[$filt_id]) )  continue;

				$fval             = 'fval_' . (int) $filt_id;
				$customfilts_join .= " JOIN #__flexicontent_fields_item_relations AS {$fval} on i.id = {$fval}.item_id AND {$fval}.field_id = " . (int) $filt_id;
				$quoted_vals      = [];
				foreach ($filt_vals as $v) $quoted_vals[] = $db->q($v);
				if ($filters[(int) $filt_id]->field_type == 'relation')
				{
					$where[] = " {$fval}.value_integer IN (" . implode(', ', $quoted_vals) . ")";
				}
				else
				{
					$where[] = " {$fval}.value IN (" . implode(', ', $quoted_vals) . ")";
				}
			}
		}


		/**
		 * Item retrieving query ... put together and execute it
		 */

		// Create the WHERE clause
		$where = !count($where)
			? ''
			: ' WHERE ' . implode(' AND ', $where);

		$query = 'SELECT i.id, i.catid, i.title, i.state, i.alias'
			. ' FROM #__content AS i '
			. $types_join
			. ' JOIN #__flexicontent_cats_item_relations AS rel on i.id=rel.itemid '
			. $orderby_join
			. $customfilts_join
			. $where
			. ' GROUP BY rel.itemid '
			. $orderby;
		$items_arr = $db->setQuery($query)->loadObjectList();

		// Some configuration
		$prepend_item_state = (int) $field->parameters->get('itemselector_prepend_item_state', 0);
		$append_field_ids      = $field->parameters->get('itemselector_append_fields', []);
		$append_field_ids      = FLEXIUtilities::paramToArray($append_field_ids);
		$state_shortname    = array(1 => '', 0 => 'U', -1 => 'A', -3 => 'PE', -4 => 'OQ', -5 => 'IP');

		foreach ($items_arr as $itemdata)
		{
			$itemtitle = StringHelper::strlen($itemdata->title) > $maxtitlechars
				? StringHelper::substr($itemdata->title, 0, $maxtitlechars) . '...'
				: $itemdata->title;
			$append_field_text = [];
			foreach ($append_field_ids as $append_field)
			{
				$append_field_text[] = $append_field;
			}
			$itemtitle .= $append_field_text
				? ' [' . implode(', ', $append_field_text) . ']'
				: '';

			if ($prepend_item_state)
			{
				$statestr  = $state_shortname[$itemdata->state] ?? '';
				$statestr  = $statestr ? '[' . $statestr . ']' : '';
				$itemtitle = $statestr . $itemtitle . " ";
			}

			$itemid      = $itemdata->id;

			$response['options'][] = array('item_id' => $itemid, 'item_title' => $itemtitle);
		}

		//jexit(print_r($response, true));

		// Output the field
		jexit(json_encode($response));
	}


	// Parses and returns fields values, unserializing them if serialized
	protected function parseValues($_values)
	{
		$use_ingroup = $this->field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $this->field->parameters->get('allow_multiple', 0);

		// Make sure we have an array of values
		if (!$_values)
		{
			$vals = array(array());
		}
		else
		{
			$vals = !is_array($_values)
				? array($_values)
				: $_values;
		}

		// Compatibility with legacy storage, we no longer serialize all values to one, this way the field can be reversed and filtered
		if (count($vals) === 1 && is_string(reset($vals)))
		{
			$array = $this->unserialize_array(reset($vals), $force_array = false, $force_value = false);
			$vals  = $array ?: $vals;
		}

		// Force multiple value format (array of arrays)
		if (!$multiple)
		{
			if (is_string(reset($vals)))
			{
				$vals = array($vals);
			}
		}
		else
		{
			foreach ($vals as & $v)
			{
				if (!is_array($v))
				{
					$v = strlen($v) ? array($v) : array();
				}
			}
			unset($v);
		}

		//echo '<div class="alert alert-info"><h2>parseValues(): ' . $this->field->label . '</h2><pre>'; print_r($vals); echo '</pre></div>';
		return $vals;
	}

	// Parses and returns fields values, unserializing them if serialized
	protected function parseRelatedItems($values, $item_limit, &$itemids_sets = null)
	{
		$related_items_sets = array();
		$itemids_sets       = array();

		foreach ($values as $n => $vals)
		{
			$related_items_sets[$n] = array();
			$itemids_sets[$n]       = array();

			if (!$vals)
			{
				continue;
			}

			// Limit list to desired max # items
			$max_vals = $item_limit > 0 && $item_limit <= count($vals)
				? $item_limit
				: count($vals);

			for ($i = 0; $i < $max_vals; $i++)
			{
				$v = $vals[$i];

				if (!$v)
				{
					continue;
				}

				list ($itemid, $catid) = explode(':', $v);
				$itemid                                  = (int) $itemid;
				$catid                                   = (int) $catid;
				$related_items_sets[$n][$itemid]         = new stdClass;
				$related_items_sets[$n][$itemid]->itemid = $itemid;
				$related_items_sets[$n][$itemid]->catid  = $catid;
				$related_items_sets[$n][$itemid]->value  = $v;
				$itemids_sets[$n][]                      = $itemid;
			}
		}

		//echo '<div class="alert alert-info"><h2>parseRelatedItems(): ' . $this->field->label . '</h2><pre>'; print_r($related_items_sets); echo '</pre></div>';
		return $related_items_sets;
	}


	/**
	 * Method to create the HTML container of a filter
	 *
	 * @param   array  $filter  Contains the configuration of the filter, most notable properties the 'label' text and the 'html' (form elements) of the filter
	 *
	 * @return  string   The created HTML container
	 *
	 * @since   3.3.0
	 */
	public function getFilterDisplay($filter)
	{
		$label_extra_class = isset($filter['label_extra_class']) ? $filter['label_extra_class'] : '';
		$label_extra_attrs = isset($filter['label_extra_attrs']) ? ArrayHelper::toString($filter['label_extra_attrs']) : '';

		if (!FLEXI_J40GE)
		{
			$label = $filter['label']
				? '<div class="add-on ' . $label_extra_class . '" ' . $label_extra_attrs . '>' . $filter['label'] . '</div>'
				: '';

			return '
				<div class="fc-filter nowrap_box">
					<div class="input-prepend input-append fc-xpended-row">
						' . $label . '
						' . $filter['html'] . '
					</div>
				</div>
			';
		}
		else
		{
			$label = $filter['label']
				? '<div class="input-group-text ' . $label_extra_class . '" ' . $label_extra_attrs . '>' . $filter['label'] . '</div>'
				: '';

			return '
				<div class="fc-filter nowrap_box">
					<div class="input-group fc-xpended-row">
						<div class="input-group-prepend">
						' . $label . '
							' . $filter['html'] . '
						</div>
					</div>
				</div>
			';
		}
	}


	function getCustomFilts($field, $elementid)
	{
		$app    = Factory::getApplication();
		$user   = Factory::getUser();
		$db     = Factory::getDbo();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$fcform = $jinput->get('fcform', 0, 'int');

		$p = $option . '.' . $view . '.';

		$this->_custom_filters = [];
		$filter_ids = $this->getFilterIds($field);
		if (empty($filter_ids))
		{
			return;
		}

		// Field's has_access flag
		$aid_arr  = JAccess::getAuthorisedViewLevels($user->id);
		$aid_list = implode(",", $aid_arr);

		// Column of has_access flag
		$select_access = ', CASE WHEN fi.access IN (0,' . $aid_list . ') THEN 1 ELSE 0 END AS has_access';

		$query = ' SELECT fi.*'
			. $select_access
			. ' FROM #__flexicontent_fields AS fi'
			. ' WHERE fi.id IN ("' . implode('","', $filter_ids) . '")'
			. ' ORDER BY FIELD(fi.id, "' . implode('","', $filter_ids) . '")';
		$custom_filters = $db->setQuery($query)->loadObjectList('id');

		foreach ($custom_filters as $filter)
		{
			$item_instance = null;
			FlexicontentFields::loadFieldConfig($filter, $item_instance);

			// Since the filter values, may or may not be an array, we need to use 'array' as filter
			$filter->value = $fcform ? $jinput->get('filter_' . $filter->id, null, 'array') : $app->getUserStateFromRequest($p . 'filter_' . $filter->id, 'filter_' . $filter->id, null, 'array');

			// Force value to be array
			if (!is_array($filter->value)) $filter->value = strlen($filter->value) ? array($filter->value) : array();
			// Convert array having a single zero length string, to array()
			if (count($filter->value) == 1 && !strlen(reset($filter->value))) $filter->value = array();
		}

		$this->_custom_filters = &$custom_filters;
		$this->renderFiltersHTML($elementid, $field);

		return $this->_custom_filters;
	}


	/**
	 * Method to get fields values of the fields used as extra columns of the item list
	 *
	 * @access public
	 * @return object
	 */
	function renderFiltersHTML($elementid, $field)
	{
		$app    = Factory::getApplication();
		$jinput = $app->input;

		$form_name = $formName = $elementid; //'item_form';

		// Set view to category before rendering the filters HTML
		$view = $jinput->get('view', '', 'cmd');

		$field->parameters->set('use_filters', 1);
		unset($this->_custom_filters['categories']);

		if ($field->parameters->get('use_static_filters'))
		{
			FlexicontentFields::setFilterValues($field->parameters, 'static_filters', $is_persistent = 1, $set_method = "array");
		}

		FlexicontentFields::setFilterValues($field->parameters, 'initial_filters', $is_persistent = 0);
		if (!empty($this->_custom_filters))
		{
			flexicontent_html::loadFramework('flexi_tmpl_common');
			foreach ($this->_custom_filters as $filter) $filter->parameters->set('filter_extra_attribs', ' onchange="fcfield_relation.filters_change(this);"');
			FlexicontentFields::renderFilters($field->parameters, $this->_custom_filters, $form_name);
		}
	}


	/**
	 * Method to get fields values of the fields used as extra columns of the item list
	 *
	 * @access public
	 * @return object
	 */
	function renderFiltersHTML_v1($elementid, $field)
	{
		$app    = Factory::getApplication();
		$jinput = $app->input;

		$formName = $elementid; //'adminForm';

		// Set view to category before rendering the filters HTML
		$view = $jinput->get('view', '', 'cmd');
		$jinput->set('view', 'category');

		$indexed_field_types = array_flip(array('select', 'selectmultiple', 'radio', 'radioimage', 'checkbox', 'checkboximage'));

		foreach ($this->_custom_filters as $filter)
		{

			$filter->isfilter = 1;
			$filter->parameters->set('faceted_filter', 0);
			$filter->parameters->set('display_filter_as', 0);
			$filter->parameters->set('display_label_filter', 0);
			$filter->parameters->set('label_filter_css', '');
			//$filter->parameters->set('display_label_filter', -1);
			//$filter->parameters->set('label_filter_css', 'add-on');
			$filter->parameters->set('filter_extra_attribs', ' onchange="fcfield_relation.filters_change(this);"');

			$is_indexed_field = isset($indexed_field_types[$filter->field_type]);
			$elements         = false;

			if ($is_indexed_field)
			{
				$item_pros   = false;
				$extra_props = ($filter->field_type == 'radioimage' || $filter->field_type == 'checkboximage') ? array('image', 'valgrp', 'state') : array();
				$elements    = FlexicontentFields::indexedField_getElements($filter, $item = null, $extra_props, $item_pros, $is_filter = true);
			}
			elseif ($filter->field_type == 'text')
			{
				$db       = Factory::getDbo();
				$query    = $db->getQuery(true)
						->select('DISTINCT value, value AS text')
						->from('#__flexicontent_fields_item_relations')
						->where('field_id = ' . (int) $filter->id) . ' AND value != ""';
				$elements = $db->setQuery($query)->loadObjectList();
			}
			else
			{
				$display_label_filter_override = (int) $field->parameters->get('show_filter_labels', 0);
				$display_label_filter_saved    = $filter->parameters->get('display_label_filter');
				if ($display_label_filter_override) $filter->parameters->set('display_label_filter', $display_label_filter_override); // suppress labels inside filter's HTML (hide or show all labels externally)

				// else ... filter default label behavior
				$filter->html = '';  // make sure filter HTML display is cleared
				$field_type   = $filter->iscore ? 'core' : $filter->field_type;
				//$results 	= $dispatcher->trigger('onDisplayFilter', array( &$filter, $filtervalue ));
				FLEXIUtilities::call_FC_Field_Func($field_type, 'onDisplayFilter', array(&$filter, $filter->value, $formName));
				$filter->parameters->set('display_label_filter', $display_label_filter_saved);
				continue;
			}

			// Check for error during getting indexed field elements
			if (!$elements && $is_indexed_field)
			{
				$filter->html = '';

				// Must retrieve variable here, and not before retrieving elements !
				$sql_mode = $filter->parameters->get('sql_mode', 0);

				if ($sql_mode && $item_pros > 0)
				{
					$filter->html = sprintf(JText::_('FLEXI_FIELD_ITEM_SPECIFIC_AS_FILTERABLE'), $filter->label);
				}
				else
				{
					$filter->html = $sql_mode
						? JText::_('FLEXI_FIELD_INVALID_QUERY')
						: JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
				}
				continue;
			}

			FlexicontentFields::createFilter($filter, $filter->value, $formName, $elements);
		}

		// Restore view
		$jinput->set('view', $view);
	}

	public function getFilterIds($field)
	{
		$filter_ids = $field->parameters->get('itemselector_custom_filters', []);
		if (!is_array($filter_ids))
		{
			$filter_ids = preg_split("/\s*,\s*/u", $filter_ids);
			if (empty($filter_ids[0]))
			{
				unset($filter_ids[0]);
			}
		}
		// Sanitize the given filter_ids ... just in case
		$filter_ids = array_filter($filter_ids, 'is_numeric');

		// array_flip to get unique filter ids as KEYS (due to flipping) ... and then array_keys to get filter_ids in 0,1,2, ... array
		$filter_ids = array_keys(array_flip($filter_ids));

		return $filter_ids;
	}
}
