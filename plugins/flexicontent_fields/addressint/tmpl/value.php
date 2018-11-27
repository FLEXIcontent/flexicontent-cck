<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// Get view
$view = JFactory::getApplication()->input->getCmd('view', '');

// Get Map Engine
$map_api = $field->parameters->get('mapapi', 'googlemap');

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
$directions_link_label = $field->parameters->get('directions_link_label', JText::_('PLG_FC_ADDRESSINT_GET_DIRECTIONS'));

$show_map = $field->parameters->get('show_map','');
$show_map = $show_map === 'both' || ($view !== 'item' && $show_map === 'category') || ($view === 'item' && $show_map === 'item');

$map_embed_type = $field->parameters->get('map_embed_type','img'); // defaults to img for backward compatibility
$map_type = $field->parameters->get('map_type','roadmap');
$map_zoom = (int) $field->parameters->get('map_zoom', 16);
$link_map = (int) $field->parameters->get('link_map', 1);

$map_position = (int) $field->parameters->get('map_position', 0);
$marker_color = $field->parameters->get('marker_color', 'red');
$marker_size  = $field->parameters->get('marker_size', 'mid');

$map_width  = (int) $field->parameters->get('map_width', 200);
$map_height = (int) $field->parameters->get('map_height', 150);

$field_prefix = $field->parameters->get('field_prefix', '');
$field_suffix = $field->parameters->get('field_suffix', '');

static $addressint_map_styles = array();

if (!isset($addressint_map_styles[$field->id]))
{
	$addressint_map_styles[$field->id] = null;
	$map_style = trim($field->parameters->get('map_style',''));

	if (strlen($map_style))
	{
		json_decode($map_style);
		if (json_last_error() == JSON_ERROR_NONE)
			$addressint_map_styles[$field->id] = $map_style;
		else
			echo '<div class="alert alert-warning"> Bad map styling was set for Address International field #: '. $field->id.'</div>';
	}
}
$map_style = $addressint_map_styles[$field->id];


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

/**
 * Per value Javascript, needed by current OpenStreet Maps implementation.
 * TODO replace this and instead use single method that reads data properties from every map's continainer DIV
 */
