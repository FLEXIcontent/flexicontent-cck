<?php
/**
 * @version		$Id: search.php 1699 2013-07-30 04:29:37Z ggppdk $
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

jimport('legacy.model.legacy');
use Joomla\String\StringHelper;


/**
 * FLEXIcontent Component Search Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		2.0
 */
class FLEXIcontentModelSearch extends JModelLegacy
{
	/**
	 * view's rows
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * rows total
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
	function __construct()
	{
		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
		parent::__construct();
		
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$fcform = $jinput->get('fcform', 0, 'int');
		$p      = $option.'.'.$view.'.';
		
		// Parameters of the view, in our case it is only the component parameters
		$this->cparams = JComponentHelper::getParams( 'com_flexicontent' );
		
		
		
		// **************
		// view's Filters
		// **************
		
		// Various filters
		$filter_itemstate = $fcform ? $jinput->get('filter_itemstate', '', 'cmd')     :  $app->getUserStateFromRequest( $p.'filter_itemstate', 'filter_itemstate', '', 'cmd' );
		$filter_itemtype  = $fcform ? $jinput->get('filter_itemtype',  0,  'int')     :  $app->getUserStateFromRequest( $p.'filter_itemtype',  'filter_itemtype',   0, 'int' );
		$filter_fieldtype = $fcform ? $jinput->get('filter_fieldtype', '', 'cmd')     :  $app->getUserStateFromRequest( $p.'filter_fieldtype', 'filter_fieldtype', '', 'cmd' );
		$search_itemtitle = $fcform ? $jinput->get('search_itemtitle', '', 'string')  :  $app->getUserStateFromRequest( $p.'search_itemtitle', 'search_itemtitle', '', 'string' );
		$search_itemid    = $fcform ? $jinput->get('search_itemid',     0, 'int')     :  $app->getUserStateFromRequest( $p.'search_itemid',    'search_itemid',     0, 'int' );
		
		$this->setState('filter_itemstate', $filter_itemstate);
		$this->setState('filter_itemtype', $filter_itemtype);
		$this->setState('filter_fieldtype', $filter_fieldtype);
		$this->setState('search_itemtitle', $search_itemtitle);
		$this->setState('search_itemid', $search_itemid);
		
		$app->setUserState($p.'filter_itemstate', $filter_itemstate);
		$app->setUserState($p.'filter_itemtype', $filter_itemtype);
		$app->setUserState($p.'filter_fieldtype', $filter_fieldtype);
		$app->setUserState($p.'search_itemtitle', $search_itemtitle);
		$app->setUserState($p.'search_itemid', $search_itemid);
		
		
		// Type of search index being listed
		$indexer = $app->getUserStateFromRequest( $p.'indexer', 'indexer', '', 'cmd' );
		if ($indexer) {
			$filter_indextype = $indexer;
			$app->setUserState('indexer', '');
		} else {
			$filter_indextype = $fcform ? $jinput->get('filter_indextype',  'advanced', 'cmd')  :  $app->getUserStateFromRequest( $p.'filter_indextype', 'filter_indextype', 'advanced', 'cmd' );
		}
		$this->setState('filter_indextype', $filter_indextype);
		$app->setUserState($p.'filter_indextype', $filter_indextype);
		$isADV = $filter_indextype=='advanced';
		
		
		// Text search
		$search = $fcform ? $jinput->get('search', '', 'string')  :  $app->getUserStateFromRequest( $p.'search',  'search',  '',  'string' );
		$this->setState('search', $search);
		$app->setUserState($p.'search', $search);
		
		
		// ****************************************
		// Ordering: filter_order, filter_order_Dir
		// ****************************************
		
		$default_order     = $this->cparams->get('search_manager_order', 'a.title');  // Parameter does not exist
		$default_order_dir = $this->cparams->get('search_manager_order_dir', 'ASC');  // Parameter does not exist
				
		$filter_order      = $fcform ? $jinput->get('filter_order',     $default_order,      'cmd')  :  $app->getUserStateFromRequest( $p.'filter_order',     'filter_order',     $default_order,      'cmd' );
		$filter_order_Dir  = $fcform ? $jinput->get('filter_order_Dir', $default_order_dir, 'word')  :  $app->getUserStateFromRequest( $p.'filter_order_Dir', 'filter_order_Dir', $default_order_dir, 'word' );
		
		if (!$filter_order)     $filter_order     = $default_order;
		if (!$filter_order_Dir) $filter_order_Dir = $default_order_dir;
		if (!$isADV && !in_array($filter_order, array('a.id', 'a.title', 'ext.search_index'))) $filter_order = 'a.title';
		
		$this->setState('filter_order', $filter_order);
		$this->setState('filter_order_Dir', $filter_order_Dir);
		
		$app->setUserState($p.'filter_order', $filter_order);
		$app->setUserState($p.'filter_order_Dir', $filter_order_Dir);
		
		
		
		// *****************************
		// Pagination: limit, limitstart
		// *****************************
		
		$limit      = $fcform ? $jinput->get('limit', $app->getCfg('list_limit'), 'int')  :  $app->getUserStateFromRequest( $p.'limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $fcform ? $jinput->get('limitstart',                     0, 'int')  :  $app->getUserStateFromRequest( $p.'limitstart', 'limitstart', 0, 'int' );
		
		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );
		$jinput->set( 'limitstart',	$limitstart );
		
		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
		
		$app->setUserState($p.'limit', $limit);
		$app->setUserState($p.'limitstart', $limitstart);
	}
	
	
	function getData()
	{
		if (!empty($this->_data)) return $this->_data;
		
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		
		// 1, get filtered, limited, ordered items
		$query = $this->_buildQuery();
		
		if ( $print_logging_info )  $start_microtime = microtime(true);
		$this->_db->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));
		$rows = $this->_db->loadObjectList();
		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		// 2, get current items total for pagination
		$this->_db->setQuery("SELECT FOUND_ROWS()");
		$this->_total = $this->_db->loadResult();
		
		$filter_indextype = $this->getState( 'filter_indextype' );
		$isADV = $filter_indextype=='advanced';
		
		
		// 3, get item ids
		$query_ids = array();
		foreach ($rows as $row) {
			$query_ids[] = $isADV ? $row->sid : $row->item_id;
		}
		
		
		// 4, get item data
		if (count($query_ids)) $query = $this->_buildQuery($query_ids);
		if ( $print_logging_info )  $start_microtime = microtime(true);
		$_data = array();
		if (count($query_ids)) {
			$this->_db->setQuery($query);
			$_data = $this->_db->loadObjectList($isADV ? 'sid' : 'item_id');
		}
		if ( $print_logging_info ) @$fc_run_times['execute_sec_queries'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		// 5, reorder items and get cat ids
		$this->_data = array();
		foreach($query_ids as $query_id) {
			$this->_data[] = $_data[$query_id];
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
		if ( $this->_total === null ) {
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
	function _buildQuery( $query_ids=false )
	{
		if ( !$query_ids )
		{
			$where		= $this->_buildWhere();
			$orderby	= $this->_buildOrderBy();
			
			$filter_order     = $this->getState( 'filter_order' );
			
			$filter_fieldtype = $this->getState( 'filter_fieldtype' );
			$filter_itemstate = $this->getState( 'filter_itemstate' );
			$filter_itemtype  = $this->getState( 'filter_itemtype' );
			
			$search_itemtitle = $this->getState( 'search_itemtitle' );
			$search_itemid    = $this->getState( 'search_itemid' );
		}
		$filter_indextype = $this->getState( 'filter_indextype' );
		$isADV = $filter_indextype=='advanced';
		
		$query = !$query_ids ?
			'SELECT SQL_CALC_FOUND_ROWS '.($isADV ? 'ai.sid' : 'ext.item_id') :
			($isADV ?
				'SELECT f.label, f.name, f.field_type, ai.*, a.title, a.id ' :
				'SELECT ext.*, a.title, a.id '
			);
		$query .= $isADV ? ' FROM #__flexicontent_advsearch_index as ai' : ' FROM #__flexicontent_items_ext as ext';
		if ($query_ids) {
			$query .= ''
				.' JOIN #__flexicontent_items_tmp as a ON ' .($isADV ? 'ai' : 'ext'). '.item_id=a.id'
				.(!$isADV ? '' : ''
					.' JOIN #__flexicontent_items_ext as ext ON ext.item_id=a.id'
					.' JOIN #__flexicontent_fields_type_relations as rel ON rel.field_id=ai.field_id AND rel.type_id=ext.type_id'
					.' JOIN #__flexicontent_fields as f ON ai.field_id=f.id'
				)
				;
		} else {
			if ( $isADV && (in_array($filter_order, array('f.label','f.name','f.field_type')) || $filter_fieldtype) )
				$query .= ''
					.' JOIN #__flexicontent_items_tmp as a ON ai.item_id=a.id'
					.' JOIN #__flexicontent_items_ext as ext ON ext.item_id=a.id'
					.' JOIN #__flexicontent_fields_type_relations as rel ON rel.field_id=ai.field_id AND rel.type_id=ext.type_id'
					.' JOIN #__flexicontent_fields as f ON ai.field_id=f.id'
					;
			else {
				if ($filter_order == 'a.id' || $filter_order == 'a.title' || $filter_itemstate || $filter_itemtype || $search_itemtitle || $search_itemid)
					$query .= ' JOIN #__flexicontent_items_tmp as a ON ' .($isADV ? 'ai' : 'ext'). '.item_id=a.id';
				if ($isADV && $filter_itemtype)
					$query .= ' JOIN #__flexicontent_items_ext as ext ON ext.item_id=a.id';
			}
		}
		$query .= !$query_ids ?
			$where.$orderby :
			($isADV ?
				' WHERE ai.sid IN ('. implode(',', $query_ids) .')' :
				' WHERE ext.item_id IN ('. implode(',', $query_ids) .')'
			);
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
		$filter_order     = $this->getState( 'filter_order' );
		$filter_order_Dir = $this->getState( 'filter_order_Dir' );
		
		$orderby = $filter_order.' '.$filter_order_Dir;
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
		
		$filter_itemstate	= $this->getState( 'filter_itemstate' );
		$filter_itemtype	= $this->getState( 'filter_itemtype' );
		$filter_fieldtype = $this->getState( 'filter_fieldtype' );
		$search_itemtitle	= $this->getState( 'search_itemtitle' );
		$search_itemid		= $this->getState( 'search_itemid' );
		
		$filter_indextype = $this->getState( 'filter_indextype' );
		$isADV = $filter_indextype=='advanced';
		
		$search  = $this->getState( 'search' );
		$search  = StringHelper::trim( StringHelper::strtolower( $search ) );
		
		$where = array();

		if ( $isADV && $filter_fieldtype ) {
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
			$where[] = 'ext.type_id = ' . (int)$filter_itemtype;
		}

		if ($search) {
			$escaped_search = $this->_db->escape( $search, true );
			$where[] = ' LOWER(' .($isADV ? 'ai' : 'ext'). '.search_index) LIKE '.$this->_db->Quote( '%'.$escaped_search.'%', false );
		}
		
		if ($search_itemtitle) {
			$search_itemtitle_escaped = $this->_db->escape( $search_itemtitle, true );
			$where[] = ' LOWER(a.title) LIKE '.$this->_db->Quote( '%'.$search_itemtitle_escaped.'%', false );
		}
		
		if ($search_itemid) {
			$where[] = ' a.id= '. (int)$search_itemid;
		}
		
		$where = ( count( $where ) ? implode( ' AND ', $where ) : '' );
		$where = trim($where) ? " WHERE ".$where : "";
		
		return $where;
	}	
	
	
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
		jimport('cms.pagination.pagination');
		$this->_pagination = new JPagination($this->getCount(), $this->getState('limitstart'), $this->getState('limit'));

		return $this->_pagination;
	}
	function getLimitStart() {
		$app = JFactory::getApplication();
		return $this->getState('limitstart', $app->getCfg('list_limit'));
	}
	
	/**
	 * Method to empty search indexes
	 *
	 * @access	public
	 * @return	null
	 * @since	1.0
	 */
	function purge( $del_fieldids=null )
	{
		$app = JFactory::getApplication();
		$db  = JFactory::getDbo();
		
		// ******************************
		// Empty Common text-search index
		// ******************************
		
		if ( empty($del_fieldids) ) {
			$query = "TRUNCATE TABLE `#__flexicontent_advsearch_index`;";
		} else {
			$del_fieldids_list = implode( ',' , $del_fieldids);
			$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id IN (". $del_fieldids_list. ")";
		}
		$db->setQuery($query);
		$db->execute();
		
		
		// **********************
		// Empty per field TABLES
		// **********************
		
		$filterables = FlexicontentFields::getSearchFields('id', $indexer='advanced', null, null, $_load_params=true, 0, $search_type='filter');
		$filterables = array_keys($filterables);
		$filterables = array_flip($filterables);
		
		$dbprefix = $app->getCfg('dbprefix');
		$dbname   = $app->getCfg('db');
		$tbl_prefix = $dbprefix.'flexicontent_advsearch_index_field_';
		$query = "SELECT TABLE_NAME
			FROM INFORMATION_SCHEMA.TABLES
			WHERE TABLE_SCHEMA = '".$dbname."' AND TABLE_NAME LIKE '".$tbl_prefix."%'
			";
		$db->setQuery($query);
		$tbl_names = $db->loadColumn();
		
		foreach($tbl_names as $tbl_name)
		{
			$_field_id = str_replace($tbl_prefix, '', $tbl_name);
			
			// Drop the table of no longer filterable field 
			if ( !isset($filterables[$_field_id]) )
				$db->setQuery( 'DROP TABLE IF EXISTS '.$tbl_name );
			
			// Truncate (or drop/recreate) tables of fields that are still filterable. Any dropped but needed tables will be recreated below
			else if ( empty($del_fieldids) || isset($del_fieldids[$_field_id]) )
				//$db->setQuery( 'DROP TABLE IF EXISTS '.$tbl_name );
				$db->setQuery( 'TRUNCATE TABLE '.$tbl_name );
			
			$db->execute();
		}
		
		// VERIFY all search tables exist
		$tbl_names_flipped = array_flip($tbl_names);
		foreach ($filterables as $_field_id => $_ignored)
		{
			$tbl_name = $app->getCfg('dbprefix').'flexicontent_advsearch_index_field_'.$_field_id;
			$query = '
			CREATE TABLE IF NOT EXISTS `' .$tbl_name. '` (
			  `sid` int(11) NOT NULL auto_increment,
			  `field_id` int(11) NOT NULL,
			  `item_id` int(11) NOT NULL,
			  `extraid` int(11) NOT NULL,
			  `search_index` longtext NOT NULL,
			  `value_id` varchar(255) NULL,
			  PRIMARY KEY (`field_id`,`item_id`,`extraid`),
			  KEY `sid` (`sid`),
			  KEY `field_id` (`field_id`),
			  KEY `item_id` (`item_id`),
			  FULLTEXT `search_index` (`search_index`),
			  KEY `value_id` (`value_id`)
			) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`
			';
			$db->setQuery($query);
			$db->execute();
		}
		
	}
}
