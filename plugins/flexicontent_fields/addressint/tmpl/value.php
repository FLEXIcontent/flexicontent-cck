<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// get view
$view = JRequest::getVar('view');

// get parameters
$google_maps_js_api_key = $field->parameters->get('google_maps_js_api_key', '');
$google_maps_static_api_key = $field->parameters->get('google_maps_static_api_key', '');

$show_address = $field->parameters->get('show_address','both');
$show_address = $show_address == 'both' || ($view != 'item' && $show_address == 'category') || ($view == 'item' && $show_address == 'item');

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
$show_map = $show_map == 'both' || ($view != 'item' && $show_map == 'category') || ($view == 'item' && $show_map == 'item');

$map_embed_type = $field->parameters->get('map_embed_type','img'); // defaults to img for backward compatibility
$map_type = $field->parameters->get('map_type','roadmap');
$map_zoom = (int) $field->parameters->get('map_zoom',16);
$link_map = (int) $field->parameters->get('link_map',1);

$map_position = (int) $field->parameters->get('map_position',0);
$marker_color = $field->parameters->get('marker_color','red');
$marker_size  = $field->parameters->get('marker_size','mide');

$map_width  = (int) $field->parameters->get('map_width',200);
$map_height = (int) $field->parameters->get('map_height',150);

$field_prefix = $field->parameters->get('field_prefix','');
$field_suffix = $field->parameters->get('field_suffix','');

$style = $field->parameters->get('style','');

