<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

$dom_ready_js = '';

$n = 0;
foreach ($values as $value)
{
	$fieldname_n = $fieldname.'['.$n.']';
	$elementid_n = $elementid.'_'.$n;

	// make sure that optional data exist
	$value['addr_display']   = isset($value['addr_display'])   ? $value['addr_display'] : '';
	$value['addr_formatted'] = isset($value['addr_formatted']) ? $value['addr_formatted'] : '';
	$value['zip_suffix']     = isset($value['zip_suffix'])     ? $value['zip_suffix'] : '';
	$value['custom_marker']  = isset($value['custom_marker'])  ? $value['custom_marker'] : '';
	$value['marker_anchor']  = !empty($value['marker_anchor'])  ? $value['marker_anchor'] : 'BotC';

	$value['url']      = isset($value['url'])   ? $value['url'] : '';
	$value['zoom']     = isset($value['zoom'])  ? $value['zoom'] : '';
	$value['name']     = isset($value['name'])  ? $value['name'] : '';
	$value['addr1']    = isset($value['addr1']) ? $value['addr1'] : '';
	$value['addr2']    = isset($value['addr2']) ? $value['addr2'] : '';
	$value['addr3']    = isset($value['addr3']) ? $value['addr3'] : '';

	$value['state']    = isset($value['state'])    ? $value['state'] : '';
	$value['province'] = isset($value['province']) ? $value['province'] : '';
	$value['city']     = isset($value['city'])     ? $value['city'] : '';
	$value['country']  = isset($value['country'])  ? $value['country'] : '';

	$value['zip']      = isset($value['zip']) ? $value['zip'] : '';
	$value['lat']      = isset($value['lat']) ? $value['lat'] : '';
	$value['lon']      = isset($value['lon']) ? $value['lon'] : '';

	$coords_are_empty = !$value['lat'] && !$value['lon'];
	$value_is_empty   =
		empty($value['addr_display']) && empty($value['addr_formatted']) && empty($value['addr1']) &&
		empty($value['city']) && empty($value['state']) && empty($value['province']) &&
		(empty($value['lat']) || empty($value['lon'])) && empty($value['url']);

	// Use default marker icon only for new values
	if ($value_is_empty)
	{
		$value['custom_marker'] = $default_marker_file;
	}

	$value_is_disabled       = $enable_disable_btns && $value_is_empty;
	$field->fc_form_data[$n] = (object) array(
		'value_disabled' => $value_is_disabled
	);

	$disabled_class         = $value_is_disabled ? ' fc-field-prop-disabled' : '';
	$disabled_attr          = $value_is_disabled ? ' disabled="disabled" ' : '';

	$google_maps_js_api_key = $field->parameters->get('google_maps_js_api_key', '');
	$mapapi_edit            = $field->parameters->get('mapapi_edit', 'googlemap');

	// Missing API key error
	if ($mapapi_edit === 'googlemap' && $google_maps_js_api_key == '')
	{
		$message_error = 'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_GOOGLE_MAPS_EMPTY_API_KEY_WARNING';
	}
	elseif ($mapapi_edit === "algolia" && ($algolia_api_id == '' || $algolia_api_key == ''))
	{
		$message_error = 'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ALGOLIA_EMPTY_API_KEY_WARNING';
	}
	else
	{
		$message_error = '';
	}

	// Container of missing API key error
	$message_error = $message_error ? '<span class="alert alert-warning fc-iblock">' . JText::_($message_error) . '</span>' : '';


	$field_html = '
	<div class="fcfield_field_data_box fcfield_addressint_data">

		<div>
			<div id="'.$elementid_n.'_messages" class="alert alert-warning fc-iblock addrint_messages" style="display:none;"></div>
		</div>

		<table class="fc-form-tbl fcfullwidth fcinner fc-addressint-field-tbl"><tbody>
			<tr>
				<td colspan="2" class="">
					' . $message_error . '
					<div class="'.$input_grp_class . ' fc-xpended-row">
						<label class="' . $add_on_class . ' fc-lbl-short addrint_autocomplete-lbl" for="'.$elementid_n.'_autocomplete" style="float: none;"><span class="icon-search"></span></label>
						<input id="'.$elementid_n.'_autocomplete" class="addrint_autocomplete" name="'.$fieldname_n.'[autocomplete]" type="text" autocomplete="off" spellcheck="false" />
						<select id="'.$elementid_n.'_ac_type" class="addrint_ac_type use_select2_lib" name="'.$fieldname_n.'[ac_type]" onchange="fcfield_addrint.changeAutoCompleteType' . ($mapapi_edit === 'googlemap' ? '' : '_OS') . '(this.id.replace(\'_ac_type\', \'\'), \''.$field_name_js.'\');">
							'.$ac_type_options.'
						</select>
					</div>
				</td>
			<tr>
		' .

		($addr_edit_mode != 'plaintext' ? '' : '
			<tr>
				<td class="key"><label class="fc-prop-lbl addrint_addr_display-lbl" for="'.$elementid_n.'_addr_display">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ADDRESS').'</label></td>
				<td><textarea class="fcfield_textval addrint_addr_display ' . (in_array('address', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr . ' id="'.$elementid_n.'_addr_display" name="'.$fieldname_n.'[addr_display]" rows="4" cols="24">'
				.($value['addr_display'] ? $value['addr_display'] :
					(
						( // Minimum fields present for creating an address
						!empty($value['addr1'])    || !empty($value['city']) || !empty($value['state']) ||
						!empty($value['province']) || !empty($value['zip'])
						) ?
						 ($value['name'] ? $value['name']."\n" : '')
						.($value['addr1'] ? $value['addr1'] . "\n" : '')
						.($value['addr2'] ? $value['addr2'] . "\n" : '')
						.($value['addr3'] ? $value['addr3'] . "\n" : '')
						.($value['city'] || $value['state'] ? ($value['city']  ? ' ' . $value['city']  : '') . ($value['state'] ? ' ' . $value['state'] : '') : '')
						.($value['province'] ? ' '  . $value['province'] : '')
						.($value['zip']      ? ', ' . $value['zip'] . ($value['zip_suffix'] ? ' '.$value['zip_suffix'] : '') . "\n" : '')
						.($value['country']  ? JText::_('PLG_FC_ADDRESSINT_CC_'.$value['country']) . "\n" : '')
					: ''
					)
				)
				.'</textarea>
				</td>
			</tr>
		') .


		($addr_edit_mode === 'formatted' ? '
			<tr '.($use_name ? '' : 'style="display:none;"').' class="fc_gm_name_row">
				<td class="key"><label class="fc-prop-lbl addrint_name-lbl" for="'.$elementid_n.'_name" >'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_BUSINESS_LOCATION').'</label></td>
				<td><input type="text" class="fcfield_textval addrint_name ' . ($use_name && in_array('business_location', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr . ' id="'.$elementid_n.'_name" name="'.$fieldname_n.'[name]" value="'.htmlspecialchars($value['name'], ENT_COMPAT, 'UTF-8').'" size="50" maxlength="100" /></td>
			</tr>

			<tr class="fc_gm_addr_row">
				<td class="key"><label class="fc-prop-lbl addrint_addr1-lbl" for="'.$elementid_n.'_addr1">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_STREET_ADDRESS').'</label></td>
				<td>
					<textarea class="fcfield_textval addrint_addr1 ' . (in_array('street_address', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr . ' id="'.$elementid_n.'_addr1" name="'.$fieldname_n.'[addr1]" maxlength="400" cols="47" rows="2">'.$value['addr1'].'</textarea>'
					.($use_addr2 ? '<br/><textarea class="fcfield_textval addrint_addr2" id="'.$elementid_n.'_addr2" name="'.$fieldname_n.'[addr2]" maxlength="400" rows="2">'.$value['addr2'].'</textarea>' : '')
					.($use_addr3 ? '<br/><textarea class="fcfield_textval addrint_addr3" id="'.$elementid_n.'_addr3" name="'.$fieldname_n.'[addr3]" maxlength="400" rows="2">'.$value['addr3'].'</textarea>' : '')
					.'
				</td>
			</tr>

			<tr class="fc_gm_city_row">
				<td class="key"><label class="fc-prop-lbl fc_gm_city-lbl" for="'.$elementid_n.'_city">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_CITY').'</label></td>
				<td><input type="text" class="fcfield_textval fc_gm_city ' . (in_array('city', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr . ' id="'.$elementid_n.'_city" name="'.$fieldname_n.'[city]" value="'.htmlspecialchars($value['city'], ENT_COMPAT, 'UTF-8').'" size="50" maxlength="100" /></td>
			</tr>

			<tr '.($use_usstate && $value['country'] === 'US' ? '' : 'style="display:none;"').' class="fc_gm_usstate_row">
				<td class="key"><label class="fc-prop-lbl fc_gm_usstate-lbl" for="'.$elementid_n.'_state">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_US_STATE').'</label></td>
				<td>' .
					JHtml::_('select.genericlist', $list_states, $fieldname_n.'[state]',
						' class="use_select2_lib fc_gm_usstate ' . ($use_usstate && in_array('us_state', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr,
						'value', 'text', $value['state'], $elementid_n.'_state') . '
				</td>
			</tr>

			<tr '.($use_province ? '' : 'style="display:none;"').' class="fc_gm_province_row">
				<td class="key"><label class="fc-prop-lbl fc_gm_province-lbl" for="'.$elementid_n.'_province">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_NON_US_STATE_PROVINCE').'</label></td>
				<td><input type="text" class="fcfield_textval fc_gm_province ' . ($use_province && in_array('non_us_state_province', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr . ' id="'.$elementid_n.'_province" name="'.$fieldname_n.'[province]" value="'.htmlspecialchars($value['province'], ENT_COMPAT, 'UTF-8').'" size="50" maxlength="100" /></td>
			</tr>

			<tr class="fc_gm_zip_row">
				<td class="key"><label class="fc-prop-lbl addrint_zip-lbl" for="'.$elementid_n.'_zip">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ZIP_POSTAL_CODE').'</label></td>
				<td>
					<input type="text" class="fcfield_textval inlineval addrint_zip ' . (in_array('zip_postal_code', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr . ' id="'.$elementid_n.'_zip" name="'.$fieldname_n.'[zip]" value="'.htmlspecialchars($value['zip'], ENT_COMPAT, 'UTF-8').'" size="10" maxlength="10" />
					' .
					// MANUALLY entered PROPERTY !! Remove from form instead of hiding !!
					($use_zip_suffix ? '&nbsp; <input type="text" class="fcfield_textval inlineval addrint_zip_suffix" id="'.$elementid_n.'_zip_suffix" name="'.$fieldname_n.'[zip_suffix]" value="'.htmlspecialchars($value['zip_suffix'], ENT_COMPAT, 'UTF-8').'" size="5" maxlength="10" />' : '') . '
				</td>
			</tr>

			<tr '.($use_country ? '' : 'style="display:none;"').' class="fc_gm_country_row">
				<td class="key"><label class="fc-prop-lbl fc_gm_country-lbl" for="'.$elementid_n.'_country">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_COUNTRY').'</label></td>
				<td>' .
					JHtml::_('select.genericlist', $allowed_countries, $fieldname_n.'[country]', $countries_attribs . ' class="use_select2_lib fc_gm_country ' .
						($use_country && in_array('country', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr,
						'value', 'text', ($value['country'] ? $value['country'] : $ac_country_default), $elementid_n.'_country') . '
				</td>
			</tr>
		' : '') . '

			<tr '.($edit_latlon ? '' : 'style="display:none;"').' class="fc_gm_latitude_row">
				<td class="key"><label class="fc-prop-lbl addrint_lat-lbl">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_LATITUDE').'</label></td>
				<td><input type="text" class="fcfield_textval addrint_lat ' . (in_array('latitude', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr . ' id="'.$elementid_n.'_lat" name="'.$fieldname_n.'[lat]" value="'.htmlspecialchars($value['lat'], ENT_COMPAT, 'UTF-8').'" size="50" maxlength="10" ' . (!$edit_latlon || $edit_latlon == 2 ? ' readonly="readonly" ' : '') . '/></td>
			</tr>
			<tr class="fc_gm_longitude_row">
				<td class="key"><label class="fc-prop-lbl addrint_lon-lbl">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_LONGITUDE').'</label></td>
				<td><input type="text" class="fcfield_textval addrint_lon ' . (in_array('longitude', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr . ' id="'.$elementid_n.'_lon" name="'.$fieldname_n.'[lon]" value="'.htmlspecialchars($value['lon'], ENT_COMPAT, 'UTF-8').'" size="50" maxlength="10" ' . (!$edit_latlon || $edit_latlon == 2 ? ' readonly="readonly" ' : '') . '/></td>
			</tr>' .


		// MANUALLY entered PROPERTY !! Remove from form instead of hiding !!
		($use_custom_marker ? '
			<tr class="fc_gm_custom_marker_row">
			<td class="key"><label class="fc-prop-lbl fc_gm_custom_marker-lbl" for="'.$elementid_n.'_custom_marker">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_CUSTOM_MARKER').'</label></td>
				<td>' .
					JHtml::_('select.genericlist', $custom_markers, $fieldname_n.'[custom_marker]',
						' class="fc_gm_custom_marker has_select2_lib" data-marker-base-url = "' . $custom_marker_url_base . '" ' .
						' onchange="fcfield_addrint.updateMarkerIcon' . ($mapapi_edit === 'googlemap' ? '' : '_OS') . '(this.id.replace(\'_custom_marker\', \'\'), \''.$field_name_js.'\');" ',
						'value', 'text', $value['custom_marker'], $elementid_n.'_custom_marker') . '
				</td>
			</tr>
			<tr class="fc_gm_marker_anchor_row">
			<td class="key"><label class="fc-prop-lbl fc_gm_marker_anchor-lbl" for="'.$elementid_n.'_marker_anchor">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ANCHOR').'</label></td>
				<td style="position: relative;">' .
					JHtml::_('select.genericlist', $marker_anchors, $fieldname_n.'[marker_anchor]',
						' class="fc_gm_marker_anchor use_select2_lib" onchange="fcfield_addrint.updateMarkerIcon' . ($mapapi_edit === 'googlemap' ? '' : '_OS') . '(this.id.replace(\'_marker_anchor\', \'\'), \''.$field_name_js.'\');" ',
						'value', 'text', $value['marker_anchor'], $elementid_n.'_marker_anchor') . '
						<button type="button" class="btn btn-small btn-sm hasPopover" data-toggle="popover" title="' .  JText::_('FLEXI_EXAMPLES', true) . '" data-content="' .  JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ANCHOR_INFO', true) . '"><span class="icon-info"></span>' .  JText::_('FLEXI_ABOUT', true) . '</button>
				</td>
			</tr>

		' : '') . '

		</tbody></table>

	</div>


	<div id="'.$elementid_n.'_addressint_map" class="fcfield_field_preview_box fcfield_addressint_map" style="display: none;">
		<div>
			' . ($mapapi_edit === "googlemap" ? '
			<div class="'.$input_grp_class.' fc-xpended" class="addrint_marker_tolerance_box">
				<label class="' . $add_on_class . ' fc-lbl-short addrint_marker_tolerance-lbl" for="'.$elementid_n.'_marker_tolerance">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_TOLERANCE').'</label>
				<input type="text" class="fcfield_textval inlineval addrint_marker_tolerance" id="'.$elementid_n.'_marker_tolerance" name="'.$fieldname_n.'[marker_tolerance]" value="50" size="7" maxlength="7" />
			</div>
			&nbsp;
			' : '') . '
			<div class="'.$input_grp_class.' fc-xpended" class="addrint_zoom_box">
				<label class="' . $add_on_class . ' fc-lbl-short addrint_zoom-lbl" for="'.$elementid_n.'_zoom" >'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ZOOM_LEVEL').'</label>
				<input type="text" class="fcfield_textval inlineval addrint_zoom" id="'.$elementid_n.'_zoom" name="'.$fieldname_n.'[zoom]" value="'.htmlspecialchars($value['zoom'], ENT_COMPAT, 'UTF-8').'" readonly="readonly" size="2" />
			</div>
		</div>

		<div class="fcfield_addressint_canvas_outer">
			<div id="map_canvas_'.$elementid_n.'" class="addrint_map_canvas" '.($map_width || $map_height  ?  'style="width:'.$map_width.'px; height:'.$map_height.'px;"' : '').'></div>
		</div>
	</div>


	<input type="hidden" id="'.$elementid_n.'_addr_formatted" name="'.$fieldname_n.'[addr_formatted]" class="addrint_addr_formatted" value="'.htmlspecialchars($value['addr_formatted'], ENT_COMPAT, 'UTF-8').'" />
	<input type="hidden" id="'.$elementid_n.'_url" name="'.$fieldname_n.'[url]" class="addrint_url" value="'.htmlspecialchars($value['url'], ENT_COMPAT, 'UTF-8').'" />
	';

	if ($addr_edit_mode === 'plaintext')
	{
		$field_html .= '
		<input type="hidden" id="'.$elementid_n.'_name" name="'.$fieldname_n.'[name]" class="addrint_name" value="'.htmlspecialchars($value['name'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_addr1" name="'.$fieldname_n.'[addr1]" class="addrint_addr1" value="'.htmlspecialchars($value['addr1'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_city" name="'.$fieldname_n.'[city]" class="addrint_city" value="'.htmlspecialchars($value['city'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_state" name="'.$fieldname_n.'[state]" class="addrint_state" value="'.htmlspecialchars($value['state'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_province" name="'.$fieldname_n.'[province]" class="addrint_province" value="'.htmlspecialchars($value['province'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_zip" name="'.$fieldname_n.'[zip]" class="addrint_zip" value="'.htmlspecialchars($value['zip'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_country" name="'.$fieldname_n.'[country]" class="addrint_country" value="'.htmlspecialchars($value['country'], ENT_COMPAT, 'UTF-8').'" />
		'
		// DO NOT STORE manually enter properties: ADDR2, ADDR3, ZIP_SUFFIX in plaintext mode !!
		/* '
		<input type="hidden" id="'.$elementid_n.'_addr2" name="'.$fieldname_n.'[addr2]" class="addrint_addr2" value="'.htmlspecialchars($value['addr2'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_addr3" name="'.$fieldname_n.'[addr3]" class="addrint_addr3" value="'.htmlspecialchars($value['addr3'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_zip_suffix" name="'.$fieldname_n.'[zip_suffix]" class="addrint_zip_suffix" value="'.htmlspecialchars($value['zip_suffix'], ENT_COMPAT, 'UTF-8').'" />
		'*/
		;
	}

	// if ($addr_edit_mode == 'formatted')
	else
	{
		$field_html .= '
			<input type="hidden" id="'.$elementid_n.'_addr_display" name="'.$fieldname_n.'[addr_display]" class="addrint_addr_display" value="'.htmlspecialchars($value['addr_display'], ENT_COMPAT, 'UTF-8').'" />
		';
	}


	/**
	 * Initialize Custom Marker Selector
	 */

	$dom_ready_js .= (!$use_custom_marker ? '' : '
		fcfield_addrint.initMarkerSelector("'.$elementid_n.'", "'.$field_name_js.'");
	');


	/**
	 * Initialize GOOGLEMAP 's (1) Autocomplete search box and (2) MAP display
	 */

	if ($mapapi_edit === 'googlemap')
	{
		$js .= '
		fcfield_addrint.LatLon["'.$elementid_n.'"] = {lat: '.($value['lat'] ? $value['lat'] : '0').', lng: '.($value['lon'] ? $value['lon'] : '0').'};
		';

		$dom_ready_js .= '
			fcfield_addrint.initAutoComplete("'.$elementid_n.'", "'.$field_name_js.'");' . /* autocomplete search */'
			' . (!$coords_are_empty ? '
				fcfield_addrint.initMap("'.$elementid_n.'", "'.$field_name_js.'");'
			: '')
		;
	}


	/**
	 * Initialize Algolia autocomplete ENGINE
	 */

	elseif ($mapapi_edit === 'algolia')
	{
		$dom_ready_js .= '
			setTimeout(function()
			{
				fcfield_addrint.initAutoComplete_OS("'.$elementid_n.'", "'.$field_name_js.'");
			}, 100);
		';
	}

	$field->html[$n] = '
		<div class="fc-field-value-properties-box ' . ($value_is_disabled ? ' fc-field-value-disabled' : '') . '">
			' . $field_html . '
		</div>
	';

	$n++;
}

if ($dom_ready_js)
{
	$js .= '
	// load autocomplete on page ready
	jQuery(document).ready(function() {
		' . $dom_ready_js . '
	});
	';
}

$document->addScriptDeclaration($js);