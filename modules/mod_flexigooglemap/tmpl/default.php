<?php

/**
 * @package         FLEXIcontent
 * @subpackage      mod_flexigooglemap
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// no direct access
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die('Restricted access');


flexicontent_html::loadFramework('jQuery');
flexicontent_html::loadFramework('flexi-lib');

// Check if having at least 1 location, otherwise skip showing the map
if (empty($renderedMapLocations) && (int)$params->get('hide_map_when_empty', '1')) {
	return;
}

\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.tooltip');
$document = \Joomla\CMS\Factory::getDocument();
$modified = filemtime(__DIR__ . '/../assets/css/style.css');
$document->addStyleSheet(Uri::root(true) . '/modules/mod_flexigooglemap/assets/css/style.css?v=' . $modified);

$itemmodel_name = 'FlexicontentModelItem';
$itemmodel = new $itemmodel_name();

Text::script('MOD_FLEXIGOOGLEMAP_SECHING_TXT');

// Module config
$width  = $params->get('width', '100%');
$height = $params->get('height', '60vh');
$width  = is_numeric($width) ? $width . 'px' : $width;
$height = is_numeric($height) ? $height . 'px' : $height;

$mapapi    = $params->get('mapapi', 'googlemap');
$mapcenter = $params->get('mapcenter', '48.8566667, 2.3509871');
$maptype   = $params->get('maptype', '');
$apikey    = $params->get('apikey', '');

$defaut_icon_url = $mapapi === 'googlemap'
	? 'https://maps.gstatic.com/mapfiles/api-3/images/spotlight-poi2.png'
	: Uri::root(true) . '/components/com_flexicontent/librairies/leaflet/images/marker-icon.png';

$maxzoommarker = (int) $params->get('maxzoommarker', 18);
$mappadding    = (int) $params->get('mappadding', '50');
$mappadding    = $mappadding >= 0 && $mappadding <= 100 ? $mappadding / 100.0 : 0.5;
$info_popup    = (int) $params->get('info_popup', 1);

$geo_locate          = (int) $params->get('geo_locate', 0);
$geo_locate_btn      = (int) $params->get('geo_locate_btn', 0);
$geo_locate_zoom_sel = (int) $params->get('geo_locate_zoom_sel', 0);
$geo_locate_zoom_def = (int) $params->get('geo_locate_zoom_def', 10);

$marker_list_update_to_map_bounds = (int) $params->get('marker_list_update_to_map_bounds', 1);

$mapstyle  = $params->get('mapstyle', '');
$mapstyle  = substr($mapstyle, 1, -1); // Remove[] at end and start
$map_style = trim($params->get('mapstyle', ''));
if ($map_style) {
	json_decode($map_style);
	if (json_last_error() == JSON_ERROR_NONE) {
		// ALL good, user gave good style
	} else {
		$map_style = null;
		echo '<div class="alert alert-warning"> Bad map styling was set for Module with ID #: ' . $module->id . '</div>';
	}
}
//modal size
$windows_width = (int) $params->get('modal_width', 0);
$windows_height = (int) $params->get('modal_height', 0);
$custom_layout = $params->get('ilayout', '');
$marker_clicktarget = $params->get('marker_clicktarget', '_popup');

//add custom layout output
if ($params->get('info_popup', '') >= 2) {

	$layout = $params->get('ilayout', '');
	$custom_layout = '&ilayout=' . $layout;
} else {
	$custom_layout = '';
}

if ($info_popup <= 1) {
	$onclick_js  = '';
} elseif ($info_popup >= 2) {
	$_target_win = $marker_clicktarget === '_modal'
		? 'module_' . $module->id . '_window'   // For "_modal" case we use same (named) window for all links
		: $marker_clicktarget;
	if ($marker_clicktarget === '_popup') {
		$onclick_js  = 'fc_field_dialog_handle_' . $module->id . ' = fc_showDialog(jQuery(this).data(\'content_link\'), \'fc_modal_popup_container\', 0, ' . $windows_width . ', ' . $windows_height . ', 0, {title: \'\'}); return false;';
	} else {
		$onclick_js  = 'window.open(jQuery(this).data(\'content_link\'), \'' . $_target_win . '\', \'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=' . $windows_width . ',height=' . $windows_height . '\'); return false;';
	}

	// For case of link to item views we will add tmpl=component to the URL
	foreach ($mapItemData as $mapItem) {
		$mapItem->content_link = $mapItem->link
			. ($info_popup === 2
				? (strpos($mapItem->link, '?', 0) === false ? '?' : '&') . 'tmpl=component' . $custom_layout
				: '');
	}
}

$gridsize = $params->get('gridsize', '');
$maxzoom  = $params->get('maxzoom', '');
$ratiomap = $params->get('ratiomap', '');
$usescrollmouse = $params->get('usescrollmouse', 'true');

$clustermode = $params->get('clustermode', '');
if ($clustermode) {
	$imgcluster = $params->get('imgcluster', '');
	if ($imgcluster && $img_info = getimagesize(\Joomla\Filesystem\Path::clean(JPATH_ROOT . DS . $imgcluster))) {
		$imgcluster_w = $img_info[0];
		$imgcluster_h = $img_info[1];
		$imgcluster_url  = Uri::root(true) . '/' . $imgcluster;
	} else {
		$imgcluster_w = 53;
		$imgcluster_h = 52;
		$imgcluster_url =  'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m1.png';
	}
}

// Load framework (Map API)
switch ($mapapi) {
	case 'googlemap';
		flexicontent_html::loadFramework('google-maps', '', $params);
		break;
	case 'openstreetmap';
		flexicontent_html::loadFramework('openstreetmap', '', $params);

		// Get OS Map TILE server
		$os_tile_server_url = $params->get('os_tile_server_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'); 

		break;
}

?>

<style>
	#map-bounds-box<?php echo $module->id; ?>.map-bounds-box,
	#fc_module_map_<?php echo $module->id; ?>.fc_module_map {
		width: <?php echo $width; ?>;
		height: <?php echo $height; ?>;
	}
	#fc_module_marker_list_box_<?php echo $module->id; ?>.fc_module_marker_list_box {
		height: calc(<?php echo $height; ?> + var(--fcmap-header-elements-height));
	}
	.leaflet-popup-content-wrapper {
		height: calc(<?php echo $height; ?> / 2) !important;
	}
</style>


<?php
$use_mlist  = (int) $params->get('use_dynamic_marker_list', 0);

$actions_btn_count = 0;

$use_link_to_item     = (int) $params->get('uselink', 0);
$use_map_directions   = (int) $params->get('usedirection', 0);   // 1: Add the button
$use_highlight_marker = $use_mlist;
if ($use_link_to_item) $actions_btn_count++;
if ($use_map_directions) $actions_btn_count++;
if ($use_highlight_marker) $actions_btn_count++;
?>
<style>
	@container (width > <?php echo ($actions_btn_count * 180 - 10) . 'px'; ?>) {
		.fc_module_marker_list .marker_actions * {
			flex-wrap: nowrap;
		}
	}
	@container (width <= <?php echo ($actions_btn_count * 180 - 10) . 'px'; ?>) {
		.fc_module_marker_list .marker_actions {
			justify-content: right;
		}
		.fc_module_marker_list .marker_actions .fc-map-link-icon {
			margin: 0;
		}
		.fc_module_marker_list .marker_actions .fc-map-link-text {
			display: none;
		}
	}
	/*@container (width < 480px) {
		.fc_module_marker_list * {
			white-space: wrap;
		}
		.fc_module_marker_list .marker_actions {
			position: absolute !important;
			justify-content: end !important;
			right: 0;
			top: 0.4rem;
		}
		.marker-info-contents-box {
			padding-left: 0.4rem;
			padding-right: 2.5rem;
		}
	}*/
