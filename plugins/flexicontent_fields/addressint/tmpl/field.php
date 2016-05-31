<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// Some parameter shortcuts
$required = $field->parameters->get('required', 0);
$required_class = $required ? 'required' : '';

$google_maps_js_api_key = $field->parameters->get('google_maps_js_api_key', '');
$addr_edit_mode = $field->parameters->get('addr_edit_mode', 'plaintext');
$edit_latlon  = $field->parameters->get('edit_latlon',  1);
$use_name     = $field->parameters->get('use_name',     1);
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
	$document->addScript('https://maps.google.com/maps/api/js?libraries=geometry,places' . ($google_maps_js_api_key ? '&key=' . $google_maps_js_api_key : ''));
}



// Google autocomplete search types drop down list (for geolocation)
$list_ac_types = array(
	''=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_ALL_SEARCH_TYPES',
	'geocode'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_GEOCODE',
	'address'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_ADDRESS',
	'establishment'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_BUSINESS',
	'(regions)'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_REGION',
	'(cities)'=>'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_AC_CITY'
);



// States drop down list
$list_states = array(
	''=>JText::_('FLEXI_SELECT'),
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


// initialize framework objects and other variables
$document = JFactory::getDocument();
$cparams  = JComponentHelper::getParams( 'com_flexicontent' );

$tooltip_class = 'hasTooltip';
$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';		

// Field name and HTML TAG id
$fieldname = 'custom['.$field->name.']';
$elementid = 'custom_'.$field->name;

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
	$value['addr2'] = @ $value['addr2'];
	$value['addr3'] = @ $value['addr3'];
	$value['state']    = @ $value['state'];
	$value['province'] = @ $value['province'];
	$value['country']  = @ $value['country'];
	
	$is_empty = !$value['lat'] && !$value['lon'];
	
	$field_html = '
	<table class="fc-form-tbl fcfullwidth fcinner fc-addressint-field-tbl"><tbody>
		<tr>
			<td>
				<div class="'.$input_grp_class.' fc-xpended">
					<label class="'.$add_on_class.' fc-lbl addrint-ac-lbl" for="'.$elementid_n.'_autocomplete">'.JText::_( 'PLG_FLEXICONTENT_FIELDS_ADDRESSINT_SEARCH' ).'</label>
					<input id="'.$elementid_n.'_autocomplete" placeholder="" class="input-xxlarge" name="'.$fieldname_n.'[autocomplete]" type="text" />
					<select id="'.$elementid_n.'_ac_type" class="" name="'.$fieldname_n.'[ac_type]" onchange="changeAutoCompleteType_'.$field->name.$n.'();">
						'.$ac_type_options.'
					</select>
				</div>
			</td>
		</tr>
	</tbody></table>
	
	<div class="fcfield_field_data_box fcfield_addressint_data">
	
	<div><div id="'.$elementid_n.'_messages" class="alert alert-warning fc-iblock" style="display:none;"></div></div>
	
	<table class="fc-form-tbl fcfullwidth fcinner fc-addressint-field-tbl"><tbody>
	';
	
	if($addr_edit_mode == 'plaintext')
	{
		$field_html .= '
		<tr>
			<td class="key"><span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_FORMATTED_ADDRESS').'</span></td>
			<td><textarea class="fcfield_textval" id="'.$elementid_n.'_addr_display" name="'.$fieldname_n.'[addr_display]" rows="4" cols="24" class="'.$required_class.'" />'
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
		';
	}
	if($addr_edit_mode == 'formatted') {
		$field_html .= '
		<tr '.($use_name ? '' : 'style="display:none;"').' class="fc_gm_name_row">
			<td class="key"><span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_BUSINESS_LOCATION').'</span></td>
			<td><input type="text" class="fcfield_textval '.$required_class.'" id="'.$elementid_n.'_name" name="'.$fieldname_n.'[name]" value="'.htmlspecialchars($value['name'], ENT_COMPAT, 'UTF-8').'" size="50" maxlength="100" /></td>
		</tr>
		<tr class="fc_gm_addr_row">
			<td class="key"><span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_STREET_ADDRESS').'</span></td>
			<td>
				<textarea class="fcfield_textval '.$required_class.'" id="'.$elementid_n.'_addr1" name="'.$fieldname_n.'[addr1]" maxlength="400" rows="2">'.$value['addr1'].'</textarea>'
				.($use_addr2 ? '<br/><textarea class="fcfield_textval" id="'.$elementid_n.'_addr2" name="'.$fieldname_n.'[addr2]" maxlength="400" rows="2">'.$value['addr2'].'</textarea>' : '')
				.($use_addr3 ? '<br/><textarea class="fcfield_textval" id="'.$elementid_n.'_addr3" name="'.$fieldname_n.'[addr3]" maxlength="400" rows="2">'.$value['addr3'].'</textarea>' : '')
				.'
			</td>
		</tr>
		<tr class="fc_gm_city_row">
			<td class="key"><span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_CITY').'</span></td>
			<td><input type="text" class="fcfield_textval '.$required_class.'" id="'.$elementid_n.'_city" name="'.$fieldname_n.'[city]" value="'.htmlspecialchars($value['city'], ENT_COMPAT, 'UTF-8').'" size="50" maxlength="100" /></td>
		</tr>
		<tr '.($use_usstate ? '' : 'style="display:none;"').' class="fc_gm_usstate_row">
			<td class="key"><span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_US_STATE').'</span></td>
			<td>'.JHTML::_('select.genericlist', $list_states, $fieldname_n.'[state]', ' class="use_select2_lib fc_gm_usstate" ', 'value', 'text', $value['state'], $elementid_n.'_state').'</td>
		</tr>
		<tr '.($use_province ? '' : 'style="display:none;"').' class="fc_gm_province_row">
			<td class="key"><span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_NON_US_STATE_PROVINCE').'</span></td>
			<td><input type="text" class="fcfield_textval" id="'.$elementid_n.'_province" name="'.$fieldname_n.'[province]" value="'.htmlspecialchars($value['province'], ENT_COMPAT, 'UTF-8').'" size="50" maxlength="100" /></td>
		</tr>
		<tr class="fc_gm_zip_row">
			<td class="key"><span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ZIP_POSTAL_CODE').'</span></td>
			<td>
				<input type="text" class="fcfield_textval inlineval '.$required_class.'" id="'.$elementid_n.'_zip" name="'.$fieldname_n.'[zip]" value="'.htmlspecialchars($value['zip'], ENT_COMPAT, 'UTF-8').'" size="10" maxlength="10" />
				<span '.($use_zip_suffix ? '' : 'style="display:none"').'>&nbsp;<input type="text" class="fcfield_textval inlineval" id="'.$elementid_n.'_zip_suffix" name="'.$fieldname_n.'[zip_suffix]" value="'.htmlspecialchars($value['zip_suffix'], ENT_COMPAT, 'UTF-8').'" size="5" maxlength="10" /></span>
			</td>
		</tr>
		<tr '.($use_country ? '' : 'style="display:none;"').' class="fc_gm_country_row">
			<td class="key"><span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_COUNTRY').'</span></td>
			<td>
				'.JHTML::_('select.genericlist', $allowed_countries, $fieldname_n.'[country]', $countries_attribs, 'value', 'text', ($value['country'] ? $value['country'] : $ac_country_default), $elementid_n.'_country').'
			</td>
		</tr>
		';
	}
	
	if($edit_latlon) {
		$field_html .= '
		<tr>
			<td class="key"><span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_LATITUDE').'</span></td>
			<td><input type="text" class="fcfield_textval '.$required_class.'" id="'.$elementid_n.'_lat" name="'.$fieldname_n.'[lat]" value="'.htmlspecialchars($value['lat'], ENT_COMPAT, 'UTF-8').'" size="10" maxlength="10" /></td>
		</tr>
		<tr>
			<td class="key"><span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_LONGITUDE').'</span></td>
			<td><input type="text" class="fcfield_textval '.$required_class.'" id="'.$elementid_n.'_lon" name="'.$fieldname_n.'[lon]" value="'.htmlspecialchars($value['lon'], ENT_COMPAT, 'UTF-8').'" size="10" maxlength="10" /></td>
		</tr>
		';
	}
	
	$field_html .= '
	</tbody></table>
	</div>
	
	
	<div id="'.$elementid_n.'_addressint_map" class="fcfield_field_preview_box fcfield_addressint_map" style="display: none;">
		<table class="fc-form-tbl"><tbody>
			<tr>
				<td>
					<span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_TOLERANCE').'</span>
					<input type="text" class="fcfield_textval inlineval" id="'.$elementid_n.'_marker_tolerance" name="'.$fieldname_n.'[marker_tolerance]" value="50" size="7" maxlength="7" />
				</td>
				<td>
					<span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ZOOM_LEVEL').'</span>
					<span id="'.$elementid_n.'_zoom_label" class="alert alert-info fc-small fc-iblock">'.$value['zoom'].'</span>
				</td>
			</tr>
		</tbody></table>
		
		<div class="fcfield_addressint_canvas_outer">
			<div id="map_canvas_'.$field->name.$n.'" style="width:100%; height:0; padding-bottom:56.25%;"></div>
		</div>
	</div>
	
	
	<input type="hidden" id="'.$elementid_n.'_addr_formatted" name="'.$fieldname_n.'[addr_formatted]" value="'.htmlspecialchars($value['addr_formatted'], ENT_COMPAT, 'UTF-8').'" />
	<input type="hidden" id="'.$elementid_n.'_url" name="'.$fieldname_n.'[url]" value="'.htmlspecialchars($value['url'], ENT_COMPAT, 'UTF-8').'" />
	<input type="hidden" id="'.$elementid_n.'_zoom" name="'.$fieldname_n.'[zoom]" value="'.htmlspecialchars($value['zoom'], ENT_COMPAT, 'UTF-8').'" />
	';
	
	if($addr_edit_mode == 'plaintext') {
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

	if($addr_edit_mode == 'formatted') {
		$field_html .= '
		<input type="hidden" id="'.$elementid_n.'_addr_display" name="'.$fieldname_n.'[addr_display]" value="'.htmlspecialchars($value['addr_display'], ENT_COMPAT, 'UTF-8').'" />
		';
	}
	
	if(!$edit_latlon) {
		$field_html .= '
		<input type="hidden" id="'.$elementid_n.'_lat" name="'.$fieldname_n.'[lat]" value="'.htmlspecialchars($value['lat'], ENT_COMPAT, 'UTF-8').'" />
		<input type="hidden" id="'.$elementid_n.'_lon" name="'.$fieldname_n.'[lon]" value="'.htmlspecialchars($value['lon'], ENT_COMPAT, 'UTF-8').'" />
		';
	}
	$js = '
	
	if ('.count($ac_country_allowed_list).')
		var allowed_countries_'.$field->name.$n.' = new Array("'.implode('", "', $ac_country_allowed_list).'");
	else
		var allowed_countries_'.$field->name.$n.' = new Array();
	
	// autocomplete object
	var autoComplete_'.$field->name.$n.';
	var gmapslistener_'.$field->name.$n.';

	// initialize autocomplete
	function initAutoComplete_'.$field->name.$n.'()
	{
		var ac_input = document.getElementById("'.$elementid_n.'_autocomplete");
		var ac_type    = jQuery("#'.$elementid_n.'_ac_type").val();
		var ac_country = "'.$single_country.'";
		//window.console.log(ac_type);
		
		var ac_options = {};
		if (ac_type)    ac_options.types = [ ac_type ];
		if (ac_country) ac_options.componentRestrictions = {country: ac_country};
		
		autoComplete_'.$field->name.$n.' = new google.maps.places.Autocomplete( ac_input, ac_options );
		
		gmapslistener_'.$field->name.$n.' = google.maps.event.addListener(autoComplete_'.$field->name.$n.', "place_changed", function() {
			jQuery("#'.$elementid_n.'_messages").html("").hide();
			fillInAddress_'.$field->name.$n.'();
		});
		return true;
	}
	
	
	// re-initialize autocomplete
	function changeAutoCompleteType_'.$field->name.$n.'()
	{
		// Remove listener that update the google map on autocomplete selection
		google.maps.event.removeListener(gmapslistener_'.$field->name.$n.' );
		
		// Clone replace input to remove the currently configured autocomplete search
		var el = document.getElementById("'.$elementid_n.'_autocomplete");
		el.parentNode.replaceChild(el.cloneNode(true), el);
		
		// Attach new autocomplete search
		return initAutoComplete_'.$field->name.$n.'();
	}
	
	
	// fill address fields when autocomplete address is selected
	function fillInAddress_'.$field->name.$n.'(place)
	{
		var redrawMap = false;
		if (typeof place === "undefined" || !place)
		{
			place = autoComplete_'.$field->name.$n.'.getPlace();
			redrawMap = true;
		}
		//window.console.log(place);
		
		if (typeof place.address_components == "undefined") return;
		
		// Check allowed country, (zero length means all allowed)
		var country_valid = allowed_countries_'.$field->name.$n.'.length == 0;
		var selected_country = "";
		if (!country_valid)
		{
			//place.address_components.forEach(function(o)
			for(var j=0; j<place.address_components.length; j++)
			{
				var o = place.address_components[j];
				if (o.types[0] != "country") continue;
				selected_country = o.long_name;
				for(var i=0; i<allowed_countries_'.$field->name.$n.'.length; i++)
				{
					if (o.short_name == allowed_countries_'.$field->name.$n.'[i]) { country_valid = true; break; }
				}
				if (country_valid) break;
			}
		}
		if (!country_valid) {
			jQuery("#'.$elementid_n.'_messages").removeClass("alert-success").removeClass("alert-info").addClass("alert-warning").html(
				"'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_COUNTRY_NOT_ALLOWED_WARNING').': <b>"
				+selected_country+"</b><br/>"
				+"<b>'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_PLEASE_USE_COUNTRIES').'</b>: "
				+"'.htmlspecialchars(implode(', ', $allowed_country_names), ENT_COMPAT, 'UTF-8').'"
			).show();
			return false;
		}
		else
			jQuery("#'.$elementid_n.'_messages").html("").hide();
		
		
		// Empty all fields in case they are not set to a new value
		jQuery(""+
			"#'.$elementid_n.'_autocomplete, #'.$elementid_n.'_name, #'.$elementid_n.'_url,"+
			"#'.$elementid_n.'_addr_display, #'.$elementid_n.'_addr_formatted,"+
			"#'.$elementid_n.'_addr1, #'.$elementid_n.'_addr2, #'.$elementid_n.'_addr3,"+
			"#'.$elementid_n.'_city, #'.$elementid_n.'_state, #'.$elementid_n.'_province, #'.$elementid_n.'_country,"+
			"#'.$elementid_n.'_zip, #'.$elementid_n.'_zip_suffix, #'.$elementid_n.'_lat, #'.$elementid_n.'_lon"
		).val("").trigger("change");
		
		// load city, country code, postal code
		var country_long_name = "";
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
				jQuery("#'.$elementid_n.'_country").val(o.short_name).trigger("change");
				country_long_name = o.long_name;
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
		
		var street_address = "", index = -1, div = document.createElement("div");
		
		// Get FULL address
		if (typeof place.formatted_address != "undefined")
			street_address = place.formatted_address;
		else if (typeof place.adr_address != "undefined")
			street_address = place.adr_address;
		
		window.console.log(street_address);
		
		// Strip tags
		div.innerHTML = street_address;
		street_address = div.textContent || div.innerText; // innerText: FF >= 45  --  textContent: IE >= 9
		street_address = !!street_address ? street_address : "";  // Case div.textContent was empty string and div.innerText was undefined
		
		// Convert full-address to just street-address, by splitting at the (zip) postal code
		street_address = street_address.split( jQuery("#'.$elementid_n.'_zip").val() )[0];
		
		// Also split at the city / province or country in case that postal code (zip) was missing or in case it was placed after city or  province
		index = jQuery("#'.$elementid_n.'_city").val() ? street_address.lastIndexOf( jQuery("#'.$elementid_n.'_city").val() ) : -1;
		if (index != -1)  street_address = street_address.substring(0, index);
		
		index = jQuery("#'.$elementid_n.'_province").val() ? street_address.lastIndexOf( jQuery("#'.$elementid_n.'_province").val() ) : -1;
		if (index != -1)  street_address = street_address.substring(0, index);		
		
		if (country_long_name)  street_address = street_address.split(country_long_name)[0];
		
		// Get the street address trimming any spaces, commas
		street_address = street_address.replace(/(^\s*,)|(,\s*$)/g, "")
		jQuery("#'.$elementid_n.'_addr1").val(street_address);
		
		if(jQuery("#'.$elementid_n.'_country").val() == "US")
		{	
			// load state
			place.address_components.forEach(function(o){
				if(o.types[0] == "administrative_area_level_1")
				{
					jQuery("#'.$elementid_n.'_state").val(o.short_name).trigger("change");
				}
			});
		}
		
		// load suggested display address
		jQuery("#'.$elementid_n.'_addr_display, #'.$elementid_n.'_addr_formatted").val(place.formatted_address);
		
		// name to google maps
		jQuery("#'.$elementid_n.'_name").val(place.name);
		
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
		if (redrawMap)
		{
			initMap_'.$field->name.$n.'();
		}
	}
	
	
	// load autocomplete on page ready
	jQuery(document).ready(function(){initAutoComplete_'.$field->name.$n.'();});
	
	// map object
	var myMap_'.$field->name.$n.';
	var myLatLon_'.$field->name.$n.' = {lat: '.($value['lat'] ? $value['lat'] : '0').', lng: '.($value['lon'] ? $value['lon'] : '0').'};
	
	
	function initMap_'.$field->name.$n.'()
	{
		jQuery("#'.$elementid_n.'_addressint_map").show();  // Show map container
		
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
			draggable:true,
			animation: google.maps.Animation.DROP,
			position: myLatLon_'.$field->name.$n.'
		});
		
		google.maps.event.addListener(myMap_'.$field->name.$n.', "zoom_changed", function() {
			jQuery("#'.$elementid_n.'_zoom").val(myMap_'.$field->name.$n.'.getZoom());
			jQuery("#'.$elementid_n.'_zoom_label").text(myMap_'.$field->name.$n.'.getZoom());
		});
		
		google.maps.event.addListener(myMarker, "dragend", function (event) {
			geocodePosition_'.$field->name.$n.'( this.getPosition(), myMarker );
		});
	}
	
	
	function geocodePosition_'.$field->name.$n.'(pos, marker)
	{
		jQuery("#'.$elementid_n.'_messages")
			.removeClass("alert-success").removeClass("alert-warning").addClass("alert-info")
			.html("Searching address of new marker position ...").show();
		geocoder = new google.maps.Geocoder();
		geocoder.geocode(
			{ latLng: pos },
			function(results, status)
			{
				if (status == google.maps.GeocoderStatus.OK)
				{
					var tolerance = parseInt( jQuery("#'.$elementid_n.'_marker_tolerance").val() );
					if ( !tolerance || tolerance < 1 ) {
						tolerance = 50;
						jQuery("#'.$elementid_n.'_marker_tolerance").val(tolerance);
					}
					
					var distance = Math.round( parseInt( google.maps.geometry.spherical.computeDistanceBetween(results[0].geometry.location, pos) ) );
					if (distance > tolerance) {
						jQuery("#'.$elementid_n.'_lat").val(pos.lat);
						jQuery("#'.$elementid_n.'_lon").val(pos.lng);
						jQuery("#'.$elementid_n.'_messages")
							.removeClass("alert-success").removeClass("alert-warning").addClass("alert-info")
							.html( Joomla.JText._("PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_NOT_FOUND_WITHIN_TOLERANCE").replace("%s", tolerance) + "<br/> -" + Joomla.JText._("PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_ONLY_LONG_LAT") ).show();
					} else {
						jQuery("#'.$elementid_n.'_messages").html("");
						fillInAddress_'.$field->name.$n.'( results[0] );
						marker.setPosition( results[0].geometry.location );
						var html = ! jQuery("#'.$elementid_n.'_messages").html().length ? "" : jQuery("#'.$elementid_n.'_messages").html() + "<br/> ";
						jQuery("#'.$elementid_n.'_messages")
							.removeClass("alert-info").removeClass("alert-warning").addClass("alert-success")
							.html(html + Joomla.JText._("PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_FOUND_WITHIN_TOLERANCE").replace("%s", distance)).show();
					}
				}
				else
				{
					jQuery("#'.$elementid_n.'_lat").val(pos.lat);
					jQuery("#'.$elementid_n.'_lon").val(pos.lng);
					jQuery("#'.$elementid_n.'_messages")
						.removeClass("alert-info").removeClass("alert-success").removeClass("alert-info").addClass("alert-warning")
						.html( Joomla.JText._("PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_FOUND_WITHIN_TOLERANCE") + "<br/> -" + Joomla.JText._("PLG_FLEXICONTENT_FIELDS_ADDRESSINT_MARKER_ADDRESS_ONLY_LONG_LAT") ).show();
				}
			}
		);
	}
	
	'.($is_empty ? '' : '
	jQuery(document).ready(function(){
		initMap_'.$field->name.$n.'();
	});
	');
	JFactory::getDocument()->addScriptDeclaration($js);
	
	$field->html[$n] = $field_html;
	$n++;
}