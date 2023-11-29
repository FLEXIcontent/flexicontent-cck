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
 * FLEXIcontent Component Search Model
 *
 */
class FLEXIcontentModelSearch extends FCModelAdminList
{
	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl  = null;

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = null;

	/**
	 * Column names
	 */
	var $state_col      = null;
	var $name_col       = null;
	var $parent_col     = null;
	var $created_by_col = null;

	/**
	 * Search and ordering columns
	 */
	var $search_cols = array(
	);
	var $default_order     = 'a.title';
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
	 * Search areas
	 *
	 * @var array
	 */
	var $_areas = null;

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
		$filter_type      = $fcform ? $jinput->get('filter_type', 0, 'int') : $app->getUserStateFromRequest($p . 'filter_type',  'filter_type',   0, 'int' );
		$filter_itemlang  = $fcform ? $jinput->get('filter_itemlang', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_itemlang', 'filter_itemlang', '', 'cmd');
		$filter_fieldtype = $fcform ? $jinput->get('filter_fieldtype', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_fieldtype', 'filter_fieldtype', '', 'cmd');
		$search_itemtitle = $fcform ? $jinput->get('search_itemtitle', '', 'string') : $app->getUserStateFromRequest($p . 'search_itemtitle', 'search_itemtitle', '', 'string' );
		$search_itemid    = $fcform ? $jinput->get('search_itemid', 0, 'int') : $app->getUserStateFromRequest( $p.'search_itemid',    'search_itemid',     0, 'int' );

		$this->setState('filter_type', $filter_type);
		$this->setState('filter_itemlang', $filter_itemlang);
		$this->setState('filter_fieldtype', $filter_fieldtype);
		$this->setState('search_itemtitle', $search_itemtitle);
		$this->setState('search_itemid', $search_itemid);

		$app->setUserState($p.'filter_type', $filter_type);
		$app->setUserState($p.'filter_itemlang', $filter_itemlang);
		$app->setUserState($p.'filter_fieldtype', $filter_fieldtype);
		$app->setUserState($p.'search_itemtitle', $search_itemtitle);
		$app->setUserState($p.'search_itemid', $search_itemid);


		// Type of search index being listed
		$indexer = $app->getUserStateFromRequest( $p.'indexer', 'indexer', '', 'cmd' );
		if ($indexer)
		{
			$filter_indextype = $indexer;
			$app->setUserState('indexer', '');
		}
		else
		{
			$filter_indextype = $fcform ? $jinput->get('filter_indextype',  'advanced', 'cmd')  :  $app->getUserStateFromRequest( $p.'filter_indextype', 'filter_indextype', 'advanced', 'cmd' );
		}
		$this->setState('filter_indextype', $filter_indextype);
		$app->setUserState($p.'filter_indextype', $filter_indextype);
		$isADV = $filter_indextype=='advanced';



		/**
		 * Override default ordering code
		 */

		$default_order     = $this->cparams->get('search_manager_order', 'a.title');  // Parameter does not exist
		$default_order_dir = $this->cparams->get('search_manager_order_dir', 'ASC');  // Parameter does not exist

		$filter_order      = $fcform ? $jinput->get('filter_order',     $default_order,      'cmd')  :  $app->getUserStateFromRequest( $p.'filter_order',     'filter_order',     $default_order,      'cmd' );
		$filter_order_Dir  = $fcform ? $jinput->get('filter_order_Dir', $default_order_dir, 'word')  :  $app->getUserStateFromRequest( $p.'filter_order_Dir', 'filter_order_Dir', $default_order_dir, 'word' );

		if (!$filter_order)
		{
			$filter_order = $default_order;
		}

		if (!$filter_order_Dir)
		{
			$filter_order_Dir = $default_order_dir;
		}

		// Clear invalid order when search if switch search is 
		if (!$isADV && !in_array($this->getState('filter_order'), array('a.id', 'a.title', 'ext.search_index')))
		{
			$this->setState('filter_order', 'a.title');
			$app->setUserState($p.'filter_order', 'a.title');
		}
	}


	function getData()
	{
		if (!empty($this->_data)) return $this->_data;

		$cparams = JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;


		// 1, get filtered, limited, ordered items
		$query = $this->_buildQuery();

		if ( $print_logging_info )  $start_microtime = microtime(true);
		$this->_db->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));
		$rows = $this->_db->loadObjectList();
		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// 2, get current items total for pagination
		$this->_db->setQuery("SELECT FOUND_ROWS()");
		$this->_total = $this->_db->loadResult();

		$filter_indextype = $this->getState('filter_indextype');
		$isADV = $filter_indextype=='advanced';


		// 3, get item ids
		$query_ids = array();
		foreach ($rows as $row) {
			$query_ids[] = $isADV ? $row->sid : $row->item_id;
		}


