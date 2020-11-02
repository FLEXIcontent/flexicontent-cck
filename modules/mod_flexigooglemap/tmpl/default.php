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
defined('_JEXEC') or die('Restricted access');

// Check if having at least 1 location, otherwise skip showing the map
if (empty($renderedMapLocations))
{
	return;
}

JHtml::_('bootstrap.tooltip');
JHtml::_('behavior.modal');
$document = JFactory::getDocument();
$document->addStyleSheet("./modules/mod_flexigooglemap/assets/css/style.css",'text/css',"screen");

$itemmodel_name = 'FlexicontentModelItem';
$itemmodel = new $itemmodel_name();

// Module config
$height = $params->get('height', '300px');
$width  = $params->get('width', '200px');
$height = is_numeric($height) ? $height . 'px' : $height;
$width  = is_numeric($width) ? $width . 'px' : $width;

$mapapi    = $params->get('mapapi', 'googlemap');
$mapcenter = $params->get('mapcenter', '48.8566667, 2.3509871');
$maptype   = $params->get('maptype', '');
$apikey    = $params->get('apikey', '');

$maxzoommarker = $params->get('maxzoommarker', '');
$info_popup    = (int)$params->get('info_popup', 1);

$mapstyle  = $params->get('mapstyle', '');
$mapstyle  = substr($mapstyle,1,-1); // Remove[] at end and start
$map_style = trim($params->get('mapstyle', ''));
if ($map_style)
{
	json_decode($map_style);
	if (json_last_error() == JSON_ERROR_NONE)
	{
		// ALL good, user gave good style
	}
	else
	{
		$map_style = null;
		echo '<div class="alert alert-warning"> Bad map styling was set for Module with ID #: '. $module->id.'</div>';
	}
}

$gridsize = $params->get('gridsize', '');
$maxzoom  = $params->get('maxzoom', '');
$ratiomap = $params->get('ratiomap','');
$usescrollmouse = $params->get('usescrollmouse','true');

$clustermode = $params->get('clustermode', '' );
if ($clustermode)
{
	$imgcluster = $params->get('imgcluster', '');
	if ($imgcluster && $img_info = getimagesize(JPATH::clean(JPATH_ROOT.DS.$imgcluster)))
	{
		$imgcluster_w = $img_info[0];
		$imgcluster_h = $img_info[1];
		$imgcluster_url  = JUri::root(true) . '/' . $imgcluster;
	}
	else
	{
		$imgcluster_w = 53;
		$imgcluster_h = 52;
		$imgcluster_url =  'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m1.png';
	}
}

// Load framework (Map API)
switch ($mapapi)
{
	case 'googlemap';
		flexicontent_html::loadFramework('google-maps', '', $params);
		break;
	case 'openstreetmap';
		flexicontent_html::loadFramework('openstreetmap', '', $params);

		// Get OS Map TILE server
		$os_tile_server_url = $params->get('os_tile_server_url', 'https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png');
		break;
}

$use_mlist = (int) $params->get('use_dynamic_marker_list', 0);
?>


<?php /* Module container*/ ?>

<div id="mod_fleximap_default<?php echo $module->id;?>" class="mod_fleximap map<?php echo $moduleclass_sfx ?>" style="display: contents;">

	<div class="row">

    <div class="<?php echo $use_mlist ? 'span8' : 'span12'; ?>">
			<?php /* Map container*/ ?>
			<div style="width:<?php echo $width; ?>; height:<?php echo $height; ?>; max-width: 100%;">
				<div id="fc_module_map_<?php echo $module->id;?>" style="width:<?php echo $width; ?>; height:<?php echo $height; ?>; max-width: 100%;"></div>
			</div>

    </div>

    <?php if ($use_mlist) :
		JText::script("MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_0_ENTRIES", true);
		JText::script("MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_1_ENTRY", true);
		JText::script("MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_N_ENTRIES", true);
		?>
    <div class="span4">
			<div id="fc_module_marker_list_box_<?php echo $module->id;?>" class="fc_module_marker_list_box" style="height:<?php echo $height; ?>;" >
				<div id="fc_module_marker_list_header_<?php echo $module->id;?>" class="fc_module_marker_list_header"></div>
				<ol id="fc_module_marker_list_<?php echo $module->id;?>" class="fc_module_marker_list"></ol>
			</div>
    </div>
    <?php endif; ?>
	</div>

</div>


