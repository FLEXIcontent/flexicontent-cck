<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;

jimport('legacy.model.list');
require_once('traitbase.php');
require_once('traitlegacylist.php');

/**
 * FLEXIcontent Component BASE (list) Model
 *
 */
abstract class FCModelAdminList extends JModelList
{
	use FCModelTraitBase;
	use FCModelTraitLegacyList;

	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl  = 'flexicontent_records';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_records';

	/**
	 * Column names
	 */
	var $state_col      = 'published';
	var $name_col       = 'title';
	var $parent_col     = null;
	var $created_by_col = 'created_by';

	/**
	 * (Default) Behaviour Flags
	 */
	protected $listViaAccess = false;
	protected $copyRelations = true;


	/**
	 * Supported Features Flags
	 */
	const canDelRelated = false;

	/**
	 * Events
	 *
	 * @var string
	 */
	var $event_context = null;
	var $event_before_delete = null;
	var $event_after_delete = null;

	/**
	 * Search and ordering columns
	 */
	var $search_cols = array(
		'FLEXI_TITLE' => 'title',
		'FLEXI_ALIAS' => 'alias',
		'FLEXI_NAME' => 'name',
		'FLEXI_LABEL' => 'label',
	);
	var $default_order     = 'a.title';
	var $default_order_dir = 'ASC';

	/**
	 * List filters that are always applied
	 */
	var $hard_filters = array();

	/**
	 * Rows that can have their state modified
	 */
	var $changeable_rows = array('core.delete' => array(), 'core.edit.state' => array());

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
	 * Associated record translations
	 *
	 * @var array
	 */
	var $_translations = null;