</style>

<?php

if ($use_mlist)
{
	/**
	 * When using markers list and markers list is wrapped after the map box
	 * because of the outer container width reaching the configured width limit
	 */
	$marker_list_wrap_limit = (int) $params->get('marker_list_wrap_limit', 1200);
	$marker_list_wrap_limit = $marker_list_wrap_limit >= 0 ? $marker_list_wrap_limit : 1200;

	$map_height_wrapped = $params->get('map_height_wrapped', '45vh');
	$map_height_wrapped = is_numeric($map_height_wrapped) ? $map_height_wrapped . 'px' : $map_height_wrapped;

	$marker_list_height_wrapped = $params->get('marker_list_height_wrapped', '45vh');
	$marker_list_height_wrapped = is_numeric($marker_list_height_wrapped) ? $marker_list_height_wrapped . 'px' : $marker_list_height_wrapped;

	$_inlineCSS_WRAP_WIDTH = <<<CSS

		#map-bounds-box{$module->id}.map-bounds-box,
		#fc_module_map_{$module->id}.fc_module_map {
			height: {$map_height_wrapped};
		}
		#fc_module_marker_list_box_{$module->id}.fc_module_marker_list_box {
			height: calc({$marker_list_height_wrapped});
			margin-top: calc(var(--absolute-top-row-elements-counter-font-size) * 1.5); 
		}
	
		#map_contents_box_{$module->id}.map_contents_box {
			flex-wrap: wrap;
			min-height: fit-content;
		}
		#map_contents_box_{$module->id}.map_contents_box .col8 {
			height: unset;
			width: 100%;
			flex-basis: 100%;
		}
		#map_contents_box_{$module->id}.map_contents_box .col4 {
			height: unset;
			width: 100%;
			flex-basis: 100%;
		}

		.leaflet-popup-content-wrapper {
			height: calc({$map_height_wrapped} / 2) !important;
		}

	CSS;
	?>

