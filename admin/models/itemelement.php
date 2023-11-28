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
 * Flexicontent Component Itemelement Model
 *
 */
class FlexicontentModelItemelement extends FCModelAdminList
{
	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl = 'content';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_items';

	/**
	 * Column names
	 */
	var $state_col      = 'state';
	var $name_col       = 'title';
	var $parent_col     = 'catid';
	var $created_by_col = 'created_by';

	/**
	 * (Default) Behaviour Flags
	 */
	protected $listViaAccess = true;
	protected $copyRelations = false;

	/**
	 * Search and ordering columns
	 */
	var $search_cols = array(
		'FLEXI_TITLE' => 'title',
		'FLEXI_ALIAS' => 'alias',
		'FLEXI_NOTES' => 'note',
	);
	var $default_order     = 'a.id';
	var $default_order_dir = 'DESC';

	/**
	 * List filters that are always applied
	 */
	var $hard_filters = array('c.extension' => FLEXI_CAT_EXTENSION);

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
		$this->assocs_id = $jinput->getInt('assocs_id', 0);
		$this->view_id   = $view . '_' . $layout . ($this->assocs_id ? '' . $this->assocs_id : '');

		// Call parent after setting ... $this->view_id
		parent::__construct($config);

		$p = $this->ovid;


		/**
		 * View's Filters
		 * Inherited filters : filter_state, filter_access, filter_lang, filter_author, filter_id, search
		 */

		// Various filters
		$filter_cats     = $fcform ? $jinput->get('filter_cats', 0, 'int') : $app->getUserStateFromRequest($p . 'filter_cats', 'filter_cats', 0, 'int');
		$filter_type     = $fcform ? $jinput->get('filter_type', 0, 'int') : $app->getUserStateFromRequest($p . 'filter_type', 'filter_type', 0, 'int');

		$this->setState('filter_cats', $filter_cats);
		$this->setState('filter_type', $filter_type);

		$app->setUserState($p . 'filter_cats', $filter_cats);
		$app->setUserState($p . 'filter_type', $filter_type);

		/**
		 * Set locked filters into state
		 */
		if ($this->assocs_id)
		{
			$type_id     = $app->getUserStateFromRequest($p . 'type_id', 'type_id', 0, 'int');
			$item_lang   = $app->getUserStateFromRequest($p . 'item_lang', 'item_lang', '', 'string');
			$created_by  = $app->getUserStateFromRequest($p . 'created_by', 'created_by', 0, 'int');

			$assocanytrans = JFactory::getUser()->authorise('flexicontent.assocanytrans', 'com_flexicontent');

			// Limit to creator if creator not privileged
			if (!$assocanytrans && !$created_by)
			{
				$created_by = JFactory::getUser()->id;

				$this->setState('created_by', $created_by);
				$app->setUserState($p . 'created_by', $created_by);
			}

			// Limit to same type if creator not privileged
			if (!$assocanytrans && !$type_id)
			{
				$this->getTypeData($this->assocs_id, $type_id);

				$this->setState('type_id', $type_id);
				$app->setUserState($p . 'type_id', $type_id);
			}

			$filter_type   = $this->assocs_id && $type_id    ? $type_id    : $this->getState('filter_type');
			$filter_lang   = $this->assocs_id && $item_lang  ? $item_lang  : $this->getState('filter_lang');
			$filter_author = $this->assocs_id && $created_by ? $created_by : $this->getState('filter_author');

			$this->setState('filter_lang', $filter_lang);
			$this->setState('filter_type', $filter_type);
			$this->setState('filter_author', $filter_author);

			$app->setUserState($p . 'filter_lang', $filter_lang);
			$app->setUserState($p . 'filter_type', $filter_type);
			$app->setUserState($p . 'filter_author', $filter_author);
		}

		// Association KEY filter
		$filter_assockey = $fcform ? $jinput->get('filter_assockey', 0, 'cmd')  :  $app->getUserStateFromRequest( $p.'filter_assockey',  'filter_assockey',  0,  'cmd' );

