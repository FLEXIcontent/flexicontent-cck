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
require_once JPATH_SITE . '/components/com_flexicontent/classes/helpers/security.php';
// Lazy class loading (was JLoader::register): loaded only if/when actually used.
spl_autoload_register(static function ($class) {
	$map = array(
		'FCField' => JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php',
	);
	if (isset($map[$class]) && is_file($map[$class])) require_once $map[$class];
}, true, true);
#[AllowDynamicProperties] //php8.2 compatibility
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

		$field->label = $field->parameters->get('label_form') ? \Joomla\CMS\Language\Text::_($field->parameters->get('label_form')) : \Joomla\CMS\Language\Text::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;


		// Initialize framework objects and other variables
		$document = \Joomla\CMS\Factory::getApplication()->getDocument();
		$cparams  = \Joomla\CMS\Component\ComponentHelper::getParams( 'com_flexicontent' );

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$btn_item_class  = $cparams->get('bootstrap_ver', 2)==2  ?  'btn' : 'btn btn-outline-secondary';
		$btn_group_class = $cparams->get('bootstrap_ver', 2)==2  ?  'btn-group' : 'btn-group';
		$flat_btn_class  = ' fc-flat-btn';
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


		// MAP Engine to use in item form: 'googlemap' or 'nominatim'
		$mapapi_edit = $field->parameters->get('mapapi_edit', 'googlemap');
		if ($mapapi_edit === 'algolia') $mapapi_edit = 'nominatim';

		// Width of the address card in the item edit form
		$form_card_width = (int) $field->parameters->get('form_card_width', 600);
		$form_card_width = $form_card_width > 0 ? $form_card_width : 600;

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
		$show_marker_anchor  = (int) $field->parameters->get('show_marker_anchor', 0);
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
			''=>'FLEXI_ALL',
			'address'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_ADDRESS',
			'geocode'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_GEOCODE',
			'establishment'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_BUSINESS',
			'(regions)'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_REGION',
			'(cities)'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_CITY',
		);


		// Load Countries ($list_countries) and US states ($list_states)
		include('tmpl_common' . DS . 'areas.php');

		// GET ALLOWED ac search types
		$ac_types_default     = $field->parameters->get('ac_types_default', '');
		$ac_type_allowed_list = $field->parameters->get('ac_type_allowed_list', false);

		$ac_type_allowed_list = $ac_type_allowed_list ?:
			array('','geocode','address','establishment','(regions)','(cities)');
		$ac_type_allowed_list = FLEXIUtilities::paramToArray($ac_type_allowed_list, false, false, true);


		// GET ALLOWED countries, with special check for single country
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
		$allowed_countries     = array('' => \Joomla\CMS\Language\Text::_('FLEXI_SELECT'));

		foreach($_list as $country_code => $k)
		{
			$country_op = new stdClass;
			$country_op->value = $country_code;
			$country_op->text  = \Joomla\CMS\Language\Text::_('PLG_FC_ADDRESSINT_CC_' . $country_code);

			$allowed_countries[] = $country_op;

			if (count($ac_country_allowed_list))
			{
				$allowed_country_names[] = $country_op->text;
			}
		}
		$countries_attribs = ''
			. ($single_country ? ' disabled="disabled" readonly="readonly"' : '')
			. ' onchange="fcfield_addrint.toggle_USA_state(this);" ';


		/**
		 * Create Image marker list
		 */
		if ($use_custom_marker)
		{
			$custom_marker_path_abs = \Joomla\Filesystem\Path::clean(JPATH_SITE . DS . $custom_marker_path. DS);
			$custom_marker_url_base = str_replace('\\', '/', \Joomla\CMS\Uri\Uri::root() . $custom_marker_path . '/');

			// Default marker icon (depends on map engine)
			$custom_marker_default = $mapapi_edit === 'nominatim'
				? 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png'
				: 'https://maps.gstatic.com/mapfiles/api-3/images/spotlight-poi2.png';

			$imgs = \Joomla\Filesystem\Folder::files($custom_marker_path_abs);

			if ($imgs)
			{
				$custom_markers = array('' => \Joomla\CMS\Language\Text::_('FLEXI_SELECT'));
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
				(object) array('value'=>'TopL', 'text'=>\Joomla\CMS\Language\Text::_('FLEXI_TOP') . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_LEFT')),
				(object) array('value'=>'TopC', 'text'=>\Joomla\CMS\Language\Text::_('FLEXI_TOP') . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_CENTER')),
				(object) array('value'=>'TopR', 'text'=>\Joomla\CMS\Language\Text::_('FLEXI_TOP') . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_RIGHT')),
				(object) array('value'=>'MidL', 'text'=>\Joomla\CMS\Language\Text::_('FLEXI_MIDDLE') . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_LEFT')),
				(object) array('value'=>'MidC', 'text'=>\Joomla\CMS\Language\Text::_('FLEXI_MIDDLE') . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_CENTER')),
				(object) array('value'=>'MidR', 'text'=>\Joomla\CMS\Language\Text::_('FLEXI_MIDDLE') . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_RIGHT')),
				(object) array('value'=>'BotL', 'text'=>\Joomla\CMS\Language\Text::_('FLEXI_BOTTOM') . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_LEFT')),
				(object) array('value'=>'BotC', 'text'=>\Joomla\CMS\Language\Text::_('FLEXI_BOTTOM') . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_CENTER')),
				(object) array('value'=>'BotR', 'text'=>\Joomla\CMS\Language\Text::_('FLEXI_BOTTOM') . ' ' . \Joomla\CMS\Language\Text::_('FLEXI_RIGHT')),
			);
		}

		// CREATE AC SEARCH TYPE OPTIONS
		$ac_type_options = '';
		foreach($ac_type_allowed_list as $ac_type)
		{
			$lbl = $list_ac_types[$ac_type];
			$ac_type_options .= '<option value="'.htmlspecialchars($ac_type, ENT_COMPAT, 'UTF-8').'"  '.($ac_type == $ac_types_default ? 'selected="selected"' : '').'>'.\Joomla\CMS\Language\Text::_($lbl)."</option>\n";
		}

		// CSS classes of value container ('floated' => several values side by side)
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id . ($fields_box_placing ? ' floated' : '');

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);

		flexicontent_html::loadFramework('form-utils');
		$document->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/plugins/flexicontent_fields/addressint/js/repeater.js', array('version' => 'auto'));

		// JS & CSS of current field
		$js = '
			fcfield_addrint.allowed_countries["'.$field_name_js.'"] = new Array('.(count($ac_country_allowed_list) ? '"' . implode('", "', $ac_country_allowed_list) . '"' : '').');
			fcfield_addrint.single_country["'.$field_name_js.'"] = "' . $single_country . '";

			fcfield_addrint.map_zoom["'.$field_name_js.'"] = ' . $map_zoom . ';
			fcfield_addrint.map_type["'.$field_name_js.'"] = "' . strtoupper($map_type) . '";
		';

		// Nominatim: pass optional tile server URL
		if ($mapapi_edit === 'nominatim')
		{
			\Joomla\CMS\Language\Text::script('JSEARCH_FILTER_SUBMIT');
			$js .= 'fcfield_addrint.nominatim_url[' . json_encode($field_name_js) . '] = '
				. json_encode(trim($field->parameters->get('nominatim_api_url', ''))) . ';';
			$nominatim_tile_url = trim($field->parameters->get('nominatim_tile_url', ''));
			if ($nominatim_tile_url)
			{
				$js .= '
			fcfield_addrint.tile_url["'.$field_name_js.'"] = "' . addslashes($nominatim_tile_url) . '";
				';
			}
		}

		$css = '';

		// Handle multiple records
		if ($multiple)
		{
			$config = array(
				'fieldKey' => $field_name_js,
				'mapEngine' => $mapapi_edit,
				'defaults' => array('custom_marker' => $default_marker_file, 'marker_anchor' => 'BotC', 'zoom' => (string) $map_zoom),
			);
			$js .= $this->buildValueRepeater($field, $elementid, $fieldname, $max_values, $add_ctrl_btns, $config, 'fcfield_address');

			$remove_button = '
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-delvalue ' . $font_icon_class . '"
					title="' . \Joomla\CMS\Language\Text::_( 'FLEXI_REMOVE_VALUE' ) . '"
					onclick="deleteField'.$field->id.'(this); return false;"
				></button>';
			$move2 = '
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-drag-handle ' . $font_icon_class . '" onclick="event.preventDefault(); return false;"
					title="' . \Joomla\CMS\Language\Text::_( 'FLEXI_CLICK_TO_DRAG' ) . '"
				></button>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-insertvalue fc_before ' . $font_icon_class . '"
					title="' . \Joomla\CMS\Language\Text::_( 'FLEXI_ADD_BEFORE' ) . '"
					onclick="addField'.$field->id.'(null, this.closest(\'ul\'), this.closest(\'li\'), {insert_before: 1}); return false;"
				></button> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-insertvalue fc_after ' . $font_icon_class . '"
					title="' . \Joomla\CMS\Language\Text::_( 'FLEXI_ADD_AFTER' ) . '"
					onclick="addField'.$field->id.'(null, this.closest(\'ul\'), this.closest(\'li\'), {insert_before: 0}); return false;"
				></button> ' : '';

		}

		// Field not multi-value
		else
		{
			$remove_button = '';
			$move2         = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}


		// Skip feature is available for non-required fields
		$enable_disable_btns = !$required;

		// Skip button only (borderless icon), toggles the disabled state of the value
		$skip_btn = $enable_disable_btns ? '
			<div class="fc-xpended-btns">
				<button type="button" class="' . $btn_item_class . $flat_btn_class . ' fcfield-skipvalue" title="'.\Joomla\CMS\Language\Text::_( 'FLEXI_SKIP_VALUE_DATA_ON_SAVE' ).'" aria-pressed="__SKIPPED__" onclick="fcfield_address.toggleSkipped(this); return false;"><span class="fc-image-tool-icon fas __EYE_ICON__ fc-skip-icon" aria-hidden="true"></span></button>
			</div>
		' : '';


		// Add needed JS/CSS (once per engine, supports mixed usage on same page)
		static $js_added = array();
		if (!isset($js_added[$mapapi_edit]))
		{
			$js_added[$mapapi_edit] = true;

			if (count($js_added) === 1)
			{
				// Load translation strings once (shared by both engines)
				\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_NOT_FOUND_WITHIN_TOLERANCE', false);
				\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_FOUND_WITHIN_TOLERANCE', false);
				\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_NOT_FOUND_AT_MARKER', false);
				\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_ONLY_LONG_LAT', false);
				\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_COUNTRY_NOT_ALLOWED_WARNING', false);
				\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_PLEASE_USE_COUNTRIES', false);
			}

			if ($mapapi_edit === 'googlemap')
			{
				\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AUTOCOMPLETE_LABEL');
				\Joomla\CMS\Language\Text::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_PLACE_SEARCH_FAILED');

				// Load form.js (Google Maps autocomplete + map)
				$document->getWebAssetManager()->registerAndUseScript('plg_flexicontent_fields_addressint.form', 'plugins/flexicontent_fields/addressint/js/form.js', array('version' => FLEXI_VHASH));

				// Load Google Maps JS API
				flexicontent_html::loadFramework('google-maps', 'form', $field->parameters);
			}
			elseif ($mapapi_edit === 'nominatim')
			{
				// Load form_osm.js (Nominatim autocomplete + Leaflet map)
				// Leaflet itself is loaded dynamically by form_osm.js if not already present
				$document->getWebAssetManager()->registerAndUseScript('plg_flexicontent_fields_addressint.form_osm', 'plugins/flexicontent_fields/addressint/js/form_osm.js', array('version' => FLEXI_VHASH));
			}
		}


		// Add field's CSS / JS
		if ($js)  $document->getWebAssetManager()->addInlineScript($js);
		if ($css) $document->getWebAssetManager()->addInlineStyle($css);


		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();

		// Render form field
		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout ? 'field_'.$formlayout : 'field';

		include(self::getFormPath($this->fieldtypes[0], $formlayout));

		foreach($field->html as $n => & $_html_)
		{
			$value_is_disabled = !empty($field->fc_form_data[$n]->value_disabled);
			$skip_btn_n = str_replace(array('__EYE_ICON__', '__SKIPPED__'), array($value_is_disabled ? 'fa-eye-slash' : 'fa-eye', $value_is_disabled ? 'true' : 'false'), $skip_btn);

			$_html_ = '
				<div class="card fc-video-card fc-address-card'.($fields_box_placing ? ' fc-address-multi' : '').($value_is_disabled ? ' fc-address-skipped' : '').'" style="'.($fields_box_placing ? 'width: '.$form_card_width.'px; max-width: 100%;' : 'width: 100%; max-width: 100%;').'">
				<div class="fc-video-card-body">

				<div class="fc-address-top-row">
					'.(!$add_ctrl_btns ? '' : '
					<div class="'.$btn_group_class.' fc-xpended-btns fc-image-top-toolbar">
						'.$move2.'
						'.$remove_button.'
						'.(!$add_position ? '' : $add_here).'
					</div>
					')
					. $skip_btn_n . '
				</div>
				<div class="fcclear"></div>

				'.$_html_.'

				</div><!-- EOF .fc-video-card-body -->
				</div><!-- EOF .card -->
				';
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
				<div class="'.$btn_group_class.' fc-xpended-btns">
					<button type="button" class="fcfield-addvalue ' . $font_icon_class . ' ' . $btn_item_class . '" onclick="addField'.$field->id.'(this.closest(\'.fc-xpended-btns\'));" title="'.\Joomla\CMS\Language\Text::_( 'FLEXI_ADD_TO_BOTTOM' ).'">
						'.\Joomla\CMS\Language\Text::_( 'FLEXI_ADD_VALUE' ).'
					</button>
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
		$use_ingroup = $field->parameters->get('use_ingroup', 0);

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
			// Skip value if both address and formated address are empty
			if (
				empty($v['addr_display']) && empty($v['addr_formatted']) &&
				empty($v['addr1']) && empty($v['city']) && empty($v['state']) &&
				empty($v['province']) && empty($v['zip']) &&
				(empty($v['lat']) || empty($v['lon'])) && empty($v['url'])
			)
			{
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}

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
			$newpost[$new]['url']   = !isset($v['url']) ? '' : flexicontent_security::safeUrl(flexicontent_html::dataFilter($v['url'], 4000, 'URL', ''));
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
			if ($v === null) continue;
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

		// Get Embed type
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
		$directions_link_label = $field->parameters->get('directions_link_label', \Joomla\CMS\Language\Text::_('PLG_FC_ADDRESSINT_GET_DIRECTIONS'));

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
		$defaut_icon_url   = 'https://maps.gstatic.com/mapfiles/api-3/images/spotlight-poi2.png';
	}
}
