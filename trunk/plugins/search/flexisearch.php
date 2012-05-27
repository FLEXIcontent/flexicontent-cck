<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
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
defined( '_JEXEC' ) or die( 'Restricted access' );
jimport('joomla.plugin.plugin');

$mainframe->registerEvent( 'onSearch', 'plgSearchFlexisearch' );
$mainframe->registerEvent( 'onSearchAreas', 'plgSearchFlexisearchAreas' );

require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
require_once(JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

//Load the Plugin language file out of the administration
JPlugin::loadLanguage( 'plg_search_flexisearch', JPATH_ADMINISTRATOR);
	
	function _getAreas(){
		$params = (FLEXI_J16GE) ? $this->params : new JParameter( JPluginHelper::getPlugin('search', 'flexisearch')->params );
		
		$areas = array();
		if ($params->get('search_title',	1)) {$areas['FlexisearchTitle'] = JText::_('FLEXI_STDSEARCH_TITLE');}
		if ($params->get('search_desc',	1)) {$areas['FlexisearchDesc'] = JText::_('FLEXI_STDSEARCH_DESC');}
		if ($params->get('search_fields',	1)) {$areas['FlexisearchFields'] = JText::_('FLEXI_STDSEARCH_FIELDS');}
		if ($params->get('search_meta',	1)) {$areas['FlexisearchMeta'] = JText::_('FLEXI_STDSEARCH_META');}
		if ($params->get('search_tags',	1)) {$areas['FlexisearchTags'] = JText::_('FLEXI_STDSEARCH_TAGS');}
		end($areas);
		$areas[key($areas)]=current($areas).'<br><br>';
		return $areas;
	}

	function _getContentTypes(){
		$params = (FLEXI_J16GE) ? $this->params : new JParameter( JPluginHelper::getPlugin('search', 'flexisearch')->params );
	
		$typeIds = $params->get('search_types',	'');
		$typesarray = array();
		preg_match_all('/\b\d+\b/',$typeIds, $typesarray);
		$wheres=array();
		foreach ($typesarray[0] as $key=>$typeID){
			$wheres[]='t.id = '.$typeID;
		}
		$whereTypes =  $wheres ? '(' . implode(') OR (', $wheres) . ')' : '';
		
		$db		= JFactory::getDbo();
		$query	= ' SELECT t.id, t.name'
			.' FROM #__flexicontent_types AS t '
			.' WHERE t.published = 1'.($whereTypes ? ' AND ('.$whereTypes.')' : '')
			.' ORDER BY t.id ASC';

		$db->setQuery($query);
		$list = $db->loadObjectList();

		$ContentType = array();
		if (isset($list)){
			foreach($list as $item){
				$ContentType['FlexisearchType'.$item->id]=$item->name.'<br>';
			}
		}
		end($ContentType);
		$ContentType[key($ContentType)]=current($ContentType).'<br>';
		return $ContentType;
	
	}
	/*
	 * @return array of search areas
	 */
	function &plgSearchFlexisearchAreas()
	{
		static $areas = array();
		
		$params = (FLEXI_J16GE) ? $this->params : new JParameter( JPluginHelper::getPlugin('search', 'flexisearch')->params );
		
		$areas = $params->get('search_select_types',	1) ? _getAreas() + _getContentTypes() : _getAreas();
		return $areas;			
	}
	
	if (!function_exists('plgSearchContentAreas')) {
		function &plgSearchContentAreas()
		{
			static $areas = array();
			return $areas;			
		}
	}
	
	/**
	 * Content Search method
	 * The sql must return the following fields that are used in a common display
	 * routine: href, title, section, created, text, browsernav
	 * @param string Target search string
	 * @param string mathcing option, exact|any|all
	 * @param string ordering option, newest|oldest|popular|alpha|category
	 * @param mixed An array if restricted to areas, null if search all
	 */
	function plgSearchFlexisearch( $text, $phrase='', $ordering='', $areas=null )
	{
		$params = (FLEXI_J16GE) ? $this->params : new JParameter( JPluginHelper::getPlugin('search', 'flexisearch')->params );
		
		$db		= JFactory::getDbo();
		$app	= JFactory::getApplication();
		$user	= JFactory::getUser();
		
		// Get language, joomfish or J1.7+ language selector will append lang variable to the URL
		$lang = JRequest::getWord('lang', '' );
		if(empty($lang)){
			$langFactory= JFactory::getLanguage();
			$tagLang = $langFactory->getTag();
			//Well, the substr is not even required as flexi saves the Joomla language tag... so we could have kept the $tagLang tag variable directly.
			$lang = substr($tagLang ,0,2);
		}
	    
	  // COMPONENT PARAMETERS
		$cparams 	= & $app->getParams('com_flexicontent');
		if (!defined('FLEXI_SECTION'))
			define('FLEXI_SECTION', $cparams->get('flexi_section'));		// define section
		$show_noauth = $cparams->get('show_noauth', 0);		// items the user cannot see ...

		$searchText = $text;
		
		$AllAreas=array_keys(FLEXI_J16GE ? $this->_getAreas() : _getAreas());
		$AllTypes=array_keys(FLEXI_J16GE ? $this->_getContentTypes() : _getContentTypes());
			
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

		$filter_lang	= $params->def('filter_lang',	1);
		$limit				= $params->def('search_limit', 50);

		// Dates for publish up & down items
		$nullDate		= $db->getNullDate();
		$date =& JFactory::getDate();
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
				if (in_array('FlexisearchTitle', $searchAreas))		{$wheres2[]	= 'a.title LIKE '.$text;}
				if (in_array('FlexisearchDesc', $searchAreas))		{$wheres2[]	= 'a.introtext LIKE '.$text;	$wheres2[]	= 'a.fulltext LIKE '.$text;}
				if (in_array('FlexisearchMeta', $searchAreas))		{$wheres2[]	= 'a.metakey LIKE '.$text;		$wheres2[]	= 'a.metadesc LIKE '.$text;}
				if (in_array('FlexisearchFields', $searchAreas))	{$wheres2[]	= "f.field_type='text' AND f.issearch=1 AND fir.value LIKE ".$text;}
				if (in_array('FlexisearchTags', $searchAreas))		{$wheres2[]	= 't.name LIKE '.$text;}
				if (count($wheres2)) $where		= '(' . implode(') OR (', $wheres2) . ')';
				break;
			case 'all':
			case 'any':
			default:
				$words = explode(' ', $text);
				$wheres = array();
				foreach ($words as $word) {
					$word		= $db->Quote('%'.$db->getEscaped($word, true).'%', false);
					$wheres2	= array();
					if (in_array('FlexisearchTitle', $searchAreas))		{$wheres2[]	= 'a.title LIKE '.$word;}
					if (in_array('FlexisearchDesc', $searchAreas))		{$wheres2[]	= 'a.introtext LIKE '.$word;	$wheres2[]	= 'a.fulltext LIKE '.$word;}
					if (in_array('FlexisearchMeta', $searchAreas))		{$wheres2[]	= 'a.metakey LIKE '.$word;		$wheres2[]	= 'a.metadesc LIKE '.$word;}
					if (in_array('FlexisearchFields', $searchAreas))	{$wheres2[]	= "f.field_type='text' AND f.issearch=1 AND fir.value LIKE ".$word;}
					if (in_array('FlexisearchTags', $searchAreas))		{$wheres2[]	= 't.name LIKE '.$word;}
					if (count($wheres2)) $wheres[]	= '(' . implode(') OR (', $wheres2) . ')';
				}
				if (count($wheres)) {
					$where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
				}
				break;
		}
		if (!$where) {return array();}
		//if ( empty($where) ) $where = '1';
		
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

		// Select only items user has access to if he is not allowed to show unauthorized items
		$joinaccess	= '';
		$andaccess	= '';
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$andaccess .= 'AND a.access IN ('.$aid_list.') ';
				$andaccess .= 'AND c.access IN ('.$aid_list.') ';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gi ON a.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
					$andaccess	.= ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. (int) $aid . ')';
					$andaccess  .= ' AND (gi.aro IN ( '.$user->gmid.' ) OR a.access <= '. (int) $aid . ')';
				} else {
					$andaccess  .= ' AND c.access <= '.$aid;
					$andaccess  .= ' AND a.access <= '.$aid;
				}
			}
		}
	
		// filter by active language
		$andlang = '';
		if (FLEXI_FISH && $filter_lang) {
			$andlang .= ' AND ie.language LIKE ' . $db->Quote( $lang .'%' );
		}
	
		// search articles
		$results = array();
		if ( $limit > 0)
		{
			$query 	= 'SELECT a.sectionid,'
				.' a.id as id,'
				.' a.title AS title,'
				.' a.metakey AS metakey,'
				.' a.metadesc AS metadesc,'
				.' a.modified AS created,'     // TODO ADD a PARAMETER FOR CONTROLING the use of modified by or created by date as "created"
				.' t.name AS tagname,'
				.' fir.value as field,'
				.' CONCAT(a.introtext, a.fulltext) AS text,'
				.' CONCAT_WS( " / ", '. $db->Quote( JText::_( 'FLEXICONTENT' ) ) .', c.title, a.title ) AS section,'
				.' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END AS slug,'
				.' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END AS catslug,'
				.' "2" AS browsernav'
				.' FROM #__content AS a'
				.' LEFT JOIN #__flexicontent_items_ext AS ie ON a.id = ie.item_id'
				.' LEFT JOIN #__flexicontent_fields_item_relations AS fir ON a.id = fir.item_id'
				.' LEFT JOIN #__categories AS c ON a.catid = c.id'
				.' LEFT JOIN #__flexicontent_tags_item_relations AS tir ON a.id = tir.itemid'
				.' LEFT JOIN #__flexicontent_fields AS f ON fir.field_id = f.id'
				.' LEFT JOIN #__flexicontent_tags AS t ON tir.tid = t.id	'
				. $joinaccess
				.' WHERE ( '.$where.' ) '
				.' AND ie.type_id IN('.$types.') '
				.' AND a.state IN (1, -5) AND c.published = 1 '
				.' AND (a.publish_up = '.$db->Quote($nullDate).' OR a.publish_up <= '.$db->Quote($now).') '
				.' AND (a.publish_down = '.$db->Quote($nullDate).' OR a.publish_down >= '.$db->Quote($now).') '
				. $andaccess // Filter by user access
				. $andlang   // Filter by current language
				. ' GROUP BY a.id '
				. ' ORDER BY '. $order
				;
			//echo "<pre>".$query."</pre>";
			
			$db->setQuery($query, 0, $limit);
			$list = $db->loadObjectList();
			if ( $db->getErrorNum() ) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage(nl2br($query."\n".$db->getErrorMsg()."\n"),'error');
			}
			if (isset($list))
			{
				foreach($list as $key => $item)
				{
					// echo $item->title." ".$item->tagname."<br/>"; // Before checking for noHTML
					if($item->sectionid==FLEXI_SECTION)
						$list[$key]->href = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->catslug));
					else
						$list[$key]->href = JRoute::_(ContentHelperRoute::getArticleRoute($item->slug, $item->catslug, $item->sectionid));
					if (searchHelper::checkNoHTML($item, $searchText, array('text', 'title', 'metadesc', 'metakey', 'tagname', 'field' ))) {
						$results[$item->id] = $item;
					}
				}
			}
		}

		return $results;
	}
