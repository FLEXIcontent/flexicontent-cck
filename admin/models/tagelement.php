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

require_once('base/baselist.php');

/**
 * Flexicontent Component Tagelement Model
 *
 */
class FlexicontentModelTagelement extends FCModelAdminList
{
	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl  = 'flexicontent_tags';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_tags';

	/**
	 * Column names
	 */
	var $state_col      = 'published';
	var $name_col       = 'name';
	var $parent_col     = null;
	var $created_by_col = null;

	/**
	 * (Default) Behaviour Flags
	 */
	protected $listViaAccess = false;
	protected $copyRelations = false;

	/**
	 * Search and ordering columns
	 */
	var $search_cols = array(
		'FLEXI_NAME' => 'name',
		'FLEXI_ALIAS' => 'alias',
	);
	var $default_order     = 'a.name';
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
		 * Inherited filters : filter_state, search
		 */

		// Various filters
		$filter_assigned = $fcform ? $jinput->get('filter_assigned', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_assigned', 'filter_assigned', '', 'cmd');
		$this->setState('filter_assigned', $filter_assigned);
		$app->setUserState($p . 'filter_assigned', $filter_assigned);


		// Manage view permission
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanTags;
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
		// Create a query with all its clauses: WHERE, HAVING and ORDER BY, etc
		$query = parent::getListQuery()
			->select(
				'CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug'
			)
		;

		/**
		 * Get assignments
		 */

		$filter_order    = $this->getState('filter_order');
		$filter_assigned = $this->getState('filter_assigned');

		// Join to get assignments
		if ($filter_assigned || $filter_order == 'nrassigned')
		{
			$query->leftJoin('#__flexicontent_tags_item_relations AS rel ON rel.tid = a.id');
		}

		// Select to get assignments
		if ($filter_order === 'nrassigned')
		{
			$query->select('(SELECT COUNT(rel.tid) FROM #__flexicontent_tags_item_relations AS rel WHERE rel.tid = a.id GROUP BY a.id) AS nrassigned');
		}

		/**
		 * Because of multi-multi tag-item relations it is faster to calculate this with a single seperate query
		 * if it was single mapping e.g. like it is 'item' TO 'content type' or 'item' TO 'creator' we could use a subquery
		 * the more tags are listed (query LIMIT) the bigger the performance difference ...
		 */
		// ... by using method *::getAssignedItems()

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
		// Inherited filters : filter_state, search
		$where = parent::_buildContentWhere(false);

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
		$rel_col         = 'rel.tid';

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
	 * START OF MODEL SPECIFIC METHODS
	 */




	/**
	 * Method to count assigned items for the given categories
	 *
	 * @return	string
	 *
	 * @since	1.6
	 */
	public function getAssignedItems($tids)
	{
		if (empty($tids))
		{
			return array();
		}

		// Select the required fields from the table.
		$query = " SELECT rel.tid, COUNT(rel.itemid) AS nrassigned"
			. " FROM #__flexicontent_tags_item_relations AS rel"
			. " WHERE rel.tid IN (" . implode(",", $tids) . ") "
			. " GROUP BY rel.tid";

		$assigned = $this->_db->setQuery($query)->loadObjectList('tid');
		return $assigned;
	}



}
