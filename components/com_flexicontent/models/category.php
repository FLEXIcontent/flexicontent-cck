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
 * @subpackage Flexicontent
 * @since		1.0
 */
class FlexicontentModelCategory extends JModel {
	/**
	 * Category id
	 *
	 * @var int
	 */
	var $_id = null;
	
	/**
	 * Category properties
	 *
	 * @var object
	 */
	var $_category = null;

	/**
	 * Array of Subcategories properties
	 *
	 * @var mixed
	 */
	var $_childs = null;
	
	/**
	 * Category/Subcategory (current page) ITEMS  (belonging to $_data_cats Categories)
	 *
	 * @var mixed
	 */
	var $_data = null;
	
	/**
	 * Array of subcategory ids (includes category id too) used to create the ITEMS list
	 *
	 * @var array
	 */
	var $_data_cats = array();
	
	/**
	 * Count of the total (not just current page) Category/Subcategory ITEMS
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Category parameters, merged with (a) Component and (b) Current Joomla menu item
	 *
	 * @var object
	 */
	var $_params = null;

	/**
	 * Category author (used by AUTHOR layout)
	 *
	 * @var integer
	 */
	var $_authorid = 0;
	
	/**
	 * Category layout
	 *
	 * @var string
	 */
	var $_layout = 'category';
	
	/**
	 * Comments information for cat's items
	 *
	 * @var string
	 */
	var $_comments = null;
	
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct()
	{
		// Set category id and call constrcuctor
		$cid		= JRequest::getInt('cid', 0);
		$this->setId((int)$cid);  // This will set the category id and clear all member variables
		parent::__construct();
		
		// Populate state data, if category id is changed this function must be called again
		$this->populateCategoryState();
	}
	
	/**
	 * Method to populate the categry model state.
	 *
	 * return	void
	 * @since	1.5
	 */
	protected function populateCategoryState($ordering = null, $direction = null) {
		$this->_layout = JRequest::getVar('layout', 'category');
		
		if ($this->_layout=='author') {
			$this->_authorid = JRequest::getInt('authorid', 0);
		} else if ($this->_layout=='myitems') {
			$user =& JFactory::getUser();
			if ($user->guest) {
				JError::raiseError(404, JText::_( 'FLEXI_LOGIN_TO_DISPLAY_YOUR_CONTENT'));
			}
			$this->_authorid = $user->id;
		}
		
		// Set layout and authorid variables into state
		$this->setState('layout', $this->_layout);
		$this->setState('authorid', $this->_authorid);

		// We need to merge parameters here to get the correct page limit value, we must call this after populating layput and author variables
		$this->_params = $this->_loadCategoryParams($this->_id);
		$params = $this->_params;

		// Set the pagination variables into state (We get them from http request OR use default category parameters)
		$limit = JRequest::getVar('limit') ? JRequest::getVar('limit') : $params->get('limit');
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		// Set filter order variables into state
		$this->setState('filter_order', 	JRequest::getCmd('filter_order', 'i.title'));
		$this->setState('filter_order_dir', JRequest::getCmd('filter_order_Dir', 'ASC'));
	}


	/**
	 * Method to set the category id
	 *
	 * @access	public
	 * @param	int	category ID number
	 */
	function setId($cid)
	{
		// Set new category ID and wipe data
		if ($this->_id != $cid) {
			$this->_category  = null;
			$this->_childs    = null;
			$this->_data      = null;
			$this->_data_cats = null;
			$this->_total     = null;
			$this->_params    = null;
			$this->_comments = null;
		}
		$this->_id = $cid;
	}		


	/**
	 * Method to get Data
	 *
	 * @access public
	 * @return mixed
	 */
	function getData()
	{
		$format	= JRequest::getVar('format', null);
		
		// Allow limit zero to achieve a category view without items
		if ($this->getState('limit') <= 0)
		{
			$this->_data = array();
		}
		else if (empty($this->_data))
		{
			// Load the content if it doesn't already exist
			$query = $this->_buildQuery();

			$this->_total = $this->_getListCount($query);
			if ($this->_db->getErrorNum()) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage('SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
			}

			if ((int)$this->getState('limitstart') < (int)$this->_total) {
				$this->_data = $this->_getList( $query, $this->getState('limitstart'), $this->getState('limit') );
			} else {
				$this->setState('limitstart',0);
				$this->setState('start',0);
				JRequest::setVar('start',0);
				JRequest::setVar('limitstart',0);
				$this->_data = $this->_getList( $query, 0, $this->getState('limit') );
			}
			if ($this->_db->getErrorNum()) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage('SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
			}
		}

		return $this->_data;
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
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}
	
	
	/**
	 * Method to get the category pagination
	 *
	 * @access	public
	 * @return	string
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
	 * Returns category parameters (merged in order: component, menu item, category and author overidden params)
	 *
	 * @access public
	 * @return integer
	 */
	function & getParams()
	{
		return $this->_params;
	}