<style>
@media screen and (max-width: <?php echo $marker_list_wrap_limit - 1;?>px) {  <?php /* LEGACY browsers, assume no side column ? */ ?>
	<?php echo $_inlineCSS_WRAP_WIDTH; ?>
}

@container (width < <?php echo $marker_list_wrap_limit;?>px) {  <?php /* MODERN browsers, regardless of side column */ ?>
	<?php echo $_inlineCSS_WRAP_WIDTH; ?>
}


</style>

<?php
}
?>

<?php /* Module container*/ ?>

<div id="mod_fleximap_default<?php echo $module->id; ?>" class="mod_fleximap map<?php echo $moduleclass_sfx ?>">

	<div id="map_actions_box_<?php echo $module->id; ?>" class="map_actions_box">
		<div id="map_mssg_box_<?php echo $module->id; ?>" class="map_mssg_box"></div>
	</div>

	<?php /*
    metersPerPx = 156543.03392 * Math.cos(latLng.lat() * Math.PI / 180) / Math.pow(2, zoom);
    'latLng.lat()' = map.getCenter().lat()
    'zoom' = map.getZoom()
    */ ?>

	<div id="map_contents_box_<?php echo $module->id; ?>" class="map_contents_box">

		<div class="<?php echo $use_mlist ? 'col8' : ''; ?>">

			<?php if ($geo_locate_zoom_sel) : ?>
			<div class="geo-locate-box">
				<?php echo $geo_locate_btn ? '<span class="btn btn-primary geo-locate-me-btn">
		      ' . Text::_('MOD_FLEXIGOOGLEMAP_NEARBY_LOCATIONS') . '
		      </span>
		      ' : ''; ?>
				<label class="label geo-locate-zoom-level-lbl">
					<?php echo Text::_('MOD_FLEXIGOOGLEMAP_ZOOM'); ?>
				</label>
				<select class="form-select geo-locate-zoom-level geo-locate-zoom-level-<?php echo $module->id; ?>" style="display: inline-block; width: auto;">
					<?php
					$distance_lbls = array(
						15 => Text::_('MOD_FLEXIGOOGLEMAP_DIST_CLOSED'),
						10 => Text::_('MOD_FLEXIGOOGLEMAP_DIST_DISTANT'),
						7 => Text::_('MOD_FLEXIGOOGLEMAP_DIST_VERYDISTANT'),
						2 => Text::_('MOD_FLEXIGOOGLEMAP_DIST_MOSTDIST'),
					);

					for ($i = 15; $i >= 2; $i--) {
						$km_distance = ((40000 / pow(2, $i)) * 2) ;
						$km_distance = round($km_distance, 1);
						$km_distance = $km_distance == 2.4 ? 2.5 : $km_distance;
						$km_distance = $km_distance == 4.9 ? 5 : $km_distance;
						$km_distance = $km_distance > 5 ? ceil($km_distance) : $km_distance;

						$distance_lbls[$i] = $km_distance . ' ' . Text::_('MOD_FLEXIGOOGLEMAP_KILOMETERS');
						echo '
            <option value="' . $i . '"' . ($i == $geo_locate_zoom_def ? ' selected="selected"' : '')
							. '> ' . (isset($distance_lbls[$i]) ? $distance_lbls[$i] : 'Zoom: ' . $i)
							. '</option>';
					}
					?>
				</select>
			</div>
			<?php endif; ?>

			<?php /* Map bounding box */ ?>
			<div id="map-bounds-box<?php echo $module->id; ?>" class="map-bounds-box" style="max-width:100%;">
				<div id="fc_module_map_<?php echo $module->id; ?>" class="fc_module_map" style="max-width:100%; z-index:1;"></div>
			</div>

		</div>

		<?php if ($use_mlist) :
			Text::script("MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_0_ENTRIES", true);
			Text::script("MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_1_ENTRY", true);
			Text::script("MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_N_ENTRIES", true);
		?>
			<div class="col4">
				<div id="fc_module_marker_list_box_<?php echo $module->id; ?>" class="fc_module_marker_list_box">
					<div id="fc_module_marker_list_header_<?php echo $module->id; ?>" class="fc_module_marker_list_header"></div>
					<ol id="fc_module_marker_list_<?php echo $module->id; ?>" class="fc_module_marker_list"></ol>
				</div>
			</div>
		<?php endif; ?>
	</div>

