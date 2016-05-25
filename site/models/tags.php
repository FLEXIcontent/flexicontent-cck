<?php
/**
 * @version 1.5 stable $Id: tags.php 1876 2014-03-24 03:24:41Z ggppdk $
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

jimport('legacy.model.legacy');

/**
 * FLEXIcontent Component Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.5
 */
class FlexicontentModelTags extends JModelLegacy
{
	/**
	 * Current Tag properties
	 *
	 * @var mixed
	 */
	var $_tag = null;
	
	/**
	 * Item list data
	 *
	 * @var array
	 */
	var $_data = null;
	
	/**
	 * Items list total
	 *
	 * @var integer
	 */
	var $_total = null;
	
	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;
	
	/**
	 * Tags view parameters via menu item or via tag cloud module or ... via global configuration selected menu item
	 *
	 * @var object
	 */
	var $_params = null;
	
	/**
	 * Constructor
	 *
	 * @since 1.5
	 */
	function __construct()
	{
		parent::__construct();
		
		// Set id and load parameters
		$id = JRequest::getInt('id', 0);		
		$this->setId((int)$id);
		$cparams = & $this->_params;
		
		// Set the pagination variables into state (We get them from http request OR use view's parameters)
		$limit = strlen(JRequest::getVar('limit')) ? JRequest::getInt('limit') : $this->_params->get('limit');
		$limitstart	= JRequest::getInt('limitstart', JRequest::getInt('start', 0, '', 'int'), '', 'int');
		
		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );
		JRequest::setVar('limitstart', $limitstart);  // Make sure it is limitstart is set
		JFactory::getApplication()->input->set('limitstart', $limitstart);
		
		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
		
