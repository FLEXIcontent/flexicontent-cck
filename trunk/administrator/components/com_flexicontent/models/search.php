<?php
/**
 * @version		$Id$
 * @package		Joomla
 * @subpackage	Search
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters. All rights reserved.
 * @license		GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant to the
 * GNU General Public License, and as distributed it includes or is derivative
 * of works licensed under the GNU General Public License or other free or open
 * source software licenses. See COPYRIGHT.php for copyright notices and
 * details.
 */

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.model');
class FLEXIcontentModelSearch extends JModelLegacy
{
	/**
	 * Sezrch data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Search total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Search areas
	 *
	 * @var integer
	 */
	var $_areas = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Constructor
	 *
	 * @since 1.5
	 */
	function __construct() {
		parent::__construct();
		$option = 'com_flexicontent';
		$mainframe = &JFactory::getApplication();
		$limit		= $mainframe->getUserStateFromRequest( $option.'.search.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
		$limitstart = $mainframe->getUserStateFromRequest( $option.'.search.limitstart', 'limitstart', 0, 'int' );

		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}
	
	
	function getData() {
		if(empty($this->_data)) {
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			$db =& JFactory::getDBO();
			$db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $db->loadResult();
		}
		return $this->_data;
	}
	
	/**
	 * Method to get the number of relevant search index records
	 *
	 * @access	public
	 * @return	mixed	False on failure, integer on success.
	 * @since	1.0
	 */
	public function getCount() {
		// Lets load the Items if it doesn't already exist
		if (empty($this->_total)) {
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}
		return $this->_total;
	}
	
	/**
	 * Method to build the query for the retrieval of search index records
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildQuery() {
		$where		= $this->_buildWhere();
		$orderby	= $this->_buildOrderBy();
		
		$query = "SELECT SQL_CALC_FOUND_ROWS f.label, f.name, ai.*, a.title, a.id" ."\n"
			." FROM #__flexicontent_advsearch_index as ai" ."\n"
			." JOIN #__flexicontent_fields as f ON ai.field_id=f.id" ."\n"
			." JOIN #__content as a ON ai.item_id=a.id" ."\n"
			." JOIN #__flexicontent_items_ext as ext ON ext.item_id=a.id" ."\n"
			." JOIN #__flexicontent_fields_type_relations as rel ON (rel.field_id=ai.field_id AND rel.type_id=ext.type_id)" ."\n"
			.$where ."\n"
			.$orderby
		;
		//echo "<pre>"; die($query);
		return $query;
	}

	/**
	 * Method to build the orderby clause of the query for the search index
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildOrderBy()
	{
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.search.filter_order', 'filter_order', 'a.title', 'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.search.filter_order_Dir',	'filter_order_Dir',	'ASC', 'word' );
		
		$orderby = $filter_order.' '.$filter_order_Dir;
		$orderby = trim($orderby) ? $orderby : 'a.title ASC';
		$orderby = ' ORDER BY '.$orderby;

		return $orderby;
	}
	
	

	/**
	 * Method to build the where clause of the query for the fields
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildWhere()
	{
		static $where;
		if ( isset($where) ) return $where;
		
		$option = JRequest::getVar('option');
		$mainframe = &JFactory::getApplication();

		$filter_itemstate	= $mainframe->getUserStateFromRequest( $option.'.search.filter_itemstate', 'filter_itemstate', '', 'word' );
		$filter_itemtype	= $mainframe->getUserStateFromRequest( $option.'.search.filter_itemtype', 'filter_itemtype', '', 'int' );
		$filter_fieldtype = $mainframe->getUserStateFromRequest( $option.'.search.filter_fieldtype', 'filter_fieldtype', '', 'word' );
		$search_index 		= $mainframe->getUserStateFromRequest( $option.'.search.search_index', 'search_index', '', 'string' );
		$search_index 		= $this->_db->getEscaped( trim(JString::strtolower( $search_index ) ) );
		$search_itemtitle	= $mainframe->getUserStateFromRequest( $option.'.search.search_itemtitle', 'search_itemtitle', '', 'string' );
		$search_itemid		= $mainframe->getUserStateFromRequest( $option.'.search.search_itemid', 'search_itemid', '', 'int' );

		$where = array();

		if ( $filter_fieldtype ) {
			if ( $filter_fieldtype == 'C' ) {
				$where[] = 'f.iscore = 1';
			} else if ($filter_fieldtype == 'NC' ) {
				$where[] = 'f.iscore = 0';
			} else {
				$where[] = 'f.field_type = "'.$filter_fieldtype.'"';
			}
		}

		if ( $filter_itemstate ) {
			if ( $filter_itemstate == 'P' ) {
				$where[] = 'a.state IN (1, -5)';
			} else if ($filter_itemstate == 'U' ) {
				$where[] = 'a.state NOT IN (1, -5)';
			}
		}

		if ( $filter_itemtype ) {
			$where[] = 'ext.type_id = ' . $filter_itemtype;
		}

		if ($search_index) {
			$where[] = ' LOWER(ai.search_index) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $search_index, true ).'%', false );
		}
		
		if ($search_itemtitle) {
			$where[] = ' LOWER(a.title) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $search_itemtitle, true ).'%', false );
		}
		
		if ($search_itemid) {
			$where[] = ' a.id='.$search_itemid;
		}
		
		$where = ( count( $where ) ? implode( ' AND ', $where ) : '' );
		$where = trim($where) ? " WHERE ".$where : "";
		
		return $where;
	}	
	
	
	/**
	 * Method to get types list when performing an edit action
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ()
	{
		$query = 'SELECT id, name'
				. ' FROM #__flexicontent_types'
				. ' WHERE published = 1'
				. ' ORDER BY name ASC'
				;
		$this->_db->setQuery($query);
		$types = $this->_db->loadObjectList();
		return $types;	
	}
	
	
	/**
	 * Method to get list of field types used
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getFieldTypes ()
	{
		$query = 'SELECT field_type, count(id) as assigned'
				. ' FROM #__flexicontent_fields'
				. ' WHERE iscore=0 '
				. ' GROUP BY field_type'
				;
		$this->_db->setQuery($query);
		$fieldtypes = $this->_db->loadObjectList('field_type');
		return $fieldtypes;
	}
	
	
	/**
	 * Method to get a list pagination object.
	 *
	 * @access	public
	 * @return	object	A JPagination object.
	 * @since	1.0
	 */
	public function getPagination() {
		if (!empty($this->_pagination)) {
			return $this->_pagination;
		}
		jimport('joomla.html.pagination');
		$this->_pagination = new JPagination($this->getCount(), $this->getState('limitstart'), $this->getState('limit'));

		return $this->_pagination;
	}
	function getLimitStart() {
		$mainframe = &JFactory::getApplication();
		return $this->getState('limitstart', $mainframe->getCfg('list_limit'));
	}
	function purge() {
		$db = &JFactory::getDBO();
		$query = "TRUNCATE TABLE `#__flexicontent_advsearch_index`;";
		$db->setQuery($query);
		$db->query();
	}
}
