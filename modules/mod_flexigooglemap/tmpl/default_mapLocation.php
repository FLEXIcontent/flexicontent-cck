<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

defined('_JEXEC') or die('Restricted access');

/**
 * Use an anonymous function to encapsulate the logic for rendering map locations
 * - this allows us to use the variables $params, $mapItems, and $module without polluting the global namespace.
 * - and also allows to have custom logic for rendering map locations via atemplate file
 *
 * @var  object   $params          see below
 * @var  object   $locationItem    see below
 * @var  string[] $locationCoords  see below
 * @var  string[] $mapLocations    see below
 * @var  object[] $mapItems        see below
 * @var  object   $module          see below
 */
$renderedMapLocations = (
	/**
	 * Render the HTML popup window display of location marker for the given item
	 *
	 * @param  object    $params          The module parameters
	 * @param  object    $locationItem    The item for which the location marker is being rendered
	 * @param  string[]  $locationCoords  The coordinates of the location, indexes:
	 *                                     'lat', 'lon', 'addr1', 'city', 'province', 'state', 'zip', 'country', 'addr_display', 'url', 'custom_marker', 'marker_anchor'
	 * @param  string[]  $mapLocations    The rendered HTML of the popup window display of location marker for the given item.
	 *                                     - a new string is added for each location marker (MATCHES THE INDEX of $mapItems)
	 * @param  object[]  $mapItems        The items having these markers, to be used by the module layout
	 *                                     - a new object is added for each location marker (MATCHES THE INDEX of $mapLocations)
	 * @param  object    $module          The module object
	 *
	 * @return void
	 * @since 3.0.0
	 */
function ($params, $locationItem, $locationCoords, &$mapLocations, &$mapItems, $module)
{
	/**
	 * Get the location coordinates
	 */
	$locationCoords = flexicontent_db::unserialize_array($locationCoords, false, false);

	/**
	 * Skip current item if address field value has empty either of the coordinates:
	 * - latitude or longitude
	 */
	if (!$locationCoords) return;
	if (!isset($locationCoords['lat']) || !isset($locationCoords['lon'])) return;
	if (!strlen($locationCoords['lat']) || !strlen($locationCoords['lon'])) return;


	/**
	 * Initialize the configuration for the module
	 */
	static $config = [];
	if (!isset($config[$module->id]))
	{
		/**
		 * Read-more button (link to content item)
		 */
		$config[$module->id]['uselink']       = (int) $params->get('uselink', 0);   // 1: Add the button
		$config[$module->id]['readmore']      = Text::_($params->get('readmore', 'MOD_FLEXIGOOGLEMAP_READMORE_TXT'));  // Custom button text for the button
		$config[$module->id]['linkmode']      = $params->get('linkmode', '');       // Link target for the read-more button: Same window OR New window
		$config[$module->id]['forced_itemid'] = $params->get('forced_itemid', 0);   // A forced menu item for the links

		/**
		 * Map directions button (open marker in new window with map directions)
		 */
		$config[$module->id]['usedirection']  = (int) $params->get('usedirection', 0);   // 1: Add the button
		$config[$module->id]['directionname'] = Text::_($params->get('directionname', 'MOD_FLEXIGOOGLEMAP_DIRECTIONNAME_TXT')); // Custom button text for the button

		/**
		 * The content displayed in the info popup window for the marker
		 */
		$config[$module->id]['infotextmode'] = (int) $params->get('infotextmode', 0);  // 0: Title + Address, 1: Custom HTML with field, language, etc. replacements
		$config[$module->id]['tmpl_html']    =  $params->get('custom_html_with_replacements', '<h4 class="marker-info-title">{item->title}</h4><p>{__address__}</p>');  // The custom HTML with replacements

		/**
		 * Display address field value to the marker popup window
		 */
		$config[$module->id]['useadress']    = (int) $params->get('useadress', 1);   // 1: Display the address
		$config[$module->id]['useadress']    = !$config[$module->id]['infotextmode'] ? $config[$module->id]['useadress'] : strpos($config[$module->id]['tmpl_html'], '{__address__}') !== false;

		/**
		 * The address field (id
		 */
		$config[$module->id]['fieldaddressid'] = $params->get('fieldaddressid');
	}

	$useLink       = $config[$module->id]['uselink'];
	$readMore      = $config[$module->id]['readmore'];
	$linkMode      = $config[$module->id]['linkmode'];
	$forced_ItemId = $config[$module->id]['forced_itemid'];

	$useDirection  = $config[$module->id]['usedirection'];
	$directionName = $config[$module->id]['directionname'];

	$infoTextMode = $config[$module->id]['infotextmode'];
	$tmpl_html    = $config[$module->id]['tmpl_html'];

	$useAddress     = $config[$module->id]['useadress'];
	$fieldAddressId = $config[$module->id]['fieldaddressid'];

	/**
	 * Get address (maps) field
	 */
	$field = modFlexigooglemapHelper::getField($fieldAddressId);

	/**
	 * Get image configuration if using custom marker
	 */
	if (!empty($fieldAddressId))
	{
		$use_custom_marker = (int) $params->get('use_custom_marker', 1) && (int) $field->parameters->get('use_custom_marker', 1);

		if ($use_custom_marker)
		{
			$custom_marker_path     = $field->parameters->get('custom_marker_path', 'modules/mod_flexigooglemap/assets/marker');
			$custom_marker_path_abs = Path::clean(JPATH_SITE . DS . $custom_marker_path . DS);
			$custom_marker_url_base = str_replace('\\', '/', Uri::root() . $custom_marker_path . '/');
		}
	}

	/**
	 * Add item to array of know items
	 */
	$item = clone ($locationItem);
	$item->itemslug = $item->itemslug ?? $item->id.':'.$item->alias;
	$item->catslug = $item->catslug ?? $item->catid;
	$item->link = Route::_(FlexicontentHelperRoute::getItemRoute($item->itemslug, $item->catslug, $forced_ItemId, $item));
	$mapItems[] = $item;

	$relatedItem_html = '';
	if ($infoTextMode)
	{
		$_params = new Registry();
		$_params->set('relitem_html', $tmpl_html);
		$_item_list = [$item->id => $item];
		$_itemIDs   = [];
		$_options   = new stdClass();
		FlexicontentFields::getFields($_item_list);
		$relatedItem_html = addslashes(FlexicontentFields::createItemsListHTML($_params, $_item_list, false, false, $_itemIDs, $_options));
	}

	$title = addslashes($item->title);
	$link = '';
	$addr = '';
	$markerDirections_linkHtml = '';

	/**
	 * Popup window: show (button) link to the item view
	 */
	if ($useLink) {
		$link = $item->link;
		$link = '
<div class="marker_readmore">
	<a href="' . $link . '" target="' . $linkMode . '" class="fc-map-link btn btn-secondary">
		<span class="fc-map-link-icon"></span>
		<span class="fc-map-link-text">' . $readMore . '</span>
	</a>
</div>
';
		$link = addslashes(str_replace(["\r\n", "\n", "\r"], ' ', $link));
	}

	/**
	 * Popup window: show address details
	 */
	if ($useAddress && !empty($locationCoords['addr_display']))
	{
		$addr = $locationCoords['addr_display'];
		$addr = addslashes($addr);
		$addr = preg_replace("/(\r\n|\n|\r)/", " ", $addr);

		if ($infoTextMode)
		{
			$relatedItem_html = str_replace('{__address__}', $addr, $relatedItem_html);
		}
	}

	/**
	 * Popup window: show (button) link to Google-map directions page
	 */
	if ($useDirection)
	{
		/**
		 * Generate link to google maps directions
		 */
		$markerDirections_link = empty($locationCoords['url'])  ?  false  :  $locationCoords['url'];

		/**
		 * if no url, compatibility with old values
		 */
		if (empty($markerDirections_link))
		{
			$markerDirections_link = "http://maps.google.com/maps?q=";
			if (!empty($locationCoords['addr1']) && !empty($locationCoords['city']) && (!empty($locationCoords['province']) || !empty($locationCoords['state']))  && !empty($locationCoords['zip'])) {
				$markerDirections_link .= urlencode(($locationCoords['addr1'] ? $locationCoords['addr1'] . ',' : '')
					. ($locationCoords['city'] ? $locationCoords['city'] . ',' : '')
					. ($locationCoords['state'] ? $locationCoords['state'] . ',' : ($locationCoords['province'] ? $locationCoords['province'] . ',' : ''))
					. ($locationCoords['zip'] ? $locationCoords['zip'] . ',' : '')
					. ($locationCoords['country'] ? Text::_('PLG_FC_ADDRESSINT_CC_' . $locationCoords['country']) : ''));
			} else {
				$markerDirections_link .= urlencode($locationCoords['lat'] . "," . $locationCoords['lon']);
			}
		}

		$markerDirections_linkHtml = '
<div class="marker_directions">
	<a href="' . $markerDirections_link . '" target="_blank" class="fc-map-link btn btn-secondary">
		<span class="fc-map-link-icon"></span>
		<span class="fc-map-link-text">	' . $directionName . '</span>
	</a>
</div>
';
	}
	$markerDirections_linkHtml = addslashes(str_replace(["\r\n", "\n", "\r"], ' ', $markerDirections_linkHtml));

	/**
	 * Popup window: custom HTML with replacements or default
	 */
	$contentWindows = $infoTextMode
		? preg_replace("/(\r\n|\n|\r)/", " ", $relatedItem_html)
		: "<h4 class=\"marker-info-title\">$title</h4> <p>$addr</p>";

	/**
	 * Get custom marker and its anchor (= marker's placement)
	 */
	$add_custom_marker = $use_custom_marker && !empty($locationCoords['custom_marker']);

	$marker_anchor = $add_custom_marker && !empty($locationCoords['marker_anchor'])
		? $locationCoords['marker_anchor']
		: 'BotC';

	$marker_path = $add_custom_marker
		? $custom_marker_path_abs . $locationCoords['custom_marker']
		: '';
	$marker_url  = $add_custom_marker
		? $custom_marker_url_base . $locationCoords['custom_marker']
		: '';

	/**
	 * If using custom marker, get its size and anchor position
	 */
	if ($marker_path)
	{
		/**
		 * Marker Size
		 */
		list($markerWidth, $markerHeight) = getimagesize($marker_path);

		/**
		 * Marker Anchor position relative to the marker dimensions
		 * - The anchor is the point of the marker that touches the map location.
		 * - This is typically the bottom center of the majority of marker images.
		 */
		switch ($marker_anchor) {
			case 'TopL':
				$markerAnchor_x = 0;
				$markerAnchor_y = 0;
				break;
			case 'TopC':
				$markerAnchor_x = $markerWidth / 2;
				$markerAnchor_y = 0;
				break;
			case 'TopR':
				$markerAnchor_x = $markerWidth;
				$markerAnchor_y = 0;
				break;

			case 'MidL':
				$markerAnchor_x = 0;
				$markerAnchor_y = $markerHeight / 2;
				break;
			case 'MidC':
				$markerAnchor_x = $markerWidth / 2;
				$markerAnchor_y = $markerHeight / 2;
				break;
			case 'MidR':
				$markerAnchor_x = $markerWidth;
				$markerAnchor_y = $markerHeight / 2;
				break;

			case 'BotL':
				$markerAnchor_x = 0;
				$markerAnchor_y = $markerHeight;
				break;
			case 'BotC':
				$markerAnchor_x = $markerWidth / 2;
				$markerAnchor_y = $markerHeight;
				break;
			case 'BotR':
				$markerAnchor_x = $markerWidth;
				$markerAnchor_y = $markerHeight;
				break;
		}
	}

	/**
	 * Offset the markers, commented out, instead use: overlapping-marker-spiderfier JS
	 * - for google maps use: OverlappingMarkerSpiderfier JS library (https://cdnjs.cloudflare.com/ajax/libs/OverlappingMarkerSpiderfier/n.n.n/oms.min.js
	 * - and for OpenLayers (Leaflet) use:leaflet.markercluster.js
	 */
	/*static $same_coords_counters = [];
	$_lat = $locationCoords['lat'];
	$_lon = $locationCoords['lon'];
	$same_coords_counters[$_lat][$_lon] = $same_coords_counters[$_lat][$_lon] ?? 0;
	$same_coords_counters[$_lat][$_lon]++;
	if ($same_coords_counters[$_lat][$_lon] > 0)
	{
		$same_coords_counter = $same_coords_counters[$_lat][$_lon];
		$locationCoords['lat'] += 0.0001 * $same_coords_counter * rand(-1, 1);
		$locationCoords['lon'] += 0.0001 * $same_coords_counter * rand(-1, 1);
	}*/

	$mapLocations[] = "[
					'<div class=\"marker-info-contents-box\">$contentWindows <div class=\"marker_actions\">$link $markerDirections_linkHtml</div></div>'," .
		$locationCoords['lat'] . ", " .
		$locationCoords['lon'] . ", " .
		(!$marker_url ? "'__default__'" :
			"'" . $marker_url . "', " .
			"'" . $markerWidth . "', " .
			"'" . $markerHeight . "', " .
			"'" . $markerAnchor_x . "', " .
			"'" . $markerAnchor_y . "'
					") . "
				]
				";
}) ($params, $locationItem, $locationCoords, $mapLocations, $mapItems, $module);