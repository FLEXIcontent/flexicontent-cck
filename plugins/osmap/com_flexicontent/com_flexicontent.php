<?php
/**
 * @version 2.5.0 $Id: com_flexicontent.php $
 * @package Joomla
 * @subpackage Osmap Plugin for FLEXIcontent Component
 * @copyright (C) 2013 FLEXIcontent Team
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_osmap'.DS.'helpers'.DS.'osmap.php');

class osmap_com_flexicontent
{
	/*
	 * Base entry
	 */
	static function getTree($osmap, $parent, $params)
	{
		// A quick filter to find if given links has tree to be expanded
		if (!strpos($parent->link, 'view=category') && !strpos($parent->link, 'view=tags') && !strpos($parent->link, 'view=item'))
		{
			// DO NOT have tree, or we do not want to expand further,
			// e.g. we do not want to expand 'favourites' view or 'search' view
			return;
		}

		$link_query = parse_url( $parent->link );
		parse_str( html_entity_decode( $link_query['query']), $link_vars );
		$catid  = ArrayHelper::getValue($link_vars, 'cid', 0, 'INT');
		$id     = ArrayHelper::getValue($link_vars, 'id', 0, 'INT');
		$layout = ArrayHelper::getValue($link_vars, 'layout', '', 'STRING');
		$view   = ArrayHelper::getValue($link_vars, 'view', '', 'STRING');
		$tid    = $id;


		// *************************
		// Initialize item inclusion
		// *************************

		$include_items = ArrayHelper::getValue($params, 'include_items', 1, 'INT');
		$include_items = ( $include_items === 1
			|| ( $include_items === 2 && $osmap->view === 'xml')
			|| ( $include_items === 3 && $osmap->view === 'html'));
		$params['include_items'] = $include_items;

    //----- Set include_items_maincatonly param
		$include_items_maincatonly = ArrayHelper::getValue($params, 'include_items_maincatonly', 0, 'INT');
		$include_items_maincatonly = $include_items_maincatonly === 1
			|| ( $include_items_maincatonly === 2 && $osmap->view === 'xml')
			|| ( $include_items_maincatonly === 3 && $osmap->view === 'html');
		$params['include_items_maincatonly'] = $include_items_maincatonly;

    //----- Set expand_cats param
		$expand_cats = ArrayHelper::getValue($params, 'expand_cats', 0, 'INT');
		$expand_cats = $expand_cats === 1
			|| ( $expand_cats === 2 && $osmap->view === 'xml')
			|| ( $expand_cats === 3 && $osmap->view === 'html');
		$params['expand_cats'] = $expand_cats;

    //----- Set expand_authors param
		$expand_authors = ArrayHelper::getValue($params, 'expand_authors', 0, 'INT');
		$expand_authors = $expand_authors === 1
			|| ( $expand_authors === 2 && $osmap->view === 'xml')
			|| ( $expand_authors === 3 && $osmap->view === 'html');
		$params['expand_authors'] = $expand_authors;

    //----- Set expand_tags param
		$expand_tags = ArrayHelper::getValue($params, 'expand_tags', 0, 'INT');
		$expand_tags = ( $expand_tags === 1
						|| ( $expand_tags === 2 && $osmap->view == 'xml')
						|| ( $expand_tags === 3 && $osmap->view == 'html'));
		$params['expand_tags'] = $expand_tags;

    //----- Set add non-authorized content inclusion param
		$show_noauth = ArrayHelper::getValue($params, 'show_noauth', '', 'STRING');

		// Get show unauthorized items from component
		if ($show_noauth === '')
		{
			$fparams = JComponentHelper::getParams('com_flexicontent');
			$show_noauth = (int) $fparams->get('show_noauth', 0);
		}

		// Decide show unauthorized items according to current OSMap view (xml / html)
		else
		{
			$show_noauth = (int) $show_noauth;
			$show_noauth = $show_noauth === 1
				|| ( $show_noauth === 2 && $osmap->view === 'xml')
				|| ( $show_noauth === 3 && $osmap->view === 'html');
		}

		$params['show_noauth'] = $show_noauth;

    //----- Set add add images param
    $add_images = 0; //ArrayHelper::getValue($params, 'add_images', 0, 'INT');
    $add_images = $add_images === 1 && $osmap->view === 'html';
    $params['add_images'] = $add_images;
    $params['max_images'] = ArrayHelper::getValue($params, 'max_images', 1000, 'INT');

    //----- Set add pagebreaks param
		$add_pagebreaks = ArrayHelper::getValue($params, 'add_pagebreaks', 0, 'INT');
		$add_pagebreaks = ( $add_pagebreaks == 1
						|| ( $add_pagebreaks == 2 && $osmap->view == 'xml')
            || ( $add_pagebreaks == 3 && $osmap->view == 'html')
            || $osmap->view == 'navigator');
		$params['add_pagebreaks'] = $add_pagebreaks;


		// *****************
		// Category settings
		// *****************

		$priority 	= ArrayHelper::getValue($params, 'cat_priority', $parent->priority, '', 'STRING');
		$changefreq = ArrayHelper::getValue($params, 'cat_changefreq', $parent->changefreq, '', 'STRING');
		if ($priority  == '-1')   $priority   = $parent->priority;
		if ($changefreq  == '-1') $changefreq = $parent->changefreq;
		$params['cat_priority']   = $priority;
		$params['cat_changefreq'] = $changefreq;


		// *************
		// Item settings
		// *************

		$priority 	= ArrayHelper::getValue($params, 'item_priority', $parent->priority, '', 'STRING');
		$changefreq = ArrayHelper::getValue($params, 'item_changefreq', $parent->changefreq, '', 'STRING');
		if ($priority  == '-1')   $priority   = $parent->priority;
		if ($changefreq  == '-1') $changefreq = $parent->changefreq;
		$params['item_priority']   = $priority;
		$params['item_changefreq'] = $changefreq;

		if ($include_items)
		{
			$params['limit'] 	= '';
			$params['days'] 	= '';

			// A total count limitation (not to include too many items)
			$limit = ArrayHelper::getValue($params, 'max_items', '', 'STRING');

			if (intval($limit))
			{
				$params['limit'] = ' LIMIT ' . (int) $limit;
			}

			// A max age limitation (not to include to old items)
			$days = ArrayHelper::getValue($params, 'max_age', '', 'STRING');

			if (intval($days))
			{
				$creation_date = date('Y-m-d H:m:s', ($osmap->now - ($days*86400)) );
				$params['days'] = " AND i.created >= '" . $creation_date . "'";
			}
		}


		// *********************
		// Get the Sub Tree Data
		// *********************

		// tag menu items
		if ($view === 'tags')
		{
			if ($params['include_items'] && $params['expand_tags'])
			{
				osmap_com_flexicontent::getFlexicontentTagTree($osmap, $parent, $params, $tid);
			}
		}

		// category menu items (various layouts)
		elseif ($view === 'category')
		{
			switch($layout)
			{
				case 'myitems':
					return;

				case 'tags':
					$tagid = ArrayHelper::getValue( $link_vars, 'tagid', 0, 'INT');

					if (!$tagid)
					{
						osmap_com_flexicontent::expandDefaultTagMI($osmap, $parent, $params, 0);
					}

					return;


				case 'author':
					$authorid = ArrayHelper::getValue( $link_vars, 'authorid', 0, 'INT');

					if ($authorid)
					{
						osmap_com_flexicontent::getFlexicontentCatTree($osmap, $parent, $params, $catid, $authorid);
					}

					return;

				case 'mcats':
					$cids = ArrayHelper::getValue($link_vars, 'cids', '', '');

					if (!is_array($cids))
					{
						$cids = preg_replace('/[^0-9,]/i', '', (string) $cids);
						$cids = explode(',', $cids);
					}

					$cids = ArrayHelper::toInteger($cids);

					foreach ($cids as $cid)
					{
						osmap_com_flexicontent::getFlexicontentCatTree($osmap, $parent, $params, $cid);
					}

					return;

				case '':
				default:
					osmap_com_flexicontent::getFlexicontentCatTree($osmap, $parent, $params, $catid);

					return;
			}
		}

		// OTHER unhandled, this should be unreachable
		elseif ($view === 'item')
		{
			$parent->expandible = false;

			// First thing we need to do is to select only the requested items
			// (ONLY include items that have current category as their main category)
			$where  = ' WHERE i.id='.intval($id);
			$items = self::getItems($where_basic = $where, $params, $extra_join = '', $extra_endwhere='');

			if (count($items))
			{
				$row = $items[0];
				$text = @ $row->introtext . @ $row->fulltext;

				if ($params['add_images'])
				{
					$parent->images = OSMapHelper::getImages($text, ArrayHelper::getValue($params, 'max_images', 1000, 'int'));
				}

				if ($params['add_pagebreaks'])
				{
					$parent->subnodes = OSMapHelper::getPagebreaks($text, $parent->link);
					$parent->expandible = (count($parent->subnodes) > 0); // This article has children
				}
			}

			if ($parent->expandible)
			{
				self::printNodes($osmap, $parent, $params, $parent->subnodes);
			}
		}

		// OTHER unhandled, this should be unreachable
		/*else
		{
		}*/
	}


	/*
	 * Get the Categories with with their items
	 */
	static function getFlexicontentCatTree($osmap, $parent, $params, $catid = 0, $authorid = 0)
	{
		// Terminate if no category AND no author were given
		if (!$catid && !$authorid)
		{
			return;
		}


		// *************************
		// Initialize some variables
		// *************************

		static $db, $user, $nullDate, $now, $ordering, $access_clauses;
		static $initialized = null;

		if ($initialized === null)
		{
			$initialized = true;
			$db    = JFactory::getDBO();
			$user  = JFactory::getUser();
			$date  = JFactory::getDate();
			$nullDate = $db->getNullDate();
			$now = 'UTC_TIMESTAMP()'; //$this->_db->Quote( $date->toMySQL() );
			$ordering = FLEXI_J16GE ? 'c.lft ASC' : 'c.ordering ASC';
			$access_clauses = self::getAccessClauses($params);
		}


		// ******************************************************************************
		// DO QUERY to get items Sub-Categories of given category (if not an author view)
		// ******************************************************************************

		$cats = null;

		if ($catid && !$authorid)
		{
			$query 	= 'SELECT c.id, c.title, c.parent_id, '
					. ' c.created_time, c.modified_time, '
					. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug'
					. ' FROM #__categories AS c'
					. $access_clauses->joinaccess_cat
					. ' WHERE c.published = 1'
					. ' AND c.parent_id = '. (int) $catid
					. $access_clauses->andaccess_cat
					. ' ORDER BY '.$ordering
					;
			$cats = $db->setQuery($query)->loadObjectList();
		}


		// ***************************************
		// ADD Found sub-categories to the SiteMap
		// ***************************************

		if (!empty($cats))
		{
			// Start including SUB-Categories, change level +1
			$osmap->changeLevel(1);

			foreach($cats as $cat)
			{
				$node = new stdclass();
				$node->id  = $parent->id;
				$node->uid = $parent->uid . 'c' . $cat->id;
				$node->pid = $cat->parent_id;
				$node->browserNav = $parent->browserNav;
				$node->priority   = $params['cat_priority'];
				$node->changefreq = $params['cat_changefreq'];
				$node->name       = $cat->title;
				$node->expandible = true;
				$node->secure     = $parent->secure;
				$node->newsItem   = 0;

				//$node->slug = $cat->path ? ($cat->id . ':' . $cat->path) : $cat->id;
				$node->slug   = $cat->slug;
				$node->link   = FlexicontentHelperRoute::getCategoryRoute($node->slug);
				$node->tree   = array();

				// For the google news we should use the publication date instead the last modification date
				$node->modified = ($osmap->isNews || !$cat->modified_time) ? $cat->created_time : $cat->modified_time;

				// Add category and then expand it
				if ($osmap->printNode($node))
				{
					osmap_com_flexicontent::getFlexicontentCatTree($osmap, $parent, $params, $cat->id);
				}
			}

			// Finish including SUB-Categories, change level -1
			$osmap->changeLevel(-1);
		}


		// ***************************************************************
		// DO QUERY to get items of current category and/or current author
		// ***************************************************************

		// Include Content (items) of current category if so configured
		if (!$params['include_items'])
		{
			return;
		}

		if (($authorid && !$params['expand_authors'])  || (!$authorid && !$params['expand_cats']))
		{
			return;
		}

		// First thing we need to do is to select only the requested items
		// (ONLY include items that have current category as their main category)
		$where_basic = ' WHERE i.state IN (1, -5) ';
		$extra_join  = '';
		$extra_endwhere = '';

		if ($catid)
		{
			$where_basic .= $params['include_items_maincatonly'] ? ' AND i.catid = ' . (int) $catid : ' AND rel.catid = ' . (int) $catid;
			$extra_join  .= $params['include_items_maincatonly'] ? '' : ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id';
		}

		if ($authorid)
		{
			$where_basic .= ' AND i.created_by = ' . (int) $authorid;
		}

		$items = self::getItems($where_basic, $params, $extra_join, $extra_endwhere);

		// Terminate if no items were found
		if ( empty($items) ) return;


		// ******************************
		// ADD Found Items to the SiteMap
		// ******************************
		self::addContentItems($osmap, $parent, $params, $items);
	}


	/*
	 * When tag is used a menu item, get the items tagged
	 */
	static function getFlexicontentTagTree($osmap, $parent, $params, $tid)
	{
		if ( !$params['include_items'] && !$params['expand_tags'] )  return;


		// **********************************
		// DO QUERY to get items of given tag
		// **********************************

		// First thing we need to do is to select only the requested items
		$where_basic = ' WHERE t.tid = ' . (int) $tid;
		$extra_join  = ' JOIN #__flexicontent_tags_item_relations AS t ON t.itemid = i.id';   // Join to get Limit to given tag
		$extra_endwhere = '';

		$items = self::getItems($where_basic, $params, $extra_join, $extra_endwhere);

		// Terminate if no items were found
		if ( empty($items) ) return;


		// ******************************
		// ADD Found Items to the SiteMap
		// ******************************

		self::addContentItems($osmap, $parent, $params, $items);
	}


	/*
	 * When tag is used a menu item, get the items tagged
	 */
	static function expandDefaultTagMI($osmap, $parent, $params, $tid)
	{
		//if (!$tid && !$params['expand_default_tag_mi'])  return;


		static $db, $user, $nullDate, $now, $ordering, $access_clauses;
		static $initialized = null;

		if ($initialized === null)
		{
			$initialized = true;
			$db    = JFactory::getDBO();
			$user  = JFactory::getUser();
			$date  = JFactory::getDate();
			$nullDate = $db->getNullDate();
			$now = 'UTC_TIMESTAMP()'; //$this->_db->Quote( $date->toMySQL() );
			$ordering = FLEXI_J16GE ? 'c.lft ASC' : 'c.ordering ASC';
			$access_clauses = self::getAccessClauses($params);
		}

		$query = 'SELECT DISTINCT t.id, t.name as title, CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
			. ' FROM #__flexicontent_tags AS t'
			. ' WHERE t.published = 1';
		$tags = $db->setQuery($query)->loadObjectList();

		// Terminate if no items were found
		if ( empty($tags) ) return;

		// ******************************
		// ADD Found Items to the SiteMap
		// ******************************

		self::addCatBasedTagLinks($osmap, $parent, $params, $tags);
	}


	/*
	 * When tag is used a menu item, get the items tagged
	 */
	static function &getItems( $where_basic, &$params, $extra_join, $extra_endwhere )
	{
		static $db, $user, $nullDate, $_nowDate, $ordering, $access_clauses;
		static $initialized = null;

		if ($initialized === null)
		{
			$initialized = true;
			$db    = JFactory::getDBO();
			$user  = JFactory::getUser();
			$date  = JFactory::getDate();
			$_nowDate = 'UTC_TIMESTAMP()'; //$this->_db->Quote( $date->toMySQL() );
			$nullDate = $db->getNullDate();
			$ordering = FLEXI_J16GE ? 'c.lft ASC' : 'c.ordering ASC';
			$access_clauses = self::getAccessClauses($params);
		}

		// First basic where part
		$where  = $where_basic;

		// Second is to only select items the user has access to
		$states = '1, -5';  //if ($user->gid > 2) $states .= ', 0 , -3, -4';
		$where .= ' AND i.state IN ('.$states.')';
		$where .= ' AND ( i.publish_up IS NULL OR i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= ' . $_nowDate . ' )';
		$where .= ' AND ( i.publish_down IS NULL OR i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= ' . $_nowDate . ' )';
		$where .= ' AND c.published = 1';

		// Third other limitations
		$where .= $params['days'];

		$query = 'SELECT DISTINCT i.id, i.title, c.id AS cid, '
				. ' i.modified, i.created, '.(FLEXI_J16GE ? 'i.language,' : 'ie.language,')
				. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS catslug'
				. ($params['add_images'] || $params['add_pagebreaks'] ? ', i.introtext, i.fulltext ' : '')
				. ' FROM #__content AS i'
				. ' JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id'          // Join to get Content Type
				. ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'  // Join with types to check perms
				. ' JOIN #__categories AS c ON c.id = i.catid '              // Join main category to check perms
				. $extra_join
				. $access_clauses->joinaccess_item
				. $where
				. $extra_endwhere
				. $access_clauses->andaccess_item
				. ' ORDER BY i.title'
				. $params['limit']
				;
		$items = $db->setQuery($query)->loadObjectList();

		return $items;
	}


	static private function getAccessClauses(&$params)
	{
		static $access_clauses = null;

		if ($access_clauses !== null)
		{
			return $access_clauses;
		}

		// Unauthorized will be shown so, return empty clauses, to allow this
		if ($params['show_noauth'])
		{
			$access_clauses = new stdClass();
			$access_clauses->joinaccess_cat  = '';
			$access_clauses->andaccess_cat   = '';
			$access_clauses->joinaccess_item = '';
			$access_clauses->andaccess_item  = '';
			return $access_clauses;
		}

		$joinaccess_cat  = $andaccess_cat  = '';
		$joinaccess_item = $andaccess_item = '';

		// CASE A: Select content according to CURRENT USER ACCESS Level
		if (!$params['show_noauth'])
		{
			$user = JFactory::getUser();

			$aid_arr = $user->getAuthorisedViewLevels();
			$aid_list = implode(",", $aid_arr);
			$andaccess_cat .= ' AND c.access IN (0,'.$aid_list.')';

			$aid_arr = $user->getAuthorisedViewLevels();
			$aid_list = implode(",", $aid_arr);
			$andaccess_item .= ' AND ty.access IN (0,'.$aid_list.')';
			$andaccess_item .= ' AND  c.access IN (0,'.$aid_list.')';
			$andaccess_item .= ' AND  i.access IN (0,'.$aid_list.')';
		}

		$access_clauses = new stdClass();
		$access_clauses->joinaccess_cat  = $joinaccess_cat;
		$access_clauses->andaccess_cat   = $andaccess_cat;
		$access_clauses->joinaccess_item = $joinaccess_item;
		$access_clauses->andaccess_item  = $andaccess_item;

		return $access_clauses;
	}


	static private function addCatBasedTagLinks($osmap, $parent, $params, &$tags)
	{
		// Start including Tags, change level +1
		$osmap->changeLevel(1);

		foreach($tags as $tag)
		{
			$node = new stdclass;
			$node->id     = $parent->id;
			$node->uid    = $parent->uid .'t'.$tag->id;

			$node->browserNav = $parent->browserNav;
			$node->priority   = $params['item_priority'];
			$node->changefreq = $params['item_changefreq'];
			$node->name       = $tag->title;
			$node->expandible = false;
			$node->secure = $parent->secure;

			// TODO: Should we include category name or metakey here?
			// $node->keywords = $tag->metakey;
			$node->newsItem = 1;
			$node->language = isset($tag->language) ? $tag->language : '*';

			// For the google news we should use te publication date instead the last modification date.
			$node->modified = ''; //($osmap->isNews || !$tag->modified) ? $tag->created : $tag->modified;

			$node->slug     = $tag->slug;
			$node->link     = FlexicontentHelperRoute::getCategoryRoute(0, 0, array('layout'=>'tags','tagid'=>$tag->slug), $tag);
			$node->tree     = array();

			$osmap->printNode($node);
		}

		// Finished including tags, change level -1
		$osmap->changeLevel(-1);
	}

	static private function addContentItems($osmap, $parent, $params, &$items)
	{
		// Start including Content Items, change level +1
		$osmap->changeLevel(1);

		foreach($items as $item)
		{
			$node = new stdclass;
			$node->id     = $parent->id;
			$node->uid    = $parent->uid .'d'.$item->id;

			$node->browserNav = $parent->browserNav;
			$node->priority   = $params['item_priority'];
			$node->changefreq = $params['item_changefreq'];
			$node->name       = $item->title;
			$node->expandible = false;
			$node->secure = $parent->secure;

			// TODO: Should we include category name or metakey here?
			// $node->keywords = $item->metakey;
			$node->newsItem = 1;
			$node->language = $item->language;

			// For the google news we should use te publication date instead the last modification date.
			$node->modified = ($osmap->isNews || !$item->modified) ? $item->created : $item->modified;

			//$node->slug = $item->alias ? ($item->id . ':' . $item->alias) : $item->id;
			//$node->catslug = $item->category_route ? ($catid . ':' . $item->category_route) : $catid;
			$node->slug     = $item->slug;
			$node->catslug  = $item->catslug;
			$node->link     = FlexicontentHelperRoute::getItemRoute( $item->slug, $item->catslug );
			$node->tree     = array();

			// Add images of the content item
			$text = @$item->introtext . @$item->fulltext;

			if ($params['add_images'])
			{
				$node->images = OSMapHelper::getImages($text, $params['max_images']);
			}

			// Check if adding sub-pages of the content item
			if ($params['add_pagebreaks'])
			{
				$subnodes = OSMapHelper::getPagebreaks($text, $node->link);
				$node->expandible = count($subnodes) > 0; // This article has children
			}

			// Add current content items and its sub-pages if so configured
			if ($osmap->printNode($node) && $node->expandible)
			{
				self::printNodes($osmap, $node, $params, $subnodes);
			}
		}

		// Finished including Content Items, change level -1
		$osmap->changeLevel(-1);
	}


	static private function printNodes($osmap, $parent, $params, &$subnodes)
	{
		$osmap->changeLevel(1);
		$i = 0;

		foreach ($subnodes as $subnode)
		{
			$i++;
			$subnode->id   = $parent->id;
			$subnode->uid  = $parent->uid.'p'.$i;
			$subnode->browserNav = $parent->browserNav;
			$subnode->priority   = $params['item_priority'];
			$subnode->changefreq = $params['item_changefreq'];
			$subnode->secure     = $parent->secure;
			$osmap->printNode($subnode);
		}

		$osmap->changeLevel(-1);
	}
}
