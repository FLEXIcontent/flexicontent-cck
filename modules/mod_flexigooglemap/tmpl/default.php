<?php
/**
* @version 0.9.1 stable $Id: default.php yannick berges
* @package Joomla
* @subpackage FLEXIcontent
* @copyright (C) 2015 Berges Yannick - www.com3elles.com
* @license GNU/GPL v2

* special thanks to ggppdk and emmanuel dannan for flexicontent
* special thanks to my master Marc Studer

* FLEXIadmin module is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
**/

//blocage des accés directs sur ce script
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
/*$markermode = $params->get('markermode', $params->get('lettermarkermode', 0));
$markerimage = $params->get('markerimage');
if ($markermode==0 && $markerimage && $img_info = getimagesize(JPATH::clean(JPATH_ROOT.DS.$markerimage)))
{
	$scaledSize = 'scaledSize: new google.maps.Size('. $img_info[0] . ', ' . $img_info[1] . ')';
}*/

// Add google maps API
flexicontent_html::loadFramework('google-maps', '', $params);

?>

<div id="mod_fleximap_default<?php echo $module->id;?>" class="mod_fleximap map<?php echo $moduleclass_sfx ?>" style="width:<?php echo $width; ?>;height:<?php echo $height; ?>;">
	<div id="fc_module_map_<?php echo $module->id;?>" style="width:<?php echo $width; ?>;height:<?php echo $height; ?>;"></div>

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

		var icons = [
			{
				url: <?php echo $markerdisplay; ?>,
				scaledSize: <?php echo $scaledSize; ?>, // scaled size
				origin: new google.maps.Point(0, 0), // origin
				anchor: new google.maps.Point(0, 0) // anchor
			}
		];
		var iconsLength = icons.length;

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

		var iconCounter = 0;

		// Add the markers and infowindows to the map
		for (var i = 0; i < locations.length; i++)
		{
			var marker = new google.maps.Marker({
				position: new google.maps.LatLng(locations[i][1], locations[i][2]),
				map: map,
				<?php	echo ($params->get('animationmarker', 1) ? 'animation: google.maps.Animation.DROP,' : '')."\n"; ?>
				icon: icons[iconCounter]
			});

			markers.push(marker);

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

			iconCounter++;
			// We only have a limited number of possible icon colors, so we may have to restart the counter
			if(iconCounter >= iconsLength)
			{
				iconCounter = 0;
			}
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
	fc_MapMod_initialize_<?php echo $module->id;?>();

	</script>
</div>
