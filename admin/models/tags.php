<?php
/**
 * @version 1.5 stable $Id: tags.php 1889 2014-04-26 03:25:28Z ggppdk $
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
 * FLEXIcontent Component tags Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelTags extends JModelLegacy
{
	/**
	 * Tag data
	 *
	 * @var object
	 */
	var $_data = null;

	/**
	 * Tag total
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
	 * Tag id
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
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
		
		
		
		// ****************************************
		// Ordering: filter_order, filter_order_Dir
		// ****************************************
		
		$default_order     = 't.name';
		$default_order_dir = 'ASC';
		
		$filter_order      = $fcform ? $jinput->get('filter_order',     $default_order,      'cmd')  :  $app->getUserStateFromRequest( $p.'filter_order',     'filter_order',     $default_order,      'cmd' );
		$filter_order_Dir  = $fcform ? $jinput->get('filter_order_Dir', $default_order_dir, 'word')  :  $app->getUserStateFromRequest( $p.'filter_order_Dir', 'filter_order_Dir', $default_order_dir, 'word' );
		
		if (!$filter_order)     $filter_order     = $default_order;
		if (!$filter_order_Dir) $filter_order_Dir = $default_order_dir;
		
		$this->setState('filter_order', $filter_order);
		$this->setState('filter_order_Dir', $filter_order_Dir);
		
		$app->setUserState($p.'filter_order', $filter_order);
		$app->setUserState($p.'filter_order_Dir', $filter_order_Dir);
		
		
		
		// **************
		// view's Filters
		// **************
		
		// Various filters
		$filter_state    = $fcform ? $jinput->get('filter_state',    '', 'string')  :  $app->getUserStateFromRequest( $p.'filter_state',    'filter_state',    '', 'string' );   // we may check for '*', so string filter
		$filter_assigned = $fcform ? $jinput->get('filter_assigned', '', 'cmd')     :  $app->getUserStateFromRequest( $p.'filter_assigned', 'filter_assigned', '', 'cmd' );
		
		$this->setState('filter_state', $filter_state);
		$this->setState('filter_assigned', $filter_assigned);
		
		$app->setUserState($p.'filter_state', $filter_state);
		$app->setUserState($p.'filter_assigned', $filter_assigned);
		
		
		// Text search
		$search = $fcform ? $jinput->get('search', '', 'string')  :  $app->getUserStateFromRequest( $p.'search',  'search',  '',  'string' );
		$this->setState('search', $search);
		$app->setUserState($p.'search', $search);
		
		
		
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
	 * Method to set the Tag identifier
	 *
	 * @access	public
	 * @param	int Tag identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
	}


	/**
	 * Method to count assigned items for the given categories
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	function getAssignedItems($tids)
	{
		if (empty($tids)) return array();
		
		$db = JFactory::getDbo();
		
		// Select the required fields from the table.
		$query  = " SELECT rel.tid, COUNT(rel.itemid) AS nrassigned";
		$query .= " FROM #__flexicontent_tags_item_relations AS rel";
		$query .= " WHERE rel.tid IN (".implode(",", $tids).") ";
		$query .= " GROUP BY rel.tid";
		
		$db->setQuery( $query );
		$assigned = $db->loadObjectList('tid');
		return $assigned;
	}
	
	
	/**
	 * Method to get tags data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the tags if it doesn't already exist
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
	 * Method to get the total nr of the tags
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the tags if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}


	/**
	 * Method to get a pagination object for the tags
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the tags if it doesn't already exist
		if (empty($this->_pagination))
		{
			require_once (JPATH_COMPONENT_SITE.DS.'helpers'.DS.'pagination.php');
			$this->_pagination = new FCPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}


	/**
	 * Method to build the query for the tags
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
		$filter_assigned	= $this->getState( 'filter_assigned' );
		
		$query = 'SELECT SQL_CALC_FOUND_ROWS t.*, u.name AS editor'
			// because of multi-multi tag-item relations it is faster to calculate this with a single seperate query
			// if it was single mapping e.g. like it is 'item' TO 'content type' or 'item' TO 'creator' we could use a subquery
			// the more tags are listed (query LIMIT) the bigger the performance difference ...
			. ($filter_order=='nrassigned' ? ', (SELECT COUNT(rel.tid) FROM #__flexicontent_tags_item_relations AS rel WHERE rel.tid=t.id GROUP BY t.id) AS nrassigned' : '')
			. ', CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
			. ' FROM #__flexicontent_tags AS t'
			. ' LEFT JOIN #__users AS u ON u.id = t.checked_out'
			. ($filter_assigned || $filter_order=='nrassigned' ? ' LEFT JOIN #__flexicontent_tags_item_relations AS rel ON rel.tid=t.id' : '')
			. $where
			. ' GROUP BY t.id'
			. $having
			. $orderby
			;

		return $query;
	}


	/**
	 * Method to build the orderby clause of the query for the tags
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
	 * Method to build the where clause of the query for the tags
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentWhere()
	{
		$app = JFactory::getApplication();
		$option = $app->input->get('option', '', 'CMD');
		
		$filter_state	= $this->getState( 'filter_state' );
		
		// text search
		$search  = $this->getState( 'search' );
		$search  = StringHelper::trim( StringHelper::strtolower( $search ) );
		
		$where = array();

		if ( empty($filter_state) )
		{
			$where[] = 't.published <> -2';
			$where[] = 't.published <> 2';
		}
		else
		{
			if ( $filter_state == 'P' ) {
				$where[] = 't.published = 1';
			} else if ($filter_state == 'U' ) {
				$where[] = 't.published = 0';
			} else if ($filter_state == 'A' ) {
				$where[] = 't.published = 2';
			} else if ($filter_state == 'T' ) {
				$where[] = 't.published = -2';
			}
		}

		if ($search) {
			$escaped_search = FLEXI_J16GE ? $this->_db->escape( $search, true ) : $this->_db->getEscaped( $search, true );
			$where[] = ' LOWER(t.name) LIKE '.$this->_db->Quote( '%'.$escaped_search.'%', false );
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
		$filter_assigned	= $this->getState( 'filter_assigned' );
		
		$having = '';
		
		if ( $filter_assigned ) {
			if ( $filter_assigned == 'O' ) {
				$having = ' HAVING COUNT(rel.tid) = 0';
			} else if ($filter_assigned == 'A' ) {
				$having = ' HAVING COUNT(rel.tid) > 0';
			}
		}
		
		return $having;
	}


	/**
	 * Method to (un)publish a tag
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

			$query 	= 'UPDATE #__flexicontent_tags'
					. ' SET published = ' . (int) $publish
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
		$cid_noauth = FlexicontentHelperPerm::getPerm()->CanTags ? array() : $cid;

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
		$cid_noauth = FlexicontentHelperPerm::getPerm()->CanTags ? array() : $cid;

		return !count($cid_noauth) && !count($cid_wassocs);
	}


	/**
	 * Method to remove a tag
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
			$query = 'DELETE FROM #__flexicontent_tags'
					. ' WHERE id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );

			if(!$this->_db->execute()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			$query = 'DELETE FROM #__flexicontent_tags_item_relations'
					. ' WHERE tid IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );

			if(!$this->_db->execute()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}

		return true;
	}


	/**
	 * Method to import a list of tags
	 *
	 * @access	public
	 * @params	string	the list of tags to import
	 * @return	array	the import logs
	 * @since	1.5
	 */
	function importList($tags)
	{
		if (!$tags) return;
		
		// initialize the logs counters
		$logs = array();
		$logs['error'] 		= 0;
		$logs['success'] 	= 0;
		
		$tags = explode("\n", $tags);
		
		foreach ($tags as $tag) {
			$row  = $this->getTable('flexicontent_tags', '');
			$row->name 		= $tag;
			$row->alias 	= JFilterOutput::stringURLSafe($tag);
			$row->published = 1;
			if (!$row->check()) {
				$logs['error']++;			
			} else {
				$row->store();
				$logs['success']++;
			}
		}

		return $logs;
	}

}
?>