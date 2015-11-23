<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// Some parameter shortcuts
$required = $field->parameters->get('required', 0);
$required = $required ? ' class="required"' : '';

$google_maps_js_api_key = $field->parameters->get('google_maps_js_api_key', '');
$addr_edit_mode = $field->parameters->get('addr_edit_mode', 'plaintext');
$edit_latlon = $field->parameters->get('edit_latlon', 1);
$use_addr2    = $field->parameters->get('use_addr2',    1);
$use_addr3    = $field->parameters->get('use_addr3',    1);
$use_usstate  = $field->parameters->get('use_usstate',  1);
$use_province = $field->parameters->get('use_province', 1);
$use_zip_suffix = $field->parameters->get('use_zip_suffix', 1);
$use_country  = $field->parameters->get('use_country',  1);
$map_type = $field->parameters->get('map_type', 'roadmap');
$map_zoom = $field->parameters->get('map_zoom', 16);

// Add needed JS/CSS
static $js_added = null;
if ($js_added === null)
{
	$js_added = true;
	$document = JFactory::getDocument();
	// Load google maps library
	$document->addScript('https://maps.google.com/maps/api/js?libraries=places' . ($google_maps_js_api_key ? '&key=' . $google_maps_js_api_key : ''));
}

// States drop down list
$list_states = array(
	''=>JText::_('FLEXI_PLEASE_SELECT'),
	'AL'=>'Alabama',
	'AK'=>'Alaska',
	'AS'=>'American Samoa',
	'AZ'=>'Arizona',
	'AR'=>'Arkansas',
	'CA'=>'California',
	'CO'=>'Colorado',
	'CT'=>'Connecticut',
	'DE'=>'Delaware',
	'DC'=>'District of Columbia',
	'FL'=>'Florida',
	'GA'=>'Georgia',
	'GU'=>'Guam',
	'HI'=>'Hawaii',
	'ID'=>'Idaho',
	'IL'=>'Illinois',
	'IN'=>'Indiana',
	'IA'=>'Iowa',
	'KS'=>'Kansas',
	'KY'=>'Kentucky',
	'LA'=>'Louisiana',
	'ME'=>'Maine',
	'MH'=>'Marshall Islands',
	'MD'=>'Maryland',
	'MA'=>'Massachusetts',
	'MI'=>'Michigan',
	'MN'=>'Minnesota',
	'MS'=>'Mississippi',
	'MO'=>'Missouri',
	'MT'=>'Montana',
	'NE'=>'Nebraska',
	'NV'=>'Nevada',
	'NH'=>'New Hampshire',
	'NJ'=>'New Jersey',
	'NM'=>'New Mexico',
	'NY'=>'New York',
	'NC'=>'North Carolina',
	'ND'=>'North Dakota',
	'MP'=>'Northern Mariana Islands',
	'OH'=>'Ohio',
	'OK'=>'Oklahoma',
	'OR'=>'Oregon',
	'PW'=>'Palau',
	'PA'=>'Pennsylvania',
	'PR'=>'Puerto Rico',
	'RI'=>'Rhode Island',
	'SC'=>'South Carolina',
	'SD'=>'South Dakota',
	'TN'=>'Tennessee',
	'TX'=>'Texas',
	'UT'=>'Utah',
	'VT'=>'Vermont',
	'VI'=>'Virgin Islands',
	'VA'=>'Virginia',
	'WA'=>'Washington',
	'WV'=>'West Virginia',
	'WI'=>'Wisconsin',
	'WY'=>'Wyoming'
);

