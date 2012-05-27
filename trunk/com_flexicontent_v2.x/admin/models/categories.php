<?php
/**
 * @version 1.5 stable $Id: categories.php 1223 2012-03-30 08:34:34Z ggppdk $
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
	 * Method to get the query used to retrieve categories data
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	function getListQuery()
	{
		$mainframe = &JFactory::getApplication();
		$db = $this->getDbo();
		$option = JRequest::getVar('option');
		//$filter_state= $this->getState($option.'.categories.filter_state');
		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.categories.filter_order', 		'filter_order', 	'c.lft', 'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.categories.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		$filter_state 		= $mainframe->getUserStateFromRequest( $option.'.categories.filter_state', 		'filter_state', 	'*', 'word' );
		$filter_language 		= $mainframe->getUserStateFromRequest( $option.'.categories.filter_language', 'filter_language', 	'*', 'cmd' );
		$search 			= $mainframe->getUserStateFromRequest( $option.'.categories.search', 			'search', 			'', 'string' );
		//$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );

		// Create a new query object.
		$query = $db->getQuery(true);
		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select',
				'c.*, u.name AS editor, g.title AS groupname, COUNT(rel.catid) AS nrassigned, c.params as config '
			)
		);
		$query->from('#__categories AS c');
		$query->select('l.title AS language_title');
		$query->join('LEFT', '`#__languages` AS l ON l.lang_code = c.language');
		$query->join('LEFT', '#__flexicontent_cats_item_relations AS rel ON rel.catid = c.id');
		$query->join('LEFT', '#__usergroups AS g ON g.id = c.access');
		$query->join('LEFT', '#__users AS u ON u.id = c.checked_out');
		$query->where("c.extension = '".FLEXI_CAT_EXTENSION."' ");
		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$query->where("c.published = 1");
			} else if ($filter_state == 'U' ) {
				$query->where("c.published = 0");
			}
		}
		if ( $filter_language && $filter_language != '*' ) {
			echo $filter_language;
			$query->where("l.lang_code = '".$filter_language."'");
		}
		$query->where(' (c.lft > ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt < ' . $this->_db->Quote(FLEXI_RGT_CATEGORY) . ')');
		// Filter by search in title
		//$search = $this->getState('com_flexicontent.categories.search');
		if (!empty($search)) {			
			$search = $db->Quote('%'.$db->getEscaped(trim($search), true).'%');
			$query->where('(c.title LIKE '.$search.' OR c.alias LIKE '.JString::strtolower($search).' OR c.note LIKE '.$search.')');
		}
		$query->group('c.id');
		// Add the list ordering clause.
		$query->order($db->getEscaped($filter_order).' '.$db->getEscaped($filter_order_Dir));
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
			
			$user	=& JFactory::getUser();
			
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
	 * Method to remove a category
	 *
	 * @access	public
	 * @return	string $msg
	 * @since	1.0
	 */
	function delete($cids)
	{
		$params = & JComponentHelper::getParams('com_flexicontent');
		$table  = & $this->getTable('flexicontent_categories', '');
		$user 	= & JFactory::getUser();
		
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
		$cids = $cid;
			foreach ($cids as $id) {
				$table-> id = $id;
				$table->delete($id);
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
	function saveaccess($id, $access)
	{
		$category  =& $this->getTable('flexicontent_categories', '');
		
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
