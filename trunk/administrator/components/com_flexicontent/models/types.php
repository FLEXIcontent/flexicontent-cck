<?php
/**
 * @version 1.5 stable $Id$
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
 * FLEXIcontent Component types Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelTypes extends JModel
{
	/**
	 * Type data
	 *
	 * @var object
	 */
	var $_data = null;

	/**
	 * Type total
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
	 * Type id
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

		global $mainframe, $option;

		$limit		= $mainframe->getUserStateFromRequest( $option.'.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
		$limitstart = $mainframe->getUserStateFromRequest( $option.'.tags.limitstart', 'limitstart', 0, 'int' );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		$array = JRequest::getVar('cid',  0, '', 'array');
		$this->setId((int)$array[0]);

	}

	/**
	 * Method to set the Type identifier
	 *
	 * @access	public
	 * @param	int Type identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
	}

	/**
	 * Method to get types data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the types if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			$db =& JFactory::getDBO();
			$db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $db->loadResult();
		}
		return $this->_data;
	}

	/**
	 * Method to get the total nr of the types
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the types if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the types
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the types if it doesn't already exist
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Method to build the query for the types
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
//		$having		= $this->_buildContentHaving();

		$subquery = 'SELECT COUNT(type_id)'
					. ' FROM #__flexicontent_items_ext'
					. ' WHERE type_id = t.id'
					;

		$query = 'SELECT SQL_CALC_FOUND_ROWS t.*, u.name AS editor, g.name AS groupname, COUNT(rel.type_id) AS fassigned, ('.$subquery.') AS iassigned'
					. ' FROM #__flexicontent_types AS t'
					. ' LEFT JOIN #__flexicontent_fields_type_relations AS rel ON t.id = rel.type_id'
					. ' LEFT JOIN #__groups AS g ON g.id = t.access'
					. ' LEFT JOIN #__users AS u ON u.id = t.checked_out'
					. $where
					. ' GROUP BY t.id'
//					. $having
					. $orderby
					;
					

		return $query;

	}

	/**
	 * Method to build the orderby clause of the query for the types
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentOrderBy()
	{
		global $mainframe, $option;

		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.types.filter_order', 		'filter_order', 	't.name', 'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.types.filter_order_Dir',	'filter_order_Dir',	'ASC', 'word' );

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir;

		return $orderby;
	}

	/**
	 * Method to build the where clause of the query for the types
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentWhere()
	{
		global $mainframe, $option;

		$filter_state 		= $mainframe->getUserStateFromRequest( $option.'.types.filter_state', 'filter_state', '', 'word' );
		$search 			= $mainframe->getUserStateFromRequest( $option.'.types.search', 'search', '', 'string' );
		$search 			= $this->_db->getEscaped( trim(JString::strtolower( $search ) ) );

		$where = array();

		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$where[] = 't.published = 1';
			} else if ($filter_state == 'U' ) {
				$where[] = 't.published = 0';
			}
		}

		if ($search) {
			$where[] = ' LOWER(t.name) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $search, true ).'%', false );
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
		global $mainframe, $option;
		
		$filter_assigned	= $mainframe->getUserStateFromRequest( $option.'.types.filter_assigned', 'filter_assigned', '', 'word' );
		
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
	 * Method to (un)publish a type
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function publish($cid = array(), $publish = 1)
	{
		$user 	=& JFactory::getUser();

		if (count( $cid ))
		{
			$cids = implode( ',', $cid );

			$query = 'UPDATE #__flexicontent_types'
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
	 * Method to check if we can remove a type
	 * return false if there are items associated
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function candelete($cid = array())
	{
		$n		= count( $cid );
		if (count( $cid ))
		{
			for ($i = 0; $i < $n; $i++)
			{
			$query = 'SELECT COUNT( type_id )'
			. ' FROM #__flexicontent_items_ext'
			. ' WHERE type_id = '. (int) $cid[$i]
			;
			$this->_db->setQuery( $query );
			$count = $this->_db->loadResult();
			
			if ($count > 0) {
				return false;
				}
			}
		return true;
		}
	}

	/**
	 * Method to remove a type
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
			$query = 'DELETE FROM #__flexicontent_types'
					. ' WHERE id IN ('. $cids .')'
					;
			$this->_db->setQuery( $query );
			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// delete fields_type relations
			$query = 'DELETE FROM #__flexicontent_fields_type_relations'
					. ' WHERE type_id IN ('. $cids .')'
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
	 * Method to copy types
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function copy($cid = array())
	{
		if (count( $cid ))
		{
			foreach ($cid as $id) {
				$type  =& $this->getTable('flexicontent_types', '');
				$type->load($id);
				$type->id = 0;
				$type->name = $type->name . ' [copy]';
				$type->alias = JFilterOutput::stringURLSafe($type->name);
				$type->check();
				$type->store();
				
				$query 	= 'SELECT * FROM #__flexicontent_fields_type_relations'
						. ' WHERE type_id = ' . (int)$id
						;
				$this->_db->setQuery($query);
				$rels = $this->_db->loadObjectList();
				
				foreach ($rels as $rel) {
					$query = 'INSERT INTO #__flexicontent_fields_type_relations (`field_id`, `type_id`, `ordering`) VALUES(' . (int)$rel->field_id . ',' . $type->id . ',' . (int)$rel->ordering . ')';
					$this->_db->setQuery($query);
					$this->_db->query();
				}
			}
			return true;
		}
		return false;
	}

	/**
	 * Method to set the access level of the Types
	 *
	 * @access	public
	 * @param 	integer id of the category
	 * @param 	integer access level
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function access($id, $access)
	{
		global $mainframe;
		$row =& JTable::getInstance('flexicontent_types', '');

		$row->load( $this->_id );
		$row->id = $id;
		$row->access = $access;

		if ( !$row->check() ) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		if ( !$row->store() ) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		
		$mainframe->redirect( 'index.php?option=com_flexicontent&view=types' );

	}
}
?>