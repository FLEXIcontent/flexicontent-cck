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
 * FLEXIcontent Component Categories Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelCategories extends JModelLegacy
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
	function __construct()
	{
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
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
	}

	/**
	 * Method to get categories data
	 *
	 * @access public
	 * @return array
	 * @since	1.0
	 */
	function getData()
	{
		$app = JFactory::getApplication();
		$db = $this->getDbo();
		$option = JRequest::getVar('option');
		
		$filter_order     = $app->getUserStateFromRequest( $option.'.categories.filter_order',     'filter_order',     'c.ordering', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.categories.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$filter_state     = $app->getUserStateFromRequest( $option.'.categories.filter_state',     'filter_state',     '', 'word' );
		$search           = $app->getUserStateFromRequest( $option.'.categories.search', 'search', '', 'string' );
		$search           = trim( JString::strtolower( $search ) );
		$limit          = $app->getUserStateFromRequest( $option.'.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart     = $app->getUserStateFromRequest( $option.'.categories.limitstart', 'limitstart', 0, 'int' );
		
		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', c.ordering';
		
		$where = array();
		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$where[] = 'c.published = 1';
			} else if ($filter_state == 'U' ) {
				$where[] = 'c.published = 0';
			}
		}
		
		$where 		= ( count( $where ) ? ' AND ' . implode( ' AND ', $where ) : '' );
		
		//select the records
		//note, since this is a tree we have to do the limits code-side
		if ($search) {			
			$query = 'SELECT c.id'
					. ' FROM #__categories AS c'
					. ' WHERE LOWER(c.title) LIKE '.$db->Quote( '%'.$db->getEscaped( $search, true ).'%', false )
					. ' AND c.section = ' . FLEXI_SECTION
					. $where
					;
			$db->setQuery( $query );
			$search_rows = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();					
		}
		
		$query = 'SELECT c.*, u.name AS editor, g.name AS groupname, COUNT(rel.catid) AS nrassigned, c.params as config '
					. ' FROM #__categories AS c'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
					. ' LEFT JOIN #__groups AS g ON g.id = c.access'
					. ' LEFT JOIN #__users AS u ON u.id = c.checked_out'
					. ' LEFT JOIN #__sections AS sec ON sec.id = c.section'
					. ' WHERE c.section = ' . FLEXI_SECTION
					. ' AND sec.scope = ' . $db->Quote('content')
					. $where
					. ' GROUP BY c.id'
					. $orderby
					;
		$db->setQuery( $query );
		$rows = $db->loadObjectList();
		//establish the hierarchy of the categories
		$children = array();
		
		//set depth limit
		$levellimit = 10;
		
		foreach ($rows as $child) {
			$parent = $child->parent_id;
			$list 	= @$children[$parent] ? $children[$parent] : array();
			array_push($list, $child);
			$children[$parent] = $list;
		}
    	
		//get list of the items
		$list = flexicontent_cats::treerecurse(0, '', array(), $children, false, max(0, $levellimit-1));

		//eventually only pick out the searched items.
		if ($search) {
			$list1 = array();

			foreach ($search_rows as $sid )
			{
				foreach ($list as $item)
				{
					if ($item->id == $sid) {
						$list1[] = $item;
					}
				}
			}
			// replace full list with found items
			$list = $list1;
		}
		
    	$total = count( $list );

		jimport('joomla.html.pagination');
		$this->_pagination = new JPagination( $total, $limitstart, $limit );

		// slice out elements based on limits
		$list = array_slice( $list, $this->_pagination->limitstart, $this->_pagination->limit );
		
		return $list;
	}

	/**
	 * Method to get a pagination object for the categories
	 *
	 * @access public
	 * @return integer
	 */
	function &getPagination()
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
		if (count( $cid ))
		{
			$user = JFactory::getUser();
			
			// Add all children to the list
			if (!$publish)  foreach ($cid as $id)  $this->_addCategories($id, $cid);
			// Add all parents to the list
			else            foreach ($cid as $id)  $this->_addCategories($id, $cid, 'parents');
			
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
		$row = JTable::getInstance('flexicontent_categories','');

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
		$row = JTable::getInstance('flexicontent_categories','');
		
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
			$row->reorder('parent_id = '.$group.(!FLEXI_J16GE ? ' AND section = '.FLEXI_SECTION : ' AND extension="'.FLEXI_CAT_EXTENSION.'" ') );
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
		$params = JComponentHelper::getParams('com_flexicontent');
		$table  = $this->getTable('flexicontent_categories', '');
		$user 	= JFactory::getUser();
		if (!$cids || !is_array($cids) || !count($cids)) return "No categories given for deletion";
		
		// Add all children to the list, since we must check if they have assigned items
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
		
		$rows = $this->_db->loadObjectList();
		if ( !$rows ) {
			$msg = $this->_db->getErrorNum() ? $this->_db->stderr() : 'Given category(ies) were not found';
			$this->setError( $msg );
			return false;
		}
		
		if (FLEXI_J16GE) {
			// Check access to delete of categories, this may seem redundant, but it is a security check in case user manipulates the backend adminform ...
			foreach ($rows as $row) {
				$canDelete		= $user->authorise('core.delete', 'com_content.category.'.$row->id);
				$canDeleteOwn	= $user->authorise('core.delete.own', 'com_content.category.'.$row->id) && $row->created_user_id == $user->get('id');
				if	( !$canDelete && !$canDeleteOwn ) {
					$this->setError(
						'You are not authorised to delete category with id: '. $row->id
						.'<br />NOTE: when deleting a category the children categories will get deleted too'
					);
					return false;
				}
			}
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
		
		// Remove categories only if no errors were found
		if (count( $cid ) && count($err) == 0)
		{
			if (FLEXI_J16GE) {
				// table' is object of 'JTableNested' extended class, which will also delete assets
				$cids = $cid;
				foreach ($cids as $id) {
					$table-> id = $id;
					$table->delete($id);
				}
			} else {

				$cids = implode( ',', $cid );
				$query = 'DELETE FROM #__categories'
						. ' WHERE id IN ('. $cids .')';
				
				$this->_db->setQuery( $query );
				
				if(!$this->_db->query()) {
					$this->setError($this->_db->getErrorMsg());
					return false;
				}
			}
			
			// remove also categories acl if applicable
			if (FLEXI_ACCESS) {
				$query 	= 'DELETE FROM #__flexiaccess_acl'
						. ' WHERE acosection = ' . $this->_db->Quote('com_content')
						. ' AND axosection = ' . $this->_db->Quote('category')
						. ' AND axo IN ('. $cids .')'
						;
				$this->_db->setQuery( $query );
				
				if(!$this->_db->query()) {
					$this->setError($this->_db->getErrorMsg());
					return false;
				}
			}
		}
		
		// Create result message and return it
		if ( count($err) ) {
			$err_string = count($err)==1 ? 'FLEXI_ITEM_ASSIGNED_CATEGORY' : 'FLEXI_ITEMS_ASSIGNED_CATEGORY';
			$msg = JText::sprintf( $err_string, implode( ', ', $err ) ) ."<br/>\n";
		} else {
			$msg = count( $cid ) .' '. JText::_( 'FLEXI_CATEGORIES_DELETED' );
		}
		return $msg;
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
	function saveaccess($id, $access)
	{
		$category = $this->getTable('flexicontent_categories', '');
		
		//handle childs
		$cids = array();
		$cids[] = $id;
		$this->_addCategories($id, $cids);   // Propagate access level to children
		
		foreach ($cids as $cid) {
			
			$category->load( (int)$cid );
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
				. ' FROM #__categories as c'
				. ' WHERE'.(!FLEXI_J16GE ? ' c.section = '.FLEXI_SECTION : ' c.extension="'.FLEXI_CAT_EXTENSION.'" ')
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