</div>


<?php if ($mapapi === 'googlemap') : ?>


	<script src="modules/mod_flexigooglemap/assets/js/markerclusterer.js"></script>
	<script>
		// Global reference to map
		var module_map_<?php echo $module->id; ?>;

		function fc_MapMad_geolocateMe_<?php echo $module->id; ?>(map) {
			let output = document.getElementById('map_mssg_box_<?php echo $module->id; ?>');
			let zoom_level = jQuery('select.geo-locate-zoom-level-<?php echo $module->id; ?>').val();
			zoom_level = parseInt(zoom_level) ? parseInt(zoom_level) : <?php echo $geo_locate_zoom_def; ?>;

			if (!navigator.geolocation) {
				output.innerHTML = '<span class="geo-location-no-support" style="color: darkred;">Geolocation is not supported by your browser</span>';
				return;
			}

			function success(position) {
				var latitude = position.coords.latitude;
				var longitude = position.coords.longitude;
				output.innerHTML = '<span class="geo-location-results" style="display:none;">Lat: ' + latitude + '° <br>Lng: ' + longitude + '°</span>';
				map.panTo(new google.maps.LatLng(latitude, longitude));
				map.setZoom(zoom_level);
			}

			function error() {
				output.innerHTML = '<span class="geo-location-blocked">Unable to retrieve your location</span>';
			}

			output.innerHTML = '<span class="geo-location-trying" style="align-self: center;">'+ Joomla.JText._('MOD_FLEXIGOOGLEMAP_SECHING_TXT') +
			'</span>';

			navigator.geolocation.getCurrentPosition(success, error);
		}

		function fc_MapMod_addToVisibleList_<?php echo $module->id; ?>(map, marker)
		{
			let ol = document.getElementById("fc_module_marker_list_<?php echo $module->id; ?>");
			if (!!!ol) return;

			let li = document.createElement("li");
			li.innerHTML = '<img class="fc_module_marker_list_icon" src="' + marker._icon_url + '" /> ' + marker._location_info;

			/*var btn = document.createElement("button");
			btn.innerHTML = 'Center';
			btn._marker_ref = marker;
			btn.setAttribute('onclick', "this._marker_ref._map_ref.setCenter(this._marker_ref.getPosition());");
			li.appendChild(btn);*/

			let btn_div = document.createElement("div");
			let btn_link     = document.createElement("a");
			btn_div.className = 'marker_highlight';
			btn_link.innerHTML = '<span class="fc-map-link-icon"></span> <span class="fc-map-link-text"><?php echo Text::_("MOD_FLEXIGOOGLEMAP_MARKER_HIGHLIGHT_ENTRY", true) ?></span>';
			btn_link.className = 'fc-map-link btn btn btn-secondary';
			btn_link._marker_ref = marker;
			btn_link.setAttribute('onclick', "new google.maps.event.trigger(this._marker_ref, 'click');");

			//window.console.log(marker);
			let marker_actions_box  = li.querySelector('.marker_actions');
			btn_div.appendChild(btn_link);
			marker_actions_box.appendChild(btn_div);
			ol.appendChild(li);
		}

		function fc_MapMod_updateVisibleMarkerList_<?php echo $module->id; ?>(map, markers) {
			//window.console.log('bounds_changed');

			// Get our current map view bounds.
			// Create a new bounds object so we don't affect the map.
			var ol = document.getElementById("fc_module_marker_list_<?php echo $module->id; ?>");
			var ol_header = document.getElementById("fc_module_marker_list_header_<?php echo $module->id; ?>");
			if (!!!ol) return;

			ol.innerHTML = "";

			var mapBounds = new google.maps.LatLngBounds(map.getBounds().getSouthWest(), map.getBounds().getNorthEast());
			var bounds = mapBounds;
			var added = 0;

			for (var i = 0, marker; marker = markers[i]; i++) {
				if (bounds.contains(marker.getPosition())) {
					added++;
					fc_MapMod_addToVisibleList_<?php echo $module->id; ?>(map, marker);
				}
			}

			var header_html = added == 1 ?
				Joomla.JText._('MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_1_ENTRY') :
				(added == 0 ?
					Joomla.JText._('MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_0_ENTRIES') :
					Joomla.JText._('MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_N_ENTRIES'));
			ol_header.innerHTML = header_html.replace(/%s/, added);
		};

		function fc_MapMod_autoCenter_<?php echo $module->id; ?>(map, markers) {
			//  Create a new viewpoint bound
			var bounds = new google.maps.LatLngBounds();
			//  Go through each...
			for (var i = 0; i < markers.length; i++) {
				bounds.extend(markers[i].position);
			}
			//  Fit these bounds to the map
			map.fitBounds(bounds);
		}


		function fc_MapMod_resizeMap_<?php echo $module->id; ?>(map) {
			//window.console.log('resizing');
			var center = map.getCenter();
			google.maps.event.trigger(map, "resize");
			map.setCenter(center);
		}


		function fc_MapMod_on_marker_click_<?php echo $module->id; ?>(e) {
			<?php echo $onclick_js; ?>
			fc_MapMod_on_marker_add_styles<?php echo $module->id; ?>();
		}

		function fc_MapMod_on_marker_add_styles<?php echo $module->id; ?>(e) {
			setTimeout(function() {
				document.querySelectorAll('.gm-style-iw').forEach(element => element.classList.add('fc-mod-map'));
			}, 1);
		}

		window.clusterManager_<?php echo $module->id; ?> = false;

		function fc_MapMod_initialize_<?php echo $module->id; ?>() {
			// Define your locations: HTML content for the info window, latitude, longitude
			var locations = [<?php echo implode(",",  $renderedMapLocations); ?>];
			var mapItems = <?php echo json_encode($mapItemData); ?>;

			var map = new google.maps.Map(document.getElementById('fc_module_map_<?php echo $module->id; ?>'), {
				maxZoom: [<?php echo $maxzoommarker; ?>],
				center: new google.maps.LatLng(-37.92, 151.25),
				mapTypeId: google.maps.MapTypeId.<?php echo $maptype; ?>,
				mapTypeControl: true,
				streetViewControl: true,
				scrollwheel: <?php echo $usescrollmouse; ?>,
				panControl: true,
				styles: [<?php echo $mapstyle; ?>],
				zoomControlOptions: {
					position: google.maps.ControlPosition.LEFT_BOTTOM
				}
			});

			// Initialize Spiderify for overlapping markers
			var oms = new OverlappingMarkerSpiderfier(map);

			var infowindow = new google.maps.InfoWindow({
				maxWidth: 160
			});

			var markers = new Array();
			var defaultMarkerIcon = <?php echo !$defaultMarkerURL ? 'null;' : '{
				url: \'' . $defaultMarkerURL . '\'' .
										// Unlike Openstreet Map (which position top-left) Google maps will position bottom-middle, so we may have skipped calculating these ...)
										($wS_dMU && $hS_dMU ? ',
					size: new google.maps.Size(' . $wS_dMU . ', ' . $hS_dMU . '),
					origin: new google.maps.Point(0, 0),
					anchor:  new google.maps.Point(' . $wA_dMU . ', ' . $hA_dMU . ')'
											: '') . '
			}
			'; ?>

			// Add the markers and infowindows to the map
			for (var i = 0; i < locations.length; i++) {
				let customMarkerIcon = locations[i][3] == '__default__' ?
					defaultMarkerIcon :
					{
						url: locations[i][3],
						size: new google.maps.Size(locations[i][4], locations[i][5]),
						origin: new google.maps.Point(0, 0),
						anchor: new google.maps.Point(locations[i][6], locations[i][7])
					};

				var marker = new google.maps.Marker({
					position: new google.maps.LatLng(locations[i][1], locations[i][2]),
					map: map,
					icon: customMarkerIcon
					<?php echo ($params->get('animationmarker', 1)
						? ',animation: google.maps.Animation.DROP'
						: '');
					?>
				});

				// Add marker to spiderify
				oms.addMarker(marker);

				marker._location_info = locations[i][0];
				marker._icon_url = locations[i][3] == '__default__' ? '<?php echo $defaultMarkerURL ?: $defaut_icon_url; ?>' : locations[i][3];
				marker._map_ref = map;

				jQuery(marker).data('content_link', mapItems[i].content_link);
				if (<?php echo strlen($onclick_js) ? 1 : 0 ?>)
					google.maps.event.addListener(marker, 'click', fc_MapMod_on_marker_click_<?php echo $module->id; ?>);
				else {
					google.maps.event.addListener(marker, 'click', fc_MapMod_on_marker_add_styles<?php echo $module->id; ?>);
				}

				markers[i] = marker;

				<?php if ($info_popup) {
					echo "
					google.maps.event.addListener(marker, 'click', (function(marker, i)
					{
						return function() {
							let found_inside_cluster = false;

						  if (window.clusterManager_" . $module->id .")
							{
								let clusters = window.clusterManager_" . $module->id .".clusters_; // use the get clusters method which returns an array of objects
		
								if (!!clusters) for( var ii=0, ll=clusters.length; ii<ll; ii++ )
								{
									if (clusters[ii].markers_.length <= 1) continue;
									found_inside_cluster = clusters[ii].isMarkerInClusterBounds(marker);
							    if (found_inside_cluster) break;
								}
						  }
						  
						  //console.log('found_inside_cluster'); console.log(found_inside_cluster);
							map.setCenter(marker.getPosition());
							if (found_inside_cluster)
							{
								map.setZoom(" . $maxzoom . " + 1);
							}
							infowindow.setContent(locations[i][0]);
							infowindow.open(map, marker);
						}
					})(marker, i));
					";
				}
				?>
			}

			var fc_MapMod_resizeMap_debounced_<?php echo $module->id; ?> = fc_debounce_exec(fc_MapMod_resizeMap_<?php echo $module->id; ?>, 200, false, null);
			var fc_MapMod_updateVisibleMarkerList_debounced_<?php echo $module->id; ?> = fc_debounce_exec(fc_MapMod_updateVisibleMarkerList_<?php echo $module->id; ?>, 200, false, null);

			google.maps.event.addDomListener(window, "resize", function() {
				fc_MapMod_resizeMap_debounced_<?php echo $module->id; ?>(map);
			});

			<?php if ($clustermode) {
				echo "
				var mcOptions = {
					zoomOnClick: true,
					gridSize:$gridsize,
					maxZoom:$maxzoom,
					styles: [{
						url: '$imgcluster_url',
						width: $imgcluster_w,
						height: $imgcluster_h
					}]
				};
				window.clusterManager_" . $module->id ." = new MarkerClusterer(map, markers, mcOptions);
				";
			}
			?>

			// Center to markers
			fc_MapMod_autoCenter_<?php echo $module->id; ?>(map, markers);

			// Try to geo-locate visitor
			if (<?php echo $geo_locate ? 1 : 0; ?>) setTimeout(function() {
				fc_MapMad_geolocateMe_<?php echo $module->id; ?>(map);
			}, 50);

			// Assign global map reference
			module_map_<?php echo $module->id; ?> = map;

			<?php /* Either update visible markers only once on initialization (on first triggering of bounds_changed event) or whenever the map bounds change */ ?>
			var marker_list_update_to_map_bounds = <?php echo $marker_list_update_to_map_bounds ? 'true' : 'false'; ?>;
			var marker_list_updates = 0;
			google.maps.event.addDomListener(map, "bounds_changed", function() {
				if (marker_list_update_to_map_bounds || marker_list_updates === 0) {
					marker_list_updates++;
					fc_MapMod_updateVisibleMarkerList_debounced_<?php echo $module->id; ?>(map, markers);
				}
			});
		}

		// Initialize the Map
		fc_MapMod_initialize_<?php echo $module->id; ?>();

		// Geo locate visitor on-demand via button click
		document.addEventListener("click", function(e) {
			if (e.target && e.target.classList.contains("geo-locate-me-btn")) {
				fc_MapMad_geolocateMe_<?php echo $module->id; ?>(module_map_<?php echo $module->id; ?>);
			}
		});
	</script>