		$this->setState('filter_assockey', $filter_assockey);
		$app->setUserState($p.'filter_assockey', $filter_assockey);
	}


	/**
	 * Method to get records data
	 *
	 * @return array
	 *
	 * @since	3.3.0
	 */
	function getData()
	{
		// Catch case of guest user submitting in frontend
		if (!JFactory::getUser()->id)
		{
			return $this->_data = array();
		}

		$lang_assocs = array();

		if ($this->assocs_id)
		{
			$lang_assocs = flexicontent_db::getLangAssocs(
				array($this->assocs_id),
				null
			);
		}

		$print_logging_info = $this->cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;

		// Lets load the records if it doesn't already exist
		if ($this->_data === null)
		{
			if (!empty($this->_ids))
			{
				$query_ids = $this->_ids;
			}
			else
			{
				// 1, get filtered, limited, ordered items
				$query = $this->_buildQuery();

				if ( $print_logging_info )  $start_microtime = microtime(true);
				$this->_db->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));
				$rows = $this->_db->loadObjectList();
				if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

				// 2, get current items total for pagination
				$this->_db->setQuery("SELECT FOUND_ROWS()");
				$this->_total = $this->_db->loadResult();
				// Check if something intefered with the query, and SQL_CALC_FOUND_ROWS did not work, do extra query
				if (count($rows) && !$this->_total) $this->_total = null;

				// 3, get item ids
				$query_ids = array();
				foreach ($rows as $row)
				{
					$query_ids[] = $row->id;
				}
			}

			// 4, get item data
			if (count($query_ids)) $query = $this->_buildQuery($query_ids);
			if ( $print_logging_info )  $start_microtime = microtime(true);
			$_data = array();
			if (count($query_ids))
			{
				$_data = $this->_db->setQuery($query)->loadObjectList('id');
			}
			if ( $print_logging_info ) @$fc_run_times['execute_sec_queries'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

			// 5, reorder items and get cat ids
			$this->_data = array();
			foreach($query_ids as $id)
			{
				$item = $_data[$id];
				$this->_data[] = $item;

				if (isset($lang_assocs[$this->assocs_id][$id]))
				{
					$item->is_current_association = 1;
				}
			}
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
		// Catch case of guest user submitting in frontend
		if (!JFactory::getUser()->id)
		{
			return $this->_total = 0;
		}

		// Lets load the records if it was not calculated already via using SQL_CALC_FOUND_ROWS + 'SELECT FOUND_ROWS()'
		if ($this->_total === null)
		{
			$this->_total = (int) $this->_getListCount($this->_buildQuery());
		}

		return $this->_total;
	}


	/**
	 * Method to build the query for the records
	 *
	 * @return  JDatabaseQuery   The DB Query object
	 *
	 * @since   3.3.0
	 */
	protected function _buildQuery($query_ids = false)
	{
		$filter_assockey = $this->getState('filter_assockey');

		$filter_meta = $this->getState('filter_meta');
		$scope       = $this->getState('scope');
		$search      = $this->getState('search');
		$use_tmp     = !$query_ids && !$filter_meta && (!$search || !in_array($scope, array('-1', '_desc_', '_meta_', 'a.metadesc', 'a.metakey')));
		$tbl         = $use_tmp ? '#__flexicontent_items_tmp' : '#__' . $this->records_dbtbl;

		if (!$query_ids)
		{
			$query = $this->_db->getQuery(true)
				->select('SQL_CALC_FOUND_ROWS a.id')
				->select('ua.name AS author')
				->select('t.name AS type_name')
				->from($tbl . ' AS a')
				->join('LEFT', '#__users as ua ON ua.id = a.' . $this->created_by_col)
				->join('LEFT', '#__flexicontent_items_ext AS ie ON ie.item_id = a.id')
				->join('LEFT', '#__flexicontent_types AS t ON t.id = ie.type_id')
				->join('LEFT', '#__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id')
				->join('LEFT', '#__categories AS c ON a.catid = c.id')
				->group('a.id');
			;

			/**
			 * Listing associated items
			 */
			if ($filter_assockey)
			{
				$query->join('inner', ' #__associations AS assoc ON a.id = assoc.id AND assoc.context = ' . $this->_db->quote('com_content.item'));
				$query->where('assoc.key = ' . $this->_db->quote($filter_assockey));
			}

			// Get the WHERE, HAVING and ORDER BY clauses for the query
			$this->_buildContentWhere($query);
			$this->_buildContentHaving($query);
			$this->_buildContentOrderBy($query);

			// Add always-active ("hard") filters
			$this->_buildHardFiltersWhere($query);
		}
		else
		{
			$query = $this->_db->getQuery(true)
				->select('a.*')
				->select('CASE WHEN level.title IS NULL THEN CONCAT_WS(\'\', \'deleted:\', a.access) ELSE level.title END AS access_level')
				->select('ua.name AS author')
				->select('t.name AS type_name')
				->from('#__' . $this->records_dbtbl . ' AS a')
				->join('LEFT', '#__viewlevels as level ON level.id = a.access')
				->join('LEFT', '#__users as ua ON ua.id = a.' . $this->created_by_col)
				->join('LEFT', '#__flexicontent_items_ext AS ie ON ie.item_id = a.id')
				->join('LEFT', '#__flexicontent_types AS t ON t.id = ie.type_id')
				->join('LEFT', '#__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id')
				->join('LEFT', '#__categories AS c ON a.catid = c.id')
				->where('a.id IN (' . implode(',', $query_ids) . ')')
				->group('a.id');
		}

		//echo nl2br(str_replace('#__', 'jos_', $query));
		//echo str_replace('#__', 'jos_', $query->__toString());

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
		// Inherited filters : filter_state, filter_access, filter_lang, filter_author, filter_id, search
		$where = parent::_buildContentWhere(false);

		// Various filters
		$filter_cats     = $this->getState('filter_cats');
		$filter_type     = $this->getState('filter_type');

		// Filter by assigned category
		if ($filter_cats)
		{
			$where[] = 'rel.catid = ' . $filter_cats;
		}

		// Filter by type
		if ($filter_type)
		{
			$where[] = 'ie.type_id = ' . (int) $filter_type;
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
		$textwhere = array();

		$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix

		if ($search)
		{
			$escaped_search = str_replace(' ', '%', $this->_db->escape(trim($search), true));
			$search_quoted  = $this->_db->Quote('%' . $escaped_search . '%', false);

			switch($scope)
			{
				case 'a.metadesc':
				case 'a.metakey':
				case 'a.' . $this->name_col:
					$textwhere[] = ' LOWER(' . $scope . ') LIKE ' . $search_quoted;
					break;

				case '_meta_':
					$textwhere[] = 'LOWER(a.metadesc) LIKE ' . $search_quoted;
					$textwhere[] = 'LOWER(a.metakey)  LIKE ' . $search_quoted;
					break;

				case '_desc_':
					$textwhere[] = 'LOWER(a.introtext) LIKE ' . $search_quoted;
					$textwhere[] = 'LOWER(a.fulltext)  LIKE ' . $search_quoted;
					break;

				case 'ie.search_index':
					$textwhere[] = ' MATCH (ie.search_index) AGAINST (' . $this->_db->Quote($search_prefix . $escaped_search . '*', false ).' IN BOOLEAN MODE)';
					break;
			}
		}

		return $textwhere;
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
		$config = $config ?: (object) array(
			'table'       => $this->records_dbtbl,
			'table_ext'   => 'flexicontent_items_ext',
			'ext_id'      => 'item_id',
			'context'     => 'com_content.item',
			'created'     => 'created',
			'modified'    => 'modified',
			'state'       => 'state',
			'catid'       => 'catid',
			'is_uptodate' =>'is_uptodate',
		);

		return parent::getLangAssocs($ids, $config);
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */


	/**
	 * Method to get types list
	 *
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ($type_ids = false, $check_perms = false, $published = true)
	{
		return flexicontent_html::getTypesList($type_ids, $check_perms, $published);
	}


	/**
	 * Method to get typedata for specific item_id or by type_id
	 *
	 * @return array
	 * @since 1.5
	 */
	function getTypeData($item_id, &$type_id = false)
	{
		if ( !$item_id && !$type_id )  return false;

		static $item_ids_type = array();
		if (!$type_id)
		{
			if ( !isset($item_ids_type[$item_id]) )
			{
				$this->_db->setQuery('SELECT type_id FROM #__flexicontent_items_ext WHERE item_id = '.$item_id);
				$item_ids_type[$item_id] = $this->_db->loadResult();
			}
			$type_id = $item_ids_type[$item_id];
		}
		if ( !$type_id )  return false;

		$type_data = $this->getTypeslist();
		return isset($type_data[$type_id])  ?  $type_data[$type_id]  :  false;
	}
}
