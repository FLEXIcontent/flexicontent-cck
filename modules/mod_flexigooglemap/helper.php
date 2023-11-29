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

defined('_JEXEC') or die('Restricted access');

class modFlexigooglemapHelper
{
	public static function getItemsLocations(&$params)
	{
		/**
		 * Check field ID configured in module configuration
		 */
		$fieldaddressid = $params->get('fieldaddressid');

		if (empty($fieldaddressid)) {
			echo '<div class="alert alert-warning">' . JText::_('MOD_FLEXIGOOGLEMAP_ADDRESSFORGOT') . '</div>';
			return array();
		}


		/**
		 * Get Address (Maps) field
		 */
		$field = modFlexigooglemapHelper::_getField($fieldaddressid);

		if (empty($field)) {
			// Rare error. Do not add language string
			echo '<div class="alert alert-warning">Address (Maps) Field with id ' . $fieldaddressid . ' or it is not an address field. Please select an appropriate field in module configuration</div>';
			return array();
		}


		/**
		 * First get the categories that have the items
		 */

		// By default include children categories
		$treeinclude = $params->get('treeinclude', 1);

		// Make sure categories is an array
		$catids = $params->get('catid');
		$catids = is_array($catids) ? $catids : array($catids);

		// Retrieve extra categories, such children or parent categories
		$catids_arr = flexicontent_cats::getExtraCats($catids, $treeinclude, array());

		// Check if zero allowed categories
		if (empty($catids_arr)) {
			return array();
		}

		$count = $params->get('count');

		// Include : 1 or Exclude : 0 categories
		$method_category = $params->get('method_category', '1');

		$catWheres = $method_category == 0
			? ' rel.catid IN (' . implode(',', $catids_arr) . ')'
			: ' rel.catid NOT IN (' . implode(',', $catids_arr) . ')';


		/**
		 * Retrieve the items having the map locations (for the given field and the given categories)
		 */

		$db = JFactory::getDbo();
		$queryLoc = 'SELECT a.id, a.title, b.field_id, b.value , a.catid '
			. ', CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as itemslug'
			. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
			. ' FROM #__content  AS a'
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id '
			. ' JOIN #__categories AS c ON c.id = a.catid'
			. ' LEFT JOIN #__flexicontent_fields_item_relations AS b ON a.id = b.item_id '
			. ' WHERE b.field_id = ' . $fieldaddressid . ' AND ' . $catWheres . '  AND state = 1'
			. ' ORDER BY title ' . $count;
		$db->setQuery($queryLoc);
		$itemsLoc = $db->loadObjectList();


		/**
		 * Also create the item links
		 */
		$forced_itemid = $params->get('forced_itemid', 0);

		foreach ($itemsLoc as &$itemLoc) {
			$itemLoc->link = JRoute::_(FlexicontentHelperRoute::getItemRoute($itemLoc->itemslug, $itemLoc->catslug, $forced_itemid, $itemLoc));
		}

		return $itemsLoc;
	}



