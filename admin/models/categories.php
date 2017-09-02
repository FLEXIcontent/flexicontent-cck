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

jimport('legacy.model.list');
use Joomla\String\StringHelper;

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
		
		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');
		$fcform  = $jinput->get('fcform', 0, 'int');
		
		$p = $option.'.'.$view.'.';
		
		
		// **************
		// view's Filters
		// **************
		
		// Various filters
		$filter_cats      = $fcform ? $jinput->get('filter_cats',     0,  'int')     :  $app->getUserStateFromRequest( $p.'filter_cats',      'filter_cats',      0,   'int' );
		$filter_state     = $fcform ? $jinput->get('filter_state',    '', 'string')  :  $app->getUserStateFromRequest( $p.'filter_state',     'filter_state',     '',  'string' );   // we may check for '*', so string filter
		$filter_access    = $fcform ? $jinput->get('filter_access',   '', 'int')     :  $app->getUserStateFromRequest( $p.'filter_access',    'filter_access',    '',  'int' );
		$filter_level     = $fcform ? $jinput->get('filter_level',    '', 'int')     :  $app->getUserStateFromRequest( $p.'filter_level',     'filter_level',     '',  'int' );
		$filter_language  = $fcform ? $jinput->get('filter_language', '', 'string')  :  $app->getUserStateFromRequest( $p.'filter_language',  'filter_language',  '',  'string' );
		
		$this->setState('filter_cats',     $filter_cats);
		$this->setState('filter_state',    $filter_state);
		$this->setState('filter_access',   $filter_access);
		$this->setState('filter_level',    $filter_level);
		$this->setState('filter_language', $filter_language);
		
		$app->setUserState($p.'filter_cats',     $filter_cats);
		$app->setUserState($p.'filter_state',    $filter_state);
		$app->setUserState($p.'filter_access',   $filter_access);
		$app->setUserState($p.'filter_level',    $filter_level);
		$app->setUserState($p.'filter_language', $filter_language);
		
		
		// Item ID filter
		$filter_id  = $fcform ? $jinput->get('filter_id', '', 'int')  :  $app->getUserStateFromRequest( $p.'filter_id',  'filter_id',  '',  'int' );
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
		
		$default_order     = 'a.lft';
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
	 * Method to count assigned items for the given categories
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	function getAssignedItems($cids)
	{
		if (empty($cids))
		{
			return array();
		}

		$query = ' SELECT rel.catid, COUNT(rel.itemid) AS nrassigned'
			. ' FROM #__flexicontent_cats_item_relations AS rel'
			. ' WHERE rel.catid IN (' . implode(',', $cids) . ')'
			. ' GROUP BY rel.catid'
			;

		return $this->_db->setQuery($query)->loadObjectList('catid');
	}
	
	
	/**
	 * Method to get parameters of parent categories
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	function getParentParams($cid)
	{
		if (empty($cid))
		{
			return array();
		}

		global $globalcats;

		$query = ' SELECT id, params'
			. ' FROM #__categories'
			. ' WHERE id IN (' . $globalcats[$cid]->ancestors . ')'
			. ' ORDER BY level ASC'
			;
		return $this->_db->setQuery($query)->loadObjectList('id');
	}
	
	
	/**
	 * Method to count assigned items for the given categories
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	function countItemsByState($cids)
	{
		if (empty($cids))
		{
			return array();
		}

		$query = ' SELECT rel.catid, i.state, COUNT(rel.itemid) AS nrassigned'
			. ' FROM #__flexicontent_cats_item_relations AS rel'
			. ' JOIN #__content AS i ON i.id=rel.itemid'
			. ' WHERE rel.catid IN (' . implode(',', $cids) . ')'
			. ' GROUP BY rel.catid, i.state'
			;
		$data = $this->_db->setQuery($query)->loadObjectList();

		$assigned = array();
		foreach($data as $catid => $d)
		{
			$assigned[$d->catid][$d->state] = $d->nrassigned;
		}

		return $assigned;
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
		// Create a query with all its clauses: WHERE, HAVING and ORDER BY, etc
		global $globalcats;

		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		$user = JFactory::getUser();

		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');
		$fcform  = $jinput->get('fcform', 0, 'int');

		// various filters
		$filter_cats      = $this->getState( 'filter_cats' );
		$filter_state     = $this->getState( 'filter_state' );
		$filter_access    = $this->getState( 'filter_access' );
		$filter_level     = $this->getState( 'filter_level' );
		$filter_language  = $this->getState( 'filter_language' );

		// filter id
		$filter_id = $this->getState( 'filter_id' );

		// text search
		$search = $this->getState( 'search' );
		$search = StringHelper::trim( StringHelper::strtolower( $search ) );

		// ordering filters
		$filter_order     = $this->getState( 'filter_order' );
		$filter_order_Dir = $this->getState( 'filter_order_Dir' );

		// Create a new query object.
		$query = $this->_db->getQuery(true);
		
		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select',
				'a.*'
				.', u.name AS editor, CASE WHEN level.title IS NULL THEN CONCAT_WS(\'\', \'deleted:\', a.access) ELSE level.title END AS access_level'
				// because of multi-multi category-item relation it is faster to calculate ITEM COUNT with a seperate query
				// if it was single mapping e.g. like it is 'item' TO 'content type' or 'item' TO 'creator' we could use a subquery
				// the more categories are listed (query LIMIT) the bigger the performance difference ...
				//.', (SELECT COUNT(*) FROM #__flexicontent_cats_item_relations AS rel WHERE rel.catid = a.id) AS nrassigned '
				.', a.params AS config, ag.title AS access_level '
			)
		)
		->from('#__categories AS a')
		->select('l.title AS language_title')
		->join('LEFT', '#__languages AS l ON l.lang_code = a.language')
		->join('LEFT', '#__viewlevels as level ON level.id = a.access')
		->join('LEFT', '#__users AS u ON u.id = a.checked_out')
		->join('LEFT', '#__viewlevels AS ag ON ag.id = a.access')
		->where('a.extension = ' . $this->_db->Quote(FLEXI_CAT_EXTENSION));

		// Filter by publication state
		if (is_numeric($filter_state))
		{
			$query->where('a.published = ' . (int) $filter_state);
		}

		elseif ( $filter_state === '')
		{
			$query->where('a.published IN (0, 1)');
		}

		// $filter_state === '*', or any other will allow all states including: archive, trashed 
		else ;

		// Filter by access level
		if ( $filter_access )
		{
			$query->where('a.access = '.(int) $filter_access);
		}

		// Limit category list to those contain in the subtree of the choosen category
		if ( $filter_cats )
		{
			$query->where(' a.id IN (SELECT cat.id FROM #__categories AS cat JOIN #__categories AS parent ON cat.lft BETWEEN parent.lft AND parent.rgt WHERE parent.id='. (int) $filter_cats.')' );
		}

		// Limit category list to those containing CONTENT (joomla articles)
		else
		{
			$query->where(' (a.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND a.rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY) . ')');
		}

		// Filter on the level.
		if ( $filter_level )
		{
			$query->where('a.level <= '.(int) $filter_level);
		}

		// Filter by language
		if ( $filter_language )
		{
			$query->where('a.language = ' . $this->_db->Quote( $filter_language ));
		}
		
		// Filter by id
		if ( $filter_id )
		{
			$query->where('a.id = '.(int) $filter_id);
		}
		
		// Implement View Level Access
		if (!$user->authorise('core.admin'))
		{
			$groups	= implode(',', JAccess::getAuthorisedViewLevels($user->id));
			$query->where('a.access IN (' . $groups . ')');
		}
		
		// Filter by search word (can be also be  id:NN  OR author:AAAAA)
		if (strlen($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where('a.id = ' . (int) substr($search, 3) );
			}
			elseif (stripos($search, 'author:') === 0)
			{
				$search_quoted = $this->_db->Quote('%' . $this->_db->escape(substr($search, 7), true) . '%');
				$query->where('(u.name LIKE ' . $search_quoted . ' OR u.username LIKE ' . $search_quoted . ')');
			}
			else
			{
				$search_quoted = $this->_db->Quote('%' . $this->_db->escape($search, true) . '%');
				$query->where('(a.title LIKE ' . $search_quoted . ' OR a.alias LIKE ' . $search_quoted . ' OR a.note LIKE ' . $search_quoted . ')');
			}
		}

		$query->group('a.id');

		// Add the list ordering clause.
		$query->order($this->_db->escape($filter_order.' '.$filter_order_Dir));
		
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
		if (!count($cid))
		{
			return array();
		}

		$user = JFactory::getUser();

		// Add all children to the list
		if ($publish!=1)  foreach ($cid as $id)  $this->_addCategories($id, $cid);

		// Add all parents to the list
		if ($publish==1)  foreach ($cid as $id)  $this->_addCategories($id, $cid, 'parents');

		// Get the owner of all categories
		$query = 'SELECT id, created_user_id'
			. ' FROM #__categories'
			. ' WHERE extension = ' . $this->_db->Quote(FLEXI_CAT_EXTENSION)
			;
		$this->_db->setQuery( $query );
		$cats = $this->_db->loadObjectList('id');

		// Check access to change state of categories
		foreach ($cid as $catid)
		{
			$hasEditState			= $user->authorise('core.edit.state', 'com_content.category.'.$catid);
			$hasEditStateOwn	= $user->authorise('core.edit.state.own', 'com_content.category.'.$catid) && $cats[$catid]->created_user_id==$user->get('id');
			if (!$hasEditState && !$hasEditStateOwn)
			{
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
		$this->_db->execute();

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
		$pk = (int)$this->_id;
		
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
		if ( !$rows )
		{
			$msg = $this->_db->getErrorNum() ? $this->_db->stderr() : 'Given category(ies) were not found';
			$this->setError( $msg );
			return false;
		}
		
		// Check access to delete of categories, this may seem redundant, but it is a security check in case user manipulates the backend adminform ...
		foreach ($rows as $row)
		{
			$canDelete		= $user->authorise('core.delete', 'com_content.category.'.$row->id);
			$canDeleteOwn	= $user->authorise('core.delete.own', 'com_content.category.'.$row->id) && $row->created_user_id == $user->get('id');
			if	( !$canDelete && !$canDeleteOwn )
			{
				$this->setError(
					'You are not authorised to delete category with id: '. $row->id
					.'<br />NOTE: when deleting a category the children categories will get deleted too'
				);
				return false;
			}
		}
		
		$err = array();
		$cid = array();
		
		//TODO: Categories and its childs without assigned items will not be deleted if another tree has any item entry 
		foreach ($rows as $row)
		{
			if ($row->numcat == 0)
			{
				$cid[] = $row->id;
			}
			else
			{
				$err[] = $row->title;
			}
		}

		// Remove categories only if no errors were found
		if (count( $cid ) && count($err) == 0)
		{
			// table' is object of 'JTableNested' extended class, which will also delete assets
			$cids = $cid;
			foreach ($cids as $id)
			{
				$table->id = $id;
				$table->delete($id);
			}
		}

		// Create result message and return it
		if ( count($err) )
		{
			$err_string = count($err)==1 ? 'FLEXI_ITEM_ASSIGNED_CATEGORY' : 'FLEXI_ITEMS_ASSIGNED_CATEGORY';
			$msg = JText::sprintf( $err_string, implode( ', ', $err ) ) ."<br/>\n";
		}
		else
		{
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
		
		foreach ($cids as $cid)
		{
			$category->load( (int)$cid );
			$category->access = $access;

			if ( !$category->check() )
			{
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			if ( !$category->store() )
			{
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}

		//handle parents
		$pcids = array();
		$this->_addCategories($id, $pcids, 'parents');
				
		foreach ($pcids as $pcid)
		{
			if ($pcid == 0 || $pcid == $id)
			{
				continue;
			}

			$category->load( (int) $pcid );

			if ($category->access > $access)
			{
				$category->access = $access;

				if ( !$category->check() )
				{
					$this->setError($this->_db->getErrorMsg());
					return false;
				}

				if ( !$category->store() )
				{
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

		$get = $type == 'children' ? 'id' : 'parent_id';
		$source = $type == 'children' ? 'parent_id' : 'id';

		// Get all rows with parent of $id
		$query = 'SELECT ' . $get
			. ' FROM #__categories'
			. ' WHERE extension = ' . $this->_db->Quote(FLEXI_CAT_EXTENSION)
			. '  AND ' . $source . ' = ' . (int) $id . ' AND ' . $get . ' <> 1'
			;
		$rows = $this->_db->setQuery($query)->loadObjectList();

		// Recursively iterate through all children
		foreach ($rows as $row)
		{
			$found = false;
			foreach ($list as $idx)
			{
				if ($idx == $row->$get)
				{
					$found = true;
					break;
				}
			}
			if (!$found)
			{
				$list[] = $row->$get;
			}
			$return = $this->_addCategories($row->$get, $list, $type);
		}

		return $return;
	}

}