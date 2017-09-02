<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_flexicontent
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
jimport('legacy.model.list');

/**
 * Methods supporting a list of user records.
 *
 * @since  1.6
 */
class FlexicontentModelUsers extends JModelList
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
		$filter_itemscount = $fcform ? $jinput->get('filter_itemscount', 0,  'int')  :  $app->getUserStateFromRequest( $p.'filter_itemscount', 'filter_itemscount', 0,  'int' );
		$filter_usergrp    = $fcform ? $jinput->get('filter_usergrp',    0,  'int')  :  $app->getUserStateFromRequest( $p.'filter_usergrp',    'filter_usergrp',    0,  'int' );
		$filter_logged     = $fcform ? $jinput->get('filter_logged',     '', 'cmd')  :  $app->getUserStateFromRequest( $p.'filter_logged',     'filter_logged',     '', 'cmd' );
		$filter_state      = $fcform ? $jinput->get('filter_state',      '', 'cmd')  :  $app->getUserStateFromRequest( $p.'filter_state',      'filter_state',      '', 'cmd' );
		$filter_active     = $fcform ? $jinput->get('filter_active',     '', 'cmd')  :  $app->getUserStateFromRequest( $p.'filter_active',     'filter_active',     '', 'cmd' );
		
		$this->setState('filter_itemscount', $filter_itemscount);
		$this->setState('filter_usergrp', $filter_usergrp);
		$this->setState('filter_logged', $filter_logged);
		$this->setState('filter_state', $filter_state);
		$this->setState('filter_active', $filter_active);
		
		$app->setUserState($p.'filter_itemscount', $filter_itemscount);
		$app->setUserState($p.'filter_usergrp', $filter_usergrp);
		$app->setUserState($p.'filter_logged', $filter_logged);
		$app->setUserState($p.'filter_state', $filter_state);
		$app->setUserState($p.'filter_active', $filter_active);
		
		
		// Date filters
		$date	 				= $fcform ? $jinput->get('date',      1,  'int')  :  $app->getUserStateFromRequest( $p.'date',      'date',      1, 'int' );
		$startdate	 	= $fcform ? $jinput->get('startdate', '', 'cmd')  :  $app->getUserStateFromRequest( $p.'startdate', 'startdate', '', 'cmd' );
		$enddate	 		= $fcform ? $jinput->get('enddate',   '', 'cmd')  :  $app->getUserStateFromRequest( $p.'enddate',   'enddate',   '', 'cmd' );
		
		$this->setState('date', $date);
		$this->setState('startdate', $startdate);
		$this->setState('enddate', $enddate);
		
		$app->setUserState($p.'date', $date);
		$app->setUserState($p.'startdate', $startdate);
		$app->setUserState($p.'enddate', $enddate);
		
		
		// Author ID filter
		$filter_id  = $fcform ? $jinput->get('filter_id', 0, 'int')  :  $app->getUserStateFromRequest( $p.'filter_id', 'filter_id', 0, 'int' );
		$filter_id  = $filter_id ? $filter_id : '';  // needed to make text input field be empty
		
		$this->setState('filter_id', $filter_id);
		$app->setUserState($p.'filter_id', $filter_id);
		
		
		// Text search
		$search = $fcform ? $jinput->get('search', '', 'string')  :  $app->getUserStateFromRequest( $p.'search',  'search',  '',  'string' );
		$this->setState('search', $search);
		$app->setUserState($p.'search', $search);
		
		
		
		// ****************************************
		// Ordering: filter_order, filter_order_Dir
		// ****************************************
		
		$default_order     = 'a.id';
		$default_order_dir = 'DESC';
		
		$filter_order      = $fcform ? $jinput->get('filter_order',     $default_order,      'cmd')  :  $app->getUserStateFromRequest( $p.'filter_order',     'filter_order',     $default_order,      'cmd' );
		$filter_order_Dir  = $fcform ? $jinput->get('filter_order_Dir', $default_order_dir, 'word')  :  $app->getUserStateFromRequest( $p.'filter_order_Dir', 'filter_order_Dir', $default_order_dir, 'word' );
		
		if (!$filter_order)     $filter_order     = $default_order;
		if (!$filter_order_Dir) $filter_order_Dir = $default_order_dir;
		
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
		
		
		// For some model function that use single id
		$array = $jinput->get('cid', array(0), 'array');
		$this->setId((int)$array[0]);
	}
	
	
	/**
	 * Method to set the Items identifier
	 *
	 * @access	public
	 * @param	int Category identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
		$this->_total= null;
	}
	
	
	function getData()
	{
		// Lets load the files if it doesn't already exist
		if (empty($this->_data))
		{
		
			if ( $this->cparams->get('print_logging_info') )  $start_microtime = microtime(true);
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			if (  $this->cparams->get('print_logging_info') ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			
			$this->_db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $this->_db->loadResult();
		}
		
		return $this->_data;
	}
	
	
	/**
	 * Method to get users data
	 *
	 * @access public
	 * @return array
	 */
	function _buildQuery()
	{
		// various filters
		$filter_itemscount = $this->getState( 'filter_itemscount' );
		$filter_usergrp    = $this->getState( 'filter_usergrp' );
		$filter_logged     = $this->getState( 'filter_logged' );
		$filter_state      = $this->getState( 'filter_state' );
		$filter_active     = $this->getState( 'filter_active' );
		
		// date filters
		$date       = $this->getState( 'date' );
		$startdate  = $this->getState( 'startdate' );
		$enddate    = $this->getState( 'enddate' );
		
		$startdate = StringHelper::trim( StringHelper::strtolower( $startdate ) );
		$enddate   = StringHelper::trim( StringHelper::strtolower( $enddate ) );
		
		// author id
		$filter_id  = $this->getState( 'filter_id' );
		
		// text search
		$search = $this->getState( 'search' );
		$search = StringHelper::trim( StringHelper::strtolower( $search ) );
		
		// ordering filters
		$filter_order      = $this->getState('filter_order');
		$filter_order_Dir  = $this->getState('filter_order_Dir');
		
		$where  = array();
		$having = array();
		$extra_joins = array();
		
		if (isset( $search ) && $search!= '')
		{
			$escaped_search = $this->_db->escape( $search, true );
			$escaped_search = $this->_db->Quote( '%'.$escaped_search.'%', false );
			$where[] = 'a.username LIKE '.$escaped_search.' OR a.email LIKE '.$escaped_search.' OR a.name LIKE '.$escaped_search;
		}

		// visited date filtering
		if ($date == 1) {
			if ($startdate && !$enddate) {  // from only
				$where[] = ' a.registerDate >= ' . $this->_db->Quote($startdate);
			}
			if (!$startdate && $enddate) { // to only
				$where[] = ' a.registerDate <= ' . $this->_db->Quote($enddate);
			}
			if ($startdate && $enddate) { // date range
				$where[] = '( a.registerDate >= ' . $this->_db->Quote($startdate) . ' AND a.registerDate <= ' . $this->_db->Quote($enddate) . ' )';
			}
		}
		if ($date == 2) {
			if ($startdate && !$enddate) {  // from only
				$where[] = ' a.lastvisitDate >= ' . $this->_db->Quote($startdate);
			}
			if (!$startdate && $enddate) { // to only
				$where[] = ' a.lastvisitDate <= ' . $this->_db->Quote($enddate);
			}
			if ($startdate && $enddate) { // date range
				$where[] = '( a.lastvisitDate >= ' . $this->_db->Quote($startdate) . ' AND a.lastvisitDate <= ' . $this->_db->Quote($enddate) . ' )';
			}
		}
		
		
		if ( $filter_id )
			$where[] = 'a.id = '. (int)$filter_id;
		
		if ( $filter_usergrp )  // Filtering by usergroup, right join with usergroups DB table, to limit users to those belonging to the selected group
			$extra_joins[] = ' RIGHT JOIN #__user_usergroup_map AS ug ON ug.user_id = a.id AND ug.group_id='. (int)$filter_usergrp;
		
		if (strlen($filter_logged))
		{
			if ( $filter_logged === '1' )
				$where[] = 's.userid IS NOT NULL';
			else if ($filter_logged === '0')
				$where[] = 's.userid IS NULL';
		}

		if (strlen($filter_state))
		{
			$where[] = 'a.block = ' . (int) $filter_state;
		}

		if (strlen($filter_active))
		{
			if ( $filter_active === '1' )
				$where[] = ' LENGTH(a.activation) > 1';  // Not active
			else if ($filter_active === '0')
				$where[] = 'a.activation IN (' . $this->_db->quote('') . ', ' . $this->_db->quote('0') . ')';   // Active
		}

		if ( $filter_itemscount==2 )
			$having[] = ' itemscount > 0 ';
		else if ( $filter_itemscount==1 )
			$having[] = ' itemscount = 0 ';
		
		// ensure filter_order has a valid value.
		//$allowed_order_cols = array('a.name', 'itemscount', 'a.username', 'loggedin', 'a.block', 'groupname','a.email', 'a.lastvisitDate', 'a.lastvisitDate', 'a.id');
		//if (!in_array($filter_order, $allowed_order_cols)) $filter_order = 'a.name';
		//if (!in_array(strtoupper($filter_order_Dir), array('ASC', 'DESC'))) $filter_order_Dir = '';
		
		$orderby = ' ORDER BY '. $filter_order .' '. $filter_order_Dir;
		$where = ( count( $where ) ? ' WHERE (' . implode( ') AND (', $where ) . ')' : '' );
		$having = ( count( $having ) ? ' HAVING (' . implode( ') AND (', $having ) . ')' : '' );
		$extra_joins = ( count( $extra_joins ) ? implode( ' ', $extra_joins ) : '' );
		
		// Do main query to get the authors
		$query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT a.*, s.userid IS NOT NULL AS loggedin'
			. ', (SELECT COUNT(*) FROM #__content AS i WHERE i.created_by = a.id) AS itemscount '
			. ', (SELECT SUM(size) FROM #__flexicontent_files AS f WHERE f.uploaded_by = a.id) AS uploadssize'
			. ' FROM #__users AS a'
			. ' LEFT JOIN #__flexicontent_authors_ext AS ue ON a.id = ue.user_id'
			. ' LEFT JOIN #__session AS s ON s.userid = a.id'
			. $extra_joins
			. $where
			//. ' GROUP BY a.id'
			. $having
			. $orderby
			;
		
		return $query;
	}
	
	
	/**
	 * Method to get the total nr of the files
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the files if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}
	
	
	/**
	 * Method to get a pagination object for the files
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the files if it doesn't already exist
		if (empty($this->_pagination))
		{
			require_once (JPATH_COMPONENT_SITE.DS.'helpers'.DS.'pagination.php');
			$this->_pagination = new FCPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}
	
}
?>
