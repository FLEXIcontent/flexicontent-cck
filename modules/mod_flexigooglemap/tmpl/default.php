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

JHtml::_('bootstrap.tooltip');
JHTML::_('behavior.modal');
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

$clustermode = $params->get('clustermode', '' );
$gridsize = $params->get('gridsize', '' );
$maxzoom = $params->get('maxzoom', '' );
$imgcluster = $params->get('imgcluster','');


$uselink = $params->get('uselink', '' );
$useadress = $params->get('useadress', '' );

$animationmarker = $params->get('animationmarker', '' );
$linkmode = $params->get('linkmode', '' );

$readmore = $params->get('readmore', '' );

$usedirection = $params->get('usedirection','');
$directionname = $params->get('directionname','');

$catidmode = $params->get('catidmode');
$fieldaddressid = $params->get('fieldaddressid');
$forced_itemid = $params->get('forced_itemid','');

$infotextmode = $params->get('infotextmode','');
$relitem_html = $params->get('relitem_html','');

$ratiomap = $params->get('ratiomap','');

// Get items of current view
global $fc_list_items;
if ( empty($fc_list_items) )
{
	$fc_list_items = array();
}

// Add google maps API
flexicontent_html::loadFramework('google-maps', '', $params);
?>

<div id="mod_fleximap_default<?php echo $module->id;?>" class="mod_fleximap map<?php echo $moduleclass_sfx ?>" style="width:<?php echo $width; ?>;height:<?php echo $height; ?>;padding-bottom: <?php echo $ratiomap; ?>;">
	<div id="map" style="position: absolute;width:<?php echo $width; ?>;height:<?php echo $height; ?>;"></div>

	<script type="text/javascript" src="modules/mod_flexigooglemap/assets/js/markerclusterer.js"></script>
	<script type="text/javascript">

		<?php
		$tMapTips = array();

		// Fixed category mode
		if ($catidmode ==0)
		{
			foreach ($itemsLoc as $itemLoc)
			{
				if ( empty($itemLoc->value) ) continue;   // skip empty value

				$coord = unserialize ($itemLoc->value);
				$lat = $coord['lat'];
				$lon = $coord['lon'];

				if ( empty($lat) && empty($lon) ) continue;    // skip empty value

				$title = rtrim( addslashes($itemLoc->title) );

				$link = '';
				if ($uselink)
				{
					$link = $itemLoc->link;
					$link = '<p class="link"><a href="'.$link.'" target="'.$linkmode.'">'.JText::_($readmore).'</a></p>';
					$link = addslashes($link);
				}

				$addr = '';
				if ($useadress)
				{
					if ( !isset($coord['addr_display']) ) $coord['addr_display'] = '';
					$addr = '<p>'.$coord['addr_display'].'</p>';
					$addr = addslashes($addr);
					$addr = preg_replace("/(\r\n|\n|\r)/", " ", $addr);
				}

				$linkdirection = '';
				if ($usedirection)
				{
					$adressdirection = $addr;
					$linkdirection= '<div class="directions"><a href="http://maps.google.com/maps?q='.$adressdirection.'" target="_blank" class="direction">'.JText::_($directionname).'</a></div>';
				}

				$contentwindows = $infotextmode  ?  $relitem_html  :  $addr .' '. $link;

				$coordinates = $lat .','. $lon;
				$tMapTips[] = "['<h4 class=\"fleximaptitle\">$title</h4>$contentwindows $linkdirection'," . $coordinates . "]\r\n";
			}
		}

		// Current category mode
		else
		{
			foreach ($fc_list_items as $address)
			{
				if ( ! isset( $address->fieldvalues[$fieldaddressid][0]) ) continue;   // skip empty value

				$coord = unserialize ($address->fieldvalues[$fieldaddressid][0]);
				$lat = $coord['lat'];
				$lon = $coord['lon'];

				if ( empty($lat) && empty($lon) ) continue;    // skip empty value

				$title = addslashes($address->title);

				$link = '';
				if ($uselink)
				{
					$link = JRoute::_(FlexicontentHelperRoute::getItemRoute($address->id, $address->catid, $forced_itemid, $address));
					$link = '<p class="link"><a href="'.$link.'" target="'.$linkmode.'">'.JText::_($readmore).'</a></p>';
					$link = addslashes($link);
				}

				$addr = '';
				if ($useadress)
				{
					if ( !isset($coord['addr_display']) ) $coord['addr_display'] = '';
					$addr = '<p>'.$coord['addr_display'].'</p>';
					$addr = addslashes($addr);
					$addr = preg_replace("/(\r\n|\n|\r)/", " ", $addr);
				}

				$linkdirection = '';
				if ($usedirection)
				{
					$adressdirection = $addr;
					$linkdirection= '<div class="directions"><a href="http://maps.google.com/maps?q='.$adressdirection.'" target="_blank" class="direction">'.JText::_($directionname).'</a></div>';
				}

				$contentwindows = $infotextmode  ?  $relitem_html  :  $addr .' '. $link;

				$coordinates = $lat .','. $lon;
				$tMapTips[] = "['<h4 class=\"fleximaptitle\">$title</h4>$contentwindows $linkdirection'," . $coordinates . "]\r\n";
			}
		}

		$tabMapTipsJS = implode(",",  $tMapTips);
		?>

		// nouveau script
		// Define your locations: HTML content for the info window, latitude, longitude
		var locations = [ <?php echo $tabMapTipsJS; ?>  ];

		var icons = [<?php echo $markerdisplay; ?>]
		var iconsLength = icons.length;

		var map = new google.maps.Map(document.getElementById('map'), {
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
				<?php echo ($animationmarker ? 'animation: google.maps.Animation.DROP,' : ''); ?>
				icon: icons[iconCounter]
			});

			markers.push(marker);

			google.maps.event.addListener(marker, 'click', (function(marker, i) {
				return function() {
					infowindow.setContent(locations[i][0]);
					infowindow.open(map, marker);
				}
			})(marker, i));

			iconCounter++;
			// We only have a limited number of possible icon colors, so we may have to restart the counter
			if(iconCounter >= iconsLength)
			{
				iconCounter = 0;
			}
		}

		function autoCenter()
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

		<?php if ($clustermode)
		{
			echo "
			var mcOptions = {gridSize:$gridsize, maxZoom:$maxzoom, imagePath: 'images/mod_flexigooglemap/cluster/$imgcluster'};
			var marker = new MarkerClusterer(map, markers, mcOptions);
			";
		}
		?>
		autoCenter();

	</script>
</div>
