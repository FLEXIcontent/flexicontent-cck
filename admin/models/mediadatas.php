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
 * FLEXIcontent Component Mediadatas Model
 *
 */
class FlexicontentModelMediadatas extends FCModelAdminList
{
	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl  = 'flexicontent_mediadatas';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_mediadatas';

	/**
	 * Column names
	 */
	var $state_col      = 'state';
	var $name_col       = 'id';
	var $parent_col     = null;
	var $created_by_col = 'user_id';

	/**
	 * (Default) Behaviour Flags
	 */
	protected $listViaAccess = false;
	protected $copyRelations = true;

	/**
	 * Search and ordering columns
	 */
	var $search_cols = array(
		'FLEXI_ID' => 'id',
	);
	var $default_order     = 'a.id';
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
		$filter_media_type = $fcform ? $jinput->get('filter_media_type', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_media_type', 'filter_media_type', '', 'cmd');
		$this->setState('filter_media_type', $filter_media_type);
		$app->setUserState($p . 'filter_media_type', $filter_media_type);


		// Manage view permission
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanMediadatas;
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
			->select(array(
				'f.filename AS title',
				'ua.name AS uploader'
			))
			->leftJoin('#__flexicontent_files AS f ON (f.id = a.file_id)')
			->leftJoin('#__users AS ua ON ua.id = f.uploaded_by');
		;

		//echo $query;
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

		// Various filters
		$filter_media_type = $this->getState('filter_media_type');


		// Filter by media_type flag
		if (is_numeric($filter_media_type))
		{
			$where[] = 'a.media_type = ' . (int) $filter_media_type;
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
	 * Method to copy records
	 *
	 * @param		array			$cid             array of record ids to copy
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
	 * Method to get the associated content rows for the given mediadatas
	 *
	 * @param		array   $mediadatas  Mediadatas array
	 *
	 * @return	array   An Array of content items corresponding to the given mediadatas
	 *
	 * @since   3.3.0
	 */
	public function getContentRows($mediadatas)
	{
		if (!$mediadatas)
		{
			return array();
		}

		$mediadata_ids = array();

		foreach ($mediadatas as $mediadata)
		{
			$mediadata_ids[] = $mediadata->id;
		}

		$mediadata_ids = ArrayHelper::toInteger($mediadata_ids);
		$mediadata_ids_list = implode(',', $mediadata_ids);

		$query = $this->_db->getQuery(true)
			->select('i.*')
			->select('CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug')
			->from('#__content i')
			->leftJoin('#__categories AS c ON i.catid = c.id')
			->where('i.id IN (' . $mediadata_ids_list . ')');

		return $this->_db->setQuery($query)->loadObjectlist('id');
	}


	/**
	 * START OF MODEL LEGACY METHODS
	 */

}
