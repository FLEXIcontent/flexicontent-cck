<?php
/**
 * @version 1.5 stable $Id: favourites.php 1848 2014-02-16 12:03:55Z ggppdk $
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

jimport('joomla.application.component.model');

/**
 * FLEXIcontent Component Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.5
 */
class FlexicontentModelFavourites extends JModelLegacy
{
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
	 * Favourites view parameters via menu item
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
		$id = 0;  // no id used by this view
		$this->setId((int)$id);
		$params = & $this->_params;
		
		// Set the pagination variables into state (We get them from http request OR use default tags view parameters)
		$limit = JRequest::getVar('limit') ? JRequest::getVar('limit') : $params->get('limit');
		$limitstart = JRequest::getInt('limitstart');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
		
		// Get the filter request variables
		$this->setState('filter_order', 'i.modified');
		$this->setState('filter_order_dir', 'DESC');
	}
	
	
	/**
	 * Method to set initialize data, setting an element id for the view
	 *
	 * @access	public
	 * @param	int
	 */
	function setId($id)
	{
		//$this->_id      = $id;  // not used by current view
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
			$query = $this->_buildQuery();
			$this->_data = $this->_getList( $query, $this->getState('limitstart'), $this->getState('limit') );
			if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
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
	public function getPagination() {
		// Load the content if it doesn't already exist
		if (empty($this->_pagination)) {
			//jimport('joomla.html.pagination');
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
		$params = & $this->_params;
		
		// show unauthorized items
		$show_noauth = $params->get('show_noauth', 0);
		
		// Select only items that user has view access, if listing of unauthorized content is not enabled
		$joinaccess	 = '';
		$andaccess   = '';
		$select_access  = '';
		
		// Extra access columns for main category and content type (item access will be added as 'access')
		$select_access .= ', c.access as category_access, ty.access as type_access';
		
		if ( !$show_noauth ) {   // User not allowed to LIST unauthorized items
			if (FLEXI_J16GE) {
				$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
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
			// Access Flags for: content type, main category, item
			if (FLEXI_J16GE) {
				$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
				$aid_list = implode(",", $aid_arr);
				$select_access .= ', '
					.' CASE WHEN '
					.'  ty.access IN (0,'.$aid_list.') AND '
					.'   c.access IN (0,'.$aid_list.') AND '
					.'   i.access IN (0,'.$aid_list.') '
					.' THEN 1 ELSE 0 END AS has_access';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gt ON ty.id = gt.axo AND gt.aco = "read" AND gt.axosection = "type"';
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gc ON  c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gi ON  i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
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
		
		// Create sql WHERE clause
		$where = $this->_buildItemWhere();
		
		// Create sql ORDERBY clause -and- set 'order' variable (passed by reference), that is, if frontend user ordering override is allowed
		$order = '';
		$orderby = $this->_buildItemOrderBy($order);
		$orderby_join = '';
		$orderby_col = '';
		
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
		
		$query = 'SELECT i.id, i.*, ie.* '
			. $orderby_col
			. $select_access
			. ', CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug'
			. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ' FROM #__content AS i'
			. ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' JOIN #__flexicontent_favourites AS fav ON fav.itemid = i.id'
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
		$request_var = $this->_params->get('orderby_override') ? 'orderby' : '';
		$default_order = $this->getState('filter_order');
		$default_order_dir = $this->getState('filter_order_dir');
		
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
		$params = $this->_params;
		
		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		//  thus the items are published globally at the time the author specified in his/her local clock
		//$app  = JFactory::getApplication();
		//$now  = FLEXI_J16GE ? $app->requestTime : $app->get('requestTime');   // NOT correct behavior it should be UTC (below)
		//$date = JFactory::getDate();
		//$now  = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();              // NOT good if string passed to function that will be cached, because string continuesly different
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate = $db->getNullDate();
		
		// First thing we need to do is to select only the requested FAVOURED items
		$where = ' WHERE fav.userid = '.(int)$user->get('id');
		
		// Get privilege to view non viewable items (upublished, archived, trashed, expired, scheduled).
		// NOTE:  ACL view level is checked at a different place
		if ( FLEXI_J16GE )
			$ignoreState = $user->authorise('flexicontent.ignoreviewstate', 'com_flexicontent');
		else if (FLEXI_ACCESS)
			$ignoreState = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'ignoreviewstate', 'users', $user->gmid) : 1;
		else
			$ignoreState = $user->gid  > 19;  // author has 19 and editor has 20
		
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
		
		$text = JRequest::getString('filter', '', 'default');
		//$text = $this->_params->get('use_search') ? $text : '';
		$phrase = JRequest::getVar('searchphrase', 'exact', 'default');
		$si_tbl = 'flexicontent_items_ext';
		
		$text = trim( $text );
		if( strlen($text) )
		{
			$ts = 'ie';
			$escaped_text = FLEXI_J16GE ? $db->escape($text, true) : $db->getEscaped($text, true);
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
					$words = preg_split('/\s\s*/u', $text);
					$stopwords = array();
					$shortwords = array();
					$words = flexicontent_db::removeInvalidWords($words, $stopwords, $shortwords, $si_tbl, 'search_index', $isprefix=0);
					if (empty($words)) {
						// All words are stop-words or too short, we could try to execute a query that only contains a LIKE %...% , but it would be too slow
						JRequest::setVar('ignoredwords', implode(' ', $stopwords));
						JRequest::setVar('shortwords', implode(' ', $shortwords));
						$_text_match = ' 0=1 ';
					} else {
						// speed optimization ... 2-level searching: first require ALL words, then require exact text
						$newtext = '+' . implode( ' +', $words );
						$quoted_text = FLEXI_J16GE ? $db->escape($newtext, true) : $db->getEscaped($newtext, true);
						$quoted_text = $db->Quote( $quoted_text, false );
						$exact_text  = $db->Quote( '%'. $escaped_text .'%', false );
						$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) AND '.$ts.'.search_index LIKE '.$exact_text;
					}
					break;
				
				case 'all':
					$words = preg_split('/\s\s*/u', $text);
					$stopwords = array();
					$shortwords = array();
					$words = flexicontent_db::removeInvalidWords($words, $stopwords, $shortwords, $si_tbl, 'search_index', $isprefix=1);
					JRequest::setVar('ignoredwords', implode(' ', $stopwords));
					JRequest::setVar('shortwords', implode(' ', $shortwords));
					
					$newtext = '+' . implode( '* +', $words ) . '*';
					$quoted_text = FLEXI_J16GE ? $db->escape($newtext, true) : $db->getEscaped($newtext, true);
					$quoted_text = $db->Quote( $quoted_text, false );
					$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					break;
				
				case 'any':
				default:
					$words = preg_split('/\s\s*/u', $text);
					$stopwords = array();
					$shortwords = array();
					$words = flexicontent_db::removeInvalidWords($words, $stopwords, $shortwords, $si_tbl, 'search_index', $isprefix=1);
					JRequest::setVar('ignoredwords', implode(' ', $stopwords));
					JRequest::setVar('shortwords', implode(' ', $shortwords));
					
					$newtext = implode( '* ', $words ) . '*';
					$quoted_text = FLEXI_J16GE ? $db->escape($newtext, true) : $db->getEscaped($newtext, true);
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
		
		// Get the COMPONENT only parameters, then merge the menu parameters
		$comp_params = JComponentHelper::getComponent('com_flexicontent')->params;
		$params = FLEXI_J16GE ? clone ($comp_params) : new JParameter( $comp_params ); // clone( JComponentHelper::getParams('com_flexicontent') );
		if ($menu) {
			$menu_params = FLEXI_J16GE ? $menu->params : new JParameter($menu->params);
			$params->merge($menu_params);
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
}
?>