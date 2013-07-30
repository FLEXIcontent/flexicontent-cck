<?php
/**
 * @version 1.5 stable $Id$
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

jimport('joomla.application.component.model');

/**
 * Flexicontent Component Categoryelement Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelQfcategoryelement extends JModelLegacy
{
	/**
	 * Category data
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
	 * Category id
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		$array = JRequest::getVar('cid',  0, '', 'array');
		$this->setId((int)$array[0]);

	}

	/**
	 * Method to set the category identifier
	 *
	 * @access	public
	 * @param	int Category identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
	}

	/**
	 * Method to get categories data
	 *
	 * @access public
	 * @return array
	 * @since	1.0
	 */
	function getData()
	{
		$app  = JFactory::getApplication();
		$db   = JFactory::getDBO();
		$user = JFactory::getUser();
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');
		global $globalcats;
		
		$order_property = !FLEXI_J16GE ? 'c.ordering' : 'c.lft';
		$filter_order     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order',     'filter_order',     $order_property, 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$filter_cats      = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_cats',			 'filter_cats',			 '', 'int' );
		$filter_state     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_state',     'filter_state',     '', 'string' );
		$filter_access    = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_access',    'filter_access',    '', 'string' );
		$filter_level     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_level',     'filter_level',     '', 'string' );
		if (FLEXI_J16GE) {
			$filter_language  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_language',  'filter_language',  '', 'string' );
		}
		$search           = $app->getUserStateFromRequest( $option.'.'.$view.'.search',           'search',           '', 'string' );
		$search           = trim( JString::strtolower( $search ) );
		$limit            = $app->getUserStateFromRequest( $option.'.'.$view.'.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart       = $app->getUserStateFromRequest( $option.'.'.$view.'.limitstart', 'limitstart', 0, 'int' );
		
		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', '. $order_property;
		
		$where = array();
		
		// Filter by publication state, ... breaks tree construction, commented out and done below
		/*if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$where[] = 'c.published = 1';
			} else if ($filter_state == 'U' ) {
				$where[] = 'c.published = 0';
			}
		}*/
		
		// Filter by access level, ... breaks tree construction, commented out and done below
		/*if ( $filter_access ) {
			$where[] = 'c.access = '.(int) $filter_access;
		}*/
		
		if ( $filter_cats && isset($globalcats[$filter_cats]) )  {
			// Limit category list to those contain in the subtree of the choosen category
			$where[] = 'c.id IN (' . $globalcats[$filter_cats]->descendants . ')';
		}
		
		// Filter on the level.
		if ( $filter_level ) {
			$cats = array();
			$filter_level = (int) $filter_level;
			foreach($globalcats as $cat) {
				if ( @$cat->level <= $filter_level) $cats[] = $cat->id;
			}
			if ( !empty($cats) ) {
				$where[] = 'c.id IN (' . implode(",", $cats) . ')';
			}
		}
		
		$where 		= ( count( $where ) ? ' AND ' . implode( ' AND ', $where ) : '' );
		
		// Note, since this is a tree we have to do the WORD SEARCH separately.
		if ($search) {			
			$query = 'SELECT c.id'
					. ' FROM #__categories AS c'
					. ' WHERE LOWER(c.title) LIKE '.$db->Quote( '%'.$db->getEscaped( $search, true ).'%', false )
					. ' AND c.section = ' . FLEXI_SECTION
					. $where
					;
			$db->setQuery( $query );
			$search_rows = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();					
		}
		
		$query = 'SELECT c.*, u.name AS editor, COUNT(rel.catid) AS nrassigned, c.params as config, '
					. (FLEXI_J16GE ? 'level.title AS access_level' : 'g.name AS groupname')
					. ' FROM #__categories AS c'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
					. (FLEXI_J16GE ?
					  ' LEFT JOIN #__viewlevels AS level ON level.id=c.access' :
					  ' LEFT JOIN #__groups AS g ON g.id = c.access'
						)
					. ' LEFT JOIN #__users AS u ON u.id = c.checked_out'
					. (FLEXI_J16GE ? '' : ' LEFT JOIN #__sections AS sec ON sec.id = c.section')
					. (FLEXI_J16GE ? 
					  ' WHERE c.extension = '.$db->Quote(FLEXI_CAT_EXTENSION).' AND c.lft >= ' . $db->Quote(FLEXI_LFT_CATEGORY).' AND c.rgt<='.$db->Quote(FLEXI_RGT_CATEGORY) :
					  ' WHERE c.section = '.FLEXI_SECTION
					)
					. (FLEXI_J16GE ? '' : ' AND sec.scope = ' . $db->Quote('content'))
					. $where
					. ' GROUP BY c.id'
					. $orderby
					;
		$db->setQuery( $query );
		$rows = $db->loadObjectList();
		
		//establish the hierarchy of the categories
		$children = array();
		
		// Set depth limit
		$levellimit = 30;
		
		foreach ($rows as $child) {
			$parent = $child->parent_id;
			$list 	= @$children[$parent] ? $children[$parent] : array();
			array_push($list, $child);
			$children[$parent] = $list;
		}
    
		// Put found items into a tree, in the case of displaying the subree of top level category use the parent id of the category
		$ROOT_CATEGORY_ID = FLEXI_J16GE ? 1 :0;
		$root_cat = !$filter_cats ? $ROOT_CATEGORY_ID : $globalcats[$filter_cats]->parent_id;
		$list = flexicontent_cats::treerecurse($root_cat, '', array(), $children, false, max(0, $levellimit-1));
		
		// Eventually only pick out the searched items.
		if ($search)
		{
			$srows = array();
			foreach ($search_rows as $sid) $srows[$sid] = 1;
			
			$list_search = array();
			foreach ($list as $item)
			{
				if ( @ $srows[$item->id] )  $list_search[] = $item;
			}
			$list = $list_search;
		}
		
		// Filter by access level
		if ( $filter_access ) {
			$_access = (int) $filter_access;
			
			$list_search = array();
			foreach ($list as $item) {
				if ( $item->access == $_access)  $list_search[] = $item;
			}
			$list = $list_search;
		}
		
		// Filter by publication state
		if ( $filter_state == 'P' || $filter_state == 'U' ) {
			$_state = $filter_state == 'P' ? 1 : 0;
			
			$list_search = array();
			foreach ($list as $item) {
				if ( $item->published == $_state)  $list_search[] = $item;
			}
			$list = $list_search;
		}
		
		// Create pagination object
		$total = count( $list );
		jimport('joomla.html.pagination');
		$this->_pagination = new JPagination( $total, $limitstart, $limit );

		// Slice out elements based on limits
		$list = array_slice( $list, $this->_pagination->limitstart, $this->_pagination->limit );
		
		return $list;
	}

	/**
	 * Method to get a pagination object for the categories
	 *
	 * @access public
	 * @return object
	 */
	function getPagination()
	{
		if ($this->_pagination == null) {
			$this->getData();
		}
		return $this->_pagination;
	}
}
?>