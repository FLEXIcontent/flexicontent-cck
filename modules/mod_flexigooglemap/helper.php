<?php

/**
 * @version 0.6 stable $Id: helper.php yannick berges
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


use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

defined('_JEXEC') or die('Restricted access');

class modFlexigooglemapHelper
{

	/**
	 * Check if the module is properly configured
	 *
	 * @param object    $params       The module parameters
	 * @param bool      $print_error  If true, print error messages
	 *
	 * @return bool
	 * @since  3.0.0
	 */
	public static function _checkConfiguration($params, $print_error = false)
	{
		/**
		 * Check field ID configured in module configuration
		 */
		$fieldAddressId = $params->get('fieldaddressid');

		if (empty($fieldAddressId)) {
			if ($print_error) echo '<div class="alert alert-warning">' . Text::_('MOD_FLEXIGOOGLEMAP_ADDRESSFORGOT') . '</div>';
			return false;
		}

		/**
		 * Get Address (Maps) field
		 */
		$field = modFlexigooglemapHelper::getField($fieldAddressId);

		if (empty($field))
		{
			/**
			 * Rare error done by webmasters (so do not add language string), the field ID is not a valid field ID or it is not an address field
			 */
			if ($print_error) echo '<div class="alert alert-warning">Address (Maps) Field with id ' . $fieldAddressId . ' or it is not an address field. Please select an appropriate field in module configuration</div>';
			return false;
		}

		return true;
	}

	/**
	 * Get items having map locations (for the given field and the given categories)
	 *
	 * @param object    $params    The module parameters
	 *
	 * @return array|mixed
	 * @since  1.0.0
	 */
	public static function getItemsLocations($params)
	{
		if (!static::_checkConfiguration($params, $print_error = true)) return [];

		// Get field ID configured in module configuration
		$fieldAddressId = $params->get('fieldaddressid');

		/**
		 * First, get the categories that have the items
		 * - By default include children categories
		 */
		$treeInclude = $params->get('treeinclude', 1);
		$catIds      = $params->get('catid');
		$catIds      = is_array($catIds) ? $catIds : [$catIds];

		/**
		 * Retrieve extra categories, such children or parent categories
		 * - Check if zero allowed categories
		 */
		$catIdsArr = flexicontent_cats::getExtraCats($catIds, $treeInclude, array());
		if (empty($catIdsArr)) return [];

		$count = $params->get('count');

		/**
		 * Include : 1 or Exclude : 0 categories
		 */
		$method_category = $params->get('method_category', '1');
		$catWhere = $method_category == 0
			? ' rel.catid IN (' . implode(',', $catIdsArr) . ')'
			: ' rel.catid NOT IN (' . implode(',', $catIdsArr) . ')';

		/**
		 * Retrieve the items having the map locations (for the given field and the given categories)
		 */
		$db = \Joomla\CMS\Factory::getDbo();
		$queryLoc = 'SELECT a.*, b.field_id, b.value '
			. ', CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as itemslug'
			. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
			. ', ext.type_id as type_id'
			. ' FROM #__content  AS a'
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id '
			. ' JOIN #__categories AS c ON c.id = a.catid'
			. ' LEFT JOIN #__flexicontent_fields_item_relations AS b ON a.id = b.item_id '
			. ' LEFT JOIN #__flexicontent_items_ext AS ext ON a.id = ext.item_id '
			. ' WHERE b.field_id = ' . $fieldAddressId . ' AND ' . $catWhere . '  AND state = 1'
			. ' ORDER BY title ' . $count;
		$itemsLoc = $db->setQuery($queryLoc)->loadObjectList();

		return $itemsLoc;
	}


	/**
	 * Render the map locations for the items that will be matched according to configuration
	 *
	 * @param object     $params     The module parameters
	 * @param object[]   $mapItems   The items having these markers, to be used by the module layout
	 * @param object     $module     The module object
	 *
	 * @return array
	 * @since  3.0.0
	 * @throws Exception
	 */
	public static function renderMapLocations($params, &$mapItems, $module)
	{
		if (!static::_checkConfiguration($params, $print_error = false)) return [];

		// Get field ID configured in module configuration
		$fieldaddressid = $params->get('fieldaddressid');

		// The rendered HTML and other Metadata of the map Locations for the $mapItems that will be matched according to configuration
		$mapLocations = [];

		// Fixed category mode
		if ($params->get('catidmode') == 0)
		{
			$itemsLocations = modFlexigooglemapHelper::getItemsLocations($params);
			$itemsLocations = $itemsLocations ?: array();

			// Items having these markers, to be used by the module layout
			$mapItems = [];

			foreach ($itemsLocations as $address_item) {
				// Skip empty value
				if (empty($address_item->value)) continue;
				$coord = $address_item->value;

				// Render the Location HTML according to configuration, possibly using customn HTML with replacements
				static::renderLocation($params, $address_item, $coord, $mapLocations, $mapItems, $module);
			}
		}

		/**
		 * Current category mode or current item mode, these are pre-created (global variables)
		 */
		else
		{
			/**
			 * Current category mode
			 * - Get items of current (category) view via a global variable
			 */
			if ($params->get('catidmode') == 1)
			{
				global $fc_list_items;
				if (empty($fc_list_items))
				{
					$fc_list_items = array();
				}
			}

			/**
			 * Get current item mode
			 * - Get item of current (item) view via a global variable
			 */
			else
			{
				global $fc_view_item;
				$fc_list_items = !empty($fc_view_item) ? [$fc_view_item] : [];
			}

			/**
			 * Render the map locations for the content items
			 * - We will create one to one array below for locations and items (items are repeated if having multiple locations)
			 * - Skip any item that has no address values
			 * - The location HTML is rendered according to configuration, possibly using custom HTML with replacements
			 */
			$mapItems = [];
			foreach ($fc_list_items as $address_item) if (!empty($address_item->fieldvalues[$fieldaddressid])) foreach ($address_item->fieldvalues[$fieldaddressid] as $coord)
			{
				static::renderLocation($params, $address_item, $coord, $mapLocations, $mapItems, $module);
			}
		}

		return $mapLocations;
	}


	/**
	 * @param  object    $params          The module parameters
	 * @param  object    $address_item
	 * @param  string[]  $locationCoords  The coordinates of the location, indexes:
	 *                                     'lat', 'lon', 'addr1', 'city', 'province', 'state', 'zip', 'country', 'addr_display', 'url', 'custom_marker', 'marker_anchor'
	 * @param  string[]  $mapLocations    The rendered HTML and other Metadata of the map Locations for the $mapItems that will be matched according to configuration
	 *                                     (to be used by the module layout)
	 * @param  object[]  $mapItems        The items having these markers, to be used by the module layout
	 * @param  object    $module          The module object
	 *
	 * @since 3.0.0
	 */
	public static function renderLocation($params, $address_item, $locationCoords, &$mapLocations, &$mapItems, $module)
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
		$item = clone ($address_item);
		$item->itemslug = $item->itemslug ?? $item->id.':'.$item->alias;
		$item->catslug = $item->catslug ?? $item->catid;
		$item->link = \Joomla\CMS\Router\Route::_(FlexicontentHelperRoute::getItemRoute($item->itemslug, $item->catslug, $forced_ItemId, $item));
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
	}

	/**
	 * Get a default Marker icon URL for map locations that do not specify a specific marker icon
	 *
	 * @param   object   $params         The module parameters
	 * @param   int     &$markerWidth    The marker width, (passed by reference)
	 * @param   int     &$markerHeight   The marker height, (passed by reference)
	 * @param   int     &$markerAnchorX  The marker anchor X position relative to the marker width, (passed by reference)
	 * @param   int     &$markerAnchorY  The marker anchor Y position relative to the marker height, (passed by reference)
	 *
	 * @since 3.0.0
	 */
	public static function getDefaultMarkerURL($params, &$markerWidth = 0, &$markerHeight = 0, &$markerAnchorX = 0, &$markerAnchorY = 0)
	{
		/**
		 * Get marker mode, 'lettermarkermode' was old parameter name, (in future wew may more more modes, so the old parameter name was renamed)
		 */
		$markerMode  = (int) $params->get('markermode', $params->get('lettermarkermode', 0));
		$markerImage = $params->get('markerimage', '');

		/**
		 * Fall-back to the default marker icon if custom image not set
		 */
		$markerMode = $markerMode === 1 && !$markerImage
			? -1   // Default marker icon
			: $markerMode;

		$defaultMarker_path = '';

		switch ($markerMode)
		{
			/**
			 * 'Letter' mode
			 */
			case 1:
				$color_to_file = [
					'red'   => 'spotlight-waypoint-b.png',
					'green' => 'spotlight-waypoint-a.png',
					''      => 'spotlight-waypoint-b.png' /* '' is for not set*/
				];
				$defaultMarker_url = "https://mts.googleapis.com/vt/icon/name=icons/spotlight/"
					. $color_to_file[$params->get('markercolor', '')]
					. "?text=" . $params->get('lettermarker')
					. "&psize=16&font=fonts/arialuni_t.ttf&color=ff330000&scale=1&ax=44&ay=48";
				break;
			/**
			 * 'Local image file' mode
			 */
			case 0:
				$defaultMarker_path = JPATH_SITE . '/' . $markerImage;
				$defaultMarker_url  = Uri::root(true) . '/' . $markerImage;
				break;

			/**
			 * Default marker icon: !!! AVOID changing the default marker icon URL
			 * - so that we do not have to call getimagesize() on it, which will cause a delay during PHP execution
			 */
			case -1:
			default:
				$defaultMarker_url = null;
				/*$defautmarker_url = $params->get('mapapi', 'googlemap') === 'googlemap'
					? 'https://maps.gstatic.com/mapfiles/api-3/images/spotlight-poi2.png'
					: 'https://unpkg.com/leaflet@1.5.1/dist/images/marker-icon.png';*/
				break;
		}

		/**
		 * Calculate the default Marker Size and placement
		 * - Only calculate these if the default marker has been changed, because this is maybe a URL on which we need to call getimagesize()
		 * - in order to avoid delay during PHP execution due to getimagesize() call, we use caching to store the marker size and marker anchor position
		 */

		if ($defaultMarker_url && $params->get('mapapi', 'googlemap') !== 'googlemap')
		{
			if (FLEXI_CACHE) {
				$cache = \Joomla\CMS\Factory::getCache('com_flexicontent');
				$cache->setCaching(1);
				$cache->setLifeTime(FLEXI_CACHE_TIME);
				list($markerWidth, $markerHeight) = $cache->get('getimagesize', array($defaultMarker_path ?: $defaultMarker_url));
			} else {
				list($markerWidth, $markerHeight) = getimagesize($defaultMarker_path ?: $defaultMarker_url);
			}

			/**
			 * Marker anchor default marker anchor.
			 * - This is the point of the marker that touches the map location.
			 * - For default marker icons this is the bottom center.
			 */
			$markerAnchorX = $markerWidth / 2;
			$markerAnchorY = $markerHeight;
		}

		return $defaultMarker_url;
	}


	/**
	 * Load a Flexicontent field via its ID
	 *
	 * @param   int  $fieldId  The field ID
	 *
	 * @return  object|false  The field object if found, otherwise false
	 * @since   3.0.0
	 */
	public static function getField($fieldId)
	{
		/**
		 * Return already loaded field
		 */
		static $fields = [];
		if (isset($fields[$fieldId])) return $fields[$fieldId];

		/**
		 * Load the field
		 */
		$_fields = FlexicontentFields::getFieldsByIds(array($fieldId), false);
		$field   = !empty($_fields[$fieldId]) ? $_fields[$fieldId] : false;

		/**
		 * Parse field parameters
		 */
		if ($field)
		{
			$field->parameters = new \Joomla\Registry\Registry($field->attribs);
		}

		/**
		 * Cache and return the field
		 */
		return $fields[$fieldId] = $field;
	}

}
