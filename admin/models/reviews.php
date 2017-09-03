<?php
/**
 * @version 1.5 stable $Id: reviews.php 1889 2014-04-26 03:25:28Z ggppdk $
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

jimport('legacy.model.list');
use Joomla\String\StringHelper;

/**
 * FLEXIcontent Component reviews Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelReviews extends JModelList
{
	var $records_dbtbl = 'flexicontent_reviews_dev';

	/**
	 * Record rows
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Rows total
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
	 * Single record id (used in operations)
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Constructor
	 *
	 * @since 3.2.0
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
		
		
		// **************
		// view's Filters
		// **************
		
		// Various filters
		$filter_state     = $fcform ? $jinput->get('filter_state',     '', 'string')  :  $app->getUserStateFromRequest( $p.'filter_state',    'filter_state',      '', 'string' );    // we may check for '*', so string filter
		
		$this->setState('filter_state', $filter_state);
		
		$app->setUserState($p.'filter_state', $filter_state);
		
		
		// Text search
		$search = $fcform ? $jinput->get('search', '', 'string')  :  $app->getUserStateFromRequest( $p.'search',  'search',  '',  'string' );
		$this->setState('search', $search);
		$app->setUserState($p.'search', $search);



		// ****************************************
		// Ordering: filter_order, filter_order_Dir
		// ****************************************
		
		$default_order     = 'r.title';
		$default_order_dir = 'ASC';
		
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
	 * Method to set the Record identifier and clear record rows
	 *
	 * @access	public
	 * @param	int Record identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
		$this->_total= null;
	}


	/**
	 * Method to get a pagination object for the records
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Create pagination object if it doesn't already exist
		if (empty($this->_pagination))
		{
			require_once (JPATH_COMPONENT_SITE.DS.'helpers'.DS.'pagination.php');
			$this->_pagination = new FCPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}


	/**
	 * Method to get reviews data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the reviews if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			$db = JFactory::getDbo();
			$db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $db->loadResult();
		}
		
		return $this->_data;
	}


	/**
	 * Method to get the total nr of the reviews
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Calculate total if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}



	/**
	 * Method to build the query for the reviews
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	function _buildQuery()
	{
		// Get the WHERE, HAVING and ORDER BY clauses for the query
		$where		= $this->_buildContentWhere();
		$orderby	= $this->_buildContentOrderBy();
		$having		= $this->_buildContentHaving();
		
		$filter_order     = $this->getState( 'filter_order' );
		
		$query = 'SELECT SQL_CALC_FOUND_ROWS r.*, u.name AS editor'
			. ' FROM #__' . $this->records_dbtbl . ' AS r'
			. ' LEFT JOIN #__users AS u ON u.id = r.checked_out'
			. $where
			. ' GROUP BY r.id'
			. $having
			. $orderby
			;

		return $query;
	}


	/**
	 * Method to build the orderby clause of the query for the records
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentOrderBy()
	{
		$filter_order     = $this->getState( 'filter_order' );
		$filter_order_Dir	= $this->getState( 'filter_order_Dir' );
		
		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir;

		return $orderby;
	}


	/**
	 * Method to build the where clause of the query for the records
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentWhere()
	{
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');

		// Various filters
		$filter_state     = $this->getState( 'filter_state' );
		
		// Text search
		$search = $this->getState( 'search' );
		$search = StringHelper::trim( StringHelper::strtolower( $search ) );

		$where = array();

		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$where[] = '(r.state = 1 OR r.approved<>0)';
			} else if ($filter_state == 'U' ) {
				$where[] = 'r.state = 0 OR r.approved=0';
			} // else ALL: published & unpublished (in future we may have more states, e.g. archived, trashed)
		}

		if ($search) {
			$escaped_search = FLEXI_J16GE ? $this->_db->escape( $search, true ) : $this->_db->getEscaped( $search, true );
			$where[] = ' LOWER(r.title) LIKE '.$this->_db->Quote( '%'.$escaped_search.'%', false );
		}

		$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );

		return $where;
	}


	/**
	 * Method to build the having clause of the query for the files
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentHaving()
	{
		$having = '';
		
		return $having;
	}


	/**
	 * Method to (un)publish a record
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function publish($cid = array(), $publish = 1)
	{
		$user = JFactory::getUser();

		if (count( $cid ))
		{
			$cids = implode( ',', $cid );

			$query = 'UPDATE #__' . $this->records_dbtbl
				. ' SET state = ' . (int) $publish
				. ' WHERE id IN ('. $cids .')'
				. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
				;
			$this->_db->setQuery( $query );
			$this->_db->execute();
		}
		return true;
	}


	/**
	 * Method to check if given records can not be deleted e.g. due to assignments or due to being a CORE record
	 *
	 * @access	public
	 * @return	boolean
	 * @since	1.5
	 */
	function candelete($cid, & $cid_noauth=array(), & $cid_wassocs=array())
	{
		$cid_noauth = $cid_wassocs = array();

		// Find ACL disallowed
		$cid_noauth = FlexicontentHelperPerm::getPerm()->CanReviews ? array() : $cid;

		return !count($cid_noauth) && !count($cid_wassocs);
	}


	/**
	 * Method to check if given records can not be unpublished e.g. due to assignments or due to being a CORE record
	 *
	 * @access	public
	 * @return	boolean
	 * @since	1.5
	 */
	function canunpublish($cid, & $cid_noauth=array(), & $cid_wassocs=array())
	{
		$cid_noauth = $cid_wassocs = array();

		// Find ACL disallowed
		$cid_noauth = FlexicontentHelperPerm::getPerm()->CanReviews ? array() : $cid;

		return !count($cid_noauth) && !count($cid_wassocs);
	}


	/**
	 * Method to (un)approve a review
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function approve($cid = array(), $approved = 2)
	{
		$user = JFactory::getUser();

		if (count( $cid ))
		{
			$cids = implode( ',', $cid );

			$query = 'UPDATE #__' . $this->records_dbtbl
					. ' SET approved = ' . (int) $publish
					. ' WHERE id IN ('. $cids .')'
					. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
					;
			$this->_db->setQuery( $query );
			$this->_db->execute();
		}
		return true;
	}


	/**
	 * Method to remove a record
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function delete($cid = array())
	{
		$result = false;

		if (count( $cid ))
		{
			$cids = implode( ',', $cid );
			$query = 'DELETE FROM #__' . $this->records_dbtbl
					. ' WHERE id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );

			if(!$this->_db->execute()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}

		return true;
	}

}
?>