$list_states = array(
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

foreach ($this->values as $n => $value)
{
	// Skip value if both address and formated address are empty
	if (
		!isset($value['addr_display']) && !isset($value['addr_formatted']) && !isset($value['addr1']) && 
		!isset($value['city']) && !isset($value['state']) && !isset($value['province'])  &&
		(!isset($value['lat']) || !isset($value['lon'])) && !isset($value['url'])
	) continue;
	
	// generate address html
	$addr = '';
	
	if ($addr_display_mode == 'plaintext' && !empty($value['addr_display']))
	{
		$addr = '<div class="address">' . str_replace("\n", '<br />', $value['addr_display']) . '</div>';
	}
	
	// prefer addr_display if available
	else if ($addr_display_mode == 'formatted')
	{
		$matches = array();
		$addr = $addr_format_tmpl;
		
		// match all conditional groups first
		preg_match_all('/(\[\[.[^\]\]]*\]\])/', $addr, $matches);
		
		foreach($matches[1] as $match)
		{			
			// $match is something like '[[addr2|{{addr2}}<br />]]'
			preg_match('/\[\[(.[^\|]*)\|(.[^\]\]]*)\]\]/', $match, $cond_field);
			
			// $cond_field[1] is something like 'addr2'
			if(empty($value[$cond_field[1]])) {
					$addr = str_replace($match, '', $addr);
			}
			
			// $cond_content[2] is something like '{{addr2}}<br />'
			else {
					$addr = str_replace($match, $cond_field[2], $addr);
			}		
		}
		
		// match all field value groups
		preg_match_all('/\{\{(.[^\}\}]*)\}\}/m', $addr, $matches);
		
		$is_us = @ $value['country'] == 'US';
		$state_added = false;
		foreach($matches[1] as $match)
		{
			// $match is something like 'addr2'
			if ($is_us && ($match == 'state' || $match == 'province')) {
				if ($state_added)
					$prop_val = '';
				else
					$prop_val = @ $value['province'] ? $value['province'] : (@ $value['state'] ? @ $list_states[$value['state']] : '');
				$state_added = true;
			}
			else
				$prop_val = @ $value[$match];
			$addr = str_replace('{{'.$match.'}}', ($match == 'country' ? (!empty($value['country']) ? JText::_('PLG_FC_ADDRESSINT_CC_'.$value['country']) : '') : $prop_val), $addr);
		}
		
		$addr = '<div class="address">' . $addr . '</div>';
	}
	
	// generate link to google maps directions
	$map_link = empty($value['url'])  ?  false  :  $value['url'];
	
	// if no url, compatibility with old values
	if (empty($map_link))
	{
		$map_link = "http://maps.google.com/maps?q=";
		if(!empty($value['addr1']) && !empty($value['city']) && (!empty($value['province']) || !empty($value['state']))  && !empty($value['zip']))
		{
			$map_link .= urlencode(($value['addr1'] ? $value['addr1'].',' : '')
				.($value['city'] ? $value['city'].',' : '')
				.($value['state'] ? $value['state'].',' : ($value['province'] ? $value['province'].',' : ''))
				.($value['zip'] ? $value['zip'].',' : '')
				.($value['country'] ? JText::_('PLG_FC_ADDRESSINT_CC_'.$value['country']) : ''));
		}
		else {
			$map_link .= urlencode($value['lat'] . "," . $value['lon']); 
		}
	}

	// generate map directions link html
	$map_directions = '<div class="directions"><a href="'.$map_link.'" target="_blank">'.$directions_link_label.'</a></div>';
	
	// generate map (only if lat and lon available)
	$map = '';
	if($show_map && (!empty($value['lon']) || !empty($value['lat'])))
	{
		if($map_embed_type == 'img')
		{
			$map_url = "https://maps.google.com/maps/api/staticmap?center=".$value['lat'].",".$value['lon']
				."&amp;zoom=".($value['zoom'] ? $value['zoom'] : $map_zoom)
				."&amp;size=".$map_width."x".$map_height
				."&amp;maptype=".$map_type
				."&amp;markers=size:".$marker_size."%7Ccolor:".$marker_color."%7C|".$value['lat'].",".$value['lon']
				."&amp;sensor=false"
				.($google_maps_static_api_key ? '&amp;key=' . $google_maps_static_api_key : '');
				
			$map .= '<div class="map"><div class="image">';
			
			if($link_map == 1) $map .= '<a href="'.$map_link.'" target="_blank">';
			$map .= '<img src="'.$map_url.'" '.($map_width || $map_height  ?  'style="min-width:'.$map_width.'px; min-height:'.$map_height.'px;"' : '').' alt="Map" />';
			if($link_map == 1) $map .= '</a>';
			
			$map .= '</div></div>';
		}
		
		if ( $map_embed_type == 'int' )
		{
			$map .= '
			<div class="fc_addressint_map">
				<div
					class="fc_addressint_map_canvas"
					data-maplatlon="{lat: '.($value['lat'] ? $value['lat'] : '0').', lng: '.($value['lon'] ? $value['lon'] : '0').'}"
					data-mapzoom="'.($value['zoom'] ? $value['zoom'] : $map_zoom).'"
					data-mapaddr="'.$value['addr1'].'"
					data-maptype="google.maps.MapTypeId.'.strtoupper($map_type).'"
					data-mapcontent="'.htmlspecialchars(json_encode($addr.$map_directions), ENT_COMPAT, 'UTF-8' ).'"
					id="map_canvas_'.$field->name.'_'.$n.'_'.$field->item_id.'" '.
					($map_width || $map_height  ?  'style="min-width:'.$map_width.'px; min-height:'.$map_height.'px;"' : '').'
				>
				</div>
			</div>
			';
		}
		
		static $addressint_view_js_added = null;
		if ( $addressint_view_js_added === null && $map_embed_type == 'int' )
		{
			$addressint_view_js_added = true;
			$js = '
			function fc_addressint_initMap(mapBox)
			{
				var mapLatLon = eval("(" + mapBox.attr("data-maplatlon") + ")");
				var mapZoom   = parseInt(mapBox.attr("data-mapzoom"));
				var mapAddr   = mapBox.attr("data-mapaddr");
				var mapType   = eval("(" + mapBox.attr("data-maptype") + ")");
				var mapContent= eval("(" + mapBox.attr("data-mapcontent") + ")");
				
				var myMap = new google.maps.Map(document.getElementById(mapBox.attr("id")), {
					center: mapLatLon,
					scrollwheel: false,
					zoom: mapZoom,
					mapTypeId: mapType,
					zoomControl: true,
					mapTypeControl: false,
					scaleControl: false,
					streetViewControl: false,
					rotateControl: false,
					styles: '.$style.', 
				});
				
				var myInfoWindow = new google.maps.InfoWindow({
					content: mapContent
				});
				
				var myMarker = new google.maps.Marker({
					map: myMap,
					position: mapLatLon,
					title: mapAddr
				});
				
				myMarker.addListener("click", function() {
					myInfoWindow.open(myMap, myMarker);
				});
			}
			
			jQuery(document).ready(function(){
				jQuery(".fc_addressint_map_canvas").each( function() {
					fc_addressint_initMap( jQuery(this) );
		  	});
			});
			';
			
			$document = JFactory::getDocument();
			$document->addScript('https://maps.googleapis.com/maps/api/js' . ($google_maps_js_api_key ? '?key=' . $google_maps_js_api_key : ''));
			$document->addScriptDeclaration($js);
		}
	}
	
	if ( empty($map) && empty($addr) ) continue;
	
	$field->{$prop}[$n] =
		$field_prefix
		.($map_position == 0 && $show_map ? $map : '')
		.($directions_position == 'before' && $show_address ? $map_directions : '')
		.($show_address ? $addr : '')
		.($directions_position == 'after' && $show_address ? $map_directions : '')
		.($map_position == 1 && $show_map ? $map : '')
		.$field_suffix;
	
	$n++;
}