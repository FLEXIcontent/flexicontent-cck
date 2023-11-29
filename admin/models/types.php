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
 * FLEXIcontent Component Types Model
 *
 */
class FlexicontentModelTypes extends FCModelAdminList
{
	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl  = 'flexicontent_types';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_types';

	/**
	 * Column names
	 */
	var $state_col      = 'published';
	var $name_col       = 'name';
	var $parent_col     = null;

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
		 * Inherited filters : filter_state, filter_access, search
		 */

		// Manage view permission
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanTypes;
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
				'(SELECT COUNT(*) FROM #__flexicontent_items_ext AS i WHERE i.type_id = a.id) AS iassigned, ' .
				'COUNT(rel.type_id) AS fassigned, a.attribs AS config'
			)
			->leftJoin('#__flexicontent_fields_type_relations AS rel ON a.id = rel.type_id')
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
	 * @since   3.3.0
	 */
	protected function _buildContentWhere($q = false)
	{
		// Inherited filters : filter_state, filter_access, search
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

			// Delete also field - type relations
			$query = $this->_db->getQuery(true)
				->delete('#__flexicontent_fields_type_relations')
				->where('type_id IN (' . $cid_list . ')')
			;
			$this->_db->setQuery($query)->execute();

			/**
			 * This is incomplete as it does not delete content items,
			 * so this should never be called if content items exist
			 */
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
		foreach ($ids_map as $id => $new_id)
		{
			$query = 'SELECT * FROM #__flexicontent_fields_type_relations'
				. ' WHERE type_id = ' . (int) $id
			;

			$rels = $this->_db->setQuery($query)->loadObjectList();

			foreach ($rels as $rel)
			{
				$query = 'INSERT INTO #__flexicontent_fields_type_relations (`field_id`, `type_id`, `ordering`) '
					. ' VALUES(' . (int) $rel->field_id . ',' . (int) $new_id . ',' . (int) $rel->ordering . ')'
				;
				$this->_db->setQuery($query)->execute();
			}
		}
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
			// Trash (currently not allowed if item assignments exist)
			case -2:
				$query = 'SELECT DISTINCT type_id'
					. ' FROM #__flexicontent_items_ext'
					. ' WHERE type_id IN (' . implode(',', $cid) . ')'
				;

				$cid_wassocs = $this->_db->setQuery($query)->loadColumn();
				break;

			// Unpublish (=Disable)
			case 0:
				break;
		}

		return $cid_wassocs;
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */


	/**
	 * START OF MODEL LEGACY METHODS
	 */

}
