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

class plgFlexicontent_fieldsAddressint extends FCField
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

		$values = $this->parseValues($field->value);

		if (empty($values))
		{
			// Default value
			$field->value = array(0 => array(
				'autocomplete' => '',
				'addr_display' => '',
				'addr_formatted' => '',
				'name' => '',
				'addr1' => '',
				'addr2' => '',
				'addr3' => '',
				'city' => '',
				'state' => '',
				'province' => '',
				'zip' => '',
				'zip_suffix' => '',
				'country' => '',
				'lat' => '',
				'lon' => '',
				'url' => '',
				'zoom' => '',
				'custom_marker' => '',
				'marker_anchor' => ''
			));
			$values = $field->value;
		}
		$this->values = & $values;


		// MAP Engine to use in item form
		$mapapi_edit    = $field->parameters->get('mapapi_edit', 'googlemap');

		// Some parameter shortcuts
		$addr_edit_mode = $field->parameters->get('addr_edit_mode', 'plaintext');
		$edit_latlon    = (int) $field->parameters->get('edit_latlon',  1);
		$use_name       = (int) $field->parameters->get('use_name',     1);
		$use_addr2      = (int) $field->parameters->get('use_addr2',    1);
		$use_addr3      = (int) $field->parameters->get('use_addr3',    1);
		$use_usstate    = (int) $field->parameters->get('use_usstate',  1);
		$use_province   = (int) $field->parameters->get('use_province', 1);
		$use_zip_suffix = (int) $field->parameters->get('use_zip_suffix', 1);
		$use_country    = (int) $field->parameters->get('use_country',  1);

		$use_custom_marker   = (int) $field->parameters->get('use_custom_marker', 1);
		$default_marker_file = $field->parameters->get('default_marker_file', '');
		$custom_marker_path  = $field->parameters->get('custom_marker_path', 'modules/mod_flexigooglemap/assets/marker');

		// Map configuration
		$map_type   = $field->parameters->get('map_type', 'roadmap');
		$map_zoom   = (int) $field->parameters->get('map_zoom', 16);
		$map_width  = (int) $field->parameters->get('map_width_form', 350);
		$map_height = (int) $field->parameters->get('map_height_form', 250);

		// Get required properties from field configuration
		$required_props = $addr_edit_mode != 'plaintext'
			? $field->parameters->get('required_props', array())
			: $field->parameters->get('required_props_plaintext', array());

		$required_props = $required && !$required_props
			? array('address')
			: $required_props;

		// Google autocomplete search types drop down list (for geolocation)
		$list_ac_types = array(
			//''=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_ALL_SEARCH_TYPES',
			''=>'FLEXI_ALL',
			'address'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_ADDRESS',

			'geocode'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_GEOCODE',
			'establishment'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_BUSINESS',
			'(regions)'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_REGION',
			'(cities)'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_CITY',

			'busStop'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_BUS_STOP',
			'trainStation'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_TRAIN_STATION',
			'townhall'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_TOWN_HALL',
			'airport'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_AIRPORT',
			'country'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_COUNTRY',
			'city'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_CITY_OR_AREA',
		);


		// Load Countries ($list_countries) and US states ($list_states)
		include('tmpl_common' . DS . 'areas.php');

		$api_sfx = $mapapi_edit === 'googlemap' ? '' : '_algolia';

		// GET ALLOWED ac search types, note 'false' may have been saved for 'ac_type_allowed_list' due to legacy bug, so do not use heritage directly
		$ac_types_default     = $field->parameters->get('ac_types_default' . $api_sfx, '');
		$ac_type_allowed_list = $field->parameters->get('ac_type_allowed_list' . $api_sfx, false);

		$ac_type_allowed_list = $ac_type_allowed_list ?:
			($mapapi_edit === 'googlemap'
				? array('','geocode','address','establishment','(regions)','(cities)')
				: array('','address','busStop','trainStation','townhall','airport','country','city')
			);
		$ac_type_allowed_list = FLEXIUtilities::paramToArray($ac_type_allowed_list, false, false, true);


		// CET ALLOWED countries, with special check for single country
		$ac_country_default      = trim($field->parameters->get('ac_country_default', ''));
		$ac_country_allowed_list = trim($field->parameters->get('ac_country_allowed_list', ''));

		if (!strlen($ac_country_allowed_list))
		{
			// Empty array indicates all countries allowed
			$ac_country_allowed_list = array();
		}
		else
		{
			// Add default country and make the list unique
			$ac_country_allowed_list = $ac_country_default . ',' . $ac_country_allowed_list;
			$ac_country_allowed_list = array_unique(FLEXIUtilities::paramToArray($ac_country_allowed_list, "/[\s]*,[\s]*/", false, true));
		}

		$single_country = count($ac_country_allowed_list) === 1 && $ac_country_default === reset($ac_country_allowed_list)
			? $ac_country_default
			: false;

		// CREATE COUNTRY OPTIONS
		$_list = count($ac_country_allowed_list) ? array_flip($ac_country_allowed_list) : $list_countries;

		$allowed_country_names = array();
		$allowed_countries     = array('' => JText::_('FLEXI_SELECT'));

		foreach($_list as $country_code => $k)
		{
			$country_op = new stdClass;
			$country_op->value = $country_code;
			$country_op->text  = JText::_('PLG_FC_ADDRESSINT_CC_' . $country_code);

			$allowed_countries[] = $country_op;

			if (count($ac_country_allowed_list))
			{
				$allowed_country_names[] = $country_op->text;
			}
		}
		$countries_attribs = ''
			. ($single_country ? ' disabled="disabled" readonly="readonly"' : '')
			. ($mapapi_edit === 'googlemap' ? ' onchange="fcfield_addrint.toggle_USA_state(this);" ' : '');


		/**
		 * Create Image marker list
		 */
		if ($use_custom_marker)
		{
			$custom_marker_path_abs = JPATH::clean(JPATH_SITE . DS . $custom_marker_path. DS);
			$custom_marker_url_base = str_replace('\\', '/', JURI::root() . $custom_marker_path . '/');

			// Default marker
			if ($mapapi_edit === 'googlemap')
			{
				$custom_marker_default = 'https://maps.gstatic.com/mapfiles/api-3/images/spotlight-poi2.png';
			}
			elseif ($mapapi_edit === 'algolia')
			{
				$custom_marker_default = 'https://unpkg.com/leaflet@1.5.1/dist/images/marker-icon.png';
			}

			$imgs = JFolder::files($custom_marker_path_abs);

			if ($imgs)
			{
				$custom_markers = array('' => JText::_('FLEXI_SELECT'));
				foreach ($imgs as $custom_marker)
				{
					$custom_markers_op = new stdClass;
					$custom_markers[] = $custom_markers_op;

					// Use full path, this way we can change in the future to other folder ??
					$custom_markers_op->value = $custom_marker;
					$custom_markers_op->text  = $custom_marker;
				}
			}
			$marker_anchors = array(
				(object) array('value'=>'TopL', 'text'=>JText::_('FLEXI_TOP') . ' ' . JText::_('FLEXI_LEFT')),
				(object) array('value'=>'TopC', 'text'=>JText::_('FLEXI_TOP') . ' ' . JText::_('FLEXI_CENTER')),
				(object) array('value'=>'TopR', 'text'=>JText::_('FLEXI_TOP') . ' ' . JText::_('FLEXI_RIGHT')),
				(object) array('value'=>'MidL', 'text'=>JText::_('FLEXI_MIDDLE') . ' ' . JText::_('FLEXI_LEFT')),
				(object) array('value'=>'MidC', 'text'=>JText::_('FLEXI_MIDDLE') . ' ' . JText::_('FLEXI_CENTER')),
				(object) array('value'=>'MidR', 'text'=>JText::_('FLEXI_MIDDLE') . ' ' . JText::_('FLEXI_RIGHT')),
				(object) array('value'=>'BotL', 'text'=>JText::_('FLEXI_BOTTOM') . ' ' . JText::_('FLEXI_LEFT')),
				(object) array('value'=>'BotC', 'text'=>JText::_('FLEXI_BOTTOM') . ' ' . JText::_('FLEXI_CENTER')),
				(object) array('value'=>'BotR', 'text'=>JText::_('FLEXI_BOTTOM') . ' ' . JText::_('FLEXI_RIGHT')),
			);
		}

		// CREATE AC SEARCH TYPE OPTIONS
		$ac_type_options = '';
		foreach($ac_type_allowed_list as $ac_type)
		{
			$lbl = $list_ac_types[$ac_type];
			$ac_type_options .= '<option value="'.htmlspecialchars($ac_type, ENT_COMPAT, 'UTF-8').'"  '.($ac_type == $ac_types_default ? 'selected="selected"' : '').'>'.JText::_($lbl)."</option>\n";
		}

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);

		// JS & CSS of current field
		if ($mapapi_edit === 'googlemap')
		{
			$conf_ops = array();

			if (count($ac_country_allowed_list))
			{
				$conf_ops[] = 'country:[\'' . implode("', '", $ac_country_allowed_list) . '\']';
			}

			$js = '
				fcfield_addrint.allowed_countries["'.$field_name_js.'"] = new Array('.(count($ac_country_allowed_list) ? '"' . implode('", "', $ac_country_allowed_list) . '"' : '').');
				fcfield_addrint.single_country["'.$field_name_js.'"] = "' . $single_country . '";

				fcfield_addrint.map_zoom["'.$field_name_js.'"] = ' . $map_zoom . ';
				fcfield_addrint.map_type["'.$field_name_js.'"] = "' . strtoupper($map_type) . '";

				fcfield_addrint.configure["' . $field_name_js . '"] = function(placesAutocomplete)
				{
					if (' . ($conf_ops ? 1 : 0)  . ') placesAutocomplete.setComponentRestrictions({' . implode(', ', $conf_ops) . '});
				}
			';
			$css = '';
		}
		else // ($mapapi_edit === 'algolia')
		{
			$algolia_api_id  = $field->parameters->get('algolia_edit_api_id', '');
			$algolia_api_key = $field->parameters->get('algolia_edit_api_key', '');

			$js = '
				fcfield_addrint.algolia_api_id["'.$field_name_js.'"]  = "' . $algolia_api_id . '";
				fcfield_addrint.algolia_api_key["'.$field_name_js.'"] = "' . $algolia_api_key . '";
			';

			$conf_ops = array();
			$ac_types_default = $field->parameters->get('ac_types_default_algolia', '');

			if (count($ac_country_allowed_list))
			{
				$conf_ops[] = 'countries:[\'' . implode("', '", $ac_country_allowed_list) . '\']';
			}

			if (!$ac_types_default)
			{
				$conf_ops[] = 'type:[\'' . implode("', '", array_filter($ac_type_allowed_list)) . '\']';
			}
			else
			{
				$conf_ops[] = 'type:\'' . $ac_types_default . '\'';
			}

			$js .= '
				fcfield_addrint.configure["' . $field_name_js . '"] = function(placesAutocomplete)
				{
					placesAutocomplete.configure({' . implode(', ', $conf_ops) . '});
				}
			';

			$css = '
			body .ap-suggestion {
				height: 2em !important;
				font-size: 12px !important;
				line-height: 1em !important;
			}
			';
		}

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

				// Destroy any select2 elements
				var sel2_elements = newField.find('div.select2-container');
				if (sel2_elements.length)
				{
					sel2_elements.remove();
					newField.find('select.use_select2_lib, select.has_select2_lib').select2('destroy').show();
				}

				// New element's field name and id
				var uniqueRowN = uniqueRowNum" . $field->id . ";
				var element_id = '" . $elementid . "_' + uniqueRowN;
				var fname_pfx  = '" . $fieldname . "[' + uniqueRowN + ']';
				";

			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			$js .=

			/**
			 * Update non-optional properties
			 * Latitude, Longtitude, Directions URL
			 */
				"
				theInput = newField.find('input.addrint_lat').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[lat]');
				theInput.attr('id', element_id + '_lat');
				newField.find('.addrint_lat-lbl').first().attr('for', element_id + '_lat');

				theInput = newField.find('input.addrint_lon').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[lon]');
				theInput.attr('id', element_id + '_lon');
				newField.find('.addrint_lon-lbl').first().attr('for', element_id + '_lon');

				theInput = newField.find('input.addrint_url').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[url]');
				theInput.attr('id', element_id + '_url');
				newField.find('.addrint_url-lbl').first().attr('for', element_id + '_url');
				" .

			/**
			 * Address format: 'plaintext'
			 */
				"
				theArea = newField.find('.addrint_addr_display').first();
				theArea.val('');
				theArea.attr('name', fname_pfx + '[addr_display]');
				theArea.attr('id', element_id + '_addr_display');
				newField.find('.addrint_addr_display-lbl').first().attr('for', element_id + '_addr_display');
				" .

			/**
			 * Address format: 'formatted'
			 */
				"
				theInput = newField.find('.addrint_addr_formatted').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[addr_formatted]');
				theInput.attr('id', element_id + '_addr_formatted');
				newField.find('.addrint_addr_formatted-lbl').first().attr('for', element_id + '_addr_formatted');

				theInput = newField.find('.addrint_addr1').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[addr1]');
				theInput.attr('id', element_id + '_addr1');
				newField.find('.addrint_addr1-lbl').first().attr('for', element_id + '_addr1');
				" .

			/**
			 * Update optional properties of 'formatted' format
			 */
				"
				theInput = newField.find('.addrint_name').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[name]');
				theInput.attr('id', element_id + '_name');
				newField.find('.addrint_name-lbl').first().attr('for', element_id + '_name');

				theInput = newField.find('.addrint_addr2').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[addr2]');
				theInput.attr('id', element_id + '_addr2');

				theInput = newField.find('.addrint_addr3').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[addr3]');
				theInput.attr('id', element_id + '_addr3');

				theInput = newField.find('.fc_gm_city').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[city]');
				theInput.attr('id', element_id + '_city');
				newField.find('.fc_gm_city-lbl').first().attr('for', element_id + '_city');

				theSelect = newField.find('select.fc_gm_usstate').first();
				theSelect.val('');
				theSelect.attr('name', fname_pfx + '[state]');
				theSelect.attr('id', element_id + '_state');
				newField.find('.fc_gm_usstate-lbl').first().attr('for', element_id + '_state');

				theInput = newField.find('.fc_gm_province').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[province]');
				theInput.attr('id', element_id + '_province');
				newField.find('.fc_gm_province-lbl').first().attr('for', element_id + '_province');

				theSelect = newField.find('select.fc_gm_country').first();
				theSelect.val('');
				theSelect.attr('name', fname_pfx + '[country]');
				theSelect.attr('id', element_id + '_country');
				newField.find('.fc_gm_country-lbl').first().attr('for', element_id + '_country');

				theInput = newField.find('.addrint_zip').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[zip]');
				theInput.attr('id', element_id + '_zip');
				newField.find('.addrint_zip-lbl').first().attr('for', element_id + '_zip');

				theInput = newField.find('.addrint_zip_suffix').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[zip_suffix]');
				theInput.attr('id', element_id + '_zip_suffix');
				";

			/**
			 * Update custom marker
			 */
			if ($use_custom_marker) $js .= "
				theSelect = newField.find('select.fc_gm_custom_marker').first();
				theSelect.val('" . $default_marker_file ."');
				theSelect.attr('name', fname_pfx + '[custom_marker]');
				theSelect.attr('id', element_id + '_custom_marker');
				newField.find('.fc_gm_custom_marker-lbl').first().attr('for', element_id + '_custom_marker');

				theSelect = newField.find('select.fc_gm_marker_anchor').first();
				theSelect.val('BotC');
				theSelect.attr('name', fname_pfx + '[marker_anchor]');
				theSelect.attr('id', element_id + '_marker_anchor');
				newField.find('.fc_gm_marker_anchor-lbl').first().attr('for', element_id + '_marker_anchor');
				";

			/**
			 * Update map header information
			 */
			$js .= "
				theInput = newField.find('.addrint_marker_tolerance').first();
				theInput.attr('name', fname_pfx + '[marker_tolerance]');
				theInput.attr('id', element_id + '_marker_tolerance');
				newField.find('.addrint_marker_tolerance-lbl').first().attr('for', element_id + '_marker_tolerance');

				theInput = newField.find('.addrint_zoom').first();
				theInput.attr('value', '" . $map_zoom . "');
				theInput.attr('name', fname_pfx + '[zoom]');
				theInput.attr('id', element_id + '_zoom');
				newField.find('.addrint_zoom-lbl').first().attr('for', element_id + '_zoom');
				";

			// Update messages box
			$js .= "
				theDiv = newField.find('div.addrint_messages');
				theDiv.html('');
				theDiv.attr('id', element_id + '_messages');
				";

			// Clear canvas container and hide outer container of map
			$js .= "
				theDiv = newField.find('div.addrint_map_canvas');
				theDiv.html('').removeClass('has_fc_google_maps_map');
				theDiv.attr('id', 'map_canvas_' + element_id );

				theDiv = newField.find('div.fcfield_addressint_map');
				theDiv.attr('id', element_id + '_addressint_map');
				theDiv.hide();
				";

			// Attach search auto-complete
			$js .= "
				theInput = newField.find('.addrint_autocomplete').first();
				theInput.val('');
				theInput.attr('name', fname_pfx + '[autocomplete]');
				theInput.attr('id', element_id + '_autocomplete');
				newField.find('.addrint_autocomplete-lbl').first().attr('for', element_id + '_autocomplete');

				" . ($mapapi_edit === 'algolia' ? "
				// Remove previous autocomplete algolia container
				theParent = theInput.parent('.algolia-places');
				if (theParent.length)
				{
					theInput.insertAfter(theParent);
					theParent.remove();
				}
				" : "") . "

				theSelect = newField.find('select.addrint_ac_type').first();
				theSelect.val('');
				theSelect.attr('name', fname_pfx + '[ac_type]');
				theSelect.attr('id', element_id + '_ac_type');

				// Re-init any select2 element
				var has_select2 = theSelect.prev().hasClass('.select2-container');
				if (has_select2) {
					theSelect.prev().remove();
					theSelect.select2('destroy').show();
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

				// Initialize maps search autocomplete
				fcfield_addrint.initAutoComplete" . ($mapapi_edit === 'googlemap' ? "" : "_OS") . "(element_id, '" . $field_name_js . "');

				// Initialize marker selector
				fcfield_addrint.initMarkerSelector(element_id, '" . $field_name_js . "');

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
					row.find('.fcfield-enablevalue').remove();
					row.find('.fcfield-disablevalue').remove();
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


		// Add field's custom CSS / JS
		$js .= "
			function enableField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('.fcfieldval_container');

				row.find('.fcfield-enablevalue').parent().hide();
				row.find('.fcfield-disablevalue').parent().show();

				row.find('.fc-field-prop-disabled').removeAttr('disabled').prop('disabled', false).removeClass('fc-field-prop-disabled');
				row.find('.fc-field-value-properties-box').removeClass('fc-field-value-disabled');
			}
			function disableField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('.fcfieldval_container');

				row.find('.fcfield-enablevalue').parent().show();
				row.find('.fcfield-disablevalue').parent().hide();

				row.find(':enabled').attr('disabled', 'disabled').prop('disabled', true).addClass('fc-field-prop-disabled');
				row.find('.fc-field-value-properties-box').addClass('fc-field-value-disabled');
			}
		";

		// Check if not required, and add buttons  Edit / Skip to allow skipping the value block
		$enable_disable_btns = !$required /*&& count($required_props)*/ ? '
			<div class="'.$input_grp_class.' fc-xpended-btns" style="%s">
				<span class="fcfield-enablevalue ' . $font_icon_class . '" title="'.JText::_( 'FLEXI_ENABLE_N_EDIT_VALUE_DATA' ).'" onclick="enableField'.$field->id.'(this);"> '.JText::_( 'FLEXI_EDIT' ).'</span>
			</div>
			<div class="'.$input_grp_class.' fc-xpended-btns" style="%s">
				<span class="fcfield-disablevalue ' . $font_icon_class . '" title="'.JText::_( 'FLEXI_SKIP_VALUE_DATA_ON_SAVE' ).'" onclick="disableField'.$field->id.'(this);"> '.JText::_( 'FLEXI_SKIP' ).'</span>
			</div>
		' : '';


		// Add needed JS/CSS
		static $js_added = array();
		if (!isset($js_added[$mapapi_edit]))
		{
			$js_added[$mapapi_edit] = true;

			if (count($js_added) < 2)
			{
				JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_NOT_FOUND_WITHIN_TOLERANCE', false);
				JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_FOUND_WITHIN_TOLERANCE', false);
				JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_NOT_FOUND_AT_MARKER', false);
				JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_ONLY_LONG_LAT', false);
				JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_COUNTRY_NOT_ALLOWED_WARNING', false);
				JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_PLEASE_USE_COUNTRIES', false);
			}

			// Load form.js (google maps)
			$document->addScript(JUri::root(true) . '/plugins/flexicontent_fields/addressint/js/form.js', array('version' => FLEXI_VHASH));

			// Load google maps library
			if ($mapapi_edit === 'googlemap')
			{
				flexicontent_html::loadFramework('google-maps', 'form', $field->parameters);
			}

			// Load leaflet & form_algolia.js
			// TODO: move js in field to a framework call to support multi values
			elseif ($mapapi_edit === 'algolia')
			{
				$document->addStyleSheet('https://cdn.jsdelivr.net/leaflet/1/leaflet.css');
				$document->addScript('https://cdn.jsdelivr.net/leaflet/1/leaflet.js');
				$document->addScript('https://cdn.jsdelivr.net/npm/places.js@1.17.1');
			}
		}


		// Add field's CSS / JS
		if ($multiple) $js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
		";
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);


		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();

		// Render form field
		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout ? 'field_'.$formlayout : 'field';

		//$this->setField($field);
		//$this->setItem($item);
		//$this->displayField( $formlayout );

		include(self::getFormPath($this->fieldtypes[0], $formlayout));

		foreach($field->html as $n => & $_html_)
		{
			$_html_ = '
				'.(!$add_ctrl_btns ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				')
				. ($enable_disable_btns ? sprintf($enable_disable_btns, !$field->fc_form_data[$n]->value_disabled ? 'display:none' : '',  $field->fc_form_data[$n]->value_disabled ? 'display:none' : '') : '') . '
				<div class="fcclear"></div>
				'.$_html_;
		}
		unset($_html_);

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



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		// Check if field has posted data
		if ( empty($post) || !is_array($post)) return;

		// Make sure posted data is an array
		$v = reset($post);
		$post = !is_array($v) ? array($post) : $post;

		// Enforce configuration so that user does not manipulate form to add disabled data
		$use_name          = (int) $field->parameters->get('use_name', 1);
		$use_addr2         = (int) $field->parameters->get('use_addr2', 1);
		$use_addr3         = (int) $field->parameters->get('use_addr3', 1);
		$use_usstate       = (int) $field->parameters->get('use_usstate', 1);
		$use_province      = (int) $field->parameters->get('use_province', 1);
		$use_country       = (int) $field->parameters->get('use_country', 1);
		$use_zip_suffix    = (int) $field->parameters->get('use_zip_suffix', 1);
		$map_zoom          = (int) $field->parameters->get('map_zoom', 16);
		$use_custom_marker = (int) $field->parameters->get('use_custom_marker', 0);

		// Get allowed countries
		$ac_country_default      = $field->parameters->get('ac_country_default', '');
		$ac_country_allowed_list = $field->parameters->get('ac_country_allowed_list', '');
		$ac_country_allowed_list = array_unique(FLEXIUtilities::paramToArray($ac_country_allowed_list, "/[\s]*,[\s]*/", false, true));
		$ac_country_allowed_list = array_flip($ac_country_allowed_list);

		$new=0;
		$newpost = array();
		foreach ($post as $n => $v)
		{
			if (empty($v)) continue;

			// Skip value if both address and formated address are empty
			if (
				empty($v['addr_display']) && empty($v['addr_formatted']) &&
				empty($v['addr1']) && empty($v['city']) && empty($v['state']) &&
				empty($v['province']) && empty($v['zip']) &&
				(empty($v['lat']) || empty($v['lon'])) && empty($v['url'])
			) continue;

			// validate data or empty/set default values
			$newpost[$new] = array();

			// Skip value if non-allowed country was passed
			if ($use_country)
			{
				if (!empty($v['country']) && count($ac_country_allowed_list) && !isset($ac_country_allowed_list[$v['country']]))
				{
					$continue;
				}
			}

			$newpost[$new]['addr_display']  = !isset($v['addr_display']) ? '' : flexicontent_html::dataFilter($v['addr_display'],   4000, 'STRING', '');
			$newpost[$new]['addr_formatted']= !isset($v['addr_formatted']) ? '' : flexicontent_html::dataFilter($v['addr_formatted'], 4000, 'STRING', '');
			$newpost[$new]['addr1'] = !isset($v['addr1']) ? '' : flexicontent_html::dataFilter($v['addr1'],  4000, 'STRING', '');
			$newpost[$new]['city']  = !isset($v['city']) ? '' : flexicontent_html::dataFilter($v['city'],   4000, 'STRING', '');
			$newpost[$new]['zip']   = !isset($v['zip']) ? '' : flexicontent_html::dataFilter($v['zip'],    10,   'STRING', '');
			$newpost[$new]['lat']   = !isset($v['lat']) ? '' : flexicontent_html::dataFilter(str_replace(',', '.', $v['lat']),  100, 'DOUBLE', 0);
			$newpost[$new]['lon']   = !isset($v['lon']) ? '' : flexicontent_html::dataFilter(str_replace(',', '.', $v['lon']),  100, 'DOUBLE', 0);
			$newpost[$new]['url']   = !isset($v['url']) ? '' : flexicontent_html::dataFilter($v['url'],    4000,   'URL', '');
			$newpost[$new]['zoom']  = !isset($v['zoom']) ? '' : flexicontent_html::dataFilter($v['zoom'],  2, 'INTEGER', $map_zoom);

			$newpost[$new]['lat']   = $newpost[$new]['lat'] ? $newpost[$new]['lat'] : '';  // clear if zero
			$newpost[$new]['lon']   = $newpost[$new]['lon'] ? $newpost[$new]['lon'] : '';  // clear if zero

			// Allow saving these into the DB, so that they can be enabled later
			$newpost[$new]['name']          = /*!$use_name       ||*/ !isset($v['name'])          ? '' : flexicontent_html::dataFilter($v['name'],          4000,  'STRING', 0);
			$newpost[$new]['addr2']         = /*!$use_addr2      ||*/ !isset($v['addr2'])         ? '' : flexicontent_html::dataFilter($v['addr2'],         4000,  'STRING', 0);
			$newpost[$new]['addr3']         = /*!$use_addr3      ||*/ !isset($v['addr3'])         ? '' : flexicontent_html::dataFilter($v['addr3'],         4000,  'STRING', 0);
			$newpost[$new]['state']         = /*!$use_usstate    ||*/ !isset($v['state'])         ? '' : flexicontent_html::dataFilter($v['state'],          200,  'STRING', 0);
			$newpost[$new]['country']       = /*!$use_country    ||*/ !isset($v['country'])       ? '' : flexicontent_html::dataFilter($v['country'],          2,  'STRING', 0);
			$newpost[$new]['province']      = /*!$use_province   ||*/ !isset($v['province'])      ? '' : flexicontent_html::dataFilter($v['province'],       200,  'STRING', 0);
			$newpost[$new]['zip_suffix']    = /*!$use_zip_suffix ||*/ !isset($v['zip_suffix'])    ? '' : flexicontent_html::dataFilter($v['zip_suffix'],      10,  'STRING', 0);
			$newpost[$new]['custom_marker'] = /*!$custom_marker  ||*/ !isset($v['custom_marker']) ? '' : flexicontent_html::dataFilter($v['custom_marker'], 4000,  'PATH', 0);
			$newpost[$new]['marker_anchor'] = /*!$marker_anchor  ||*/ !isset($v['marker_anchor']) ? '' : flexicontent_html::dataFilter($v['marker_anchor'], 4000,  'WORD', 0);

			$new++;
		}
		$post = $newpost;

		// Serialize multi-property data before storing them into the DB, also map some properties as fields
		$props_to_fields = array('name', 'addr1', 'addr2', 'addr3', 'city', 'zip', 'country', 'lon', 'lat', 'custom_marker', 'marker_anchor');
		$_fields = array();
		$byIds = FlexicontentFields::indexFieldsByIds($item->fields, $item);
		foreach($post as $i => $v)
		{
			foreach($props_to_fields as $propname)
			{
				$to_fieldid = $field->parameters->get('field_'.$propname);
				if ( $to_fieldid && isset($byIds[$to_fieldid]) )
				{
					$to_fieldname = $byIds[$to_fieldid]->name;
					$item->calculated_fieldvalues[$to_fieldname][$i] = $v[$propname];
				}
			}

			$post[$i] = serialize($v);
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

		// Use the custom field values, if these were provided
		$values = $values !== null ? $values : $this->field->value;

		// Parse field values
		$this->values = $this->parseValues($values);


		// CSV export: Create customized output and return
		if ($prop === 'csv_export')
		{
			$separatorf = ' | ';
			$itemprop = false;

			$csv_export_text = $field->parameters->get('csv_export_text', '{{addr1}} {{city}} ZIP: {{zip}}');
			$field_matches = null;

			$result = preg_match_all("/\{\{([a-zA-Z_0-9-]+)\}\}/", $csv_export_text, $field_matches);
			$propertyNames = $result ? $field_matches[1] : array();

			$field->{$prop} = array();

			foreach ($this->values as $value)
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

		// TODO Implement a static image for google maps "img" display
		//$this->getCachedStaticMapImage();


		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout && $viewlayout !== 'value' ? 'value_'.$viewlayout : 'value';

		// Create field's display
		$this->displayFieldValue($prop, $viewlayout);
	}



	// ***
	// *** SEARCH INDEX METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	public function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;

		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array('addr1','addr2','addr3','city','state','province','zip','country'), $properties_spacer=' ', $filter_func=null);
		return true;
	}


	// Method to create basic search index (added as the property field->search)
	public function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;

		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array('addr1','addr2','addr3','city','state','province','zip','country'), $properties_spacer=' ', $filter_func=null);
		return true;
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***


	// !!!UNFINISHED !!!
	// TODO Implement a static image for google maps "img" display
	protected function getCachedStaticMapImage()
	{
		$field = $this->field;
		$item  = $this->item;

		// Get Map Engine
		$map_api = $field->parameters->get('mapapi', 'googlemap');

		// Get Embeed type
		$map_embed_type = $field->parameters->get('map_embed_type','img'); // defaults to img for backward compatibility

		if ($map_api !== 'googlemap' || $map_embed_type !== 'int')
		{
			return;
		}

		// Get API Key for viewing, falling back to edit key
		$google_maps_js_api_key = trim($field->parameters->get('google_maps_js_api_key', ''));
		$google_maps_static_api_key = trim($field->parameters->get('google_maps_static_api_key', $google_maps_js_api_key));

		// Get parameters
		$show_address = $field->parameters->get('show_address','both');
		$show_address = $show_address === 'both' || ($view !== 'item' && $show_address === 'category') || ($view === 'item' && $show_address === 'item');

		$addr_display_mode = $field->parameters->get('addr_display_mode','plaintext');
		$addr_format_tmpl = $field->parameters->get('addr_format_tmpl',	'
		 [[name|<h3 class="fc-addrint business-name">{{name}}</h3>]]
		 [[addr1|<span class="fc-addrint street-address">{{addr1}}</span><br/>]]
		 [[addr2|<span class="fc-addrint street-address2">{{addr2}}</span><br/>]]
		 [[addr3|<span class="fc-addrint street-address3">{{addr3}}</span><br/>]]
		 [[city|<span class="fc-addrint city">{{city}}</span>]]
		 <span class="fc-addrint state">[[state|{{state}}]][[province|{{province}},]]</span>
		 <span class="fc-addrint postal-code">{{zip}}[[zip_suffix|-{{zip_suffix}}]]</span><br/>
		 <span class="fc-addrint country">{{country}}</span>
		');

		$directions_position = $field->parameters->get('directions_position','after');
		$directions_link_label = $field->parameters->get('directions_link_label', JText::_('PLG_FC_ADDRESSINT_GET_DIRECTIONS'));

		$show_map = $field->parameters->get('show_map','');
		$show_map = $show_map === 'both' || ($view !== 'item' && $show_map === 'category') || ($view === 'item' && $show_map === 'item');

		$map_type     = $field->parameters->get('map_type','roadmap');
		$map_zoom     = (int) $field->parameters->get('map_zoom', 16);
		$link_map     = (int) $field->parameters->get('link_map', 1);

		$map_position = (int) $field->parameters->get('map_position', 0);
		$marker_color = $field->parameters->get('marker_color', 'red');
		$marker_size  = $field->parameters->get('marker_size', 'mid');

		$map_width  = (int) $field->parameters->get('map_width', 200);
		$map_height = (int) $field->parameters->get('map_height', 150);

		$use_custom_marker = (int) $field->parameters->get('use_custom_marker', 1);
		$defaut_icon_url   = $map_api === 'googlemap'
			? 'https://maps.gstatic.com/mapfiles/api-3/images/spotlight-poi2.png'
			: 'https://unpkg.com/leaflet@1.5.1/dist/images/marker-icon.png';
	}
}