$js_perValue = array();

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

	if ($addr_display_mode === 'plaintext' && !empty($value['addr_display']))
	{
		$addr = '<div class="address">' . str_replace("\n", '<br />', $value['addr_display']) . '</div>';
	}

	// prefer addr_display if available
	else if ($addr_display_mode === 'formatted')
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

		$is_us = @ $value['country'] === 'US';
		$state_added = false;
		foreach($matches[1] as $match)
		{
			// $match is something like 'addr2'
			if ($is_us && ($match === 'state' || $match === 'province')) {
				if ($state_added)
					$prop_val = '';
				else
					$prop_val = @ $value['province'] ? $value['province'] : (@ $value['state'] ? @ $list_states[$value['state']] : '');
				$state_added = true;
			}
			else
				$prop_val = @ $value[$match];
			$addr = str_replace('{{'.$match.'}}', ($match === 'country' ? (!empty($value['country']) ? JText::_('PLG_FC_ADDRESSINT_CC_'.$value['country']) : '') : $prop_val), $addr);
		}

		$addr = '<div class="address">' . $addr . '</div>';
	}

	// generate link to google maps directions
	$map_link = empty($value['url'])  ?  false  :  $value['url'];

	// if no url, compatibility with old values
	if (empty($map_link))
	{
		$map_link = "https://maps.google.com/maps?q=";
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
	$map_tagid = 'map_canvas_' . $field->name . '_' . $n . '_' . $item->id;

	if ($show_map && (!empty($value['lon']) || !empty($value['lat'])))
	{
		if ($map_api === 'googlemap')
		{
			if ($map_embed_type === 'int')
			{
				$map .= '
				<div class="fc_addressint_map">
					<div class="fc_addressint_map_canvas"
						data-maplatlon="{lat: '.($value['lat'] ? $value['lat'] : '0').', lng: '.($value['lon'] ? $value['lon'] : '0').'}"
						data-mapzoom="'.($value['zoom'] ? $value['zoom'] : $map_zoom).'"
						data-mapaddr="'.$value['addr1'].'"
						data-maptype="google.maps.MapTypeId.'.strtoupper($map_type).'"
						data-mapcontent="'.htmlspecialchars(json_encode($addr.$map_directions), ENT_COMPAT, 'UTF-8' ).'"
						id="' . $map_tagid . '"' .
						($map_width || $map_height ? 'style="min-width: ' . $map_width . 'px; min-height: ' . $map_height . 'px;"' : '') . '
					>
					</div>
				</div>
				';
			}

			// default or case: googlemap with $map_embed_type === 'img'
			else
			{
				$map_url = "https://maps.google.com/maps/api/staticmap?center=".$value['lat'].",".$value['lon']
					."&amp;zoom=".($value['zoom'] ? $value['zoom'] : $map_zoom)
					."&amp;size=".$map_width."x".$map_height
					."&amp;maptype=".$map_type
					."&amp;markers=size:".$marker_size."%7Ccolor:".$marker_color."%7C|".$value['lat'].",".$value['lon']
					."&amp;sensor=false"
					.($google_maps_static_api_key ? '&amp;key=' . $google_maps_static_api_key : '');

				$map .= '
					<div class="map">
						<div class="image">
							' . ($link_map === 1 ? '<a href="'.$map_link.'" target="_blank">' : '') . '
							<img src="'.$map_url.'" '.($map_width || $map_height  ?  'style="min-width:'.$map_width.'px; min-height:'.$map_height.'px;"' : '').' alt="Map" />
							' . ($link_map === 1 ? '</a>' : '') . '
						</div>
					</div>';
			}
		}

		// default or case: $map_api === 'openstreetmap'
		else
		{
			$map .= '
			<div id="' . $map_tagid . '" '
				. ($map_width || $map_height ? 'style="min-width: ' . $map_width . 'px; min-height: ' . $map_height . 'px;"' : '') . '>
			</div>
			';

			if (empty($js_perValue))
			{
				$js_perValue[] = '
				var theMap, theMarker;
				';
			}

			$js_perValue[] = '
				theMap = L.map("' . $map_tagid . '").setView(['.($value['lat'] ? $value['lat'] : '0').','.($value['lon'] ? $value['lon'] : '0').'], '.($value['zoom'] ? $value['zoom'] : $map_zoom).');
				L.tileLayer(\'https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png\',
				{
					attribution: \'données © <a href="//osm.org/copyright">OpenStreetMap</a>/ODbL - rendu <a href="//openstreetmap.fr">OSM France</a>\',
					minZoom: 1,
					maxZoom: 20
				}).addTo(theMap);

				theMarker = L.marker(['.($value['lat'] ? $value['lat'] : '0').','.($value['lon'] ? $value['lon'] : '0').']).addTo(theMap);
				theMarker.bindPopup(\''.htmlspecialchars(json_encode($addr.$map_directions), ENT_COMPAT, 'UTF-8').'\');
			';
		}

	}

	// Skip empty value, adding an empty placeholder if field inside in field group
	if (empty($map) && empty($addr))
	{
		if ( $is_ingroup )
		{
			$field->{$prop}[$n++]	= '';
		}
		continue;
	}

	$field->{$prop}[$n] =
		$field_prefix
		.($map_position === 0 && $show_map ? $map : '')
		.($directions_position === 'before' && $show_address ? $map_directions : '')
		.($show_address ? $addr : '')
		.($directions_position === 'after' && $show_address ? $map_directions : '')
		.($map_position === 1 && $show_map ? $map : '')
		.$field_suffix;

	$n++;
}


static $addressint_view_js_added = null;

if ($addressint_view_js_added === null && $map_embed_type === 'int' && $map_api === 'googlemap')
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

		var theMap = new google.maps.Map(document.getElementById(mapBox.attr("id")), {
			center: mapLatLon,
			scrollwheel: false,
			zoom: mapZoom,
			mapTypeId: mapType,
			zoomControl: true,
			mapTypeControl: false,
			scaleControl: false,
			streetViewControl: false,
			rotateControl: false
			'.($map_style ? ',styles: '.$map_style : '').'
		});

		mapBox.addClass("has_fc_google_maps_map");
		mapBox.data("google_maps_ref", theMap);

		var myInfoWindow = new google.maps.InfoWindow({
			content: mapContent
		});

		var theMarker = new google.maps.Marker({
			map: theMap,
			position: mapLatLon,
			title: mapAddr
		});

		theMarker.addListener("click", function() {
			myInfoWindow.open(theMap, theMarker);
		});
	}

	jQuery(document).ready(function(){
		jQuery(".fc_addressint_map_canvas").each( function() {
			fc_addressint_initMap( jQuery(this) );
  	});
	});
	';

	// Load google-maps library
	flexicontent_html::loadFramework('google-maps', '', $field->parameters);
	JFactory::getDocument()->addScriptDeclaration($js);
}

// Code is WIP (work-in-progress) only for testing 1 address inside item view
static $addressint_view_js_added_OS = null;

if ($addressint_view_js_added_OS === null && $map_api === 'openstreetmap')
{
	$addressint_view_js_added_OS = true;

	// Load openstreetmap library
	flexicontent_html::loadFramework('openstreetmap', '', $field->parameters);
}


/**
 * Add per value Javascript, needed by current OpenStreet Maps implementation.
 * TODO replace this and instead use single method that reads data properties from every map's continainer DIV
 */
if (!empty($js_perValue))
{
	$js = '
	jQuery(document).ready(function()
	{
		' . implode($js_perValue, "\n") . '
	});
	';
	JFactory::getDocument()->addScriptDeclaration($js);
}
