<?php
//No direct access
defined('_JEXEC') or die('Restricted access');

// get some parameters
$google_maps_js_api_key = $field -> parameters -> get('google_maps_js_api_key', '');
$addr_edit_mode = $field -> parameters -> get('addr_edit_mode', 'plaintext');
$edit_latlon = $field -> parameters -> get('edit_latlon', 1);
$use_addr2 = $field -> parameters -> get('use_addr2', 1);
$use_addr3 = $field -> parameters -> get('use_addr3', 1);
$use_usstate = $field -> parameters -> get('use_usstate', 1);
$use_province = $field -> parameters -> get('use_province', 1);
$use_country = $field -> parameters -> get('use_country', 1);
$map_type = $field -> parameters -> get('map_type', 'roadmap');
$map_zoom = $field -> parameters -> get('map_zoom', 16);

// load google maps library
$document = JFactory::getDocument();
$document -> addScript('https://maps.google.com/maps/api/js?libraries=places' . ($google_maps_js_api_key ? '&key=' . $google_maps_js_api_key : ''));

// States drop down list
$list_states = array('' => JText::_('FLEXI_PLEASE_SELECT'), 'AL' => 'Alabama', 'AK' => 'Alaska', 'AS' => 'American Samoa', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'GU' => 'Guam', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MH' => 'Marshall Islands', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'MP' => 'Northern Mariana Islands', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PW' => 'Palau', 'PA' => 'Pennsylvania', 'PR' => 'Puerto Rico', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VI' => 'Virgin Islands', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming');
// Country drop down list
$list_countries = array('' => JText::_('FLEXI_PLEASE_SELECT'), 'AF' => 'Afghanistan', 'AX' => '&Aring;land Islands', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa', 'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda', 'BT' => 'Bhutan', 'BO' => 'Bolivia, Plurinational State of', 'BQ' => 'Bonaire, Sint Eustatius and Saba', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BV' => 'Bouvet Island', 'BR' => 'Brazil', 'IO' => 'British Indian Ocean Territory', 'BN' => 'Brunei Darussalam', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CV' => 'Cape Verde', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CX' => 'Christmas Island', 'CC' => 'Cocos (Keeling) Islands', 'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo, the Democratic Republic of the', 'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'C&ocirc;te d\'Ivoire', 'HR' => 'Croatia', 'CU' => 'Cuba', 'CW' => 'Cura&ccedil;ao', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands (Malvinas)', 'FO' => 'Faroe Islands', 'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia', 'TF' => 'French Southern Territories', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada', 'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti', 'HM' => 'Heard Island and McDonald Islands', 'VA' => 'Holy See (Vatican City State)', 'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran, Islamic Republic of', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'Korea, Democratic People\'s Republic of', 'KR' => 'Korea, Republic of', 'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Lao People\'s Democratic Republic', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MO' => 'Macao', 'MK' => 'Macedonia, the former Yugoslav Republic of', 'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico', 'FM' => 'Micronesia, Federated States of', 'MD' => 'Moldova, Republic of', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia', 'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NC' => 'New Caledonia', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue', 'NF' => 'Norfolk Island', 'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau', 'PS' => 'Palestinian Territory, Occupied', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RE' => 'R&eacute;union', 'RO' => 'Romania', 'RU' => 'Russian Federation', 'RW' => 'Rwanda', 'BL' => 'Saint Barth&eacute;lemy', 'SH' => 'Saint Helena, Ascension and Tristan da Cunha', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia', 'MF' => 'Saint Martin (French part)', 'PM' => 'Saint Pierre and Miquelon', 'VC' => 'Saint Vincent and the Grenadines', 'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SX' => 'Sint Maarten (Dutch part)', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'GS' => 'South Georgia and the South Sandwich Islands', 'SS' => 'South Sudan', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen', 'SZ' => 'Swaziland', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syrian Arab Republic', 'TW' => 'Taiwan', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania, United Republic of', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau', 'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'UM' => 'United States Minor Outlying Islands', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VE' => 'Venezuela, Bolivarian Republic of', 'VN' => 'Viet Nam', 'VG' => 'Virgin Islands, British', 'VI' => 'Virgin Islands, U.S.', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara', 'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe');

