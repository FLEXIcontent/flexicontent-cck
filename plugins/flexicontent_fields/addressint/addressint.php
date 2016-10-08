<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('cms.plugin.plugin');
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsAddressint extends FCField
{
	static $field_types = array('addressint');

	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		
		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';


		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;


		// Initialise value property
		$values = $this->parseValues($field->value);
		if (empty($values)) {
			$values[0]['autocomplete'] = '';
			$values[0]['addr_display'] = '';
			$values[0]['addr_formatted'] = '';
			$values[0]['name'] = '';
			$values[0]['addr1'] = '';
			$values[0]['addr2'] = '';
			$values[0]['addr3'] = '';
			$values[0]['city'] = '';
			$values[0]['state'] = '';
			$values[0]['province'] = '';
			$values[0]['zip'] = '';
			$values[0]['zip_suffix'] = '';
			$values[0]['country'] = '';
			$values[0]['lat'] = '';
			$values[0]['lon'] = '';
			$values[0]['url'] = '';
			$values[0]['zoom'] = '';
		}
		$this->values = & $values;


		// Some parameter shortcuts
		$required = $field->parameters->get('required', 0);
		$required_class = $required ? 'required' : '';

		$addr_edit_mode = $field->parameters->get('addr_edit_mode', 'plaintext');
		$edit_latlon  = (int) $field->parameters->get('edit_latlon',  1);
		$use_name     = (int) $field->parameters->get('use_name',     1);
		$use_addr2    = (int) $field->parameters->get('use_addr2',    1);
		$use_addr3    = (int) $field->parameters->get('use_addr3',    1);
		$use_usstate  = (int) $field->parameters->get('use_usstate',  1);
		$use_province = (int) $field->parameters->get('use_province', 1);
		$use_zip_suffix = (int) $field->parameters->get('use_zip_suffix', 1);
		$use_country  = (int) $field->parameters->get('use_country',  1);
		$map_type = $field->parameters->get('map_type', 'roadmap');
		$map_zoom = (int) $field->parameters->get('map_zoom', 16);


		// Google autocomplete search types drop down list (for geolocation)
		$list_ac_types = array(
			''=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_ALL_SEARCH_TYPES',
			'geocode'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_GEOCODE',
			'address'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_ADDRESS',
			'establishment'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_BUSINESS',
			'(regions)'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_REGION',
			'(cities)'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_CITY'
		);


		// Countries and US states
		include('tmpl_common'.DS.'areas.php');

		// CET ALLOWED ac search types
		$ac_types_default = $field->parameters->get('ac_types_default', '');
		$ac_type_allowed_list = $field->parameters->get('ac_type_allowed_list', array('','geocode','address','establishment','(regions)','(cities)'));
		$ac_type_allowed_list = FLEXIUtilities::paramToArray($ac_type_allowed_list, false, false, true);


		// CET ALLOWED countries, with special check for single country
		$ac_country_default = $field->parameters->get('ac_country_default', '');
		$ac_country_allowed_list = $field->parameters->get('ac_country_allowed_list', '');
		$ac_country_allowed_list = array_unique(FLEXIUtilities::paramToArray($ac_country_allowed_list, "/[\s]*,[\s]*/", false, true));
		$single_country = count($ac_country_allowed_list)==1 && $ac_country_default ? $ac_country_default : false;


		// CREATE COUNTRY OPTIONS
		$_list = count($ac_country_allowed_list) ? array_flip($ac_country_allowed_list) : $list_countries;
		$allowed_country_names = array();
		$allowed_countries = array(''=>JText::_('FLEXI_SELECT'));
		foreach($_list as $country_code => $k)
		{
			$country_op = new stdClass;
			$allowed_countries[] = $country_op;
			$country_op->value = $country_code;
			$country_op->text  = JText::_('PLG_FC_ADDRESSINT_CC_'.$country_code);
			if (count($ac_country_allowed_list)) $allowed_country_names[] = $country_op->text;
		}
		//echo $ac_country_options; exit;

		$countries_attribs = ' class="use_select2_lib fc_gm_country '.$required_class.'"'
			. ($single_country ? ' disabled="disabled" readonly="readonly"' : '')
			. ' onchange="var country=jQuery(this); var usstate_row = country.closest(\'table\').find(\'.fc_gm_usstate_row\'); country.val()==\'US\' ? usstate_row.show(600) : usstate_row.hide(600); " ';


		// CREATE AC SEARCH TYPE OPTIONS
		$ac_type_options = '';
		foreach($ac_type_allowed_list as $ac_type)
		{
			$lbl = $list_ac_types[$ac_type];
			$ac_type_options .= '<option value="'.$ac_type.'"  '.($ac_type == $ac_types_default ? 'selected="selected"' : '').'>'.JText::_($lbl)."</option>\n";
		}
		//echo $ac_type_options; exit;


		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		
		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;


		// JS data of current field
		$js = '
			fcfield_addrint.allowed_countries["'.$field->name.'"] = new Array('.(count($ac_country_allowed_list) ? '"' . implode('", "', $ac_country_allowed_list) . '"' : '').');
			fcfield_addrint.single_country["'.$field->name.'"] = "'.$single_country.'";
		
			fcfield_addrint.map_zoom["'.$field->name.'"] = '.$map_zoom.';
			fcfield_addrint.map_type["'.$field->name.'"] = "'.strtoupper($map_type).'";
		';
		$css = "";
		
		if ($multiple) // handle multiple records
		{
			// Add the drag and drop sorting feature
			if (!$use_ingroup) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
			});
			";
			
			if ($max_values) JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
			
			function addField".$field->id."(el, groupval_box, fieldval_box, params)
			{
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;
				
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				// Find last container of fields and clone it to create a new container of fields
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				";
			
			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			// Update non-optional properties
			$js .= "
				theInput = newField.find('input.addrint_lat').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][lat]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_lat');
				newField.find('.addrint_lat-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_lat');
				
				theInput = newField.find('input.addrint_lon').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][lon]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_lon');
				newField.find('.addrint_lon-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_lon');
			";
			
			// Address format: 'plaintext'
			if ($addr_edit_mode == 'plaintext') $js .= "
				theInput = newField.find('.addrint_addr_display').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][addr_display]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_addr_display');
				newField.find('.addrint_addr_display-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_addr_display');
				";

			// Address format: 'formatted'
			if ($addr_edit_mode == 'formatted') $js .= "
				theInput = newField.find('.addrint_name').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][name]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_name');
				newField.find('.addrint_name-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_name');
				"

			// Update optional properties of 'formatted' format
			.($use_name ? "
				theInput = newField.find('.addrint_addr1').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][addr1]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_addr1');
				newField.find('.addrint_addr1-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_addr1');
				" : "")
			.($use_addr2 ? "
				theInput = newField.find('.addrint_addr2').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][addr2]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_addr2');
				" : "")
			.($use_addr3 ? "
				theInput = newField.find('.addrint_addr3').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][addr3]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_addr3');
				" : "")."

				theInput = newField.find('.fc_gm_city').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][city]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_city');
				newField.find('.fc_gm_city-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_city');
				"
			.($use_usstate ? "
				theSelect = newField.find('select.fc_gm_usstate').first();
				theSelect.val('');
				theSelect.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][state]');
				theSelect.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_state');
				newField.find('.fc_gm_usstate-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_state');
				
				// Re-init any select2 element
				var has_select2 = theSelect.hasClass('use_select2_lib');
				if (has_select2) {
					theSelect.prev().remove();
					theSelect.select2('destroy').show();
					fc_attachSelect2(theSelect.parent());
				}
				" : "")

			.($use_province ? "
				theInput = newField.find('.fc_gm_province').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][province]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_province');
				newField.find('.fc_gm_province-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_province');
				" : "")

			.($use_country ? "
				theSelect = newField.find('select.fc_gm_country').first();
				theSelect.val('');
				theSelect.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][country]');
				theSelect.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_country');
				newField.find('.fc_gm_country-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_country');

				// Re-init any select2 element
				var has_select2 = theSelect.hasClass('use_select2_lib');
				if (has_select2) {
					theSelect.prev().remove();
					theSelect.select2('destroy').show();
					fc_attachSelect2(theSelect.parent());
				}
				" : "")."

				theInput = newField.find('.addrint_zip').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][zip]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_zip');
				newField.find('.addrint_zip-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_zip');
				"
				.($use_zip_suffix ? "
				theInput = newField.find('.addrint_zip_suffix').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][zip_suffix]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_zip_suffix');
				" : "")."
				theInput = newField.find('.addrint_marker_tolerance').first();
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][marker_tolerance]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_marker_tolerance');
				newField.find('.addrint_marker_tolerance-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_marker_tolerance');

				theInput = newField.find('.addrint_zoom_label').first();
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_zoom_label');
				";

			// Update messages box
			$js .= "
				theDiv = newField.find('div.addrint_messages');
				theDiv.html('');
				theDiv.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_messages');
				";
			
			// Clear canvas container and attach search auto-complete
			$js .= "
				theDiv = newField.find('div.addrint_map_canvas');
				theDiv.html('');
				theDiv.attr('id','map_canvas_".$elementid."_'+uniqueRowNum".$field->id.");
				
				theInput = newField.find('.addrint_autocomplete').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][autocomplete]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_autocomplete');
				newField.find('.addrint_autocomplete-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_autocomplete');
				
				theSelect = newField.find('select.addrint_ac_type').first();
				theSelect.val('');
				theSelect.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][ac_type]');
				theSelect.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_ac_type');
				";
			
			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();
				";
							
			// Add new element to sortable objects (if field not in group)
			if (!$use_ingroup) $js .= "
				//jQuery('#sortables_".$field->id."').sortable('refresh');  // Refresh was done appendTo ?
				";
			
			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800);
				
				// Enable tooltips on new element
				newField.find('.hasTooltip').tooltip({'html': true,'container': newField});

				// Attach form validation on new element
				fc_validationAttach(newField);
				
				// Initialize gmaps search autocomplete
				fcfield_addrint.initAutoComplete('".$elementid."_'+uniqueRowNum".$field->id.", '".$field->name."');
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Disable clicks
				var btn = fieldval_box ? false : jQuery(el);
				if (btn) btn.css('pointer-events', 'none').off('click');

				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');
				
				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 1)
					addField".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});
				
				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if (rowCount".$field->id." > 1)
				{
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ jQuery(this).remove(); });
					rowCount".$field->id."--;
				}

				// If not removing re-enable clicks
				else if (btn) btn.css('pointer-events', '').on('click');
			}
			";
			
			$css .= '';
			
			$remove_button = '<span class="'.$add_on_class.' fcfield-delvalue'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="'.$add_on_class.' fcfield-drag-handle'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="'.$add_on_class.' fcfield-insertvalue fc_before'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="'.$add_on_class.' fcfield-insertvalue fc_after'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		} else {
			$remove_button = '';
			$move2 = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}


		// Add needed JS/CSS
		static $js_added = null;
		if ( $js_added === null )
		{
			$js_added = true;
			JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_NOT_FOUND_WITHIN_TOLERANCE', false);
			JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_FOUND_WITHIN_TOLERANCE', false);
			JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_NOT_FOUND_AT_MARKER', false);
			JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_ONLY_LONG_LAT', false);
			JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_COUNTRY_NOT_ALLOWED_WARNING', false);
			JText::script('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_PLEASE_USE_COUNTRIES', false);
			$document->addScript(JURI::root(true).'/plugins/flexicontent_fields/addressint/js/form.js');	

			// Load google maps library
			$google_maps_js_api_key = $field->parameters->get('google_maps_js_api_key', '');
			$document->addScript('https://maps.google.com/maps/api/js?libraries=geometry,places' . ($google_maps_js_api_key ? '&key=' . $google_maps_js_api_key : ''));
		}
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		

		// Render form field
		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout ? 'field_'.$formlayout : 'field';

		//$this->setField($field);
		//$this->setItem($item);
		//$this->displayField( $formlayout );

		include(self::getFormPath($this->fieldtypes[0], $formlayout));

		foreach($field->html as &$_html_) {
			$_html_ = '
				'.($use_ingroup ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				<div class="fcclear"></div>
				').'
				'.$_html_;
		}
		unset($_html_);
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue '.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">'.JText::_( 'FLEXI_ADD_VALUE' ).'</span>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	/*function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array('addr1','addr2','addr3','city','state','province','zip','country'), $properties_spacer=' ', $filter_func=null);
		return true;
	}*/
	
	
	// Method to create basic search index (added as the property field->search)
	/*function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array('addr1','addr2','addr3','city','state','province','zip','country'), $properties_spacer=' ', $filter_func=null);
		return true;
	}*/

	
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// Check if field has posted data
		if ( empty($post) || !is_array($post)) return;
		
		// Make sure posted data is an array
		$v = reset($post);
		$post = !is_array($v) ? array($post) : $post;
		
		// Enforce configuration so that user does not manipulate form to add disabled data
		$use_name     = $field->parameters->get('use_name', 1);
		$use_addr2    = $field->parameters->get('use_addr2', 1);
		$use_addr3    = $field->parameters->get('use_addr3', 1);
		$use_usstate  = $field->parameters->get('use_usstate', 1);
		$use_province = $field->parameters->get('use_province', 1);
		$use_country  = $field->parameters->get('use_country', 1);
		$use_zip_suffix = $field->parameters->get('use_zip_suffix', 1);
		$map_zoom = $field->parameters->get('map_zoom', 16);
		
		// Get allowed countries
		$ac_country_default = $field->parameters->get('ac_country_default', '');
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
				empty($v['addr_display']) && empty($v['addr_formatted']) && empty($v['addr1']) &&
				empty($v['city']) && empty($v['state']) && empty($v['province']) &&
				(empty($v['lat']) || empty($v['lon'])) && empty($v['url'])
			) continue;
			
			// validate data or empty/set default values
			$newpost[$new] = array();
			
			// Skip value if non-allowed country was passed
			if ( $use_country && @ $v['country'] && count($ac_country_allowed_list) && !isset($ac_country_allowed_list[$v['country']]) ) $continue;
			
			$newpost[$new]['autocomplete']  = flexicontent_html::dataFilter($v['autocomplete'],   4000, 'STRING', '');
			$newpost[$new]['addr_display']  = flexicontent_html::dataFilter($v['addr_display'],   4000, 'STRING', '');
			$newpost[$new]['addr_formatted']= flexicontent_html::dataFilter($v['addr_formatted'], 4000, 'STRING', '');
			$newpost[$new]['addr1'] = flexicontent_html::dataFilter($v['addr1'],  4000, 'STRING', '');
			$newpost[$new]['city']  = flexicontent_html::dataFilter($v['city'],   4000, 'STRING', '');
			$newpost[$new]['zip']   = flexicontent_html::dataFilter($v['zip'],    10,   'STRING', '');
			$newpost[$new]['lat']   = flexicontent_html::dataFilter(str_replace(',', '.', $v['lat']),  100, 'DOUBLE', 0);
			$newpost[$new]['lon']   = flexicontent_html::dataFilter(str_replace(',', '.', $v['lon']),  100, 'DOUBLE', 0);
			$newpost[$new]['url']   = flexicontent_html::dataFilter($v['url'],    4000,   'URL', '');
			$newpost[$new]['zoom']  = flexicontent_html::dataFilter($v['zoom'],  2, 'INTEGER', $map_zoom);
			
			$newpost[$new]['lat']   = $newpost[$new]['lat'] ? $newpost[$new]['lat'] : '';  // clear if zero
			$newpost[$new]['lon']   = $newpost[$new]['lon'] ? $newpost[$new]['lon'] : '';  // clear if zero
			
			// Allow saving these into the DB, so that they can be enabled later
			$newpost[$new]['name']       = /*!$use_name       ||*/ !isset($v['name'])       ? '' : flexicontent_html::dataFilter($v['name'],     4000,  'STRING', 0);
			$newpost[$new]['addr2']      = /*!$use_addr2      ||*/ !isset($v['addr2'])      ? '' : flexicontent_html::dataFilter($v['addr2'],    4000, 'STRING', 0);
			$newpost[$new]['addr3']      = /*!$use_addr3      ||*/ !isset($v['addr3'])      ? '' : flexicontent_html::dataFilter($v['addr3'],    4000,  'STRING', 0);
			$newpost[$new]['state']      = /*!$use_usstate    ||*/ !isset($v['state'])      ? '' : flexicontent_html::dataFilter($v['state'],     200,  'STRING', 0);
			$newpost[$new]['country']    = /*!$use_country    ||*/ !isset($v['country'])    ? '' : flexicontent_html::dataFilter($v['country'],     2,  'STRING', 0);
			$newpost[$new]['province']   = /*!$use_province   ||*/ !isset($v['province'])   ? '' : flexicontent_html::dataFilter($v['province'],  200,  'STRING', 0);
			$newpost[$new]['zip_suffix'] = /*!$use_zip_suffix ||*/ !isset($v['zip_suffix']) ? '' : flexicontent_html::dataFilter($v['zip_suffix'], 10,  'STRING', 0);
			
			$new++;
		}
		$post = $newpost;

		// Serialize multi-property data before storing them into the DB, also map some properties as fields
		$props_to_fields = array('addr1', 'addr2', 'addr3', 'city', 'zip', 'country', 'lon', 'lat');
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
	
	
	/*function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		$field->label = JText::_($field->label);
		
		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);
		
		// Get choosen display layout
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value';
		
		// Render the field's HTML
		$this->values = $values;
		$this->displayFieldValue( $prop, $viewlayout );
	}*/
}