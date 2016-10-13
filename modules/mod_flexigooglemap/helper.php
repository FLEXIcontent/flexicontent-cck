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

//blocage des accés directs sur ce script
defined('_JEXEC') or die('Restricted access');

require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');

require_once (JPATH_SITE.DS."components".DS."com_flexicontent".DS."classes".DS."flexicontent.fields.php");
require_once (JPATH_SITE.DS."components".DS."com_flexicontent".DS."classes".DS."flexicontent.helper.php");
require_once (JPATH_SITE.DS."components".DS."com_flexicontent".DS."helpers".DS."permission.php");

require_once (JPATH_SITE.DS."components".DS."com_flexicontent".DS."models".DS.FLEXI_ITEMVIEW.".php");


class modFlexigooglemapHelper
{
	public static function getLoc(&$params)
	{
		$fieldaddressid = $params->get('fieldaddressid');
		if ( empty($fieldaddressid) )
		{
			echo '<div class="alert alert-warning">' . JText::_('FLEXI_GOOGLEMAP_ADDRESSFORGOT') .'</div>';
			return null;
		}

		global $globalcats;
		$catid = $params->get('catid');
		$catlist = !empty($globalcats[$catid]->descendants) ? $globalcats[$catid]->descendants : $catid;

		$count = $params->get('count');
		$forced_itemid = $params->get('forced_itemid','');

		$db = JFactory::getDbo();
		$queryLoc = 'SELECT a.id, a.title, b.field_id, b.value , a.catid '
			.' FROM #__content  AS a'
			.' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id '
			.' LEFT JOIN #__flexicontent_fields_item_relations AS b ON a.id = b.item_id '
			.' WHERE b.field_id = '.$fieldaddressid.' AND rel.catid IN ('.$catlist.') AND state = 1'
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


	public static function getMarkercolor(&$params)
	{
		$markerimage = $params->get('markerimage');
		$markercolor = $params->get('markercolor');
		$lettermarker = $params->get('lettermarker');

		$lettermarkermode = $params->get('lettermarkermode', 0);  // compatibility with old parameter
		$markermode = $params->get('markermode', $lettermarkermode);
		
		if ($markermode==1)
		{
			$letter = "&text=".$lettermarker."&psize=16&font=fonts/arialuni_t.ttf&color=ff330000&scale=1&ax=44&ay=48";
			switch ($markercolor)
			{
				case "red":
					$color ="spotlight-waypoint-b.png";
					break;
				case "green":
					$color ="spotlight-waypoint-a.png";
					break;
				default :
					$color ="spotlight-waypoint-b.png";
					break;
			}

			$icon = "'https://mts.googleapis.com/vt/icon/name=icons/spotlight/" . $color . $letter . "'";	
		}
		else
		{
			$icon = $markerimage ? "'" . JURI::base() . $markerimage . "'" : "''";
		}

		return $icon;
	}
}
