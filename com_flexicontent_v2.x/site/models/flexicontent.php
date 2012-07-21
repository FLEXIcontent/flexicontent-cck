<?php
/**
 * @version 1.5 stable $Id: flexicontent.php 1275 2012-05-09 06:51:20Z ggppdk $
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
 * @subpackage Flexicontent
 * @since		1.0
 */
class FlexicontentModelFlexicontent extends JModel
{
	/**
	 * Root category from this directory
	 *
	 * @var int
	 */
	var $_rootcat = null;
	
	/**
	 * data
	 *
	 * @var object
	 */
	var $_data = null;
	
	/**
	 * total
	 *
	 * @var int
	 */
	var $_total = null;

	/**
	 * parameters
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
		
		//get the root category of the directory
		$this->_rootcat = JRequest::getVar('rootcat', false);
		if ( $this->rootcat===false )
			// compatibility of old saved menu items, the value is inside params instead of being URL query variable
			$this->_rootcat = $params->get('rootcat', FLEXI_J16GE ? 1:0);
		else
			$params->set('rootcat', $this->_rootcat);
		
		//set directory parameters
		$this->_params = & $params;
		
		//set limits
		$limit 			= $params->def('catlimit', 5);
		$limitstart		= JRequest::getInt('limitstart');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

	}
	
	/**
	 * Method to get Data
	 *
	 * @access public
	 * @return object
	 */
	function getData()
	{
		// Lets load the categories if it doesn't already exist
		if (empty($this->_data))
		{
			//get data
			$this->_data = $this->_getList( $this->_buildQuery(), $this->getState('limitstart'), $this->getState('limit') );
			
			//add childs of each category
			$k = 0;
			$count = count($this->_data);
			for($i = 0; $i < $count; $i++)
			{
				$category 			=& $this->_data[$i];
				$category->subcats	= $this->_getsubs( $category->id );

				$k = 1 - $k;
			}
		}

		return $this->_data;
	}
	
	
	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildItemOrderBy($prefix)
	{
		$params = $this->_params;
		
		$filter_order     = $this->getState('filter_order');
		$filter_order_dir = $this->getState('filter_order_dir');
		
		$filter_order     = $filter_order     ? $filter_order      :  'c.title';
		$filter_order_dir = $filter_order_dir ? $filter_order_dir  :  'ASC';

		if ($params->get($prefix.'orderby')) {
			$order = $params->get($prefix.'orderby');
			
			switch ($order) {
				case 'date' :                  // *** J2.5 only ***
				$filter_order		= 'c.created_time';
				$filter_order_dir	= 'ASC';
				break;
				case 'rdate' :                 // *** J2.5 only ***
				$filter_order		= 'c.created_time';
				$filter_order_dir	= 'DESC';
				break;
				case 'modified' :              // *** J2.5 only ***
				$filter_order		= 'c.modified_time';
				$filter_order_dir	= 'DESC';
				break;
				case 'alpha' :
				$filter_order		= 'c.title';
				$filter_order_dir	= 'ASC';
				break;
				case 'ralpha' :
				$filter_order		= 'c.title';
				$filter_order_dir	= 'DESC';
				break;
				case 'author' :                // *** J2.5 only ***
				$filter_order		= 'u.name';
				$filter_order_dir	= 'ASC';
				break;
				case 'rauthor' :               // *** J2.5 only ***
				$filter_order		= 'u.name';
				$filter_order_dir	= 'DESC';
				break;
				case 'hits' :                  // *** J2.5 only ***
				$filter_order		= 'c.hits';
				$filter_order_dir	= 'ASC';
				break;
				case 'rhits' :                 // *** J2.5 only ***
				$filter_order		= 'c.hits';
				$filter_order_dir	= 'DESC';
				break;
				case 'order' :
				$filter_order		= !FLEXI_J16GE ? 'c.ordering' : 'c.lft';
				$filter_order_dir	= 'ASC';
				break;
			}
			
		}
		
		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_dir;
		$orderby .= $filter_order!='c.title' ? ', c.title' : '';   // Order by title after default ordering

		return $orderby;
	}
	
	
	/**
	 * Method to build the Categories query
	 * This method creates the first level categories and their assigned item count
	 *
	 * @access private
	 * @return string
	 */
	function _buildQuery()
	{
		$params = $this->_params;

		$user = & JFactory::getUser();
		$orderby = $this->_buildItemOrderBy('cat_');

		// Get a 2 character language tag
		$lang = flexicontent_html::getUserCurrentLang();

		// Do we filter the categories
		$filtercat  = $params->get('filtercat', 0);
		// show unauthorized items
		$show_noauth = $params->get('show_noauth', 0);

		// Build where clause
		$where  = ' WHERE cc.published = 1';
		$where .= ' AND c.id = cc.id';
		// Filter the category view with the active active language
		if ((FLEXI_FISH || FLEXI_J16GE) && $filtercat) {
			$where .= ' AND ( ie.language LIKE ' . $this->_db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
		}

		$states = ((int)$user->get('gid') > 19) ? '1, -5, 0, -3, -4' : '1, -5';
		$where .= ' AND i.state IN ('.$states.')';
		
		// Select only items user has access to if he is not allowed to show unauthorized items
		$subjoin = $suband = $join = $and = '';
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$suband	= ' AND i.access IN ('.$aid_list.') AND cc.access IN ('.$aid_list.')';
				$and		= ' AND c.access IN ('.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$subjoin  = ' LEFT JOIN #__flexiaccess_acl AS sgc ON cc.id = sgc.axo AND sgc.aco = "read" AND sgc.axosection = "category"';
					$subjoin .= ' LEFT JOIN #__flexiaccess_acl AS sgi ON i.id = sgi.axo AND sgi.aco = "read" AND sgi.axosection = "item"';
					$suband   = ' AND (sgc.aro IN ( '.$user->gmid.' ) OR cc.access <= '. $aid . ')';
					$suband  .= ' AND (sgi.aro IN ( '.$user->gmid.' ) OR i.access <= '. $aid . ')';
					$join     = ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$and      = ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. $aid . ')';
				} else {
					$suband   = ' AND cc.access <= '.$aid.' AND i.access <= '.$aid;
					$and      = ' AND c.access <= '.$aid;
				}
			}
		}
		$join .= (FLEXI_J16GE ? ' LEFT JOIN #__users AS u ON u.id = c.created_user_id' : '');

		$query = 'SELECT c.*,'
				. (FLEXI_J16GE ? ' u.name as author,' : '')
				. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug,'
					. ' ('
					. ' SELECT COUNT( DISTINCT i.id )'
					. ' FROM #__content AS i'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
					. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
					. ' LEFT JOIN #__categories AS cc ON cc.id = rel.catid'
					. $subjoin
					. $where
					. $suband
					. ')' 
					. ' AS assigneditems'
				. ' FROM #__categories AS c'
				. $join
				. ' WHERE c.published = 1'
				. (!FLEXI_J16GE ? ' AND c.section = '.FLEXI_SECTION : ' AND c.extension="'.FLEXI_CAT_EXTENSION.'" ' )
				. ' AND c.parent_id = '.$this->_rootcat
				. $and
				. $orderby
				;
		return $query;
	}

	/**
	 * Method to build the Categories query without subselect
	 * That's enough to get the total value.
	 *
	 * @access private
	 * @return string
	 */
	function _buildQueryTotal()
	{
		$params = $this->_params;

		$user = & JFactory::getUser();
		
		// show unauthorized items
		$show_noauth = $params->get('show_noauth', 0);
		
		// Select only items user has access to if he is not allowed to show unauthorized items
		$join = $and = '';
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$and		= ' AND c.access IN ('.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$join  = ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$and   = ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. $aid . ')';
				} else {
					$and   = ' AND c.access <= '.$aid;
				}
			}
		}

		$query 	= 'SELECT c.id'
				. ' FROM #__categories AS c'
				. $join
				. ' WHERE c.published = 1'
				. (!FLEXI_J16GE ? ' AND c.section = '.FLEXI_SECTION : ' AND c.extension="'.FLEXI_CAT_EXTENSION.'" ' )
				. ' AND c.parent_id = ' . $this->_rootcat
				. $and
				;

		return $query;
	}
	
	/**
	 * Total nr of Categories
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQueryTotal();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}
	
	/**
	 * Method to fetch the subcategories
	 *
	 * @access private
	 * @return object
	 */
	function _getsubs($id)
	{
		$params = $this->_params;

		$user = & JFactory::getUser();
		$orderby = $this->_buildItemOrderBy('subcat_');
		
		// Get a 2 character language tag
		$lang = flexicontent_html::getUserCurrentLang();
		
		// Do we filter the categories
		$filtercat  = $params->get('filtercat', 0);
		// show unauthorized items
		$show_noauth = $params->get('show_noauth', 0);

		// Build where clause
		$where  = ' WHERE cc.published = 1';
		$where .= ' AND c.id = cc.id';
		// Filter the category view with the active active language
		if ((FLEXI_FISH || FLEXI_J16GE) && $filtercat) {
			$where .= ' AND ( ie.language LIKE ' . $this->_db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
		}

		$states = ((int)$user->get('gid') > 19) ? '1, -5, 0, -3, -4' : '1, -5';
		$where .= ' AND i.state IN ('.$states.')';
		
		// Select only items user has access to if he is not allowed to show unauthorized items
		$subjoin = $suband = $join = $and = '';
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$suband	= ' AND i.access IN ('.$aid_list.') AND cc.access IN ('.$aid_list.')';
				$and		= ' AND c.access IN ('.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$subjoin  = ' LEFT JOIN #__flexiaccess_acl AS sgc ON cc.id = sgc.axo AND sgc.aco = "read" AND sgc.axosection = "category"';
					$subjoin .= ' LEFT JOIN #__flexiaccess_acl AS sgi ON i.id = sgi.axo AND sgi.aco = "read" AND sgi.axosection = "item"';
					$suband	  = ' AND (sgc.aro IN ( '.$user->gmid.' ) OR cc.access <= '. $aid . ')';
					$suband  .= ' AND (sgi.aro IN ( '.$user->gmid.' ) OR i.access <= '. $aid . ')';
					$join  	  = ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$and	  = ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. $aid . ')';
				} else {
					$suband   = ' AND cc.access <= '.$aid;
					$suband  .= ' AND i.access <= '.$aid;
					$and   	  = ' AND c.access <= '.$aid;
				}
			}
		}

		$query = 'SELECT c.*,'
				. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug,'
					. ' ('
					. ' SELECT COUNT( DISTINCT i.id )'
					. ' FROM #__content AS i'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
					. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
					. ' LEFT JOIN #__categories AS cc ON cc.id = rel.catid'
					. $subjoin
					. $where
					. $suband
					. ')' 
					. ' AS assignedsubitems,'
					. ' ('
					. ' SELECT COUNT( sc.id )'
					. ' FROM #__categories AS sc'
					. ' WHERE c.id = sc.parent_id'
					. ' AND sc.published = 1'
					. ')' 
					. ' AS assignedcats'
				. ' FROM #__categories AS c'
				. $join
				. ' WHERE c.published = 1'
				. (!FLEXI_J16GE ? ' AND c.section = '.FLEXI_SECTION : ' AND c.extension="'.FLEXI_CAT_EXTENSION.'" ' )
				. ' AND c.parent_id = '.(int)$id
				. $and
				. $orderby
				;

		$this->_db->setQuery($query);
		$this->_subs = $this->_db->loadObjectList();

		return $this->_subs;
	}
	
	/**
	 * Get the feed data
	 *
	 * @access public
	 * @return object
	 */
	function getFeed()
	{
		$params = $this->_params;

		$user = &JFactory::getUser();
		$limit 		= JRequest::getVar('limit', 10);
		
		// Get a 2 character language tag
		$lang = flexicontent_html::getUserCurrentLang();
		
		// Do we filter the categories
		$filtercat  = $params->get('filtercat', 0);

		// Filter the category view with the active active language
		if ((FLEXI_FISH || FLEXI_J16GE) && $filtercat) {
			$where .= ' AND ( ie.language LIKE ' . $this->_db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
		}
		
		// WE DO NOT show_noauth parameter in FEEDs ... we only list authorised ...
		if (FLEXI_J16GE) {
			$aid_arr  = $user->getAuthorisedViewLevels();
			$aid_list = implode(",", $aid_arr);
			$andaccess  = ' AND c.access IN ('.$aid_list.')';
		} else {
			$aid = (int) $user->get('aid');
			$andaccess  = ' AND c.access <= '.$aid;
		}

		$query 	= 'SELECT DISTINCT i.*, ie.*, c.title AS cattitle,'
				. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				. ' FROM #__flexicontent_items AS i'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
				. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
				. ' WHERE c.published = 1'
				. (!FLEXI_J16GE ? ' AND c.section = '.FLEXI_SECTION : ' AND c.extension="'.FLEXI_CAT_EXTENSION.'" ' )
				. $andaccess
				. $and
				. ' AND i.state IN (1, -5)'
				. ' LIMIT '. $limit
				;
		
		$this->_db->setQuery($query);
		$feed = $this->_db->loadObjectList();

		return $feed;
	}
}
?>