	/**
	 * Builds the query
	 *
	 * @access public
	 * @return string
	 */
	function _buildQuery()
	{
		static $query;
		if(!$query) {
			// Get the WHERE and ORDER BY clauses for the query
			$where			= $this->_buildItemWhere();
			$orderby		= $this->_buildItemOrderBy();
			$params = $this->_params;

			// Add sort items by custom field. Issue 126 => http://code.google.com/p/flexicontent/issues/detail?id=126#c0
			$order_field_join = '';
			if ($params->get('orderbycustomfieldid', 0) != 0) {
				$order_field_join = ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.(int)$params->get('orderbycustomfieldid', 0);
			}
			
			// Add image field used as item image in RSS feed
			$feed_image_source = 0;
			if (JRequest::getVar("type", "") == "rss") {
				$feed_image_source = (int) $params->get('feed_image_source', '');
			}
			$feed_img_join= '';
			$feed_img_col = '';
			if ($feed_image_source) {
				$feed_img_join = ' LEFT JOIN #__flexicontent_fields_item_relations AS img ON img.item_id = i.id AND img.field_id='.$feed_image_source;
				$feed_img_col = ' img.value as image,';
			}
			
			$query = 'SELECT DISTINCT i.*, ie.*, u.name as author, ty.name AS typename,'.$feed_img_col 
			. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
			. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ' FROM #__content AS i'
			. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
			. $feed_img_join
			. $order_field_join
		//. ' LEFT JOIN #__categories AS c ON c.id = '. $this->_id
			. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
			. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			. $where
			. $orderby
			;
		}
		return $query;
	}

