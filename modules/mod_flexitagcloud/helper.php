<?php
/**
 * @version 1.1 $Id: helper.php 1762 2013-09-14 16:42:09Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent Tag Cloud Module
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

class modFlexiTagCloudHelper
{
	static function getTags(&$params, &$module)
	{
		// Initialize
		$app = JFactory::getApplication();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$db				= JFactory::getDBO();
		$user			= JFactory::getUser();
		
		//$now    = FLEXI_J16GE ? JFactory::getDate()->toSql() : JFactory::getDate()->toMySQL();
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate	= $db->getNullDate();
		$show_noauth = $cparams->get('show_noauth', 0);

		// Get parameters
		$minsize 	= (int)$params->get('min_size', '1');
		$maxsize 	= (int)$params->get('max_size', '10');
		$limit 		= (int)$params->get('count', '25');
		$method		= (int)$params->get('method', '1');
		$scope		= $params->get('categories');
		$scope		= is_array($scope) ? implode(',', $scope) : $scope;
		$tagitemid	= (int)$params->get('force_itemid', 0);

		$where 	= !FLEXI_J16GE ? ' WHERE i.sectionid = ' . FLEXI_SECTION : ' WHERE 1 ';
		$where .= ' AND i.state IN ( 1, -5 )';
		$where .= ' AND ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' )';
		$where .= ' AND ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' )';
		$where .= ' AND c.published = 1';
		$where .= ' AND tag.published = 1';

		// filter by permissions
		if (!$show_noauth) {
			$aid_arr  = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$where  .= ' AND i.access IN ('.$aid_list.')';
		}

		// category scope
		if ($method == 2) { // include method
			$where .= ' AND c.id NOT IN (' . $scope . ')';		
		} else if ($method == 3) { // exclude method
			$where .= ' AND c.id IN (' . $scope . ')';		
		}

		// count Tags
		$result = array();
		
		$query = 'SELECT COUNT( t.tid ) AS no'
				. ' FROM #__flexicontent_tags_item_relations AS t'
				. ' LEFT JOIN #__content AS i ON i.id = t.itemid'
				. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
				. ' LEFT JOIN #__flexicontent_tags as tag ON tag.id = t.tid'
				. $where
				. ' GROUP BY t.tid'
				. ' ORDER BY no DESC'
				;

		$db->setQuery($query, 0, $limit);
		$result = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();

		//Do we have any tags?
		if (!$result) {
			return $result;
		}
		
		$max = (int)$result[0];
		$min = (int)$result[sizeof($result)-1];
		
		$query = 'SELECT tag.id, tag.name, count( rel.tid ) AS no,'
				. ' CASE WHEN CHAR_LENGTH(tag.alias) THEN CONCAT_WS(\':\', tag.id, tag.alias) ELSE tag.id END as slug'
				. ' FROM #__flexicontent_tags AS tag'
				. ' LEFT JOIN #__flexicontent_tags_item_relations AS rel ON rel.tid = tag.id'
				. ' LEFT JOIN #__content AS i ON i.id = rel.itemid'
				. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
				. $where
				. ' GROUP BY tag.id'
				. ' HAVING no >= '. $min
				. ' ORDER BY tag.name'
				;
		
		$db->setQuery($query, 0, $limit);
		$rows = $db->loadObjectList();
		
		$use_catlinks = $cparams->get('tags_using_catview', 0);
		$i		= 0;
		$lists	= array();
		foreach ( $rows as $row )
		{
			$lists[$i] = new stdClass();
			$lists[$i]->size 			= modFlexiTagCloudHelper::sizer($min, $max, $row->no, $minsize, $maxsize);
			$lists[$i]->name 			= $row->name;
			$lists[$i]->screenreader	= JText::sprintf('FLEXI_NR_ITEMS_TAGGED', $row->no);

			$lists[$i]->link = $use_catlinks ?
				FlexicontentHelperRoute::getCategoryRoute(0, $tagitemid, array('layout'=>'tags','tagid'=>$row->slug)) :
				FlexicontentHelperRoute::getTagRoute($row->slug, $tagitemid) . '&module='.$module->id ;
			$lists[$i]->link = JRoute::_( $lists[$i]->link );

			$i++;
		}

		return $lists;
	}
	
	/**
	 * sort the tags between a range from 1 to 10 according their use
	 */
	static function sizer($min, $max, $no, $minsize, $maxsize)
	{		
		$spread = $max - $min;
		if (0 == $spread) {
	   		$spread = 1;
		}

		$step = ($maxsize - $minsize) / $spread;

    	$size = $minsize + (($no - $min) * $step);
		$size = ceil($size);
		
		return $size;
	}
}
?>