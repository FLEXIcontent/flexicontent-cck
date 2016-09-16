<?php
/**
 * @version 1.5 stable $Id: qfcategoryelement.php 184 2010-04-04 06:08:30Z emmanuel.danan $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('legacy.model.list');
use Joomla\String\StringHelper;

/**
 * Flexicontent Component Categoryelement Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelQfcategoryelement extends JModelList
{
	/**
	 * rows array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Category total
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
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');

		$limit      = $app->getUserStateFromRequest( $option.'.'.$view.'.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( $option.'.'.$view.'.limitstart', 'limitstart', 0, 'int' );

		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}
	

	/**
	 * Method to get the query used to retrieve categories data
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	function getListQuery()
	{
		$query = $this->_db->getQuery(true);
		$query->select(
				'c.*'
				.', u.name AS author, CASE WHEN level.title IS NULL THEN CONCAT_WS(\'\', \'deleted:\', c.access) ELSE level.title END AS access_level'
				.', c.params as config, l.title AS language_title');
		$query->from('#__categories AS c');
		$query->join('LEFT', '#__languages AS l ON l.lang_code = c.language');
		$query->join('LEFT', '#__viewlevels as level ON level.id=c.access');
		$query->join('LEFT', '#__users AS u ON u.id = c.created_user_id');
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
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');
		
		$filter_order     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order',     'filter_order',     'c.lft',      'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir', 'filter_order_Dir', '',           'cmd' );
		
		$orderby = $filter_order.' '.$filter_order_Dir . ($filter_order != 'c.lft' ? ', c.lft' : '');
		if ($query)
			$query->order($orderby);
		else
			return ' ORDER BY '. $orderby;
	}
	
	
	/**
	 * Build the where clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildContentWhere($query = null)
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');
		
		$assocs_id   = JRequest::getInt( 'assocs_id', 0 );
		
		if ($assocs_id)
		{
			$language    = $app->getUserStateFromRequest( $option.'.'.$view.'.language', 'language', '', 'string' );
			$created_by  = $app->getUserStateFromRequest( $option.'.'.$view.'.created_by', 'created_by', 0, 'int' );
			
			$assocanytrans = $user->authorise('flexicontent.assocanytrans', 'com_flexicontent');
			if (!$assocanytrans && !$created_by) {
				$created_by = $user->id;
				$app->setUserState( $option.'.'.$view.'.created_by', $created_by );
			}
		}
		
		$filter_state  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_state', 'filter_state', '', 'cmd' );
		$filter_cats   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_cats',  'filter_cats',  0,  'int' );
		
		$filter_level  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_level', 'filter_level', 0,  'int' );
		$filter_lang   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_lang',  'filter_lang',  '', 'cmd' );
		$filter_author = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_author','filter_author','', 'cmd' );
		$filter_access = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_access','filter_access','', 'string' );
		
		$filter_lang   = $assocs_id && $language   ? $language   : $filter_lang;
		$filter_author = $assocs_id && $created_by ? $created_by : $filter_author;
		
		$search = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 'search', '', 'string' );
		$search = StringHelper::trim( StringHelper::strtolower( $search ) );

		$where = array();
		$where[] = "c.extension = '".FLEXI_CAT_EXTENSION."' ";
		
		// Filter by publications state
		if (is_numeric($filter_state)) {
			$where[] = 'c.published = ' . (int) $filter_state;
		}
		elseif ( $filter_state === '') {
			$where[] = 'c.published IN (0, 1)';
		}
		
		// Filter by access level
		if ( strlen($filter_access) ) {
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
		
		// Implement View Level Access
		if (!$user->authorise('core.admin'))
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
				$where[] = '(u.name LIKE '.$search.' OR u.username LIKE '.$search.')';
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
	
	
	// ***********************************
	// *** MODEL SPECIFIC HELPER FUNCTIONS
	// ***********************************
	
	/**
	 * Method to get author list for filtering
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getAuthorslist ()
	{
		$query = 'SELECT i.created_by AS id, u.name AS name'
				. ' FROM #__content AS i'
				. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
				. ' GROUP BY i.created_by'
				. ' ORDER BY u.name'
				;
		$this->_db->setQuery($query);

		return $this->_db->loadObjectList();
	}

}
