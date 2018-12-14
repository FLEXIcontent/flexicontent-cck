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

// no direct access
defined('_JEXEC') or die('Restricted access');

class modFlexigooglemapHelper
{
	public static function getItemsLocations(&$params)
	{
		$fieldaddressid = $params->get('fieldaddressid');
		if ( empty($fieldaddressid) )
		{
			echo '<div class="alert alert-warning">' . JText::_('MOD_FLEXIGOOGLEMAP_ADDRESSFORGOT') .'</div>';
			return null;
		}

		// By default include children categories
		$treeinclude = $params->get('treeinclude', 1);

		// Make sure categories is an array
		$catids = $params->get('catid');
		$catids = is_array($catids) ? $catids : array($catids);

		// Retrieve extra categories, such children or parent categories
		$catids_arr = flexicontent_cats::getExtraCats($catids, $treeinclude, array());

		// Check if zero allowed categories
		if (empty($catids_arr))
		{
			return array();
		}

		$count = $params->get('count');
		$forced_itemid = $params->get('forced_itemid', 0);

		// Include : 1 or Exclude : 0 categories
		$method_category = $params->get('method_category', '1');

		$catWheres = $method_category == 0
			? ' rel.catid IN (' . implode(',', $catids_arr) . ')'
			: ' rel.catid NOT IN (' . implode(',', $catids_arr) . ')';

		$db = JFactory::getDbo();
		$queryLoc = 'SELECT a.id, a.title, b.field_id, b.value , a.catid '
			.' FROM #__content  AS a'
			.' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id '
			.' LEFT JOIN #__flexicontent_fields_item_relations AS b ON a.id = b.item_id '
			.' WHERE b.field_id = ' . $fieldaddressid.' AND ' . $catWheres . '  AND state = 1'
			.' ORDER BY title '.$count
			;
		$db->setQuery( $queryLoc );
		$itemsLoc = $db->loadObjectList();

		foreach ($itemsLoc as &$itemLoc)
		{
			$itemLoc->link = JRoute::_(FlexicontentHelperRoute::getItemRoute($itemLoc->id, $itemLoc->catid, $forced_itemid, $itemLoc));
		}

		return $itemsLoc;
	}


