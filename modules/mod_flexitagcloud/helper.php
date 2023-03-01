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

class modFlexiTagCloudHelper
{
	static function getTags(&$params, &$module)
	{
		// Initialize
		$app  = JFactory::getApplication();
		$db   = JFactory::getDbo();
		$user = JFactory::getUser();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );

		//$now    = FLEXI_J16GE ? JFactory::getDate()->toSql() : JFactory::getDate()->toMySQL();
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate	= $db->getNullDate();
		$show_noauth = $cparams->get('show_noauth', 0);

		// Get parameters
		$minsize 	= (int)$params->get('min_size', '1');
		$maxsize 	= (int)$params->get('max_size', '10');
		$limit 		= (int)$params->get('count', '25');
		$method		= (int)$params->get('method', '1');   // Category method
		$method_types = (int)$params->get('method_types', '1');  // (current) Type method
		$cids = $params->get('categories');
		$cids = is_array($cids)
			? $cids
			: ((int) $cids ? array((int) $cids) : array());
		$treeinclude 		= $params->get('treeinclude');

		$tagitemid	= (int)$params->get('force_itemid', 0);

		$where = ' WHERE 1 '
			. ' AND i.state IN ( 1, -5 )'
			. ' AND ( i.publish_up IS NULL OR i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' )'
			. ' AND ( i.publish_down IS NULL OR i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' )'
			. ' AND c.published = 1'
			. ' AND tag.published = 1';

		// access scope
		if (!$show_noauth)
		{
			$aid_arr  = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$where  .= ' AND i.access IN ('.$aid_list.')';
		}


		// ***
		// *** Check if loading tags according to current view
		// ***

		if ($method === 1 || $method_types === 1)
		{
			$option  = $app->input->get('option', '', 'cmd');
			$view    = $app->input->get('view', '', 'cmd');

			$id   = $app->input->get('id', 0, 'int');   // id of current item
			$cid  = $app->input->get(($option == 'com_content' ? 'id' : 'cid'), 0, 'int');   // current category ID or category ID of current item
			$rootcatid = $app->input->get('rootcatid', 0, 'int');   // root category ID for directory view

			$is_content_ext   = $option == 'com_flexicontent' || $option == 'com_content';
			$isflexi_itemview = $is_content_ext && ($view == 'item' || $view == 'article') && $id;
			$isflexi_catview  = $is_content_ext && $view == 'category' && $cid;
			$isflexi_dirview  = $view == 'flexicontent' && $rootcatid;
		}


		// ***
		// *** Category scope
		// ***

		if ($method === 1)
		{
			// Find current category
			if ($isflexi_itemview)
			{
				$query = 'SELECT catid FROM #__content WHERE id = ' . $id;
				$db->setQuery($query);
				$cid = $db->loadResult();
				$cids = $cid ? array($cid) : array(0);
			}

			elseif($isflexi_catview)
			{
				$cids = array($cid);
			}

			elseif($isflexi_dirview)
			{
				$cids = array($rootcatid);
			}

			else
			{
				$cids = array();
			}
		}

		// Retrieve extra categories, such children or parent categories
		$cids = flexicontent_cats::getExtraCats($cids, $treeinclude, array(0));
		$cids = empty($cids)
			? array(0)
			: $cids;

		// EXCLUDE method
		if ($method == 2)
		{
			$where .= ' AND c.id NOT IN (' . implode(',', $cids) . ')';
		}

		// INCLUDE method (specified categories or current category)
		elseif ($method == 3 || $method == 1)
		{
			$where .= ' AND c.id IN (' . implode(',', $cids) . ')';
		}


		// ***
		// *** (current) content type scope
		// ***

		$typeid = 0;
		if ($method_types === 1 && $isflexi_itemview)
		{
			$query = 'SELECT type_id FROM #__flexicontent_items_ext WHERE item_id = ' . $id;
			$db->setQuery($query);
			$typeid = $db->loadResult();

			if ($typeid)
			{
				$where .= ' AND i.type_id = ' . $typeid;
			}
		}


		// ***
		// *** Get matching tags and their usage counts
		// ***

		$result = array();

		$query = 'SELECT tag.id, COUNT( rel.tid ) AS no'
			. ' FROM #__flexicontent_tags_item_relations AS rel'
			. ' JOIN #__flexicontent_items_tmp AS i ON i.id = rel.itemid'
			. ' JOIN #__categories AS c ON c.id = i.catid'  // to check publication state and limit to specific cats
			. ' JOIN #__flexicontent_tags as tag ON tag.id = rel.tid'  // to check publication state
			. $where
			. ' GROUP BY rel.tid'
			. ' ORDER BY no DESC'
			;
		$tag_counts = $db->setQuery($query, 0, $limit)->loadObjectList('id');

		// Did any tags match our criteria ?
		if (!$tag_counts)
		{
			return $tag_counts;
		}


		// ***
		// *** Find out max & min usage count of tags
		// ***

		$max_no = reset($tag_counts);
		$min_no = end($tag_counts);
		$max = (int) $max_no->no;
		$min = (int) $min_no->no;


		// ***
		// *** Get tag data
		// ***

		$query = $db->getQuery(true)
			->select('tag.id AS _id, jt.id, jt.title, jt.description')
			->select('CASE WHEN CHAR_LENGTH(tag.alias) THEN CONCAT_WS(\':\', tag.id, tag.alias) ELSE tag.id END as slug')
			->from('#__flexicontent_tags AS tag')
			->leftjoin('#__tags AS jt ON jt.id = tag.jtag_id')
			->where('tag.id IN (' . implode(', ', array_keys($tag_counts)) . ')')
			;
		$rows = $db->setQuery($query)->loadObjectList('_id');

		// Add tag counts, calculated above
		foreach($rows as $row)
		{
			$row->id   = $row->_id;
			$row->no   = $tag_counts[$row->id]->no;

			unset($row->_id);
		}


		// ***
		// *** Create the tag links and other tag information
		// ***

		$use_catlinks = $cparams->get('tags_using_catview', 0);
		$i = 0;
		$lists	= array();
		foreach ($rows as $row)
		{
			$lists[$i] = new stdClass();

			$lists[$i]->size = modFlexiTagCloudHelper::sizer($min, $max, $row->no, $minsize, $maxsize);
			$lists[$i]->name = ($row->title ?: $row->name); // . ' ['. $row->no.']';
			$lists[$i]->assignments_count   = $row->no;

			// To sort inside the layout use:
			//usort($list, fn($a, $b) => ((int)$b->assignments_count > (int)$a->assignments_count));

			$lists[$i]->description = $row->description;
			$lists[$i]->screenreader = JText::sprintf('FLEXI_NR_ITEMS_TAGGED', $row->no);

			$lists[$i]->link = $use_catlinks
				? FlexicontentHelperRoute::getCategoryRoute(0, $tagitemid, array('layout'=>'tags','tagid'=>$row->slug)) . ($typeid ? '&filter_8=' . $typeid : '')
				: FlexicontentHelperRoute::getTagRoute($row->slug, $tagitemid) . '&module='.$module->id;
			$lists[$i]->link = JRoute::_( $lists[$i]->link );

			$i++;
		}

		return $lists;
	}


	/**
	 * sort the tags between a range from 1 to 10 according their usage
	 */
	static function sizer($min, $max, $no, $minsize, $maxsize)
	{
		$spread = $max - $min;
		$spread = $spread === 0 ? 1 : $spread;

		$step = ($maxsize - $minsize) / $spread;

		$size = $minsize + (($no - $min) * $step);
		$size = ceil($size);

		return $size;
	}
}