<?php if ($mapapi === 'googlemap') : ?>


	<script type="text/javascript" src="modules/mod_flexigooglemap/assets/js/markerclusterer.js"></script>
	<script>

		function fc_MapMod_addToVisibleList_<?php echo $module->id;?>(map, marker)
		{
			var ol = document.getElementById("fc_module_marker_list_<?php echo $module->id;?>");
			if (!!!ol) return;

			var li = document.createElement("li");
			li.innerHTML = marker._location_info;

			/*var btn = document.createElement("button");
			btn.innerHTML = 'Center';
			btn._map_ref = map;
			btn._marker_ref = marker;
			btn.setAttribute('onclick', "this._map_ref.setCenter(this._marker_ref.getPosition());");
			li.appendChild(btn);*/

			var btn2 = document.createElement("button");
			btn2.innerHTML = 'Highlight';
			btn2._map_ref = map;
			btn2._marker_ref = marker;
			btn2.setAttribute('onclick', "new google.maps.event.trigger(this._marker_ref, 'click');");
			li.appendChild(btn2);

			//window.console.log(marker);
			ol.appendChild(li);
		};

		function fc_MapMod_updateVisibleMarkerList_<?php echo $module->id;?>(map, markers)
		{
			//window.console.log('bounds_changed');

			// Get our current map view bounds.
			// Create a new bounds object so we don't affect the map.
			var ol = document.getElementById("fc_module_marker_list_<?php echo $module->id;?>");
			var ol_header = document.getElementById("fc_module_marker_list_header_<?php echo $module->id;?>");
			if (!!!ol) return;

			ol.innerHTML = "";

			var mapBounds = new google.maps.LatLngBounds(map.getBounds().getSouthWest(), map.getBounds().getNorthEast());
			var bounds = mapBounds;
			var added = 0;

			for (var i = 0, marker; marker = markers[i]; i++)
			{
				if (bounds.contains(marker.getPosition()))
				{
					added++;
					fc_MapMod_addToVisibleList_<?php echo $module->id;?>(map, marker);
				}
			}

			var header_html = added == 1
				? Joomla.JText._('MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_1_ENTRY')
				: (added == 0
					? Joomla.JText._('MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_0_ENTRIES')
					: Joomla.JText._('MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_N_ENTRIES'));
			ol_header.innerHTML = header_html.replace(/%s/, added);
		};

		function fc_MapMod_autoCenter_<?php echo $module->id;?>(map, markers)
		{
			//  Create a new viewpoint bound
			var bounds = new google.maps.LatLngBounds();
			//  Go through each...
			for (var i = 0; i < markers.length; i++) {
				bounds.extend(markers[i].position);
			}
			//  Fit these bounds to the map
			map.fitBounds(bounds);
		}


		function fc_MapMod_resizeMap_<?php echo $module->id;?>(map)
		{
			//window.console.log('resizing');
			var center = map.getCenter();
			google.maps.event.trigger(map, "resize");
			map.setCenter(center);
		}


		function fc_MapMod_initialize_<?php echo $module->id;?>()
		{
			// Define your locations: HTML content for the info window, latitude, longitude
			var locations = [ <?php echo implode(",",  $renderedMapLocations); ?>  ];

			var map = new google.maps.Map(document.getElementById('fc_module_map_<?php echo $module->id;?>'), {
				maxZoom: [<?php echo $maxzoommarker; ?>],
				center: new google.maps.LatLng(-37.92, 151.25),
				mapTypeId: google.maps.MapTypeId.<?php echo $maptype;?>,
				mapTypeControl: false,
				streetViewControl: false,
				scrollwheel: <?php echo $usescrollmouse;?>,
				panControl: false,
				styles:[<?php echo $mapstyle; ?>],
				zoomControlOptions: {
					position: google.maps.ControlPosition.LEFT_BOTTOM
				}
			});

			var infowindow = new google.maps.InfoWindow({
				maxWidth: 160
			});

			var markers = new Array();
			var defaultMarkerIcon =  <?php echo !$defaultMarkerURL ? 'null;' : '{
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
			for (var i = 0; i < locations.length; i++)
			{
				let customMarkerIcon = locations[i][3] == '__default__'
					? defaultMarkerIcon
					: {
						url: locations[i][3],
						size: new google.maps.Size(locations[i][4], locations[i][5]),
						origin: new google.maps.Point(0, 0),
						anchor: new google.maps.Point(locations[i][6], locations[i][7])
					};

				var marker = new google.maps.Marker({
					position: new google.maps.LatLng(locations[i][1], locations[i][2]),
					map: map,
					icon : customMarkerIcon
					<?php	echo ($params->get('animationmarker', 1)
						? ',animation: google.maps.Animation.DROP'
						: '');
					?>
				});
				marker._location_info = locations[i][0];

				markers.push(marker);

				<?php if ($info_popup)
				{
					echo "
					google.maps.event.addListener(marker, 'click', (function(marker, i)
					{
						return function() {
							infowindow.setContent(locations[i][0]);
							infowindow.open(map, marker);
						}
					})(marker, i));
					";
				}
				?>

				var fc_MapMod_resizeMap_debounced_<?php echo $module->id;?> = fc_debounce_exec(fc_MapMod_resizeMap_<?php echo $module->id;?>, 200, false, null);
				var fc_MapMod_updateVisibleMarkerList_debounced_<?php echo $module->id;?> = fc_debounce_exec(fc_MapMod_updateVisibleMarkerList_<?php echo $module->id;?>, 200, false, null);

				google.maps.event.addDomListener(window, "resize", function()
				{
					fc_MapMod_resizeMap_debounced_<?php echo $module->id;?>(map);
				});

				google.maps.event.addDomListener(map, "bounds_changed", function()
				{
					fc_MapMod_updateVisibleMarkerList_debounced_<?php echo $module->id;?>(map, markers);
				});
			}

			<?php if ($clustermode)
			{
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
				var marker = new MarkerClusterer(map, markers, mcOptions);
				";
			}
			?>
			fc_MapMod_autoCenter_<?php echo $module->id;?>(map, markers);
		}

		// Initialize the Map
		fc_MapMod_initialize_<?php echo $module->id;?>();

	</script>


