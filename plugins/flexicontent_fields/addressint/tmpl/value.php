<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );


// Get Map Engine
$map_api = $field->parameters->get('mapapi', 'googlemap');


/**
 * Google MAP
 */
if ($map_api === 'googlemap')
{
	// Get Map Embeed Method (defaults to 'img' for backward compatibility)
	$map_embed_type = $field->parameters->get('map_embed_type', 'img');

	// Get API Key for viewing, falling back to edit key
	$google_maps_js_api_key     = trim($field->parameters->get('google_maps_js_api_key', ''));
	$google_maps_static_api_key = trim($field->parameters->get('google_maps_static_api_key', $google_maps_js_api_key));

	if ($map_embed_type === 'int')
	{
	}
	else
	{
		$link_map     = (int) $field->parameters->get('link_map', 1);
		$marker_color = $field->parameters->get('marker_color', 'red');
		$marker_size  = $field->parameters->get('marker_size', 'mid');
	}
}
else  //  $map_api === 'openstreetmap'
{
	// Get OS Map TILE server
	$os_tile_server_url = $field->parameters->get('os_tile_server_url', 'https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png');
}


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

$map_type_view = $field->parameters->get('map_type_view', $field->parameters->get('map_type', 'roadmap'));
$map_zoom      = (int) $field->parameters->get('map_zoom', 16);
$map_position  = (int) $field->parameters->get('map_position', 0);

$map_width  = (int) $field->parameters->get('map_width', 200);
$map_height = (int) $field->parameters->get('map_height', 150);

$use_custom_marker      = (int) $field->parameters->get('use_custom_marker', 1);
$custom_marker_path     = $field->parameters->get('custom_marker_path', 'modules/mod_flexigooglemap/assets/marker');

$custom_marker_path_abs = JPATH::clean(JPATH_SITE . DS . $custom_marker_path. DS);
$custom_marker_url_base = str_replace('\\', '/', JURI::root() . $custom_marker_path . '/');

$defaut_icon_url = $map_api === 'googlemap'
	? '' //'https://maps.gstatic.com/mapfiles/api-3/images/spotlight-poi2.png'
	: 'https://unpkg.com/leaflet@1.5.1/dist/images/marker-icon.png';


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
	$value['lat'] = isset($value['lat']) ? $value['lat'] : '';
	$value['lon'] = isset($value['lon']) ? $value['lon'] : '';

	// Skip value if both address and formated address are empty
	if (
		empty($value['addr_display']) && empty($value['addr_formatted']) && empty($value['addr1']) &&
		empty($value['city']) && empty($value['state']) && empty($value['province'])  &&
		(!strlen($value['lat']) || !strlen($value['lon'])) && empty($value['url'])
	) continue;

	// Clear custom marker if feature is disabled. This will force using default markers
	$value['custom_marker'] = $use_custom_marker && !empty($value['custom_marker'])
		? $value['custom_marker']
		: '';
	$value['marker_anchor'] = $value['custom_marker'] && !empty($value['marker_anchor'])
		? $value['marker_anchor']
		: 'BotC';

	$marker_path = !empty($value['custom_marker'])
		? $custom_marker_path_abs . $value['custom_marker']
		: '';
	$marker_url  = !empty($value['custom_marker'])
		? $custom_marker_url_base . $value['custom_marker']
		: $defaut_icon_url;


	if ($marker_path || $marker_url)
	{
		// Marker Size
		list($wS, $hS) = getimagesize($marker_path ?: $marker_url);

		// Marker Anchor
		switch($value['marker_anchor'])
		{
			case 'TopL' : $wA = 0;     $hA = 0; break;
			case 'TopC' : $wA = $wS/2; $hA = 0; break;
			case 'TopR' : $wA = $wS;   $hA = 0; break;

			case 'MidL' : $wA = 0;     $hA = $hS/2; break;
			case 'MidC' : $wA = $wS/2; $hA = $hS/2; break;
			case 'MidR' : $wA = $wS;   $hA = $hS/2; break;

			case 'BotL' : $wA = 0;     $hA = $hS; break;
			case 'BotC' : $wA = $wS/2; $hA = $hS; break;
			case 'BotR' : $wA = $wS;   $hA = $hS; break;
		}
	}

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


	// Skip adding map for this address if it has empty coordinates, note latitude '0' and / or longitude '0' are not "empty", these are Equator's coordinates
	if ($show_map && strlen($value['lon']) && strlen($value['lat']))
	{
		if ($map_api === 'googlemap')
		{
			if ($map_embed_type === 'int')
			{
				$map .= '
				<div class="fc_addressint_map">
					<div class="fc_addressint_map_canvas"
						data-maplatlon="{lat: ' . ($value['lat'] ? $value['lat'] : '0') . ', lng: ' . ($value['lon'] ? $value['lon'] : '0') . '}"
						data-mapzoom="' . ($value['zoom'] ? $value['zoom'] : $map_zoom) . '"
						data-mapaddr="' . htmlspecialchars(json_encode($value['addr1']), ENT_COMPAT, 'UTF-8') . '"
						data-maptype="google.maps.MapTypeId.'.strtoupper($map_type_view).'"
						data-mapcontent="' . htmlspecialchars(json_encode($addr . $map_directions), ENT_COMPAT, 'UTF-8') . '"
            ' . ($value['custom_marker'] ? '
							data-mapicon="' . htmlspecialchars(json_encode($marker_url), ENT_COMPAT, 'UTF-8') . '"
							data-mapicon_ws="' . $wS . '"
							data-mapicon_hs="' . $hS . '"
							data-mapicon_wa="' . $wA . '"
							data-mapicon_ha="' . $hA . '"
						' : '') . '
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
				$imageMap_URL = "https://maps.google.com/maps/api/staticmap?center=".$value['lat'].",".$value['lon']
					."&amp;zoom=".($value['zoom'] ? $value['zoom'] : $map_zoom)
					."&amp;size=".$map_width."x".$map_height
					."&amp;maptype=".$map_type_view
					."&amp;markers=size:".$marker_size."%7Ccolor:".$marker_color."%7C|".$value['lat'].",".$value['lon']
					."&amp;sensor=false"
					.($google_maps_static_api_key ? '&amp;key=' . $google_maps_static_api_key : '')
					;

				$map .= '
					<div class="map">
						<div class="image">
							' . ($link_map === 1 ? '<a href="'.$map_link.'" target="_blank">' : '') . '
							<img src="'.$imageMap_URL.'" '.($map_width || $map_height  ?  'style="min-width:'.$map_width.'px; min-height:'.$map_height.'px;"' : '').' alt="Map" />
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
				L.tileLayer(\'' . $os_tile_server_url . '\',
				{
					attribution: \'données © <a href="//osm.org/copyright">OpenStreetMap</a>/ODbL - rendu <a href="//openstreetmap.fr">OSM France</a>\',
					minZoom: 1,
					maxZoom: 20
				}).addTo(theMap);

				var LeafIcon = L.Icon.extend({
					options: {}
				});

				var mapIcon = new LeafIcon({
					iconUrl: \''.$marker_url.'\',
					iconSize: [' . $wS . ', ' . $hS . '],
					iconAnchor: [' . $wA . ', ' . $hA . ']
				});
				var contentPopup = ' . json_encode($addr . $map_directions) . ';

				theMarker = L.marker(['.($value['lat'] ? $value['lat'] : '0').','.($value['lon'] ? $value['lon'] : '0').'], {icon: mapIcon}).addTo(theMap);
				theMarker.bindPopup(contentPopup);
			';
		}
	}

	$map = $map ? '
		<div class="fc_addressint_container_' . $field->id . '">' . $map . '</div>
	' : '';

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
		  ($map_position === 0 && $show_map ? $map : '')
		. ($directions_position === 'before' && $show_address ? $map_directions : '')
		. ($show_address ? $addr : '')
		. ($directions_position === 'after' && $show_address ? $map_directions : '')
		. ($map_position === 1 && $show_map ? $map : '')
		;

	$n++;
}


