<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

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

$n = 0;
$_add_map = ($view!='item' && ($show_map=='category' || $show_map=='both')) || ($view=='item' && ($show_map=='item' || $show_map=='both'));
foreach ($values as $value)
{
	// generate map
	$map = '';
	if($_add_map && (!empty($value['lon']) || !empty($value['lat'])) )
	{
		$map_link = "http://maps.google.com/maps?q=".$value['lat'].",".$value['lon'];
		$map_url = "http://maps.google.com/maps/api/staticmap?center=".$value['lat'].",".$value['lon']."&zoom=".$map_zoom."&size=".$map_width."x".$map_height."&maptype=".$map_type."&markers=size:".$marker_size."%7Ccolor:".$marker_color."%7C|".$value['lat'].",".$value['lon']."&sensor=false";
		$map .= '<div class="map">';
		if($link_map==1) $map .= '<a href="'.$map_link.'" target="_blank">';
		$map .= '<img src="'.$map_url.'" width="'.$map_width.'" height="'.$map_height.'" />';
		if($link_map==1) $map .= '<br />Click Map for Directions</a>';
		$map .= '</div>';
	}
	if (
		empty($map) && empty($value['addr1']) && empty($value['addr2']) &&
		empty($value['city']) && empty($value['province'])  && empty($value['zip']) /*&& empty($value['state']) && empty($value['country'])*/
	) continue;
	
	$field->{$prop}[$n] =
		$field_prefix
		.($map_position==0 ? $map : '')
		.($value['addr1'] ? '<div class="addr1">'.$value['addr1'].'</div>' : '')
		.($value['addr2'] ? '<div class="addr2">'.$value['addr2'].'</div>' : '')
		;
	
	if ($value['city'] || $value['state'] || $value['province'])
	{
		$field->{$prop}[$n] .= '
		<div class="city-state-zip">'
			.($value['city'] ? '<span class="city">'.$value['city'].'</span>, ' : '')
			.($value['state'] ? '<span class="state">'.$list_states[$value['state']].'</span> ' : '')
			.($value['province'] ? '<span class="province">'.$value['province'].'</span> ' : '')
			.($value['zip'] ? '<span class="zip">'.$value['zip'].'</span>' : '').'
		 </div>
		 ';
	}
	
	$field->{$prop}[$n] .= ''
	 	.($value['country'] ? '<div class="country">'.JText::_('PLG_FC_ADDRESSINT_CC_'.$value['country']).'</div>' : '')
		.($map_position==1 ? $map : '')
		.$field_suffix
		;
}