// Country drop down list
$list_countries = array(
	''=>JText::_('FLEXI_PLEASE_SELECT'),
	'AF'=>'Afghanistan',
	'AX'=>'&Aring;land Islands',
	'AL'=>'Albania',
	'DZ'=>'Algeria',
	'AS'=>'American Samoa',
	'AD'=>'Andorra',
	'AO'=>'Angola',
	'AI'=>'Anguilla',
	'AQ'=>'Antarctica',
	'AG'=>'Antigua and Barbuda',
	'AR'=>'Argentina',
	'AM'=>'Armenia',
	'AW'=>'Aruba',
	'AU'=>'Australia',
	'AT'=>'Austria',
	'AZ'=>'Azerbaijan',
	'BS'=>'Bahamas',
	'BH'=>'Bahrain',
	'BD'=>'Bangladesh',
	'BB'=>'Barbados',
	'BY'=>'Belarus',
	'BE'=>'Belgium',
	'BZ'=>'Belize',
	'BJ'=>'Benin',
	'BM'=>'Bermuda',
	'BT'=>'Bhutan',
	'BO'=>'Bolivia, Plurinational State of',
	'BQ'=>'Bonaire, Sint Eustatius and Saba',
	'BA'=>'Bosnia and Herzegovina',
	'BW'=>'Botswana',
	'BV'=>'Bouvet Island',
	'BR'=>'Brazil',
	'IO'=>'British Indian Ocean Territory',
	'BN'=>'Brunei Darussalam',
	'BG'=>'Bulgaria',
	'BF'=>'Burkina Faso',
	'BI'=>'Burundi',
	'KH'=>'Cambodia',
	'CM'=>'Cameroon',
	'CA'=>'Canada',
	'CV'=>'Cape Verde',
	'KY'=>'Cayman Islands',
	'CF'=>'Central African Republic',
	'TD'=>'Chad',
	'CL'=>'Chile',
	'CN'=>'China',
	'CX'=>'Christmas Island',
	'CC'=>'Cocos (Keeling) Islands',
	'CO'=>'Colombia',
	'KM'=>'Comoros',
	'CG'=>'Congo',
	'CD'=>'Congo, the Democratic Republic of the',
	'CK'=>'Cook Islands',
	'CR'=>'Costa Rica',
	'CI'=>'C&ocirc;te d\'Ivoire',
	'HR'=>'Croatia',
	'CU'=>'Cuba',
	'CW'=>'Cura&ccedil;ao',
	'CY'=>'Cyprus',
	'CZ'=>'Czech Republic',
	'DK'=>'Denmark',
	'DJ'=>'Djibouti',
	'DM'=>'Dominica',
	'DO'=>'Dominican Republic',
	'EC'=>'Ecuador',
	'EG'=>'Egypt',
	'SV'=>'El Salvador',
	'GQ'=>'Equatorial Guinea',
	'ER'=>'Eritrea',
	'EE'=>'Estonia',
	'ET'=>'Ethiopia',
	'FK'=>'Falkland Islands (Malvinas)',
	'FO'=>'Faroe Islands',
	'FJ'=>'Fiji',
	'FI'=>'Finland',
	'FR'=>'France',
	'GF'=>'French Guiana',
	'PF'=>'French Polynesia',
	'TF'=>'French Southern Territories',
	'GA'=>'Gabon',
	'GM'=>'Gambia',
	'GE'=>'Georgia',
	'DE'=>'Germany',
	'GH'=>'Ghana',
	'GI'=>'Gibraltar',
	'GR'=>'Greece',
	'GL'=>'Greenland',
	'GD'=>'Grenada',
	'GP'=>'Guadeloupe',
	'GU'=>'Guam',
	'GT'=>'Guatemala',
	'GG'=>'Guernsey',
	'GN'=>'Guinea',
	'GW'=>'Guinea-Bissau',
	'GY'=>'Guyana',
	'HT'=>'Haiti',
	'HM'=>'Heard Island and McDonald Islands',
	'VA'=>'Holy See (Vatican City State)',
	'HN'=>'Honduras',
	'HK'=>'Hong Kong',
	'HU'=>'Hungary',
	'IS'=>'Iceland',
	'IN'=>'India',
	'ID'=>'Indonesia',
	'IR'=>'Iran, Islamic Republic of',
	'IQ'=>'Iraq',
	'IE'=>'Ireland',
	'IM'=>'Isle of Man',
	'IL'=>'Israel',
	'IT'=>'Italy',
	'JM'=>'Jamaica',
	'JP'=>'Japan',
	'JE'=>'Jersey',
	'JO'=>'Jordan',
	'KZ'=>'Kazakhstan',
	'KE'=>'Kenya',
	'KI'=>'Kiribati',
	'KP'=>'Korea, Democratic People\'s Republic of',
	'KR'=>'Korea, Republic of',
	'KW'=>'Kuwait',
	'KG'=>'Kyrgyzstan',
	'LA'=>'Lao People\'s Democratic Republic',
	'LV'=>'Latvia',
	'LB'=>'Lebanon',
	'LS'=>'Lesotho',
	'LR'=>'Liberia',
	'LY'=>'Libya',
	'LI'=>'Liechtenstein',
	'LT'=>'Lithuania',
	'LU'=>'Luxembourg',
	'MO'=>'Macao',
	'MK'=>'Macedonia, the former Yugoslav Republic of',
	'MG'=>'Madagascar',
	'MW'=>'Malawi',
	'MY'=>'Malaysia',
	'MV'=>'Maldives',
	'ML'=>'Mali',
	'MT'=>'Malta',
	'MH'=>'Marshall Islands',
	'MQ'=>'Martinique',
	'MR'=>'Mauritania',
	'MU'=>'Mauritius',
	'YT'=>'Mayotte',
	'MX'=>'Mexico',
	'FM'=>'Micronesia, Federated States of',
	'MD'=>'Moldova, Republic of',
	'MC'=>'Monaco',
	'MN'=>'Mongolia',
	'ME'=>'Montenegro',
	'MS'=>'Montserrat',
	'MA'=>'Morocco',
	'MZ'=>'Mozambique',
	'MM'=>'Myanmar',
	'NA'=>'Namibia',
	'NR'=>'Nauru',
	'NP'=>'Nepal',
	'NL'=>'Netherlands',
	'NC'=>'New Caledonia',
	'NZ'=>'New Zealand',
	'NI'=>'Nicaragua',
	'NE'=>'Niger',
	'NG'=>'Nigeria',
	'NU'=>'Niue',
	'NF'=>'Norfolk Island',
	'MP'=>'Northern Mariana Islands',
	'NO'=>'Norway',
	'OM'=>'Oman',
	'PK'=>'Pakistan',
	'PW'=>'Palau',
	'PS'=>'Palestinian Territory, Occupied',
	'PA'=>'Panama',
	'PG'=>'Papua New Guinea',
	'PY'=>'Paraguay',
	'PE'=>'Peru',
	'PH'=>'Philippines',
	'PN'=>'Pitcairn',
	'PL'=>'Poland',
	'PT'=>'Portugal',
	'PR'=>'Puerto Rico',
	'QA'=>'Qatar',
	'RE'=>'R&eacute;union',
	'RO'=>'Romania',
	'RU'=>'Russian Federation',
	'RW'=>'Rwanda',
	'BL'=>'Saint Barth&eacute;lemy',
	'SH'=>'Saint Helena, Ascension and Tristan da Cunha',
	'KN'=>'Saint Kitts and Nevis',
	'LC'=>'Saint Lucia',
	'MF'=>'Saint Martin (French part)',
	'PM'=>'Saint Pierre and Miquelon',
	'VC'=>'Saint Vincent and the Grenadines',
	'WS'=>'Samoa',
	'SM'=>'San Marino',
	'ST'=>'Sao Tome and Principe',
	'SA'=>'Saudi Arabia',
	'SN'=>'Senegal',
	'RS'=>'Serbia',
	'SC'=>'Seychelles',
	'SL'=>'Sierra Leone',
	'SG'=>'Singapore',
	'SX'=>'Sint Maarten (Dutch part)',
	'SK'=>'Slovakia',
	'SI'=>'Slovenia',
	'SB'=>'Solomon Islands',
	'SO'=>'Somalia',
	'ZA'=>'South Africa',
	'GS'=>'South Georgia and the South Sandwich Islands',
	'SS'=>'South Sudan',
	'ES'=>'Spain',
	'LK'=>'Sri Lanka',
	'SD'=>'Sudan',
	'SR'=>'Suriname',
	'SJ'=>'Svalbard and Jan Mayen',
	'SZ'=>'Swaziland',
	'SE'=>'Sweden',
	'CH'=>'Switzerland',
	'SY'=>'Syrian Arab Republic',
	'TW'=>'Taiwan',
	'TJ'=>'Tajikistan',
	'TZ'=>'Tanzania, United Republic of',
	'TH'=>'Thailand',
	'TL'=>'Timor-Leste',
	'TG'=>'Togo',
	'TK'=>'Tokelau',
	'TO'=>'Tonga',
	'TT'=>'Trinidad and Tobago',
	'TN'=>'Tunisia',
	'TR'=>'Turkey',
	'TM'=>'Turkmenistan',
	'TC'=>'Turks and Caicos Islands',
	'TV'=>'Tuvalu',
	'UG'=>'Uganda',
	'UA'=>'Ukraine',
	'AE'=>'United Arab Emirates',
	'GB'=>'United Kingdom',
	'US'=>'United States',
	'UM'=>'United States Minor Outlying Islands',
	'UY'=>'Uruguay',
	'UZ'=>'Uzbekistan',
	'VU'=>'Vanuatu',
	'VE'=>'Venezuela, Bolivarian Republic of',
	'VN'=>'Viet Nam',
	'VG'=>'Virgin Islands, British',
	'VI'=>'Virgin Islands, U.S.',
	'WF'=>'Wallis and Futuna',
	'EH'=>'Western Sahara',
	'YE'=>'Yemen',
	'ZM'=>'Zambia',
	'ZW'=>'Zimbabwe'
);


