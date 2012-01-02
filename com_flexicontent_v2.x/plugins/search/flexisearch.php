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
	function _getAreas(){
		$areas = array();
		if ($this->params->get('search_title',	1)) {$areas['FlexisearchTitle'] = JText::_('FLEXI_STDSEARCH_TITLE');}
		if ($this->params->get('search_desc',	1)) {$areas['FlexisearchDesc'] = JText::_('FLEXI_STDSEARCH_DESC');}
		if ($this->params->get('search_fields',	1)) {$areas['FlexisearchFields'] = JText::_('FLEXI_STDSEARCH_FIELDS');}
		if ($this->params->get('search_meta',	1)) {$areas['FlexisearchMeta'] = JText::_('FLEXI_STDSEARCH_META');}
		if ($this->params->get('search_tags',	1)) {$areas['FlexisearchTags'] = JText::_('FLEXI_STDSEARCH_TAGS');}
		end($areas);
		$areas[key($areas)]=current($areas).'<br><br>';
		return $areas;
	}

	function _getContentTypes(){
	
		$typeIds = $this->params->get('search_types',	'');
		$typesarray = array();
		preg_match_all('/\b\d+\b/',$typeIds, $typesarray);
		$wheres=array();
		foreach ($typesarray[0] as $key=>$typeID){
			$wheres[]='t.id = '.$typeID;
		}
		$whereTypes =  $wheres ? '(' . implode(') OR (', $wheres) . ')' : '';
		
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		$query->clear();
		$query->select('t.id, t.name ');
		$query->from('#__flexicontent_types AS t ');
		$query->where('t.published = 1'.($whereTypes ? ' AND ('.$whereTypes.')' : ''));
		$query->order('t.id ASC');

		$db->setQuery($query);
		$list = $db->loadObjectList();

		$ContentType = array();
		if (isset($list)){
			foreach($list as $item){
				$ContentType['FlexisearchType'.$item->id]=$item->name.'<br>';
			}
		}
		return $ContentType;
	
	}
	/*
	 * @return array of search areas
	 */
	function onContentSearchAreas()
	{
		static $areas = array();
		
		 $areas = $this->params->get('search_select_types',	1) ? $this->_getAreas() + $this->_getContentTypes() :  $this->_getAreas();
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

		$searchText = $text;
		
		$AllAreas=array_keys($this->_getAreas());
		$AllTypes=array_keys($this->_getContentTypes());
			
		if (is_array($areas)) {
			// search in selected areas
			$searchAreas = array_intersect($areas, $AllAreas);
			$searchTypes = array_intersect($areas, $AllTypes);
			
			if (!$searchAreas && !$searchTypes) {
				return array();
			}
			if (!$searchAreas) {$searchAreas = $AllAreas;}
			if (!$searchTypes) {$searchTypes = $AllTypes;}
		}else{
			// search in all avaliable areas if no selected ones
			$searchAreas = $AllAreas;
			$searchTypes = $AllTypes;
		}
		
		foreach ($searchTypes as $id=>$tipe){
			$searchTypes[$id]=preg_replace('/\D/','',$tipe);
		}
		
		$types= implode(', ',$searchTypes);

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
				if (in_array('FlexisearchTitle', $searchAreas))	{$wheres2[]	= 'a.title LIKE '.$text;}
				if (in_array('FlexisearchDesc', $searchAreas))	{$wheres2[]	= 'a.introtext LIKE '.$text;
																	 $wheres2[]	= 'a.fulltext LIKE '.$text;}
				if (in_array('FlexisearchMeta', $searchAreas))	{$wheres2[]	= 'a.metakey LIKE '.$text;
																	 $wheres2[]	= 'a.metadesc LIKE '.$text;}
				if (in_array('FlexisearchFields', $searchAreas))	{$wheres2[]	= 'fir.value LIKE '.$text;}
				if (in_array('FlexisearchTags', $searchAreas))	{$wheres2[]	= 't.name LIKE '.$text;}
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
					if (in_array('FlexisearchTitle', $searchAreas)) 	{$wheres2[]	= 'a.title LIKE '.$word;}
					if (in_array('FlexisearchDesc', $searchAreas)) 	{$wheres2[]	= 'a.introtext LIKE '.$word;
																		 $wheres2[]	= 'a.fulltext LIKE '.$word;}
					if (in_array('FlexisearchMeta', $searchAreas))	{$wheres2[]	= 'a.metakey LIKE '.$word;
																		 $wheres2[]	= 'a.metadesc LIKE '.$word;}
					if (in_array('FlexisearchFields', $searchAreas))	{$wheres2[]	= 'fir.value LIKE '.$word;}
					if (in_array('FlexisearchTags', $searchAreas))	{$wheres2[]	= 't.name LIKE '.$word;}
					$wheres[]	= implode(' OR ', $wheres2);
				}
				$where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
				break;
		}
		if (!$where) {return array();}
		
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
				break;

			case 'newest':
			default:
				$order = 'a.created DESC';
				break;
		}

		$rows = array();
		$query	= $db->getQuery(true);

		// search articles
		$results = array();
		if ( $limit > 0)
		{
			$query->clear();
			$query->select(
				' a.title AS title, '
				.'a.metakey AS metakey, '
				.'a.metadesc AS metadesc, '
				.'a.modified AS created, '
				.'t.name AS tagname, '
				.'CONCAT(a.introtext, a.fulltext) AS text, '
				.'CONCAT_WS("/", c.title) AS section, '
				.'CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END AS slug, '
				.'CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END AS catslug, '
				.'"2" AS browsernav ');
			$query->from('(#__content AS a '
				.'LEFT JOIN #__flexicontent_items_ext AS ie ON a.id = ie.item_id '
				.'LEFT JOIN #__flexicontent_fields_item_relations AS fir ON a.id = fir.item_id '
				.'LEFT JOIN #__categories AS c ON a.catid = c.id '
				.'LEFT JOIN #__flexicontent_tags_item_relations AS tir ON a.id = tir.itemid) '
				.'LEFT JOIN #__flexicontent_fields AS f ON fir.field_id = f.id '
				.'LEFT JOIN #__flexicontent_tags AS t ON tir.tid = t.id	');
			$query->where(" ((f.field_type='text' AND f.issearch=1) Or f.field_type Is Null Or t.id Is Not Null) "
				.'AND ('. $where .') ' 
				.'AND ie.type_id IN('.$types.') '
				.'AND a.state=1 AND c.published = 1 AND a.access IN ('.$groups.') '
				.'AND c.access IN ('.$groups.') '
				.'AND (a.publish_up = '.$db->Quote($nullDate).' OR a.publish_up <= '.$db->Quote($now).') '
				.'AND (a.publish_down = '.$db->Quote($nullDate).' OR a.publish_down >= '.$db->Quote($now).') '); 
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
					if (searchHelper::checkNoHTML($item, $searchText, array('text', 'title', 'metadesc', 'metakey', 'tagname'))) {
						$results[] = $item;
					}
				}
			}
		}

		return $results;
	}
}
