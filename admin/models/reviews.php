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
 * FLEXIcontent Component Reviews Model
 *
 */
class FlexicontentModelReviews extends FCModelAdminList
{
	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl  = 'flexicontent_reviews';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_reviews';

	/**
	 * Column names
	 */
	var $state_col      = 'state';
	var $name_col       = 'title';
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
		'FLEXI_REVIEW_SEARCH_TITLE' => 'title',
		'FLEXI_REVIEW_SEARCH_TEXT'  => 'text',
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
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	public function __construct($config = array())
	{
		$app    = \Joomla\CMS\Factory::getApplication();
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
		$filter_approved = $fcform ? $jinput->get('filter_approved', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_approved', 'filter_approved', '', 'cmd');
		$this->setState('filter_approved', $filter_approved);
		$app->setUserState($p . 'filter_approved', $filter_approved);

		// Reviews with text, or only with rating (voting with the stars): 'text' | 'rating'
		$filter_hastext = $fcform ? $jinput->get('filter_hastext', '', 'cmd') : $app->getUserStateFromRequest($p . 'filter_hastext', 'filter_hastext', '', 'cmd');
		$filter_hastext = in_array($filter_hastext, array('text', 'rating'), true) ? $filter_hastext : '';
		$this->setState('filter_hastext', $filter_hastext);
		$app->setUserState($p . 'filter_hastext', $filter_hastext);

		// Filter by reviewed items and by reviewers (arrays of ids, reviewer id 0 means guests)
		foreach (array('filter_item', 'filter_user') as $filter_name)
		{
			$filter_value = $fcform
				? $jinput->get($filter_name, array(), 'array')
				: $app->getUserStateFromRequest($p . $filter_name, $filter_name, array(), 'array');

			$filter_value = array_values(array_unique(array_map('intval', array_filter((array) $filter_value, 'is_numeric'))));

			// Reviewed items must have a valid id
			if ($filter_name === 'filter_item')
			{
				$filter_value = array_values(array_filter($filter_value));
			}

			$this->setState($filter_name, $filter_value);
			$app->setUserState($p . $filter_name, $filter_value);
		}

		// Text search scope: search in all columns (review title and text) by default
		if (!strlen((string) $this->getState('scope')))
		{
			$this->setState('scope', '-1');
			$app->setUserState($p . 'scope', '-1');
		}


		// Manage view permission
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanReviews;
	}


	/**
	 * Method to build the query for the records
	 *
	 * @return  \Joomla\Data\DataObjectbaseQuery   The DB Query object
	 *
	 * @since   3.3.0
	 */
	protected function getListQuery()
	{
		// Create a query with all its clauses: WHERE, HAVING and ORDER BY, etc
		$query = parent::getListQuery();

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
		$filter_approved = $this->getState('filter_approved');


		// Filter by approved flag
		if (is_numeric($filter_approved))
		{
			$where[] = 'a.approved = ' . (int) $filter_approved;
		}

		// Filter by review text: reviews with text, or only with rating (no title and no text)
		$filter_hastext = $this->getState('filter_hastext');

		if ($filter_hastext === 'text')
		{
			$where[] = '(CHAR_LENGTH(TRIM(a.text)) > 0 OR CHAR_LENGTH(TRIM(a.title)) > 0)';
		}
		elseif ($filter_hastext === 'rating')
		{
			$where[] = '(CHAR_LENGTH(TRIM(a.text)) = 0 AND CHAR_LENGTH(TRIM(a.title)) = 0)';
		}

		// Filter by reviewed items
		$filter_item = (array) $this->getState('filter_item');

		if ($filter_item)
		{
			$where[] = 'a.type = ' . $this->_db->Quote('item');
			$where[] = 'a.content_id IN (' . implode(',', ArrayHelper::toInteger($filter_item)) . ')';
		}

		// Filter by reviewers (0 = guests)
		$filter_user = (array) $this->getState('filter_user');

		if ($filter_user)
		{
			$where[] = 'a.user_id IN (' . implode(',', ArrayHelper::toInteger($filter_user)) . ')';
		}

		if ($q instanceof JDatabaseQuery || (FLEXI_J40GE && $q instanceof \Joomla\Database\DatabaseQuery))
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
			$table->alias = \Joomla\CMS\Filter\OutputFilter::stringURLSafe($table->$name);

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
	 * Method to get the associated content rows for the given reviews
	 *
	 * @param		array   $reviews  Reviews array
	 *
	 * @return	array   An Array of content items corresponding to the given reviews
	 *
	 * @since   3.3.0
	 */
	public function getContentRows($reviews)
	{
		if (!$reviews)
		{
			return array();
		}

		// The reviewed content items (reviews of other types, e.g. categories, are not supported yet)
		$content_ids = array();

		foreach ($reviews as $review)
		{
			if ($review->type === 'item' && $review->content_id)
			{
				$content_ids[] = (int) $review->content_id;
			}
		}

		if (!$content_ids)
		{
			return array();
		}

		$review_ids_list = implode(',', array_unique(ArrayHelper::toInteger($content_ids)));

		$query = $this->_db->getQuery(true)
			->select('i.*')
			->select('ie.type_id')
			->select('CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug')
			->from('#__content i')
			->leftJoin('#__flexicontent_items_ext AS ie ON ie.item_id = i.id')
			->leftJoin('#__categories AS c ON i.catid = c.id')
			->where('i.id IN (' . $review_ids_list . ')');

		return $this->_db->setQuery($query)->loadObjectlist('id');
	}


	/**
	 * Method to get the number of reviews of the given reviews' items, as shown in the list
	 * (same rule as the default list: trashed and archived reviews are not counted)
	 *
	 * @param		array   $reviews  Reviews array
	 *
	 * @return	array   Array of content_id => number of reviews
	 *
	 * @since   3.3.0
	 */
	public function getReviewCounts($reviews)
	{
		$content_ids = array();

		foreach ($reviews as $review)
		{
			if ($review->type === 'item' && $review->content_id)
			{
				$content_ids[] = (int) $review->content_id;
			}
		}

		if (!$content_ids)
		{
			return array();
		}

		$query = $this->_db->getQuery(true)
			->select('r.content_id, COUNT(*) AS total')
			->from('#__flexicontent_reviews AS r')
			->where('r.type = ' . $this->_db->Quote('item'))
			->where('r.content_id IN (' . implode(',', array_unique($content_ids)) . ')')
			->where('r.state NOT IN (-2, 2)')
			->group('r.content_id');

		return $this->_db->setQuery($query)->loadAssocList('content_id', 'total');
	}


	/**
	 * Method to get the options of the item filter: only items that have reviews
	 *
	 * @return	array   Array of objects (id, title)
	 *
	 * @since   3.3.0
	 */
	public function getReviewedItems()
	{
		$query = $this->_db->getQuery(true)
			->select('c.id, c.title')
			->from('#__content AS c')
			->where('c.id IN (SELECT r.content_id FROM #__flexicontent_reviews AS r WHERE r.type = ' . $this->_db->Quote('item') . ')')
			->order('c.title ASC');

		return $this->_db->setQuery($query)->loadObjectList();
	}


	/**
	 * Method to get the options of the user filter: only users that have reviews
	 *
	 * @return	array   Array of objects (id, name), guests have id 0
	 *
	 * @since   3.3.0
	 */
	public function getReviewers()
	{
		$query = $this->_db->getQuery(true)
			->select('r.user_id AS id, MAX(u.name) AS name')
			->from('#__flexicontent_reviews AS r')
			->leftJoin('#__users AS u ON u.id = r.user_id')
			->group('r.user_id')
			->order('name ASC');

		return $this->_db->setQuery($query)->loadObjectList();
	}


	/**
	 * START OF MODEL LEGACY METHODS
	 */

}