// Optionally silently enforce single country
$single_country = $field->parameters->get('single_country',  '');
if ($single_country && !$use_country && !isset($list_countries[$single_country]) )
{
	$field->html[-1] = '<br/><div class="alert">Invalid (parameter) single country CODE: '.$single_country.'</div> <strong>Valid country codes</strong><br/> '.print_r($list_countries, true);
	$single_country = '';
}

// Field name and HTML TAG id
$fieldname = 'custom['.$field->name.']';
$elementid = 'custom_'.$field->name;

$n = 0;
foreach ($values as $value)
{
	$fieldname_n = $fieldname.'['.$n.']';
	$elementid_n = $elementid.'_'.$n;
	
	$field_html = '
	<table class="fc-form-tbl fcinner fc-addressint-field-tbl"><tbody>
		<tr>
			<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_SEARCH_ADDRESS').'</td>
			<td>
				<div id="locationField">
					<input id="'.$elementid_n.'_autocomplete" placeholder="" class="fcfield_textval" name="'.$fieldname_n.'[autocomplete]" type="text" />
				</div>
			</td>
		</tr>
	';
	if($addr_edit_mode == 'plaintext') {
		$field_html .= '
		<tr>
			<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_FORMATTED_ADDRESS').':</td>
			<td><textarea class="fcfield_textval" id="'.$elementid_n.'_addr_display" name="'.$fieldname_n.'[addr_display]" rows="5" cols="120" '.$required.' />'
			.($value['addr_display'] ? $value['addr_display'] :
			((!empty($value['addr1']) && !empty($value['city']) && (!empty($value['province']) || !empty($value['state']))  && !empty($value['zip'])) ?
			($value['addr1'] ? $value['addr1']."\n" : '')
			.($value['addr2'] ? $value['addr2']."\n" : '')
			.($value['addr3'] ? $value['addr3']."\n" : '')
			.($value['city'] ? $value['city'] : '')
			.($value['state'] ? ' '. $value['state'] : '')
			.($value['province'] ? ' '.$value['province'] : '')
			.($value['zip'] ? ', '.$value['zip']."\n" : '')
			.($value['country'] ? JText::_('PLG_FC_ADDRESSINT_CC_'.$value['country']) : '')
			: '')
			)
			.'</textarea>
			</td>
		</tr>
		';
	}
	if($addr_edit_mode == 'formatted') {
		$field_html .= '
		<tr>
			<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_STREET_ADDRESS').':</td>
			<td>
				<input type="text" class="fcfield_textval" id="'.$elementid_n.'_addr1" name="'.$fieldname_n.'[addr1]" value="'.$value['addr1'].'" size="10" maxlength="10" '.$required.' />'
				.($use_addr2 ? '<input type="text" class="fcfield_textval" id="'.$elementid_n.'_addr2" name="'.$fieldname_n.'[addr2]" value="'.$value['addr2'].'" size="10" maxlength="10" />' : '')
				.($use_addr3 ? '<input type="text" class="fcfield_textval" id="'.$elementid_n.'_addr3" name="'.$fieldname_n.'[addr3]" value="'.$value['addr3'].'" size="10" maxlength="10" />' : '')
				.'
			</td>
		</tr>
		<tr>
			<td class="key"><span class="label label-info">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_CITY').'</span></td>
			<td><input type="text" class="fcfield_textval" id="'.$elementid_n.'_city" name="'.$fieldname_n.'[city]" value="'.$value['city'].'" size="50" maxlength="100" '.$required.' /></td>
		</tr>
		<tr '.($use_usstate ? '' : 'style="display:none;"').'>
			<td class="key"><span class="label label-info">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_US_STATE').'</span></td>
			<td>'.JHTML::_('select.genericlist', $list_states, $fieldname_n.'[state]', 0, 'value', 'text', $value['state']).'</td>
		</tr>
		<tr '.($use_province ? '' : 'style="display:none;"').'>
			<td class="key"><span class="label label-info">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_NON_US_STATE_PROVINCE').'</span></td>
			<td><input type="text" class="fcfield_textval" id="'.$elementid_n.'_province" name="'.$fieldname_n.'[province]" value="'.$value['province'].'" size="50" maxlength="100" /></td>
		</tr>
		<tr>
			<td class="key"><span class="label label-info">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ZIP_POSTAL_CODE').'</span></td>
			<td>
				<input type="text" class="fcfield_textval" id="'.$elementid_n.'_zip" name="'.$fieldname_n.'[zip]" value="'.$value['zip'].'" size="10" maxlength="10" '.$required.' />
				<span '.($use_zip_suffix ? '' : 'style="display:none"').'>-<input type="text" class="fcfield_textval" id="'.$elementid_n.'_zip_suffix" name="'.$fieldname_n.'[zip_suffix]" value="'.$value['zip_suffix'].'" size="5" maxlength="10" /></span>
			</td>
		</tr>
		<tr '.($use_country ? '' : 'style="display:none;"').'>
			<td class="key"><span class="label label-info">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_COUNTRY').'</span></td>
			<td>'.JHTML::_('select.genericlist', $list_countries, $fieldname_n.'[country]', $required, 'value', 'text', ($use_country ? $value['country'] : $single_country)).'</td>
		</tr>
		';
	}
	
	if($edit_latlon) {
		$field_html .= '
		<tr>
			<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_LATITUDE').':</td>
			<td><input type="text" class="fcfield_textval" id="'.$elementid_n.'_lat" name="'.$fieldname_n.'[lat]" value="'.$value['lat'].'" size="10" maxlength="10" '.$required.' /></td>
		</tr>
		<tr>
			<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_LONGITUDE').':</td>
			<td><input type="text" class="fcfield_textval" id="'.$elementid_n.'_lon" name="'.$fieldname_n.'[lon]" value="'.$value['lon'].'" size="10" maxlength="10" '.$required.' /></td>
		</tr>
		';
	}
	
	$field_html .= '
		<tr>
			<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_PREVIEW').':</td>
			<td><div style="width:100%; max-width:480px;"><div id="map_canvas_'.$field->name.$n.'" style="width:100%; height:0; padding-bottom:56.25%;"></div></div></td>
		</tr>
		<tr>
			<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ZOOM_LEVEL').':</td>
			<td><span id="'.$elementid_n.'_zoom_label">'.$value['zoom'].'</span></td>
		</tr>
	</tbody></table>
	
	<input type="hidden" id="'.$elementid_n.'_addr_formatted" name="'.$fieldname_n.'[addr_formatted]" value="'.$value['addr_formatted'].'" />
	<input type="hidden" id="'.$elementid_n.'_url" name="'.$fieldname_n.'[url]" value="'.$value['url'].'" />
	<input type="hidden" id="'.$elementid_n.'_zoom" name="'.$fieldname_n.'[zoom]" value="'.$value['zoom'].'" />
	';
	
	if($addr_edit_mode == 'plaintext') {
		$field_html .= '
		<input type="hidden" id="'.$elementid_n.'_addr1" name="'.$fieldname_n.'[addr1]" value="'.$value['addr1'].'" />
		<input type="hidden" id="'.$elementid_n.'_addr2" name="'.$fieldname_n.'[addr2]" value="'.$value['addr2'].'" />
		<input type="hidden" id="'.$elementid_n.'_addr3" name="'.$fieldname_n.'[addr3]" value="'.$value['addr3'].'" />
		<input type="hidden" id="'.$elementid_n.'_city" name="'.$fieldname_n.'[city]" value="'.$value['city'].'" />
		<input type="hidden" id="'.$elementid_n.'_state" name="'.$fieldname_n.'[state]" value="'.$value['state'].'" />
		<input type="hidden" id="'.$elementid_n.'_province" name="'.$fieldname_n.'[province]" value="'.$value['province'].'" />
		<input type="hidden" id="'.$elementid_n.'_zip" name="'.$fieldname_n.'[zip]" value="'.$value['zip'].'" />
		<input type="hidden" id="'.$elementid_n.'_zip_suffix" name="'.$fieldname_n.'[zip_suffix]" value="'.$value['zip_suffix'].'" />
		<input type="hidden" id="'.$elementid_n.'_country" name="'.$fieldname_n.'[country]" value="'.$value['country'].'" />
		';
	}

	if($addr_edit_mode == 'formatted') {
		$field_html .= '
		<input type="hidden" id="'.$elementid_n.'_addr_display" name="'.$fieldname_n.'[addr_display]" value="'.$value['addr_display'].'" />
		';
	}

	if(!$edit_latlon) {
		$field_html .= '
		<input type="hidden" id="'.$elementid_n.'_lat" name="'.$fieldname_n.'[lat]" value="'.$value['lat'].'" />
		<input type="hidden" id="'.$elementid_n.'_lon" name="'.$fieldname_n.'[lon]" value="'.$value['lon'].'" />
		';
	}
	$field_html .= '
	<script>

	// autocomplete object
	var autoComplete_'.$field->name.$n.';

	// initialize autocomplete
	function initAutoComplete_'.$field->name.$n.'() {
		autoComplete_'.$field->name.$n.' = new google.maps.places.Autocomplete(document.getElementById("'.$elementid_n.'_autocomplete"), { types: [ "geocode" ] });
		google.maps.event.addListener(autoComplete_'.$field->name.$n.', "place_changed", function() {
			fillInAddress_'.$field->name.$n.'();
		});
	}

	// fill address fields when autocomplete address is selected
	function fillInAddress_'.$field->name.$n.'()
	{
		var place = autoComplete_'.$field->name.$n.'.getPlace();
		console.log(place);
		
		// empty all fields
		jQuery("#'.$elementid_n.'_autocomplete, #'.$elementid_n.'_addr_display, #'.$elementid_n.'_addr_formatted, #'.$elementid_n.'_addr1, #'.$elementid_n.'_addr2, #'.$elementid_n.'_addr3, #'.$elementid_n.'_city, #'.$elementid_n.'_state, #'.$elementid_n.'_province, #'.$elementid_n.'_country, #'.$elementid_n.'_zip, #'.$elementid_n.'_zip_suffix, #'.$elementid_n.'_lat, #'.$elementid_n.'_lon ").val("");
		
		// get street address
		jQuery("#'.$elementid_n.'_addr1").val(place.formatted_address.split(",")[0]);
		
		// load city, country code, postal code
		place.address_components.forEach(function(o)
		{
			switch(o.types[0])
			{
				// load city
				case "locality":
				jQuery("#'.$elementid_n.'_city").val(o.long_name);
				break;
				
				// load country code
				case "country":
				jQuery("#'.$elementid_n.'_country").val(o.short_name);
				break;
				
				// load postal code
				case "postal_code":
				jQuery("#'.$elementid_n.'_zip").val(o.long_name);
				break;
				
				// load postal code suffix
				case "postal_code_suffix":
				jQuery("#'.$elementid_n.'_zip_suffix").val(o.long_name);
				break;
				
				// province
				case "administrative_area_level_1":
				jQuery("#'.$elementid_n.'_province").val(o.long_name);
				break;
			}
		});
		
		if(jQuery("#'.$elementid_n.'_country").val() == "US")
		{	
			// load state
			place.address_components.forEach(function(o){
				if(o.types[0] == "administrative_area_level_1")
				{
					jQuery("#'.$elementid_n.'_state").val(o.short_name);
				}
			});
		}
		
		// load suggested display address
		jQuery("#'.$elementid_n.'_addr_display, #'.$elementid_n.'_addr_formatted").val(place.formatted_address);
		
		// url to google maps
		jQuery("#'.$elementid_n.'_url").val(place.url);
		
		// default zoom level
		jQuery("#'.$elementid_n.'_zoom").val('.$map_zoom.');
		jQuery("#'.$elementid_n.'_zoom_label").text("'.$map_zoom.'");
		
		// latitude
		jQuery("#'.$elementid_n.'_lat").val(place.geometry.location.lat);
		
		// longitude
		jQuery("#'.$elementid_n.'_lon").val(place.geometry.location.lng);
		
		// reset map lat/lon
		myLatLon_'.$field->name.$n.' = place.geometry.location;
		
		// redraw map
		initMap_'.$field->name.$n.'();
	}
	
	// load autocomplete on page ready
	jQuery(document).ready(function(){initAutoComplete_'.$field->name.$n.'();});
	
	// map object
	var myMap_'.$field->name.$n.';
	var myLatLon_'.$field->name.$n.' = {lat: '.($value['lat']?$value['lat']:0).', lng: '.($value['lon']?$value['lon']:0).'};
	
	function initMap_'.$field->name.$n.'()
	{
		myMap_'.$field->name.$n.' = new google.maps.Map(document.getElementById("map_canvas_'.$field->name.$n.'"), {
			center: myLatLon_'.$field->name.$n.',
			scrollwheel: false,
			zoom: '.$map_zoom.',
			mapTypeId: google.maps.MapTypeId.'.strtoupper($map_type).',
			zoomControl: true,
			mapTypeControl: false,
			scaleControl: false,
			streetViewControl: false,
			rotateControl: false,
		});
		
		myMarker = new google.maps.Marker({
			map: myMap_'.$field->name.$n.',
			position: myLatLon_'.$field->name.$n.'
		});
		
		myMap_'.$field->name.$n.'.addListener("zoom_changed", function() {
			jQuery("#'.$elementid_n.'_zoom").val(myMap_'.$field->name.$n.'.getZoom());
			jQuery("#'.$elementid_n.'_zoom_label").text(myMap_'.$field->name.$n.'.getZoom());
		});
		
	}
	
	jQuery(document).ready(function(){
		initMap_'.$field->name.$n.'();
	});
	
	</script>
	';
	
	$field->html[$n] = $field_html;
	$n++;
}