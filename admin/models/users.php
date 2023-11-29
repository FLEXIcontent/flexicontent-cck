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
use Joomla\CMS\Table\User;

require_once('base/baselist.php');

/**
 * FLEXIcontent Component Users Model
 *
 */
class FlexicontentModelUsers extends FCModelAdminList
{
	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl  = 'users';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'JTableUser';

	/**
	 * Column names
	 */
	var $state_col      = 'block';
	var $name_col       = 'name';
	var $parent_col     = null;

	/**
	 * (Default) Behaviour Flags
	 */
	protected $listViaAccess = false;
	protected $copyRelations = false;

	/**
	 * Search and ordering columns
	 */
	var $search_cols = array(
		'FLEXI_USER_NAME' => 'username',
		'FLEXI_USER_EMAIL' => 'email',
		'FLEXI_NAME' => 'name',
	);
	var $default_order     = 'a.id';
	var $default_order_dir = 'DESC';

	/**
	 * List filters that are always applied
	 */
	var $hard_filters = array();

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

		// Make session index more specific ... (if needed by this model)
		//$this->view_id = $view . '_' . $layout;

		// Call parent after setting ... $this->view_id
		parent::__construct($config);

		$p = $this->ovid;


		/**
		 * View's Filters
		 * Inherited filters : filter_state, filter_id, scope, search
		 */