<?php elseif ($mapapi === 'openstreetmap') : ?>


	<script type="text/javascript">
		// Global reference to map
		var module_map_<?php echo $module->id; ?>;

		function fc_MapMad_geolocateMe_<?php echo $module->id; ?>(map) {
			let output = document.getElementById('map_mssg_box_<?php echo $module->id; ?>');
			let zoom_level = jQuery('select.geo-locate-zoom-level-<?php echo $module->id; ?>').val();
			zoom_level = parseInt(zoom_level) ? parseInt(zoom_level) : <?php echo $geo_locate_zoom_def; ?>;

			if (!navigator.geolocation) {
				output.innerHTML = '<span class="geo-location-no-support" style="color: darkred;">Geolocation is not supported by your browser</span>';
				return;
			}

			function success(position) {
				var latitude = position.coords.latitude;
				var longitude = position.coords.longitude;
				output.innerHTML = '<span class="geo-location-results" style="display:none;">Lat: ' + latitude + '° <br>Lng: ' + longitude + '°</span>';
				map.flyTo([latitude, longitude], zoom_level);
			}

			function error() {
				output.innerHTML = '<span class="geo-location-blocked">Unable to retrieve your location</span>';
			}

			output.innerHTML = '<span class="geo-location-trying" style="align-self: center;">Searching ...</span>';

			navigator.geolocation.getCurrentPosition(success, error);
		}


		function fc_MapMod_addToVisibleList_<?php echo $module->id; ?>(map, marker)
		{
			let ol = document.getElementById("fc_module_marker_list_<?php echo $module->id; ?>");
			if (!!!ol) return;

			let li = document.createElement("li");
			li.innerHTML = '<img class="fc_module_marker_list_icon" src="' + marker._icon_url + '" /> ' + marker._location_info;

			let btn_div = document.createElement("div");
			let btn_link     = document.createElement("a");
			btn_div.className = 'marker_highlight';
			btn_link.innerHTML = '<span class="fc-map-link-icon"></span> <span class="fc-map-link-text"><?php echo Text::_("MOD_FLEXIGOOGLEMAP_MARKER_HIGHLIGHT_ENTRY", true) ?></span>';
			btn_link.className = 'fc-map-link btn btn btn-secondary';
			btn_link._marker_ref = marker;

			<?php /* Add single location marker */
			echo $clustermode ? '
				btn_link.onclick = function () {
					var marker = this._marker_ref;
					var map    = marker._map_ref;
					var mLayer = marker.theMarkerClusters_.getLayer(marker._leaflet_id);

					if (!map.hasLayer(marker))
					{
						//window.console.log(marker); window.console.log(marker.__parent._leaflet_id);
						marker.theMarkerClusters_.zoomToShowLayer(mLayer, function()
						{
							/*if (map.getZoom() > 16) { map.setView([marker._latlng.lat, marker._latlng.lng], map.getZoom() + 1); } */
							mLayer.openPopup();
							//mLayer.__parent.spiderfy();
						});
					}
					else
					{
						mLayer.openPopup(); //marker.fire(\'click\');
					}
				};
				' : '
				btn_link.onclick = function () {
					var marker = this._marker_ref;
					var map    = marker._map_ref;
					var mLayer = map._topLayerGroup_ref.getLayer(marker._leaflet_id);
					//map.setView([marker._latlng.lat, marker._latlng.lng], 9);
					mLayer.openPopup(); //marker.fire(\'click\');
				};
				'; ?>

			//window.console.log(marker);
			let marker_actions_box  = li.querySelector('.marker_actions');
			btn_div.appendChild(btn_link);
			marker_actions_box.appendChild(btn_div);
			ol.appendChild(li);
		}


		function fc_MapMod_updateVisibleMarkerList_<?php echo $module->id; ?>(map, markers) {
			//window.console.log('bounds_changed');

			// Get our current map view bounds.
			// Create a new bounds object so we don't affect the map.
			var ol = document.getElementById("fc_module_marker_list_<?php echo $module->id; ?>");
			var ol_header = document.getElementById("fc_module_marker_list_header_<?php echo $module->id; ?>");
			if (!!!ol) return;

			ol.innerHTML = "";

			var mapBounds = map.getBounds();
			var bounds = mapBounds;
			var added = 0;

			for (var i = 0, marker; marker = markers[i]; i++) {
				if (marker instanceof L.Marker && bounds.contains(marker.getLatLng())) {
					added++;
					fc_MapMod_addToVisibleList_<?php echo $module->id; ?>(map, marker);
				}
			}

			var header_html = added == 1 ?
				Joomla.JText._('MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_1_ENTRY') :
				(added == 0 ?
					Joomla.JText._('MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_0_ENTRIES') :
					Joomla.JText._('MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_N_ENTRIES'));
			ol_header.innerHTML = header_html.replace(/%s/, added);
		};


		function fc_MapMod_on_marker_click_<?php echo $module->id; ?>(e) {
			<?php echo $onclick_js; ?>
		}


		function fc_MapMod_initialize_<?php echo $module->id; ?>() {
			var locations = [<?php echo implode(",",  $renderedMapLocations); ?>];
			var mapItems = <?php echo json_encode($mapItemData); ?>;

			var markerClusters;
			var markers = [];

			var lat = 48.852969;
			var lon = 2.349903;

			var map = L.map('fc_module_map_<?php echo $module->id; ?>', {
				scrollWheelZoom: <?php echo $usescrollmouse; ?>
			}).setView([lat, lon], 11);
			<?php if ($clustermode) {
				echo "markerClusters = L.markerClusterGroup({ disableClusteringAtZoom: " . $maxzoommarker . ",removeOutsideVisibleBounds:true,animate:true, maxClusterRadius :" . $gridsize, " }); "; // create cluster and add zoom limitation
			}
			?>
			// Title display
			L.tileLayer('<?php echo $os_tile_server_url; ?>', {
					// Datas sources
					attribution: '<?php echo Text::_('OPENSTREETMAP_ATTRIBUTION_TXT'); ?>',
					minZoom: 1,
					maxZoom: <?php echo $maxzoom; ?>
				})
				.addTo(map);

			var defaultMarkerIcon = <?php echo !$defaultMarkerURL ? 'null' : 'L.icon({
				iconUrl: \'' . $defaultMarkerURL . '\',
				iconSize: [' . $wS_dMU . ', ' . $hS_dMU . '],
				iconAnchor: [' . $wA_dMU . ', ' . $hA_dMU . ']
			});
			'; ?>

			for (var i = 0; i < locations.length; i++) {
				<?php /* TODO Add Mapbox key and title loading for custom display */ ?>
				let customMarkerIcon = locations[i][3] == '__default__' ?
					defaultMarkerIcon :
					L.icon({
						iconUrl: locations[i][3],
						iconSize: [locations[i][4], locations[i][5]],
						iconAnchor: [locations[i][6], locations[i][7]]
					});

				if (customMarkerIcon) {
					marker = new L.marker([locations[i][1], locations[i][2]], {
						icon: customMarkerIcon
					});
				} else {
					marker = new L.marker([locations[i][1], locations[i][2]]);
				}

				marker._location_info = locations[i][0];
				marker._icon_url = locations[i][3] == '__default__' ? '<?php echo $defaultMarkerURL ?: $defaut_icon_url; ?>' : locations[i][3];
				marker._map_ref = map;

				jQuery(marker).data('content_link', mapItems[i].content_link);
				if (<?php echo strlen($onclick_js) ? 1 : 0 ?>)
					marker.on('click', fc_MapMod_on_marker_click_<?php echo $module->id; ?>);

				marker
				<?php /* Display information window on marker click */
				echo $info_popup ? '
					.bindPopup(locations[i][0])
					' : ''; ?>

				<?php /* Add single location marker */
				echo !$clustermode ? '
					.addTo(map);
					' : ''; ?>
				;

				<?php /* Add a cluster of markers */
				echo $clustermode ?
					'markerClusters.addLayer(marker); marker.theMarkerClusters_ = markerClusters;' : ''; ?>
				markers.push(marker);
			}
			var group = new L.featureGroup(markers);

			<?php echo $clustermode ?
				'map.addLayer(markerClusters);' : ''; ?>

			map._topLayerGroup_ref = group;

			// Center to markers
			markers.length ?
				map.fitBounds(group.getBounds().pad(<?php echo $mappadding; ?>)) :
				map.setZoom(1);

			// Try to geo-locate visitor
			if (<?php echo $geo_locate ? 1 : 0; ?>) setTimeout(function() {
				fc_MapMad_geolocateMe_<?php echo $module->id; ?>(map);
			}, 50);

			// Make an initial update of visible markers
			fc_MapMod_updateVisibleMarkerList_<?php echo $module->id; ?>(map, markers);

			var fc_MapMod_updateVisibleMarkerList_debounced_<?php echo $module->id; ?> = fc_debounce_exec(fc_MapMod_updateVisibleMarkerList_<?php echo $module->id; ?>, 200, false, null);

			<?php /* Either update visible markers only on initialization or when the map bounds change */ ?>
			<?php if ($marker_list_update_to_map_bounds) : ?>
			map.on('moveend', function(e) {
				//window.console.log('moveend');
				fc_MapMod_updateVisibleMarkerList_debounced_<?php echo $module->id; ?>(map, markers);
				//fc_MapMod_updateVisibleMarkerList_<?php echo $module->id; ?>(map, markers);
			});
			<?php endif; ?>

			// Assign global map reference
			module_map_<?php echo $module->id; ?> = map;
		}

		// Initialize the Map
		fc_MapMod_initialize_<?php echo $module->id; ?>();

		// Geo locate visitor on-demand via button click
		document.addEventListener("click", function(e) {
			if (e.target && e.target.classList.contains("geo-locate-me-btn")) {
				fc_MapMad_geolocateMe_<?php echo $module->id; ?>(module_map_<?php echo $module->id; ?>);
			}
		});

		// Geo locate visitor on-demand via button click
		document.addEventListener("change", function(e) {
			if (e.target && e.target.classList.contains("geo-locate-zoom-level")) {
				fc_MapMad_geolocateMe_<?php echo $module->id; ?>(module_map_<?php echo $module->id; ?>);
			}
		});
	</script>


<?php endif; ?>