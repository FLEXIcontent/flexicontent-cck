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
class FlexicontentModelCategory extends JModelLegacy {
	/**
	 * Category id
	 *
	 * @var int
	 */
	var $_id = null;
	
	/**
	 * Category ids (multiple category view)
	 *
	 * @var int
	 */
	var $_ids = array();
	
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
	 * Array of subcategory ids, including category id (or ids for multi-category view) too, used to create the ITEMS list
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
	var $_layout = '';  // !! This should be empty for empty for 'category' layout
	
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
		$this->_layout = JRequest::getCmd('layout', '');  // !! This should be empty for empty for 'category' layout
		
		if ($this->_layout=='author') {
			$this->_authorid = JRequest::getInt('authorid', 0);
		} else if ($this->_layout=='myitems') {
			$user = JFactory::getUser();
			if ($user->guest) {
				$msg = JText::_( 'FLEXI_LOGIN_TO_DISPLAY_YOUR_CONTENT');
				if (FLEXI_J16GE) throw new Exception($msg, 403); else JError::raiseError(403, $msg);
			}
			$this->_authorid = $user->id;
		} else if ($this->_layout=='mcats') {
			$this->_ids = preg_replace( '/[^0-9,]/i', '', (string) JRequest::getVar('cids', '') );
			$this->_ids = explode(',', $this->_ids);
			// make sure given data are integers ... !!
			foreach ($this->_ids as $i => $_id) $this->_ids[$i] = (int)$_id;
		}
		
		// Set layout and authorid variables into state
		$this->setState('layout', $this->_layout);
		$this->setState('authorid', $this->_authorid);
		$this->setState('cids', $this->_ids);

		// We need to merge parameters here to get the correct page limit value, we must call this after populating layput and author variables
		$this->_params = $this->_loadCategoryParams($this->_id);
		$params = $this->_params;

		// Set the pagination variables into state (We get them from http request OR use default category parameters)
		$limit = JRequest::getInt('limit') ? JRequest::getInt('limit') : $params->get('limit');
		$limitstart	= JRequest::getInt('limitstart', 0, '', 'int');
		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		// Set filter order variables into state
		$this->setState('filter_order', 'i.title');
		$this->setState('filter_order_dir', 'ASC');
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
		$format	= JRequest::getCmd('format', null);
		
