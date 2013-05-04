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
 * FLEXIcontent Component fields Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelFields extends JModelLegacy
{
	/**
	 * Field data
	 *
	 * @var object
	 */
	var $_data = null;

	/**
	 * Field total
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
	 * Field id
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

		$option = JRequest::getVar('option');
		$app = JFactory::getApplication();

		$limit		= $app->getUserStateFromRequest( $option.'.fields.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( $option.'.fields.limitstart', 'limitstart', 0, 'int' );

		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		$array = JRequest::getVar('cid',  0, '', 'array');
		$this->setId((int)$array[0]);
	}

	/**
	 * Method to set the Field identifier
	 *
	 * @access	public
	 * @param	int Field identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
	}

	/**
	 * Method to get fields data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the fields if it doesn't already exist
		if (empty($this->_data))
		{
			$db = JFactory::getDBO();
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
			$db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $db->loadResult();
		}

		return $this->_data;
	}

	/**
	 * Method to get the total nr of the fields
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the fields if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the fields
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the fields if it doesn't already exist
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Method to build the query for the fields
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	function _buildQuery()
	{
		static $query;
		
		if(!isset($query)) {
			
			// Get the WHERE, HAVING and ORDER BY clauses for the query
			$where		= $this->_buildContentWhere();
			$orderby	= $this->_buildContentOrderBy();
			$having		= $this->_buildContentHaving();
	
			$query = 'SELECT SQL_CALC_FOUND_ROWS t.*, u.name AS editor, COUNT(rel.type_id) AS nrassigned, g.name AS groupname, rel.ordering as typeordering, t.field_type as type, plg.name as field_friendlyname'
				. ' FROM #__flexicontent_fields AS t'
				. " LEFT JOIN #__plugins AS plg ON (plg.element = t.field_type AND plg.folder='flexicontent_fields')"
				. ' LEFT JOIN #__flexicontent_fields_type_relations AS rel ON rel.field_id = t.id'
				. ' LEFT JOIN #__groups AS g ON g.id = t.access'
				. ' LEFT JOIN #__users AS u ON u.id = t.checked_out'
				. $where
				. ' GROUP BY t.id'
				. $having
				. $orderby
				;
		}//end if(!isset($query))
		
		return $query;
	}

	/**
	 * Method to build the orderby clause of the query for the fields
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentOrderBy()
	{
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');

		$filter_type 		= $app->getUserStateFromRequest( $option.'.fields.filter_type', 'filter_type', '', 'int' );
		$filter_order		= $app->getUserStateFromRequest( $option.'.fields.filter_order', 		'filter_order', 	't.ordering', 'cmd' );
		if ($filter_type && $filter_order == 't.ordering') {
			$filter_order	= $app->setUserState( $option.'.fields.filter_order', 'typeordering' );
		} else if (!$filter_type && $filter_order == 'typeordering') {
			$filter_order	= $app->setUserState( $option.'.fields.filter_order', 't.ordering' );
		}
		$filter_order_Dir	= $app->getUserStateFromRequest( $option.'.fields.filter_order_Dir',	'filter_order_Dir',	'ASC', 'word' );

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir;

		return $orderby;
	}

	/**
	 * Method to build the where clause of the query for the fields
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentWhere()
	{
		static $where;
		if(!isset($where)) {
			$option = JRequest::getVar('option');
			$app = JFactory::getApplication();
	
			$filter_state     = $app->getUserStateFromRequest( $option.'.fields.filter_state', 'filter_state', '', 'word' );
			$filter_type      = $app->getUserStateFromRequest( $option.'.fields.filter_type', 'filter_type', '', 'int' );
			$filter_fieldtype = $app->getUserStateFromRequest( $option.'.fields.filter_fieldtype', 'filter_fieldtype', '', 'word' );
			$search = $app->getUserStateFromRequest( $option.'.fields.search', 'search', '', 'string' );
			$search = trim( JString::strtolower( $search ) );
	
			$where = array();
	
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
	
			if ( $filter_state ) {
				if ( $filter_state == 'P' ) {
					$where[] = 't.published = 1';
				} else if ($filter_state == 'U' ) {
					$where[] = 't.published = 0';
				}
			}
	
			if ( $filter_type ) {
				$where[] = 'rel.type_id = ' . $filter_type;
				}
	
			if ($search) {
				$where[] = ' LOWER(t.name) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $search, true ).'%', false );
			}
			
			$where[] = ' (plg.id IS NULL OR plg.folder="flexicontent_fields" ) ';
			
			$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );
		}//end if(!isset($where))
		
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
		
		$filter_assigned	= $app->getUserStateFromRequest( $option.'.fields.filter_assigned', 'filter_assigned', '', 'word' );
		
		$having = '';
		
		if ( $filter_assigned ) {
			if ( $filter_assigned == 'O' ) {
				$having = ' HAVING COUNT(rel.type_id) = 0';
			} else if ($filter_assigned == 'A' ) {
				$having = ' HAVING COUNT(rel.type_id) > 0';
			}
		}
		
		return $having;
	}

	/**
	 * Method to (un)publish a field
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
			$query = 'UPDATE #__flexicontent_fields'
				. ' SET published = ' . (int) $publish
				. $set_search_properties
				. ' WHERE id IN ('. $cids .') '
				. '   AND published<>' . (int) $publish   // IMPORTANT only update fields changing publication state
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
			$query = 'UPDATE #__flexicontent_fields'
				. $set_clause
				. ' WHERE id IN ('. implode(",",$support_ids) .')'
				. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
				;
			$this->_db->setQuery( $query );
			if ( !( $result = $this->_db->query() ) ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// Get affected fields, non affected fields must have been locked by another user
			$affected = $this->_db->getAffectedRows($result);
			$locked = count($support_ids) - $affected;
		}
		return $affected;
	}
		
	
	/**
	 * Method to check if we can remove a field
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
				$query = 'SELECT COUNT( id )'
				. ' FROM #__flexicontent_fields'
				. ' WHERE id = '. (int) $cid[$i]
				. ' AND iscore = 1'
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
	 * Method to check if we can unpublish a field
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function canunpublish($cid = array())
	{
		$n		= count( $cid );
		if (count( $cid ))
		{
			for ($i = 0; $i < $n; $i++)
			{
				// the six first fields are needed for versioning, filtering and advanced search
				if ($cid[$i] < 7) {
					return false;
				}
			}
			return true;
		}
	}

	/**
	 * Method to remove a field
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
			$query = 'DELETE FROM #__flexicontent_fields'
					. ' WHERE id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			// delete also field in fields_type relation
			$query = 'DELETE FROM #__flexicontent_fields_type_relations'
					. ' WHERE field_id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			// delete also field in fields_item relation
			$query = 'DELETE FROM #__flexicontent_fields_item_relations'
					. ' WHERE field_id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// delete also field in fields versions table
			$query = 'DELETE FROM #__flexicontent_items_versions'
					. ' WHERE field_id IN ('. $cids .')'
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
		$option = JRequest::getVar('option');
		$row = JTable::getInstance('flexicontent_fields', '');

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
	 * @access	private
	 * @return	int
	 * @since	1.5
	 */
	function _getLastId()
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
					$params = FLEXI_J16GE ? new JRegistry($field->attribs) : new JParameter($field->attribs);
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
		
		$db = JFactory::getDBO();
		foreach ($ids_map as $source_id => $target_id) {
			// Copy field - content type assignments
			$query = 'INSERT INTO #__flexicontent_fields_type_relations (field_id, type_id, ordering)'
				.' SELECT '.$target_id.', type_id, ordering FROM #__flexicontent_fields_type_relations as rel'
				.' WHERE rel.field_id='.$source_id;
			$db->setQuery($query);
			$db->query();
			if ( $db->getErrorNum() ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// Copy field values assigned to items
			$query = 'INSERT INTO #__flexicontent_fields_item_relations (field_id, item_id, valueorder, value)'
				.' SELECT '.$target_id.',item_id, valueorder, value FROM #__flexicontent_fields_item_relations as rel'
				.' WHERE rel.field_id='.$source_id;
			$db->setQuery($query);
			$db->query();
			if ( $db->getErrorNum() ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}
		return true;
	}
	
	
	/**
	 * Method to get types list when performing an edit action
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ()
	{
		$query = 'SELECT id, name'
				. ' FROM #__flexicontent_types'
				. ' WHERE published = 1'
				. ' ORDER BY name ASC'
				;
		$this->_db->setQuery($query);
		$types = $this->_db->loadObjectList();
		return $types;	
	}
	
	
	/**
	 * Method to get list of field types used
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getFieldTypes ()
	{
		$query = 'SELECT field_type, count(id) as assigned'
				. ' FROM #__flexicontent_fields'
				. ' WHERE iscore=0 '
				. ' GROUP BY field_type'
				;
		$this->_db->setQuery($query);
		$fieldtypes = $this->_db->loadObjectList('field_type');
		return $fieldtypes;
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
			$typelist[] 	= JHTML::_( 'select.option', '0', JText::_( 'FLEXI_SELECT_TYPE' ) );
		}
		
		foreach ($list as $item) {
			$typelist[] = JHTML::_( 'select.option', $item->id, $item->name);
		}
		return JHTML::_('select.genericlist', $typelist, $name, $class, 'value', 'text', $selected );
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
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$filter_type = $app->getUserStateFromRequest( $option.'.fields.filter_type', 'filter_type', '', 'int' );

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

				if (!$this->_db->query())
				{
					$msg = $this->_db->getErrorMsg();
					$this->setError( $msg );
					return false;
				}

				$query = 'UPDATE #__flexicontent_fields_type_relations'
				. ' SET ordering = '.(int) $origin->ordering
				. ' WHERE field_id = '. (int) $row->field_id
				. ' AND type_id = ' . $filter_type
				;
				$this->_db->setQuery( $query );
	
				if (!$this->_db->query())
				{
					$msg = $this->_db->getErrorMsg();
					$this->setError( $msg );
					return false;
				}

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
	
				if (!$this->_db->query())
				{
					$msg = $this->_db->getErrorMsg();
					$this->setError( $msg );
					return false;
				}
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
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$filter_type = $app->getUserStateFromRequest( $option.'.fields.filter_type', 'filter_type', '', 'int' );

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

			$row->reorder( );
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
			// on utilise la methode _getList pour s'assurer de ne charger que les résultats compris entre les limites
			//$rows = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			$this->_db->setQuery($query);
			$rows = $this->_db->loadObjectList('field_id');

			for( $i=0; $i < count($cid); $i++ )
			{
				if ($rows[$cid[$i]]->ordering != $order[$i])
				{
					$rows[$cid[$i]]->ordering = $order[$i];
					
					$query = 'UPDATE #__flexicontent_fields_type_relations'
							.' SET ordering=' . $order[$i]
							.' WHERE type_id = ' . $filter_type
							.' AND field_id = ' . $cid[$i]
							;

					$this->_db->setQuery($query);

					if (!$this->_db->query()) {
						$this->setError($this->_db->getErrorMsg());
						return false;
					}
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
						$this->_db->query();
					}
				}
			}

			return true;
		}

	}
	
}
?>
