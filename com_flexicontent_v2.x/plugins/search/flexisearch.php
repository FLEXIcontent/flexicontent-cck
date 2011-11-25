<?php
/**
 * @version		$Id: content.php 21097 2011-04-07 15:38:03Z dextercowley $
 * @copyright	Copyright (C) 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

require_once JPATH_SITE.'/components/com_flexicontent/router.php';

/**
 * Content Search plugin
 *
 * @package		Joomla.Plugin
 * @subpackage	Search.content
 * @since		1.6
 */
class plgSearchFlexisearch extends JPlugin
{
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	/**
	 * @return array An array of search areas
	 */
		function onContentSearchAreas()
		{
		static $areas = array();
		if ($this->params->get('search_title',	1)) {$areas['flexisearchTitle'] = 'FLEXI_STDSEARCH_TITLE';}
		if ($this->params->get('search_desc',	1)) {$areas['flexisearchDesc'] = 'FLEXI_STDSEARCH_DESC';}
		if ($this->params->get('search_fields',	1)) {$areas['flexisearchFields'] = 'FLEXI_STDSEARCH_FIELDS';}
		if ($this->params->get('search_meta',	1)) {$areas['flexisearchMeta'] = 'FLEXI_STDSEARCH_META';}
		if ($this->params->get('search_tags',	1)) {$areas['flexisearchTags'] = 'FLEXI_STDSEARCH_TAGS';}
		return $areas;			
	}

