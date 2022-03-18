<?php
/**
 * @version 1.0 $Id: flexisearch.php 1353 2012-06-23 20:47:30Z ggppdk $
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

jimport('cms.plugin.plugin');

require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

FLEXI_J40GE
	? require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'src'.DS.'Helper'.DS.'RouteHelper.php')
	: require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
if (!FLEXI_J40GE) require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_search'.DS.'helpers'.DS.'search.php');

require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

/**
 * Content Search plugin
 *
 * @package		FLEXIcontent.Plugin
 * @subpackage	Search.flexisearch
 * @since		1.6
 */
class plgSearchFlexisearch extends JPlugin
{
	var $autoloadLanguage = false;

	/**
	 * Constructor
	 *
	 * @access      public
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 * @since       1.5
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		static $language_loaded = null;
		if (!$this->autoloadLanguage && $language_loaded === null) $language_loaded = JPlugin::loadLanguage('plg_search_flexisearch', JPATH_ADMINISTRATOR);
	}
	
	
	function _getAreas()
	{
		$areas = array();
		if ($this->params->get('search_title',	1)) {$areas['FlexisearchTitle'] = JText::_('FLEXI_STDSEARCH_TITLE');}
		if ($this->params->get('search_desc',		1)) {$areas['FlexisearchDesc'] = JText::_('FLEXI_STDSEARCH_DESC');}
		if ($this->params->get('search_fields',	1)) {$areas['FlexisearchFields'] = JText::_('FLEXI_STDSEARCH_FIELDS');}
		if ($this->params->get('search_meta',		1)) {$areas['FlexisearchMeta'] = JText::_('FLEXI_STDSEARCH_META');}
		if ($this->params->get('search_tags',		1)) {$areas['FlexisearchTags'] = JText::_('FLEXI_STDSEARCH_TAGS');}
		
		// Goto last element of array and add to it 2 line breaks, this layout hack is not appropriate e.g. the areas maybe inside a list ...
		//end($areas);
		//$areas[key($areas)]=current($areas).'<br><br>';
		
		return $areas;
	}
	
	
	function _getContentTypes()
	{
		// Get allowed search types
		$typeIds = $this->params->get('search_types',	'');
		$typesarray = array();
		preg_match_all('/\b\d+\b/', $typeIds, $typesarray);
		
		$wheres=array();
		foreach ($typesarray[0] as $key=>$typeID)
		{
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
				$ContentType['FlexisearchType'.$item->id]=$item->name.'<br>'; // add a line break too
			}
		}
		
		// Goto last element of array and add to it one more line breaks, this layout hack is not appropriate e.g. the areas maybe inside a list ...
		//end($ContentType);
		//$ContentType[key($ContentType)]=current($ContentType).'<br>';
		
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
	
	// Also add J1.5 function signature
	/*function onSearchAreas()
	{
		return $this->onContentSearchAreas();
	}*/
	