	/**
	 * Retrieve author item
	 *
	 * @access public
	 * @return string
	 */
	function getAuthorDescrItem() {
		$params = $this->_params;
		$authordescr_itemid = $params->get('authordescr_itemid', 0);
		if (!$authordescr_itemid) return false;
		
		$query = 'SELECT DISTINCT i.*, ie.*, u.name as author, ty.name AS typename,'
		. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
		. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
		. ' FROM #__content AS i'
		. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
		. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
		. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
		. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
		. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
		.  ' WHERE i.id='. $authordescr_itemid
		;
		
		$this->_db->setQuery($query);
		$authorItem = $this->_db->loadObject();
		
		return $authorItem;
	}
	
	
	/**
	 * Retrieve subcategory ids of a given category
	 *
	 * @access public
	 * @return string
	 */
	function & _getDataCats($id)
	{
		if ( $this->_data_cats ) return $this->_data_cats;

		global $globalcats;
		$cparams = & $this->_params;
		$user 		= &JFactory::getUser();
		$ordering	= FLEXI_J16GE ? 'lft ASC' : 'ordering ASC';

		// show unauthorized items
		$show_noauth = $cparams->get('show_noauth', 0);
		
		// filter by permissions
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$andaccess = ' AND c.access IN ('.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$readperms = FAccess::checkUserElementsAccess($user->gmid, 'read');
					if (isset($readperms['category']) && count($readperms['category']) ) {
						$andaccess = ' AND ( c.access <= '.$aid.' OR c.id IN ('.implode(",", $readperms['category']).') )';
					} else {
						$andaccess = ' AND c.access <= '.$aid;
					}
				} else {
					$andaccess = ' AND c.access <= '.$aid;
				}
			}
		} else {
			$andaccess = '';
		}		
		
		// filter by depth level
		$display_subcats = $cparams->get('display_subcategories_items', 0);
		if ($display_subcats==0) {
			//$anddepth = ' AND c.id = '. $this->_id;
			$this->_data_cats = array($id);
			return $this->_data_cats;
		} else if ($display_subcats==1) {
			$anddepth = ' AND ( c.parent_id = ' .$id. ' OR c.id='.$id.')';
		} else {
			$catlist = !empty($globalcats[$id]->descendants) ? $globalcats[$id]->descendants : $id;
			$anddepth = ' AND c.id IN ('.$catlist.')';
		}
		
		// finally create the query string
		$query = 'SELECT c.id'
			. ' FROM #__categories AS c'
			. ' WHERE c.published = 1'
			. $andaccess
			. $anddepth
			. ' ORDER BY '.$ordering
			;
		
		$this->_db->setQuery($query);
		$this->_data_cats = $this->_db->loadResultArray();
		if ( $this->_db->getErrorNum() ) {
			$jAp=& JFactory::getApplication();
			$jAp->enqueueMessage('SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
		}
		
		return $this->_data_cats;
	}
	
	
	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildItemOrderBy()
	{
		$mainframe = &JFactory::getApplication();
		$params = & $this->_params;
		
		// NOTE: *** 'filter_order' AND 'filter_order_dir' are the real ORDERING DB column names
		
		// Get ordering columns set in state
		$filter_order     = $this->getState('filter_order');
		$filter_order_dir = $this->getState('filter_order_dir');
		
		// If ordering columns state were not set in state use ASCENDING title as fall back default
		$filter_order     = $filter_order     ? $filter_order      :  'i.title';
		$filter_order_dir = $filter_order_dir ? $filter_order_dir  :  'ASC';
		
		// NOTE: *** 'orderby' is a symbolic order variable ***
		
		// Get user setting, and if not set, then fall back to category / global configuration setting
		$request_orderby = JRequest::getVar('orderby');
		
		// A symbolic order name to indicate using the category / global ordering setting
		$order = $request_orderby ? $request_orderby : $params->get('orderby');
		
		if ($order)
		{
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
		if (empty($request_orderby) && $params->get('orderbycustomfieldid', 0) != 0)
		{
			if ($params->get('orderbycustomfieldint', 0) != 0) $int = ' + 0'; else $int ='';
			$filter_order		= 'f.value'.$int;
			$filter_order_dir	= $params->get('orderbycustomfielddir', 'ASC');
		}
		
		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_dir;
		$orderby .= $filter_order!='i.title' ? ', i.title' : '';   // Order by title after default ordering
		
		return $orderby;
	}
	
	
	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return array
	 */
	function _buildItemWhere( $no_alpha=0 )
	{
		global $globalcats, $currcat_data;
		if ( $no_alpha && isset($currcat_data['where_no_alpha']) ) return $currcat_data['where_no_alpha'];
		if ( isset($currcat_data['where']) ) return $currcat_data['where'];
		
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		$user		= & JFactory::getUser();
		$db =& JFactory::getDBO();
		
		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		//  thus the items are published globally at the time the author specified in his/her local clock
		//$now		= $mainframe->get('requestTime');
		$now			= JFactory::getDate()->toMySQL();
		$nullDate	= $db->getNullDate();
		
		$cparams = & $this->_params;                      // Get the category parameters
		$lang = flexicontent_html::getUserCurrentLang();  // Get user current language
		$catlang = $cparams->get('language', '');         // Category language parameter, currently UNUSED
		$filtercat  = $cparams->get('filtercat', 0);      // Filter items using currently selected language
		$show_noauth = $cparams->get('show_noauth', 0);   // Show unauthorized items
		
		// First thing we need to do is to select only the requested items
		$where = ' WHERE 1=1';
		if ($this->_authorid)
			$where .= ' AND i.created_by = ' . $this->_db->Quote($this->_authorid);
		if ($this->_id) {
			// Get sub categories used to create items list, according to configuration and user access
			$_data_cats = $this->_getDataCats($this->_id);
			$_data_cats = "'".implode("','", $this->_data_cats)."'";
			$where .= ' AND rel.catid IN ('.$_data_cats.')';
		} 
		
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
		$where .= ' AND ( i.state IN ('.$states.') OR i.created_by = '.$user->id.' )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		
		// Limit by publication date. Exception: when displaying personal user items or items modified by the user
		$where .= ' AND ( ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$this->_db->Quote($now).' ) OR i.created_by = '.$user->id.' )';       //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		$where .= ' AND ( ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$this->_db->Quote($now).' ) OR i.created_by = '.$user->id.' )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		
		// Filter the category view with the active language
		if ((FLEXI_FISH || FLEXI_J16GE) && $filtercat) {
			$where .= ' AND ( ie.language LIKE ' . $this->_db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
		}
		
		$where .= !FLEXI_J16GE ? ' AND i.sectionid = ' . FLEXI_SECTION : '';

		// Select only items user has access view, if showing unauthorized items is not enabled
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$where .= ' AND i.access IN ('.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$readperms = FAccess::checkUserElementsAccess($user->gmid, 'read');
					if ( isset($readperms['item']) && count($readperms['item']) ) {
						$where .= ' AND ( i.access <= '.$aid.' OR i.id IN ('.implode(",", $readperms['item']).') OR i.created_by = '.$user->id.' )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
					} else {
						$where .= ' AND ( i.access <= '.$aid.' OR i.created_by = '.$user->id.' )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
					}
				} else {
					$where .= ' AND ( i.access <= '.$aid.' OR i.created_by = '.$user->id.' )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
				}
			}
		}

		// Get session
		$session  =& JFactory::getSession();
		
		/*
		 * If we have a filter, and this is enabled... lets tack the AND clause
		 * for the filter onto the WHERE clause of the item query.
		 */
		if ( $cparams->get('use_filters') || $cparams->get('use_search') )
		{
			if ($this->_id) {
				$filter  = $mainframe->getUserStateFromRequest( $option.'.category'.$this->_id.'.filter', 'filter', '', 'string' );
			} else if ($this->_authorid) {
				$filter  = $mainframe->getUserStateFromRequest( $option.'.author'.$this->_authorid.'.filter', 'filter', '', 'string' );
			} else {
				$filter  = JRequest::getVar('filter', NULL, 'default');
			}
			
			if ($filter)
			{
				$where .= ' AND MATCH (ie.search_index) AGAINST ('.$this->_db->Quote( $this->_db->getEscaped( $filter, true ), false ).' IN BOOLEAN MODE)';
			}
		}
		
		$filters = $this->getFilters();

		if ($filters)
		{
			foreach ($filters as $filtre)
			{
				if ($this->_id) {
					$filtervalue 	= $mainframe->getUserStateFromRequest( $option.'.category'.$this->_id.'.filter_'.$filtre->id, 'filter_'.$filtre->id, '', 'string' );
				} else if ($this->_authorid) {
					$filtervalue  = $mainframe->getUserStateFromRequest( $option.'.author'.$this->_authorid.'.filter_'.$filtre->id, 'filter_'.$filtre->id, '', 'string' );
				} else {
					$filtervalue  = JRequest::getString('filter_'.$filtre->id, '', 'default');
				}
				if (strlen($filtervalue)>0) {
					$where .= $this->_getFiltered($filtre->id, $filtervalue, $filtre->field_type);
				}
			}
		}
		
		// Featured items, this item property exists in J1.6+ only
		if (FLEXI_J16GE) {
			$featured = $cparams->get('featured');
			switch ($featured) {
				case 'show': $where .= ' AND i.featured=1'; break;
				case 'hide': $where .= ' AND i.featured=0'; break;
				default: break;
			}
		}
		
		// In case alpha parsing fails ...
		$currcat_data['where_no_alpha'] = $where;
		$currcat_data['where'] = $where;
		
		$alpha = JRequest::getVar('letter', NULL, 'request');
		/*if($alpha===NULL) {
			$alpha =  $session->get($option.'.category.letter');
		} else {
			$session->set($option.'.category.letter', $alpha);
		}*/
		
		// WARNING DO THIS because utf8 is multibyte and MySQL regexp doesnot support multibyte, so we cannot use [] with utf8
		$range = explode("-", $alpha);
		
		$regexp='';
		if (JString::strlen($alpha)==0) {
			// nothing to do
		} else if (count($range) > 2) {
			echo "Error in Alpha Index please correct letter range: ".$alpha."<br>";
		} else if (count($range) == 1) {
			
			$regexp = '"^('.JString::substr($alpha,0,1);
			for($i=1; $i<JString::strlen($alpha); $i++) :
				$regexp .= '|'.JString::substr($alpha,$i,1);
			endfor;
			$regexp .= ')"';
			
		} else if (count($range) == 2) {
			
			// Get range characters
			$startletter = $range[0];  $endletter = $range[1];
			
			// ERROR CHECK: Range START and END are single character strings
			if (JString::strlen($startletter) != 1 || JString::strlen($endletter) != 1) {
				echo "Error in Alpha Index<br>letter range: ".$alpha." start and end must be one character<br>";
				return $where;
			}
			
			// Get ord of characters and their rangle length
			$startord=FLEXIUtilities::uniord($startletter);
			$endord=FLEXIUtilities::uniord($endletter);
			$range_length = $endord - $startord;
			
			// ERROR CHECK: Character range has at least one character
			if ($range_length > 200 || $range_length < 1) {
				// A sanity check that the range is something logical and that 
				echo "Error in Alpha Index<br>letter range: ".$alpha.", is incorrect or contains more that 200 characters<br>";
				return $where;
			}
			
			// Check if any character out of the range characters exists
			// Meaning (There is at least on item title starting with one of the range characters)
			$regexp = '"^('.$startletter;
			for($uord=$startord+1; $uord<=$endord; $uord++) :
				$regexp .= '|'.FLEXIUtilities::unichr($uord);
			endfor;
			$regexp .= ')"';
			
		} else {
			echo "Error in Alpha Index<br>incorrect letter range: ".$alpha."<br>";
		}
		
		if ($regexp) {
			
			if ($alpha == '0') {
				$where .= ' AND ( CONVERT (( i.title ) USING BINARY) REGEXP CONVERT ('.$regexp.' USING BINARY) )' ;
				//$where .= ' AND LOWER( i.title ) RLIKE '.$this->_db->Quote( $this->_db->getEscaped( '^['.$alpha.']', true ), false );
			}
			elseif (!empty($alpha)) {
				$where .= ' AND ( CONVERT (LOWER( i.title ) USING BINARY) REGEXP CONVERT ('.$regexp.' USING BINARY) )' ;
				//$where .= ' AND LOWER( i.title ) RLIKE '.$this->_db->Quote( $this->_db->getEscaped( '^['.$alpha.']', true ), false );
			}
		}
		
		return $currcat_data['where'] = $where;
	}


	/**
	 * Method to build the childs categories query
	 *
	 * @access private
	 * @return string
	 */
	function _buildChildsQuery()
	{
		$user 		= &JFactory::getUser();
		$ordering	= FLEXI_J16GE ? 'lft ASC' : 'ordering ASC';

		// Get the category parameters
		$cparams = & $this->_params;
		// show unauthorized items
		$show_noauth = $cparams->get('show_noauth', 0);
		
		// filter by permissions
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$andaccess = ' AND c.access IN ('.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$readperms = FAccess::checkUserElementsAccess($user->gmid, 'read');
					if (isset($readperms['category']) && count($readperms['category']) ) {
						$andaccess = ' AND ( c.access <= '.$aid.' OR c.id IN ('.implode(",", $readperms['category']).') )';
					} else {
						$andaccess = ' AND c.access <= '.$aid;
					}
				} else {
					$andaccess = ' AND c.access <= '.$aid;
				}
			}
		} else {
			$andaccess = '';
		}

		$query = 'SELECT c.*,'
			. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug'
			. ' FROM #__categories AS c'
			. ' WHERE c.published = 1'
			. ' AND c.parent_id = '. $this->_id
			. $andaccess
			. ' ORDER BY '.$ordering
			;
		return $query;
	}
	
	
	/**
	 * Method to get the assigned items for a category
	 *
	 * @access private
	 * @return int
	 */
	function _getassigned($id)
	{
		global $globalcats;
		$mainframe = &JFactory::getApplication();
		$user 		= &JFactory::getUser();

		// Get the category parameters
		$cparams = & $this->_params;
		
		// Show assigned items, this should not cause problems, category parameters says not to display itemcount for subcategories
		if ( !$cparams->get('show_itemcount', 0) ) return null;
		
		// Get some parameters and other info
		$catlang = $cparams->get('language', '');          // category language (currently UNUSED), this is property in J2.5 instead of as parameter in FC J1.5
		$lang = flexicontent_html::getUserCurrentLang();   // Get user current language
		$filtercat  = $cparams->get('filtercat', 0);       // Filter items using currently selected language
		$show_noauth = $cparams->get('show_noauth', 0);    // Show unauthorized items
		
		// Limit by access
		$joinaccess   = FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gc ON cc.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"' : '' ;
		$joinaccess2  = FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gi ON i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"' : '' ;
		$where        = ' WHERE cc.published = 1';

		// Filter the category view with the current user language
		if ((FLEXI_FISH || FLEXI_J16GE) && $filtercat) {
			$where .= ' AND ( ie.language LIKE ' . $this->_db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR ie.language="*" ' : '') . ' ) ';
		}
		
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
		$where .= ' AND ( i.state IN ('.$states.') OR i.created_by = '.$user->id.' )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		
		// Count items according to full depth level !!!
		$catlist = !empty($globalcats[$id]->descendants) ? $globalcats[$id]->descendants : $id;
		$where .= ' AND rel.catid IN ('.$catlist.')';
		
		// Select only items user has access to if he is not allowed to show unauthorized items
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$where .= ' AND i.access IN ('.$aid_list.') AND cc.access IN ('.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$where .= ' AND (gc.aro IN ( '.$user->gmid.' ) OR cc.access <= '. (int) $aid . ')';
					$where .= ' AND (gi.aro IN ( '.$user->gmid.' ) OR i.access <= '. (int) $aid . ')';
				} else {
					$where .= ' AND cc.access <= '.$aid;
					$where .= ' AND i.access <= '.$aid;
				}
			}
		}
		
		$query 	= 'SELECT DISTINCT itemid'
				. ' FROM #__flexicontent_cats_item_relations AS rel'
				. ' LEFT JOIN #__content AS i ON rel.itemid = i.id'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id'
				. ' LEFT JOIN #__categories AS cc ON cc.id = rel.catid'
				. $joinaccess
				. $joinaccess2
				. $where
				;
		
		$this->_db->setQuery($query);
		$assigneditems = count($this->_db->loadResultArray());
		if ( $this->_db->getErrorNum() ) {
			$jAp=& JFactory::getApplication();
			$jAp->enqueueMessage('SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
		}
		
		return $assigneditems;
	}

	/**
	 * Method to build the Categories query
	 * todo: see above and merge
	 *
	 * @access private
	 * @return array
	 */
	function _getsubs($id)
	{
		$cparams = & $this->_params;
		$show_noauth	= $cparams->get('show_noauth', 0);
		$user			= &JFactory::getUser();

		// Access
		$joinaccess		= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gc ON sc.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"' : '' ;
		
		// Where
		$where = ' WHERE sc.published = 1';
		$where .= ' AND sc.parent_id = '. (int)$id;
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr	= $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$where .= ' AND sc.access IN ( '.$aid_list.' )';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$where .= ' AND (gc.aro IN ( '.$user->gmid.' ) OR sc.access <= '. (int) $aid . ')';
				} else {
					$where .= ' AND sc.access <= '.$aid;
				}
			}
		}
		
		// Order
		$ordering	= FLEXI_J16GE ? 'lft ASC' : 'ordering ASC';
		$orderby = ' ORDER BY '.$ordering;
		
		$query = 'SELECT DISTINCT *,'
			. ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', sc.id, sc.alias) ELSE sc.id END as slug'
			. ' FROM #__categories as sc'
			. $joinaccess
			. $where
			. $orderby
			;

		$this->_db->setQuery($query);
		$subcats = $this->_db->loadObjectList();
		
		return $subcats;
	}

	/**
	 * Method to get the children of a category
	 *
	 * @access private
	 * @return array
	 */

	function getChilds()
	{
		$query = $this->_buildChildsQuery();
		$this->_childs = $this->_getList($query);
		$id = $this->_id;  // save id in case we need to change it
		$k = 0;
		$count = count($this->_childs);
		for($i = 0; $i < $count; $i++) {
			$category =& $this->_childs[$i];
			
			$category->assigneditems = $this->_getassigned( $category->id );
			$category->subcats       = $this->_getsubs( $category->id );
			//$this->_id          = $category->id;
			//$category->items    = $this->getData();
			$this->_data				= null;
			$k = 1 - $k;
		}
		$this->_id = $id;  // restore id in case it has been changed
		return $this->_childs;
	}
	
	
	/**
	 * Method to load the Category
	 *
	 * @access public
	 * @return array
	 */
	function getCategory()
	{
		$mainframe = &JFactory::getApplication();
		
		//initialize some vars
		$user		= & JFactory::getUser();
		
		if ($this->_id) {
			//get categories
			$query 	= 'SELECT c.*,'
					. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
					. ' FROM #__categories AS c'
					. ' WHERE c.id = '.$this->_id
					. ' AND c.published = 1'
					;
	
			$this->_db->setQuery($query);
			$this->_category = $this->_db->loadObject();
			if ( $this->_db->getErrorNum() ) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage('SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
				$jAp->enqueueMessage('ERROR URL:<br/>'.JFactory::getURI()->toString(), 'warning' );
				$jAp->enqueueMessage('Please report to website administrator.','message');
				$jAp->redirect( 'index.php' );
			}
		} else if ($this->_authorid) {
			$this->_category = new stdClass;
			$this->_category->published = 1;
			$this->_category->id = $this->_id;  // zero
			$this->_category->title = '';
			$this->_category->description = '';
			$this->_category->slug = '';
		} else {
			$this->_category = false;
		}
		
		//Make sure the category is published
		if (!$this->_category)
		{
			JError::raiseError(404, JText::sprintf( 'Content category with id: %d, was not found or is not published', $this->_id ));
			return false;
		}
		
		// Set category parameters, these have already been loaded
		$this->_category->parameters = & $this->_params;
		$cparams = & $this->_params;

		//check whether category access level allows access
		$canread = true;
		if ($this->_id) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$canread = in_array($this->_category->access, $aid_arr);
			} else {
				$aid = (int) $user->get('aid');
				$canread 	= FLEXI_ACCESS ? FAccess::checkAllItemReadAccess('com_content', 'read', 'users', $user->gmid, 'category', $this->_category->id) : $this->_category->access <= $aid;
			}
		}
		
		if (!$canread && $this->_id!=0)
		{
			if($user->guest) {
				// Redirect to login
				$uri		= JFactory::getURI();
				$return		= $uri->toString();
				$com_users = FLEXI_J16GE ? 'com_users' : 'com_user';
				$url  = $cparams->get('login_page', 'index.php?option='.$com_users.'&view=login');
				$url .= '&return='.base64_encode($return);

				JError::raiseWarning( 403, JText::sprintf("FLEXI_LOGIN_TO_ACCESS", $url));
				$mainframe->redirect( $url );
			} else {
				if ($cparams->get('unauthorized_page', '')) {
					$mainframe->redirect($cparams->get('unauthorized_page'));				
				} else {
					JError::raiseWarning( 403, JText::_("FLEXI_ALERTNOTAUTH_VIEW"));
					$mainframe->redirect( 'index.php' );
				}
			}
		}

		return $this->_category;
	}

	/**
	 * Method to load content article parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadCategoryParams($id)
	{
		global $currcat_data;
		if ( !empty($currcat_data['params']) ) return $currcat_data['params'];
		
		if ($this->_params === NULL) {
			jimport("joomla.html.parameter");
			$mainframe = &JFactory::getApplication();
			
			// Retrieve author parameters if using displaying AUTHOR layout
			$author_basicparams = '';
			$author_catparams = '';
			if ($this->_authorid!=0) {
				$query = 'SELECT author_basicparams, author_catparams FROM #__flexicontent_authors_ext WHERE user_id = ' . $this->_authorid;
				$this->_db->setQuery($query);
				$author_extdata = $this->_db->loadObject();
				if ($author_extdata) {
					$author_basicparams = $author_extdata->author_basicparams;
					$author_catparams =  $author_extdata->author_catparams;
					
					$authorparams = new JParameter($author_basicparams);
					if (!$authorparams->get('override_currcatconf',0)) {
						$author_catparams = '';
					}
				}
			}
			
			// Retrieve category parameters
			$catparams = "";
			if ($id) {
				$query = 'SELECT params FROM #__categories WHERE id = ' . $id;
				$this->_db->setQuery($query);
				$catparams = $this->_db->loadResult();
			}
			
			
			// Retrieve menu parameters
			$menu = JSite::getMenu()->getActive();
			if ($menu) {
				if (FLEXI_J16GE) {
					$menuParams = new JRegistry;
					$menuParams->loadJSON($menu->params);
				} else {
					$menuParams = new JParameter($menu->params);
				}
			}
			
			// a. Get the COMPONENT only parameters, NOTE: we will merge the menu parameters later selectively
			$flexi = JComponentHelper::getComponent('com_flexicontent');
			$params = new JParameter($flexi->params);
			if ($menu) {
				// some parameters not belonging to category overriden parameters
				$params->set( 'item_depth', $menuParams->get('item_depth') );
			}
			$params->set('show_title', $params->get('show_title_lists'));          // Parameter meant for lists
			$params->set('title_linkable', $params->get('title_linkable_lists'));  // Parameter meant for lists			
			/*
			// a. Get the PAGE/COMPONENT parameters (WARNING: merges current menu item parameters in J1.5 but not in J1.6+)
			$params = clone($mainframe->getParams('com_flexicontent'));
			
			// In J1.6+ the above function does not merge current menu item parameters, it behaves like JComponentHelper::getParams('com_flexicontent') was called
			if (FLEXI_J16GE && $menu) {
				$params->merge($menuParams);
			}
			*/
			
			// b. Merge category parameters
			$cparams = new JParameter($catparams);
			$params->merge($cparams);
			
			// c. Merge author basic parameters
			if ($author_basicparams!=='') {
				$params->merge( new JParameter($author_basicparams) );
			}
	
			// d. Merge author OVERRIDDEN category parameters
			if ($author_catparams!=='') {
				$params->merge( new JParameter($author_catparams) );
			}
	
			// Verify menu item points to current FLEXIcontent object, IF NOT then overwrite page title and clear page class sufix
			if ( !empty($menu) ) {
				$view_ok      = @$menu->query['view']     == 'category';
				$cid_ok       = @$menu->query['cid']      == JRequest::getInt('cid');
				$layout_ok    = @$menu->query['layout']   == JRequest::getVar('layout','');
				$authorid_ok  = @$menu->query['authorid'] == JRequest::getInt('authorid');
				// We will merge menu parameters last, thus overriding the default categories parameters if either
				// (a) override is enabled in the menu or (b) category Layout is 'myitems' which has no default parameters
				$overrideconf = $menuParams->get('override_defaultconf',0) || JRequest::getVar('layout','')=='myitems';
				
				$menu_matches = $view_ok && $cid_ok & $layout_ok && $authorid_ok && $overrideconf;
				if ( $menu_matches ) {
					$params->merge($menuParams);
				}
			}
			
			// Bugs of v2.0 RC2
			if (FLEXI_J16GE) {
				if ( is_array($orderbycustomfieldid = $params->get('orderbycustomfieldid', 0)) ) {
					JError::raiseNotice(0, "FLEXIcontent versions up to to v2.0 RC2a, had a bug, please open category and resave it, you can use 'copy parameters' to quickly update many categories");
					$cparams->set('orderbycustomfieldid', $orderbycustomfieldid[0]);
				}
				if ( preg_match("/option=com_user&/", $params->get('login_page', '')) ) {
					JError::raiseNotice(0, "FLEXIcontent versions up to to v2.0 RC2a, set the login url wrongly in the global configuration.<br /> Please replace: <u>option=com_user</u> with <u>option=com_users</u>");
					$cparams->set( 'login_page', str_replace("com_user&", "com_users&", $params->get('login_page', '')) );
				}
			}
		}
		
		return $currcat_data['params'] = $params;
	}

	/**
	 * Method to get the filter
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	function getFilters()
	{
		static $filters;
		if($filters) return $filters;
		
		$user		= &JFactory::getUser();
		$params = $this->_params;
		$scope	= $params->get('filters') ? ( is_array($params->get('filters')) ? ' AND fi.id IN (' . implode(',', $params->get('filters')) . ')' : ' AND fi.id = ' . $params->get('filters') ) : null;
		$filters	= null;
		
		if (FLEXI_J16GE) {
			$aid_arr = $user->getAuthorisedViewLevels();
			$aid_list = implode(",", $aid_arr);
			$where = ' AND fi.access IN ('.$aid_list.') ';
		} else {
			$aid = (int) $user->get('aid');
			
			if (FLEXI_ACCESS) {
				$readperms = FAccess::checkUserElementsAccess($user->gmid, 'read');
				if (isset($readperms['field']) && count($readperms['field']) ) {
					$where = ' AND ( fi.access <= '.$aid.' OR fi.id IN ('.implode(",", $readperms['field']).') )';
				} else {
					$where = ' AND fi.access <= '.$aid;
				}
			} else {
				$where = ' AND fi.access <= '.$aid;
			}
		}
		
		$query  = 'SELECT fi.*'
			. ' FROM #__flexicontent_fields AS fi'
			. ' WHERE fi.published = 1'
			. ' AND fi.isfilter = 1'
			. $where
			. $scope
			. ' ORDER BY fi.ordering, fi.name'
		;
		$this->_db->setQuery($query);
		$filters = $this->_db->loadObjectList('name');
		if (!$filters) $filters = array();
		foreach ($filters as $filter)
		{
			$filter->parameters = new JParameter($filter->attribs);
		}
		
		return $filters;
	}

	/**
	 * Method to get the active filter result
	 * 
	 * @access private
	 * @return string
	 * @since 1.5
	 */
	function _getFiltered($field_id, $value, $field_type = '')
	{
		switch($field_type) 
		{
			case 'createdby':
				$filter_query = ' AND i.created_by = ' . $this->_db->Quote($value);
			break;

			case 'modifiedby':
				$filter_query = ' AND i.modified_by = ' . $this->_db->Quote($value);
			break;
			
			case 'type':
				$filter_query = ' AND ie.type_id = ' . $this->_db->Quote($value);
			break;
			
			case 'state':
				if ( $value == 'P' ) {
					$filter_query = ' AND i.state = 1';
				} else if ($value == 'U' ) {
					$filter_query = ' AND i.state = 0';
				} else if ($value == 'PE' ) {
					$filter_query = ' AND i.state = -3';
				} else if ($value == 'OQ' ) {
					$filter_query = ' AND i.state = -4';
				} else if ($value == 'IP' ) {
					$filter_query = ' AND i.state = -5';
				}
			break;
			
			case 'categories':
				$_data_cats = array_intersect(array($value), $this->_data_cats);
				$_data_cats = "'".implode("','", $_data_cats)."'";
				$where = ' catid IN ('.$_data_cats.')';
				$query  = 'SELECT id'
					. ' FROM #__content'
					. ' WHERE ' . $where
					;
				$this->_db->setQuery($query);
				$filtered1 = $this->_db->loadResultArray();
				$query  = 'SELECT itemid'
					. ' FROM #__flexicontent_cats_item_relations'
					. ' WHERE ' . $where
					;
				$this->_db->setQuery($query);
				$filtered2 = $this->_db->loadResultArray();
				$filtered = array_unique(array_merge($filtered1, $filtered2));
				$filter_query = $filtered ? ' AND i.id IN (' . implode(',', $filtered) . ')' : ' AND i.id = 0';
			break;
			
			case 'tags':
				$query  = 'SELECT itemid'
						. ' FROM #__flexicontent_tags_item_relations'
						. ' WHERE tid = ' . $this->_db->Quote($value)
						;
				$this->_db->setQuery($query);
				$filtered = $this->_db->loadResultArray();
				$filter_query = $filtered ? ' AND i.id IN (' . implode(',', $filtered) . ')' : ' AND i.id = 0';
			break;
			
			default:
				$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.DS.strtolower($field_type).(FLEXI_J16GE ? DS.strtolower($field_type) : "").'.php';
				if(file_exists($path)) require_once($path);
				require_once($path);
				$method_exists = method_exists("plgFlexicontent_fields{$field_type}", "getFiltered");
				if ($method_exists) {
					$filtered = array();
					FLEXIUtilities::call_FC_Field_Func($field_type, 'getFiltered', array( &$field_id, &$value, &$filtered ));
				} else {
					$query  = 'SELECT item_id'
						. ' FROM #__flexicontent_fields_item_relations'
						. ' WHERE field_id = ' . $field_id
						. ' AND value LIKE ' . $this->_db->Quote($value)
						. ' GROUP BY item_id'
					;
					$this->_db->setQuery($query);
					$filtered = $this->_db->loadResultArray();
				}
				$filter_query = $filtered ? ' AND i.id IN (' . implode(',', $filtered) . ')' : ' AND i.id = 0';
			break; 
		}
		
		return $filter_query;
	}

	/**
	 * Method to build the alphabetical index
	 * 
	 * @access public
	 * @return array
	 * @since 1.5
	 */
 	function getAlphaindex()
	{
		$where  = $this->_buildItemWhere($no_alpha=1);
		
		$query	= 'SELECT LOWER(SUBSTRING(i.title FROM 1 FOR 1)) AS alpha'
				. ' FROM #__content AS i'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
				. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
				. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
				. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
				. $where
				. ' GROUP BY alpha'
				. ' ORDER BY alpha ASC'
				;
		$this->_db->setQuery($query);
		$alpha = $this->_db->loadResultArray();
		if ($this->_db->getErrorNum()) {
			$jAp=& JFactory::getApplication();
			$jAp->enqueueMessage('SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
		}
		return $alpha;
	}
	
	
	function & getCommentsInfo ( $_item_ids = false)
	{
		// handle jcomments integration
		if (!file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php')) {
			return array();
		}
		
		// Normal case, item ids not given, we will retrieve comments information of cat/sub items
		if ( !$_item_ids ) {
			// Return existing data
			if ($this->_comments!==null) return $this->_comments;
			
			// Make sure item data have been retrieved
			$this->getData();
			
			// Get item ids
			$item_ids = array();
			foreach ($this->_data as $item) $item_ids[] = $item->id;
		} else {
			$item_ids = & $_item_ids;
		}
		
		$db =& JFactory::getDBO();
		$query = 'SELECT COUNT(com.object_id) AS total, com.object_id AS item_id'
		      . ' FROM #__jcomments AS com'
		      . ' WHERE com.object_id in (' . implode(',',$item_ids) .')'
		      . ' AND com.object_group = ' . $db->Quote('com_flexicontent')
		      . ' AND com.published = 1'
		      . ' GROUP BY com.object_id'
		      ;
		$db->setQuery($query);
		$comments = $db->loadObjectList('item_id');
		
		if ( !$_item_ids ) $this->_comments = & $comments;
		
		return $comments;
	}
}
?>