$n = 0;
foreach ($values as $value) {
	$field_html = '
	<table class="admintable">
	<tbody>
	    <tr>
	       <td class="key" align="right">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_SEARCH_ADDRESS') . '</td>
	       <td><div id="locationField"><input id="custom' . $field -> name . $n . 'autocomplete" placeholder="" class="fcfield_textval" name="custom[' . $field -> name . '][' . $n . '][autocomplete]" type="text" /></div></td>
	    </tr>
	';
	if ($addr_edit_mode == 'plaintext') {
		$field_html .= '
        <tr>
            <td class="key" align="right">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_FORMATTED_ADDRESS') . ':</td>
            <td><textarea class="fcfield_textval" id="custom' . $field -> name . $n . 'addr_display" name="custom[' . $field -> name . '][' . $n . '][addr_display]" rows="5" cols="120" ' . $required . ' />' . ($value['addr_display'] ? $value['addr_display'] : ((!empty($value['addr1']) && !empty($value['city']) && (!empty($value['province']) || !empty($value['state'])) && !empty($value['zip'])) ? ($value['addr1'] ? $value['addr1'] . "\n" : '') . ($value['addr2'] ? $value['addr2'] . "\n" : '') . ($value['addr3'] ? $value['addr3'] . "\n" : '') . ($value['city'] ? $value['city'] : '') . ($value['state'] ? ' ' . $value['state'] : '') . ($value['province'] ? ' ' . $value['province'] : '') . ($value['zip'] ? ', ' . $value['zip'] . "\n" : '') . ($value['country'] ? JText::_('PLG_FC_ADDRESSINT_CC_' . $value['country']) : '') : '')) . '</textarea></td>
        </tr>
        ';
	}
	if ($addr_edit_mode == 'formatted') {
		$field_html .= '
        <tr>
            <td class="key" align="right">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_STREET_ADDRESS') . ':</td>
            <td>
                <input type="text" class="fcfield_textval" id="custom' . $field -> name . $n . 'addr1" name="custom[' . $field -> name . '][' . $n . '][addr1]" value="' . $value['addr1'] . '" size="10" maxlength="10" ' . $required . ' />' . ($use_addr2 ? '<input type="text" class="fcfield_textval" id="custom' . $field -> name . $n . 'addr2" name="custom[' . $field -> name . '][' . $n . '][addr2]" value="' . $value['addr2'] . '" size="10" maxlength="10" />' : '') . ($use_addr3 ? '<input type="text" class="fcfield_textval" id="custom' . $field -> name . $n . 'addr3" name="custom[' . $field -> name . '][' . $n . '][addr3]" value="' . $value['addr3'] . '" size="10" maxlength="10" />' : '') . '
            </td>
        </tr>
        <tr>
            <td class="key" align="right">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_CITY') . ':</td>
            <td><input type="text" class="fcfield_textval" id="custom' . $field -> name . $n . 'city" name="custom[' . $field -> name . '][' . $n . '][city]" value="' . $value['city'] . '" size="10" maxlength="10" ' . $required . ' /></td>
        </tr>
        <tr ' . (!$use_usstate ? 'style="display:none"' : '') . '>
            <td class="key" align="right">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_US_STATE') . ':</td>
            <td>' . JHTML::_('select.genericlist', $list_states, 'custom[' . $field -> name . '][' . $n . '][state]', 0, 'value', 'text', $value['state']) . '</td>
        </tr>
        <tr ' . (!$use_province ? 'style="display:none"' : '') . '>
            <td class="key" align="right">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_NON_US_STATE_PROVINCE') . ':</td>
            <td><input type="text" class="fcfield_textval" id="custom' . $field -> name . $n . 'province" name="custom[' . $field -> name . '][' . $n . '][province]" value="' . $value['province'] . '" size="10" maxlength="10" /></td>
        </tr>
        <tr>
            <td class="key" align="right">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ZIP_POSTAL_CODE') . ':</td>
            <td><input type="text" class="fcfield_textval" id="custom' . $field -> name . $n . 'zip" name="custom[' . $field -> name . '][' . $n . '][zip]" value="' . $value['zip'] . '" size="5" maxlength="10" ' . $required . ' />
                <span ' . (!$use_zip_suffix ? 'style="display:none"' : '') . '>-<input type="text" class="fcfield_textval" id="custom' . $field -> name . $n . 'zip_suffix" name="custom[' . $field -> name . '][' . $n . '][zip_suffix]" value="' . $value['zip_suffix'] . '" size="5" maxlength="10" /></span>
            </td>
        </tr>
        <tr ' . (!$use_country ? 'style="display:none"' : '') . '>
            <td class="key" align="right">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_COUNTRY') . ':</td>
            <td>' . JHTML::_('select.genericlist', $list_countries, 'custom[' . $field -> name . '][' . $n . '][country]', $required, 'value', 'text', $value['country']) . '</td>
        </tr>
        ';
	}
	if ($edit_latlon) {
		$field_html .= '
        <tr>
            <td class="key" align="right">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_LATITUDE') . ':</td>
            <td><input type="text" class="fcfield_textval" id="custom' . $field -> name . $n . 'lat" name="custom[' . $field -> name . '][' . $n . '][lat]" value="' . $value['lat'] . '" size="10" maxlength="10" ' . $required . ' /></td>
        </tr>
        <tr>
            <td class="key" align="right">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_LONGITUDE') . ':</td>
            <td><input type="text" class="fcfield_textval" id="custom' . $field -> name . $n . 'lon" name="custom[' . $field -> name . '][' . $n . '][lon]" value="' . $value['lon'] . '" size="10" maxlength="10" ' . $required . ' /></td>
        </tr>
        ';
	}
	$field_html .= '
        <tr>
            <td class="key" align="right">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_PREVIEW') . ':</td>
            <td><div style="width:100%; max-width:480px;"><div id="map_canvas_' . $field -> name . $n . '" style="width:100%; height:0; padding-bottom:56.25%;"></div></div></td>
        </tr>
        <tr>
            <td class="key" align="right">' . JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ZOOM_LEVEL') . ':</td>
            <td><span id="custom' . $field -> name . $n . 'zoom_label">' . $value['zoom'] . '</span></td>
        </tr>
	</tbody>
	</table>
	
    <input type="hidden" id="custom' . $field -> name . $n . 'addr_formatted" name="custom[' . $field -> name . '][' . $n . '][addr_formatted]" value="' . $value['addr_formatted'] . '" />
    <input type="hidden" id="custom' . $field -> name . $n . 'url" name="custom[' . $field -> name . '][' . $n . '][url]" value="' . $value['url'] . '" />
    <input type="hidden" id="custom' . $field -> name . $n . 'zoom" name="custom[' . $field -> name . '][' . $n . '][zoom]" value="' . $value['zoom'] . '" />
    ';

	if ($addr_edit_mode == 'plaintext') {
		$field_html .= '
        <input type="hidden" id="custom' . $field -> name . $n . 'addr1" name="custom[' . $field -> name . '][' . $n . '][addr1]" value="' . $value['addr1'] . '" />
        <input type="hidden" id="custom' . $field -> name . $n . 'addr2" name="custom[' . $field -> name . '][' . $n . '][addr2]" value="' . $value['addr2'] . '" />
        <input type="hidden" id="custom' . $field -> name . $n . 'addr3" name="custom[' . $field -> name . '][' . $n . '][addr3]" value="' . $value['addr3'] . '" />
        <input type="hidden" id="custom' . $field -> name . $n . 'city" name="custom[' . $field -> name . '][' . $n . '][city]" value="' . $value['city'] . '" />
        <input type="hidden" id="custom' . $field -> name . $n . 'state" name="custom[' . $field -> name . '][' . $n . '][state]" value="' . $value['state'] . '" />
        <input type="hidden" id="custom' . $field -> name . $n . 'province" name="custom[' . $field -> name . '][' . $n . '][province]" value="' . $value['province'] . '" />
        <input type="hidden" id="custom' . $field -> name . $n . 'zip" name="custom[' . $field -> name . '][' . $n . '][zip]" value="' . $value['zip'] . '" />
        <input type="hidden" id="custom' . $field -> name . $n . 'zip_suffix" name="custom[' . $field -> name . '][' . $n . '][zip_suffix]" value="' . $value['zip_suffix'] . '" />
        <input type="hidden" id="custom' . $field -> name . $n . 'country" name="custom[' . $field -> name . '][' . $n . '][country]" value="' . $value['country'] . '" />
        ';
	}

	if ($addr_edit_mode == 'formatted') {
		$field_html .= '
        <input type="hidden" id="custom' . $field -> name . $n . 'addr_display" name="custom[' . $field -> name . '][' . $n . '][addr_display]" value="' . $value['addr_display'] . '" />
        ';
	}

	if (!$edit_latlon) {
		$field_html .= '
        <input type="hidden" id="custom' . $field -> name . $n . 'lat" name="custom[' . $field -> name . '][' . $n . '][lat]" value="' . $value['lat'] . '" />
        <input type="hidden" id="custom' . $field -> name . $n . 'lon" name="custom[' . $field -> name . '][' . $n . '][lon]" value="' . $value['lon'] . '" />        
        ';
	}
	$field_html .= '
    <script>
      
      // autocompleye object
      var autoComplete_' . $field -> name . $n . ';
      
      // initialize autocomplete
      function initAutoComplete_' . $field -> name . $n . '() {
        autoComplete_' . $field -> name . $n . ' = new google.maps.places.Autocomplete(document.getElementById("custom' . $field -> name . $n . 'autocomplete"), { types: [ "geocode" ] });
        google.maps.event.addListener(autoComplete_' . $field -> name . $n . ', "place_changed", function() {
          fillInAddress_' . $field -> name . $n . '();
        });
      }
      
      // fill address fields when autocomplete address is selected
      function fillInAddress_' . $field -> name . $n . '() {
            
        var place = autoComplete_' . $field -> name . $n . '.getPlace();
        
        console.log(place);
        
        // empty all fields
        jQuery("#custom' . $field -> name . $n . 'autocomplete, #custom' . $field -> name . $n . 'addr_display, #custom' . $field -> name . $n . 'addr_formatted, #custom' . $field -> name . $n . 'addr1, #custom' . $field -> name . $n . 'addr2, #custom' . $field -> name . $n . 'addr3, #custom' . $field -> name . $n . 'city, #custom' . $field -> name . $n . 'state, #custom' . $field -> name . $n . 'province, #custom' . $field -> name . $n . 'country, #custom' . $field -> name . $n . 'zip, #custom' . $field -> name . $n . 'zip_suffix, #custom' . $field -> name . $n . 'lat, #custom' . $field -> name . $n . 'lon ").val("");
        
        // get street address
        jQuery("#custom' . $field -> name . $n . 'addr1").val(place.formatted_address.split(",")[0]);
        
        // load city, country code, postal code
        place.address_components.forEach(function(o){
            
            switch(o.types[0]) {
                
                // load city
                case "locality":
                    jQuery("#custom' . $field -> name . $n . 'city").val(o.long_name);
                    break;
                
                // load country code
                case "country":
                    jQuery("#custom' . $field -> name . $n . 'country").val(o.short_name);
                    break;
                
                // load postal code
                case "postal_code":
                    jQuery("#custom' . $field -> name . $n . 'zip").val(o.long_name);
                    break;
                
                // load postal code suffix
                case "postal_code_suffix":
                    jQuery("#custom' . $field -> name . $n . 'zip_suffix").val(o.long_name);
                    break;
                
                // province
                case "administrative_area_level_1":
                    jQuery("#custom' . $field -> name . $n . 'province").val(o.long_name);
                    break;
                
            }
            
        });
        
        if(jQuery("#custom' . $field -> name . $n . 'country").val() == "US") {
            
            // load state
            place.address_components.forEach(function(o){
                
                if(o.types[0] == "administrative_area_level_1") {
                    
                    jQuery("#custom' . $field -> name . $n . 'state").val(o.short_name);
                    
                }
                
            });
            
        }
        
        // load suggested display address
        jQuery("#custom' . $field -> name . $n . 'addr_display, #custom' . $field -> name . $n . 'addr_formatted").val(place.formatted_address);
        
        // url to google maps
        jQuery("#custom' . $field -> name . $n . 'url").val(place.url);
        
        // default zoom level
        jQuery("#custom' . $field -> name . $n . 'zoom").val(' . $map_zoom . ');
        jQuery("#custom' . $field -> name . $n . 'zoom_label").text("' . $map_zoom . '");
        
        // latitude
        jQuery("#custom' . $field -> name . $n . 'lat").val(place.geometry.location.lat);
        
        // longitude
        jQuery("#custom' . $field -> name . $n . 'lon").val(place.geometry.location.lng);
        
        // reset map lat/lon
        myLatLon_' . $field -> name . $n . ' = place.geometry.location;
        
        // redraw map
        initMap_' . $field -> name . $n . '();
        
      }
      
      // load autocomplete on page ready
      jQuery(document).ready(function(){initAutoComplete_' . $field -> name . $n . '();});
             
        
        // map object   
        var myMap_' . $field -> name . $n . ';
        var myLatLon_' . $field -> name . $n . ' = {lat: ' . ($value['lat'] ? $value['lat'] : 0) . ', lng: ' . ($value['lon'] ? $value['lon'] : 0) . '};
        
        function initMap_' . $field -> name . $n . '(){
            
            myMap_' . $field -> name . $n . ' = new google.maps.Map(document.getElementById("map_canvas_' . $field -> name . $n . '"), {
                center: myLatLon_' . $field -> name . $n . ',
                scrollwheel: false,
                zoom: ' . $map_zoom . ',
                mapTypeId: google.maps.MapTypeId.' . strtoupper($map_type) . ',
                zoomControl: true,
                mapTypeControl: false,
                scaleControl: false,
                streetViewControl: false,
                rotateControl: false,
            });
        
            myMarker = new google.maps.Marker({
                map: myMap_' . $field -> name . $n . ',
                position: myLatLon_' . $field -> name . $n . '
            });
            
            myMap_' . $field -> name . $n . '.addListener("zoom_changed", function() {
                jQuery("#custom' . $field -> name . $n . 'zoom").val(myMap_' . $field -> name . $n . '.getZoom());
                jQuery("#custom' . $field -> name . $n . 'zoom_label").text(myMap_' . $field -> name . $n . '.getZoom());
            });
            
        }
        jQuery(document).ready(function(){initMap_' . $field -> name . $n . '();});
      
    </script>
	';
	$field -> html[$n] = $field_html;
	$n++;
}
