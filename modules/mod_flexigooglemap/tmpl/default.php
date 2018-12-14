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
if (empty($tMapTips))
{
	return;
}

JHtml::_('bootstrap.tooltip');
JHtml::_('behavior.modal');
$document = JFactory::getDocument();
$document->addStyleSheet("./modules/mod_flexigooglemap/assets/css/style.css",'text/css',"screen");

$itemmodel_name = 'FlexicontentModelItem';
$itemmodel = new $itemmodel_name();

//module config
$mapapi = $params->get('mapapi', 'googlemap');
$height = $params->get('height', '300px');
$width  = $params->get('width', '200px');
$mapcenter = $params->get('mapcenter', '48.8566667, 2.3509871');
$apikey    = $params->get('apikey', '');
$maptype   = $params->get('maptype', '');
$maxzoommarker = $params->get('maxzoommarker', '');

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
$maxzoom = $params->get('maxzoom', '');
$ratiomap = $params->get('ratiomap','');

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

// Get image size of local marker
$scaledSize = 'null';
/*
$markermode = $params->get('markermode', $params->get('lettermarkermode', 0));
$markerimage = $params->get('markerimage');

if ($markermode==0 && $markerimage && $img_info = getimagesize(JPATH::clean(JPATH_ROOT.DS.$markerimage)))
{
	$scaledSize = 'scaledSize: new google.maps.Size('. $img_info[0] . ', ' . $img_info[1] . ')';
}*/

// Load framework (Map API)
switch ($mapapi)
{
	case 'googlemap';
		flexicontent_html::loadFramework('google-maps', '', $params);
		break;
	case 'openstreetmap';
		flexicontent_html::loadFramework('openstreetmap', '', $params);
		break;
}
?>


<?php /* Module container*/ ?>
<div id="mod_fleximap_default<?php echo $module->id;?>" class="mod_fleximap map<?php echo $moduleclass_sfx ?>" style="width:<?php echo $width; ?>;height:<?php echo $height; ?>;">

	<?php /* Map container*/ ?>
	<div id="fc_module_map_<?php echo $module->id;?>" style="width:<?php echo $width; ?>;height:<?php echo $height; ?>;">
	</div>

</div>


<?php if ($mapapi === 'googlemap') : ?>


	<script type="text/javascript" src="modules/mod_flexigooglemap/assets/js/markerclusterer.js"></script>
	<script>

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

		function fc_MapMod_initialize_<?php echo $module->id;?>()
		{
			// Define your locations: HTML content for the info window, latitude, longitude
			var locations = [ <?php echo implode(",",  $tMapTips); ?>  ];

			var map = new google.maps.Map(document.getElementById('fc_module_map_<?php echo $module->id;?>'), {
				maxZoom: [<?php echo $maxzoommarker; ?>],
				center: new google.maps.LatLng(-37.92, 151.25),
				mapTypeId: google.maps.MapTypeId.<?php echo $maptype;?>,
				mapTypeControl: false,
				streetViewControl: false,
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
			var customMarkerIcons = [];
			var iconCounter = 0;

			customMarkerIcons[0] = {
				url: <?php echo $markerdisplay; ?>
				// Instead of guessing image placement we will leave this to defaults
				//,scaledSize: <?php echo $scaledSize; ?>
				//,origin: new google.maps.Point(0, 0)
				//,anchor: new google.maps.Point(11, 40)
			}

			// Add the markers and infowindows to the map
			for (var i = 0; i < locations.length; i++)
			{
				var marker = new google.maps.Marker({
					position: new google.maps.LatLng(locations[i][1], locations[i][2]),
					map: map
					<?php	echo ($params->get('animationmarker', 1) ? ',animation: google.maps.Animation.DROP' : '')."\n"; ?>
					<?php	echo ($markerdisplay ? ',icon: customMarkerIcons[iconCounter]' : '')."\n"; ?>
				});

				markers.push(marker);

				// We only have a limited number of possible icon colors, so rewind icon index when icons finish
				iconCounter = iconCounter >= customMarkerIcons.length ? 0 : iconCounter + 1;

				google.maps.event.addListener(marker, 'click', (function(marker, i) {
					return function() {
						infowindow.setContent(locations[i][0]);
						infowindow.open(map, marker);
					}
				})(marker, i));

				google.maps.event.addDomListener(window, "resize", function() {
					var center = map.getCenter();
					google.maps.event.trigger(map, "resize");
					map.setCenter(center);
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

		function fc_MapMod_initialize_<?php echo $module->id;?>()
		{
			var locations = [ <?php echo implode(",",  $tMapTips); ?>  ];

			var markerClusters;
			var markers = [];

			var lat = 48.852969;
			var lon = 2.349903;

			theMap_<?php echo $module->id;?> = L.map('fc_module_map_<?php echo $module->id;?>').setView([lat, lon], 11);
			<?php if ($clustermode) {
				echo "markerClusters = L.markerClusterGroup({ disableClusteringAtZoom: ".$maxzoommarker.",removeOutsideVisibleBounds:true,animate:true, maxClusterRadius :".$gridsize," }); ";// create cluster and add zoom limitation
			}
			?>
			// Title display
			L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png',
			{
				// Datas sources
				attribution: 'données © OpenStreetMap/ODbL - rendu OSM France',
				minZoom: 1,
				maxZoom: 20
			})
			.addTo(theMap_<?php echo $module->id;?>);

			var customMarkerIcons = [];
			var iconCounter = 0;

			customMarkerIcons[0] = L.icon({
				iconUrl: <?php echo $markerdisplay ?: 'null'; ?>,
				iconSize: [40, 67],
				iconAnchor: [25, 67],
				popupAnchor: [-3, -67]
			});

			for (var i = 0; i < locations.length; i++)
			{
				<?php /* TODO Add Mapbox key and title loading for custom display */ ?>

				marker = new L.marker(
					[locations[i][1],
					locations[i][2]]
					<?php echo $markerdisplay ? ',{ icon: customMarkerIcons[iconCounter] }' : ''; ?>
				)
					.bindPopup(locations[i][0])
					<?php /* Add single location marker */
					echo !$clustermode ?
					'.addTo(theMap_' . $module->id . ');' : ''; ?>

				<?php /* Add a cluster of markers */
				echo $clustermode ?
				'markerClusters.addLayer(marker);' : ''; ?>
				markers.push(marker);

				// We only have a limited number of possible icon colors, so rewind icon index when icons finish
				iconCounter = iconCounter >= customMarkerIcons.length ? 0 : iconCounter + 1;
			}
			var group = new L.featureGroup(markers);

			<?php echo $clustermode ?
			'theMap_' . $module->id . '.addLayer(markerClusters);' : ''; ?>

			theMap_<?php echo $module->id;?>.fitBounds(group.getBounds().pad(0.5));
		}

		// Initialize the Map
		fc_MapMod_initialize_<?php echo $module->id;?>();
	</script>


<?php endif ;?>

