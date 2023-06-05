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
require_once('base/traitnestable.php');

/**
 * FLEXIcontent Component Categories Model
 *
 */
class FlexicontentModelCategories extends FCModelAdminList
{

	use FCModelTraitNestableRecord;

	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl = 'categories';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_categories';

	/**
	 * Column names
	 */
	var $state_col      = 'published';
	var $name_col       = 'title';
	var $parent_col     = 'parent_id';
	var $created_by_col = 'created_user_id';

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
	var $default_order     = 'a.lft';
	var $default_order_dir = 'ASC';

	/**
	 * List filters that are always applied
	 */
	var $hard_filters = array('a.extension' => FLEXI_CAT_EXTENSION);

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
		//$this->view_id = $view . '_' . $layout;

		// Call parent after setting ... $this->view_id
		parent::__construct($config);

		$p = $this->ovid;


		/**
		 * View's Filters
		 * Inherited filters : filter_state, filter_access, filter_lang, filter_author, filter_id, search
		 */

		// Various filters
		$filter_cats     = $fcform ? $jinput->get('filter_cats', 0, 'int') : $app->getUserStateFromRequest($p . 'filter_cats', 'filter_cats', 0, 'int');
		$filter_level    = $fcform ? $jinput->get('filter_level', 0, 'int') : $app->getUserStateFromRequest($p . 'filter_level', 'filter_level', 0, 'int');

		$this->setState('filter_cats', $filter_cats);
		$this->setState('filter_level', $filter_level);

		$app->setUserState($p . 'filter_cats', $filter_cats);
		$app->setUserState($p . 'filter_level', $filter_level);

		// Association KEY filter
		$filter_assockey = $fcform ? $jinput->get('filter_assockey', 0, 'cmd')  :  $app->getUserStateFromRequest( $p.'filter_assockey',  'filter_assockey',  0,  'cmd' );

		$this->setState('filter_assockey', $filter_assockey);
		$app->setUserState($p.'filter_assockey', $filter_assockey);

		// Manage view permission
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanCats;
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
		;

		// Listing associated items
		$filter_assockey = $this->getState('filter_assockey');

		if ($filter_assockey)
		{
			$query->innerJoin('#__associations AS assoc ON a.id = assoc.id AND assoc.context = ' . $this->_db->quote('com_categories.item'));
		}

		/**
		 * Because of multi-multi category-item relation it is faster to calculate ITEM COUNT with a seperate query
		 * if it was single mapping e.g. like it is 'item' TO 'content type' or 'item' TO 'creator' we could use a subquery
		 * the more categories are listed (query LIMIT) the bigger the performance difference ...
		 */
		//$query->select('(COUNT(*) FROM #__flexicontent_cats_item_relations AS rel WHERE rel.catid = a.id) AS nrassigned');

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
		$filter_level    = $this->getState('filter_level');

		// Limit category list to those contain in the subtree of the choosen category
		if ($filter_cats)
		{
			$where[] = 'a.id IN (SELECT cat.id FROM #__categories AS cat JOIN #__categories AS parent ON cat.lft BETWEEN parent.lft AND parent.rgt WHERE parent.id = ' . (int) $filter_cats . ')';
		}

