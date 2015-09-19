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
		$app  = JFactory::getApplication();
		$view = JRequest::getCMD('view');
		$app->setUserState('fc_view_total_'.$view, 0);
		$app->setUserState('fc_view_limit_max_'.$view, 0);
		
		// Check if not search inside this search plugin areas
		if ( is_array($areas) && !array_intersect( $areas, array_keys($this->onContentSearchAreas()) ) )  return array();
		
		// Initialize some variables
		$db    = JFactory::getDBO();
		$user  = JFactory::getUser();
		$menu  = $app->getMenu()->getActive();
		
		// Get the COMPONENT only parameters and merge current menu item parameters
		$params = clone( JComponentHelper::getParams('com_flexicontent') );
		if ($menu) $params->merge($menu->params);
		
		// some parameter shortcuts for SQL query
		$show_noauth  = $params->get('show_noauth', 0);
		$orderby_override = $params->get('orderby_override', 1);
		
		// Compatibility text search (LIKE %word%) for language without spaces
		$filter_word_like_any = $params->get('filter_word_like_any', 0);
		
		// ************************************************
		// some parameter shortcuts common with search view
		// ************************************************
		$canseltypes  = $params->get('canseltypes', 1);
		$txtmode      = $params->get('txtmode', 0);  // 0: BASIC Index, 1: ADVANCED Index without search fields user selection, 2: ADVANCED Index with search fields user selection
		
		// Get if text searching according to specific (single) content type
		$show_txtfields = $params->get('show_txtfields', 1);  //0:hide, 1:according to content, 2:use custom configuration
		$show_txtfields = $txtmode ? 0 : $show_txtfields;  // disable this flag if using BASIC index for text search
		
		// Get if filtering according to specific (single) content type
		$show_filters   = $params->get('show_filters', 1);  //0:hide, 1:according to content, 2:use custom configuration
		
		// Force single type selection and showing the content type selector
		$type_based_search = ($show_filters==1 || $show_txtfields==1);
		$canseltypes = $type_based_search ? 1 : $canseltypes;
		
		
		
		// ********************************************************************
		// Get Content Types allowed for user selection in the Search Form
		// Also retrieve their configuration, plus the currently selected types
		// ********************************************************************
		// Get them from configuration
		$contenttypes = $params->get('contenttypes', array());
		
		// Sanitize them
		$contenttypes = !is_array($contenttypes)  ?  array($contenttypes)  :  $contenttypes;
		$contenttypes = array_unique(array_map('intval', $contenttypes));  // Make sure these are integers since we will be using them UNQUOTED
		
		// Force hidden content type selection if only 1 content type was initially configured
		$canseltypes = count($contenttypes)==1 ? 0 : $canseltypes;
		
		// Type data and configuration (parameters), if no content types specified then all will be retrieved
		$typeData = flexicontent_db::getTypeData( implode(",", $contenttypes) );
		$contenttypes = array();
		foreach($typeData as $tdata) $contenttypes[] = $tdata->id;
		
		// Get Content Types to use either those currently selected in the Search Form, or those hard-configured in the search menu item
		if ( $canseltypes ) {
			$form_contenttypes = JRequest::getVar('contenttypes', array());
			
			// Sanitize them
			$form_contenttypes = !is_array($form_contenttypes)  ?  array($form_contenttypes)  :  $form_contenttypes;
			$form_contenttypes = array_unique(array_map('intval', $form_contenttypes));  // Make sure these are integers since we will be using them UNQUOTED
			
			$contenttypes = array_intersect($contenttypes, $form_contenttypes);
		}
		
		// Check for zero content type (can occur during sanitizing content ids to integers)
		if ( !empty($contenttypes) ) {
			foreach($contenttypes as $i => $v) if (!strlen($contenttypes[$i])) unset($contenttypes[$i]);
		}
		
		// Type based seach, get a single content type (first one, if more than 1 were given ...)
		if ($type_based_search && !empty($contenttypes)) {
			$single_contenttype = reset($contenttypes);
			$contenttypes = array($single_contenttype);
		} else {
			$single_contenttype = false;
		}
		
		
		
		// *************************************
		// Text Search Fields of the search form
		// *************************************
		if ( !$txtmode ) {
			$txtflds = array();
			$fields_text = array();
		}
		
		else {
			$txtflds = '';
			if ( $show_txtfields ) {
				if ( $show_txtfields==1 ) {
					$txtflds = $single_contenttype ? $typeData[$single_contenttype]->params->get('searchable', '') : '';
				} else {
					$txtflds = $params->get('txtflds', '');
				}
			}
			
			// Sanitize them
			$txtflds = preg_replace("/[\"'\\\]/u", "", $txtflds);
			$txtflds = array_unique(preg_split("/\s*,\s*/u", $txtflds));
			if ( !strlen($txtflds[0]) ) unset($txtflds[0]);
			
			// Create a comma list of them
			$txtflds_list = count($txtflds) ? "'".implode("','", $txtflds)."'" : '';
			
			// Retrieve field properties/parameters, verifying the support to be used as Text Search Fields
			// This will return all supported fields if field limiting list is empty
			$fields_text = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', $txtflds_list, $contenttypes, $load_params=true, 0, 'search');
			if ( empty($fields_text) ) { // all entries of field limiting list were invalid , get ALL
				if( !empty($contenttypes) ) {
					$fields_text = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', null, $contenttypes, $load_params=true, 0, 'search');
				} else {
					$fields_text = array();
				}
			}
		}
		
		
		
		// ********************************
		// Filter Fields of the search form
		// ********************************
		// Get them from type configuration or from search menu item
		$filtflds = '';
		if ( $show_filters ) {
			if ( $show_filters==1 ) {
				$filtflds = $single_contenttype ? $typeData[$single_contenttype]->params->get('filters', '') : '';
			} else {
				$filtflds = $params->get('filtflds', '');
			}
		}
		
		// Sanitize them
		$filtflds = preg_replace("/[\"'\\\]/u", "", $filtflds);
		$filtflds = array_unique(preg_split("/\s*,\s*/u", $filtflds));
		if ( !strlen($filtflds[0]) ) unset($filtflds[0]);
		
		// Create a comma list of them
		$filtflds_list = count($filtflds) ? "'".implode("','", $filtflds)."'" : '';
		
		// Retrieve field properties/parameters, verifying the support to be used as Filter Fields
		// This will return all supported fields if field limiting list is empty
		if ( count($filtflds) ) {
			$filters_tmp = FlexicontentFields::getSearchFields($key='name', $indexer='advanced', $filtflds_list, $contenttypes, $load_params=true, 0, 'filter');
			
			// Use custom order
			$filters = array();
			if ($canseltypes && $show_filters) {
				foreach( $filtflds as $field_name) {
					if ( empty($filters_tmp[$field_name]) ) continue;
					$filter_id = $filters_tmp[$field_name]->id;
					$filters[$filter_id] = $filters_tmp[$field_name];
				}
			} else {
				foreach( $filters_tmp as $filter) {
					$filters[$filter->id] = $filter;  // index by filter_id in this case too (for consistency, although we do not use the array index ?)
				}
			}
			unset($filters_tmp);
		}
		
		// If configured filters were not found/invalid for the current content type(s)
		// then retrieve all fields marked as filterable for the give content type(s) this is useful to list per content type filters automatically, even when not set or misconfigured
		if ( empty($filters) ) {
			if( !empty($contenttypes) )
				$filters = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', null, $contenttypes, $load_params=true, 0, 'filter');
			else
				$filters = array();
		}
		
		
		
		// **********************
		// Load Plugin parameters
		// **********************
		
		$plugin = JPluginHelper::getPlugin('search', 'flexiadvsearch');
		$pluginParams = new JRegistry($plugin->params);
		
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
		// NOTE: the current date needs to use built-in MYSQL function, otherwise filter caching can not work because the CURRENT DATETIME is continuously different !!!
		//   $now = FLEXI_J16GE ? JFactory::getDate()->toSql() : JFactory::getDate()->toMySQL();
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
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
		
		$si_tbl = !$txtmode ? 'flexicontent_items_ext' : 'flexicontent_advsearch_index';
		$search_prefix = 'vvv';
		$text = preg_replace('/(\b[^\s]+\b)/', $search_prefix.'$0', trim($text));
		
		if( strlen($text) )
		{
			$ts = !$txtmode ? 'ie' : 'ts';
			$escaped_text = $db->escape($text, true);
			$quoted_text = $db->Quote( $escaped_text, false );
			
			switch ($phrase)
			{
				case 'natural':
					if ($filter_word_like_any) {
						$_text_match = ' LOWER ('.$ts.'.search_index) LIKE '.$db->Quote( '%'.$escaped_text.'%', false );
					} else {
						$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.') ';
					}
					break;
				
				case 'natural_expanded':
					$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' WITH QUERY EXPANSION) ';
					break;
				
				case 'exact':
					$words = preg_split('/\s\s*/u', $text);
					$stopwords = array();
					$shortwords = array();
					if (!$search_prefix) $words = flexicontent_db::removeInvalidWords($words, $stopwords, $shortwords, $si_tbl, 'search_index', $isprefix=0);
					if (empty($words)) {
						// All words are stop-words or too short, we could try to execute a query that only contains a LIKE %...% , but it would be too slow
						JRequest::setVar('ignoredwords', implode(' ', $stopwords));
						JRequest::setVar('shortwords', implode(' ', $shortwords));
						$_text_match = ' 0=1 ';
					} else {
						// speed optimization ... 2-level searching: first require ALL words, then require exact text
						$newtext = '+' . implode( ' +', $words );
						$quoted_text = $db->escape($newtext, true);
						$quoted_text = $db->Quote( $quoted_text, false );
						$exact_text  = $db->Quote( '%'. $escaped_text .'%', false );
						$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) AND '.$ts.'.search_index LIKE '.$exact_text;
					}
					break;
				
				case 'all':
					$words = preg_split('/\s\s*/u', $text);
					$stopwords = array();
					$shortwords = array();
					if (!$search_prefix) $words = flexicontent_db::removeInvalidWords($words, $stopwords, $shortwords, $si_tbl, 'search_index', $isprefix=1);
					JRequest::setVar('ignoredwords', implode(' ', $stopwords));
					JRequest::setVar('shortwords', implode(' ', $shortwords));
					
					$newtext = '+' . implode( '* +', $words ) . '*';
					$quoted_text = $db->escape($newtext, true);
					$quoted_text = $db->Quote( $quoted_text, false );
					$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					break;
				
				case 'any':
				default:
					if ($filter_word_like_any) {
						$_text_match = ' LOWER ('.$ts.'.search_index) LIKE '.$db->Quote( '%'.$escaped_text.'%', false );
					} else {
						$words = preg_split('/\s\s*/u', $text);
						$stopwords = array();
						$shortwords = array();
						if (!$search_prefix) $words = flexicontent_db::removeInvalidWords($words, $stopwords, $shortwords, $si_tbl, 'search_index', $isprefix=1);
						JRequest::setVar('ignoredwords', implode(' ', $stopwords));
						JRequest::setVar('shortwords', implode(' ', $shortwords));
						
						$newtext = implode( '* ', $words ) . '*';
						$quoted_text = $db->escape($newtext, true);
						$quoted_text = $db->Quote( $quoted_text, false );
						$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					}
					break;
			}
			
			// Construct TEXT SEARCH limitation SUB-QUERY (contained in a AND-WHERE clause)
			$text_where = ' AND '. $_text_match;
		} else {
			$text_where = '';
		}
		
		
		
		// *******************
		// Create ORDER clause
		// *******************
		
		// FLEXIcontent search view, use FLEXIcontent ordering
		$orderby_join = '';
		$orderby_col = '';
		if (JRequest::getVar('option') == 'com_flexicontent') {
			$order = '';
			$orderby = flexicontent_db::buildItemOrderBy(
				$params,
				$order, $_request_var='orderby', $_config_param='orderby',
				$_item_tbl_alias = 'i', $_relcat_tbl_alias = 'rel',
				$_default_order='', $_default_order_dir='', $sfx='', $support_2nd_lvl=false
			);
			
			// Create JOIN for ordering items by a custom field (Level 1)
			if ( 'field' == $order[1] ) {
				$orderbycustomfieldid = (int)$params->get('orderbycustomfieldid', 0);
				$orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.$orderbycustomfieldid;
			}
			
			// Create JOIN for ordering items by a custom field (Level 2)
			if ( 'field' == $order[2] ) {
				$orderbycustomfieldid_2nd = (int)$params->get('orderbycustomfieldid'.'_2nd', 0);
				$orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f2 ON f2.item_id = i.id AND f2.field_id='.$orderbycustomfieldid_2nd;
			}
			
			// Create JOIN for ordering items by author's name
			if ( in_array('author', $order) || in_array('rauthor', $order) ) {
				$orderby_col   = '';
				$orderby_join .= ' LEFT JOIN #__users AS u ON u.id = i.created_by';
			}
			
			// Create JOIN for ordering items by a most commented
			if ( in_array('commented', $order) ) {
				$orderby_col   = ', count(com.object_id) AS comments_total';
				$orderby_join .= ' LEFT JOIN #__jcomments AS com ON com.object_id = i.id';
			}
			
			// Create JOIN for ordering items by a most rated
			if ( in_array('rated', $order) ) {
				$orderby_col   = ', (cr.rating_sum / cr.rating_count) * 20 AS votes';
				$orderby_join .= ' LEFT JOIN #__content_rating AS cr ON cr.content_id = i.id';
			}
			
			// Create JOIN for ordering items by their ordering attribute (in item's main category)
		if ( in_array('order', $order) ) {
				$orderby_join .= ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id AND rel.catid = i.catid';
			}
		}
		
		// non-FLEXIcontent search view, use general ordering of search plugins (this is a parameter passed to this onContentSearch() function)
		else {
			switch ( $ordering )
			{
				//case 'relevance': $orderby = ' ORDER BY score DESC, i.title ASC'; break;
				case 'oldest':   $orderby = 'i.created ASC'; break;
				case 'popular':  $orderby = 'i.hits DESC'; break;
				case 'alpha':    $orderby = 'i.title ASC'; break;
				case 'category': $orderby = 'c.title ASC, i.title ASC'; break;
				case 'newest':   $orderby = 'i.created DESC'; break;
				default:         $orderby = 'i.created DESC'; break;
			}
			$orderby = ' ORDER BY '. $orderby;
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
		if (	$app->isSite() &&
					( FLEXI_FISH || (FLEXI_J16GE && $app->getLanguageFilter()) ) &&
					$filter_lang  // Language filtering enabled
		) {
			$andlang .= ' AND ( ie.language LIKE ' . $db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
		}
		
		// Filter by currently selected content types
		$andcontenttypes = count($contenttypes) ? ' AND ie.type_id IN ('. implode(",", $contenttypes) .') ' : '';
		
		
		
		// ***********************************************************************
		// Create the AND-WHERE clause parts for the currentl active Field Filters
		// ***********************************************************************
		
		$return_sql = 2;
		$filters_where = array();
		foreach($filters as $field)
		{
			// Get value of current filter, and SKIP it if value is EMPTY
			$filtervalue = JRequest::getVar('filter_'.$field->id, '');
			$empty_filtervalue_array  = is_array($filtervalue)  && !strlen(trim(implode('',$filtervalue)));
			$empty_filtervalue_string = !is_array($filtervalue) && !strlen(trim($filtervalue));
			if ($empty_filtervalue_array || $empty_filtervalue_string) continue;
			
			// Call field filtering of advanced search to find items matching the field filter (an SQL SUB-QUERY is returned)
			$field_filename = $field->iscore ? 'core' : $field->field_type;
			$filtered = FLEXIUtilities::call_FC_Field_Func($field_filename, 'getFilteredSearch', array( &$field, &$filtervalue, &$return_sql ));
			
			// An empty return value means no matching values were found
			$filtered = empty($filtered) ? ' AND 0 ' : $filtered;
			
			// A string mean a subquery was returned, while an array means that item ids we returned
			$filters_where[$field->id] = is_array($filtered) ?  ' AND i.id IN ('. implode(',', $filtered) .')' : $filtered;
			
			/*if ($filters_where[$field->id]) {
				echo "\n<br/>Filter:". $field->name ." : ";   print_r($filtervalue);
				echo "<br>".$filters_where[$field->id]."<br/>";
			}*/
		}
		//echo "\n<br/><br/>Filters Active: ". count($filters_where)."<br/>";
		//echo "<pre>"; print_r($filters_where);
		//exit;
		
		
		
		// ******************************************************
		// Create Filters JOIN clauses and AND-WHERE clause parts
		// ******************************************************
		
		// JOIN clause - USED - to limit returned 'text' to the text of TEXT-SEARCHABLE only fields ... (NOT shared with filters)
		if ( !$txtmode ) {
			$onBasic_textsearch    = $text_where;
			$onAdvanced_textsearch = '';
			$join_textsearch = '';
			$join_textfields = '';
		} else {
			$onBasic_textsearch    = '';
			$onAdvanced_textsearch = $text_where;
			$join_textsearch = ' JOIN #__flexicontent_advsearch_index as ts ON ts.item_id = i.id AND ts.field_id IN ('. implode(',',array_keys($fields_text)) .')';
			$join_textfields = ' JOIN #__flexicontent_fields as f ON f.id=ts.field_id';
		}
		
		// JOIN clauses ... (shared with filters)
		$join_clauses =  ''
			. ' JOIN #__categories AS c ON c.id = i.catid'
			. ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			;
		
		$join_clauses_with_text =  ''
			. ' JOIN #__categories AS c ON c.id = i.catid'
			. ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id' . $onBasic_textsearch
			. ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			. ($text_where ?
				$join_textsearch . $onAdvanced_textsearch .
				$join_textfields : '');
		
		// AND-WHERE sub-clauses ... (shared with filters)
		$where_conf = ' WHERE 1 '
			. ' AND i.state IN (1,-5'. ($search_archived ? ','.(FLEXI_J16GE ? 2:-1) :'' ) .') '
			. ' AND c.published = 1 '
			. ' AND ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' )'
			. ' AND ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' )'
			. $andaccess
			. $andlang
			. $andcontenttypes
			;
		
		// AND-WHERE sub-clauses for text search ... (shared with filters)
		$and_where_filters = count($filters_where) ? implode( " ", $filters_where) : '';
		
		
		// ************************************************
		// Set variables used by filters creation mechanism
		// ************************************************
		
		global $fc_searchview;
		$fc_searchview['join_clauses'] = $join_clauses;
		$fc_searchview['join_clauses_with_text'] = $join_clauses_with_text;
		$fc_searchview['where_conf_only'] = $where_conf;   // WHERE of the view (mainly configuration dependent)
		$fc_searchview['filters_where'] = $filters_where;  // WHERE of the filters
		$fc_searchview['search'] = $text_where;  // WHERE for text search
		$fc_searchview['params'] = $params; // view's parameters
		
		
		
		// *****************************************************************************************************
		// Execute search query.  NOTE this is skipped it if (a) no text-search and no (b) no filters are active
		// *****************************************************************************************************
		
		if ( !count($filters_where) && !strlen($text) && !strlen($andcontenttypes) ) return array();
		
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }
		
		
		// *****************************************
		// Overcome possible group concat limitation
		// *****************************************
		
		$query="SET SESSION group_concat_max_len = 9999999";
		$db->setQuery($query);
		$db->execute();
		
		
		
		// *************
		// Get the items
		// *************
		
		$query = 'SELECT SQL_CALC_FOUND_ROWS i.id'
			. $orderby_col
			. ' FROM #__content AS i'
			. $join_clauses_with_text
			. $orderby_join
			. $joinaccess
			. $where_conf
			. $and_where_filters 
			. ' GROUP BY i.id '
			. $orderby
		;
		//echo "Adv search plugin main SQL query: ".nl2br($query)."<br/><br/>";
		
		// NOTE: The plugin will return a PRECONFIGURED limited number of results, the SEARCH VIEW to do the pagination, splicing (appropriately) the data returned by all search plugins
		try {
			// Get items, we use direct query because some extensions break the SQL_CALC_FOUND_ROWS, so let's bypass them (at this point it is OK)
			// *** Usage of FOUND_ROWS() will fail when (e.g.) Joom!Fish or Falang are installed, in this case we will be forced to re-execute the query ...
			// PLUS, we don't need Joom!Fish or Falang layer at --this-- STEP which may slow down the query considerably in large sites
			$query_limited = $query . ' LIMIT '.$search_limit.' OFFSET 0';
			$rows = flexicontent_db::directQuery($query_limited);
			$item_ids = array();
			foreach ($rows as $row) $item_ids[] = $row->id;
			
			// Get current items total for pagination
			$db->setQuery("SELECT FOUND_ROWS()");
			$fc_searchview['view_total'] = $db->loadResult();
			$app->setUserState('fc_view_total_'.$view, $fc_searchview['view_total']);
		}
		catch (Exception $e) {
			// Get items via normal joomla SQL layer
			$db->setQuery(str_replace('SQL_CALC_FOUND_ROWS', '', $query), 0, $search_limit);
			$item_ids = $db->loadColumn(0);
		}
		
		if ( !count($item_ids) ) return array();  // No items found
		
		
		
		// *****************
		// Get the item data
		// *****************
		
		$query_data = 'SELECT i.id, i.title AS title, i.created, i.id AS fc_item_id, i.access, ie.type_id, i.language'
			. ( !$txtmode ?
				', ie.search_index AS text' :
				', GROUP_CONCAT(ts.search_index ORDER BY f.ordering ASC SEPARATOR \' \') AS text'
				)
			. ', CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug'
			. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ', CONCAT_WS( " / ", '. $db->Quote($searchFlexicontent) .', c.title, i.title ) AS section'
			. $select_access
			. ' FROM #__content AS i'
			. $join_clauses     // without on-join for basic text search
			. $join_textsearch  // without on-join for advanced text search
			. $join_textfields  // we need the text searchable fields to do ordering of text search fields above (minor effect)
			//. $orderby_join
			//. $joinaccess
			. ' WHERE i.id IN ('.implode(',',$item_ids).') '
			. ' GROUP BY i.id '
			. ' ORDER BY FIELD(i.id, '. implode(',', $item_ids) .')'
		;
		//echo nl2br($query)."<br/><br/>";
		
		$db->setQuery( $query_data );
		$list = $db->loadObjectList();
		if ($db->getErrorNum()) { echo $db->getErrorMsg(); }
		
		if ( $print_logging_info ) @$fc_run_times['search_query_runtime'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		
		
		// *************************************
		// Create item links and other variables
		// *************************************
		
		//echo "<pre>"; print_r($list); echo "</pre>";
		if( $list )
		{
			if ( count($list) >= $search_limit )
				$app->setUserState('fc_view_limit_max_'.$view, $search_limit);
			
			$item_cats = FlexicontentFields::_getCategories($list);
			foreach($list as $key => $item)
			{
				$item->text = preg_replace('/\b'.$search_prefix.'/', '', $item->text);
				$item->categories = isset($item_cats[$item->id])  ?  $item_cats[$item->id] : array();  // in case of item categories missing
				
				// If joomla article view is allowed allowed and then search view may optional create Joomla article links
				if( $typeData[$item->type_id]->params->get('allow_jview', 0) && $typeData[$item->type_id]->params->get('search_jlinks', 1) ) {
					$item->href = JRoute::_(ContentHelperRoute::getArticleRoute($item->slug, $item->categoryslug, $item->language));
				} else {
					$item->href = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item));
				}
				$item->browsernav = $browsernav;
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