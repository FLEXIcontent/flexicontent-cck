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

jimport('joomla.application.component.model');

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
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');

		$limit		= $app->getUserStateFromRequest( $option.'.tags.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( $option.'.tags.limitstart', 'limitstart', 0, 'int' );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		$array = JRequest::getVar('cid',  0, '', 'array');
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
	function getAssignedItems($tids) {
		if (empty($tids)) return array();
		
		$db = JFactory::getDBO();
		
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
			$db = JFactory::getDBO();
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
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
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

		$query = 'SELECT SQL_CALC_FOUND_ROWS t.*, u.name AS editor'
			// because of multi-multi tag-item relations it is faster to calculate this with a single seperate query
			// if it was single mapping e.g. like it is 'item' TO 'content type' or 'item' TO 'creator' we could use a subquery
			// the more categories are listed (query LIMIT) the bigger the performance difference ...
			//. ', (SELECT COUNT(rel.tid) FROM #__flexicontent_tags_item_relations AS rel WHERE rel.tid=t.id GROUP BY t.id) AS nrassigned'
			. ' FROM #__flexicontent_tags AS t'
			. ' LEFT JOIN #__users AS u ON u.id = t.checked_out'
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
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$filter_order		= $app->getUserStateFromRequest( $option.'.tags.filter_order', 		'filter_order', 	't.name', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( $option.'.tags.filter_order_Dir',	'filter_order_Dir',	'', 'word' );

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
		$option = JRequest::getVar('option');
		
		$filter_state = $app->getUserStateFromRequest( $option.'.tags.filter_state', 'filter_state', '', 'word' );
		$search = $app->getUserStateFromRequest( $option.'.tags.search', 'search', '', 'string' );
		$search = trim( JString::strtolower( $search ) );

		$where = array();

		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$where[] = 't.published = 1';
			} else if ($filter_state == 'U' ) {
				$where[] = 't.published = 0';
			}
		}

		if ($search) {
			$search_escaped = FLEXI_J16GE ? $this->_db->escape( $search, true ) : $this->_db->getEscaped( $search, true );
			$where[] = ' LOWER(t.name) LIKE '.$this->_db->Quote( '%'.$search_escaped.'%', false );
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
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$filter_assigned	= $app->getUserStateFromRequest( $option.'.tags.filter_assigned', 'filter_assigned', '', 'word' );
		
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
			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}
		return true;
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

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			$query = 'DELETE FROM #__flexicontent_tags_item_relations'
					. ' WHERE tid IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
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