	public static function renderMapLocations($params, &$mapItems = null)
	{
		$uselink = $params->get('uselink', '');
		$useadress = $params->get('useadress', '');

		$linkmode = $params->get('linkmode', '');
		$readmore = JText::_($params->get('readmore', 'MOD_FLEXIGOOGLEMAP_READMORE_TXT'));

		$usedirection = $params->get('usedirection', '');
		$directionname = JText::_($params->get('directionname', 'MOD_FLEXIGOOGLEMAP_DIRECTIONNAME_TXT'));

		$infotextmode = $params->get('infotextmode', '');
		$relitem_html = $params->get('relitem_html', '');

		$fieldaddressid = $params->get('fieldaddressid');
		$forced_itemid = $params->get('forced_itemid', 0);

		// Get address (maps) field
		$field = modFlexigooglemapHelper::_getField($fieldaddressid);

		// Get marker image configuratuon
		$use_custom_marker = (int) $params->get('use_custom_marker', 1) && (int) $field->parameters->get('use_custom_marker', 1);

		if ($use_custom_marker) {
			$custom_marker_path     = $field->parameters->get('custom_marker_path', 'modules/mod_flexigooglemap/assets/marker');
			$custom_marker_path_abs = JPATH::clean(JPATH_SITE . DS . $custom_marker_path . DS);
			$custom_marker_url_base = str_replace('\\', '/', JURI::root() . $custom_marker_path . '/');
		}

		$mapLocations = array();

		// Fixed category mode
		if ($params->get('catidmode') == 0) {
			$itemsLocations = modFlexigooglemapHelper::getItemsLocations($params);
			$itemsLocations = $itemsLocations ?: array();

			// Items having these markers, to be used by the module layout
			$mapItems = $itemsLocations;

			foreach ($itemsLocations as $itemLoc) {
				// Skip empty value
				if (empty($itemLoc->value)) {
					continue;
				}

				$coord = unserialize($itemLoc->value);
				$coord['lat'] = isset($coord['lat']) ? $coord['lat'] : '';
				$coord['lon'] = isset($coord['lon']) ? $coord['lon'] : '';

				// Skip address if it has empty coordinates, note latitude '0' and / or longitude '0' are not "empty", these are Equator's coordinates
				if (!strlen($coord['lat']) || !strlen($coord['lon'])) {
					continue;
				}

				$title = rtrim(addslashes($itemLoc->title));
				$link = '';
				$addr = '';
				$linkdirection = '';

				// Popup window: show (button) link to the item view
				if ($uselink) {
					$link = $itemLoc->link;
					$link = '<div class="link"><a href="' . $link . '" target="' . $linkmode . '" class="link btn">' . $readmore . '</a></div>';
					$link = addslashes($link);
				}

				// Popup window: show address details
				if ($useadress && !empty($coord['addr_display'])) {
					$addr = '<p>' . $coord['addr_display'] . '</p>';
					$addr = addslashes($addr);
					$addr = preg_replace("/(\r\n|\n|\r)/", " ", $addr);
				}

				// Popup window: show (button) link to Google-map directions page
				if ($usedirection) {
					// generate link to google maps directions
					$map_link = empty($coord['url'])  ?  false  :  $coord['url'];

					// if no url, compatibility with old values
					if (empty($map_link)) {
						$map_link = "http://maps.google.com/maps?q=";
						if (!empty($coord['addr1']) && !empty($coord['city']) && (!empty($coord['province']) || !empty($coord['state']))  && !empty($coord['zip'])) {
							$map_link .= urlencode(($coord['addr1'] ? $coord['addr1'] . ',' : '')
								. ($coord['city'] ? $coord['city'] . ',' : '')
								. ($coord['state'] ? $coord['state'] . ',' : ($coord['province'] ? $coord['province'] . ',' : ''))
								. ($coord['zip'] ? $coord['zip'] . ',' : '')
								. ($coord['country'] ? JText::_('PLG_FC_ADDRESSINT_CC_' . $coord['country']) : ''));
						} else {
							$map_link .= urlencode($coord['lat'] . "," . $coord['lon']);
						}
					}

					$linkdirection = '<div class="directions"><a href="' . $map_link . '" target="_blank" class="direction btn">' . $directionname . '</a></div>';
				}

				// Popup window: custom text or default (Address + button link to item)
				$contentwindows = $infotextmode
					? $relitem_html
					: $addr . ' ' . $link;

				// Get custom marker and its anchor (= marker's placement)
				$add_custom_marker = $use_custom_marker && !empty($coord['custom_marker']);

				$marker_anchor = $add_custom_marker && !empty($coord['marker_anchor'])
					? $coord['marker_anchor']
					: 'BotC';

				$marker_path = $add_custom_marker
					? $custom_marker_path_abs . $coord['custom_marker']
					: '';
				$marker_url  = $add_custom_marker
					? $custom_marker_url_base . $coord['custom_marker']
					: '';

				if ($marker_path) {
					// Marker Size
					list($wS, $hS) = getimagesize($marker_path);

					// Marker Anchor
					switch ($marker_anchor) {
						case 'TopL':
							$wA = 0;
							$hA = 0;
							break;
						case 'TopC':
							$wA = $wS / 2;
							$hA = 0;
							break;
						case 'TopR':
							$wA = $wS;
							$hA = 0;
							break;

						case 'MidL':
							$wA = 0;
							$hA = $hS / 2;
							break;
						case 'MidC':
							$wA = $wS / 2;
							$hA = $hS / 2;
							break;
						case 'MidR':
							$wA = $wS;
							$hA = $hS / 2;
							break;

						case 'BotL':
							$wA = 0;
							$hA = $hS;
							break;
						case 'BotC':
							$wA = $wS / 2;
							$hA = $hS;
							break;
						case 'BotR':
							$wA = $wS;
							$hA = $hS;
							break;
					}
				}

				$mapLocations[] = "[
					'<h4 class=\"fleximaptitle\">$title</h4>$contentwindows $linkdirection'," .
					$coord['lat'] . ", " .
					$coord['lon'] . ", " .
					(!$marker_url ? "'__default__'" :
						"'" . $marker_url . "', " .
						"'" . $wS . "', " .
						"'" . $hS . "', " .
						"'" . $wA . "', " .
						"'" . $hA . "'
					") . "
				]
				";
			}
		}

		// Current category mode or current item mode, these are pre-created (global variables)
		else {
			// Current category mode
			if ($params->get('catidmode') == 1) {
				// Get items of current view
				global $fc_list_items;
				if (empty($fc_list_items)) {
					$fc_list_items = array();
				}
			}

			// Get current item
			else {
				global $fc_view_item;
				$fc_list_items = !empty($fc_view_item)
					? array($fc_view_item)
					: array();
			}

			// Items having these markers, to be used by the module layout,
			// We will create one to one array below for locations and items (items are repeated if having multiple locations)
			$mapItems = array();

			foreach ($fc_list_items as $address_item) {
				// Skip item if it has no address value
				if (empty($address_item->fieldvalues[$fieldaddressid])) {
					continue;
				}

				// Get first value, typically this is value [0], and unserialize it
				foreach ($address_item->fieldvalues[$fieldaddressid] as $coord) {
					// Skip item if address field value is empty
					$coord = flexicontent_db::unserialize_array($coord, false, false);
					if (!$coord) continue;

					// Skip item if address field value has empty either of the coordinates
					if (!isset($coord['lat']) || !isset($coord['lon'])) continue;
					if (!strlen($coord['lat']) || !strlen($coord['lon'])) continue;

					/**
					 * Add item to array of know items
					 */
					$item = clone ($address_item);
					$item->link = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, $forced_itemid, $item));
					$mapItems[] = $item;

					$title = addslashes($item->title);
					$link = '';
					$addr = '';
					$linkdirection = '';

					// Popup window: show (button) link to the item view
					if ($uselink) {
						$link = $item->link;
						$link = '<div class="link"><a href="' . $link . '" target="' . $linkmode . '" class="link btn">' . $readmore . '</a></div>';
						$link = addslashes($link);
					}

					// Popup window: show address details
					if ($useadress && !empty($coord['addr_display'])) {
						$addr = '<p>' . $coord['addr_display'] . '</p>';
						$addr = addslashes($addr);
						$addr = preg_replace("/(\r\n|\n|\r)/", " ", $addr);
					}

					// Popup window: show (button) link to Google-map directions page
					if ($usedirection) {
						// Generate link to google maps directions
						$map_link = empty($coord['url'])  ?  false  :  $coord['url'];

						// if no url, compatibility with old values
						if (empty($map_link)) {
							$map_link = "http://maps.google.com/maps?q=";
							if (!empty($coord['addr1']) && !empty($coord['city']) && (!empty($coord['province']) || !empty($coord['state']))  && !empty($coord['zip'])) {
								$map_link .= urlencode(($coord['addr1'] ? $coord['addr1'] . ',' : '')
									. ($coord['city'] ? $coord['city'] . ',' : '')
									. ($coord['state'] ? $coord['state'] . ',' : ($coord['province'] ? $coord['province'] . ',' : ''))
									. ($coord['zip'] ? $coord['zip'] . ',' : '')
									. ($coord['country'] ? JText::_('PLG_FC_ADDRESSINT_CC_' . $coord['country']) : ''));
							} else {
								$map_link .= urlencode($coord['lat'] . "," . $coord['lon']);
							}
						}

						$linkdirection = '<div class="directions"><a href="' . $map_link . '" target="_blank" class="direction btn">' . $directionname . '</a></div>';
					}

					// Popup window: custom text or default (Address + button link to item)
					$contentwindows = $infotextmode
						? $relitem_html
						: $addr . ' ' . $link;

					// Get custom marker and its anchor (= marker's placement)
					$add_custom_marker = $use_custom_marker && !empty($coord['custom_marker']);

					$marker_anchor = $add_custom_marker && !empty($coord['marker_anchor'])
						? $coord['marker_anchor']
						: 'BotC';

					$marker_path = $add_custom_marker
						? $custom_marker_path_abs . $coord['custom_marker']
						: '';
					$marker_url  = $add_custom_marker
						? $custom_marker_url_base . $coord['custom_marker']
						: '';

					if ($marker_path) {
						// Marker Size
						list($wS, $hS) = getimagesize($marker_path);

						// Marker Anchor
						switch ($marker_anchor) {
							case 'TopL':
								$wA = 0;
								$hA = 0;
								break;
							case 'TopC':
								$wA = $wS / 2;
								$hA = 0;
								break;
							case 'TopR':
								$wA = $wS;
								$hA = 0;
								break;

							case 'MidL':
								$wA = 0;
								$hA = $hS / 2;
								break;
							case 'MidC':
								$wA = $wS / 2;
								$hA = $hS / 2;
								break;
							case 'MidR':
								$wA = $wS;
								$hA = $hS / 2;
								break;

							case 'BotL':
								$wA = 0;
								$hA = $hS;
								break;
							case 'BotC':
								$wA = $wS / 2;
								$hA = $hS;
								break;
							case 'BotR':
								$wA = $wS;
								$hA = $hS;
								break;
						}
					}

					$mapLocations[] = "[
						'<h4 class=\"fleximaptitle\">$title</h4>$contentwindows $linkdirection'," .
						$coord['lat'] . ", " .
						$coord['lon'] . ", " .
						(!$marker_url ? "'__default__'" :
							"'" . $marker_url . "', " .
							"'" . $wS . "', " .
							"'" . $hS . "', " .
							"'" . $wA . "', " .
							"'" . $hA . "'
						") . "
					]
					";
				}
			}
		}

		return $mapLocations;
	}


	/**
	 * Get a default Marker icon URL for map locations that do not specify a specific marker icon
	 */
	public static function getDefaultMarkerURL($params, &$wS = 0, &$hS = 0, &$wA = 0, &$hA = 0)
	{
		// Get marker mode, 'lettermarkermode' was old parameter name, (in future wew may more more modes, so the old parameter name was renamed)
		$markermode  = (int) $params->get('markermode', $params->get('lettermarkermode', 0));
		$markerimage = $params->get('markerimage', '');

		// Fall back to default marker icon if custom image not set
		if ($markermode === 1 && !$markerimage) {
			$markermode = -1;
		}

		$defautmarker_path = '';

		switch ($markermode) {
				// 'Letter' mode
			case 1:
				$color_to_file = array(
					'red'   => 'spotlight-waypoint-b.png',
					'green' => 'spotlight-waypoint-a.png',
					''      => 'spotlight-waypoint-b.png' /* '' is for not set*/
				);

				$defautmarker_url = "https://mts.googleapis.com/vt/icon/name=icons/spotlight/"
					. $color_to_file[$params->get('markercolor', '')]
					. "?text=" . $params->get('lettermarker')
					. "&psize=16&font=fonts/arialuni_t.ttf&color=ff330000&scale=1&ax=44&ay=48";
				break;

				// 'Local image file' mode
			case 0:
				$defautmarker_path = JPATH_SITE . '/' . $markerimage;
				$defautmarker_url  = JUri::root(true) . '/' . $markerimage;
				break;

				// Default marker icon
			case -1:
			default:
				// AVOID changing default, getimagesize on the URL may slow down page execution, giving false sense of slower PHP execution
				$defautmarker_url = null;
				/*$defautmarker_url = $params->get('mapapi', 'googlemap') === 'googlemap'
					? 'https://maps.gstatic.com/mapfiles/api-3/images/spotlight-poi2.png'
					: 'https://unpkg.com/leaflet@1.5.1/dist/images/marker-icon.png';*/
				break;
		}

		/**
		 * Calculate Default Marker Size and placement ! Because this is maybe a URL we will use caching !
		 * in order to avoid delay during PHP execution, which may falsely be considered slow ...
		 */

		if ($defautmarker_url && $params->get('mapapi', 'googlemap') !== 'googlemap') {
			$start_microtime = microtime(true);
			if (FLEXI_CACHE) {
				$cache = JFactory::getCache('com_flexicontent');
				$cache->setCaching(1);                  // Force cache ON
				$cache->setLifeTime(FLEXI_CACHE_TIME);  // Set expire time (default is 1 hour)
				list($wS, $hS) = $cache->get('getimagesize', array($defautmarker_path ?: $defautmarker_url));
			} else {
				list($wS, $hS) = getimagesize($defautmarker_path ?: $defautmarker_url);
			}

			// Marker Anchor
			$wA = $wS / 2;
			$hA = $hS;

			//$time_passed = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			//JFactory::getApplication()->enqueueMessage( "recalculated default marker url dimensions AND placement: ". sprintf('%.2f s', $time_passed/1000000), 'message');
		}

		return $defautmarker_url;
	}


	/**
	 * Get the given field by its ID
	 */
	private static function _getField($fieldid)
	{
		static $fields = array();

		// Return already loaded field
		if (isset($fields[$fieldid])) {
			return $fields[$fieldid];
		}

		// Load field
		$_fields = FlexicontentFields::getFieldsByIds(array($fieldid), false);
		$field   = !empty($_fields[$fieldid]) ? $_fields[$fieldid] : false;

		// Parse field parameters
		if ($field) {
			$field->parameters = new JRegistry($field->attribs);
		}

		// Cache and return the field
		return $fields[$fieldid] = $field;
	}
}
