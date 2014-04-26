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

jimport('joomla.application.component.modellist');

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
	 * Method to get the query used to retrieve categories data
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	function getListQuery()
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

		// Create a new query object.
		$query = $db->getQuery(true);
		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select',
				'c.*'
				.', u.name AS editor, level.title AS access_level'
				.', (SELECT COUNT(*) FROM #__flexicontent_cats_item_relations AS rel WHERE rel.catid = c.id) AS nrassigned '
				.', c.params as config, ag.title AS access_level '
			)
		);
		$query->from('#__categories AS c');
		$query->select('l.title AS language_title');
		$query->join('LEFT', '#__languages AS l ON l.lang_code = c.language');
		$query->join('LEFT', '#__viewlevels as level ON level.id=c.access');
		$query->join('LEFT', '#__users AS u ON u.id = c.checked_out');
		$query->join('LEFT', '#__viewlevels AS ag ON ag.id = c.access');
		$query->where("c.extension = '".FLEXI_CAT_EXTENSION."' ");
		
		
		// Filter by publicationd state
		if (is_numeric($filter_state)) {
			$query->where('c.published = ' . (int) $filter_state);
		}
		elseif ( $filter_state === '') {
			$query->where('c.published IN (0, 1)');
		}
		
		// Filter by access level
		if ( $filter_access ) {
			$query->where('c.access = '.(int) $filter_access);
		}
		
		if ( $filter_cats ) {
			// Limit category list to those contain in the subtree of the choosen category
			$query->where(' c.id IN (SELECT cat.id FROM #__categories AS cat JOIN #__categories AS parent ON cat.lft BETWEEN parent.lft AND parent.rgt WHERE parent.id='. (int) $filter_cats.')' );
		} else {
			// Limit category list to those containing CONTENT (joomla articles)
			$query->where(' (c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY) . ')');
		}
		
		// Filter on the level.
		if ( $filter_level ) {
			$query->where('c.level <= '.(int) $filter_level);
		}
		
		// Filter by language
		if ( $filter_language ) {
			$query->where('l.lang_code = '.$db->Quote( $filter_language ) );
		}
		
		// Implement View Level Access
		if (!$user->authorise('core.admin'))
		{
			$groups	= implode(',', $user->getAuthorisedViewLevels());
			$query->where('c.access IN ('.$groups.')');
		}
		
		// Filter by search word (can be also be  id:NN  OR author:AAAAA)
		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('c.id = '.(int) substr($search, 3));
			}
			elseif (stripos($search, 'author:') === 0) {
				$search = $db->Quote('%'.$db->escape(substr($search, 7), true).'%');
				$query->where('(u.name LIKE '.$search.' OR u.username LIKE '.$search.')');
			}
			else {
				$search = $db->Quote('%'.$db->escape($search, true).'%');
				$query->where('(c.title LIKE '.$search.' OR c.alias LIKE '.$search.' OR c.note LIKE '.$search.')');
			}
		}
		
		$query->group('c.id');
		// Add the list ordering clause.
		$query->order($db->escape($filter_order.' '.$filter_order_Dir));
		
		//echo nl2br(str_replace('#__','jos_',$query));
		//echo str_replace('#__', 'jos_', $query->__toString());
		return $query;
	}
	
}
?>