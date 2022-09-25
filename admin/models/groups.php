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
use Joomla\CMS\Table\TableNested;
use Joomla\CMS\Table\Usergroup;

require_once('base/baselist.php');
require_once('base/traitnestable.php');

/**
 * FLEXIcontent Component User Groups Model
 *
 */
if (FLEXI_J40GE)
{
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'src'.DS.'Model'.DS.'GroupsModel.php');

	class _FlexicontentModelGroups extends Joomla\Component\Users\Administrator\Model\GroupsModel
	{
	}
}
else
{
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'models'.DS.'groups.php');

	class _FlexicontentModelGroups extends UsersModelGroups
	{
	}
}

//class FlexicontentModelGroups extends _FlexicontentModelGroups {}
class FlexicontentModelGroups extends FCModelAdminList
{

	use FCModelTraitNestableRecord;

	var $records_dbtbl  = 'usergroups';
	var $records_jtable = 'JTableUserGroup';

	/**
	 * Column names
	 */
	var $state_col      = null;
	var $name_col       = 'title';
	var $parent_col     = 'parent_id';

	/**
	 * (Default) Behaviour Flags
	 */
	protected $listViaAccess = false;
	protected $copyRelations = false;

	/**
	 * Search and ordering columns
	 */
	var $search_cols = array(
		'FLEXI_TITLE' => 'title',
	);
	var $default_order     = 'a.lft';
	var $default_order_dir = 'ASC';

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
		 * Inherited filters : search
		 */

		// Various filters


		// Manage view permission
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanAuthors;
	}


	/**
	 * Gets the list of groups and adds expensive joins to the result set.
	 *
	 * @return  mixed  An array of data items on success, false on failure.
	 *
	 * @since   1.6
	 */
	public function getItems()
	{
		if ($this->_data !== null)
		{
			return $this->_data;
		}

		parent::getItems();

		try
		{
			$this->_data = $this->populateExtraData($this->_data);
		}
		catch (RuntimeException $e)
		{
			$this->setError($e->getMessage());

			return false;
		}

		return $this->_data;
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

		/**
		 * Get user counts. Disabled we will use seperate query that will use groups of current page only
		 */
		//$query->select('(SELECT COUNT(*) FROM #__user_usergroup_map AS ug ON ug.user_id = a.id) AS user_count')

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
		// Inherited filters : filter_id, search
		$where = parent::_buildContentWhere(false);

		// Various filters


		if ($q instanceof \JDatabaseQuery)
		{
			return $where ? $q->where($where) : $q;
		}

		return $q
			? ' WHERE ' . (count($where) ? implode(' AND ', $where) : ' 1 ')
			: $where;
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
	 * Populate level & path for items.
	 *
	 * @param   array  $items  Array of stdClass objects
	 *
	 * @return  array
	 *
	 * @since   3.0.0
	 */
	private function populateExtraData(array $items)
	{
		// First pass: get list of the group id's and reset the counts.
		$groupsByKey = array();

		foreach ($items as $item)
		{
			$groupsByKey[(int) $item->id] = $item;
		}

		$groupIds = array_keys($groupsByKey);

		$db = $this->getDbo();

		// Get total enabled users in group.
		$query = $db->getQuery(true);

		// Count the objects in the user group.
		$query->select('map.group_id, COUNT(DISTINCT map.user_id) AS user_count')
			->from($db->quoteName('#__user_usergroup_map', 'map'))
			->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('map.user_id'))
			->where($db->quoteName('map.group_id') . ' IN (' . implode(',', $groupIds) . ')')
			->where($db->quoteName('u.block') . ' = 0')
			->group($db->quoteName('map.group_id'));
		$db->setQuery($query);

		try
		{
			$countEnabled = $db->loadAssocList('group_id', 'count_enabled');
		}
		catch (RuntimeException $e)
		{
			$this->setError($e->getMessage());

			return false;
		}

		// Get total disabled users in group.
		$query->clear('where')
			->where('map.group_id IN (' . implode(',', $groupIds) . ')')
			->where('u.block = 1');
		$db->setQuery($query);

		try
		{
			$countDisabled = $db->loadAssocList('group_id', 'count_disabled');
		}
		catch (RuntimeException $e)
		{
			$this->setError($e->getMessage());

			return false;
		}

		// Inject the values back into the array.
		foreach ($groupsByKey as &$item)
		{
			$item->count_enabled   = isset($countEnabled[$item->id]) ? (int) $countEnabled[$item->id]['user_count'] : 0;
			$item->count_disabled  = isset($countDisabled[$item->id]) ? (int) $countDisabled[$item->id]['user_count'] : 0;
			$item->user_count      = $item->count_enabled + $item->count_disabled;
		}

		$groups = new JHelperUsergroups($groupsByKey);

		return array_values($groups->getAll());
	}


	/**
	 * START OF MODEL LEGACY METHODS
	 */

}