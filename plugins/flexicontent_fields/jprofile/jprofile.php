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

class plgFlexicontent_fieldsJProfile extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	static $users = array();

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

		/**
		 * Check if displaying a user selections otherwise nothing to show in item form
		 */
		$displayed_user = (int) $field->parameters->get('displayed_user', 1);

		// 2: is allow user selection
		if ($displayed_user !== 2)
		{
			return;
		}

		// Initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$app      = JFactory::getApplication();
		$isSite   = $app->isClient('site');

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';


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
		$add_ctrl_btns = !$use_ingroup && $multiple && !$isSite;
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

		// Input field display
		$display_label_form = (int) $field->parameters->get( 'display_label_form', 1 ) ;

		// Create extra HTML TAG parameters for the form field
		$attribs = $field->parameters->get( 'extra_attributes', '' ) ;
		if ($auto_value) $attribs .= ' readonly="readonly" ';

		// Attribute for default value(s)
		if (!empty($default_values))
		{
			$attribs .= ' data-defvals="'.htmlspecialchars( implode('|||', $default_values), ENT_COMPAT, 'UTF-8' ).'" ';
		}

		// Custom HTML placed before / after form fields
		$pretext  = $field->parameters->get( 'pretext_form', '' ) ;
		$posttext = $field->parameters->get( 'posttext_form', '' ) ;

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
				var element_id = '" . $elementid . "__' + uniqueRowN;   // Use double underscore to avoid collisions ...
				var fname_pfx  = '" . $fieldname . "[' + uniqueRowN + ']';
				";

			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			$js .= "
			// Update the new user field
				/*var theInput = newField.find('input, select').first();
				var theInput_dv = theInput.attr('data-defvals');
				(theInput_dv && theInput_dv.length) ?
					theInput.val( theInput.attr('data-defvals') ) :
					theInput.val(".json_encode($default_value).") ;*/

				var theInput = newField.find('input.field-user-input-name');
				theInput.attr('id', element_id + '_');
				theInput.attr('value', '');

				var theDiv = newField.find('div.modal');
				var tagid = 'userModal_' + element_id + '_';
				theDiv.attr('id', tagid);

				var theInput = newField.find('input.field-user-input');
				theInput.attr('name', fname_pfx);
				theInput.attr('id', element_id + '__id');
				theInput.attr('value', '');
				";

			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();

				// Attach form validation on new element
				fc_validationAttach(newField);

				// Initialize user selection JS
				" . (!$isSite ? "
				jQuery(newField).find('.field-user-wrapper').fieldUser();
				fcfield_jprofile_initUserSelector(tagid);
				" : "") . "
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

			function fcfield_jprofile_initUserSelector(tagid)
			{
				 jQuery('#' + tagid).on('show.bs.modal', function() {
						 jQuery('body').addClass('modal-open');
						 jQuery('.modalTooltip').each(function(){;
								 var attr = jQuery(this).attr('data-placement');
								 if ( attr === undefined || attr === false ) jQuery(this).attr('data-placement', 'auto-dir top-left')
						 });
						 jQuery('.modalTooltip').tooltip({'html': true, 'container': '#' + tagid});
				 }).on('shown.bs.modal', function() {
						 var modalHeight = jQuery('div.modal:visible').outerHeight(true),
								 modalHeaderHeight = jQuery('div.modal-header:visible').outerHeight(true),
								 modalBodyHeightOuter = jQuery('div.modal-body:visible').outerHeight(true),
								 modalBodyHeight = jQuery('div.modal-body:visible').height(),
								 modalFooterHeight = jQuery('div.modal-footer:visible').outerHeight(true),
								 padding = document.getElementById(tagid).offsetTop,
								 maxModalHeight = (jQuery(window).height()-(padding*2)),
								 modalBodyPadding = (modalBodyHeightOuter-modalBodyHeight),
								 maxModalBodyHeight = maxModalHeight-(modalHeaderHeight+modalFooterHeight+modalBodyPadding);
						 if (modalHeight > maxModalHeight){;
								 jQuery('.modal-body').css({'max-height': maxModalBodyHeight, 'overflow-y': 'auto'});
						 }
				 }).on('hide.bs.modal', function () {
						 jQuery('body').removeClass('modal-open');
						 jQuery('.modal-body').css({'max-height': 'initial', 'overflow-y': 'initial'});
						 jQuery('.modalTooltip').tooltip('destroy');
				 });
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


		$classes  = ' fcfield_textval' . $required_class;

		// Set field to 'Automatic' on successful validation'
		if ($auto_value)
		{
			$classes = ' fcfield_auto_value ';
		}

		// Load JFormHelper
		jimport('joomla.form.helper');

		// Load JFormFieldUser
		JFormHelper::loadFieldClass('user');


		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();
		$n = 0;

		// These are unused in this field
		$skipped_vals = array();
		$per_val_js   = '';

		foreach ($field->value as $value)
		{
			if ( !strlen($value) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group

			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			$elementid_jf_n = $elementid.'__'.$n.'_';

			$xml_field = '<field name="'.$fieldname_n.'" type="user" ' . ($isSite ? ' readonly="true" ' : '') . '/>';
			$xml_form = '<form><fields name="attribs"><fieldset name="attribs">'.$xml_field.'</fieldset></fields></form>';

			$jform = new JForm('flexicontent_field.jprofile', array('control' => '', 'load_data' => true));
			$jform->load($xml_form);
			$jfield = new JFormFieldUser($jform);

			$jfield->setup(new SimpleXMLElement($xml_field), $value, '');

			$jfield_html = $jfield->input;
			$jfield_html = $pretext . '<div style="display: inline-block;">' . $jfield_html . '</div>' . $posttext;

			$field->html[] = '
				'.$jfield_html.'
				' . ($auto_value ? '<span class="fc-mssg-inline fc-info fc-nobgimage">' . JText::_('FLEXI_AUTO') . '</span>' : '') . '
				' . (!$add_ctrl_btns || $auto_value ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				');

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

		// Add field's custom CSS / JS
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


		if (empty(static::$users))
		{
			$users = array();
			jimport('joomla.user.helper');
			JFactory::getLanguage()->load('com_users', JPATH_SITE, 'en-GB', $force_reload = false);
			JFactory::getLanguage()->load('com_users', JPATH_SITE, null, $force_reload = false);
		}


		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;

		// Display parameters
		$displayed_user = $field->parameters->get('displayed_user', 1);


		/**
		 * Get field values
		 * But override field values if showing SELF (user) or AUTHOR of item
		 */

		$values = $values ? $values : $field->value;

		switch($displayed_user)
		{
			// Current user
			case 3:
				$values = array((int) JFactory::getUser()->id);
				break;

			// Values (user ids) selected in item form
			case 2:
				break;

			// Item's author
			default:
			case 1:
				$values = array((int) $item->created_by);
				break;
		}


		/**
		 * Get common parameters like: itemprop, value's prefix (pretext), suffix (posttext), separator, value list open/close text (opentag, closetag)
		 * This will replace other field values and item properties, if such are found inside the parameter texts
		 */
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);


		// CSV export: Create a simpler output
		if ($prop === 'csv_export')
		{
			$separatorf = ', ';
			$itemprop = false;
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
	/*function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}*/


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	/*function onAfterSaveField( &$field, &$post, &$file, &$item ) {
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}*/


	// Method called just before the item is deleted to remove custom item data related to the field
	/*function onBeforeDeleteField(&$field, &$item) {
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}*/



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	/*function onAdvSearchDisplayFilter(&$filter, $value = '', $formName = 'searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;
	}*/


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	/*function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;
	}*/



	// ***
	// *** SEARCH INDEX METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	/*function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		return true;
	}*/


	// Method to create basic search index (added as the property field->search)
	/*function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;
		return true;
	}*/



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	function getUserProfile_FC()
	{
		$authordescr_item_html = false;

		// Retrieve author configuration
		$authorparams = flexicontent_db::getUserConfig($item->created_by);

		// Render author profile
		if ($authordescr_itemid = $authorparams->get('authordescr_itemid'))
		{
			$app = JFactory::getApplication();
			$saved_view = $app->input->get('view', '', 'cmd');

			$app->input->set('view', 'module');
			$flexi_html_helper = new flexicontent_html();
			$authordescr_item_html = $flexi_html_helper->renderItem($authordescr_itemid);
			$app->input->set('view', $saved_view);
		}

		return $authordescr_item_html;
	}


	function getUserProfile_Joomla()
	{
		return 'getUserProfile_Joomla() is empty';
	}


	function getUserProfile_CB()
	{
		return 'getUserProfile_CB() is empty';
	}

}
