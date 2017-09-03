<?php
/**
 * @version 1.5 stable $Id: fields.php 1640 2013-02-28 14:45:19Z ggppdk $
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
 * FLEXIcontent Component fields Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelFields extends JModelList
{
	var $records_dbtbl = 'flexicontent_fields';
	var $records_jtable = 'flexicontent_fields';

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
		
		
		// **************
		// view's Filters
		// **************
		
		// Various filters
		$filter_fieldtype = $fcform ? $jinput->get('filter_fieldtype', '', 'cmd')     :  $app->getUserStateFromRequest( $p.'filter_fieldtype', 'filter_fieldtype', '', 'cmd' );
		$filter_type      = $fcform ? $jinput->get('filter_type',      0,  'int')     :  $app->getUserStateFromRequest( $p.'filter_type',      'filter_type',      0,  'int' );
		$filter_state     = $fcform ? $jinput->get('filter_state',     '', 'string')  :  $app->getUserStateFromRequest( $p.'filter_state',    'filter_state',      '', 'string' );    // we may check for '*', so string filter
		$filter_access    = $fcform ? $jinput->get('filter_access',    0,  'int')     :  $app->getUserStateFromRequest( $p.'filter_access',    'filter_access',    0,  'int' );
		$filter_assigned  = $fcform ? $jinput->get('filter_assigned',  '', 'cmd')     :  $app->getUserStateFromRequest( $p.'filter_assigned',  'filter_assigned',  '', 'cmd' );
		
		$this->setState('filter_fieldtype', $filter_fieldtype);
		$this->setState('filter_type', $filter_type);
		$this->setState('filter_state', $filter_state);
		$this->setState('filter_access', $filter_access);
		$this->setState('filter_assigned', $filter_assigned);
		
		$app->setUserState($p.'filter_fieldtype', $filter_fieldtype);
		$app->setUserState($p.'filter_type', $filter_type);
		$app->setUserState($p.'filter_state', $filter_state);
		$app->setUserState($p.'filter_access', $filter_access);
		$app->setUserState($p.'filter_assigned', $filter_assigned);		
		
		
		// Text search
		$search = $fcform ? $jinput->get('search', '', 'string')  :  $app->getUserStateFromRequest( $p.'search',  'search',  '',  'string' );
		$this->setState('search', $search);
		$app->setUserState($p.'search', $search);



		// ****************************************
		// Ordering: filter_order, filter_order_Dir
		// ****************************************
		
		$default_order     = 't.ordering';
		$default_order_dir = 'ASC';
		
		$filter_order      = $fcform ? $jinput->get('filter_order',     $default_order,      'cmd')  :  $app->getUserStateFromRequest( $p.'filter_order',     'filter_order',     $default_order,      'cmd' );
		$filter_order_Dir  = $fcform ? $jinput->get('filter_order_Dir', $default_order_dir, 'word')  :  $app->getUserStateFromRequest( $p.'filter_order_Dir', 'filter_order_Dir', $default_order_dir, 'word' );
		
		if (!$filter_order)     $filter_order     = $default_order;
		if (!$filter_order_Dir) $filter_order_Dir = $default_order_dir;
		
		if ($filter_type && $filter_order == 't.ordering')
		{
			$filter_order = 'typeordering';
		}
		else if (!$filter_type && $filter_order == 'typeordering')
		{
			$filter_order = 't.ordering';
		}
		
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
	 * Method to get All fields by clear the where clause
	 *
	 * @access public
	 * @return array
	 * @since 3.0
	 */
	public function getAllItems()
	{
		// Load the list all data
		try
		{
			$query = $this->getListQuery()->clear('where');  // clear where clause
			$this->_db->setQuery($query/*, $limitstart=0, $limit=0*/);  // all items without limits
			
			$items = $this->_db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			//$this->setError($e->getMessage());
			//return false;
			JFactory::getApplication()->enqueueMessage( $e->getMessage() ,'error');
			return array();
		}
		
		// Return data
		return $items;
	}


	/**
	 * Method to get All fields by clearing the where clause
	 *
	 * @access public
	 * @return array
	 * @since 3.0
	 */
	public function getItems()
	{
		if (isset($this->_data))  return  $this->_data;
		try
		{
			$query = $this->getListQuery();
			$this->_db->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));
			
			$this->_data = $this->_db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			$this->_data = array();
			JFactory::getApplication()->enqueueMessage( $e->getMessage() ,'error');
			//$this->setError($e->getMessage());
			//return false;
		}
		
		// Get type data
		$this->_typeids = array();
		foreach($this->_data as $item)
		{
			$item->content_types = $item->typeids ? preg_split("/[\s]*,[\s]*/", $item->typeids) : array();
			foreach ($item->content_types as $type_id) {
				if ($type_id) $this->_typeids[$type_id] = 1;
			}
		}
		$this->_typeids = array_keys($this->_typeids);
		
		// Return data
		return $this->_data;
	}


	/**
	 * Method to build the query for the fields
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	protected function getListQuery($query = null)
	{
		if ($query instanceof JDatabaseQuery) return $query;
		
		// Get the WHERE, HAVING and ORDER BY clauses for the query
		$where		= trim($this->_buildContentWhere());
		$orderby	= trim($this->_buildContentOrderBy());
		$having		= trim($this->_buildContentHaving());
		
		$db =  JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select(
			$this->getState( 'list.select',
				't.*, u.name AS editor, COUNT(rel.type_id) AS nrassigned, GROUP_CONCAT(rel.type_id SEPARATOR  ",") AS typeids, '.
				' CASE WHEN level.title IS NULL THEN CONCAT_WS(\'\', \'deleted:\', t.access) ELSE level.title END AS access_level, rel.ordering as typeordering, t.field_type as type, plg.name as friendly'
			)
		);
		$query->from('#__' . $this->records_dbtbl . ' AS t');
		$query->join('LEFT', '#__extensions AS plg ON (plg.element = t.field_type AND plg.`type`=\'plugin\' AND plg.folder=\'flexicontent_fields\')');
		$query->join('LEFT', '#__flexicontent_fields_type_relations AS rel ON rel.field_id = t.id');
		$query->join('LEFT', '#__viewlevels AS level ON level.id=t.access');
		$query->join('LEFT', '#__users AS u ON u.id = t.checked_out');
		if ($where) $query->where($where);
		$query->group('t.id');
		if ($having) $query->having($having);
		if ($orderby) $query->order($orderby);
		
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
		$filter_type      = $this->getState( 'filter_type' );
		$filter_order     = $this->getState( 'filter_order' );
		$filter_order_Dir = $this->getState( 'filter_order_Dir' );
		
		$orderby 	= ' '.$filter_order.' '.$filter_order_Dir;

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
		static $where;
		if(isset($where))  return $where;

		// Various filters
		$filter_fieldtype = $this->getState( 'filter_fieldtype' );
		$filter_type      = $this->getState( 'filter_type' );
		$filter_state     = $this->getState( 'filter_state' );
		$filter_access    = $this->getState( 'filter_access' );
		
		// Text search
		$search = $this->getState( 'search' );
		$search = StringHelper::trim( StringHelper::strtolower( $search ) );

		$where = array();

		// Filter by item-type (assigned-to)
		if ( $filter_fieldtype ) {
			if ( $filter_fieldtype == 'C' ) {
				$where[] = 't.iscore = 1';
			} else if ($filter_fieldtype == 'NC' ) {
				$where[] = 't.iscore = 0';
			} else if ($filter_fieldtype == 'BV' ) {
				$where[] = '(t.iscore = 0 OR t.id = 1)';
			} else {
				$where[] = 't.field_type = "'.$filter_fieldtype.'"';
			}
		}
		
		// Filter by field-type
		if ( $filter_type ) {
			$where[] = 'rel.type_id = ' . (int) $filter_type;
		}
		
		// Filter by state
		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$where[] = 't.published = 1';
			} else if ($filter_state == 'U' ) {
				$where[] = 't.published = 0';
			} // else ALL: published & unpublished (in future we may have more states, e.g. archived, trashed)
		}

		// Filter by access level
		if ( $filter_access ) {
			$where[] = 't.access = '.(int) $filter_access;
		}

		// Filter by search word
		if ($search) {
			$escaped_search = $this->_db->escape( $search, true );
			$where[] = ' (LOWER(t.name) LIKE '.$this->_db->Quote( '%'.$escaped_search.'%', false )
				.' OR LOWER(t.label) LIKE '.$this->_db->Quote( '%'.$escaped_search.'%', false ) .')';
		}

		$where[] = ' (plg.extension_id IS NULL OR plg.folder="flexicontent_fields") ';

		$where = ( count( $where ) ? implode( ' AND ', $where ) : '' );

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
				$having = ' COUNT(rel.type_id) = 0';
			} else if ($filter_assigned == 'A' ) {
				$having = ' COUNT(rel.type_id) > 0';
			}
		}
		
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

			// Force dirty properties, NOTE: for this reason only fields changing publication state are updated
			if ($publish) {
				$set_search_properties =
						', issearch = CASE issearch WHEN 0 THEN 0   WHEN 1 THEN 2   ELSE issearch   END'.
						', isadvsearch = CASE isadvsearch WHEN 0 THEN 0   WHEN 1 THEN 2   ELSE isadvsearch   END'.
						', isadvfilter = CASE isadvfilter WHEN 0 THEN 0   WHEN 1 THEN 2   ELSE isadvfilter   END';
			} else {
				$set_search_properties =
						', issearch = CASE issearch WHEN 0 THEN 0   ELSE -1   END'.
						', isadvsearch = CASE isadvsearch WHEN 0 THEN 0   ELSE -1   END'.
						', isadvfilter = CASE isadvfilter WHEN 0 THEN 0   ELSE -1   END';
			}
			$query = 'UPDATE #__' . $this->records_dbtbl
				. ' SET published = ' . (int) $publish
				. $set_search_properties
				. ' WHERE id IN ('. $cids .') '
				. '   AND published<>' . (int) $publish   // IMPORTANT only update fields changing publication state
				. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
				;
			$this->_db->setQuery( $query );
			$this->_db->execute();
		}
		return true;
	}
	
	
	/**
	 * Method to toggle the given property of given field
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function toggleprop($cid = array(), $propname=null, &$unsupported=0, &$locked=0)
	{
		if (!$propname) return false;
		$user = JFactory::getUser();

		$affected = 0;
		if (count( $cid ))
		{
			// Get fields information from DB
			$query = 'SELECT field_type, iscore, id'
					. ' FROM #__flexicontent_fields'
					. ' WHERE id IN ('. implode( ',', $cid ) .') '
					;
			$this->_db->setQuery($query);
			$rows = $this->_db->loadObjectList('id');
			
			// Calculate fields not supporting the property
			$support_ids = array();
			$supportprop_name = 'support'.str_replace('is','',$propname);
			foreach ($rows as $id => $row)
			{
				$ft_support = FlexicontentFields::getPropertySupport($row->field_type, $row->iscore);
				$supportprop = isset($ft_support->{$supportprop_name}) ? $ft_support->{$supportprop_name} : false;
				if ($supportprop) $support_ids[] = $id;
			}
			$unsupported = count($cid) - count($support_ids);
			
			// Check that at least one field that supports the property was found
			if ( !count($support_ids) ) return 0;
			
			// Some fields are marked as 'dirty'
			$dirty_properties = array('issearch', 'isadvsearch', 'isadvfilter');
			$set_clause = in_array($propname,$dirty_properties) ?
				' SET '. $propname .' = CASE '. $propname .'  WHEN 2 THEN -1   WHEN -1 THEN 2   WHEN 1 THEN -1   WHEN 0 THEN 2   END' :
				' SET '. $propname .' = 1-'. $propname;
			
			// Toggle the property for fields supporting the property
			$query = 'UPDATE #__' . $this->records_dbtbl
				. $set_clause
				. ' WHERE id IN ('. implode(",",$support_ids) .')'
				. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
				;
			$this->_db->setQuery( $query );
			if ( !( $result = $this->_db->execute() ) ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			// Get affected fields, non affected fields must have been locked by another user
			$affected = $this->_db->getAffectedRows();
			$locked = count($support_ids) - $affected;
		}
		return $affected;
	}


	/**
	 * Method to check if given records can not be deleted e.g. due to assignments or due to being a CORE record
	 *
	 * @access	public
	 * @return	boolean
	 * @since	1.5
	 */
	function candelete($cid, & $cid_noauth=array(), & $cid_core=array())
	{
		$cid_noauth = $cid_core = array();

		if (!count($cid))
		{
			return false;
		}

		// Find ACL disallowed
		$user = JFactory::getUser();
		foreach($cid as $i => $_id)
		{
			if (!$user->authorise('flexicontent.deletefield', 'com_flexicontent.field.' . $_id))
			{
				$cid_noauth[] = $_id;
			}
		}

		// Find being CORE non-deletable
		$cid_core = $this->filterByCoreTypes($cid);

		return !count($cid_noauth) && !count($cid_core);
	}


	/**
	 * Method to check if given records can not be unpublished e.g. due to assignments or due to being a CORE record
	 *
	 * @access	public
	 * @return	boolean
	 * @since	1.5
	 */
	function canunpublish($cid, & $cid_noauth=array(), & $cid_core=array())
	{
		$cid_noauth = $cid_core = array();

		if ( !count($cid) )
		{
			return false;
		}

		// Find ACL disallowed
		$user = JFactory::getUser();
		foreach($cid as $i => $_id)
		{
			if (!$user->authorise('flexicontent.publishfield', 'com_flexicontent.field.' . $_id))
			{
				$cid_noauth[] = $_id;
			}
		}
		
		// Find being CORE non-unpublishable
		foreach($cid as $i => $_id)
		{
			// The fields having ID 1 to 6, are needed for versioning, filtering and search indexing
			if ($_id < 7)
			{
				$cid_core[] = $_id;
			}
		}

		return !count($cid_noauth) && !count($cid_core);
	}


	/**
	 * Method to find which type are core
	 *
	 * @access	public
	 * @param   cid  array() with type ids
	 *
	 * @return	array()  of record ids having assignments
	 * @since	1.5
	 */
	function filterByCoreTypes($cid = array())
	{
		JArrayHelper::toInteger($cid);

		$query = 'SELECT id '
		. ' FROM #__flexicontent_fields'
		. ' WHERE id = '. (int) $cid[$i]
		. ' AND iscore = 1'
		;
		$this->_db->setQuery( $query );
		return $this->_db->loadColumn();
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

			// delete also field in fields_type relation
			$query = 'DELETE FROM #__flexicontent_fields_type_relations'
					. ' WHERE field_id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );

			if(!$this->_db->execute()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			// delete also field in fields_item relation
			$query = 'DELETE FROM #__flexicontent_fields_item_relations'
					. ' WHERE field_id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );

			if(!$this->_db->execute()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// delete also field in fields versions table
			$query = 'DELETE FROM #__flexicontent_items_versions'
					. ' WHERE field_id IN ('. $cids .')'
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
	 * Method to set the access level of the Types
	 *
	 * @access	public
	 * @param integer id of the category
	 * @param integer access level
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function saveaccess($id, $access)
	{
		$row = JTable::getInstance($this->records_jtable, $prefix='');
		
		$row->load( $id );
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
		return true;
	}

	/**
	 * Method to get the last id
	 *
	 * @access	protected
	 * @return	int
	 * @since	1.5
	 */
	protected function _getLastId()
	{
		$query  = 'SELECT MAX(id)'
				. ' FROM #__flexicontent_fields'
				;
		$this->_db->setQuery($query);
		$lastid = $this->_db->loadResult();
		
		return (int)$lastid;
	}

	/**
	 * Method to copy fields
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function copy($cid = array(), $copyvalues=false)
	{
		if ( !count( $cid ) ) return false;
		
		$ids_map = array();
		foreach ($cid as $id) {
			// only non core fields
			if ($id > 14) {
				$field = $this->getTable('flexicontent_fields', '');
				$field->load($id);
				if ( $copyvalues && in_array($field->field_type, array('image')) ) {
					$params = new JRegistry($field->attribs);
					if ($params->get('image_source')) {
						JFactory::getApplication()->enqueueMessage( 'You cannot copy image field -- "'.$field->name.'" -- together with its values, since this field has data in folders too' ,'error');
						continue;
					}
				}
				$field->id = 0;
				$field->name = 'field' . ($this->_getLastId() + 1);
				$field->label = $field->label . ' [copy]';
				$field->check();
				$field->store();
				$ids_map[$id] = $field->id;
			}				
		}
		
		if ( !count( $ids_map ) ) return false; 
		if ($copyvalues) $this->copyvalues( $ids_map );  // Also copy values
		return $ids_map;
	}
	
	
	/**
	 * Method to copy field values of duplicated (copied) fields
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function copyvalues($ids_map = array())
	{
		if ( !count( $ids_map ) ) return false;
		
		$db = JFactory::getDbo();
		foreach ($ids_map as $source_id => $target_id) {
			// Copy field - content type assignments
			$query = 'INSERT INTO #__flexicontent_fields_type_relations (field_id, type_id, ordering)'
				.' SELECT '.$target_id.', type_id, ordering FROM #__flexicontent_fields_type_relations as rel'
				.' WHERE rel.field_id='.$source_id;
			$db->setQuery($query);
			$db->execute();
			if ( $db->getErrorNum() ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// Copy field values assigned to items
			$query = 'INSERT INTO #__flexicontent_fields_item_relations (field_id, item_id, valueorder, suborder, value, value_integer, value_decimal, value_datetime)'
				.' SELECT '.$target_id.',item_id, valueorder, suborder, value, CAST(value AS SIGNED), CAST(value AS DECIMAL(65,15)), CAST(value AS DATETIME) FROM #__flexicontent_fields_item_relations as rel'
				.' WHERE rel.field_id='.$source_id;
			$db->setQuery($query);
			$db->execute();
			if ( $db->getErrorNum() ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}
		return true;
	}
	
	
	/**
	 * Method to get types list
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ( $type_ids=false, $check_perms = false, $published=false )
	{
		return flexicontent_html::getTypesList( $type_ids, $check_perms, $published);
	}
	
	
	/**
	 * Method to build the list for types filter
	 * 
	 * @return array
	 * @since 1.5
	 */
	function buildtypesselect($list, $name, $selected, $top, $class = 'class="inputbox"')
	{
		$typelist 	= array();
		
		if($top) {
			$typelist[] 	= JHtml::_( 'select.option', '0', JText::_( 'FLEXI_SELECT_TYPE' ) );
		}
		
		foreach ($list as $item) {
			$typelist[] = JHtml::_( 'select.option', $item->id, $item->name);
		}
		return JHtml::_('select.genericlist', $typelist, $name, $class, 'value', 'text', $selected );
	}

	/**
	 * Method to move a Field
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function move($direction)
	{
		$filter_type = $this->getState( 'filter_type' );

		if ($filter_type == '' || $filter_type == 0)
		{
			$row = JTable::getInstance('flexicontent_fields', '');

			if (!$row->load( $this->_id ) ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			if (!$row->move( $direction )) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			return true;
		}
		else
		{
			$query = 'SELECT field_id, ordering'
					.' FROM #__flexicontent_fields_type_relations'
					.' WHERE type_id = ' . $filter_type
					.' AND field_id = ' . $this->_id
					;
			$this->_db->setQuery( $query, 0, 1 );
			$origin = $this->_db->loadObject();

			$sql = 'SELECT field_id, ordering FROM #__flexicontent_fields_type_relations';

			if ($direction < 0)
			{
				$sql .= ' WHERE ordering < '.(int) $origin->ordering;
				$sql .= ' AND type_id = ' . $filter_type;
				$sql .= ' ORDER BY ordering DESC';
			}
			else if ($direction > 0)
			{
				$sql .= ' WHERE ordering > '.(int) $origin->ordering;
				$sql .= ' AND type_id = ' . $filter_type;
				$sql .= ' ORDER BY ordering';
			}
			else
			{
				$sql .= ' WHERE ordering = '.(int) $origin->ordering;
				$sql .= ' AND type_id = ' . $filter_type;
				$sql .= ' ORDER BY ordering';
			}

			$this->_db->setQuery( $sql, 0, 1 );

			$row = null;
			$row = $this->_db->loadObject();
			
			if (isset($row))
			{
				$query = 'UPDATE #__flexicontent_fields_type_relations'
				. ' SET ordering = '. (int) $row->ordering
				. ' WHERE field_id = '. (int) $origin->field_id
				. ' AND type_id = ' . $filter_type
				;
				$this->_db->setQuery( $query );
				$this->_db->execute();

				$query = 'UPDATE #__flexicontent_fields_type_relations'
				. ' SET ordering = '.(int) $origin->ordering
				. ' WHERE field_id = '. (int) $row->field_id
				. ' AND type_id = ' . $filter_type
				;
				$this->_db->setQuery( $query );
				$this->_db->execute();

				$origin->ordering = $row->ordering;
			}
			else
			{
				$query = 'UPDATE #__flexicontent_fields_type_relations'
				. ' SET ordering = '.(int) $origin->ordering
				. ' WHERE field_id = '. (int) $origin->field_id
				. ' AND type_id = ' . $filter_type
				;
				$this->_db->setQuery( $query );
				$this->_db->execute();
			}
		return true;
		}
	}
	
	
	/**
	 * Method to order Fields
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function saveorder($cid = array(), $order)
	{
		$filter_type = $this->getState( 'filter_type' );
		
		if ($filter_type == '' || $filter_type == 0)
		{

			$row = JTable::getInstance('flexicontent_fields', '');
		
			// update ordering values
			for( $i=0; $i < count($cid); $i++ )
			{
				$row->load( (int) $cid[$i] );
	
				if ($row->ordering != $order[$i])
				{
					$row->ordering = $order[$i];
					if (!$row->store()) {
						$this->setError($this->_db->getErrorMsg());
						return false;
					}
				}
			}

			$row->reorder();
			return true;

		}
		else
		{
			// Here goes the second method for saving order.
			// As there is a composite primary key in the relations table we aren't able to use the standard methods from JTable
		
			$query = 'SELECT field_id, ordering'
					.' FROM #__flexicontent_fields_type_relations'
					.' WHERE type_id = ' . $filter_type
					.' ORDER BY ordering'
					;
			$this->_db->setQuery($query);
			$rows = $this->_db->loadObjectList('field_id');

			for( $i=0; $i < count($cid); $i++ )
			{
				if ($rows[$cid[$i]]->ordering != $order[$i])
				{
					$rows[$cid[$i]]->ordering = $order[$i];
					
					$query = 'UPDATE #__flexicontent_fields_type_relations'
						. ' SET ordering=' . $order[$i]
						. ' WHERE type_id = ' . $filter_type
						. ' AND field_id = ' . $cid[$i]
						;
					$this->_db->setQuery($query);
					$this->_db->execute();
				}
			}

			// Specific reorder procedure because the relations table has a composite primary key 
			$query 	= 'SELECT field_id, ordering'
					. ' FROM #__flexicontent_fields_type_relations'
					. ' WHERE ordering >= 0'
					. ' AND type_id = '.(int) $filter_type
					. ' ORDER BY ordering'
					;
			$this->_db->setQuery( $query );
			if (!($orders = $this->_db->loadObjectList()))
			{
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			// compact the ordering numbers
			for ($i=0, $n=count( $orders ); $i < $n; $i++)
			{
				if ($orders[$i]->ordering >= 0)
				{
					if ($orders[$i]->ordering != $i+1)
					{
						$orders[$i]->ordering = $i+1;
						$query 	= 'UPDATE #__flexicontent_fields_type_relations'
								. ' SET ordering = '. (int) $orders[$i]->ordering
								. ' WHERE field_id = '. (int) $orders[$i]->field_id
								. ' AND type_id = '.(int) $filter_type
								;
						$this->_db->setQuery( $query);
						$this->_db->execute();
					}
				}
			}

			return true;
		}

	}
}
?>
