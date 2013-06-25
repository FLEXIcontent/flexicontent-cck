<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2011 flexicontent.org
 * @license GNU/GPL v3
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

jimport( 'joomla.plugin.plugin' );

require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

require_once(JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_search'.DS.'helpers'.DS.'search.php');

require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');


/**
 * FLEXIcontent Advanced Search plugin
 *
 * @package		Joomla.Plugin
 * @subpackage	Search.contacts
 * @since		1.6
 */
class plgSearchFlexiadvsearch extends JPlugin
{
	/**
	 * Constructor
	 *
	 * @access      protected
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 * @since       1.5
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$extension_name = 'plg_search_flexiadvsearch';
		//$this->loadLanguage();
		//$this->loadLanguage( '$extension_name, JPATH_ADMINISTRATOR);
		JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, 'en-GB'	, true);
		JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, null		, true);
	}
	
	
	// Also add J1.5 function signatures
	function onSearchAreas() { return $this->onContentSearchAreas(); }
	function onSearch( $text, $phrase='', $ordering='', $areas=null )  {  return $this->onContentSearch( $text, $phrase, $ordering, $areas );  }
	
	
	/**
	* @return array An array of search areas
	*/
	function onContentSearchAreas() {
		static $areas = array(
		'flexicontent' => 'FLEXICONTENT'
		);
		return $areas;
	}
	
	
	/**
	 * Search method
	 *
	 * The sql must return the following fields that are used in a common display routine:
	 *
	 *   href, title, section, created, text, browsernav
	 *
	 * @param string Target search string
	 * @param string matching option, natural|natural_expanded|exact|any|all
	 * @param string ordering option, newest|oldest|popular|alpha|category
	 * @param mixed An array if restricted to areas, null if search all
	 */
	function onContentSearch( $text, $phrase='', $ordering='', $areas=null )
	{
		// Check if not search inside this search plugin areas
		if ( is_array($areas) && !array_intersect( $areas, array_keys($this->onContentSearchAreas()) ) )  return array();
		
		// Initialize some variables
		$app   = JFactory::getApplication();
		$db    = JFactory::getDBO();
		$user  = JFactory::getUser();
		$menu  = $app->getMenu()->getActive();
		
		// Get the COMPONENT only parameters and merge current menu item parameters
		$params = clone( JComponentHelper::getParams('com_flexicontent') );
		if ($menu) $params->merge($menu->params);
		
		// some parameter shortcuts
		$show_noauth  = $params->get('show_noauth', 0);
		$canseltypes  = $params->get('canseltypes', 1);
		$txtmode      = $params->get('txtmode', 0);
		$orderby_override = $params->get('orderby_override', 1);
		
		
		
		// *****************
		// Get Content Types
		// *****************
		
		// Use HTTP request (if user is allowed to select them)
		if ( $canseltypes ) $contenttypes = JRequest::getVar('contenttypes', array());
		
		// Fallback to configuration if user did not set them in HTTP request (or if user is not allowed to use them)
		if( !$canseltypes || empty($contenttypes) )  $contenttypes = $params->get('contenttypes', array());
		
		// Sanitize them
		$contenttypes = !is_array($contenttypes)  ?  array($contenttypes)  :  $contenttypes;
		$contenttypes = array_unique(array_map('intval', $contenttypes));  // Make sure these are integers since we will be using them UNQUOTED
		
		// Create a comma list of them
		$contenttypes_list = count($contenttypes) ? "'".implode("','", $contenttypes)."'"  :  "";
		
		
		
		// *************************************
		// Text Search Fields of the search form
		// *************************************
		
		// Using Basic Search Index for Text Search
		if ( !$txtmode ) {
			$txtflds = array();
			$fields_text = array();
		}
		
		// Using Advanced Search Index for Text Search
		else {
			// Fallback to configuration if user did not set them in HTTP request (or if user is not allowed to use them)
			if( $txtmode==1 || empty($txtflds) ) $txtflds = $params->get('txtflds', '');
			
			// Use HTTP request (if user is allowed to select them)
			if ( $txtmode==2 ) {
				$txtflds = JRequest::getVar('txtflds', array());
				if ( is_array($txtflds) ) $txtflds = implode(',', $txtflds);
			}
			
			// Sanitize them
			$txtflds = preg_replace("/[\"'\\\]/u", "", $txtflds);
			$txtflds = array_unique(preg_split("/\s*,\s*/u", $txtflds));
			if ( !strlen($txtflds[0]) ) unset($txtflds[0]);
			
			// Create a comma list of them
			$txtflds_list = "'".implode("','", $txtflds)."'";
			
			// Retrieve field properties/parameters, verifying the support to be used as Text Search Fields
			// This will return all supported fields if field limiting list is empty
			$fields_text = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', $txtflds_list, $contenttypes, $load_params=true, 0, 'search');
			if ( !count($fields_text) )  // all entries of field limiting list were invalid , get ALL
				$fields_text = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', null, $contenttypes, $load_params=true, 0, 'search');
		}
		
		
		// ********************************
		// Filter Fields of the search form
		// ********************************
		
		// Get them from configuration
		$filtflds = $params->get('filtflds', '');
		
		// Sanitize them
		$filtflds = preg_replace("/[\"'\\\]/u", "", $filtflds);
		$filtflds = array_unique(preg_split("/\s*,\s*/u", $filtflds));
		if ( !strlen($filtflds[0]) ) unset($filtflds[0]);
		
		// Create a comma list of them
		$filtflds_list = "'".implode("','", $filtflds)."'";
		
		// Retrieve field properties/parameters, verifying the support to be used as Filter Fields
		// This will return all supported fields if field limiting list is empty
		if ( count($filtflds) )
			$fields_filter = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', $filtflds_list, $contenttypes, $load_params=true, 0, 'filter');
		else
			$fields_filter = array();
		//if ( !count($fields_filter) )  // all entries of field limiting list were invalid , get ALL
		//	$fields_filter = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', null, $contenttypes, $load_params=true, 0, 'filter');
		
		
		
		// **********************
		// Load Plugin parameters
		// **********************
		
		$plugin = JPluginHelper::getPlugin('search', 'flexiadvsearch');
		$pluginParams = FLEXI_J16GE ? new JRegistry($plugin->params) : new JParameter($plugin->params);
		
		// Shortcuts for plugin parameters
		$search_limit    = $params->get( 'search_limit', $pluginParams->get( 'search_limit', 20 ) );      // Limits the returned results of this seach plugin
		$filter_lang     = $params->get( 'filter_lang', $pluginParams->get( 'filter_lang', 1 ) );         // Language filtering enabled
		$search_archived = $params->get( 'search_archived', $pluginParams->get( 'search_archived', 1 ) ); // Include archive items into the search
		$browsernav      = $params->get( 'browsernav', $pluginParams->get( 'browsernav', 2 ) );           // Open search in window (for value 1)
		
		
		
		// ***************************************************************************************************************
		// Varous other variable USED in the SQL query like (a) current frontend language and (b) -this- plugin specific ordering, (c) null / now dates, (d) etc 
		// ***************************************************************************************************************
		
		// Get current frontend language (fronted user selected)
		$lang = flexicontent_html::getUserCurrentLang();
		
		// NULL and CURRENT dates, 
		// NOTE: the above current date is needs to use built-in MYSQL function, otherwise filter caching can not work because the CURRENT DATETIME is continuously different !!!
		//   $now = FLEXI_J16GE ? JFactory::getDate()->toSql() : JFactory::getDate()->toMySQL();
		//   $_now = $db->Quote( $now );
		$_now = 'UTC_TIMESTAMP()';
		$nullDate = $db->getNullDate();
		
		// Section name
		$searchFlexicontent = JText::_( 'FLEXICONTENT' );
		
		// REMOVED / COMMENTED OUT this feature:
		// Require any OR all Filters ... this can be user selectable
		//$show_filtersop = $params->get('show_filtersop', 1);
		//$default_filtersop = $params->get('default_filtersop', 'all');
		//$FILTERSOP = !$show_filtersop ? $default_filtersop : JRequest::getVar('filtersop', $default_filtersop);
		
		
		
		// ****************************************
		// Create WHERE clause part for Text Search 
		// ****************************************
		
		$text = trim( $text );
		if( strlen($text) )
		{
			$quoted_text = FLEXI_J16GE ? $db->escape($text, true) : $db->getEscaped($text, true);
			$quoted_text = $db->Quote( $quoted_text, false );
			
			switch ($phrase)
			{
				case 'natural':
					$_text_match  = ' MATCH (search_index) AGAINST ('.$quoted_text.') ';
					break;
				
				case 'natural_expanded':
					$_text_match  = ' MATCH (search_index) AGAINST ('.$quoted_text.' WITH QUERY EXPANSION) ';
					break;
				
				case 'exact':
					$_text_match  = ' MATCH (search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					break;
				
				case 'all':
					$words = explode( ' ', $text );
					$newtext = '+' . implode( '* +', $words ) .'*';
					$quoted_text = FLEXI_J16GE ? $db->escape($newtext, true) : $db->getEscaped($newtext, true);
					$quoted_text = $db->Quote( $quoted_text, false );
					$_text_match  = ' MATCH (search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					break;
				
				case 'any':
				default:
					$words = explode( ' ', $text );
					$newtext = implode( '* ', $words ) .'*';
					$quoted_text = FLEXI_J16GE ? $db->escape($newtext, true) : $db->getEscaped($newtext, true);
					$quoted_text = $db->Quote( $quoted_text, false );
					$_text_match  = ' MATCH (search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					break;
			}
			
			// Construct TEXT SEARCH limitation SUB-QUERY (contained in a AND-WHERE clause)
			if (!$txtmode)
				$_text_SQL = ' SELECT item_id FROM #__flexicontent_items_ext WHERE %s ';
			else
				$_text_SQL = ' SELECT item_id FROM #__flexicontent_advsearch_index WHERE %s AND field_id IN ('. implode(',',array_keys($fields_text)) .')';
			
			$text_where = ' AND i.id IN ( '. sprintf($_text_SQL, $_text_match) .')';
		} else {
			$text_where = '';
		}
		
		
		
		// *******************
		// Create ORDER clause
		// *******************
		
		// First try FLEXIcontent advanced search plugin specific ordering
		$orderby = $orderby_override  ?  JRequest::getWord( 'orderby', $params->get('orderby', '') )  :  $params->get('orderby', '');
		if ( $orderby ) {
				$order = flexicontent_db::buildItemOrderBy(
					$params, $ordering, $_request_var='orderby', $_config_param='orderby',
					$_item_tbl_alias = 'i', $_relcat_tbl_alias = 'rel',
					$_default_order='', $_default_order_dir=''
				);
		}
		
		// Second try to use general ordering of search plugins
		else {
			switch ( $orderby )
			{
				//case 'relevance': $order = ' ORDER BY score DESC, i.title ASC'; break;
				case 'oldest':   $order = 'i.created ASC'; break;
				case 'popular':  $order = 'i.hits DESC'; break;
				case 'alpha':    $order = 'i.title ASC'; break;
				case 'category': $order = 'c.title ASC, i.title ASC'; break;
				case 'newest':   $order = 'i.created DESC'; break;
				default:         $order = 'i.created DESC'; break;
			}
			$order = ' ORDER BY '. $order;
		}
		
		
		
		// ***************************************************************************************
		// Create JOIN clause and WHERE clause part for filtering by current access level (= view)
		// ***************************************************************************************
		$joinaccess	= '';
		$andaccess	= '';
		$select_access = '';
		if ( !$show_noauth ) {   // User not allowed to LIST unauthorized items
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$andaccess .= ' AND ty.access IN (0,'.$aid_list.')';
				$andaccess .= ' AND  c.access IN (0,'.$aid_list.')';
				$andaccess .= ' AND  i.access IN (0,'.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gt ON ty.id = gt.axo AND gt.aco = "read" AND gt.axosection = "type"';
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gc ON  c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gi ON  i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
					$andaccess	.= ' AND (gt.aro IN ( '.$user->gmid.' ) OR ty.access <= '. $aid . ')';
					$andaccess	.= ' AND (gc.aro IN ( '.$user->gmid.' ) OR  c.access <= '. $aid . ')';
					$andaccess  .= ' AND (gi.aro IN ( '.$user->gmid.' ) OR  i.access <= '. $aid . ')';
				} else {
					$andaccess  .= ' AND ty.access <= '.$aid;
					$andaccess  .= ' AND  c.access <= '.$aid;
					$andaccess  .= ' AND  i.access <= '.$aid;
				}
			}
			$select_access .= ', 1 AS has_access';
		}
		else {
			// Extra access columns for main category and content type (item access will be added as 'access')
			$select_access .= ',  c.access as category_access, ty.access as type_access';
			
			// Access Flags for: content type, main category, item
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$select_access .= ', '
					.' CASE WHEN '
					.'  ty.access IN ('.$aid_list.') AND '
					.'   c.access IN ('.$aid_list.') AND '
					.'   i.access IN ('.$aid_list.') '
					.' THEN 1 ELSE 0 END AS has_access';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$select_access .= ', '
						.' CASE WHEN '
						.'  (gt.aro IN ( '.$user->gmid.' ) OR ty.access <= '. (int) $aid . ') AND '
						.'  (gc.aro IN ( '.$user->gmid.' ) OR  c.access <= '. (int) $aid . ') AND '
						.'  (gi.aro IN ( '.$user->gmid.' ) OR  i.access <= '. (int) $aid . ') '
						.' THEN 1 ELSE 0 END AS has_access';
				} else {
					$select_access .= ', '
						.' CASE WHEN '
						.'  (ty.access <= '. (int) $aid . ') AND '
						.'  ( c.access <= '. (int) $aid . ') AND '
						.'  ( i.access <= '. (int) $aid . ') '
						.' THEN 1 ELSE 0 END AS has_access';
				}
			}
		}
		
		
		
		// **********************************************************************************************************************************************************
		// Create WHERE clause part for filtering by current active language, and current selected contend types ( !! although this is possible via a filter too ...)
		// **********************************************************************************************************************************************************
		
		$andlang = '';
		if (	$app->isSite() &&
					( FLEXI_FISH || (FLEXI_J16GE && $app->getLanguageFilter()) ) &&
					$filter_lang  // Language filtering enabled
		) {
			$andlang .= ' AND ( ie.language LIKE ' . $db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
		}
		
		// Filter by currently selected content types
		$andcontenttypes = count($contenttypes) ? ' AND ie.type_id IN ('.$contenttypes_list.') ' : '';
		
		
		
		// ***********************************************************************
		// Create the AND-WHERE clause parts for the currentl active Field Filters
		// ***********************************************************************
		
		$return_sql = true;
		$filters_where = array();
		foreach($fields_filter as $field)
		{
			// Get value of current filter, and SKIP it if value is EMPTY
			$filtervalue = JRequest::getVar('filter_'.$field->id, '');
			$empty_filtervalue_array  = is_array($filtervalue)  && !strlen(trim(implode('',$filtervalue)));
			$empty_filtervalue_string = !is_array($filtervalue) && !strlen(trim($filtervalue));
			if ($empty_filtervalue_array || $empty_filtervalue_string) continue;
			
			// Call field filtering of advanced search to find items matching the field filter (an SQL SUB-QUERY is returned)
			$field_filename = $field->iscore ? 'core' : $field->field_type;
			$filters_where[$field->id] = FLEXIUtilities::call_FC_Field_Func($field_filename, 'getFilteredSearch', array( &$field, &$filtervalue, &$return_sql ));
			
			//echo "\n<br/>Field name:". $field->name ." : ";   print_r($filtervalue);
			//if ($filters_where[$field->id]) echo "<br>".$filters_where[$field->id]."<br/>";
		}
		//echo "\n<br/><br/>Filters Active: ". count($filters_where)."<br/>";
		
		
		
		// ******************************************************
		// Create Filters JOIN clauses and AND-WHERE clause parts
		// ******************************************************
		
		// JOIN clauses ... (shared with filters)
		$join_clauses =  ''
			. ' JOIN #__categories AS c ON c.id = i.catid'
			. ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			. ( $txtmode ? ' JOIN #__flexicontent_fields as f ON f.id=ai.field_id' : '' )
			. $joinaccess
			;
		
		// AND-WHERE sub-clauses ... (shared with filters)
		$and_where =  ' 1 '
			. ' AND i.state IN (1,-5,'. ($search_archived ? (FLEXI_J16GE ? 2:-1) :'' ) .') '
			. ' AND c.published = 1 '
			. ' AND ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_now.' )'
			. ' AND ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_now.' )'
			. $andaccess
			. $andlang
			. $andcontenttypes
			;
		
		// AND-WHERE sub-clauses for text search ... (shared with filters)
		$and_where_text_n_filters  = $text_where;
		$and_where_text_n_filters .= count($filters_where) ? implode( " ", $filters_where) : '';
		
		// JOIN clause - USED - to limit returned 'text' to the text of TEXT-SEARCHABLE only fields ... (NOT shared with filters)
		if ( !$txtmode )
			$join_textsearch = '';
		else
			$join_textsearch = ' JOIN #__flexicontent_advsearch_index as ai ON ai.item_id = i.id AND ai.field_id IN ('. implode(',',array_keys($fields_text)) .')';
		
		
		// ************************************************
		// Set variables used by filters creation mechanism
		// ************************************************
		
		global $fc_searchview;
		$fc_searchview['join_clauses'] = $join_clauses;
		$fc_searchview['where_conf_only'] = $and_where;    // WHERE of the view (mainly configuration dependent)
		$fc_searchview['filters_where'] = $filters_where;  // WHERE of the filters
		$fc_searchview['filters_where']['txtsearch'] = $text_where;      // WHERE of text search
		$fc_searchview['params'] = $params; // view's parameters
		
		
		
		// *****************************************************************************************************
		// Execute search query.  NOTE this is skipped it if (a) no text-search and no (b) no filters are active
		// *****************************************************************************************************
		
		if ( !count($filters_where) & !strlen($text) ) return array();
		
		// Overcome possible group concat limitation
		$query="SET SESSION group_concat_max_len = 9999999";
		$db->setQuery($query);
		$db->query();
		
		// Construct query's SQL
		$query 	= 'SELECT i.id, i.title AS title, i.sectionid, i.created, i.id AS fc_item_id,'
			. ( !$txtmode ?
				' ie.search_index AS text,' :
				//' GROUP_CONCAT(\'[[[b]]]\', f.label, \'[[[/b]]]: \', ai.search_index ORDER BY f.ordering ASC SEPARATOR \' [[[br/]]]\') AS text,'
				' GROUP_CONCAT(ai.search_index ORDER BY f.ordering ASC SEPARATOR \' \') AS text,'
				)
			. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
			. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug,'
			. ' CONCAT_WS( " / ", '. $db->Quote($searchFlexicontent) .', c.title, i.title ) AS section'
			. $select_access
			. ' FROM #__content AS i'
			. $join_textsearch
			. $join_clauses
			. ' WHERE '
			. $and_where
			. $and_where_text_n_filters 
			. ' GROUP BY i.id '
			. $order
		;
		
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }
		
		// Execute query ... NOTE: The plugin will return a PRECONFIGURED limited number of results (more),
		// it is the responsibility of the SEARCH VIEW to do the pagination, splicing (appropriately) the data returned by all search plugins.
		$db->setQuery( $query, 0, $search_limit );
		$list = $db->loadObjectList();
		if ($db->getErrorNum()) { echo $db->getErrorMsg(); }
		
		if ( $print_logging_info ) @$fc_run_times['search_query_runtime'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		//echo "<br>".$query."<br><br>\n";
		//echo "<pre>"; print_r($list); echo "</pre>";
		
		// Create item links and other variables
		if( $list ) {
			if ( count($list) < $search_limit ) $app->setUserState('fc_view_limit_max', 0);
			else $app->setUserState('fc_view_limit_max', $search_limit);
			
			$item_cats = FlexicontentFields::_getCategories($list);
			foreach($list as $key => $item)
			{
				if( FLEXI_J16GE || $item->sectionid==FLEXI_SECTION ) {
					$item->categories = $item_cats[$item->id];
					$list[$key]->href = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug));
				} else {
					$list[$key]->href = JRoute::_(ContentHelperRoute::getArticleRoute($item->slug, $item->catslug, $item->sectionid));
				}
				$list[$key]->browsernav = $browsernav;
			}
		}
		
		return $list;
	}
}



// Following code is when not having exactly named CLASS function.
// NOTE: in J1.5 (only) the triggerEvent() checks if functions being registered as Event Listener methods also exists outside the class, so we 
// must define wrapper classes outside the class, these can be used by triggerEvent and will only contain a call to the respective class method

// A different approach is to create wrapper class methods, that have the name of the event, we did this above

/*

// Wrapper class for J1.5 to RETURN SEARCH AREAS supported by this search plugin
if (!function_exists('onContentSearchAreas')) {
	function onContentSearchAreas() {
		//return plgSearchFlexiadvsearch::onContentSearchAreas();
	}
}

// Wrapper class for J1.5 to RETURN SEARCH RESULTS found by this search plugin
if (!function_exists('onContentSearch')) {
	function onContentSearch( $text, $phrase='', $ordering='', $areas=null ) {
		//return plgSearchFlexiadvsearch::onContentSearch( $text, $phrase, $ordering, $areas );
	}
}

$app = JFactory::getApplication();
if (!FLEXI_J16GE) {
	$app->registerEvent( 'onSearchAreas', 'onContentSearchAreas' );
	$app->registerEvent( 'onSearch', 'onContentSearch');
}
*/

?>