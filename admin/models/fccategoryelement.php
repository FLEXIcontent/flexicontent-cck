<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            http://www.flexicontent.com
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

jimport('legacy.model.list');
use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Table\User;

/**
 * Flexicontent Component Categoryelement Model
 *
 */
class FlexicontentModelFccategoryelement extends JModelList
{
	var $records_dbtbl = 'categories';
	var $records_jtable = 'flexicontent_categories';

	/**
	 * Column names and record name
	 */
	var $record_name = 'category';
	var $state_col   = 'published';
	var $name_col    = 'title';
	var $parent_col  = 'parent_id';

	/**
	 * (Default) Behaviour Flags
	 */
	var $listViaAccess = true;
	var $copyRelations = false;

	/**
	 * Search and ordering columns
	 */
	var $search_cols       = array('title', 'alias', 'note');
	var $default_order     = 'a.lft';
	var $default_order_dir = 'ASC';

	/**
	 * List filters that are always applied
	 */
	var $hard_filters = array('extension' => FLEXI_CAT_EXTENSION);

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
	 * Associated item translations
	 *
	 * @var array
	 */
	var $_translations = null;

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
		parent::__construct($config);

		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$fcform = $jinput->get('fcform', 0, 'int');
		$p      = $option . '.' . $view . '.';

		// Parameters of the view, in our case it is only the component parameters
		$this->cparams = JComponentHelper::getParams( 'com_flexicontent' );

		/**
		 * Pagination: limit, limitstart
		 */

