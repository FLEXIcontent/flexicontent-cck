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
 * FLEXIcontent Component Tags Model
 *
 */
class FlexicontentModelTags extends FCModelAdminList
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
	 * Supported Features Flags
	 */
	const canDelRelated = true;

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
	 * Joomla 3.x Tags helper object
	 *
	 * @var int
	 */
	var $tagsHelper = null;


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

		// Initialize Tags helper object
		$this->tagsHelper = new \JHelperTags;
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
		if (count($cid))
		{
			$cid = ArrayHelper::toInteger($cid);
			$cid_list = implode(',', $cid);

			// Delete also tag - item relations
			$query = $this->_db->getQuery(true)
				->delete('#__flexicontent_tags_item_relations')
				->where('tid IN (' . $cid_list . ')')
			;
			$this->_db->setQuery($query)->execute();
		}

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
	 * @return	void
	 *
	 * @since   3.3.0
	 */
	protected function _copyRelatedData($ids_map)
	{
		
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
				$query = 'SELECT DISTINCT tid'
					. ' FROM #__flexicontent_tags_item_relations'
					. ' WHERE tid IN (' . implode(',', $cid) . ')'
				;

				$cid_wassocs = $this->_db->setQuery($query)->loadColumn();
				break;

			// Trash, Unpublish
			case -2:
			case 0:
				break;
		}

		return $cid_wassocs;
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */


	/**
	 * Method to import a list of tags
	 *
	 * @params	string	the list of tags to import
	 *
	 * @return	array	the import logs
	 *
	 * @since	1.5
	 */
	public function importList($tags)
	{
		if (!$tags)
		{
			return;
		}

		// Initialize the logs counters
		$logs            = array();
		$logs['error']   = 0;
		$logs['success'] = 0;

		$tags = explode("\n", $tags);

		foreach ($tags as $tag)
		{
			$row            = $this->getTable($this->records_jtable, '');
			$row->name      = $tag;
			$row->published = 1;
			if (!$row->check())
			{
				$logs['error'] ++;
			}
			else
			{
				$row->store();
				$logs['success'] ++;
			}
		}

		return $logs;
	}


	/**
	 * Method to get ids of all files
	 *
	 * @return	boolean	integer array on success
	 *
	 * @since	1.0
	 */
	public function getNotMappedTagIds(&$total = null, $start = 0, $limit = 5000)
	{
		$query = 'SELECT SQL_CALC_FOUND_ROWS ft.id '
			. ' FROM #__flexicontent_tags AS ft'
			. ' LEFT JOIN #__tags AS jt ON ft.jtag_id = jt.id'
			. ' WHERE ft.jtag_id = 0 OR jt.id IS NULL'
			. ($limit ? ' LIMIT ' . (int) $start . ', ' . (int) $limit : '')
		;
		$tag_ids = $this->_db->setQuery($query)->loadColumn();

		// Get items total
		$total = $this->_db->setQuery('SELECT FOUND_ROWS()')->loadResult();

		return $tag_ids;
	}


	/**
	 * Create any new tags by looking for #new# in the strings
	 *
	 * @param   array  $tags      Tags text array from the field
	 * @param   array  $checkACL  Flag to indicate if tag creation ACL should be used
	 *
	 * @return  mixed   If successful, an array of tag data, indexed via the given tag titles
	 *
	 * @since   3.3.0
	 */
	public function createFindJoomlaTags($tags, $checkACL = true, $indexCol = null)
	{
		return flexicontent_db::createFindJoomlaTags($tags, $checkACL, $indexCol);
	}


	/**
	 * Create any new tags by looking for #new# in the strings
	 *
	 * @param   array  $tags  Tags text array from the field
	 *
	 * @return  mixed   If successful, metadata with new tag titles replaced by tag ids. Otherwise false.
	 *
	 * @since   3.3.0
	 */
	public function addTagMapping($ucmId, TableInterface $table, $tags = array())
	{
		$key    = $table->getKeyName();
		$item   = $table->$key;
		$typeId = $this->tagsHelper->getTypeId($this->tagsHelper->typeAlias);

		// Insert the new tag maps
		if (strpos('#', implode(',', $tags)) === false)
		{
			$tags = $this->createFindJoomlaTags($tags);
		}

		// Prevent saving duplicate tags
		$tags = array_unique($tags);

		$query = $this->_db->getQuery(true);
		$query->insert('#__contentitem_tag_map');
		$query->columns(
			array(
				$this->_db->quoteName('type_alias'),
				$this->_db->quoteName('core_content_id'),
				$this->_db->quoteName('content_item_id'),
				$this->_db->quoteName('tag_id'),
				$this->_db->quoteName('tag_date'),
				$this->_db->quoteName('type_id'),
			)
		);

		foreach ($tags as $tag)
		{
			$query->values(
				$this->_db->quote($this->tagsHelper->typeAlias)
				. ', ' . (int) $ucmId
				. ', ' . (int) $item
				. ', ' . $this->_db->quote($tag)
				. ', ' . $query->currentTimestamp()
				. ', ' . (int) $typeId
			);
		}

		return (boolean) $this->_db->setQuery($query)->execute();
	}


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


	/**
	 * START OF MODEL LEGACY METHODS
	 */

}