	/**
	 * Content Search method
	 * The sql must return the following fields that are used in a common display
	 * routine: href, title, section, created, text, browsernav
	 * @param string Target search string
	 * @param string mathcing option, exact|any|all
	 * @param string ordering option, newest|oldest|popular|alpha|category
	 * @param mixed An array if the search it to be restricted to areas, null if search all
	 */
	function onContentSearch($text, $phrase='', $ordering='', $areas=null)
	{
		$db		= JFactory::getDbo();
		$app	= JFactory::getApplication();
		$user	= JFactory::getUser();
		$groups	= implode(',', $user->getAuthorisedViewLevels());
		$tag = JFactory::getLanguage()->getTag();

//		require_once JPATH_SITE.'/components/com_content/helpers/route.php';
//		require_once JPATH_SITE.'/administrator/components/com_search/helpers/search.php';

		$searchText = $text;
		if (is_array($areas)) {
			// search in selected areas
			$searchfrom = array_intersect($areas, array_keys($this->onContentSearchAreas()));
			if (!$searchfrom) {
				return array();
			}
		}else{
			// search in all avaliable areas
			$searchfrom = array_keys($this->onContentSearchAreas());
		}

		$filter_lang	= $this->params->get('filter_lang',	1);
		$limit			= $this->params->def('search_limit',50);

		$nullDate		= $db->getNullDate();
		$date = JFactory::getDate();
		$now = $date->toMySQL();

		$text = trim($text);
		if ($text == '') {
			return array();
		}
		
		$wheres = array();
		switch ($phrase) {
			case 'exact':
				$text		= $db->Quote('%'.$db->getEscaped($text, true).'%', false);
				$wheres2	= array();
				if (in_array('flexisearchTitle', $searchfrom))	{$wheres2[]	= 'a.title LIKE '.$text;}
				if (in_array('flexisearchDesc', $searchfrom))	{$wheres2[]	= 'a.introtext LIKE '.$text;
																	 $wheres2[]	= 'a.fulltext LIKE '.$text;}
				if (in_array('flexisearchMeta', $searchfrom))	{$wheres2[]	= 'a.metakey LIKE '.$text;
																	 $wheres2[]	= 'a.metadesc LIKE '.$text;}
				if (in_array('flexisearchFields', $searchfrom))	{$wheres2[]	= 'fir.value LIKE '.$text;}
				if (in_array('flexisearchTags', $searchfrom))	{$wheres2[]	= 't.name LIKE '.$text;}
				$where		= '(' . implode(') OR (', $wheres2) . ')';
				break;
			case 'all':
			case 'any':
			default:
				$words = explode(' ', $text);
				$wheres = array();
				foreach ($words as $word) {
					$word		= $db->Quote('%'.$db->getEscaped($word, true).'%', false);
					$wheres2	= array();
					if (in_array('flexisearchTitle', $searchfrom)) 	{$wheres2[]	= 'a.title LIKE '.$word;}
					if (in_array('flexisearchDesc', $searchfrom)) 	{$wheres2[]	= 'a.introtext LIKE '.$word;
																		 $wheres2[]	= 'a.fulltext LIKE '.$word;}
					if (in_array('flexisearchMeta', $searchfrom))	{$wheres2[]	= 'a.metakey LIKE '.$word;
																		 $wheres2[]	= 'a.metadesc LIKE '.$word;}
					if (in_array('flexisearchFields', $searchfrom))	{$wheres2[]	= 'fir.value LIKE '.$word;}
					if (in_array('flexisearchTags', $searchfrom))	{$wheres2[]	= 't.name LIKE '.$word;}
					$wheres[]	= implode(' OR ', $wheres2);
				}
				$where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
				break;
		}
		if (!$where) {return array();}
		
		$morder = '';
		switch ($ordering) {
			case 'oldest':
				$order = 'a.created ASC';
				break;

			case 'popular':
				$order = 'a.hits DESC';
				break;

			case 'alpha':
				$order = 'a.title ASC';
				break;

			case 'category':
				$order = 'c.title ASC, a.title ASC';
				$morder = 'a.title ASC';
				break;

			case 'newest':
			default:
				$order = 'a.created DESC';
				break;
		}
		
		// Select only items user has access to if he is not allowed to show unauthorized items
		/*$joinaccess	= '';
		$andaccess	= '';
		if (!$show_noauth) {
			if (FLEXI_ACCESS) {
				$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
				$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gi ON a.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
				$andaccess	.= ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. (int) $gid . ')';
				$andaccess  .= ' AND (gi.aro IN ( '.$user->gmid.' ) OR a.access <= '. (int) $gid . ')';
			} else {
				$andaccess  .= ' AND c.access <= '.$gid;
				$andaccess  .= ' AND a.access <= '.$gid;
			}
		}*/
		
		$rows = array();
		$query	= $db->getQuery(true);

		// search articles
		$results = array();
		$NoItem->href	= JRoute::_('index.php?option=com_content&view=archive&year='.$created_year.'&month='.$created_month.$itemid);
		if ( $limit > 0)
		{
			$query->clear();
			$query->select(
				'a.title AS title, '
				.'a.metakey AS metakey, '
				.'a.metadesc AS metadesc, '				
				.'CONCAT(a.introtext, a.fulltext) AS text, '
				.'CONCAT_WS("/", c.title) AS section, ' 
				.'CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END AS slug, '
				.'CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END AS catslug, '
				.'"2" AS browsernav ');
			$query->from('(#__content AS a '
				.'LEFT JOIN #__flexicontent_fields_item_relations AS fir ON a.id = fir.item_id '
				.'LEFT JOIN #__categories AS c ON a.catid = c.id '
				.'LEFT JOIN #__flexicontent_tags_item_relations AS tir ON a.id = tir.itemid) '
				.'LEFT JOIN #__flexicontent_fields AS f ON fir.field_id = f.id '
				.'LEFT JOIN #__flexicontent_tags AS t ON tir.tid = t.id	');
				//. $joinaccess
//			$query->where(" (f.field_type='text' Or f.field_type Is Null)"
			$query->where(" ((f.field_type='text' AND f.issearch=1) Or f.field_type Is Null)"
				.'AND ('. $where .') ' 
				.'AND a.state=1 AND c.published = 1 AND a.access IN ('.$groups.') '
				.'AND c.access IN ('.$groups.') '
				.'AND (a.publish_up = '.$db->Quote($nullDate).' OR a.publish_up <= '.$db->Quote($now).') '
				.'AND (a.publish_down = '.$db->Quote($nullDate).' OR a.publish_down >= '.$db->Quote($now).') '); 
				//. $andaccess
			// Filter by language
			if ($app->isSite() && $app->getLanguageFilter() && $filter_lang) {
				$query->where('a.language in (' . $db->Quote($tag) . ',' . $db->Quote('*') . ')');
				$query->where('c.language in (' . $db->Quote($tag) . ',' . $db->Quote('*') . ')');
			}
			$query->group('a.id');
			$query->order($order);
			
			$db->setQuery($query, 0, $limit);
			$list = $db->loadObjectList();

			if (isset($list)){
				foreach($list as $key => $item){
					$list[$key]->href = FlexicontentHelperRoute::getItemRoute($item->slug, $item->catslug);
					if (searchHelper::checkNoHTML($item, $searchText, array('text', 'title', 'metadesc', 'metakey'))) {
						$results[] = $item;
					}
				}
			}
		}
		return $results;
	}
}