		$limit      = $fcform ? $jinput->get('limit', $app->getCfg('list_limit'), 'int')  :  $app->getUserStateFromRequest( $p.'limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $fcform ? $jinput->get('limitstart',                     0, 'int')  :  $app->getUserStateFromRequest( $p.'limitstart', 'limitstart', 0, 'int' );

		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );
		$jinput->set( 'limitstart',	$limitstart );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		$app->setUserState($p.'limit', $limit);
		$app->setUserState($p.'limitstart', $limitstart);


	}


	/**
	 * Method to build the query for the records
	 *
	 * @return JDatabaseQuery   The DB Query object
	 *
	 * @since 3.3.0
	 */
	protected function getListQuery()
	{
		$query = $this->_db->getQuery(true);
		$query->select(
				'c.*'
				.', ua.name AS author, CASE WHEN level.title IS NULL THEN CONCAT_WS(\'\', \'deleted:\', c.access) ELSE level.title END AS access_level'
				.', c.params as config, l.title AS language_title');
		$query->from('#__categories AS c');
		$query->join('LEFT', '#__languages AS l ON l.lang_code = c.language');
		$query->join('LEFT', '#__viewlevels as level ON level.id = c.access');
		$query->join('LEFT', '#__users as ua ON ua.id = c.created_user_id');
		$this->_buildContentWhere($query);
		$this->_buildContentOrderBy($query);


		//echo nl2br(str_replace('#__','jos_',$query));
		//echo str_replace('#__', 'jos_', $query->__toString());
		return $query;
	}


	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildContentOrderBy($query = null)
	{
		$app = JFactory::getApplication();
		$jinput  = $app->input;
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');

		$filter_order     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order',     'filter_order',     'c.lft',      'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir', 'filter_order_Dir', '',           'cmd' );

		$orderby = $filter_order.' '.$filter_order_Dir . ($filter_order != 'c.lft' ? ', c.lft' : '');
		if ($query)
			$query->order($orderby);
		else
			return ' ORDER BY '. $orderby;
	}


	/**
	 * Method to build the where clause of the query for the records
	 *
	 * @access private
	 * @return string
	 */
	function _buildContentWhere($query = null)
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();

		$jinput  = $app->input;
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');

		$assocs_id = $jinput->get('assocs_id', 0, 'int');

		if ($assocs_id)
		{
			$item_lang   = $app->getUserStateFromRequest( $option.'.'.$view.'.item_lang', 'item_lang', '', 'string' );
			$created_by  = $app->getUserStateFromRequest( $option.'.'.$view.'.created_by', 'created_by', 0, 'int' );

			$assocanytrans = $user->authorise('flexicontent.assocanytrans', 'com_flexicontent');

			// Limit to creator if creator not privileged
			if (!$assocanytrans && !$created_by)
			{
				$created_by = $user->id;
				$app->setUserState( $option.'.'.$view.'.created_by', $created_by );
			}
		}

		$filter_state  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_state', 'filter_state', '', 'cmd' );
		$filter_cats   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_cats',  'filter_cats',  0,  'int' );

		$filter_level  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_level', 'filter_level', 0,  'int' );
		$filter_lang   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_lang',  'filter_lang',  '', 'string' );
		$filter_author = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_author','filter_author','', 'cmd' );
		$filter_access = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_access','filter_access','', 'int' );

		$filter_lang   = $assocs_id && $item_lang  ? $item_lang  : $filter_lang;
		$filter_author = $assocs_id && $created_by ? $created_by : $filter_author;

		$search = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 'search', '', 'string' );
		$search = StringHelper::trim( StringHelper::strtolower( $search ) );

		$where = array();
		$where[] = "c.extension = '".FLEXI_CAT_EXTENSION."' ";

		// Filter by publications state
		if (is_numeric($filter_state)) {
			$where[] = 'c.published = ' . (int) $filter_state;
		}
		/*elseif ( $filter_state === '') {
			$where[] = 'c.published IN (0, 1)';
		}*/

		// Filter by Access level
		if ($filter_access) {
			$where[] = 'c.access = '.(int) $filter_access;
		}

		// Filter by parent category
		if ( $filter_cats ) {
			// Limit category list to those contain in the subtree of the choosen category
			$where[] = ' c.id IN (SELECT cat.id FROM #__categories AS cat JOIN #__categories AS parent ON cat.lft BETWEEN parent.lft AND parent.rgt WHERE parent.id='. (int) $filter_cats.')';
		} else {
			// Limit category list to those containing CONTENT (joomla articles)
			$where[] = ' (c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY) . ')';
		}

		// Filter by depth level
		if ( $filter_level ) {
			$where[] = 'c.level <= '.(int) $filter_level;
		}

		// Filter by language
		if ( $filter_lang ) {
			$where[] = 'c.language = '.$this->_db->Quote( $filter_lang );
		}

		// Filter by author / owner
		if ( strlen($filter_author) ) {
			$where[] = 'c.created_user_id = ' . $filter_author;
		}

		// Filter via View Level Access, if user is not super-admin
		if (!JFactory::getUser()->authorise('core.admin') && ($app->isSite() || $this->listViaAccess))
		{
			$groups	= implode(',', JAccess::getAuthorisedViewLevels($user->id));
			$where[] = 'c.access IN ('.$groups.')';
		}

		// Filter by search word (can be also be  id:NN  OR author:AAAAA)
		if ( !empty($search) ) {
			if (stripos($search, 'id:') === 0) {
				$where[] = 'c.id = '.(int) substr($search, 3);
			}
			elseif (stripos($search, 'author:') === 0) {
				$search = $this->_db->Quote('%'.$this->_db->escape(substr($search, 7), true).'%');
				$where[] = '(ua.name LIKE '.$search.' OR ua.username LIKE '.$search.')';
			}
			else {
				$search = $this->_db->Quote('%'.$this->_db->escape($search, true).'%');
				$where[] = '(c.title LIKE '.$search.' OR c.alias LIKE '.$search.' OR c.note LIKE '.$search.')';
			}
		}

		if ($query)
			foreach($where as $w) $query->where($w);
		else
			return count($where) ? ' WHERE '.implode(' AND ', $where) : '';
	}


	/**
	 * MODEL SPECIFIC HELPER FUNCTIONS
	 */


	/**
	 * Method to get author list for filtering
	 *
	 * @return array
	 * @since 1.5
	 */
	function getAuthorslist ()
	{
		$query = 'SELECT i.created_by AS id, ua.name AS name'
				. ' FROM #__content AS i'
				. ' LEFT JOIN #__users as ua ON ua.id = i.created_by'
				. ' GROUP BY i.created_by'
				. ' ORDER BY ua.name'
				;
		$this->_db->setQuery($query);

		return $this->_db->loadObjectList();
	}


	/**
	 * Method to get item (language) associations
	 *
	 * @param		int			The id of the item
	 *
	 * @return	array		The array of associations
	 */
	function getLangAssocs()
	{
		// If items array is empty, just return empty array
		if (empty($this->_data))
		{
			return array();
		}

		// Get associated translations
		elseif ($this->_translations === null)
		{
			$ids = array();
			foreach ($this->_data as $item)
			{
				$ids[] = $item->id;
			}

			$this->_translations = flexicontent_db::getLangAssocs($ids);
		}

		return $this->_translations;
	}
}