	/**
	 * Content Search method
	 * The sql must return the following fields that are used in a common display
	 * routine: href, title, section, created, text, browsernav
	 * @param string Target search string
	 * @param string mathcing option, exact|any|all
	 * @param string ordering option, newest|oldest|popular|alpha|category
	 * @param mixed An array if restricted to areas, null if search all
	 */
	function onContentSearch( $text, $phrase='', $ordering='', $areas=null )
	{
		$db		= JFactory::getDbo();
		$app	= JFactory::getApplication();
		$user	= JFactory::getUser();
		
		// Get language
		$cntLang = substr(JFactory::getLanguage()->getTag(), 0,2);  // Current Content language (Can be natively switched in J2.5)
		$urlLang  = JFactory::getApplication()->input->getWord('lang', '' );                 // Language from URL (Can be switched via Joomfish in J1.5)
		$lang = (FLEXI_J16GE || empty($urlLang)) ? $cntLang : $urlLang;
		
	  // COMPONENT PARAMETERS
		$cparams 	= $app->isClient('site')  ?  $app->getParams('com_flexicontent')  : JComponentHelper::getParams('com_flexicontent');
		if (!defined('FLEXI_SECTION'))
			define('FLEXI_SECTION', $cparams->get('flexi_section'));		// define section
		$show_noauth = $cparams->get('show_noauth', 0);		// items the user cannot see ...

		$searchText = $text;
		
		$AllAreas = array_keys( $this->_getAreas() );
		$AllTypes = array_keys( $this->_getContentTypes() );
		
		if (is_array($areas)) {
			// search in selected areas
			$searchAreas = array_intersect( $areas, $AllAreas );
			$searchTypes = array_intersect( $areas, $AllTypes );
			
			if (!$searchAreas && !$searchTypes)  return array();
			
			if (!$searchAreas) {$searchAreas = $AllAreas;}
			if (!$searchTypes) {$searchTypes = $AllTypes;}
		} else {
			// search in all avaliable areas if no selected ones
			$searchAreas = $AllAreas;
			$searchTypes = $AllTypes;
		}
		
		foreach ($searchTypes as $id=>$tipe){
			$searchTypes[$id]=preg_replace('/\D/','',$tipe);
		}
		
		$types= implode(', ',$searchTypes);

		$filter_lang	= $this->params->def('filter_lang',	1);
		$limit				= $this->params->def('search_limit', 50);

		// NULL and CURRENT dates,
		// NOTE: the current date needs to use built-in MYSQL function, otherwise filter caching can not work because the CURRENT DATETIME is continuously different !!!
		//$now = JFactory::getDate()->toSql();
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate = $db->getNullDate();
		
		$text = trim($text);
		if ($text == '') {
			return array();
		}
		
		$wheres = array();
		switch ($phrase) {
			case 'exact':
				$text = $db->escape($text, true);
				$text = $db->Quote('%'.$text.'%', false);
				$wheres2	= array();
				if (in_array('FlexisearchTitle', $searchAreas))		{$wheres2[]	= 'i.title LIKE '.$text;}
				if (in_array('FlexisearchDesc', $searchAreas))		{$wheres2[]	= 'i.introtext LIKE '.$text;	$wheres2[]	= 'i.fulltext LIKE '.$text;}
				if (in_array('FlexisearchMeta', $searchAreas))		{$wheres2[]	= 'i.metakey LIKE '.$text;		$wheres2[]	= 'i.metadesc LIKE '.$text;}
				if (in_array('FlexisearchFields', $searchAreas))	{$wheres2[]	= "f.field_type IN ('text','textselect') AND f.issearch=1 AND fir.value LIKE ".$text;}
				if (in_array('FlexisearchTags', $searchAreas))		{$wheres2[]	= 't.name LIKE '.$text;}
				if (count($wheres2)) $where		= '(' . implode(') OR (', $wheres2) . ')';
				break;
			case 'all':
			case 'any':
			default:
				$words = explode(' ', $text);
				$wheres = array();
				foreach ($words as $word) {
					$word = $db->escape($word, true);
					$word = $db->Quote('%'.$word.'%', false);
					$wheres2	= array();
					if (in_array('FlexisearchTitle', $searchAreas))		{$wheres2[]	= 'i.title LIKE '.$word;}
					if (in_array('FlexisearchDesc', $searchAreas))		{$wheres2[]	= 'i.introtext LIKE '.$word;	$wheres2[]	= 'i.fulltext LIKE '.$word;}
					if (in_array('FlexisearchMeta', $searchAreas))		{$wheres2[]	= 'i.metakey LIKE '.$word;		$wheres2[]	= 'i.metadesc LIKE '.$word;}
					if (in_array('FlexisearchFields', $searchAreas))	{$wheres2[]	= "f.field_type IN ('text','textselect') AND f.issearch=1 AND fir.value LIKE ".$word;}
					if (in_array('FlexisearchTags', $searchAreas))		{$wheres2[]	= 't.name LIKE '.$word;}
					if (count($wheres2)) $wheres[]	= '(' . implode(') OR (', $wheres2) . ')';
				}
				if (count($wheres)) {
					$where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
				}
				break;
		}
		
		if (!@$where) {return array();}
		
		//if ( empty($where) ) $where = '1';
		
		switch ($ordering)
		{
				//case 'relevance': $order = ' ORDER BY score DESC, i.title ASC'; break;
				case 'oldest':   $order = 'i.created ASC'; break;
				case 'popular':  $order = 'i.hits DESC'; break;
				case 'alpha':    $order = 'i.title ASC'; break;
				case 'category': $order = 'c.title ASC, i.title ASC'; break;
				case 'newest':   $order = 'i.created DESC'; break;
				default:         $order = 'i.created DESC'; break;
		}
		
		
		
		// ****************************************************************************************
		// Create JOIN clause and WHERE clause part for filtering by current (viewing) access level
		// ****************************************************************************************
		$joinaccess	= '';
		$andaccess	= '';
		$select_access = '';
		
		// Extra access columns for main category and content type (item access will be added as 'access')
		$select_access .= ',  c.access as category_access, ty.access as type_access';
		
		if ( !$show_noauth ) {   // User not allowed to LIST unauthorized items
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$andaccess .= ' AND ty.access IN (0,'.$aid_list.')';
			$andaccess .= ' AND  c.access IN (0,'.$aid_list.')';
			$andaccess .= ' AND  i.access IN (0,'.$aid_list.')';
			$select_access .= ', 1 AS has_access';
		}
		else {
			// Access Flags for: content type, main category, item
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$select_access .= ', '
				.' CASE WHEN '
				.'  ty.access IN ('.$aid_list.') AND '
				.'   c.access IN ('.$aid_list.') AND '
				.'   i.access IN ('.$aid_list.') '
				.' THEN 1 ELSE 0 END AS has_access';
		}
		
		
		
		// **********************************************************************************************************************************************************
		// Create WHERE clause part for filtering by current active language, and current selected contend types ( !! although this is possible via a filter too ...)
		// **********************************************************************************************************************************************************
		
		$andlang = '';
		if (	$app->isClient('site') &&
					( FLEXI_FISH || (FLEXI_J16GE && $app->getLanguageFilter()) ) &&
					$filter_lang  // Language filtering enabled
		) {
			$andlang .= ' AND ( i.language LIKE ' . $db->Quote( $lang .'%' ) . ' OR i.language="*" ) ';
			$andlang .= ' AND ( c.language LIKE ' . $db->Quote( $lang .'%' ) . ' OR c.language="*" ) ';
		}
		
		// search articles
		$results = array();
		if ( $limit > 0)
		{
			$query	= $db->getQuery(true);
			$query->clear();
			$query->select(''
				.' i.id as id,'
				.' i.title AS title,'
				.' i.language AS language,'
				.' i.metakey AS metakey,'
				.' i.metadesc AS metadesc,'
				.' i.modified AS created,'     // TODO ADD a PARAMETER FOR CONTROLING the use of modified by or created by date as "created"
				.' c.title AS maincat_title, c.alias AS maincat_alias,'  // Main category data
				.' t.name AS tagname,'
				.' fir.value as field,'
				.' i.access, ie.type_id,'
				.' CONCAT(i.introtext, i.fulltext) AS text,'
				.' CONCAT_WS( " / ", '. $db->Quote( JText::_( 'FLEXICONTENT' ) ) .', c.title, i.title ) AS section,'
				.' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END AS slug,'
				.' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END AS catslug,'
				.' "2" AS browsernav'
				. $select_access
				);
			$query->from('#__content AS i '
				.' JOIN #__categories AS c ON i.catid = c.id'
				.' JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id'
				.' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
				// searching into text-like fields
				.' LEFT JOIN #__flexicontent_fields_item_relations AS fir ON i.id = fir.item_id'
				.' LEFT JOIN #__flexicontent_fields AS f ON fir.field_id = f.id'
				// searching into 'tags' field
				.' LEFT JOIN #__flexicontent_tags_item_relations AS tir ON i.id = tir.itemid'
				.' LEFT JOIN #__flexicontent_tags AS t ON tir.tid = t.id	'
				. $joinaccess
				);
			$query->where(' ('. $where .') ' 
				.' AND ie.type_id IN('.$types.') '
				.' AND i.state IN (1, -5) AND c.published = 1 '
				.' AND (i.publish_up IS NULL OR i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= ' . $_nowDate . ') '
				.' AND (i.publish_down IS NULL OR i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= ' . $_nowDate . ') '
				. $andaccess // Filter by user access
				. $andlang   // Filter by current language
				); 
			$query->group('i.id');
			$query->order($order);
			//echo "<pre style='white-space:normal!important;'>".$query."</pre>";
			
			$list = $db->setQuery($query, 0, $limit)->loadObjectList();

			if ($list)
			{
				$item_cats = FlexicontentFields::_getCategories($list);
				$typeData = flexicontent_db::getTypeData();
				
				foreach($list as $key => $item)
				{
					// echo $item->title." ".$item->tagname."<br/>"; // Before checking for noHTML
					$item->categories = isset($item_cats[$item->id])  ?  $item_cats[$item->id] : array();  // in case of item categories missing
					
					// If joomla article view is allowed allowed and then search view may optional create Joomla article links
					if( $typeData[$item->type_id]->params->get('allow_jview', 0) == 1 && $typeData[$item->type_id]->params->get('search_jlinks', 1) )
					{
						$item->href = JRoute::_(ContentHelperRoute::getArticleRoute($item->slug, $item->catslug, $item->language));
					}
					else
					{
						$item->href = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->catslug, 0, $item));
					}
					
					if (searchHelper::checkNoHTML($item, $searchText, array('title', 'metadesc', 'metakey', 'tagname', 'field', 'text' ))) {
						$results[$item->id] = $item;
					}
				}
			}
		}

		return $results;
	}
	
	
	// Also add J1.5 function signature
	/*function onSearch( $text, $phrase='', $ordering='', $areas=null )
	{
		return $this->onContentSearch( $text, $phrase, $ordering, $areas );
	}*/
}