	/**
	 * Single record id (used in operations)
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * A view id for distinguishing session data e.g. filters, this is usally just the 'view' URL var
	 *
	 * @var string
	 */
	var $view_id = null;


	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	public function __construct($config = array())
	{
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->getCmd('option', '');
		$view   = $jinput->getCmd('view', '');
		$layout = $jinput->getString('layout', 'default');
		$fcform = $jinput->getInt('fcform', 0);

		parent::__construct($config);

		// Session data group
		$p = $this->ovid = $option . '.' . ($this->view_id ?: $view . '_' . $layout) . '.';

		// Parameters of the view, in our case it is only the component parameters
		$this->cparams = JComponentHelper::getParams('com_flexicontent');

		// Make sure this is correct if called from different component ...
		$this->option = 'com_flexicontent';


		/**
		 * View's Filters
		 */

		// Various filters
		$filter_state  = $fcform ? $jinput->get('filter_state', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_state', 'filter_state', '', 'cmd');
		$filter_access = $fcform ? $jinput->get('filter_access', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_access', 'filter_access', '', 'cmd');
		$filter_lang   = $fcform ? $jinput->get('filter_lang', '', 'string') : $app->getUserStateFromRequest($p . 'filter_lang', 'filter_lang', '', 'string');
		$filter_author = $fcform ? $jinput->get('filter_author', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_author', 'filter_author', '', 'string');

		$this->setState('filter_state', $filter_state);
		$this->setState('filter_access', $filter_access);
		$this->setState('filter_lang', $filter_lang);
		$this->setState('filter_author', $filter_author);

		$app->setUserState($p . 'filter_state', $filter_state);
		$app->setUserState($p . 'filter_access', $filter_access);
		$app->setUserState($p . 'filter_lang', $filter_lang);
		$app->setUserState($p . 'filter_author', $filter_author);

		// Record ID filter
		$filter_id = $fcform ? $jinput->get('filter_id', '', 'int') : $app->getUserStateFromRequest($p . 'filter_id', 'filter_id', '', 'int');
		$filter_id = $filter_id ? $filter_id : '';  // needed to make text input field be empty

		$this->setState('filter_id', $filter_id);
		$app->setUserState($p . 'filter_id', $filter_id);

		// Text search scope
		$default_scope = ''; //in_array($this->name_col, $this->search_cols) ? 'a.' . $this->name_col : '-1';
		$scope = $fcform ? $jinput->get('scope', $default_scope , 'cmd') : $app->getUserStateFromRequest($p . 'scope', 'scope', $default_scope, 'cmd');
		$this->setState('scope', $scope);
		$app->setUserState($p . 'scope', $scope);

		// Text search
		$search = $fcform ? $jinput->get('search', '', 'string') : $app->getUserStateFromRequest($p . 'search', 'search', '', 'string');
		$this->setState('search', $search);
		$app->setUserState($p . 'search', $search);


		/**
		 * Ordering: filter_order, filter_order_Dir
		 */

		$this->_setStateOrder();


		/**
		 * Pagination: limit, limitstart
		 */

		$limit      = $fcform ? $jinput->get('limit', $app->getCfg('list_limit'), 'int') : $app->getUserStateFromRequest($p . 'limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $fcform ? $jinput->get('limitstart', 0, 'int') : $app->getUserStateFromRequest($p . 'limitstart', 'limitstart', 0, 'int');

		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );
		$jinput->set('limitstart', $limitstart);

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		$app->setUserState($p . 'limit', $limit);
		$app->setUserState($p . 'limitstart', $limitstart);


		// For some model function that use single id
		$array = $jinput->get('cid', array(0), 'array');
		$this->setId((int) $array[0]);

		// Manage view permission
		$this->canManage = false;
	}


	/**
	 * Method to set the record identifier (for singular operations) and clear record rows
	 *
	 * @param		int	    $id        record identifier
	 *
	 * @since   3.3.0
	 */
	public function setId($id)
	{
		// Set record id and wipe data, if setting a different ID
		if ($this->_id != $id)
		{
			$this->_id    = $id;
			$this->_data  = null;
			$this->_total = null;
		}
	}


	/**
	 * Method to set which record identifier that should be loaded when getItems() is called
	 *
	 * @param		array			$cid          array of record ids to load
	 *
	 * @since	  3.3.0
	 */
	public function setIds($cid)
	{
		$this->_ids   = ArrayHelper::toInteger($cid);
		$this->_data  = null;
		$this->_total = null;
	}


	/**
	 * Method to get a \JPagination object for the data set
	 *
	 * @return  \JPagination  A \JPagination object for the data set
	 *
	 * @since	1.5
	 */
	public function getPagination()
	{
		// Create pagination object if it doesn't already exist
		if (empty($this->_pagination))
		{
			require_once (JPATH_COMPONENT_SITE . DS . 'helpers' . DS . 'pagination.php');
			$this->_pagination = new FCPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
		}

		return $this->_pagination;
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
		if ($this->_data === null)
		{
			$this->_data  = $this->_getList($this->_getListQuery(), $this->getState('limitstart'), $this->getState('limit'));
			$this->_total = $this->_db->setQuery('SELECT FOUND_ROWS()')->loadResult();
		}

		return $this->_data;
	}


	/**
	 * Method to get the total nr of the records
	 *
	 * @return integer
	 *
	 * @since	1.5
	 */
	public function getTotal()
	{
		// Lets load the records if it was not calculated already via using SQL_CALC_FOUND_ROWS + 'SELECT FOUND_ROWS()'
		if ($this->_total === null)
		{
			$this->_total = (int) $this->_getListCount($this->_getListQuery());
		}

		return $this->_total;
	}


	/**
	 * Method to cache the last query constructed.
	 *
	 * This method ensures that the query is constructed only once for a given state of the model.
	 *
	 * @return  \JDatabaseQuery  A \JDatabaseQuery object
	 *
	 * @since   1.6
	 */
	protected function _getListQuery()
	{
		// Create query if not already created, note: a new model instance should be created if needing different data
		if (empty($this->query))
		{
			$this->query = $this->getListQuery();
		}

		return $this->query;
	}


	/**
	 * Method to build the query for the records
	 *
	 * @return  JDatabaseQuery   The DB Query object
	 *
	 * @since   3.3.0
	 */
	protected function getListQuery()
	{
		$table = $this->getTable($this->records_jtable, '');

		$has_checked_out_col = property_exists($table, 'checked_out');
		$has_access_col      = property_exists($table, 'access');
		$has_created_by_col  = property_exists($table, $this->created_by_col ?? '');

		$editor_col_quoted   = $has_checked_out_col ? $this->_db->quoteName('u.name') : $this->_db->Quote('');

		// Create a query with all its clauses: WHERE, HAVING and ORDER BY, etc
		$query = $this->_db->getQuery(true)
			->select('SQL_CALC_FOUND_ROWS a.*')
			->select($editor_col_quoted . ' AS editor')
			->from('#__' . $this->records_dbtbl . ' AS a')
			->group('a.id');

		// Join over the users for the current editor name
		if ($has_checked_out_col)
		{
			$query->leftJoin('#__users AS u ON u.id = a.checked_out');
		}

		// Join over the access levels for access level title
		if ($has_access_col)
		{
			$query->select('CASE WHEN level.title IS NULL THEN CONCAT_WS(\'\', \'deleted:\', a.access) ELSE level.title END AS access_level')
				->leftJoin('#__viewlevels as level ON level.id = a.access');
		}

		// Join over the users for the author name
		if ($has_created_by_col)
		{
			$query->select('ua.name AS author_name')
				->leftJoin('#__users AS ua ON ua.id = a.' . $this->created_by_col);
		}

		// Get the WHERE, HAVING and ORDER BY clauses for the query
		$this->_buildContentWhere($query);
		$this->_buildContentHaving($query);
		$this->_buildContentOrderBy($query);

		// Add always-active ("hard") filters
		$this->_buildHardFiltersWhere($query);

		return $query;
	}


	/**
	 * Method to build the orderby clause of the query for the records
	 *
	 * @param		JDatabaseQuery|bool   $q   DB Query object or bool to indicate returning an array or rendering the clause
	 *
	 * @return  JDatabaseQuery|array
	 *
	 * @since 3.3.0
	 */
	protected function _buildContentOrderBy($q = false)
	{
		$filter_order     = $this->getState('filter_order');
		$filter_order_Dir = $this->getState('filter_order_Dir');

		$order = $this->_db->escape($filter_order . ' ' . $filter_order_Dir);

		if ($q instanceof \JDatabaseQuery)
		{
			return $order ? $q->order($order) : $q;
		}

		return $q
			? ' ORDER BY ' . $order
			: $order;
	}


	/**
	 * Method to build the where clause of the query for the records
	 *
	 * @param		JDatabaseQuery|bool   $q   DB Query object or bool to indicate returning an array or rendering the clause
	 *
	 * @return  JDatabaseQuery|array
	 *
	 * @since   3.3.0
	 */
	protected function _buildContentWhere($q = false)
	{
		$table = $this->getTable($this->records_jtable, '');
		$user  = JFactory::getUser();

		// Various filters
		$filter_state  = $this->getState('filter_state');
		$filter_access = $this->getState('filter_access');
		$filter_lang   = $this->getState('filter_lang');
		$filter_author = $this->getState('filter_author');
		$filter_id     = $this->getState('filter_id');

		// Text search and search scope
		$scope  = $this->getState('scope');
		$search = $this->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));

		$where = array();

		// Filter by state
		if (property_exists($table, $this->state_col ??''))
		{
			switch ($filter_state)
			{
				case 'P':
					$where[] = 'a.' . $this->state_col . ' = 1';
					break;

				case 'U':
					$where[] = 'a.' . $this->state_col . ' = 0';
					break;

				case 'A':
					$where[] = 'a.' . $this->state_col . ' = 2';
					break;

				case 'T':
					$where[] = 'a.' . $this->state_col . ' = -2';
					break;

				case 'PE':
					$where[] = 'a.' . $this->state_col . ' = -3';
					break;

				case 'OQ':
					$where[] = 'a.' . $this->state_col . ' = -4';
					break;

				case 'IP':
					$where[] = 'a.' . $this->state_col . ' = -5';
					break;

				default:
					// ALL: published & unpublished, but exclude archived, trashed
					if (!strlen($filter_state))
					{
						$where[] = 'a.' . $this->state_col . ' <> -2';
						$where[] = 'a.' . $this->state_col . ' <> 2';
					}
					elseif (is_numeric($filter_state))
					{
						$where[] = 'a.' . $this->state_col . ' = ' . (int) $filter_state;
					}
			}
		}

		// Filter by language
		if (strlen($filter_lang))
		{
			$where[] = 'a.language = ' . $this->_db->Quote($filter_lang);
		}

		/**
		 * Limit via parameter,
		 * 1: limit to current user as author,
		 * 0: list files from any uploader, and respect 'filter_uploader' URL variable
		 */
		$limit_by_author = 0;  // TODO: implement like in filemanager parameters
		$CanViewAllRecords = true;  // Unused, except for filemanager, set to true for all other case

		// Limit to current user
		if ($limit_by_author || !$CanViewAllRecords)
		{
			$where[] = 'a.' . $this->created_by_col . ' = ' . (int) $user->id;
		}

		// Filter by author / owner
		elseif (strlen($filter_author))
		{
			$where[] = 'a.' . $this->created_by_col . ' = ' . (int) $filter_author;
		}

		// Filter by access level
		if (property_exists($table, 'access'))
		{
			if (strlen($filter_access))
			{
				$where[] = 'a.access = ' . (int) $filter_access;
			}

			// Filter via View Level Access, if user is not super-admin
			if (!JFactory::getUser()->authorise('core.admin') && (JFactory::getApplication()->isClient('site') || $this->listViaAccess))
			{
				$groups  = implode(',', JAccess::getAuthorisedViewLevels($user->id));
				$where[] = 'a.access IN (' . $groups . ')';
			}
		}

		// Filter by record id
		if ($filter_id)
		{
			$where[] = 'a.id = ' . (int) $filter_id;
		}

		/**
		 * Filter according to search text and search scope
		 * (search text can be also be  id:NN  OR author:AAAAA)
		 */
		$textwhere = $this->_getTextSearch();

		if ($textwhere)
		{
			$where[] = '(' . implode(' OR ', $textwhere) . ')';
		}

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
		$having = array();

		if ($q instanceof \JDatabaseQuery)
		{
			return $having ? $q->having($having) : $q;
		}

		return $q
			? ' HAVING ' . (count($having) ? implode(' AND ', $having) : ' 1 ')
			: $having;
	}


	/**
	 * Method to publish / unpublish / etc a record, also checking ACL and assignments
	 *
	 * @param		array			$cid          Array of record ids to set to a new state
	 * @param		integer   $state        The new state
	 *
	 * @return	boolean	  True on success
	 *
	 * @since   3.3.0
	 */
	public function publish($cid, $state = 1)
	{
		/**
		 * Perform ACL and Assignments checks before calling the changestate method, this should be done at the controller,
		 * but we add it here for compatibility, if this code is removed, and also not done by the caller (e.g. controller),
		 * then ZERO records will be modified
		 */
		$cid_noauth  = null;
		$cid_wassocs = null;
		$this->canDoAction($cid, $cid_noauth, $cid_wassocs, $tostate = $state);

		return $this->changestate($cid, $state) !== false;
	}


	/**
	 * Method to change publication state a record (assumes ACL / assignments already checked)
	 *
	 * @param		array			$cid          Array of record ids to set to a new state
	 * @param		integer   $state        The new state
	 *
	 * @return	boolean	  The number of records modified, false on failure
	 *
	 * @since	3.3.0
	 */
	public function changestate($cid, $state = 1)
	{
		$cid = ArrayHelper::toInteger($cid);

		// Verify that records ACL has been checked
		$ids = array();

		foreach ($cid as $id)
		{
			if (isset($this->changeable_rows['core.edit.state'][$id]))
			{
				$ids[] = $id;
			}
		}

		if (count($ids))
		{
			$user = JFactory::getUser();

			// This is already done by controller task / caller but redo
			$ids = ArrayHelper::toInteger($ids);
			$cid_list = implode(',', $ids);

			$query = $this->_db->getQuery(true)
				->update('#__' . $this->records_dbtbl)
				->set($this->_db->qn($this->state_col) . ' = ' . (int) $state)
				->where('id IN (' . $cid_list . ')')
				->where('(checked_out = 0 OR checked_out IS NULL OR checked_out = ' . (int) $user->get('id') . ')');

			/**
			 * Only update records changing publication state,
			 * this is important when updating also other properties of the records ...
			 */
			$query->where($this->_db->qn($this->state_col) . ' <> ' . (int) $state);

			/**
			 * Get SET-clause to set new values to columns related to the changing state of the records
			 */
			$extra_set = $this->getExtraStateChangeProps($state);

			if ($extra_set)
			{
				$query->set($extra_set);
			}

			$this->_db->setQuery($query)->execute();

			// Return modified record count, by subtracting effected from initial count
			return (int) $this->_db->getAffectedRows();
		}

		return 0;
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

		return $set_properties;
	}


	/**
	 * Method to move a record upwards or downwards
	 *
	 * @param   integer   $direction   A value of 1  or -1 to indicate moving up or down respectively
	 * @param   integer   $parent_id   The id of the parent if applicable
	 *
	 * @return	boolean	  True on success
	 *
	 * @since	3.3.0
	 */
	public function move($direction, $parent_id)
	{
		// Load the moved record
		$table = $this->getTable($this->records_jtable, '');

		if (!$table->load($this->_id))
		{
			$this->setError($table->getError());
			return false;
		}

		$where = $this->parent_col
			? $this->_db->Quote($this->parent_col) . ' = ' . (int) ($parent_id ?: $table->catid)
			: '';

		if (!$table->move($direction, $where))
		{
			$this->setError($table->getError());
			return false;
		}

		return true;
	}


	/**
	 * Method to check if a set of records can not have the provided action performed due to assignments or due to permissions
	 * This will also mark ACL allowed records into model, this is required by methods like changestate to have an effect
	 *
	 * @param   array       $cid          array of record ids to check
	 * @param   array       $cid_noauth   (variable by reference), pass authorizing -ignored- IDs and return an array of non-authorized record ids
	 * @param   array       $cid_wassocs  (variable by reference), pass assignments -ignored- IDs and return an array of 'locked' record ids
	 * @param   int|string  $action       ACL rule name or new state value, to use this for calculating ACL
	 *
	 * @return  boolean   True when at least 1 publishable record found
	 *
	 * @since	3.3.0
	 */
	public function canDoAction(& $cid, & $cid_noauth = null, & $cid_wassocs = null, $action = 0)
	{
		$authorizing_ignored = $cid_noauth ? array_flip($cid_noauth) : array();
		$assignments_ignored = $cid_wassocs ? array_flip($cid_wassocs) : array();

		$cid_noauth  = array();
		$cid_wassocs = array();

		// Add children records
		if (in_array('FCModelTraitNestableRecord', class_uses($this)))
		{
			// If publishing then add all parents to the list, so that they get published too
			if (is_int($action) && (int) $action === 1)
			{
				foreach ($cid as $_id)
				{
					$this->_addPathRecords($_id, $cid, 'parents');
				}
			}

			// If not publishing then all children to the list, so that they get the new state too
			else
			{
				foreach ($cid as $_id)
				{
					$this->_addPathRecords($_id, $cid, 'children');
				}
			}
		}

		// Find ACL disallowed
		$cid_noauth = $this->filterByPermission($cid, $action);

		foreach ($cid_noauth as $i => $id)
		{
			if (isset($authorizing_ignored[$id]))
			{
				unset($cid_noauth[$i]);
			}
		}

		// Find having blocking assignments (if applicable for this record type)
		$cid_wassocs = $this->filterByAssignments($cid, $action);

		foreach ($cid_wassocs as $i => $id)
		{
			if (isset($assignments_ignored[$id]))
			{
				unset($cid_wassocs[$i]);
			}
		}

		return !count($cid_noauth) && !count($cid_wassocs);
	}


	/**
	 * Method to remove records
	 *
	 * @param		array			$cid          array of record ids to delete
	 *
	 * @return	boolean	True on success
	 *
	 * @since	1.0
	 */
	public function delete($cid, $model = null)
	{
		if ( !count( $cid ) ) return false;

		$cid = ArrayHelper::toInteger($cid);

		// Verify that records ACL has been checked
		$ids = array();

		foreach ($cid as $id)
		{
			if (isset($this->changeable_rows['core.delete'][$id]))
			{
				$ids[] = $id;
			}
		}


		/**
		 * Get records using the model, we need an array of records to use for calling the events
		 */
		$record_arr = array();

		if ($model && $this->event_context && ($this->event_before_delete || $this->event_after_delete))
		{
			$app = JFactory::getApplication();
			$dispatcher = JEventDispatcher::getInstance();

			// Load all content and flexicontent plugins for triggering their delete events
			if ($this->event_context === 'com_content.article')
			{
				JPluginHelper::importPlugin('content');
			}
			JPluginHelper::importPlugin('flexicontent');

			foreach ($cid as $record_id)
			{
				$record = $model->getRecord($record_id);
				$record_arr[] = $record;
			}
		}

		/**
		 * Trigger onBeforeDelete event(s)
		 */
		if ($this->event_before_delete) foreach($record_arr as $record)
		{
			// Trigger Event 'event_before_delete' e.g. 'onContentBeforeDelete' of flexicontent or content plugins
			FLEXI_J40GE
				? $app->triggerEvent($this->event_before_delete, array($this->event_context, $record))
				: $dispatcher->trigger($this->event_before_delete, array($this->event_context, $record));
		}


		if (count($ids))
		{
			// This is already done by controller task / caller but redo
			$ids = ArrayHelper::toInteger($ids);
			$cid_list = implode(',', $ids);

			// Delete records themselves
			$query = $this->_db->getQuery(true)
				->delete('#__' . $this->records_dbtbl)
				->where('id IN (' . $cid_list . ')');

			$this->_db->setQuery($query)->execute();

			// Also delete related Data, like 'assignments'
			$this->delete_relations($cid);
		}

		/**
		 * Trigger onAfterDelete event
		 */
		if ($this->event_after_delete) foreach($record_arr as $record)
		{
			// Trigger Event 'event_before_delete' e.g. 'onContentAfterDelete' of flexicontent or content plugins
			FLEXI_J40GE
				? $app->triggerEvent($this->event_after_delete, array($this->event_context, $record))
				: $dispatcher->trigger($this->event_after_delete, array($this->event_context, $record));
		}

		return true;
	}


	/**
	 * Method to delete records relations like record assignments
	 *
	 * @param		array			$cid      array of record ids to delete their related data
	 *
	 * @return	bool      True on success
	 *
	 * @since		3.3.0
	 */
	public function delete_relations($cid)
	{
		return true;
	}


	/**
	 * Method to copy records
	 *
	 * @param		array			$cid          array of record ids to copy
	 * @param		array			$copyRelations   flag to indicate copying 'related' data, like 'assignments'
	 *
	 * @return	array		Array of old-to new record ids of copied record IDs
	 *
	 * @since   3.3.0
	 */
	public function copy($cid, $copyRelations = null)
	{
		$copyRelations = $copyRelations === null ? $this->copyValues : $copyRelations;
		$ids_map       = array();
		$name          = $this->name_col;

		foreach ($cid as $id)
		{
			$table = $this->getTable($this->records_jtable, '');
			$table->load($id);

			$table->id    = 0;
			$table->$name = $table->$name . ' [copy]';
			$table->alias = JFilterOutput::stringURLSafe($table->$name);

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
	 * @since   3.3.0
	 */
	protected function _copyRelatedData($ids_map)
	{
	}


	/**
	 * Method to set the access level of the records
	 *
	 * @param		integer		id of the record
	 * @param		integer		access level
	 *
	 * @return	boolean		True on success
	 *
	 * @since		1.5
	 */
	public function saveaccess($id, $access)
	{
		$table = $this->getTable($this->records_jtable, '');

		$cid      = is_array($id) ? $id : array($id);
		$accesses = is_array($access) ? $access : array($access);

		foreach ($cid as $id)
		{
			$table->load($id);
			$table->id     = $id;
			$table->access = $accesses[$id];

			if (!$table->check())
			{
				$this->setError($table->getError());

				return false;
			}

			if (!$table->store())
			{
				$this->setError($table->getError());

				return false;
			}
		}

		return true;
	}


	/**
	 * Method to find which records are not authorized
	 *
	 * @param   array        $cid      Array of record ids to check
	 * @param		int|string   $action   Either an ACL rule action, or a new state
	 *
	 * @return	array     The records having assignments
	 *
	 * @since	3.3.0
	 */
	public function filterByPermission($cid, $action)
	{
		$user  = JFactory::getUser();
		$table = $this->getTable($this->records_jtable, '');

		$cid   = ArrayHelper::toInteger($cid);
		$rule  = is_int($action) ? 'core.edit.state' : $action;

		// If cannot manage then all records are not changeable
		if (!$this->canManage)
		{
			return $cid;
		}

		$has_assets_tracking = property_exists($table, 'asset_id');
		$has_created_by_col  = property_exists($table, $this->created_by_col ?? '');

		// Get record owners, needed for *.own ACL
		$query = $this->_db->getQuery(true)
			->select('c.' . $table->getKeyName() . ' AS id')
			->select('c.' . $this->state_col . ' AS state')
			->from('#__' . $this->records_dbtbl . ' AS c')
			->where('c.' . $table->getKeyName() . ' IN (' . implode(',', $cid) . ')');

		if ($has_created_by_col)
		{
			$query->select('c.' . $this->created_by_col . ' AS created_by');
		}

		$rows = $this->_db->setQuery($query)->loadObjectList('id');

		$cid_noauth   = array();
		$asset_prefix = $has_assets_tracking ? $table->getAssetPrefix() : null;
		$mapped_rule  = isset($this->rules_map[$rule]) ? $this->rules_map[$rule] : $rule;

		foreach ($rows as $id => $row)
		{
			// If this record type does not track assets, then check ACL rule according to component asset
			$asset = $has_assets_tracking ? $asset_prefix . '.' . $id : $this->option;

			$canDo		= $user->authorise($mapped_rule, $asset);
			$canDoOwn	= $has_created_by_col
				? $user->authorise($mapped_rule . '.own', $asset) && $row->created_by == $user->get('id')
				: false;
			$allowed = $canDo || $canDoOwn;

			if (!$allowed)
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
	 * @param		array        $cid      Array of record ids to check
	 * @param		int|string   $action   Either an ACL rule action, or a new state
	 *
	 * @return	array     The records having assignments
	 *
	 * @since   3.3.0
	 */
	public function filterByAssignments($cid = array(), $action = -2)
	{
		$cid = ArrayHelper::toInteger($cid);
		$cid_wassocs = array();

		switch ((string)$action)
		{
			// Delete
			case 'core.delete':
				break;

			// Trash, Unpublish
			case -2:
			case 0:
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
		$view   = $jinput->getCmd('view', '');
		$fcform = $jinput->getInt('fcform', 0);
		$p      = $this->ovid;

		// Use ordering parameter from component configuration if these exist and are set
		$default_order     = $this->cparams->get($view . '_manager_order', $this->default_order);
		$default_order_dir = $this->cparams->get($view . '_manager_order_dir', $this->default_order_dir);

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

		$this->setState('filter_order', $filter_order);
		$this->setState('filter_order_Dir', $filter_order_Dir);

		$app->setUserState($p . 'filter_order', $filter_order);
		$app->setUserState($p . 'filter_order_Dir', $filter_order_Dir);
	}


	/**
	 * Method to get Text Search clause according to search scope
	 *
	 * @return	void
	 *
	 * @since 3.3.0
	 */
	protected function _getTextSearch()
	{
		// Text search and search scope
		$scope  = $this->getState('scope');
		$search = $this->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));

		// Create the text search clauses
		$table     = $this->getTable($this->records_jtable, '');
		$col_name  = str_replace('a.', '', $scope);
		$textwhere = array();

		if (!empty($this->search_cols) && strlen($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$textwhere[] = 'a.id = ' . (int) substr($search, 3);
			}
			elseif (stripos($search, 'author:') === 0)
			{
				$search_quoted = $this->_db->Quote('%' . $this->_db->escape(substr($search, 7), true) . '%');
				$textwhere[] = 'ua.name LIKE ' . $search_quoted;
				$textwhere[] = 'ua.username LIKE ' . $search_quoted;
			}
			else
			{
				$escaped_search = str_replace(' ', '%', $this->_db->escape(trim($search), true));
				$search_quoted  = $this->_db->Quote('%' . $escaped_search . '%', false);

				if ($scope === '-1')
				{
					foreach ($this->search_cols as $search_col)
					{
						if (property_exists($table, $search_col))
						{
							$textwhere[] = 'LOWER(a.' . $search_col . ') LIKE ' . $search_quoted;
						}
					}
				}
				elseif (in_array($col_name, $this->search_cols) && property_exists($table, $col_name))
				{
					// Scope is user input, was filtered as CMD but also use quoteName() on the column
					$textwhere[] = 'LOWER(' . $this->_db->quoteName($scope) . ') LIKE ' . $search_quoted;
				}
				else
				{
					JFactory::getApplication()->enqueueMessage('Text search scope ' . $scope . ' is unknown, search failed', 'warning');
				}
			}
		}

		return $textwhere;
	}


	/**
	 * Method to get records matching specific conditions (SQL query clauses)
	 *
	 * @param   array   $clauses   Array of SQL clauses (each of them a array) like, where, order, etc
	 * @param   bool    $useMain   If true the use query created by _getListQuery()
	 *
	 * @return array
	 *
	 * @since 3.3.0
	 */
	public function getItemsByConditions($clauses = array(), $useMain = false)
	{
		// Either use main Query
		if ($useMain)
		{
			$query = $this->_getListQuery()
				->clear('where')
				->clear('order')
				->setLimit($limit = 0, $offset = 0);
		}
		else
		{
			$query = $this->_db->getQuery(true)
				->from('#__' . $this->records_dbtbl . ' AS t');

			if (!isset($clauses['select']))
			{
				$query->select('t.*');
			}
		}

		// Add the given SQL clauses
		foreach ($clauses as $clause_name => $clause_value)
		{
			$query->{$clause_name}($clause_value);
		}

		return $this->_db->setQuery($query)->loadObjectList('id');
	}


	/**
	 * Method to get author list for filtering
	 *
	 * @return	array   An array with all users owning at least 1 record
	 *
	 * @since   3.3.0
	 */
	public function getAuthorslist()
	{
		$query = $this->_db->getQuery(true)
			->select('a.' . $this->created_by_col . ' AS id, ua.name AS name')
			->from('#__' . $this->records_dbtbl . ' AS a')
			->join('LEFT', '#__users as ua ON ua.id = a.' . $this->created_by_col)
			->group('a.' . $this->created_by_col)
			->order('ua.name');

		return $this->_db->setQuery($query)->loadObjectList();
	}


	/**
	 * Method to get item (language) associations
	 *
	 * @param		array   $ids       An array of records is
	 * @param		object  $config    An object with configuration for getting associations
	 *
	 * @return	array   An array with associations of the records list
	 *
	 * @since   3.3.0
	 */
	public function getLangAssocs($ids = null, $config = null)
	{
		if ($ids)
		{
			return flexicontent_db::getLangAssocs($ids, $config);
		}

		// If items array is empty, just return empty array
		elseif (empty($this->_data))
		{
			return array();
		}

		// Get associated translations
		elseif ($this->_translations === null)
		{
			$ids = array();

			foreach ($this->_data as $item)
			{
				$ids[] = $item->id;
			}

			$this->_translations = flexicontent_db::getLangAssocs($ids, $config);
		}

		return $this->_translations;
	}


	/**
	 * Method to save the reordered nested set tree.
	 * First we save the new order values in the lft values of the changed ids.
	 * Then we invoke the table rebuild to implement the new ordering.
	 *
	 * @param   array    $pks       An array of primary key ids.
	 * @param   integer  $order     The lft or ordering value
	 * @param   integer  $group_id  The parent ID of the group / category being reorder, this is needed when records are assigned to multiple groups / categories
	 *
	 * @return  boolean   Boolean true on success, false on failure
	 *
	 * @since   3.3.0
	 */
	public function saveorder($pks, $order, $group_id = 0)
	{
		// Get an instance of the table object.
		$table = $this->getTable();

		if (!$table->saveorder($pks, $order))
		{
			$this->setError($table->getError());
			return false;
		}

		// Clear the cache
		$this->cleanCache();

		return true;
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */

}