		// 4, get item data
		if (count($query_ids)) $query = $this->_buildQuery($query_ids);
		if ( $print_logging_info )  $start_microtime = microtime(true);
		$_data = array();
		if (count($query_ids)) {
			$this->_db->setQuery($query);
			$_data = $this->_db->loadObjectList($isADV ? 'sid' : 'item_id');
		}
		if ( $print_logging_info ) @$fc_run_times['execute_sec_queries'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// 5, reorder items and get cat ids
		$this->_data = array();
		foreach($query_ids as $query_id) {
			$this->_data[] = $_data[$query_id];
		}


		return $this->_data;
	}


	/**
	 * Method to get the number of relevant search index records
	 *
	 * @access	public
	 * @return	mixed	False on failure, integer on success.
	 * @since	1.0
	 */
	public function getCount() {
		// Lets load the Items if it doesn't already exist
		if ( $this->_total === null ) {
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}
		return $this->_total;
	}


	/**
	 * Method to build the query for the retrieval of search index records
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildQuery($query_ids = false)
	{
		if (!$query_ids)
		{
			$where		= $this->_buildWhere();
			$orderby	= $this->_buildOrderBy();

			$filter_order     = $this->getState('filter_order');

			$filter_itemlang = $this->getState('filter_itemlang');
			$filter_fieldtype = $this->getState('filter_fieldtype');
			$filter_state = $this->getState('filter_state');
			$filter_type  = $this->getState('filter_type');

			$search_itemtitle = $this->getState('search_itemtitle');
			$search_itemid    = $this->getState('search_itemid');
		}

		$filter_indextype = $this->getState('filter_indextype');
		$isADV = $filter_indextype=='advanced';

		$query = !$query_ids
			? 'SELECT SQL_CALC_FOUND_ROWS ' . ($isADV ? 'ai.sid' : 'ext.item_id')
			: ($isADV
				? 'SELECT f.label, f.name, f.field_type, ai.*, a.title, a.id, a.language, 0 AS checked_out '
				: 'SELECT ext.*, a.title, a.id, a.language, 0 AS checked_out '
			);
		$query .= $isADV ? ' FROM #__flexicontent_advsearch_index as ai' : ' FROM #__flexicontent_items_ext as ext';

		if ($query_ids)
		{
			$query .= ''
				.' JOIN #__flexicontent_items_tmp as a ON ' .($isADV ? 'ai' : 'ext'). '.item_id=a.id'
				.(!$isADV ? '' : ''
					.' JOIN #__flexicontent_items_ext as ext ON ext.item_id=a.id'
					.' JOIN #__flexicontent_fields_type_relations as rel ON rel.field_id=ai.field_id AND rel.type_id=ext.type_id'
					.' JOIN #__flexicontent_fields as f ON ai.field_id=f.id'
				)
				;
		}
		else
		{
			if ($isADV && (in_array($filter_order, array('f.label','f.name','f.field_type')) || $filter_fieldtype))
			{
				$query .= ''
					.' JOIN #__flexicontent_items_tmp as a ON ai.item_id=a.id'
					.' JOIN #__flexicontent_items_ext as ext ON ext.item_id=a.id'
					.' JOIN #__flexicontent_fields_type_relations as rel ON rel.field_id=ai.field_id AND rel.type_id=ext.type_id'
					.' JOIN #__flexicontent_fields as f ON ai.field_id=f.id'
					;
			}
			else
			{
				if ($filter_order == 'a.id' || $filter_order == 'a.title' || $filter_state || $filter_type || $filter_itemlang || $search_itemtitle || $search_itemid)
					$query .= ' JOIN #__flexicontent_items_tmp as a ON ' .($isADV ? 'ai' : 'ext'). '.item_id=a.id';
				if ($isADV && $filter_type)
					$query .= ' JOIN #__flexicontent_items_ext as ext ON ext.item_id=a.id';
			}
		}

		$query .= !$query_ids ?
			$where.$orderby :
			($isADV ?
				' WHERE ai.sid IN ('. implode(',', $query_ids) .')' :
				' WHERE ext.item_id IN ('. implode(',', $query_ids) .')'
			);

		return $query;
	}


	/**
	 * Method to build the orderby clause of the query for the search index
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildOrderBy()
	{
		$filter_order     = $this->getState('filter_order');
		$filter_order_Dir = $this->getState('filter_order_Dir');

		$orderby = $filter_order.' '.$filter_order_Dir;
		$orderby = ' ORDER BY '.$orderby;

		return $orderby;
	}


	/**
	 * Method to build the where clause of the query for the fields
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildWhere()
	{
		static $where;

		if (isset($where))
		{
			return $where;
		}

		$filter_state	= $this->getState('filter_state');
		$filter_type	= $this->getState('filter_type');
		$filter_itemlang  = $this->getState('filter_itemlang');
		$filter_fieldtype = $this->getState('filter_fieldtype');
		$search_itemtitle	= $this->getState('search_itemtitle');
		$search_itemid		= $this->getState('search_itemid');

		$filter_indextype = $this->getState('filter_indextype');
		$isADV = $filter_indextype=='advanced';

		$search  = $this->getState('search');
		$search  = StringHelper::trim(StringHelper::strtolower($search));

		$where = array();

		if ($isADV && $filter_fieldtype)
		{
			if ($filter_fieldtype === 'C' )
			{
				$where[] = 'f.iscore = 1';
			}
			elseif ($filter_fieldtype === 'NC')
			{
				$where[] = 'f.iscore = 0';
			}
			else
			{
				$where[] = 'f.field_type = ' . $this->_db->quote($filter_fieldtype);
			}
		}

		if ($filter_itemlang)
		{
			$where[] = 'a.language = ' . $this->_db->quote($filter_itemlang);
		}

		if ($filter_state)
		{
			if ($filter_state === 'ALL_P')
			{
				$where[] = 'a.state IN (1, -5)';
			}
			elseif ($filter_state === 'ALL_U')
			{
				$where[] = 'a.state NOT IN (1, -5)';
			}
		}

		if ($filter_type)
		{
			$where[] = 'ext.type_id = ' . (int) $filter_type;
		}

		if ($search)
		{
			$escaped_search = str_replace(' ', '%', $this->_db->escape(trim($search), true));
			$search_quoted  = $this->_db->Quote('%' . $escaped_search . '%', false);

			$where[] = ' LOWER(' . ($isADV ? 'ai' : 'ext') . '.search_index) LIKE ' . $search_quoted;
		}

		if ($search_itemtitle)
		{
			$search_itemtitle_escaped = $this->_db->escape($search_itemtitle, true);
			$where[] = ' LOWER(a.title) LIKE ' . $this->_db->Quote('%' . $search_itemtitle_escaped . '%', false);
		}

		if ($search_itemid)
		{
			$where[] = ' a.id= ' . (int) $search_itemid;
		}

		$where = count($where) ? implode(' AND ', $where) : '';
		$where = trim($where) ? ' WHERE ' . $where : '';

		return $where;
	}


	/**
	 * Method to get types list
	 *
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ( $type_ids=false, $check_perms = false, $published=true )
	{
		return flexicontent_html::getTypesList( $type_ids, $check_perms, $published);
	}


	/**
	 * START OF MODEL LEGACY METHODS
	 */


