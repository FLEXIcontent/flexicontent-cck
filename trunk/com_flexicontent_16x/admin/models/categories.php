<?php
/**
 * @version 1.5 stable $Id: categories.php 184 2010-04-04 06:08:30Z emmanuel.danan $
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

//jimport('joomla.application.component.model');
jimport('joomla.application.component.modellist');

/**
 * FLEXIcontent Component Categories Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelCategories extends JModelList
{
	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Categorie id
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct() {
		parent::__construct();

		$array = JRequest::getVar('cid',  0, '', 'array');
		$this->setId((int)$array[0]);

	}

	/**
	 * Method to set the category identifier
	 *
	 * @access	public
	 * @param	int Category identifier
	 */
	function setId($id) {
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
	}

	/**
	 * @return	string
	 * @since	1.6
	 */
	function getListQuery() {
		// Create a new query object.
		$db = $this->getDbo();
		$filter_state= $this->getState('com_flexicontent.categories.filter_state');
		$query = $db->getQuery(true);
		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select',
				'c.*, u.name AS editor, g.title AS groupname, COUNT(rel.catid) AS nrassigned'
			)
		);
		$query->from('#__categories AS c');
		$query->join('LEFT', '#__flexicontent_cats_item_relations AS rel ON rel.catid = c.id');
		$query->join('LEFT', '#__usergroups AS g ON g.id = c.access');
		$query->join('LEFT', '#__users AS u ON u.id = c.checked_out');
		//$query->where("c.extension = 'com_content'");
		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$query->where("c.published = 1");
			} else if ($filter_state == 'U' ) {
				$query->where("c.published = 0");
			}
		}
		$query->where(' (c.lft > ' . FLEXI_CATEGORY_LFT . ' AND c.rgt < ' . FLEXI_CATEGORY_RGT . ')');
		// Filter by search in title
		$search = $this->getState('com_flexicontent.categories.search');
		if (!empty($search)) {			
			$search = $db->Quote('%'.$db->getEscaped($search, true).'%');
			$query->where('(c.title LIKE '.$search.' OR c.alias LIKE '.$search.' OR c.note LIKE '.$search.')');
		}
		$query->group('c.id');
		// Add the list ordering clause.
		$query->order($db->getEscaped($this->getState('com_flexicontent.categories.filter_order', 'c.lft')).' '.$db->getEscaped($this->getState('com_flexicontent.categories.filter_order_Dir', 'ASC')));
		//echo nl2br(str_replace('#__','jos_',$query));
		//echo $query->__toString();
		return $query;
	}

	/**
	 * Method to get a pagination object for the categories
	 *
	 * @access public
	 * @return integer
	 */
	function &getPaginationx()
	{
		if ($this->_pagination == null) {
			$this->getData();
		}
		return $this->_pagination;
	}

	/**
	 * Method to (un)publish a category
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
			if (!$publish) {
				// Add all children to the list
				foreach ($cid as $id)
				{
					$this->_addCategories($id, $cid);
				}
			} else {
				// Add all parents to the list
				foreach ($cid as $id)
				{
					$this->_addCategories($id, $cid, 'parents');
				}
			}
						
			$cids = implode( ',', $cid );

			$query = 'UPDATE #__categories'
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
		return $cid;
	}

	/**
	 * Method to move a category
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function move($direction)
	{
		$row =& JTable::getInstance('flexicontent_categories','');

		if (!$row->load( $this->_id ) ) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		if (!$row->move( $direction, 'parent_id = '.$row->parent_id )) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		
		return true;
	}

	/**
	 * Method to order categories
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function saveorder($cid = array(), $order)
	{
		$row 	=& JTable::getInstance('flexicontent_categories','');
		
		$groupings = array();

		// update ordering values
		for( $i=0; $i < count($cid); $i++ )
		{
			$row->load( (int) $cid[$i] );
			
			// track categories
			$groupings[] = $row->parent_id;

			if ($row->ordering != $order[$i])
			{
				$row->ordering = $order[$i];
				if (!$row->store()) {
					$this->setError($this->_db->getErrorMsg());
					return false;
				}
			}
		}
		
		// execute updateOrder for each parent group
		$groupings = array_unique( $groupings );
		foreach ($groupings as $group){
			$row->reorder('parent_id = '.$group.' AND section = '.FLEXI_CATEGORY);
		}

		return true;
	}

	/**
	 * Method to remove a category
	 *
	 * @access	public
	 * @return	string $msg
	 * @since	1.0
	 */
	function delete($cids)
	{		
		$params = & JComponentHelper::getParams('com_flexicontent');
		
		// Add all children to the list
		foreach ($cids as $id)
		{
			$this->_addCategories($id, $cids);
		}
				
		$cids = implode( ',', $cids );

		$query = 'SELECT c.id, c.parent_id, c.title, COUNT( e.catid ) AS numcat'
				. ' FROM #__categories AS c'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS e ON e.catid = c.id'
				. ' WHERE c.id IN ('. $cids .')'
				. ' GROUP BY c.id'
				;
		$this->_db->setQuery( $query );

		if (!($rows = $this->_db->loadObjectList())) {
			JError::raiseError( 500, $this->_db->stderr() );
			return false;
		}
		
		$err = array();
		$cid = array();
		
		//TODO: Categories and its childs without assigned items will not be deleted if another tree has any item entry 
		foreach ($rows as $row) {
			if ($row->numcat == 0) {				
				$cid[] = $row->id;
			} else {
				$err[] = $row->title;
			}
		}
		
		if (count( $cid ) && count($err) == 0)
		{
			$cids = implode( ',', $cid );
			$query = 'DELETE FROM #__categories'
					. ' WHERE id IN ('. $cids .')';

			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// remove also categories acl if applicable
			if (FLEXI_ACCESS) {
				$query 	= 'DELETE FROM #__flexiaccess_acl'
						. ' WHERE acosection = ' . $this->_db->Quote('com_content')
						. ' AND axosection = ' . $this->_db->Quote('category')
						. ' AND axo IN ('. $cids .')'
						;
				$this->_db->setQuery( $query );
			}
			
			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}

		if (count($err)) {
			$cids 	= implode( ', ', $err );
			if (count($err) < 2) {
	    		$msg 	= JText::sprintf( 'FLEXI_ITEM_ASSIGNED_CATEGORY', $cids );
			} else {
	    		$msg 	= JText::sprintf( 'FLEXI_ITEMS_ASSIGNED_CATEGORY', $cids );
			}
    		return $msg;
		} else {
			$total 	= count( $cid );
			$msg 	= $total.' '.JText::_( 'FLEXI_CATEGORIES_DELETED' );
			return $msg;
		}
	}
	
	/**
	 * Method to set the access level of the category
	 *
	 * @access	public
	 * @param integer id of the category
	 * @param integer access level
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function access($id, $access)
	{				
		$category  =& $this->getTable('flexicontent_categories', '');
		
		//handle childs
		$cids = array();
		$cids[] = $id;
		$this->_addCategories($id, $cids);
		
		foreach ($cids as $cid) {
			
			$category->load( (int)$cid );
			
			if ($category->access < $access) {				
				$category->access = $access;
			} else {
				$category->load( $id );
				$category->access = $access;
			}
			
			if ( !$category->check() ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			if ( !$category->store() ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
		}

		//handle parents
		$pcids = array();
		$this->_addCategories($id, $pcids, 'parents');
				
		foreach ($pcids as $pcid) {
			
			if($pcid == 0 || $pcid == $id) {
				continue;
			}
			
			$category->load( (int)$pcid );
			
			if ($category->access > $access) {	

				$category->access = $access;
				
				if ( !$category->check() ) {
					$this->setError($this->_db->getErrorMsg());
					return false;
				}
				if ( !$category->store() ) {
					$this->setError($this->_db->getErrorMsg());
					return false;
				}
				
			}
		}
		return true;
	}
	
	/**
	 * Method to add children/parents to a specific category
	 *
	 * @param int $id
	 * @param array $list
	 * @param string $type
	 * @return oject
	 * 
	 * @since 1.0
	 */
	function _addCategories($id, &$list, $type = 'children')
	{
		// Initialize variables
		$return = true;
		
		if ($type == 'children') {
			$get = 'id';
			$source = 'parent_id';
		} else {
			$get = 'parent_id';
			$source = 'id';
		}

		// Get all rows with parent of $id
		$query = 'SELECT '.$get
				. ' FROM #__categories'
				. ' WHERE section = ' . FLEXI_CATEGORY
				. ' AND '.$source.' = '.(int) $id;
		$this->_db->setQuery( $query );
		$rows = $this->_db->loadObjectList();

		// Make sure there aren't any errors
		if ($this->_db->getErrorNum()) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		
		// Recursively iterate through all children
		foreach ($rows as $row)
		{
			$found = false;
			foreach ($list as $idx)
			{
				if ($idx == $row->$get) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				$list[] = $row->$get;
			}
			$return = $this->_addCategories($row->$get, $list, $type);
		}
		return $return;
	}

}
?>