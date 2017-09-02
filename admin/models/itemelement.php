<?php
/**
 * @version 1.5 stable $Id: itemelement.php 1577 2012-12-02 15:10:44Z ggppdk $
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
defined('_JEXEC') or die('Restricted access');

jimport('legacy.model.legacy');
use Joomla\String\StringHelper;

/**
 * Flexicontent Component Itemelement Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelItemelement extends JModelLegacy
{
	/**
	 * rows array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Category total
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
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');

		$limit      = $app->getUserStateFromRequest( $option.'.'.$view.'.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( $option.'.'.$view.'.limitstart', 'limitstart', 0, 'int' );

		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get categories item data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		$user = JFactory::getUser();
		if ( !$user->id ) return array();  // catch case of guest user submitting in frontend
		
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
			
			$this->_db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $this->_db->loadResult();
		}
		return $this->_data;
	}

	/**
	 * Total nr
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		$user = JFactory::getUser();
		if ( !$user->id ) return array();  // catch case of guest user submitting in frontend
		
		// Lets load the content if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}
	
	
	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			require_once (JPATH_COMPONENT_SITE.DS.'helpers'.DS.'pagination.php');
			$this->_pagination = new FCPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}
	
	
	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	function _buildQuery()
	{
		$query = 'SELECT DISTINCT SQL_CALC_FOUND_ROWS rel.itemid, i.*, i.language AS lang'
			. ', u.name AS author, t.name AS type_name, CASE WHEN level.title IS NULL THEN CONCAT_WS(\'\', \'deleted:\', i.access) ELSE level.title END AS access_level'
			. ' FROM #__content AS i'
			. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
			. ' LEFT JOIN #__viewlevels as level ON level.id=i.access'
			. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			. ' LEFT JOIN #__flexicontent_types AS t ON t.id = ie.type_id'
			. ' LEFT JOIN #__categories AS c ON i.catid = c.id'
			. $this->_buildContentWhere()
			. $this->_buildContentOrderBy()
			;
		return $query;
	}
	
	
	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildContentOrderBy($query = null)
	{
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');
		
		$filter_order     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order',     'filter_order',     'i.ordering', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir', 'filter_order_Dir', '',           'cmd' );
		
		$orderby = $filter_order.' '.$filter_order_Dir . ($filter_order != 'i.ordering' ? ', i.ordering' : '');
		if ($query)
			$query->order($orderby);
		else
			return ' ORDER BY '. $orderby;
	}
	
	
	/**
	 * Build the where clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildContentWhere($query = null)
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');
		
		$assocs_id   = JRequest::getInt( 'assocs_id', 0 );
		
		if ($assocs_id)
		{
			$type_id     = $app->getUserStateFromRequest( $option.'.'.$view.'.type_id', 'type_id', 0, 'int' );
			$language    = $app->getUserStateFromRequest( $option.'.'.$view.'.language', 'language', '', 'string' );
			$created_by  = $app->getUserStateFromRequest( $option.'.'.$view.'.created_by', 'created_by', 0, 'int' );
			
			//$type_data = $this->getTypeData( $assocs_id, $type_id );
			
			$assocanytrans = $user->authorise('flexicontent.assocanytrans', 'com_flexicontent');
			if (!$assocanytrans && !$created_by) {
				$created_by = $user->id;
				$app->setUserState( $option.'.'.$view.'.created_by', $created_by );
			}
		}
		
		$filter_state  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_state', 'filter_state', '', 'cmd' );
		$filter_cats   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_cats',  'filter_cats',  0,  'int' );
		
		$filter_type   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_type',  'filter_type',  0,  'int' );
		$filter_lang   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_lang',  'filter_lang',  '', 'cmd' );
		$filter_author = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_author','filter_author','', 'cmd' );
		$filter_access = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_access','filter_access','', 'int' );
		
		$filter_type   = $assocs_id && $type_id    ? $type_id    : $filter_type;
		$filter_lang   = $assocs_id && $language   ? $language   : $filter_lang;
		$filter_author = $assocs_id && $created_by ? $created_by : $filter_author;
		
		$search = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 'search', '', 'string' );
		$search = StringHelper::trim( StringHelper::strtolower( $search ) );

		$where = array();
		if (!FLEXI_J16GE) $where[] = ' sectionid = ' . FLEXI_SECTION;
		
		// Filter by publications state
		if (is_numeric($filter_state)) {
			$where[] = 'i.state = ' . (int) $filter_state;
		}
		/*elseif ( $filter_state === '') {
			$where[] = 'i.state IN (0, 1)';
		}*/
		elseif ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$where[] = 'i.state = 1';
			} else if ($filter_state == 'U' ) {
				$where[] = 'i.state = 0';
			} else if ($filter_state == 'PE' ) {
				$where[] = 'i.state = -3';
			} else if ($filter_state == 'OQ' ) {
				$where[] = 'i.state = -4';
			} else if ($filter_state == 'IP' ) {
				$where[] = 'i.state = -5';
			} else if ($filter_state == 'A' ) {
				$where[] = 'i.state = 2';
			}
		}
		
		// Filter by Access level
		if ( $filter_access ) {
			$where[] = 'i.access = '.(int) $filter_access;
		}
		
		// Filter by assigned category
		if ( $filter_cats ) {
			$where[] = 'rel.catid = ' . $filter_cats;
		}
		
		// Filter by type.
		if ( $filter_type ) {
			$where[] = 'ie.type_id = ' . $filter_type;
		}
		
		// Filter by language
		if ( $filter_lang ) {
			$where[] = 'i.language = ' . $this->_db->Quote($filter_lang);
		}
		
		// Filter by author / owner
		if ( strlen($filter_author) ) {
			$where[] = 'i.created_by = ' . $filter_author;
		}
		
		// Implement View Level Access
		if (!$user->authorise('core.admin'))
		{
			$groups	= implode(',', JAccess::getAuthorisedViewLevels($user->id));
			$where[] = 'i.access IN ('.$groups.')';
		}
		
		// Filter by search word (can be also be  id:NN  OR author:AAAAA)
		if ( !empty($search) ) {
			if (stripos($search, 'id:') === 0) {
				$where[] = 'i.id = '.(int) substr($search, 3);
			}
			elseif (stripos($search, 'author:') === 0) {
				$search = $this->_db->Quote('%'.$this->_db->escape(substr($search, 7), true).'%');
				$where[] = '(u.name LIKE '.$search.' OR u.username LIKE '.$search.')';
			}
			else {
				$search = $this->_db->Quote('%'.$this->_db->escape($search, true).'%');
				$where[] = '(i.title LIKE '.$search.' OR i.alias LIKE '.$search.')';
			}
		}
		
		if ($query)
			foreach($where as $w) $query->where($w);
		else
			return count($where) ? ' WHERE '.implode(' AND ', $where) : '';
	}
	
	
	// ***********************************
	// *** MODEL SPECIFIC HELPER FUNCTIONS
	// ***********************************
	
	
	/**
	 * Method to get types list
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ( $type_ids=false, $check_perms = false, $published=true )
	{
		return flexicontent_html::getTypesList( $type_ids, $check_perms, $published);
	}
	
	
	/**
	 * Method to get author list for filtering
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getAuthorslist ()
	{
		$query = 'SELECT i.created_by AS id, u.name AS name'
				. ' FROM #__content AS i'
				. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
				. ' GROUP BY i.created_by'
				. ' ORDER BY u.name'
				;
		$this->_db->setQuery($query);

		return $this->_db->loadObjectList();
	}	
	
	
	/**
	 * Method to get typedata for specific item_id or by type_id
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypeData( $item_id, &$type_id=false )
	{
		if ( !$item_id && !$type_id )  return false;
		
		static $item_ids_type = array();
		if (!$type_id)
		{
			if ( !isset($item_ids_type[$item_id]) )
			{
				$this->_db->setQuery('SELECT type_id FROM #__flexicontent_items_ext WHERE item_id = '.$item_id);
				$item_ids_type[$item_id] = $this->_db->loadResult();
			}
			$type_id = $item_ids_type[$item_id];
		}
		if ( !$type_id )  return false;
		
		$type_data = $this->getTypeslist();
		return isset($type_data[$type_id])  ?  $type_data[$type_id]  :  false;
	}
}
