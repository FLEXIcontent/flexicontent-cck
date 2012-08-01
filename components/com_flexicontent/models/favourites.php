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
class FlexicontentModelFavourites extends JModel
{
	
	/**
	 * Item list data
	 *
	 * @var mixed
	 */
	var $_data = null;
	
	/**
	 * Items list total
	 *
	 * @var integer
	 */
	var $_total = null;
	
	/**
	 * Favourites view parameters via menu or ...
	 *
	 * @var object
	 */
	var $_params = null;
	
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
		
		// Set id and load parameters
		$id = JRequest::getInt('id', 0);		
		$this->setId((int)$id);
		
		$params = & $this->_params;
		
		//get the number of events from database
		$limit      = JRequest::getInt('limit', $params->get('limit'));
		$limitstart = JRequest::getInt('limitstart');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
		
		// Get the filter request variables
		$this->setState('filter_order', 	JRequest::getCmd('filter_order', 'i.modified'));
		$this->setState('filter_order_dir', JRequest::getCmd('filter_order_Dir', 'DESC'));
	}
	
	/**
	 * Method to set the tag id
	 *
	 * @access	public
	 * @param	int	tag ID number
	 */
	function setId($id)
	{
		// Set new category ID, wipe member variables and load parameters
		$this->_id      = $id;
		$this->_data    = null;
		$this->_total   = null;
		$this->_params  = null;
		$this->_loadParams();
	}

	/**
	 * Overridden get method to get properties from the tag
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return 	mixed 				The value of the property
	 * @since	1.5
	 */
	function get($property, $default=null)
	{
		if ( $this->_tag || $this->_tag = $this->getTag() ) {
			if(isset($this->_tag->$property)) {
				return $this->_tag->$property;
			}
		}
		return $default;
	}
	
	/**
	 * Overridden set method to pass properties on to the tag
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function set( $property, $value=null )
	{
		if ( $this->_tag || $this->_tag = $this->getTag() ) {
			$this->_tag->$property = $value;
			return true;
		} else {
			return false;
		}
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
			if ( $this->_db->getErrorNum() ) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage(nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
			}
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
		$user		= & JFactory::getUser();
		$params = & $this->_params;
		
		// image for an image field
		$use_image    = (int)$params->get('use_image', 1);
		$image_source = $params->get('image_source');

		// EXTRA select and join for special fields: --image--
		if ($use_image && $image_source) {
			$select_image = ' img.value AS image,';
			$join_image   = '	LEFT JOIN #__flexicontent_fields_item_relations AS img'
				. '	ON ( i.id = img.item_id AND img.valueorder = 1 AND img.field_id = '.$image_source.' )';
		} else {
			$select_image	= '';
			$join_image		= '';
		}
		
		// show unauthorized items
		$show_noauth = $params->get('show_noauth', 0);
		
		// Select only items user has access to if he is not allowed to show unauthorized items
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$andaccess = ' AND i.access IN ('.$aid_list.') AND mc.access IN ('.$aid_list.')';
				$joinaccess	= '';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$joinaccess  = ' LEFT JOIN #__flexiaccess_acl AS gc ON mc.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gi ON i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
					$andaccess	 = ' AND (gc.aro IN ( '.$user->gmid.' ) OR mc.access <= '. $aid . ')';
					$andaccess  .= ' AND (gi.aro IN ( '.$user->gmid.' ) OR i.access <= '. $aid . ')';
				} else {
					$joinaccess	 = '';
					$andaccess   = ' AND mc.access <= '.$aid;
					$andaccess  .= ' AND i.access <= '.$aid;
				}
			}
		} else {
			$joinaccess	 = '';
			$andaccess   = '';
		}

		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildItemWhere();
		$orderby	= $this->_buildItemOrderBy();
		
		// Add sort items by custom field.
		$field_item = '';
		if ($params->get('orderbycustomfieldid', 0) != 0) {
			$field_item = ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.(int)$params->get('orderbycustomfieldid', 0);
		}

		$query = 'SELECT i.id, i.*, ie.*, '.$select_image
			. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
			. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ' FROM #__content AS i'
			. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' INNER JOIN #__flexicontent_favourites AS fav ON fav.itemid = i.id'
			. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
			. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
			. ' LEFT JOIN #__categories AS mc ON mc.id = i.catid'
			. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			. $join_image
			. $field_item
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
		$params = & $this->_params;
				
		$filter_order		= $this->getState('filter_order');
		$filter_order_dir	= $this->getState('filter_order_dir');

		if ($params->get('orderby')) {
			$order = $params->get('orderby');
			
			switch ($order) {
				case 'date' :
				$filter_order		= 'i.created';
				$filter_order_dir	= 'ASC';
				break;
				case 'rdate' :
				$filter_order		= 'i.created';
				$filter_order_dir	= 'DESC';
				break;
				case 'modified' :
				$filter_order		= 'i.modified';
				$filter_order_dir	= 'DESC';
				break;
				case 'alpha' :
				$filter_order		= 'i.title';
				$filter_order_dir	= 'ASC';
				break;
				case 'ralpha' :
				$filter_order		= 'i.title';
				$filter_order_dir	= 'DESC';
				break;
				case 'author' :
				$filter_order		= 'u.name';
				$filter_order_dir	= 'ASC';
				break;
				case 'rauthor' :
				$filter_order		= 'u.name';
				$filter_order_dir	= 'DESC';
				break;
				case 'hits' :
				$filter_order		= 'i.hits';
				$filter_order_dir	= 'ASC';
				break;
				case 'rhits' :
				$filter_order		= 'i.hits';
				$filter_order_dir	= 'DESC';
				break;
				case 'order' :
				$filter_order		= 'rel.ordering';
				$filter_order_dir	= 'ASC';
				break;
			}
			
		}
		// Add sort items by custom field. Issue 126 => http://code.google.com/p/flexicontent/issues/detail?id=126#c0
		if ($params->get('orderbycustomfieldid', 0) != 0)
		{
			if ($params->get('orderbycustomfieldint', 0) != 0) $int = ' + 0'; else $int ='';
			$filter_order		= 'f.value'.$int;
			$filter_order_dir	= $params->get('orderbycustomfielddir', 'ASC');
		}
		
		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_dir.', i.created';

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
		$mainframe =& JFactory::getApplication();
		$params = & $this->_params;
		$user		= & JFactory::getUser();
		
		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		//  thus the items are published globally at the time the author specified in his/her local clock
		//$now		= $mainframe->get('requestTime');
		$now			= JFactory::getDate()->toMySQL();
		$nullDate	= $db->getNullDate();

		// First thing we need to do is to select only the requested FAVOURED items
		$where = ' WHERE fav.userid = '.(int)$user->get('id');
		
		// Limit to published items. Exception when user can edit item
		// but NO CLEAN WAY OF CHECKING individual item EDIT ACTION while creating this query !!!
		// *** so we will rely only on item created or modified by the user ***
		// when view is created the edit button will check individual item EDIT ACTION
		// NOTE: *** THE ABOVE MENTIONED EXCEPTION WILL NOT OVERRIDE ACCESS
		if (FLEXI_J16GE) {
			$ignoreState = $user->authorise('core.admin', 'com_flexicontent');  // Super user privelege, can edit all for sure
		} else if (FLEXI_ACCESS) {
			$ignoreState = (int)$user->gid >= 25;  // Super admin, can edit all for sure
		} else {
			$ignoreState = (int)$user->get('gid') > 19;  // author has 19 and editor has 20
		}
		
		$states = $ignoreState ? '1, -5, 0, -3, -4' : '1, -5';
		$where .= ' AND ( i.state IN ('.$states.') OR i.created_by = '.$user->id.' )';       //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		
		// Limit by publication date. Exception: when displaying personal user items or items modified by the user
		$where .= ' AND ( ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$this->_db->Quote($now).' ) OR i.created_by = '.$user->id.' )';       //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		$where .= ' AND ( ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$this->_db->Quote($now).' ) OR i.created_by = '.$user->id.' )';       //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';

		$where .= !FLEXI_J16GE ? ' AND i.sectionid = ' . FLEXI_SECTION : '';

		/*
		 * If we have a filter, and this is enabled... lets tack the AND clause
		 * for the filter onto the WHERE clause of the item query.
		 */
		if ($params->get('use_search'))
		{
			$filter 		= JRequest::getString('filter', '', 'request');

			if ($filter)
			{
				$where .= ' AND MATCH (ie.search_index) AGAINST ('.$this->_db->Quote( $this->_db->getEscaped( $filter, true ), false ).' IN BOOLEAN MODE)';
			}
		}
		return $where;
	}
	
	
	/**
	 * Method to load parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadParams()
	{
		if (!empty($this->_params)) return;
		
		$mainframe =& JFactory::getApplication();

		// Get the PAGE/COMPONENT parameters
		$params = clone( $mainframe->getParams('com_flexicontent') );
		
		// In J1.6+ does not merge current menu item parameters, the above code behaves like JComponentHelper::getParams('com_flexicontent') was called
		if (FLEXI_J16GE) {
			$menuParams = new JRegistry;
			if ($menu = JSite::getMenu()->getActive()) {
				$menuParams->loadJSON($menu->params);
			}
			$params->merge($menuParams);
		}
		
		$this->_params = & $params;
	}
}
?>