	public static function renderMapLocations($params)
	{
		$uselink = $params->get('uselink', '');
		$useadress = $params->get('useadress', '');

		$linkmode = $params->get('linkmode', '');
		$readmore = JText::_($params->get('readmore', 'MOD_FLEXIGOOGLEMAP_READMORE_TXT'));

		$usedirection = $params->get('usedirection', '');
		$directionname = JText::_($params->get('directionname', 'MOD_FLEXIGOOGLEMAP_DIRECTIONNAME_TXT'));

		$infotextmode = $params->get('infotextmode', '');
		$relitem_html = $params->get('relitem_html','');

		$fieldaddressid = $params->get('fieldaddressid');
		$forced_itemid = $params->get('forced_itemid', 0);

		$mapLocations = array();

		// Fixed category mode
		if ($params->get('catidmode') == 0)
		{
			$itemsLocations = modFlexigooglemapHelper::getItemsLocations($params);
			foreach ($itemsLocations as $itemLoc)
			{
				if ( empty($itemLoc->value) ) continue;   // skip empty value

				$coord = unserialize($itemLoc->value);
				if ( !isset($coord['lat']) || !isset($coord['lon']) ) continue;    // skip empty value

				$title = rtrim( addslashes($itemLoc->title) );
				$link = '';
				$addr = '';
				$linkdirection = '';

				if ($uselink)
				{
					$link = $itemLoc->link;
					$link = '<p class="link"><a href="'.$link.'" target="'.$linkmode.'">' . $readmore . '</a></p>';
					$link = addslashes($link);
				}

				if ($useadress && !empty($coord['addr_display']))
				{
					$addr = '<p>'.$coord['addr_display'].'</p>';
					$addr = addslashes($addr);
					$addr = preg_replace("/(\r\n|\n|\r)/", " ", $addr);
				}

				if ($usedirection)
				{
					// generate link to google maps directions
					$map_link = empty($coord['url'])  ?  false  :  $coord['url'];

					// if no url, compatibility with old values
					if (empty($map_link))
					{
						$map_link = "http://maps.google.com/maps?q=";
						if (!empty($coord['addr1']) && !empty($coord['city']) && (!empty($coord['province']) || !empty($coord['state']))  && !empty($coord['zip']))
						{
							$map_link .= urlencode(($coord['addr1'] ? $coord['addr1'].',' : '')
								.($coord['city'] ? $coord['city'].',' : '')
								.($coord['state'] ? $coord['state'].',' : ($coord['province'] ? $coord['province'].',' : ''))
								.($coord['zip'] ? $coord['zip'].',' : '')
								.($coord['country'] ? JText::_('PLG_FC_ADDRESSINT_CC_'.$coord['country']) : ''));
						}
						else
						{
							$map_link .= urlencode($coord['lat'] . "," . $coord['lon']); 
						}
					}

					$linkdirection= '<div class="directions"><a href="' . $map_link . '" target="_blank" class="direction">' . $directionname . '</a></div>';
				}

				$contentwindows = $infotextmode
					? $relitem_html
					: $addr . ' ' . $link;

				$coordinates = $coord['lat'] .','. $coord['lon'];
				$mapLocations[] = "['<h4 class=\"fleximaptitle\">$title</h4>$contentwindows $linkdirection'," . $coordinates . "]\r\n";
			}
		}

		// Current category mode or current item mode, these are pre-created (global variables)
		else
		{
			// Current category mode
			if ($params->get('catidmode') == 1)
			{
				// Get items of current view
				global $fc_list_items;
				if ( empty($fc_list_items) )
				{
					$fc_list_items = array();
				}
			}

			// Get current item
			else
			{
				global $fc_view_item;
				$fc_list_items = !empty($fc_view_item)
					? array($fc_view_item)
					: array();
			}

			foreach ($fc_list_items as $address)
			{
				// Skip item if it has no address value
				if ( empty($address->fieldvalues[$fieldaddressid]) )
				{
					continue;
				}

				// Get first value, typically this is value [0], and unserialize it
				foreach($address->fieldvalues[$fieldaddressid] as $coord)
				{
					$coord = flexicontent_db::unserialize_array($coord, false, false);
					if (!$coord) continue;

					// Skip value that has no cordinates
					if ( !isset($coord['lat']) || !isset($coord['lon']) ) continue;
					if ( !strlen($coord['lat']) || !strlen($coord['lon']) ) continue;

					$title = addslashes($address->title);
					$link = '';
					$addr = '';
					$linkdirection = '';

					if ($uselink)
					{
						$link = JRoute::_(FlexicontentHelperRoute::getItemRoute($address->id, $address->catid, $forced_itemid, $address));
						$link = '<p class="link"><a href="'.$link.'" target="'.$linkmode.'">' . $readmore . '</a></p>';
						$link = addslashes($link);
					}

					if ($useadress && !empty($coord['addr_display']))
					{
						$addr = '<p>'.$coord['addr_display'].'</p>';
						$addr = addslashes($addr);
						$addr = preg_replace("/(\r\n|\n|\r)/", " ", $addr);
					}

					if ($usedirection)
					{
						// generate link to google maps directions
						$map_link = empty($coord['url'])  ?  false  :  $coord['url'];

						// if no url, compatibility with old values
						if (empty($map_link))
						{
							$map_link = "http://maps.google.com/maps?q=";
							if (!empty($coord['addr1']) && !empty($coord['city']) && (!empty($coord['province']) || !empty($coord['state']))  && !empty($coord['zip']))
							{
								$map_link .= urlencode(($coord['addr1'] ? $coord['addr1'].',' : '')
									.($coord['city'] ? $coord['city'].',' : '')
									.($coord['state'] ? $coord['state'].',' : ($coord['province'] ? $coord['province'].',' : ''))
									.($coord['zip'] ? $coord['zip'].',' : '')
									.($coord['country'] ? JText::_('PLG_FC_ADDRESSINT_CC_'.$coord['country']) : ''));
							}
							else
							{
								$map_link .= urlencode($coord['lat'] . "," . $coord['lon']); 
							}
						}

						$linkdirection= '<div class="directions"><a href="' . $map_link . '" target="_blank" class="direction">' . $directionname . '</a></div>';
					}

					$contentwindows = $infotextmode
						? $relitem_html
						: $addr . ' ' . $link;

					$coordinates = $coord['lat'] .','. $coord['lon'];
					$mapLocations[] = "['<h4 class=\"fleximaptitle\">$title</h4>$contentwindows $linkdirection'," . $coordinates . "]\r\n";
				}
			}
		}

		return $mapLocations;
	}


	public static function getMarkerURL(&$params)
	{
		// Get marker mode, 'lettermarkermode' was old parameter name, (in future wew may more more modes, so the old parameter name was renamed)
		$markermode = $params->get('markermode', $params->get('lettermarkermode', 0));

		switch ($markermode)
		{
			// 'Letter' mode
			case 1:
				$color_to_file = array(
					'red'   => 'spotlight-waypoint-b.png',
					'green' => 'spotlight-waypoint-a.png',
					''      => 'spotlight-waypoint-b.png' /* '' is for not set*/
				);

				return "'https://mts.googleapis.com/vt/icon/name=icons/spotlight/"
					. $color_to_file[$params->get('markercolor', '')]
					. "?text=" . $params->get('lettermarker')
					. "&psize=16&font=fonts/arialuni_t.ttf&color=ff330000&scale=1&ax=44&ay=48"
					. "'";

			// 'Local image file' mode or empty
			case 0:
			default:
				$markerimage = $params->get('markerimage');
				return $markerimage ? ("'" . JUri::root(true) . '/' . $markerimage . "'") : null;
		}
	}

}