		// Set filter order variables into state
		$this->setState('filter_order', JRequest::getCmd('filter_order', 'i.modified', 'default'));
		$this->setState('filter_order_Dir', JRequest::getCmd('filter_order_Dir', 'DESC', 'default'));
	}
	
	
	/**
	 * Method to set initialize data, setting an element id for the view
	 *
	 * @access	public
	 * @param	int
	 */
	function setId($id)
	{
		// Set new tag ID, wipe member variables and load parameters
		$this->_id      = $id;
		$this->_tag     = null;
		$this->_data    = null;
		$this->_total   = null;
		$this->_pagination = null;
		$this->_params  = null;
		$this->_loadParams();
	}
	
	
	/**
	 * Overridden get method to get properties from the tag
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return 	mixed 				The value of the property
	 * @since	1.5
	 */
	function get($property, $default=null)
	{
		if ( $this->_tag || $this->_tag = $this->getTag() ) {
			if(isset($this->_tag->$property)) {
				return $this->_tag->$property;
			}
		}
		return $default;
	}
	
	/**
	 * Overridden set method to pass properties on to the tag
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function set( $property, $value=null )
	{
		if ( $this->_tag || $this->_tag = $this->getTag() ) {
			$this->_tag->$property = $value;
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Method to get Data
	 *
	 * @access public
	 * @return object
	 */
	function getData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			// Query the content items
			$query = $this->_buildQuery();
			$this->_data = $this->_getList( $query, $this->getState('limitstart'), $this->getState('limit') );
			// Get Original content ids for creating some untranslatable fields that have share data (like shared folders)
			flexicontent_db::getOriginalContentItemids($this->_data);
		}
		
		return $this->_data;
	}
	
	
	/**
	 * Method to get the total number of items
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}
	
	
	/**
	 * Method to get the pagination object
	 *
	 * @access	public
	 * @return	object
	 */
	public function getPagination()
	{
		// Load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			//jimport('cms.pagination.pagination');
			require_once (JPATH_COMPONENT.DS.'helpers'.DS.'pagination.php');
			$this->_pagination = new FCPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}
		return $this->_pagination;
	}
	
	
	/**
	 * Method to build the query
	 *
	 * @access public
	 * @return string
	 */
	function _buildQuery()
	{   	
		$user		= JFactory::getUser();
		$cparams = & $this->_params;
		
		// show unauthorized items
		$show_noauth = $cparams->get('show_noauth', 0);
		
		// Select only items that user has view access, if listing of unauthorized content is not enabled
		$joinaccess	 = '';
		$andaccess   = '';
		$select_access  = '';
		
		// Extra access columns for main category and content type (item access will be added as 'access')
		$select_access .= ', c.access as category_access, ty.access as type_access';
		
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
				.'  ty.access IN (0,'.$aid_list.') AND '
				.'   c.access IN (0,'.$aid_list.') AND '
				.'   i.access IN (0,'.$aid_list.') '
				.' THEN 1 ELSE 0 END AS has_access';
		}
		
		// Create sql WHERE clause
		$where = $this->_buildItemWhere();
		
		// Create sql ORDERBY clause -and- set 'order' variable (passed by reference), that is, if frontend user ordering override is allowed
		$order = '';
		$orderby = $this->_buildItemOrderBy($order);
		$orderby_join = '';
		$orderby_col = '';
		
		// Create JOIN for ordering items by a custom field (Level 1)
		if ( 'field' == $order[1] ) {
			$orderbycustomfieldid = (int)$cparams->get('orderbycustomfieldid', 0);
			$orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.$orderbycustomfieldid;
		}
		if ( 'custom:' == substr($order[1], 0, 7) ) {
			$order_parts = preg_split("/:/", $order[1]);
			$_field_id = (int) @ $order_parts[1];
			if ($_field_id && count($order_parts)==4) $orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.$_field_id;
		}
		
		// Create JOIN for ordering items by a custom field (Level 2)
		if ( 'field' == $order[2] ) {
			$orderbycustomfieldid_2nd = (int)$cparams->get('orderbycustomfieldid'.'_2nd', 0);
			$orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f2 ON f2.item_id = i.id AND f2.field_id='.$orderbycustomfieldid_2nd;
		}
		if ( 'custom:' == substr($order[2], 0, 7) ) {
			$order_parts = preg_split("/:/", $order[2]);
			$_field_id = (int) @ $order_parts[1];
			if ($_field_id && count($order_parts)==4) $orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f2 ON f2.item_id = i.id AND f2.field_id='.$_field_id;
		}
		
		// Create JOIN for ordering items by author's name
		if ( in_array('author', $order) || in_array('rauthor', $order) ) {
			$orderby_col   = '';
			$orderby_join .= ' LEFT JOIN #__users AS u ON u.id = i.created_by';
		}
		
		// Create JOIN for ordering items by a most commented
		if ( in_array('commented', $order) ) {
			$orderby_col   = ', COUNT(DISTINCT com.id) AS comments_total';
			$orderby_join .= ' LEFT JOIN #__jcomments AS com ON com.object_id = i.id AND com.object_group="com_flexicontent" AND com.published="1"';
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
		
		$query = 'SELECT i.id, i.*, ie.* '
			. $orderby_col
			. $select_access
			. ', c.title AS maincat_title, c.alias AS maincat_alias'  // Main category data
			. ', CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug'
			. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ' FROM #__content AS i'
			. ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' JOIN #__flexicontent_tags_item_relations AS tag ON tag.itemid = i.id'
			. ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			. ' JOIN #__categories AS c ON c.id = i.catid'
			. $orderby_join
			. $joinaccess
			. $where
			. $andaccess
			. ' GROUP BY i.id'
			. $orderby
			;
		return $query;
	}
	
	
	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildItemOrderBy(& $order='')
	{
		$request_var = $this->_params->get('orderby_override', 0) || $this->_params->get('orderby_override_2nd', 0) ? 'orderby' : '';
		$default_order = $this->getState('filter_order');
		$default_order_dir = $this->getState('filter_order_Dir');
		
		// Precedence: $request_var ==> $order ==> $config_param ==> $default_order
		return flexicontent_db::buildItemOrderBy(
			$this->_params,
			$order, $request_var, $config_param='orderby',
			$item_tbl_alias = 'i', $relcat_tbl_alias = 'rel',
			$default_order, $default_order_dir, $sfx='', $support_2nd_lvl=true
		);
	}
	
	
	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildItemWhere( )
	{
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		
		// Get the view's parameters
		$cparams = $this->_params;
		
		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		//  thus the items are published globally at the time the author specified in his/her local clock
		//$app  = JFactory::getApplication();
		//$now  = FLEXI_J16GE ? $app->requestTime : $app->get('requestTime');   // NOT correct behavior it should be UTC (below)
		//$date = JFactory::getDate();
		//$now  = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();              // NOT good if string passed to function that will be cached, because string continuesly different
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate = $db->getNullDate();
		
		// First thing we need to do is to select only the requested TAGGED items
		$where = ' WHERE tag.tid = '.$this->_id;
		
		// User current language
		$lang = flexicontent_html::getUserCurrentLang();
		$filtertag = $cparams->get('filtertag', 0);
		
		// Filter the tag view with the active language
		if ($filtertag)
		{
			$lta = FLEXI_J16GE ? 'i': 'ie';
			$where .= ' AND ( '.$lta.'.language LIKE ' . $db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR '.$lta.'.language="*" ' : '') . ' ) ';
		}
		
		// Get privilege to view non viewable items (upublished, archived, trashed, expired, scheduled).
		// NOTE:  ACL view level is checked at a different place
		$ignoreState = $user->authorise('flexicontent.ignoreviewstate', 'com_flexicontent');
		
		if (!$ignoreState) {
			// Limit by publication state. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( i.state IN (1, -5) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			
			// Limit by publish up/down dates. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' ) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';       //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			$where .= ' AND ( ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' ) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		}
		
		$where .= !FLEXI_J16GE ? ' AND i.sectionid = ' . FLEXI_SECTION : '';

		/*
		 * If we have a filter, and this is enabled... lets tack the AND clause
		 * for the filter onto the WHERE clause of the item query.
		 */
		
		// ****************************************
		// Create WHERE clause part for Text Search 
		// ****************************************
		
		$text = JRequest::getString('filter', JRequest::getString('q', ''), 'default');
		
		// Check for LIKE %word% search, for languages without spaces
		$filter_word_like_any = $cparams->get('filter_word_like_any', 0);
		
		$phrase = $filter_word_like_any ?
			JRequest::getWord('searchphrase', JRequest::getWord('p', 'any'),   'default') :
			JRequest::getWord('searchphrase', JRequest::getWord('p', 'exact'), 'default');
		
		$si_tbl = 'flexicontent_items_ext';
		
		$search_prefix = $cparams->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		$text = !$search_prefix  ?  trim( $text )  :  preg_replace('/(\b[^\s,\.]+\b)/u', $search_prefix.'$0', trim($text));
		$words = preg_split('/\s\s*/u', $text);
		
		if( strlen($text) )
		{
			$ts = 'ie';
			$escaped_text = $db->escape($text, true);
			$quoted_text = $db->Quote( $escaped_text, false );
			
			switch ($phrase)
			{
				case 'natural':
					$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.') ';
					break;
				
				case 'natural_expanded':
					$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' WITH QUERY EXPANSION) ';
					break;
				
				case 'exact':
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
					$stopwords = array();
					$shortwords = array();
					if (!$search_prefix) $words = flexicontent_db::removeInvalidWords($words, $stopwords, $shortwords, $si_tbl, 'search_index', $isprefix=1);
					JRequest::setVar('ignoredwords', implode(' ', $stopwords));
					JRequest::setVar('shortwords', implode(' ', $shortwords));
					
					$newtext = implode( '* ', $words ) . '*';
					$quoted_text = $db->escape($newtext, true);
					$quoted_text = $db->Quote( $quoted_text, false );
					$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					break;
			}
			
			$where .= ' AND '. $_text_match;
		}
		return $where;
	}
	
	
	/**
	 * Method to load parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadParams()
	{
		if ( $this->_params !== NULL ) return;
		
		$app  = JFactory::getApplication();
		$menu = $app->getMenu()->getActive();     // Retrieve active menu
		
		// Get the COMPONENT only parameter
		$params  = new JRegistry();
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$params->merge($cparams);
		
		// Merge the active menu parameters
		if ($menu)
		{
			$params->merge($menu->params);
		}
		
		// Merge module parameters overriding current configuration
		// (this done when module id is present in the HTTP request) (tags cloud module include tags view configuration)
		if ( JRequest::getInt('module', 0 ) )
		{
			// load by module name, not used
			//jimport('cms.module.helper');
			//$module_name = JRequest::getInt('module', 0 );
			//$module = JModuleHelper::getModule('mymodulename');
			
			// load by module id
			$module_id = JRequest::getInt('module', 0 );
			$module = JTable::getInstance ( 'Module', 'JTable' );
			
			if ( $module->load($module_id) ) {
				$moduleParams = new JRegistry($module->params);
				$params->merge($moduleParams);
			} else {
				JError::raiseNotice ( 500, $module->getError() );
			}
		}
		
		$this->_params = $params;
	}
	
	
	/**
	 * Method to get view's parameters
	 *
	 * @access public
	 * @return object
	 */
	function &getParams()
	{
		return $this->_params;
	}
	
	
	/**
	 * Method to load the Tag data
	 *
	 * @access public
	 * @return object
	 */
	function getTag()
	{
		//get categories
		$query = 'SELECT t.name, t.id,'
				. ' CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
				. ' FROM #__flexicontent_tags AS t'
				. ' WHERE t.id = '.(int)$this->_id
				. ' AND t.published = 1'
				;
		
		$this->_db->setQuery($query);
		$this->_tag = $this->_db->loadObject();       // Execute query to load tag properties
		if ( $this->_tag ) {
			$this->_tag->parameters = $this->_params;   // Assign tag parameters ( already load by setId() )
    }
    
		return $this->_tag;
	}
}
?>