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
	$value['addr_display']   = @ $value['addr_display'];
	$value['addr_formatted'] = @ $value['addr_formatted'];
	$value['zip_suffix']     = @ $value['zip_suffix'];
	$value['url']  = @ $value['url'];
	$value['zoom'] = @ $value['zoom'];
	$value['name'] = @ $value['name'];
	$value['addr1'] = @ $value['addr1'];
	$value['addr2'] = @ $value['addr2'];
	$value['addr3'] = @ $value['addr3'];
	$value['state']    = @ $value['state'];
	$value['province'] = @ $value['province'];
	$value['city']  = @ $value['city'];
	$value['country']  = @ $value['country'];
	$value['zip']  = @ $value['zip'];
	$value['lat'] = @ $value['lat'];
	$value['lon'] = @ $value['lon'];
	
	$coords_are_empty = !$value['lat'] && !$value['lon'];
	$value_is_empty   =
		empty($value['addr_display']) && empty($value['addr_formatted']) && empty($value['addr1']) &&
		empty($value['city']) && empty($value['state']) && empty($value['province']) &&
		(empty($value['lat']) || empty($value['lon'])) && empty($value['url']);

	$value_is_disabled = $enable_disable_btns && $value_is_empty;
	$field->fc_form_data[$n] = (object) array('value_disabled' => $value_is_disabled);

	$disabled_class = $value_is_disabled ? ' fc-field-prop-disabled' : '';
	$disabled_attr  = $value_is_disabled ? ' disabled="disabled" ' : '';

	$google_maps_js_api_key = $field->parameters->get('google_maps_js_api_key', '');

	$field_html = '
	<div class="fcfield_field_data_box fcfield_addressint_data">
	
	<div><div id="'.$elementid_n.'_messages" class="alert alert-warning fc-iblock addrint_messages" style="display:none;"></div></div>
	
	<table class="fc-form-tbl fcfullwidth fcinner fc-addressint-field-tbl"><tbody>
		<tr>
			<td colspan="2" class="fc-nopad-h-cell">
				' . ($google_maps_js_api_key ? '' : '<span class="alert alert-warning fc-iblock">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_GOOGLE_MAPS_EMPTY_API_KEY_WARNING') . '</span>') . '
				<div class="'.$input_grp_class . ' fc-xpended-row">
					<label class="' . $add_on_class . ' fc-lbl-short addrint_autocomplete-lbl" for="'.$elementid_n.'_autocomplete" style="float: none;"><span class="icon-search"></span></label>
					<input id="'.$elementid_n.'_autocomplete" class="addrint_autocomplete" name="'.$fieldname_n.'[autocomplete]" type="text" />
					<select id="'.$elementid_n.'_ac_type" class="addrint_ac_type" name="'.$fieldname_n.'[ac_type]" onchange="fcfield_addrint.changeAutoCompleteType(this.id.replace(\'_ac_type\', \'\'), \''.$field->name.'\');">
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
			.($value['name'] ? $value['name']."\n" : '')
			.($value['addr_display'] ? $value['addr_display'] :
				(
					(
					!empty($value['addr1'])    || !empty($value['city']) ||
					!empty($value['province']) || !empty($value['state']) ||
					!empty($value['zip'])
					) ?
					 ($value['addr1'] ? $value['addr1'] . "\n" : '')
					.($value['addr2'] ? $value['addr2'] . "\n" : '')
					.($value['addr3'] ? $value['addr3'] . "\n" : '')
					.($value['city'] || $value['state'] ? ' '
						.($value['city']  ? $value['city']  : '')
						.($value['state'] ? $value['state'] : '')
					 : ''
					)
					.($value['province'] ? ' '  . $value['province'] : '')
					.($value['zip']      ? ', ' . $value['zip']
						 .($value['zip_suffix'] ? ' '.$value['zip_suffix'] : '') . "\n"
					 : ''
					)
					.($value['country']  ? JText::_('PLG_FC_ADDRESSINT_CC_'.$value['country']) . "\n" : '')
				: ''
				)
			)
			.'</textarea>
			</td>
		</tr>
	') .


	($addr_edit_mode != 'formatted' ? '' : '
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
		<tr '.($use_usstate ? '' : 'style="display:none;"').' class="fc_gm_usstate_row">
			<td class="key"><label class="fc-prop-lbl fc_gm_usstate-lbl" for="'.$elementid_n.'_state">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_US_STATE').'</label></td>
			<td>'.JHtml::_('select.genericlist', $list_states, $fieldname_n.'[state]', ' class="use_select2_lib fc_gm_usstate ' . ($use_usstate && in_array('us_state', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr, 'value', 'text', $value['state'], $elementid_n.'_state').'</td>
		</tr>
		<tr '.($use_province ? '' : 'style="display:none;"').' class="fc_gm_province_row">
			<td class="key"><label class="fc-prop-lbl fc_gm_province-lbl" for="'.$elementid_n.'_province">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_NON_US_STATE_PROVINCE').'</label></td>
			<td><input type="text" class="fcfield_textval fc_gm_province ' . ($use_province && in_array('non_us_state_province', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr . ' id="'.$elementid_n.'_province" name="'.$fieldname_n.'[province]" value="'.htmlspecialchars($value['province'], ENT_COMPAT, 'UTF-8').'" size="50" maxlength="100" /></td>
		</tr>
		<tr class="fc_gm_zip_row">
			<td class="key"><label class="fc-prop-lbl addrint_zip-lbl" for="'.$elementid_n.'_zip">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ZIP_POSTAL_CODE').'</label></td>
			<td>
				<input type="text" class="fcfield_textval inlineval addrint_zip ' . (in_array('zip_postal_code', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr . ' id="'.$elementid_n.'_zip" name="'.$fieldname_n.'[zip]" value="'.htmlspecialchars($value['zip'], ENT_COMPAT, 'UTF-8').'" size="10" maxlength="10" />
				<span '.($use_zip_suffix ? '' : 'style="display:none"').'>&nbsp;<input type="text" class="fcfield_textval inlineval addrint_zip_suffix" id="'.$elementid_n.'_zip_suffix" name="'.$fieldname_n.'[zip_suffix]" value="'.htmlspecialchars($value['zip_suffix'], ENT_COMPAT, 'UTF-8').'" size="5" maxlength="10" /></span>
			</td>
		</tr>
		<tr '.($use_country ? '' : 'style="display:none;"').' class="fc_gm_country_row">
			<td class="key"><label class="fc-prop-lbl fc_gm_country-lbl" for="'.$elementid_n.'_country">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_COUNTRY').'</label></td>
			<td>
				'.JHtml::_('select.genericlist', $allowed_countries, $fieldname_n.'[country]', $countries_attribs . ' class="use_select2_lib fc_gm_country ' . ($use_country && in_array('country', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr, 'value', 'text', ($value['country'] ? $value['country'] : $ac_country_default), $elementid_n.'_country').'
			</td>
		</tr>
	') .


	(!$edit_latlon ? '' : '
		<tr>
			<td class="key"><label class="fc-prop-lbl addrint_lat-lbl">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_LATITUDE').'</label></td>
			<td><input type="text" class="fcfield_textval addrint_lat ' . (in_array('latitude', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr . ' id="'.$elementid_n.'_lat" name="'.$fieldname_n.'[lat]" value="'.htmlspecialchars($value['lat'], ENT_COMPAT, 'UTF-8').'" size="50" maxlength="10" /></td>
		</tr>
		<tr>
			<td class="key"><label class="fc-prop-lbl addrint_lon-lbl">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_LONGITUDE').'</label></td>
			<td><input type="text" class="fcfield_textval addrint_lon ' . (in_array('longitude', $required_props) ? ' required' : '') . $disabled_class . '" ' . $disabled_attr . ' id="'.$elementid_n.'_lon" name="'.$fieldname_n.'[lon]" value="'.htmlspecialchars($value['lon'], ENT_COMPAT, 'UTF-8').'" size="50" maxlength="10" /></td>
		</tr>
	') .


	'
	</tbody></table>
	</div>
	
	
	<div id="'.$elementid_n.'_addressint_map" class="fcfield_field_preview_box fcfield_addressint_map" style="display: none;">
		<div>
			<div class="'.$input_grp_class.' fc-xpended">
				<label class="' . $add_on_class . ' fc-lbl-short addrint_marker_tolerance-lbl">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_TOLERANCE').'</label>
				<input type="text" class="fcfield_textval inlineval addrint_marker_tolerance" id="'.$elementid_n.'_marker_tolerance" name="'.$fieldname_n.'[marker_tolerance]" value="50" size="7" maxlength="7" />
			</div>
			&nbsp;
			<div class="'.$input_grp_class.' fc-xpended">
				<label class="' . $add_on_class . ' fc-lbl-short addrint_zoom-lbl">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ZOOM_LEVEL').'</label>
				<span id="'.$elementid_n.'_zoom_label" class="' . $add_on_class . ' addrint_zoom_label">'.$value['zoom'].'</span>
			</div>
		</div>

		<div class="fcfield_addressint_canvas_outer">
			<div id="map_canvas_'.$elementid_n.'" class="addrint_map_canvas" '.($map_width || $map_height  ?  'style="width:'.$map_width.'px; height:'.$map_height.'px;"' : '').'></div>
		</div>
	</div>
	
	
	<input type="hidden" id="'.$elementid_n.'_addr_formatted" name="'.$fieldname_n.'[addr_formatted]" value="'.htmlspecialchars($value['addr_formatted'], ENT_COMPAT, 'UTF-8').'" />
	<input type="hidden" id="'.$elementid_n.'_url" name="'.$fieldname_n.'[url]" value="'.htmlspecialchars($value['url'], ENT_COMPAT, 'UTF-8').'" />
	<input type="hidden" id="'.$elementid_n.'_zoom" name="'.$fieldname_n.'[zoom]" value="'.htmlspecialchars($value['zoom'], ENT_COMPAT, 'UTF-8').'" />
	';
	
	if($addr_edit_mode == 'plaintext')
	{
		$field_html .= '
		<input type="hidden" id="'.$elementid_n.'_name" name="'.$fieldname_n.'[name]" value="'.htmlspecialchars($value['name'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_addr1" name="'.$fieldname_n.'[addr1]" value="'.htmlspecialchars($value['addr1'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_addr2" name="'.$fieldname_n.'[addr2]" value="'.htmlspecialchars($value['addr2'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_addr3" name="'.$fieldname_n.'[addr3]" value="'.htmlspecialchars($value['addr3'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_city" name="'.$fieldname_n.'[city]" value="'.htmlspecialchars($value['city'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_state" name="'.$fieldname_n.'[state]" value="'.htmlspecialchars($value['state'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_province" name="'.$fieldname_n.'[province]" value="'.htmlspecialchars($value['province'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_zip" name="'.$fieldname_n.'[zip]" value="'.htmlspecialchars($value['zip'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_zip_suffix" name="'.$fieldname_n.'[zip_suffix]" value="'.htmlspecialchars($value['zip_suffix'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_country" name="'.$fieldname_n.'[country]" value="'.htmlspecialchars($value['country'], ENT_COMPAT, 'UTF-8').'" />
		';
	}

	if($addr_edit_mode == 'formatted')
	{
		$field_html .= '
		<input type="hidden" id="'.$elementid_n.'_addr_display" name="'.$fieldname_n.'[addr_display]" value="'.htmlspecialchars($value['addr_display'], ENT_COMPAT, 'UTF-8').'" />
		';
	}
	
	if(!$edit_latlon)
	{
		$field_html .= '
		<input type="hidden" id="'.$elementid_n.'_lat" name="'.$fieldname_n.'[lat]" class="addrint_lat" value="'.htmlspecialchars($value['lat'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_lon" name="'.$fieldname_n.'[lon]" class="addrint_lon" value="'.htmlspecialchars($value['lon'], ENT_COMPAT, 'UTF-8').'" />
		';
	}

	$js .= '
	fcfield_addrint.LatLon["'.$elementid_n.'"] =  {lat: '.($value['lat'] ? $value['lat'] : '0').', lng: '.($value['lon'] ? $value['lon'] : '0').'};
	';
	
	$dom_ready_js .= '
		fcfield_addrint.initAutoComplete("'.$elementid_n.'", "'.$field->name.'");' . /* autocomplete search */'
		'.($coords_are_empty ? '' : 'fcfield_addrint.initMap("'.$elementid_n.'", "'.$field->name.'");') . /* initialize map */'
	';

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
	jQuery(document).ready(function() {'
		.$dom_ready_js.
	'});
	';
}

$document->addScriptDeclaration($js);