static $addressint_view_js_added = array();

if (!isset($addressint_view_js_added[$field->id]) && $map_api === 'googlemap' && $map_embed_type === 'int')
{
	$addressint_view_js_added[$field->id] = true;

	$map_style = $field->parameters->get('map_style', '[]');

	json_decode($map_style);

	if (json_last_error() !== JSON_ERROR_NONE)
	{
		$map_style = '[]';
		echo '<div class="alert alert-warning"> Bad map styling was set for Address International field #: '. $field->id.'</div>';
	}

	$js = '
	function fc_addressint_initMap_' . $field->id . '(mapBox)
	{
		var mapLatLon  = eval("(" + mapBox.attr("data-maplatlon") + ")");
		var mapZoom    = parseInt(mapBox.attr("data-mapzoom"));
		var mapAddr    = eval("(" + mapBox.attr("data-mapaddr") + ")");
		var mapType    = eval("(" + mapBox.attr("data-maptype") + ")");
		var mapContent = eval("(" + mapBox.attr("data-mapcontent") + ")");
		var mapIcon    = eval("(" + mapBox.attr("data-mapicon") + ")");

		var wS = mapBox.attr("data-mapicon_ws"), hS = mapBox.attr("data-mapicon_hs"),
				wA = mapBox.attr("data-mapicon_wa"), hA = mapBox.attr("data-mapicon_ha");

		var theMap = new google.maps.Map(document.getElementById(mapBox.attr("id")), {
			center: mapLatLon,
			scrollwheel: false,
			zoom: mapZoom,
			mapTypeId: mapType,
			zoomControl: true,
			mapTypeControl: false,
			scaleControl: false,
			streetViewControl: false,
			rotateControl: false,
			styles: ' . $map_style . '
		});

		mapBox.addClass("has_fc_google_maps_map");
		mapBox.data("google_maps_ref", theMap);

		var myInfoWindow = new google.maps.InfoWindow({
			content: mapContent
		});

		var theIcon = "";

		if (mapIcon)
		{
			theIcon = {
				url: mapIcon,
				size: new google.maps.Size(wS, hS),
				origin: new google.maps.Point(0, 0),
				anchor: new google.maps.Point(wA, hA)
			};
		}

		var theMarker = new google.maps.Marker({
			title: mapAddr,
			position: mapLatLon,
      icon: theIcon,
			map: theMap
		});

		theMarker.addListener("click", function() {
			myInfoWindow.open(theMap, theMarker);
		});
	}

	jQuery(document).ready(function(){
		jQuery(".fc_addressint_container_' . $field->id . ' .fc_addressint_map_canvas").each( function() {
			fc_addressint_initMap_' . $field->id . '(jQuery(this));
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
		' . implode("\n", $js_perValue) . '
	});
	';
	JFactory::getDocument()->addScriptDeclaration($js);
}
