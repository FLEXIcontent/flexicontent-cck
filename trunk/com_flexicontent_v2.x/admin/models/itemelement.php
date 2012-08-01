<?php
/**
 * @version 1.5 stable $Id: itemelement.php 1317 2012-05-19 22:17:59Z ggppdk $
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
 * Flexicontent Component Itemelement Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelItemelement extends JModel
{
	/**
	 * Items data obj
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		$limit		= $mainframe->getUserStateFromRequest( $option.'.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
		$limitstart = $mainframe->getUserStateFromRequest( $option.'.itemelement.limitstart', 'limitstart', 0, 'int' );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}

	/**
	 * Method to get categories item data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		$user = JFactory::getUser();
		if ( !$user->id ) return array();  // catch case of guest user submitting in frontend
		
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			$db =& JFactory::getDBO();
			$db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $db->loadResult();
			if ( $db->getErrorNum() ) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage(nl2br($query."\n".$db->getErrorMsg()."\n"),'error');
			}
		}
		return $this->_data;
	}

	/**
	 * Total nr of events
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		$user = JFactory::getUser();
		if ( !$user->id ) return array();  // catch case of guest user submitting in frontend
		
		// Lets load the content if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	function _buildQuery()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildContentWhere();
		$orderby	= $this->_buildContentOrderBy();
		$lang		= (FLEXI_FISH || FLEXI_J16GE) ? 'ie.language AS lang, ' : '';

		$query = 'SELECT DISTINCT SQL_CALC_FOUND_ROWS rel.itemid, i.*, ' . $lang . 'u.name AS editor, t.name AS type_name'
					. ' FROM #__content AS i'
					. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
					. ' LEFT JOIN #__users AS u ON u.id = i.checked_out'
					. ' LEFT JOIN #__flexicontent_types AS t ON t.id = ie.type_id'
					. $where
					. $orderby
					;

		return $query;
	}

	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildContentOrderBy()
	{
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.itemelement.filter_order', 		'filter_order', 	'i.ordering', 	'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.itemelement.filter_order_Dir',		'filter_order_Dir',	'', 			'word' );

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', i.ordering';

		return $orderby;
	}

	/**
	 * Build the where clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildContentWhere()
	{
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$langparent_item = $mainframe->getUserStateFromRequest( $option.'.itemelement.langparent_item', 'langparent_item', 0, 'int' );
		$type_id         = $mainframe->getUserStateFromRequest( $option.'.itemelement.type_id', 'type_id', 0, 'int' );
		$created_by      = $mainframe->getUserStateFromRequest( $option.'.itemelement.created_by', 'created_by', 0, 'int' );
		if ($langparent_item) {
			$user_fullname = JFactory::getUser($created_by)->name;
			$this->_db->setQuery('SELECT name FROM #__flexicontent_types WHERE id = '.$type_id);
			$type_name = $this->_db->loadResult();
			$msg = sprintf("Selecting ORIGINAL Content item for a translating item of &nbsp; Content Type: \"%s\" &nbsp; and &nbsp; User: \"%s\"", $type_name, $user_fullname);
			$jAp=& JFactory::getApplication();
			$jAp->enqueueMessage($msg,'message');
		}
		
		$filter_state    = $mainframe->getUserStateFromRequest( $option.'.itemelement.filter_state', 'filter_state', '', 'word' );
		$filter_cats     = $mainframe->getUserStateFromRequest( $option.'.itemelement.filter_cats', 'filter_cats', '', 'int' );
		$filter_type     = $mainframe->getUserStateFromRequest( $option.'.itemelement.filter_type', 'filter_type', '', 'int' );
		
		if (FLEXI_FISH || FLEXI_J16GE) {
			if ($langparent_item)
				$filter_lang = flexicontent_html::getSiteDefaultLang();
			else
				$filter_lang 	= $mainframe->getUserStateFromRequest( $option.'.itemelement.filter_lang', 	'filter_lang', '', 'cmd' );
		}
		
		$search 			= $mainframe->getUserStateFromRequest( $option.'.itemelement.search', 'search', '', 'string' );
		$search 			= $this->_db->getEscaped( trim(JString::strtolower( $search ) ) );

		$where = array();
		$where[] = ' i.state != -1';
		$where[] = ' i.state != -2';
		if (!FLEXI_J16GE) $where[] = ' sectionid = ' . FLEXI_SECTION;

		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$where[] = 'i.state = 1';
			} else if ($filter_state == 'U' ) {
				$where[] = 'i.state = 0';
			} else if ($filter_state == 'PE' ) {
				$where[] = 'i.state = -3';
			} else if ($filter_state == 'OQ' ) {
				$where[] = 'i.state = -4';
			} else if ($filter_state == 'IP' ) {
				$where[] = 'i.state = -5';
			}
		}
		
		if ( $filter_cats ) {
			$where[] = 'rel.catid = ' . $filter_cats;
		}
		
		if ($langparent_item && $type_id ) {
			$where[] = 'ie.type_id = ' . $type_id;
		} else if ( $filter_type ) {
			$where[] = 'ie.type_id = ' . $filter_type;
		}

		if (FLEXI_FISH || FLEXI_J16GE) {
			if ( $filter_lang ) {
				$where[] = 'ie.language = ' . $this->_db->Quote($filter_lang);
			}
		}

		if ( $search ) {
			$where[] = ' LOWER(i.title) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $search, true ).'%', false );
		}
		
		/*$user = JFactory::getUser();
		if (FLEXI_J16GE) {
			$isAdmin = JAccess::check($user->id, 'core.admin', 'root.1');
		} else {
			$isAdmin = $user->gid >= 24;
		}*/
		
		if ($langparent_item && $created_by) {
			$where[] = ' i.created_by='.$created_by;
		}

		$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );

		return $where;
	}

	/**
	 * Method to get types list
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ()
	{
		$query = 'SELECT id, name'
				. ' FROM #__flexicontent_types'
				. ' WHERE published = 1'
				. ' ORDER BY name ASC'
				;
		$this->_db->setQuery($query);
		$types = $this->_db->loadObjectList();
		
		return $types;
	}

}
?>