		$params = & $this->_params;
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		// Allow limit zero to achieve a category view without items
		if ($this->getState('limit') <= 0)
		{
			$this->_data = array();
		}
		else if (empty($this->_data))
		{
			if ( $print_logging_info )  $start_microtime = microtime(true);
			// Load the content if it doesn't already exist
			$query = $this->_buildQuery();

			$this->_total = $this->_getListCount($query);
			if ($this->_db->getErrorNum()) {
				$jAp= JFactory::getApplication();
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
				$jAp= JFactory::getApplication();
				$jAp->enqueueMessage('SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
			}
			if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
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
	function &getParams()
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
		static $query = null;
		if ( $query!==null ) return $query;
		
		// Get the WHERE and ORDER BY clauses for the query
		$where   = $this->_buildItemWhere();
		$orderby = $this->_buildItemOrderBy();
		$params  = & $this->_params;

		// Add sort items by custom field. Issue 126 => http://code.google.com/p/flexicontent/issues/detail?id=126#c0
		$order_field_join = '';
		if ($params->get('orderbycustomfieldid', 0) != 0) {
			$order_field_join = ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.(int)$params->get('orderbycustomfieldid', 0);
		}
		
		// Add image field used as item image in RSS feed
		$feed_image_source = 0;
		if (JRequest::getCmd("type", "") == "rss") {
			$feed_image_source = (int) $params->get('feed_image_source', '');
		}
		$feed_img_join= '';
		$feed_img_col = '';
		if ($feed_image_source) {
			$feed_img_join = ' LEFT JOIN #__flexicontent_fields_item_relations AS img ON img.item_id = i.id AND img.field_id='.$feed_image_source;
			$feed_img_col = ' img.value as image,';
		}
		
		$query = 'SELECT i.*, ie.*, u.name as author, ty.name AS typename,'.$feed_img_col 
		. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
		. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
		. ' FROM #__content AS i'
		. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
		. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
		. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
		. $feed_img_join
		. $order_field_join
		. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
		. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
		. $where
		. ' GROUP BY i.id '
		. $orderby
		;
		
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
	function &_getDataCats($id_arr)
	{
		if ( $this->_data_cats ) return $this->_data_cats;

		global $globalcats;
		$cparams = & $this->_params;
		$user 		= JFactory::getUser();
		$ordering	= FLEXI_J16GE ? 'c.lft ASC' : 'c.ordering ASC';

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
		
		$_data_cats = array();
		foreach ($id_arr as $id)
		{
			// filter by depth level
			$display_subcats = $cparams->get('display_subcategories_items', 0);
			if ($display_subcats==0) {
				//$anddepth = ' AND c.id = '. $this->_id;
				$_data_cats[] = array($id);
				continue;
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
			$_data_cats[] = FLEXI_J30GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
			if ( $this->_db->getErrorNum() ) {
				$jAp= JFactory::getApplication();
				$jAp->enqueueMessage('SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
			}
		}
		$this->_data_cats = array();
		foreach ($_data_cats as $cats) {
			$this->_data_cats = array_unique(array_merge($this->_data_cats, $cats));
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
		$request_var = $this->_params->get('orderby_override') ? 'orderby' : '';
		$default_order = $this->getState('filter_order');
		$default_order_dir = $this->getState('filter_order_dir');
		
		// Precedence: $request_var ==> $order ==> $config_param ==> $default_order
		return flexicontent_db::buildItemOrderBy(
			$this->_params,
			$order='', $request_var, $config_param='orderby',
			$item_tbl_alias = 'i', $relcat_tbl_alias = 'rel',
			$default_order, $default_order_dir
		);
	}
	
	
	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return array
	 */
	function _buildItemWhere( $wherepart='where' )
	{
		global $globalcats, $fc_catviev;
		if ( isset($fc_catviev[$wherepart]) ) return $fc_catviev[$wherepart];
		
		$mainframe = JFactory::getApplication();
		$option = JRequest::getVar('option');
		$user		= JFactory::getUser();
		$db     = JFactory::getDBO();
		
		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		//  thus the items are published globally at the time the author specified in his/her local clock
		//$now = $mainframe->get('requestTime');  // NOT correct behavior it should be UTC (below)
		//$now = FLEXI_J16GE ? JFactory::getDate()->toSql() : JFactory::getDate()->toMySQL();
		$_now = 'UTC_TIMESTAMP()'; //$this->_db->Quote($now);
		$nullDate	= $db->getNullDate();
		
		$cparams = & $this->_params;                      // Get the category parameters
		$lang = flexicontent_html::getUserCurrentLang();  // Get user current language
		$catlang = $cparams->get('language', '');         // Category language parameter, currently UNUSED
		$filtercat  = $cparams->get('filtercat', 0);      // Filter items using currently selected language
		$show_noauth = $cparams->get('show_noauth', 0);   // Show unauthorized items
		
		// First thing we need to do is to select only the requested items
		$where = ' WHERE 1';
		if ($this->_authorid)
			$where .= ' AND i.created_by = ' . $this->_db->Quote($this->_authorid);
		
		if ($this->_id || count($this->_ids)) {
			$id_arr = $this->_id ? array($this->_id) : $this->_ids;
			// Get sub categories used to create items list, according to configuration and user access
			$this->_getDataCats($id_arr);
			$_data_cats = "'".implode("','", $this->_data_cats)."'";
			$where .= ' AND rel.catid IN ('.$_data_cats.')';
		} 
		
		// Get privilege to view non viewable items (upublished, archived, trashed, expired, scheduled).
		// NOTE:  ACL view level is checked at a different place
		if ( FLEXI_J16GE )
			$ignoreState = $user->authorise('flexicontent.ignoreviewstate', 'com_flexicontent');
		else if (FLEXI_ACCESS)
			$ignoreState = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'ignoreviewstate', 'users', $user->gmid) : 1;
		else
			$ignoreState = $user->gid  > 19;  // author has 19 and editor has 20
		
		if (!$ignoreState) {
			// Limit by publication state. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( i.state IN (1, -5) OR i.created_by = '.$user->id.' )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		
			// Limit by publish up/down dates. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$_now.' ) OR i.created_by = '.$user->id.' )';       //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			$where .= ' AND ( ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$_now.' ) OR i.created_by = '.$user->id.' )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		}
		
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
		$session  = JFactory::getSession();
		
		// Featured items, this item property exists in J1.6+ only
		if (FLEXI_J16GE) {
			$featured = $cparams->get('featured');
			switch ($featured) {
				case 'show': $where .= ' AND i.featured=1'; break;
				case 'hide': $where .= ' AND i.featured=0'; break;
				default: break;
			}
		}
		
		$filters_where = $this->_buildFiltersWhere();
		$alpha_where   = $this->_buildAlphaIndexWhere();
		
		$fc_catviev['filters_where'] = $filters_where;
		$fc_catviev['alpha_where'] = $alpha_where;
		
		$filters_where = implode(' ', $filters_where);
		
		$fc_catviev['where_no_alpha']   = $where . $filters_where;
		$fc_catviev['where_no_filters'] = $where . $alpha_where;
		$fc_catviev['where_conf_only']  = $where;
		
		$fc_catviev['where'] = $where . $filters_where . $alpha_where;
		
		return $fc_catviev[$wherepart];
	}
	
	
	/**
	 * Method to build the part of WHERE clause related to Alpha Index
	 *
	 * @access private
	 * @return array
	 */
	function _buildFiltersWhere()
	{
		global $fc_catviev;
		$mainframe = JFactory::getApplication();
		$option    = JRequest::getVar('option');
		$cparams   = & $this->_params;
		
		$filters_where = array();
		
		//if ( $cparams->get('use_search',1) )  // Commented out to allow using persistent filters via menu
		if ( 1 )
		{
			// Get value of search text ('filter') , setting into appropriate session variables
			/*if ($this->_id) {
				$filter  = $mainframe->getUserStateFromRequest( $option.'.category'.$this->_id.'.filter', 'filter', '', 'string' );
			} else if ($this->_authorid) {
				$filter  = $mainframe->getUserStateFromRequest( $option.'.author'.$this->_authorid.'.filter', 'filter', '', 'string' );
			} else if (count($this->_ids)) {
				$filter  = $mainframe->getUserStateFromRequest( $option.'.mcats'.$this->_menu_itemid.'.filter', 'filter', '', 'string' );
			} else {
				$filter  = JRequest::getVar('filter', NULL, 'default');
			}*/
			$filter  = JRequest::getVar('filter', NULL, 'default');
			
			if ($filter)
			{
				$filters_where[ 'search' ] = ' AND MATCH (ie.search_index) AGAINST ('.$this->_db->Quote( $this->_db->getEscaped( $filter, true ), false ).' IN BOOLEAN MODE)';
			}
		}
		
		//if ( $cparams->get('use_filters',1) )  // Commented out to allow using persistent filters via menu
		if ( 1 )
		{
			$filters = $this->getFilters();
			if ($filters) foreach ($filters as $filtre)
			{
				// Get filter values, setting into appropriate session variables
				/*if ($this->_id) {
					$filtervalue 	= $mainframe->getUserStateFromRequest( $option.'.category'.$this->_id.'.filter_'.$filtre->id, 'filter_'.$filtre->id, '', '' );
				} else if ($this->_authorid) {
					$filtervalue  = $mainframe->getUserStateFromRequest( $option.'.author'.$this->_authorid.'.filter_'.$filtre->id, 'filter_'.$filtre->id, '', '' );
				} else if (count($this->_ids)) {
					$filtervalue  = $mainframe->getUserStateFromRequest( $option.'.mcats'.$this->_menu_itemid.'.filter_'.$filtre->id, 'filter_'.$filtre->id, '', '' );
				} else {
					$filtervalue  = JRequest::getVar('filter_'.$filtre->id, '', '');
				}*/
				$filtervalue  = JRequest::getVar('filter_'.$filtre->id, '', '');
				
				// Skip filters without value
				$empty_filtervalue_array  = is_array($filtervalue)  && !strlen(trim(implode('',$filtervalue)));
				$empty_filtervalue_string = !is_array($filtervalue) && !strlen(trim($filtervalue));
				if ($empty_filtervalue_array || $empty_filtervalue_string) continue;
				
				//echo "category model found filters: "; print_r($filtervalue);
				$filters_where[ $filtre->id ] = $this->_getFiltered($filtre, $filtervalue);
			}
		}
		
		return $filters_where;
	}
	
	
	/**
	 * Method to build the part of WHERE clause related to Alpha Index
	 *
	 * @access private
	 * @return array
	 */
	function _buildAlphaIndexWhere()
	{
		// Get alpha index request variable and do some security checks, by removing any quotes and other non-valid characters
		$alpha = JRequest::getVar('letter', NULL, 'request', 'string');
		$alpha = preg_replace ("/(\(|\)\'|\"|\\\)/u", "", $alpha);
		
		if (JString::strlen($alpha)==0) {
			// nothing to do
			return '';
		}
		
		// Detect and handle character groups and character ranges,  WARNING: The following is needed because
		// utf8 is multibyte and MySQL regexp doesnot support multibyte, thus we can not use [] with utf8
		
		$range = explode("-", $alpha);
		if (count($range) > 2)
		{
			echo "Error in Alpha Index please correct letter range: ".$alpha."<br>";
			return '';
		}
		
		else if (count($range) == 1)
		{
			$regexp = '"^('.JString::substr($alpha,0,1);
			for($i=1; $i<JString::strlen($alpha); $i++) :
				$regexp .= '|'.JString::substr($alpha,$i,1);
			endfor;
			$regexp .= ')"';
		}
		
		else if (count($range) == 2)
		{
			
			// Get range characters
			$startletter = $range[0];  $endletter = $range[1];
			
			// ERROR CHECK: Range START and END are single character strings
			if (JString::strlen($startletter) != 1 || JString::strlen($endletter) != 1) {
				echo "Error in Alpha Index<br>letter range: ".$alpha." start and end must be one character<br>";
				return '';
			}
			
			// Get ord of characters and their rangle length
			$startord=FLEXIUtilities::uniord($startletter);
			$endord=FLEXIUtilities::uniord($endletter);
			$range_length = $endord - $startord;
			
			// ERROR CHECK: Character range has at least one character
			if ($range_length > 50 || $range_length < 1) {
				// A sanity check that the range is something logical and that 
				echo "Error in Alpha Index<br>letter range: ".$alpha.", is incorrect or contains more that 50 characters<br>";
				return '';
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
			return '';
		}
		
		$where = '';
		if ( !empty($regexp) )
		{
			if ($alpha == '0')
			{
				$where = ' AND ( CONVERT (( i.title ) USING BINARY) REGEXP CONVERT ('.$regexp.' USING BINARY) )' ;
				//$where = ' AND LOWER( i.title ) RLIKE '.$this->_db->Quote( $this->_db->getEscaped( '^['.$alpha.']', true ), false );
			}
			elseif (!empty($alpha))
			{
				$where = ' AND ( CONVERT (LOWER( i.title ) USING BINARY) REGEXP CONVERT ('.$regexp.' USING BINARY) )' ;
				//$where = ' AND LOWER( i.title ) RLIKE '.$this->_db->Quote( $this->_db->getEscaped( '^['.$alpha.']', true ), false );
			}
		}
		
		return $where;
	}
	
	
	/**
	 * Method to build the childs categories query
	 *
	 * @access private
	 * @return string
	 */
	function _buildChildsQuery()
	{
		$user 		= JFactory::getUser();
		$ordering	= FLEXI_J16GE ? 'c.lft ASC' : 'c.ordering ASC';

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
		
		$id_arr  = $this->_id ? array($this->_id) : $this->_ids;
		$id_list = implode(',', $id_arr);
		
		$query = 'SELECT c.*,'
			. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug'
			. ' FROM #__categories AS c'
			. ' WHERE c.published = 1'
			. ' AND c.parent_id IN ('. $id_list .')'
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
		$mainframe = JFactory::getApplication();
		$user 		= JFactory::getUser();

		// Get the category parameters
		$cparams = & $this->_params;
		$db = JFactory::getDBO();
		
		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		//  thus the items are published globally at the time the author specified in his/her local clock
		//$now = $mainframe->get('requestTime');  // NOT correct behavior it should be UTC (below)
		//$now = FLEXI_J16GE ? JFactory::getDate()->toSql() : JFactory::getDate()->toMySQL();
		$_now = 'UTC_TIMESTAMP()'; //$this->_db->Quote($now);
		$nullDate	= $db->getNullDate();
		
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
		
		// Get privilege to view non viewable items (upublished, archived, trashed, expired, scheduled).
		// NOTE:  ACL view level is checked at a different place
		if ( FLEXI_J16GE )
			$ignoreState = $user->authorise('flexicontent.ignoreviewstate', 'com_flexicontent');
		else if (FLEXI_ACCESS)
			$ignoreState = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'ignoreviewstate', 'users', $user->gmid) : 1;
		else
			$ignoreState = $user->gid  > 19;  // author has 19 and editor has 20
		
		if (!$ignoreState) {
			// Limit by publication state. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( i.state IN (1, -5) OR i.created_by = '.$user->id.' )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			
			// Limit by publish up/down dates. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$_now.' ) OR i.created_by = '.$user->id.' )';       //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			$where .= ' AND ( ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$_now.' ) OR i.created_by = '.$user->id.' )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		}
		
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
		$assigneditems = count(FLEXI_J30GE ? $this->_db->loadColumn() : $this->_db->loadResultArray());
		if ( $this->_db->getErrorNum() ) {
			$jAp= JFactory::getApplication();
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
		$user			= JFactory::getUser();

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
		$ordering	= FLEXI_J16GE ? 'sc.lft ASC' : 'sc.ordering ASC';
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
		if ( !$this->_id && !count($this->_ids)) return array();
		
		$query = $this->_buildChildsQuery();
		$this->_childs = $this->_getList($query);
		$id = $this->_id;  // save id in case we need to change it
		$k = 0;
		$count = count($this->_childs);
		for($i = 0; $i < $count; $i++)
		{
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
		$mainframe = JFactory::getApplication();
		
		//initialize some vars
		$user		= JFactory::getUser();
		
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
				$jAp= JFactory::getApplication();
				$jAp->enqueueMessage('SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
				$jAp->enqueueMessage('ERROR URL:<br/>'.JFactory::getURI()->toString(), 'warning' );
				$jAp->enqueueMessage('Please report to website administrator.','message');
				$jAp->redirect( 'index.php' );
			}
		} else if ($this->_authorid || count($this->_ids)) {
			$this->_category = new stdClass;
			$this->_category->published = 1;
			$this->_category->id = $this->_id;   // can be zero for author/myitems/etc layouts
			$this->_category->title = '';
			$this->_category->description = '';
			$this->_category->slug = '';
		} else {
			$this->_category = false;
		}
		// non-empty for multi-cats
		$this->_category->ids = $this->_ids;
		
		//Make sure the category is published
		if (!$this->_category)
		{
			$msg = JText::sprintf( 'Content category with id: %d, was not found or is not published', $this->_id );
			if (FLEXI_J16GE) throw new Exception($msg, 404); else JError::raiseError(404, $msg);
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
		global $fc_catviev;
		if ( !empty($fc_catviev['params']) ) return $fc_catviev['params'];
		
		if ($this->_params === NULL) {
			jimport("joomla.html.parameter");
			$mainframe = JFactory::getApplication();
			
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
					
					$authorparams = FLEXI_J16GE ? new JRegistry($author_basicparams) : new JParameter($author_basicparams);
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
			$menu = $mainframe->getMenu()->getActive();
			if ($menu) {
				$menuParams = FLEXI_J16GE ? new JRegistry($menu->params) : new JParameter($menu->params);
			}
			
			// a. Get the COMPONENT only parameters, NOTE: we will merge the menu parameters later selectively
			$flexi = JComponentHelper::getComponent('com_flexicontent');
			$params = FLEXI_J16GE ? new JRegistry($flexi->params) : new JParameter($flexi->params);
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
			$cparams = FLEXI_J16GE ? new JRegistry($catparams) : new JParameter($catparams);
			$params->merge($cparams);
			
			// c. Merge author basic parameters
			if ($author_basicparams!=='') {
				$params->merge(  FLEXI_J16GE ? new JRegistry($author_basicparams) : new JParameter($author_basicparams)  );
			}
	
			// d. Merge author OVERRIDDEN category parameters
			if ($author_catparams!=='') {
				$params->merge(  FLEXI_J16GE ? new JRegistry($author_catparams) : new JParameter($author_catparams)  );
			}
	
			// Verify menu item points to current FLEXIcontent object, and then merge menu item parameters
			if ( !empty($menu) )
			{
				$this->_menu_itemid = $menu->id;
				
				$view_ok      = @$menu->query['view']     == 'category';
				$cid_ok       = @$menu->query['cid']      == $this->_id;
				$layout_ok    = @$menu->query['layout']   == $this->_layout;
				$authorid_ok  = (@$menu->query['authorid'] == $this->_authorid) || ($this->_layout=='myitems');  // Ignore empty author_id when layout is 'myitems'
				
				// We will merge menu parameters last, thus overriding the default categories parameters if either
				// (a) override is enabled in the menu or (b) category Layout is 'myitems' which has no default parameters
				$overrideconf = $menuParams->get('override_defaultconf',0) || $this->_layout=='myitems' || $this->_layout=='mcats';
				$menu_matches = $view_ok && $cid_ok & $layout_ok && $authorid_ok;
				
				if ( $menu_matches && $overrideconf ) {
					// Add - all - menu parameters related or not related to category parameters override
					$params->merge($menuParams);
				} else if ($menu_matches) {
					// Add menu parameters - not - related to category parameters override
					$partial_param_arr = array('item_depth', 'persistent_filters', 'initial_filters');
					foreach ($partial_param_arr as $partial_param) {
						$params->set( $partial_param, $menuParams->get($partial_param));
					}
				}
			}
			
			// Set filters via menu parameters
			$this->_setFilters( $params, 'persistent_filters', $is_persistent=1);
			$this->_setFilters( $params, 'initial_filters'   , $is_persistent=0);
			
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
		
		return $fc_catviev['params'] = $params;
	}
	
	
	/**
	 * Method to set MENU Item filters as HTTP Request variables thus filtering the category view
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	function _setFilters( & $params, $mfilter_name='persistent_filters', $is_persistent=1 )
	{
		$mfilter_data = $params->get($mfilter_name, '');
		if ($mfilter_data) {
			// Parse filter values
			$mfilter_arr = preg_split("/[\s]*%%[\s]*/", $mfilter_data);
			if ( empty($mfilter_arr[count($mfilter_arr)-1]) ) {
				unset($mfilter_arr[count($mfilter_arr)-1]);
			}
			
			// Split elements into their properties: filter_id, filter_value
			$filter_vals = array();
			$results = array();
			$n = 0;
			foreach ($mfilter_arr as $mfilter) {
				$_data  = preg_split("/[\s]*##[\s]*/", $mfilter);
				$filter_id = (int) $_data[0];  $filter_value = @$_data[1];
				if ( $filter_id ) {
					$filter_vals[$filter_id] = $filter_value;
					if ($is_persistent || JRequest::getVar('filter_'.$filter_id, false) === false ) {
						//echo "filter_.$filter_id, $filter_value <br/>";
						JRequest::setVar('filter_'.$filter_id, $filter_value);
					}
				}
			}
		}
	}
	
	
	/**
	 * Method to get the filter
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	function & getFilters()
	{
		static $filters;
		if($filters) return $filters;
		
		$user		= JFactory::getUser();
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
			$filter->parameters = FLEXI_J16GE ? new JRegistry($filter->attribs) : new JParameter($filter->attribs);
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
	function _getFiltered( &$filter, $value )
	{
		$field_type = $filter->field_type;
		
		// Sanitize filter values as integers for field that use integer values
		if ( in_array($field_type, array('createdby','modifiedby','type','categories','tags')) ) {
			$values = is_array($value) ? $value : array($value);
			foreach ($values as $i => $v) $values[$i] = (int)$v;
		}
		
		switch($field_type) 
		{
			case 'createdby':
				$filter_query = ' AND i.created_by IN ('. implode(",", $values) .')';   // no db quoting needed since these were typecasted to ints
			break;

			case 'modifiedby':
				$filter_query = ' AND i.modified_by IN ('. implode(",", $values) .')';   // no db quoting needed since these were typecasted to ints
			break;
			
			case 'type':
				$filter_query = ' AND ie.type_id IN ('. implode(",", $values) .')';   // no db quoting needed since these were typecasted to ints
			break;
			
			case 'state':
				$stateids = array ( 'PE' => -3, 'OQ' => -4, 'IP' => -5, 'P' => 1, 'U' => 0, 'A' => (FLEXI_J16GE ? 2:-1), 'T' => -2 );
				$values = is_array($value) ? $value : array($value);
				$filt_states = array();
				foreach ($values as $i => $v) if (isset($stateids[$v])) $filt_states[] = $stateids[$v];
				$filter_query = !count($values) ? ' AND 1=0 ' : ' AND i.state IN ('. implode(",", $filt_states) .')';   // no db quoting needed since these were typecasted to ints
			break;
			
			case 'categories':
				$filter_query = ' AND rel.catid IN ('. implode(",", $values) .')';
			break;
			
			case 'tags':
				$query  = 'SELECT itemid'
						. ' FROM #__flexicontent_tags_item_relations'
						. ' WHERE tid IN ('. implode(",", $values) .')';
						;
				$filter_query = ' AND i.id IN (' . $query . ')';
			break;
			
			default:
				// Make sure plugin file of current file is loaded and then check if custom filtering function exists
				$field_type_file = $filter->iscore ? 'core' : $field_type;
				$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.DS.strtolower($field_type_file).(FLEXI_J16GE ? DS.strtolower($field_type_file) : "").'.php';
				if ( file_exists($path) ) {
					require_once($path);
					$method_exists = method_exists("plgFlexicontent_fields{$field_type_file}", "getFiltered");
				}
				
				// Use custom field filtering if 'getFiltered' plugin method exists, otherwise try to use our default filtering function
				$filtered = ! @ $method_exists ?
					FlexicontentFields::getFiltered($filter, $value, $return_sql=true) :
					FLEXIUtilities::call_FC_Field_Func($field_type_file, 'getFiltered', array( &$filter, &$value ));
				
				// An empty return value means no matching values we found
				$filtered = empty($filtered) ? ' AND 1=0' : $filtered;
				
				// A string mean a subquery was returned, while an array means that item ids we returned
				$filter_query = is_array($filtered) ?  ' AND i.id IN ('. implode(',', $filtered) .')' : $filtered;
			break; 
		}
		//echo "<br/>".$filter_query."<br/>";
		
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
		$where = $this->_buildItemWhere('where_no_alpha');
		
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
		$alpha = FLEXI_J30GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
		if ($this->_db->getErrorNum()) {
			$jAp= JFactory::getApplication();
			$jAp->enqueueMessage('SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
		}
		return $alpha;
	}
	
	
	function &getCommentsInfo ( $_item_ids = false)
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
		
		$db = JFactory::getDBO();
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
