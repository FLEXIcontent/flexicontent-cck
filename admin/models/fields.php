<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            http://www.flexicontent.com
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;

require_once('base/baselist.php');

/**
 * FLEXIcontent Component Fields Model
 *
 */
class FlexicontentModelFields extends FCModelAdminList
{

	var $records_dbtbl  = 'flexicontent_fields';
	var $records_jtable = 'flexicontent_fields';

	/**
	 * Column names and record name
	 */
	var $record_name = 'field';
	var $state_col   = 'published';
	var $name_col    = 'name';
	var $parent_col  = null;

	/**
	 * (Default) Behaviour Flags
	 */
	var $listViaAccess = false;
	var $copyRelations = false;

	/**
	 * Search and ordering columns
	 */
	var $search_cols       = array('name', 'label');
	var $default_order     = 'a.ordering';
	var $default_order_dir = 'ASC';

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
	 * @since 3.3.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$fcform = $jinput->get('fcform', 0, 'int');
		$p      = $option . '.' . $view . '.';


		/**
		 * View's Filters
		 * Inherited filters : filter_state, filter_access, search
		 */

		// Various filters
		$filter_fieldtype = $fcform ? $jinput->get('filter_fieldtype', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_fieldtype', 'filter_fieldtype', '', 'cmd');
		$filter_type      = $fcform ? $jinput->get('filter_type', 0, 'int') : $app->getUserStateFromRequest($p . 'filter_type', 'filter_type', 0, 'int');
		$filter_assigned  = $fcform ? $jinput->get('filter_assigned', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_assigned', 'filter_assigned', '', 'cmd');

		$this->setState('filter_fieldtype', $filter_fieldtype);
		$this->setState('filter_type', $filter_type);
		$this->setState('filter_assigned', $filter_assigned);

		$app->setUserState($p . 'filter_fieldtype', $filter_fieldtype);
		$app->setUserState($p . 'filter_type', $filter_type);
		$app->setUserState($p . 'filter_assigned', $filter_assigned);


		// Manage view permission
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanFields;
	}


	/**
	 * Method to get records data
	 *
	 * @return array
	 *
	 * @since	3.3.0
	 */
	public function getItems()
	{
		// Lets load the records if it doesn't already exist
		if (empty($this->_data))
		{
			parent::getItems();

			/**
			 * Get type data
			 */

			$this->_typeids = array();

			foreach ($this->_data as $item)
			{
				$item->content_types = $item->typeids ? preg_split("/[\s]*,[\s]*/", $item->typeids) : array();

				foreach ($item->content_types as $type_id)
				{
					if ($type_id)
					{
						$this->_typeids[$type_id] = 1;
					}
				}
			}

			$this->_typeids = array_keys($this->_typeids);
		}

		return $this->_data;
	}


	/**
	 * Method to build the query for the records
	 *
	 * @return JDatabaseQuery   The DB Query object
	 *
	 * @since 3.3.0
	 */
	protected function getListQuery()
	{
		// Create a query with all its clauses: WHERE, HAVING and ORDER BY, etc
		$query = parent::getListQuery()
			->select(
				'COUNT(rel.type_id) AS nrassigned, GROUP_CONCAT(rel.type_id SEPARATOR  ",") AS typeids, ' .
				'rel.ordering as typeordering, a.field_type as type, plg.name as friendly'
			)
			->leftJoin('#__extensions AS plg ON (plg.element = a.field_type AND plg.`type`=\'plugin\' AND plg.folder=\'flexicontent_fields\')')
			->leftJoin('#__flexicontent_fields_type_relations AS rel ON rel.field_id = a.id')
		;

		return $query;
	}


	/**
	 * Method to build the where clause of the query for the records
	 *
	 * @param		JDatabaseQuery|bool   $q   DB Query object or bool to indicate returning an array or rendering the clause
	 *
	 * @return  JDatabaseQuery|array
	 *
	 * @since 1.0
	 */
	protected function _buildContentWhere($q = false)
	{
		// Inherited filters : filter_state, filter_access, search
		$where = parent::_buildContentWhere(false);

		// Various filters
		$filter_fieldtype = $this->getState('filter_fieldtype');
		$filter_type      = $this->getState('filter_type');

		// Filter by item-type (assigned-to)
		switch ($filter_fieldtype)
		{
			case 'C':
				$where[] = 'a.iscore = 1';
				break;
			case 'NC':
				$where[] = 'a.iscore = 0';
				break;
			case 'BV':
				$where[] = '(a.iscore = 0 OR a.id = 1)';
				break;
			default:
				if ($filter_fieldtype)
				{
					$where[] = 'a.field_type = ' . $this->_db->Quote($filter_fieldtype);
				}
				break;
		}

		// Filter by field-type
		if ($filter_type)
		{
			$where[] = 'rel.type_id = ' . (int) $filter_type;
		}

		// Limit to plugin type 'flexicontent_fields'
		$where[] = '(plg.extension_id IS NULL OR plg.folder = ' . $this->_db->quote('flexicontent_fields') . ')';

		if ($q instanceof \JDatabaseQuery)
		{
			return $where ? $q->where($where) : $q;
		}

		return $q
			? ' WHERE ' . (count($where) ? implode(' AND ', $where) : ' 1 ')
			: $where;
	}


	/**
	 * Method to build the having clause of the query for the files
	 *
	 * @param		JDatabaseQuery|bool   $q   DB Query object or bool to indicate returning an array or rendering the clause
	 *
	 * @return  JDatabaseQuery|array
	 *
	 * @since 1.0
	 */
	protected function _buildContentHaving($q = false)
	{
		$having = parent::_buildContentHaving(false);

		$filter_assigned = $this->getState('filter_assigned');
		$rel_col         = 'rel.type_id';

		switch ($filter_assigned)
		{
			case 'O':
				$having[] = 'COUNT(' . $rel_col . ') = 0';
				break;

			case 'A':
				$having[] = 'COUNT(' . $rel_col . ') > 0';
				break;
		}

		if ($q instanceof \JDatabaseQuery)
		{
			return $having ? $q->having($having) : $q;
		}

		return $q
			? ' HAVING ' . (count($having) ? implode(' AND ', $having) : ' 1 ')
			: $having;
	}


	/**
	 * Method to get SET-clause to set new values to columns related to the changing state of the records
	 *
	 * @param		array			$cid          Array of record ids to set to a new state
	 * @param		integer   $state        The new state
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.3.0
	 */
	protected function getExtraStateChangeProps($state)
	{
		$set_properties = array();

		// Force dirty properties, NOTE: for this reason only fields changing publication state are updated
		if ($state)
		{
			$set_properties = array(
				'issearch = CASE issearch WHEN 0 THEN 0   WHEN 1 THEN 2   ELSE issearch   END',
				'isadvsearch = CASE isadvsearch WHEN 0 THEN 0   WHEN 1 THEN 2   ELSE isadvsearch   END',
				'isadvfilter = CASE isadvfilter WHEN 0 THEN 0   WHEN 1 THEN 2   ELSE isadvfilter   END',
			);
		}
		else
		{
			$set_properties = array(
				'issearch = CASE issearch WHEN 0 THEN 0   ELSE -1   END',
				'isadvsearch = CASE isadvsearch WHEN 0 THEN 0   ELSE -1   END',
				'isadvfilter = CASE isadvfilter WHEN 0 THEN 0   ELSE -1   END',
			);
		}

		return $set_properties;
	}


	/**
	 * Method to move a record upwards or downwards
	 *
	 * @param  integer    $direction   A value of 1  or -1 to indicate moving up or down respectively
	 *
	 * @return	boolean	  True on success
	 *
	 * @since	3.3.0
	 */
	public function move($direction)
	{
		$filter_type = $this->getState('filter_type');

		if (!$filter_type)
		{
			return parent::move($direction);
		}

		else
		{
			$query = 'SELECT field_id, ordering'
				. ' FROM #__flexicontent_fields_type_relations'
				. ' WHERE type_id = ' . $filter_type
				. ' AND field_id = ' . $this->_id
			;
			$origin = $this->_db->setQuery($query, 0, 1)->loadObject();

			$sql = 'SELECT field_id, ordering FROM #__flexicontent_fields_type_relations';

			if ($direction < 0)
			{
				$sql .= ' WHERE ordering < ' . (int) $origin->ordering;
				$sql .= ' AND type_id = ' . $filter_type;
				$sql .= ' ORDER BY ordering DESC';
			}
			elseif ($direction > 0)
			{
				$sql .= ' WHERE ordering > ' . (int) $origin->ordering;
				$sql .= ' AND type_id = ' . $filter_type;
				$sql .= ' ORDER BY ordering';
			}
			else
			{
				$sql .= ' WHERE ordering = ' . (int) $origin->ordering;
				$sql .= ' AND type_id = ' . $filter_type;
				$sql .= ' ORDER BY ordering';
			}

			$row = $this->_db->setQuery($sql, 0, 1)->loadObject();

			if (isset($row))
			{
				$query = 'UPDATE #__flexicontent_fields_type_relations'
					. ' SET ordering = ' . (int) $row->ordering
					. ' WHERE field_id = ' . (int) $origin->field_id
					. ' AND type_id = ' . $filter_type
				;
				$this->_db->setQuery($query)->execute();

				$query = 'UPDATE #__flexicontent_fields_type_relations'
					. ' SET ordering = ' . (int) $origin->ordering
					. ' WHERE field_id = ' . (int) $row->field_id
					. ' AND type_id = ' . $filter_type
				;
				$this->_db->setQuery($query)->execute();

				$origin->ordering = $row->ordering;
			}
			else
			{
				$query = 'UPDATE #__flexicontent_fields_type_relations'
					. ' SET ordering = ' . (int) $origin->ordering
					. ' WHERE field_id = ' . (int) $origin->field_id
					. ' AND type_id = ' . $filter_type
				;
				$this->_db->setQuery($query)->execute();
			}

			return true;
		}
	}


	/**
	 * Method to delete related data of records
	 *
	 * @param		array			$cid          array of record ids to delete their related Data
	 *
	 * @return	void
	 *
	 * @since		3.3.0
	 */
	protected function _deleteRelatedData($cid)
	{
		if (count($cid))
		{
			// Delete also field - type relations
			$query = $this->_db->getQuery(true)
				->delete('#__flexicontent_fields_type_relations')
				->where('field_id IN (' . $cid_list . ')');
			$this->_db->setQuery($query)->execute();

			// Delete also field values
			$query = $this->_db->getQuery(true)
				->delete('#__flexicontent_fields_item_relations')
				->where('field_id IN (' . $cid_list . ')');
			$this->_db->setQuery($query)->execute();

			// Delete also versioned field values
			$query = $this->_db->getQuery(true)
				->delete('#__flexicontent_items_versions')
				->where('field_id IN (' . $cid_list . ')');
			$this->_db->setQuery($query)->execute();
		}
	}


	/**
	 * Method to copy records
	 *
	 * @param		array			$cid          array of record ids to copy
	 * @param		array			$copyRelations   flag to indicate copying 'related' data, like 'assignments'
	 *
	 * @return	array		Array of old-to new record ids of copied record IDs
	 *
	 * @since		1.0
	 */
	public function copy($cid, $copyRelations = null)
	{
		$copyRelations = copyValues === null ? $this->copyValues : $copyRelations;
		$ids_map       = array();
		$name          = $this->name_col;

		foreach ($cid as $id)
		{
			// Do not copy CORE fields
			if ($id <= 14)
			{
				continue;
			}

			$table = $this->getTable($this->records_jtable, '');
			$table->load($id);

			if ($copyRelations && in_array($table->field_type, array('image')))
			{
				$params = new JRegistry($table->attribs);

				if ($params->get('image_source'))
				{
					JFactory::getApplication()->enqueueMessage('You cannot copy image field -- "' . $table->name . '" -- together with its values, since this field has data in folders too', 'error');
					continue;
				}
			}

			$table->id    = 0;
			$table->$name = $this->record_name . ($this->_getLastId() + 1);
			$table->label = $table->label . ' [copy]';
			$table->check();
			$table->store();

			// Add new record id to the old-to-new IDs map
			$ids_map[$id] = $table->id;
		}

		// Also copy related Data, like 'assignments'
		if ($copyRelations)
		{
			$this->_copyRelatedData($ids_map);
		}

		return $ids_map;
	}


	/**
	 * Method to copy assignments and other related data of records
	 *
	 * @param   array     $ids_map     array of old to new record ids
	 *
	 * @return	void
	 *
	 * @since		3.3.0
	 */
	protected function _copyRelatedData($ids_map)
	{
		foreach ($ids_map as $source_id => $target_id)
		{
			// Copy field - content type assignments
			$query = 'INSERT INTO #__flexicontent_fields_type_relations (field_id, type_id, ordering)'
				. ' SELECT ' . $target_id . ', type_id, ordering FROM #__flexicontent_fields_type_relations as rel'
				. ' WHERE rel.field_id=' . $source_id;
			$this->_db->setQuery($query)->execute();

			// Copy field values assigned to items
			$query = 'INSERT INTO #__flexicontent_fields_item_relations (field_id, item_id, valueorder, suborder, value, value_integer, value_decimal, value_datetime)'
				. ' SELECT ' . $target_id . ',item_id, valueorder, suborder, value, CAST(value AS SIGNED), CAST(value AS DECIMAL(65,15)), CAST(value AS DATETIME) FROM #__flexicontent_fields_item_relations as rel'
				. ' WHERE rel.field_id=' . $source_id;
			$this->_db->setQuery($query)->execute();
		}
	}


	/**
	 * Method to find which records are not authorized
	 *
	 * @param   array     $cid     array of record ids to check
	 * @param   string    $rule    string of the ACL rule to check
	 *
	 * @return	array     The records having assignments
	 *
	 * @since	3.3.0
	 */
	public function filterByPermission($cid, $rule)
	{
		ArrayHelper::toInteger($cid);

		// If cannot manage then all records are not changeable
		if (!$this->canManage)
		{
			return $cid;
		}

		$mapped_rule = $rule === 'core.delete' ? 'flexicontent.deletefield' : $rule;
		$mapped_rule = $rule === 'core.edit.state' ? 'flexicontent.publishfield' : $rule;
		$user        = JFactory::getUser();
		$cid_noauth  = array();

		foreach ($cid as $i => $id)
		{
			if (!$user->authorise($mapped_rule, 'com_flexicontent.field.' . $id))
			{
				$cid_noauth[] = $id;
			}
			else
			{
				$this->changeable_rows[$rule][$id] = 1;
			}
		}

		return $cid_noauth;
	}


	/**
	 * Method to find which records having assignments blocking a state change
	 *
	 * @param		array     $cid      array of record ids to check
	 * @param		string    $tostate  action related to assignments
	 *
	 * @return	array     The records having assignments
	 */
	public function filterByAssignments($cid = array(), $tostate = -2)
	{
		ArrayHelper::toInteger($cid);
		$cid_wassocs = array();

		switch ($tostate)
		{
			// Trash
			case -2:
				$cid_wassocs = $this->filterByCoreTypes($cid);
				break;


			// Unpublish
			case 0:
				// Find being CORE non-unpublishable
				foreach ($cid as $i => $id)
				{
					// The fields having ID 1 to 6, are needed for versioning, filtering and search indexing
					if ($id < 7)
					{
						$cid_wassocs[] = $id;
					}
				}
				break;
		}

		return $cid_wassocs;
	}


	/**
	 * Method to set order into state
	 *
	 * @return	void
	 *
	 * @since 3.3.0
	 */
	protected function _setStateOrder()
	{
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$fcform = $jinput->get('fcform', 0, 'int');
		$p      = $option . '.' . $view . '.';

		$filter_type = $this->getState('filter_type');

		$default_order     = $this->default_order;
		$default_order_dir = $this->default_order_dir;

		$filter_order     = $fcform ? $jinput->get('filter_order', $default_order, 'cmd') : $app->getUserStateFromRequest($p . 'filter_order', 'filter_order', $default_order, 'cmd');
		$filter_order_Dir = $fcform ? $jinput->get('filter_order_Dir', $default_order_dir, 'word') : $app->getUserStateFromRequest($p . 'filter_order_Dir', 'filter_order_Dir', $default_order_dir, 'word');

		if (!$filter_order)
		{
			$filter_order = $default_order;
		}

		if (!$filter_order_Dir)
		{
			$filter_order_Dir = $default_order_dir;
		}

		if ($filter_type && $filter_order == 'a.ordering')
		{
			$filter_order = 'typeordering';
		}
		else if (!$filter_type && $filter_order == 'typeordering')
		{
			$filter_order = 'a.ordering';
		}

		$this->setState('filter_order', $filter_order);
		$this->setState('filter_order_Dir', $filter_order_Dir);

		$app->setUserState($p . 'filter_order', $filter_order);
		$app->setUserState($p . 'filter_order_Dir', $filter_order_Dir);
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */


	/**
	 * Method (LEGACY) to copy field values of duplicated (copied) fields
	 *
	 * @return	void
	 *
	 * @since	3.3.0
	 */
	protected function copyvalues($ids_map)
	{
		$this->_copyRelatedData($ids_map);
	}


	/**
	 * Method to get types list
	 *
	 * @return array
	 *
	 * @since 1.5
	 */
	function getTypeslist($type_ids = false, $check_perms = false, $published = false)
	{
		return flexicontent_html::getTypesList($type_ids, $check_perms, $published);
	}


	/**
	 * Method to build the list for types filter
	 *
	 * @return array
	 *
	 * @since 1.5
	 */
	function buildtypesselect($list, $name, $selected, $top, $class = 'class="inputbox"')
	{
		$typelist = array();

		if ($top)
		{
			$typelist[] = JHtml::_('select.option', '0', JText::_('FLEXI_SELECT_TYPE'));
		}

		foreach ($list as $item)
		{
			$typelist[] = JHtml::_('select.option', $item->id, $item->name);
		}

		return JHtml::_('select.genericlist', $typelist, $name, $class, 'value', 'text', $selected);
	}


	/**
	 * Method to find which type are core
	 *
	 * @param   array     $cid     array of field type ids to check
	 *
	 * @return	array()  of record ids having assignments
	 *
	 * @since	1.5
	 */
	function filterByCoreTypes($cid = array())
	{
		ArrayHelper::toInteger($cid);

		$query = 'SELECT id '
			. ' FROM #__flexicontent_fields'
			. ' WHERE id IN (' . implode(',', $cid) . ') '
			. ' AND iscore = 1'
		;

		return $this->_db->setQuery($query)->loadColumn();
	}


	/**
	 * Saves the manually set order of records.
	 *
	 * @param   array    $pks    An array of primary key ids.
	 * @param   integer  $order  An array of new ordering values
	 *
	 * @return  boolean|\JException  Boolean true on success, false on failure, or \JException if no items are selected
	 *
	 * @since   1.6
	 */
	public function saveorder($cid = array(), $order = array())
	{
		$filter_type = $this->getState('filter_type');

		if (!$filter_type)
		{
			// Access check should be done at the controller code, but parent::saveorder includes this again ...
			return parent::saveorder($cid, $order);
		}

		/**
		 * Ordering of field inside a specific content type 
		 * As there is a composite primary key in the relations table we aren't able to use the standard methods from JTable
		 */
		$query = 'SELECT field_id, ordering'
			. ' FROM #__flexicontent_fields_type_relations'
			. ' WHERE type_id = ' . $filter_type
			. ' ORDER BY ordering'
		;
		$rows = $this->_db->setQuery($query)->loadObjectList('field_id');

		for ($i = 0; $i < count($cid); $i++)
		{
			if ($rows[$cid[$i]]->ordering != $order[$i])
			{
				$rows[$cid[$i]]->ordering = $order[$i];

				$query = 'UPDATE #__flexicontent_fields_type_relations'
					. ' SET ordering=' . $order[$i]
					. ' WHERE type_id = ' . $filter_type
					. ' AND field_id = ' . $cid[$i]
				;
				$this->_db->setQuery($query)->execute();
			}
		}

		// Specific reorder procedure because the relations table has a composite primary key
		$query = 'SELECT field_id, ordering'
			. ' FROM #__flexicontent_fields_type_relations'
			. ' WHERE ordering >= 0'
			. ' AND type_id = ' . (int) $filter_type
			. ' ORDER BY ordering'
		;
		$orders = $this->_db->setQuery($query)->loadObjectList();

		// compact the ordering numbers
		for ($i = 0, $n = count($orders); $i < $n; $i++)
		{
			if ($orders[$i]->ordering >= 0)
			{
				if ($orders[$i]->ordering != $i + 1)
				{
					$orders[$i]->ordering = $i + 1;
					$query = 'UPDATE #__flexicontent_fields_type_relations'
						. ' SET ordering = ' . (int) $orders[$i]->ordering
						. ' WHERE field_id = ' . (int) $orders[$i]->field_id
						. ' AND type_id = ' . (int) $filter_type
					;
					$this->_db->setQuery($query)->execute();
				}
			}
		}

		return true;
	}


	/**
	 * Method to toggle the given -SEARCH- property of given field
	 *
	 * @param		array			$cid          Array of record ids to set to a new state
	 * @param		string    $propname     The property name
	 * @param		array     $propname     Return an array of field ids that do no support this propery
	 * @param		array     $locked       Return an array of field ids that have this propery locked to specific value
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.3.0
	 */
	public function toggleprop($cid = array(), $propname = null, &$unsupported = 0, &$locked = 0)
	{
		$user = JFactory::getUser();

		$affected = 0;

		if (count($cid))
		{
			// Get fields information from DB
			$query = 'SELECT field_type, iscore, id'
				. ' FROM #__flexicontent_fields'
				. ' WHERE id IN (' . implode(',', $cid) . ') '
			;
			$this->_db->setQuery($query);
			$rows = $this->_db->loadObjectList('id');

			// Calculate fields not supporting the property
			$support_ids      = array();
			$supportprop_name = 'support' . str_replace('is', '', $propname);
			foreach ($rows as $id => $row)
			{
				$ft_support  = FlexicontentFields::getPropertySupport($row->field_type, $row->iscore);
				$supportprop = isset($ft_support->{$supportprop_name}) ? $ft_support->{$supportprop_name} : false;

				if ($supportprop)
				{
					$support_ids[] = $id;
				}
			}
			$unsupported = count($cid) - count($support_ids);

			// Check that at least one field that supports the property was found
			if (!count($support_ids))
			{
				return 0;
			}

			// Some fields are marked as 'dirty'
			$dirty_properties = array('issearch', 'isadvsearch', 'isadvfilter');
			$set_clause = in_array($propname, $dirty_properties) ?
				' SET ' . $propname . ' = CASE ' . $propname . '  WHEN 2 THEN -1   WHEN -1 THEN 2   WHEN 1 THEN -1   WHEN 0 THEN 2   END' :
				' SET ' . $propname . ' = 1-' . $propname;

			// Toggle the property for fields supporting the property
			$query = 'UPDATE #__' . $this->records_dbtbl
				. $set_clause
				. ' WHERE id IN (' . implode(",", $support_ids) . ')'
				. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id') . ' ) )'
			;
			$this->_db->setQuery($query)->execute();

			// Get affected records, non records may have been locked by another user
			$affected = $this->_db->getAffectedRows();

			// Get locked records, by subtracting effected from initial count
			$locked = count($support_ids) - $affected;
		}

		return $affected;
	}


	/**
	 * START OF MODEL LEGACY METHODS
	 */

}