		// Limit category list to those containing CONTENT (joomla articles)
		else
		{
			$where[] = '(a.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND a.rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY) . ')';
		}

		// Listing associated items
		$filter_assockey = $this->getState('filter_assockey');

		if ($filter_assockey)
		{
			$where[] = 'assoc.key = ' . $this->_db->quote($filter_assockey);
		}

		// Filter by depth level
		if ($filter_level)
		{
			$where[] = 'a.level <= ' . (int) $filter_level;
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
	 * Method to find which records are not authorized
	 *
	 * @param		array        $cid      Array of record ids to check
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
	 * @param		array        $cid      Array of record ids to check
	 * @param		int|string   $action   Either an ACL rule action, or a new state
	 *
	 * @return	array     The records having assignments
	 *
	 * @since   3.3.0
	 */
	public function filterByAssignments($cid = array(), $action = -2)
	{
		global $globalcats;

		$cid = ArrayHelper::toInteger($cid);
		$cid_list = implode( ',', $cid );

		$cid_wassocs = array();

		switch ((string)$action)
		{
			// Delete
			case 'core.delete':
				$query = 'SELECT DISTINCT catid'
					. ' FROM #__flexicontent_cats_item_relations'
					. ' WHERE catid IN (' . $cid_list . ')'
				;

				$cid_wassocs = $this->_db->setQuery($query)->loadColumn();

				/**
				 * Find categories without children
				 */
				$remaining_cats = array();
				$cid_wassocs_flipped = array_flip($cid_wassocs);

				// Create an array of categories to examine, do not include categories that have item associations
				foreach($cid as $i => $cat_id)
				{
					if (!isset($cid_wassocs_flipped[$cat_id]))
					{
						$remaining_cats[$cat_id] = new stdClass;
						$remaining_cats[$cat_id]->descendantsarray = !empty($globalcats[$cat_id]->descendantsarray) ? array_flip($globalcats[$cat_id]->descendantsarray) : array();
						unset($remaining_cats[$cat_id]->descendantsarray[$cat_id]);
					}
				}

				// Repeat finding categories without children until a complete loop is run without removing any category
				do {
					$removed = false;

					foreach($remaining_cats as $cat_id => $cat)
					{
						if (!count($cat->descendantsarray))
						{
							unset($remaining_cats[$cat_id]);
							$removed = true;

							foreach($remaining_cats as $id => $cat)
							{
								unset($remaining_cats[$id]->descendantsarray[$cat_id]);
							}
						}
					}
				} while ($removed === true);

				foreach($remaining_cats as $cat_id => $cat)
				{
					$cid_wassocs[] = $cat_id;
				}

				break;

			// Trash, Unpublish
			case -2:
			case 0:
				break;
		}

		return $cid_wassocs;
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
			'table'    => 'categories',
			'context'  => 'com_categories.item',
			'created'  => 'created_time',
			'modified' => 'modified_time',
			'state'    => 'published',
		);

		return parent::getLangAssocs($ids, $config);
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */


	/**
	 * Method to count assigned items for the given categories
	 *
	 * @param		array     $cid      array of record ids to check
	 *
	 * @return	array     An array of objects with category assignments counts
	 *
	 * @since   3.3.0
	 */
	public function getAssignedItems($cids)
	{
		if (empty($cids))
		{
			return array();
		}

		$query = ' SELECT rel.catid, COUNT(rel.itemid) AS nrassigned'
			. ' FROM #__flexicontent_cats_item_relations AS rel'
			. ' WHERE rel.catid IN (' . implode(',', $cids) . ')'
			. ' GROUP BY rel.catid'
		;

		return $this->_db->setQuery($query)->loadObjectList('catid');
	}


	/**
	 * Method to get parameters of parent categories
	 *
	 * @param   integer  $pk  The category id
	 * @return	string   An array of JSON strings
	 *
	 * @since	3.3.0
	 */
	public function getParentParams($pk)
	{
		global $globalcats;

		if (empty($pk) || empty($globalcats[$pk]->ancestors))
		{
			return array();
		}

		$query = 'SELECT id, params'
			. ' FROM #__categories'
			. ' WHERE id IN (' . $globalcats[$pk]->ancestors . ')'
			. ' ORDER BY level ASC'
		;
		return $this->_db->setQuery($query)->loadObjectList('id');
	}


	/**
	 * Method to count assigned items for the given categories
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	function countItemsByState($cids)
	{
		if (empty($cids))
		{
			return array();
		}

		$query = ' SELECT rel.catid, i.state, COUNT(rel.itemid) AS nrassigned'
			. ' FROM #__flexicontent_cats_item_relations AS rel'
			. ' JOIN #__content AS i ON i.id=rel.itemid'
			. ' WHERE rel.catid IN (' . implode(',', $cids) . ')'
			. ' GROUP BY rel.catid, i.state'
		;
		$data = $this->_db->setQuery($query)->loadObjectList();

		$assigned = array();
		foreach ($data as $catid => $d)
		{
			$assigned[$d->catid][$d->state] = $d->nrassigned;
		}

		return $assigned;
	}
}
