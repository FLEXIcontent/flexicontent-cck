<?php
/**
 * @version 1.5 stable $Id: categories.php 1619 2013-01-09 02:50:25Z ggppdk $
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
	 * Category id
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
	 * Method to get the query used to retrieve categories data
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	function getListQuery()
	{
		$app  = JFactory::getApplication();
		$db   = JFactory::getDBO();
		$user = JFactory::getUser();
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');
		global $globalcats;
		
		$order_property = !FLEXI_J16GE ? 'c.ordering' : 'c.lft';
		$filter_order     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order',     'filter_order',     $order_property, 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$filter_cats      = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_cats',			 'filter_cats',			 '', 'int' );
		$filter_state     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_state',     'filter_state',     '', 'string' );
		$filter_access    = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_access',    'filter_access',    '', 'string' );
		$filter_level     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_level',     'filter_level',     '', 'string' );
		if (FLEXI_J16GE) {
			$filter_language  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_language',  'filter_language',  '', 'string' );
		}
		$search           = $app->getUserStateFromRequest( $option.'.'.$view.'.search',           'search',           '', 'string' );
		$search           = trim( JString::strtolower( $search ) );

		// Create a new query object.
		$query = $db->getQuery(true);
		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select',
				'c.*, u.name AS editor, level.title AS access_level, COUNT(rel.catid) AS nrassigned, c.params as config, ag.title AS access_level '
			)
		);
		$query->from('#__categories AS c');
		$query->select('l.title AS language_title');
		$query->join('LEFT', '#__languages AS l ON l.lang_code = c.language');
		$query->join('LEFT', '#__flexicontent_cats_item_relations AS rel ON rel.catid = c.id');
		$query->join('LEFT', '#__viewlevels as level ON level.id=c.access');
		$query->join('LEFT', '#__users AS u ON u.id = c.checked_out');
		$query->join('LEFT', '#__viewlevels AS ag ON ag.id = c.access');
		$query->where("c.extension = '".FLEXI_CAT_EXTENSION."' ");
		
		
		// Filter by publicationd state
		if (is_numeric($filter_state)) {
			$query->where('c.published = ' . (int) $filter_state);
		}
		elseif ( $filter_state === '') {
			$query->where('c.published IN (0, 1)');
		}
		
		// Filter by access level
		if ( $filter_access ) {
			$query->where('c.access = '.(int) $filter_access);
		}
		
		if ( $filter_cats ) {
			// Limit category list to those contain in the subtree of the choosen category
			$query->where(' c.id IN (SELECT cat.id FROM #__categories AS cat JOIN #__categories AS parent ON cat.lft BETWEEN parent.lft AND parent.rgt WHERE parent.id='. (int) $filter_cats.')' );
		} else {
			// Limit category list to those containing CONTENT (joomla articles)
			$query->where(' (c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY) . ')');
		}
		
		// Filter on the level.
		if ( $filter_level ) {
			$query->where('c.level <= '.(int) $filter_level);
		}
		
		// Filter by language
		if ( $filter_language ) {
			$query->where('l.lang_code = '.$db->Quote( $filter_language ) );
		}
		
		// Implement View Level Access
		if (!$user->authorise('core.admin'))
		{
			$groups	= implode(',', $user->getAuthorisedViewLevels());
			$query->where('c.access IN ('.$groups.')');
		}
		
		// Filter by search word (can be also be  id:NN  OR author:AAAAA)
		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('c.id = '.(int) substr($search, 3));
			}
			elseif (stripos($search, 'author:') === 0) {
				$search = $db->Quote('%'.$db->escape(substr($search, 7), true).'%');
				$query->where('(u.name LIKE '.$search.' OR u.username LIKE '.$search.')');
			}
			else {
				$search = $db->Quote('%'.$db->escape($search, true).'%');
				$query->where('(c.title LIKE '.$search.' OR c.alias LIKE '.$search.' OR c.note LIKE '.$search.')');
			}
		}
		
		$query->group('c.id');
		// Add the list ordering clause.
		$query->order($db->escape($filter_order.' '.$filter_order_Dir));
		
		//echo nl2br(str_replace('#__','jos_',$query));
		//echo str_replace('#__', 'jos_', $query->__toString());
		return $query;
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
			if ($publish!=1)  foreach ($cid as $id)  $this->_addCategories($id, $cid);
			// Add all parents to the list
			if ($publish==1)  foreach ($cid as $id)  $this->_addCategories($id, $cid, 'parents');
			
			// Get the owner of all categories
			$query = 'SELECT id, created_user_id'
					. ' FROM #__categories as c'
					. ' WHERE'.(!FLEXI_J16GE ? ' c.section = '.FLEXI_SECTION : ' c.extension="'.FLEXI_CAT_EXTENSION.'" ');
			$this->_db->setQuery( $query );
			$cats = $this->_db->loadObjectList('id');

			// Check access to change state of categories
			foreach ($cid as $catid) {
				$hasEditState			= $user->authorise('core.edit.state', 'com_content.category.'.$catid);
				$hasEditStateOwn	= $user->authorise('core.edit.state.own', 'com_content.category.'.$catid) && $cats[$catid]->created_user_id==$user->get('id');
				if (!$hasEditState && !$hasEditStateOwn) {
					$this->setError(
						'You are not authorised to change state of category with id: '. $catid
						.'<br />NOTE: when publishing a category the parent categories will get published'
						.'<br />NOTE: when unpublishing a category the children categories will get unpublished'
					);
					return false;
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
	 * Method to save the reordered nested set tree.
	 * First we save the new order values in the lft values of the changed ids.
	 * Then we invoke the table rebuild to implement the new ordering.
	 *
	 * @param   array    $idArray    An array of primary key ids.
	 * @param   integer  $lft_array  The lft value
	 *
	 * @return  boolean  False on failure or error, True otherwise
	 *
	 * @since   1.6
	*/
	public function saveorder($idArray = null, $lft_array = null)
	{
		// Get an instance of the table object.
		$table = $this->getTable();

		if (!$table->saveorder($idArray, $lft_array))
		{
			$this->setError($table->getError());
			return false;
		}

		// Clear the cache
		$this->cleanCache();

		return true;
	}
	
	
	/**
	 * Returns a Table object, always creating it
	 *
	 * @param	type	The table type to instantiate
	 * @param	string	A prefix for the table class name. Optional.
	 * @param	array	Configuration array for model. Optional.
	 * @return	JTable	A database object
	 * @since	1.6
	*/
	public function getTable($type = 'flexicontent_categories', $prefix = '', $config = array()) {
		return JTable::getInstance($type, $prefix, $config);
	}
	
	
	/**
	 * Check in a category
	 *
	 * @since	1.6
	 */
	/*function checkin()
	{
		$cid      = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$pk       = (int)$cid[0];
		
		// Only attempt to check the row in if it exists.
		if ($pk)
		{
			$user = JFactory::getUser();

			// Get an instance of the row to checkin.
			$table = $this->getTable();
			if (!$table->load($pk))
			{
				$this->setError($table->getError());
				return false;
			}

			// Check if this is the user having previously checked out the row.
			if ($table->checked_out > 0 && $table->checked_out != $user->get('id') && !$user->authorise('core.admin', 'com_checkin'))
			{
				$this->setError(JText::_('JLIB_APPLICATION_ERROR_CHECKIN_USER_MISMATCH'));
				return false;
			}

			// Attempt to check the row in.
			if (!$table->checkin($pk))
			{
				$this->setError($table->getError());
				return false;
			}
		}

		return true;
	}*/
	
	
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
