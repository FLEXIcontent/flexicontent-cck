<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsAddressint extends JPlugin
{
	static $field_types = array('addressint');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsAddressint( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_addressint', JPATH_ADMINISTRATOR);
	}

	function onDisplayField(&$field, &$item)
	{
		// displays the field when editing content item
		// execute the code only if the field type match the plugin type
		$field->label = JText::_($field->label);
		if ( !in_array($field->field_type, self::$field_types) ) return;

		$editor 	= JFactory::getEditor();
		
		// some parameter shortcuts
		$required 	= $field->parameters->get( 'required', 0 ) ;						
		$required 	= $required ? ' class="required"' : '';
		
		// initialise property
		if ( !empty($field->value[0]) ) {
			$value = unserialize($field->value[0]);
		}
		else {
			$value['addr1'] = '';
			$value['addr2'] = '';
			$value['addr3'] = '';
			$value['city'] = '';
			$value['state'] = '';
			$value['province'] = '';
			$value['zip'] = '';
			$value['country'] = '';
			$value['lat'] = '';
			$value['lon'] = '';
		}
		
		$field->html  = '';
		$field->html .= '<table class="admintable" border="0" cellspacing="0" cellpadding="5"><tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_STREET_ADDRESS').':</td>';
		$field->html .= '<td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][addr1]" value="'.$value['addr1'].'" size="50" maxlength="100"'.$required.' /></td>';
		$field->html .= '</tr><tr><td class="key" align="right">&nbsp;</td>';
		$field->html .= '<td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][addr2]" value="'.$value['addr2'].'" size="50" maxlength="100" /></td>';
		$field->html .= '</tr><tr><td class="key" align="right">&nbsp;</td>';
		$field->html .= '<td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][addr3]" value="'.$value['addr3'].'" size="50" maxlength="100" /></td>';
		$field->html .= '</tr><tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_CITY').':</td>';
		$field->html .= '<td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][city]" id="city" value="'.$value['city'].'" size="50" maxlength="100" /></td>';
		$field->html .= '</tr><tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_US_STATE').':</td>';

		// generate state drop down list
		$listarrays = array(
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
						'WY'=>'Wyoming');
		
		$options = array(); 
		$options[] = JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT'));
		$display = "";
		$fval = @ $field->value[0];
		if ( !empty($fval)  && isset($listarrays[$fval]) ) {
			$display = $listarrays[$fval];
		}
		
		$field->html	.= '<td>'.JHTML::_('select.genericlist', $options, 'custom['.$field->name.'][state]', $required, 'value', 'text', $value['state']).'</td>';
		$field->html	.= '</tr><tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_NON_US_STATE_PROVINCE').':</td>';
		$field->html	.= '<td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][province]" value="'.$value['province'].'" size="50" maxlength="100" /></td>';
		$field->html	.= '</tr><tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_ZIP_POSTAL_CODE').':</td>';
		$field->html	.= '<td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][zip]" value="'.$value['zip'].'" size="10" maxlength="10"'.$required.' /></td>';
		$field->html	.= '</tr><tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_COUNTRY').':</td>';

		// generate state drop down list
		$listarrays = array(
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
						'TW'=>'Taiwan, Province of China',
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
						'ZW'=>'Zimbabwe');
		
		$options = array(); 
		$options[] = JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT'));
		$display = "";
		
		$fval = @ $field->value[0];
		if ( !empty($fval)  && isset($listarrays[$fval]) ) {
			$display = $listarrays[$fval];
		}
		
		$field->html	.= '<td>'.JHTML::_('select.genericlist', $options, 'custom['.$field->name.'][country]', $required, 'value', 'text', $value['country']).'</td>';
		$field->html	.= '</tr><tr><td></td><td><input class="fcfield-button" type="button" value="'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_GEOLOCATE').'" onclick="geolocateAddr(\'custom['.$field->name.']\', \''.$field->name.'\');" /> <input type="text" class="fcfield_textval" name="custom['.$field->name.'][lat]" value="'.$value['lat'].'" size="5" maxlength="10"'.$required.' readonly="readonly" /> <input type="text" class="fcfield_textval" name="custom['.$field->name.'][lon]" value="'.$value['lon'].'" size="5" maxlength="10"'.$required.' readonly="readonly" /> <div id="'.$field->name.'_map"><img src="http://maps.google.com/maps/api/staticmap?center='.$value['lat'].','.$value['lon'].'&zoom=12&size=250x150&maptype=roadmap&markers=size:mid%7Ccolor:red%7C|'.$value['lat'].','.$value['lon'].'&sensor=false" alt="" /></div></td>';
		$field->html	.= '</tr></table>';
		
		static $js_added = false;
		if (!$js_added) {
			$js_added = true;
			$document = JFactory::getDocument();
			$document->addScript('//maps.google.com/maps/api/js?sensor=false');
			$document->addScriptDeclaration('
			function geolocateAddr(fieldname, plainname) {
				var geocoder = new google.maps.Geocoder();
				var address = document.forms["adminForm"].elements[fieldname+"[addr1]"].value +", "+document.forms["adminForm"].elements[fieldname+"[city]"].value +", "+document.forms["adminForm"].elements[fieldname+"[state]"].value +" "+document.forms["adminForm"].elements[fieldname+"[province]"].value +", "+document.forms["adminForm"].elements[fieldname+"[zip]"].value +", "+document.forms["adminForm"].elements[fieldname+"[country]"].value;
				geocoder.geocode( { \'address\': address}, function(results, status) {
					if (status == google.maps.GeocoderStatus.OK) {
						var latitude = results[0].geometry.location.lat();
						var longitude = results[0].geometry.location.lng();
						var latfield = fieldname+"[lat]";
						var lonfield = fieldname+"[lon]";
						document.forms["adminForm"].elements[latfield].value = latitude;
						document.forms["adminForm"].elements[lonfield].value = longitude;
						document.getElementById(plainname+"_map").innerHTML = \'<img src="http://maps.google.com/maps/api/staticmap?center=\'+latitude+\',\'+longitude+\'&zoom=12&size=250x150&maptype=roadmap&markers=size:mid%7Ccolor:red%7C|\'+latitude+\',\'+longitude+\'&sensor=false" alt="Geographical address locator" />\';
					} 
					else {
						var latfield = fieldname+"[lat]";
						var lonfield = fieldname+"[lon]";
						document.forms["adminForm"].elements[latfield].value = "";
						document.forms["adminForm"].elements[lonfield].value = "";
						document.getElementById(plainname+"_map").innerHTML = \'<img src="http://maps.google.com/maps/api/staticmap?center=0,0&zoom=12&size=250x150&maptype=roadmap&markers=size:mid%7Ccolor:red%7C|0,0&sensor=false" alt="Geographical address locator" />\';
					}
				}); 
			}
			');
		}
	}
	


	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array('addr1','addr2','addr3','city','state','province','zip','country'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array('addr1','addr2','addr3','city','state','province','zip','country'), $properties_spacer=' ', $filter_func=null);
		return true;
	}

	
	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// Check if field has posted data
		if ( empty($post) ) return;
		
		// Serialize multi-property data before storing them into the DB
		$post = serialize($post);
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// displays the field in the frontend
		
		// execute the code only if the field type match the plugin type
		$field->label = JText::_($field->label);
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// get parameters
		$show_map = $field->parameters->get('show_map','none');
		$map_width = $field->parameters->get('map_width',200);
		$map_height = $field->parameters->get('map_height',150);
		$map_type = $field->parameters->get('map_type','roadmap');
		$map_zoom = $field->parameters->get('map_zoom',16);
		$link_map = $field->parameters->get('link_map',1);
		$map_position = $field->parameters->get('map_position',0);
		$marker_color = $field->parameters->get('marker_color','red');
		$marker_size = $field->parameters->get('marker_size','mide');
		$field_prefix = $field->parameters->get('field_prefix','');
		$field_suffix = $field->parameters->get('field_suffix','');
		// get view
		$view = JRequest::getVar('view');
		// get value
		$values = $field->value[0] ;
		$address = unserialize($values);
		// generate map
		$map = '';
		if(($view=='category' && ($show_map=='category' || $show_map=='both')) || ($view!='category' && ($show_map=='item' || $show_map=='both'))) {
			
			$map_link = "http://maps.google.com/maps?q=".$address['lat'].",".$address['lon'];
			$map_url = "http://maps.google.com/maps/api/staticmap?center=".$address['lat'].",".$address['lon']."&zoom=".$map_zoom."&size=".$map_width."x".$map_height."&maptype=".$map_type."&markers=size:".$marker_size."%7Ccolor:".$marker_color."%7C|".$address['lat'].",".$address['lon']."&sensor=false";
			$map .= '<div class="map">';
			if($link_map==1) $map .= '<a href="'.$map_link.'" target="_blank">';
			$map .= '<img src="'.$map_url.'" width="'.$map_width.'" height="'.$map_height.'" />';
			if($link_map==1) $map .= '<br />Click Map for Directions</a>';
			$map .= '</div>';
		}
		$field->{$prop} = $field_prefix;
		if($map_position==0) $field->{$prop} .= $map;
		if($address['addr1']) $field->{$prop} .= '<div class="addr1">'.$address['addr1'].'</div>';
		if($address['addr2']) $field->{$prop} .= '<div class="addr2">'.$address['addr2'].'</div>';
		if($address['city']||$address['state']||$address['province']) {
			$field->{$prop} .= '<div class="city-state-zip">';
			if($address['city']) $field->{$prop} .= '<span class="city">'.$address['city'].'</span>, ';
			if($address['state']) $field->{$prop} .= '<span class="state">'.$address['state'].'</span> ';
			if($address['province']) $field->{$prop} .= '<span class="province">'.$address['province'].'</span> ';
			if($address['zip']) $field->{$prop} .= '<span class="zip">'.$address['zip'].'</span>';			
			$field->{$prop} .= '</div>';
		}
		if($address['country']) $field->{$prop} .= '<div class="country">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_CC_'.$address['country']).'</div>';
		if($map_position==1) $field->{$prop} .= $map;
		$field->{$prop} .= $field_suffix;
	}
}