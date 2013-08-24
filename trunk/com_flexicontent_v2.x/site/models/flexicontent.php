<?php
/**
 * @version 1.5 stable $Id: flexicontent.php 1670 2013-04-15 08:01:57Z ggppdk $
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
class FlexicontentModelFlexicontent extends JModelLegacy
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
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

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
		
		// Set id and load parameters
		$id = 0;  // no id used by this view
		$this->setId((int)$id);
		$params = & $this->_params;
		
		//get the root category of the directory
		$this->_rootcat = (int) JRequest::getInt('rootcat', 0);
		if ( !$this->_rootcat)
			// compatibility of old saved menu items, the value is inside params instead of being URL query variable
			$this->_rootcat = $params->get('rootcat', FLEXI_J16GE ? 1:0);
		else
			$params->set('rootcat', $this->_rootcat);
		
		//set limits
		$limit 			= $params->def('catlimit', 5);
		$limitstart	= JRequest::getInt('limitstart');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}
	
	
	/**
	 * Method to set initialize data, setting an element id for the view
	 *
	 * @access	public
	 * @param	int
	 */
	function setId($id)
	{
		//$this->_id      = $id;  // not used by current view
		$this->_rootcat = null;
		$this->_data    = null;
		$this->_total   = null;
		$this->_pagination = null;
		$this->_params  = null;
		$this->_loadParams();
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
	function _buildCatOrderBy($prefix)
	{
		$request_var = '';
		$config_param = $prefix.'orderby';
		$default_order = $this->getState('filter_order', 'c.title');
		$default_order_dir = $this->getState('filter_order_dir', 'ASC');
		
		// Precedence: $request_var ==> $order ==> $config_param ==> $default_order
		return flexicontent_db::buildCatOrderBy(
			$this->_params,
			$order='', $request_var, $config_param,
			$cat_tbl_alias = 'c', $user_tbl_alias = 'u',
			$default_order, $default_order_dir
		);
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
		$orderby = $this->_buildCatOrderBy('cat_');

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
		
		// Select only items that user has view access, if listing of unauthorized content is not enabled
		$subjoin = $suband = $join = $and = '';
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$suband .= ' AND ty.access IN (0,'.$aid_list.')';
				$suband .= ' AND cc.access IN (0,'.$aid_list.')';
				$suband .= ' AND i.access IN (0,'.$aid_list.')';
				$and    .= ' AND c.access IN (0,'.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$subjoin .= ' LEFT JOIN #__flexiaccess_acl AS sgt ON ty.id = sgt.axo AND sgt.aco = "read" AND sgt.axosection = "type"';
					$subjoin .= ' LEFT JOIN #__flexiaccess_acl AS sgc ON cc.id = sgc.axo AND sgc.aco = "read" AND sgc.axosection = "category"';
					$subjoin .= ' LEFT JOIN #__flexiaccess_acl AS sgi ON i.id = sgi.axo AND sgi.aco = "read" AND sgi.axosection = "item"';
					$suband  .= ' AND (sgt.aro IN ( '.$user->gmid.' ) OR ty.access <= '. $aid . ')';
					$suband  .= ' AND (sgc.aro IN ( '.$user->gmid.' ) OR cc.access <= '. $aid . ')';
					$suband  .= ' AND (sgi.aro IN ( '.$user->gmid.' ) OR i.access <= '. $aid . ')';
					$join    .= ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$and     .= ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. $aid . ')';
				} else {
					$suband  .= ' AND ty.access <= '.$aid;
					$suband  .= ' AND cc.access <= '.$aid;
					$suband  .= ' AND i.access <= '.$aid;
					$and     .= ' AND c.access <= '.$aid;
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
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
			. ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			. ' JOIN #__categories AS cc ON cc.id = rel.catid'
			. $subjoin
			. $where
			. $suband
			. ') AS assigneditems'
			
			. ' FROM #__categories AS c'
			. $join
			. ' WHERE c.published = 1'
			. (!FLEXI_J16GE ? ' AND c.section = '.FLEXI_SECTION : ' AND c.extension="'.FLEXI_CAT_EXTENSION.'" ' )
			. (!$this->_rootcat ? ' AND c.parent_id = '.(FLEXI_J16GE ? 1 : 0) : ' AND c.parent_id = '. (int)$this->_rootcat)
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
				$and		= ' AND c.access IN (0,'.$aid_list.')';
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
				. (!$this->_rootcat ? ' AND c.parent_id = '.(FLEXI_J16GE ? 1 : 0) : ' AND c.parent_id = '. (int)$this->_rootcat)
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
	 * Method to get a pagination object
	 *
	 * @access public
	 * @return integer
	 */
	public function getPagination() {
		// Load the content if it doesn't already exist
		if (empty($this->_pagination)) {
			//jimport('joomla.html.pagination');
			require_once (JPATH_COMPONENT.DS.'helpers'.DS.'pagination.php');
			$this->_pagination = new FCPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}
		return $this->_pagination;
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
		$orderby = $this->_buildCatOrderBy('subcat_');
		
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
		
		// Select only items that user has view access, if listing of unauthorized content is not enabled
		$subjoin = $suband = $join = $and = '';
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$suband .= ' AND ty.access IN (0,'.$aid_list.')';
				$suband .= ' AND cc.access IN (0,'.$aid_list.')';
				$suband .= ' AND i.access IN (0,'.$aid_list.')';
				$and    .= ' AND c.access IN (0,'.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$subjoin .= ' LEFT JOIN #__flexiaccess_acl AS sgt ON ty.id = sgt.axo AND sgt.aco = "read" AND sgt.axosection = "type"';
					$subjoin .= ' LEFT JOIN #__flexiaccess_acl AS sgc ON cc.id = sgc.axo AND sgc.aco = "read" AND sgc.axosection = "category"';
					$subjoin .= ' LEFT JOIN #__flexiaccess_acl AS sgi ON i.id = sgi.axo AND sgi.aco = "read" AND sgi.axosection = "item"';
					$suband  .= ' AND (sgt.aro IN ( '.$user->gmid.' ) OR ty.access <= '. $aid . ')';
					$suband  .= ' AND (sgc.aro IN ( '.$user->gmid.' ) OR cc.access <= '. $aid . ')';
					$suband  .= ' AND (sgi.aro IN ( '.$user->gmid.' ) OR i.access <= '. $aid . ')';
					$join    .= ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$and     .= ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. $aid . ')';
				} else {
					$suband  .= ' AND ty.access <= '.$aid;
					$suband  .= ' AND cc.access <= '.$aid;
					$suband  .= ' AND i.access <= '.$aid;
					$and     .= ' AND c.access <= '.$aid;
				}
			}
		}

		$query = 'SELECT c.*,'
			. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug,'
			
			. ' ('
			. ' SELECT COUNT( DISTINCT i.id )'
			. ' FROM #__content AS i'
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
			. ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			. ' JOIN #__categories AS cc ON cc.id = rel.catid'
			. $subjoin
			. $where
			. $suband
			. ' ) AS assignedsubitems,'
			
			. ' ('
			. ' SELECT COUNT( sc.id )'
			. ' FROM #__categories AS sc'
			. ' WHERE c.id = sc.parent_id'
			. ' AND sc.published = 1'
			. ' ) AS assignedcats'
			
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
			$andaccess  = ' AND c.access IN (0,'.$aid_list.')';
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
	
	
	/**
	 * Method to load parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadParams()
	{
		if ( $this->_params !== NULL ) return;
		
		$app  = JFactory::getApplication();
		$menu = JSite::getMenu()->getActive();     // Retrieve active menu
		
		// Get the COMPONENT only parameters, then merge the menu parameters
		$comp_params = JComponentHelper::getComponent('com_flexicontent')->params;
		$params = FLEXI_J16GE ? clone ($comp_params) : new JParameter( $comp_params ); // clone( JComponentHelper::getParams('com_flexicontent') );
		if ($menu) {
			$menu_params = FLEXI_J16GE ? $menu->params : new JParameter($menu->params);
			$params->merge($menu_params);
		}
				
		$this->_params = $params;
	}
	
	
	/**
	 * Method to get view's parameters
	 *
	 * @access public
	 * @return object
	 */
	function &getParams()
	{
		return $this->_params;
	}
}
?>