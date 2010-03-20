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
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.model');

/**
 * FLEXIcontent Component Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelTags extends JModel
{
	/**
	 * Data
	 *
	 * @var mixed
	 */
	var $_data = null;
	
	/**
	 * Tag
	 *
	 * @var mixed
	 */
	var $_tag = null;
	
	/**
	 * Items total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
		
		global $mainframe;

		// Get the paramaters of the active menu item
		$params = & $mainframe->getParams('com_flexicontent');

		//get the number of events from database
		$limit			= JRequest::getInt('limit', $params->get('limit'));
		$limitstart		= JRequest::getInt('limitstart');
		$id				= JRequest::getInt('id', 0);
		
		$this->setId((int)$id);

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
		
		// Get the filter request variables
		$this->setState('filter_order', JRequest::getCmd('filter_order', 'i.title'));
		$this->setState('filter_order_dir', JRequest::getCmd('filter_order_Dir', 'ASC'));
	}
	
	/**
	 * Method to set the tag id
	 *
	 * @access	public
	 * @param	int	tag ID number
	 */
	function setId($id)
	{
		// Set new category ID and wipe data
		$this->_id			= $id;
		$this->_data		= null;
	}

	/**
	 * Method to get Data
	 *
	 * @access public
	 * @return object
	 */
	function getData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{		
			$query = $this->_buildQuery();
			$this->_data = $this->_getList( $query, $this->getState('limitstart'), $this->getState('limit') );
		}
		
		return $this->_data;
	}
	
	/**
	 * Total nr of Items
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}
	
	/**
	 * Method to build the query
	 *
	 * @access public
	 * @return string
	 */
	function _buildQuery()
	{   	
		global $mainframe;

		$user		= & JFactory::getUser();
		$gid		= (int) $user->get('aid');
        // Get the WHERE and ORDER BY clauses for the query
		$params 	= & $mainframe->getParams('com_flexicontent');
		// show unauthorized items
		$show_noauth = $params->get('show_noauth', 0);

		// Select only items user has access to if he is not allowed to show unauthorized items
		if (!$show_noauth) {
			if (FLEXI_ACCESS) {
				$joinaccess  = ' LEFT JOIN #__flexiaccess_acl AS gc ON mc.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
				$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gi ON i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
				$andaccess	 = ' AND (gc.aro IN ( '.$user->gmid.' ) OR mc.access <= '. (int) $gid . ')';
				$andaccess  .= ' AND (gi.aro IN ( '.$user->gmid.' ) OR i.access <= '. (int) $gid . ')';
			} else {
				$joinaccess	 = '';
				$andaccess   = ' AND mc.access <= '.$gid;
				$andaccess  .= ' AND i.access <= '.$gid;
			}
		}

		$where		= $this->_buildItemWhere();
		$orderby	= $this->_buildItemOrderBy();

        $query = 'SELECT i.id, i.title, i.*, ie.*,'
         . ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
         . ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
         . ' FROM #__content AS i'
		 . ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
         . ' INNER JOIN #__flexicontent_tags_item_relations AS t ON t.itemid = i.id'
         . ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
         . ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
         . ' LEFT JOIN #__categories AS mc ON mc.id = i.catid'
         . $joinaccess
         . $where
         . $andaccess
         . ' GROUP BY i.id'
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
	function _buildItemOrderBy()
	{	
		$filter_order		= $this->getState('filter_order');
		$filter_order_dir	= $this->getState('filter_order_dir');

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_dir.', i.title';

		return $orderby;
	}
	
	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildItemWhere( )
	{
		global $mainframe;

		$user		= & JFactory::getUser();
		$gid		= (int) $user->get('aid');
		$params 	= & $mainframe->getParams('com_flexicontent');
		// shortcode of the site active language (joomfish)
		$lang 		= JRequest::getWord('lang', '' );
		$filtertag  = $params->get('filtertag', 0);

		// First thing we need to do is to select only the requested items
		$where = ' WHERE t.tid = '.$this->_id;
		
		// Filter the category view with the active active language
		if (FLEXI_FISH && $filtertag) {
			$where .= ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' );
		}

		$states = ((int)$user->get('gid') > 19) ? '1, -5, 0, -3, -4' : '1, -5';
		$where .= ' AND i.state IN ('.$states.')';

		$where .= ' AND i.sectionid = '.FLEXI_SECTION;

		/*
		 * If we have a filter, and this is enabled... lets tack the AND clause
		 * for the filter onto the WHERE clause of the item query.
		 */
		if ($params->get('use_search'))
		{
			$filter 		= JRequest::getString('filter', '', 'request');

			if ($filter)
			{
				// clean filter variables
				// $filter			= $this->_db->getEscaped( trim(JString::strtolower( $filter ) ) );
				// $where .= ' AND LOWER( i.title ) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $filter, true ).'%', false );
				$where .= ' AND MATCH (ie.search_index) AGAINST ('.$this->_db->Quote( $this->_db->getEscaped( $filter, true ), false ).' IN BOOLEAN MODE)';
			}
		}
		return $where;
	}
	
	/**
	 * Method to load the Tag data
	 *
	 * @access public
	 * @return object
	 */
	function getTag()
	{
		//get categories
		$query = 'SELECT t.name, t.id,'
				. ' CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
				. ' FROM #__flexicontent_tags AS t'
				. ' WHERE t.id = '.$this->_id
				. ' AND t.published = 1'
				;

		$this->_db->setQuery($query);
        $this->_tag = $this->_db->loadObject();
        
		return $this->_tag;
	}
}
?>