		// Various filters
		$filter_itemscount = $fcform ? $jinput->get('filter_itemscount', 0, 'int') : $app->getUserStateFromRequest($p . 'filter_itemscount', 'filter_itemscount', 0, 'int');
		$filter_usergrp    = $fcform ? $jinput->get('filter_usergrp', 0, 'int') : $app->getUserStateFromRequest($p . 'filter_usergrp', 'filter_usergrp', 0, 'int');
		$filter_logged     = $fcform ? $jinput->get('filter_logged', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_logged', 'filter_logged', '', 'cmd');
		$filter_active     = $fcform ? $jinput->get('filter_active', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_active', 'filter_active', '', 'cmd');

		$this->setState('filter_itemscount', $filter_itemscount);
		$this->setState('filter_usergrp', $filter_usergrp);
		$this->setState('filter_logged', $filter_logged);
		$this->setState('filter_active', $filter_active);

		$app->setUserState($p . 'filter_itemscount', $filter_itemscount);
		$app->setUserState($p . 'filter_usergrp', $filter_usergrp);
		$app->setUserState($p . 'filter_logged', $filter_logged);
		$app->setUserState($p . 'filter_active', $filter_active);


		// Date filters
		$date      = $fcform ? $jinput->get('date', 1, 'int') : $app->getUserStateFromRequest($p . 'date', 'date', 1, 'int');
		$startdate = $fcform ? $jinput->get('startdate', '', 'cmd') : $app->getUserStateFromRequest($p . 'startdate', 'startdate', '', 'cmd');
		$enddate   = $fcform ? $jinput->get('enddate', '', 'cmd') : $app->getUserStateFromRequest($p . 'enddate', 'enddate', '', 'cmd');

		$this->setState('date', $date);
		$this->setState('startdate', $startdate);
		$this->setState('enddate', $enddate);

		$app->setUserState($p . 'date', $date);
		$app->setUserState($p . 'startdate', $startdate);
		$app->setUserState($p . 'enddate', $enddate);


		// Manage view permission
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanAuthors;
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
			->select('s.userid IS NOT NULL AS loggedin')
			->select('(SELECT COUNT(*) FROM #__content AS i WHERE i.created_by = a.id) AS itemscount')
			->select('(SELECT SUM(size) FROM #__flexicontent_files AS f WHERE f.uploaded_by = a.id) AS uploadssize')
			->from('#__' . $this->records_dbtbl . ' AS a')
			->leftJoin('#__flexicontent_authors_ext AS ue ON a.id = ue.user_id')
			->leftJoin('#__session AS s ON s.userid = a.id')
			->group('a.id');

		// Join over the users for the current editor name
		if ($has_checked_out_col)
		{
			$query->leftJoin('#__users AS u ON u.id = a.checked_out');
		}

		/**
		 * Filtering by usergroup, right join with usergroups DB table, to limit users to those belonging to the selected group
		 */
		$filter_usergrp = $this->getState('filter_usergrp');

		if ($filter_usergrp)
		{
			$query->rightJoin('#__user_usergroup_map AS ug ON ug.user_id = a.id AND ug.group_id = ' . (int) $filter_usergrp);
		}

		// Get the WHERE, HAVING and ORDER BY clauses for the query
		$this->_buildContentWhere($query);
		$this->_buildContentHaving($query);
		$this->_buildContentOrderBy($query);

		return $query;
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
		// Inherited filters : filter_state, filter_id, search
		$where = parent::_buildContentWhere(false);

		// Various filters
		$filter_logged = $this->getState('filter_logged');
		$filter_active = $this->getState('filter_active');

		// Date filters
		$date = $this->getState('date');

		$startdate = $this->getState('startdate');
		$enddate   = $this->getState('enddate');
		$startdate = StringHelper::trim(StringHelper::strtolower($startdate));
		$enddate   = StringHelper::trim(StringHelper::strtolower($enddate));

		// Register, last-visit, last-login dates
		$date_filter_types = array(
			1 => 'a.registerDate',
			2 => 'a.registerDate',
			/* 3 => 'n.lastLogin', */
		);
		$date_col = isset($date_filter_types[$date]) ? $date_filter_types[$date] : null;

		if ($date_col)
		{
			// Date range
			if ($startdate && $enddate)
			{
				$where[] = '(' . $date_col . ' >= ' . $this->_db->Quote($startdate) . ' AND ' . $date_col . ' <= ' . $this->_db->Quote($enddate) . ')';
			}

			// From date only
			elseif ($startdate && !$enddate)
			{
				$where[] = $date_col . ' >= ' . $this->_db->Quote($startdate);
			}

			// To date only
			elseif (!$startdate && $enddate)
			{
				$where[] = $date_col . ' <= ' . $this->_db->Quote($enddate);
			}
		}

		// Filter by is-Logged
		if (strlen($filter_logged))
		{
			if ($filter_logged === '1')
			{
				$where[] = 's.userid IS NOT NULL';
			}
			elseif ($filter_logged === '0')
			{
				$where[] = 's.userid IS NULL';
			}
		}

		// Filter by is-Active (not blocked)
		if (strlen($filter_active))
		{
			if ($filter_active === '1')
			{
				$where[] = ' LENGTH(a.activation) > 1';  // Not active
			}
			elseif ($filter_active === '0')
			{
				$where[] = 'a.activation IN (' . $this->_db->quote('') . ', ' . $this->_db->quote('0') . ')';   // Active
			}
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
		$having = parent::_buildContentHaving(false);

		$filter_itemscount = $this->getState('filter_itemscount');

		switch ($filter_itemscount)
		{
			case 2:
				$having[] = 'itemscount > 0';
				break;

			case 1:
				$having[] = 'itemscount = 0';
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
	 * Method to find which records are not authorized
	 *
	 * @param   array        $cid      Array of record ids to check
	 * @param		int|string   $action   Either an ACL rule action, or a new state
	 *
	 * @return	array     The records having assignments
	 *
	 * @since   3.3.0
	 */
	public function filterByPermission($cid, $action)
	{
		return parent::filterByPermission($cid, $action);
	}


	/**
	 * Method to find which records having assignments blocking a state change
	 *
	 * @param		array     $cid      array of record ids to check
	 * @param		string    $tostate  action related to assignments
	 *
	 * @return	array     The records having assignments
	 *
	 * @since   3.3.0
	 */
	public function filterByAssignments($cid = array(), $tostate = -2)
	{
		return parent::filterByAssignments($cid, $tostate);
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */


	/**
	 * START OF MODEL LEGACY METHODS
	 */

}