<?php elseif ($mapapi === 'openstreetmap') : ?>


	<script type="text/javascript">


		function fc_MapMod_addToVisibleList_<?php echo $module->id;?>(map, marker)
		{
			var ol = document.getElementById("fc_module_marker_list_<?php echo $module->id;?>");
			if (!!!ol) return;

			var li = document.createElement("li");
			li.innerHTML = marker._location_info;

			/*var btn = document.createElement("button");
			btn.innerHTML = 'Center';
			btn._map_ref = map;
			btn._marker_ref = marker;
			btn.setAttribute('onclick', "this._map_ref.setView(this._marker_ref.getLatLng(),5);");
			li.appendChild(btn);*/
			
			var btn2 = document.createElement("button");
			btn2.innerHTML = 'Highlight';
			btn2._map_ref = map;
			btn2._marker_ref = marker;
			btn2.setAttribute('onclick', "this._marker_ref.fire('click');");
			li.appendChild(btn2);

			//window.console.log(marker);
			ol.appendChild(li);
		};

		function fc_MapMod_updateVisibleMarkerList_<?php echo $module->id;?>(map, markers)
		{
			//window.console.log('bounds_changed');

			// Get our current map view bounds.
			// Create a new bounds object so we don't affect the map.
			var ol = document.getElementById("fc_module_marker_list_<?php echo $module->id;?>");
			var ol_header = document.getElementById("fc_module_marker_list_header_<?php echo $module->id;?>");
			if (!!!ol) return;

			ol.innerHTML = "";

			var mapBounds = map.getBounds();
			var bounds = mapBounds;
			var added = 0;

			for (var i = 0, marker; marker = markers[i]; i++)
			{
				if (marker instanceof L.Marker && bounds.contains(marker.getLatLng()))
				{
					added++;
					fc_MapMod_addToVisibleList_<?php echo $module->id;?>(map, marker);
				}
			}

			var header_html = added == 1
				? Joomla.JText._('MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_1_ENTRY')
				: (added == 0
					? Joomla.JText._('MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_0_ENTRIES')
					: Joomla.JText._('MOD_FLEXIGOOGLEMAP_MARKERS_LIST_HEADER_N_ENTRIES'));
			ol_header.innerHTML = header_html.replace(/%s/, added);
		};


		function fc_MapMod_initialize_<?php echo $module->id;?>()
		{
			var locations = [ <?php echo implode(",",  $renderedMapLocations); ?>  ];

			var markerClusters;
			var markers = [];

			var lat = 48.852969;
			var lon = 2.349903;

			var map = L.map('fc_module_map_<?php echo $module->id;?>',{scrollWheelZoom: <?php echo $usescrollmouse;?>}).setView([lat, lon],11);
			<?php if ($clustermode) {
				echo "markerClusters = L.markerClusterGroup({ disableClusteringAtZoom: ".$maxzoommarker.",removeOutsideVisibleBounds:true,animate:true, maxClusterRadius :".$gridsize," }); ";// create cluster and add zoom limitation
			}
			?>
			// Title display
			L.tileLayer('<?php echo $os_tile_server_url; ?>',
			{
				// Datas sources
				attribution: '<?php echo JText::_('OPENSTREETMAP_ATTRIBUTION_TXT'); ?>',
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

			for (var i = 0; i < locations.length; i++)
			{
				<?php /* TODO Add Mapbox key and title loading for custom display */ ?>
				let customMarkerIcon = locations[i][3] == '__default__'
					? defaultMarkerIcon
					: L.icon({
						iconUrl: locations[i][3],
						iconSize: [locations[i][4], locations[i][5]],
						iconAnchor: [locations[i][6], locations[i][7]]
					});

				if (customMarkerIcon)
				{
					marker = new L.marker( [locations[i][1], locations[i][2]], {icon: customMarkerIcon} );
				}
				else
				{
					marker = new L.marker( [locations[i][1], locations[i][2]] );
				}

				marker._location_info = locations[i][0];

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
				'markerClusters.addLayer(marker);' : ''; ?>
				markers.push(marker);
			}
			var group = new L.featureGroup(markers);

			<?php echo $clustermode ?
			'map.addLayer(markerClusters);' : ''; ?>

			map.fitBounds(group.getBounds().pad(0.5));

			fc_MapMod_updateVisibleMarkerList_<?php echo $module->id;?>(map, markers);				

			map.on('moveend', function(e)
			{
				//window.console.log('moveend');
				fc_MapMod_updateVisibleMarkerList_<?php echo $module->id;?>(map, markers);				
			});
		}

		// Initialize the Map
		fc_MapMod_initialize_<?php echo $module->id;?>();
	</script>


<?php endif ;?>