	/**
	 * Method to empty search indexes
	 *
	 * @return	null
	 *
	 * @since	3.0
	 */
	public function purge($del_fieldids = null)
	{
		$app      = JFactory::getApplication();
		$dbprefix = $app->getCfg('dbprefix');
		$dbname   = $app->getCfg('db');

		/**
		 * Empty Common text-search index
		 */

		if (empty($del_fieldids))
		{
			$query = "TRUNCATE TABLE `#__flexicontent_advsearch_index`;";
		}
		else
		{
			$del_fieldids_list = implode( ',' , $del_fieldids);
			$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id IN (". $del_fieldids_list. ")";
		}

		$this->_db->setQuery($query)->execute();


		/**
		 * Empty per field TABLES
		 */

		$filterables = FlexicontentFields::getSearchFields('id', $indexer='advanced', null, null, $_load_params=true, 0, $search_type='filter');
		$filterables = array_keys($filterables);
		$filterables = array_flip($filterables);

		$tbl_prefix = $dbprefix . 'flexicontent_advsearch_index_field_';
		$query = 'SELECT TABLE_NAME
			FROM INFORMATION_SCHEMA.TABLES
			WHERE TABLE_SCHEMA = ' . $this->_db->Quote($dbname)
				. ' AND TABLE_NAME LIKE ' . $this->_db->Quote($tbl_prefix . '%');
		$tbl_names = $this->_db->setQuery($query)->loadColumn();

		foreach($tbl_names as $tbl_name)
		{
			$_field_id = str_replace($tbl_prefix, '', $tbl_name);

			// Drop the table of no longer filterable field
			if (!isset($filterables[$_field_id]))
			{
				$this->_db->setQuery('DROP TABLE IF EXISTS ' . $tbl_name);
			}

			// Truncate (or drop/recreate) tables of fields that are still filterable. Any dropped but needed tables will be recreated below
			elseif (empty($del_fieldids) || isset($del_fieldids[$_field_id]))
			{
				$this->_db->setQuery('TRUNCATE TABLE ' . $tbl_name);
			}

			$this->_db->execute();
		}

		// VERIFY all search tables exist
		$tbl_names_flipped = array_flip($tbl_names);

		foreach ($filterables as $_field_id => $_ignored)
		{
			$tbl_name = $dbprefix . 'flexicontent_advsearch_index_field_'.$_field_id;
			$query = '
			CREATE TABLE IF NOT EXISTS ' . $this->_db->quoteName($tbl_name) . ' (
			  `sid` int(11) NOT NULL auto_increment,
			  `field_id` int(11) NOT NULL,
			  `item_id` int(11) NOT NULL,
			  `extraid` int(11) NOT NULL,
			  `search_index` longtext NOT NULL,
			  `value_id` varchar(255) NULL,
			  PRIMARY KEY (`field_id`,`item_id`,`extraid`),
			  KEY `sid` (`sid`),
			  KEY `field_id` (`field_id`),
			  KEY `item_id` (`item_id`),
			  FULLTEXT `search_index` (`search_index`),
			  KEY `value_id` (`value_id`)
			) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`
			';
			$this->_db->setQuery($query)->execute();
		}
	}
}
