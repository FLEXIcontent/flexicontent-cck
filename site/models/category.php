<?php
/**
 * @version 1.5 stable $Id: category.php 1959 2014-09-18 00:15:15Z ggppdk $
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

jimport('legacy.model.legacy');
use Joomla\String\StringHelper;

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
	 * Array of sub-categories data
	 *
	 * @var mixed
	 */
	var $_childs = null;
	
	/**
	 * Template configuration name (layout)
	 *
	 * @var int
	 */
	var $_clayout = null;
	
	/**
	 * Array of peer-categories data
	 *
	 * @var mixed
	 */
	var $_peers = null;
	
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
	var $_data_cats = null;
	
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
	 * Author of listed items (used by AUTHOR / MYITEMS layout)
	 *
	 * @var integer
	 */
	var $_authorid = 0;
	
	/**
	 * User id for favoured items (used by FAVS layout)
	 *
	 * @var integer
	 */
	var $_uid = 0;
	
	/**
	 * Tag id (used by TAGS layout)
	 *
	 * @var integer
	 */
	var $_tagid = 0;
	
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
	 * Current / active search elements
	 *
	 * @var string
	 */
	var $_active_filts  = null;
	var $_active_search = null;
	var $_active_ai     = null;
	
	
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
	 * Method to populate the category model state.
	 *
	 * return	void
	 * @since	1.5
	 */
	protected function populateCategoryState($ordering = null, $direction = null)
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$p      = $option.'.'.$view.'.';
		
		$this->_layout = JRequest::getCmd('layout', '');  // !! This should be empty for empty for 'category' layout
		
		// Force layout to be have proper value
		if ( $this->_layout && !in_array($this->_layout, array('favs','tags','mcats','myitems','author')) )
		{
			//JError::raiseNotice(0, "'layout' variable is ".$this->_layout.", acceptable are: 'favs','tags','mcats','myitems','author', this may be due to some 3rd party plugin");
			$this->_layout = '';
			JRequest::setVar('layout', '');
		}
		
		$this->_clayout = JRequest::getCmd('clayout', '');  // !! This should be empty for using view's configured clayout (template)
		
		if ($this->_layout=='author') {
			$this->_authorid = JRequest::getInt('authorid', 0);
		}
		else if ($this->_layout=='myitems') {
			$this->_authorid = $user->id;
		}
		else if ($this->_layout=='favs') {
			$this->_uid = $user->id;
		}
		else if ($this->_layout=='tags') {
			$_tagid = JRequest::getInt('tagid', '');
			$this->_tagid = $_tagid;
		}
		else if ($this->_layout=='mcats') {
			$_cids = JRequest::getVar('cids', '');
			if ( !is_array($_cids) ) {
				$_cids = preg_replace( '/[^0-9,]/i', '', (string) $_cids );
				$_cids = explode(',', $_cids);
			}
			// make sure given data are integers ... !!
			$this->_ids = array();
			foreach ($_cids as $i => $_id)  if ((int)$_id) $this->_ids[] = (int)$_id;
			
			// Clear category id, it is not used by this layout
			$this->_id = 0;
		}
		else if (!$this->_id) {
		}
		
		// Set behaviour variables into state
		$this->setState('layout', $this->_layout);
		$this->setState('authorid', $this->_authorid);
		$this->setState('tagid', $this->_tagid);
		$this->setState('uid', $this->_uid);
		$this->setState('cids', $this->_ids);
		$this->setState('clayout', $this->_clayout);
		
		// Other
		//if ($this->_id) $app->setUserState( $option.'.nav_catid',  $this->_id );
		$this->setState('option', $option);
		
		// We set category parameters to component parameters, these will be full calculated when getCategory() is called
		$this->_params = new JRegistry();
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$this->_params->merge($cparams);
		
		
		// ******************************************************************************
		// Set state EXCEPT: limit and limitstart that will be calculated at getData()
		// and after getCategory() has been called thus parameters have been loaded fully
		// ******************************************************************************

		// Set filter order variables into state
		$this->setState('filter_order', JRequest::getCmd('filter_order', 'i.title', 'default'));
		$this->setState('filter_order_Dir', JRequest::getCmd('filter_order_Dir', 'ASC', 'default'));
		
		// Get minimum word search length
		//if ( !$app->getUserState( $option.'.min_word_len', 0 ) ) {  // Do not cache to allow configuration changes
			$db = JFactory::getDBO();
			$db->setQuery("SHOW VARIABLES LIKE '%ft_min_word_len%'");
			$_dbvariable = $db->loadObject();
			$min_word_len = (int) @ $_dbvariable->Value;
			$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
			$min_word_len = !$search_prefix ?  $min_word_len : 1;
			$app->setUserState($option.'.min_word_len', $min_word_len);
		//}
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
			$this->_comments  = null;
			$this->_clayout   = null;
		}
		$this->_id = $cid;
	}
	
	
	/**
	 * Method to set & override item's layout
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function setCatLayout($name=null)
	{
		$this->_clayout = $name;
		$this->setState('clayout', $this->_clayout);
	}
	
	
	/**
	 * Method to get Data
	 *
	 * @access public
	 * @return mixed
	 */
	function getData()
	{
		// Make sure category has been loaded (false means category view without current category)
		if ( $this->_category === null) $this->getCategory();
		
		$app     = JFactory::getApplication();
		$cparams = $this->_params;
		
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		$this->_listall = $app->input->get('listall');
		
		// Set the pagination variables into state (We get them from http request OR use default category parameters)
		$this->_active_limit = strlen( $app->input->get('limit') );
		$limit = $this->_active_limit ? $app->input->get('limit', 0, 'int') : $this->_params->get('limit');
		$limitstart	= $app->input->get('limitstart', $app->input->get('start', 0, 'int'), 'int');
		
		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );
		JRequest::setVar('limitstart', $limitstart);  // Make sure it is limitstart is set
		$app->input->set('limitstart', $limitstart);
		
		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
		
		
		// Allow limit zero to achieve a category view without items
		if ($limit <= 0)
		{
			$this->_data = array();
		}
		else if ( $this->_data===null )
		{
			if ( $print_logging_info )  $start_microtime = microtime(true);
			// Load the content if it doesn't already exist
			
			// 1, create full query: filter, ordered, limited
			$query = $this->_buildQuery();
			
			// Check if Text Search / Filters / AI are NOT active and special before FORM SUBMIT (per page) -limit- was configured
			$use_limit_before = $app->getUserState('use_limit_before_search_filt', 0);
			if ( $use_limit_before )
			{
				$limit_before = (int) $cparams->get('limit_before_search_filt', 0);
				$limit = $use_limit_before  ?  $limit_before  :  $limit;
				JRequest::setVar('limit', $limit);
				$app->input->set('limit', $limit);
				$this->setState('limit', $limit);
			}
			
			try {
				// 2, get items, we use direct query because some extensions break the SQL_CALC_FOUND_ROWS, so let's bypass them (at this point it is OK)
				// *** Usage of FOUND_ROWS() will fail when (e.g.) Joom!Fish or Falang are installed, in this case we will be forced to re-execute the query ...
				// PLUS, we don't need Joom!Fish or Falang layer at --this-- STEP which may slow down the query considerably in large sites
				$query_limited = $query . ' LIMIT '.$limit.' OFFSET '.$limitstart;
				$rows = flexicontent_db::directQuery($query_limited);
				$query_ids = array();
				foreach ($rows as $row) $query_ids[] = $row->id;
				//$this->_db->setQuery($query, $limitstart, $limit);
				//$query_ids = $this->_db->loadColumn();
				
				// 3, get current items total for pagination
				$this->_db->setQuery("SELECT FOUND_ROWS()");
				$this->_total = $this->_db->loadResult();
			}
			
			catch (Exception $e) {
				// 2, get items via normal joomla SQL layer
				$this->_db->setQuery($query, $limitstart, $limit);
				$query_ids = $this->_db->loadColumn();
				if ($this->_db->getErrorNum())  $app->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
				
				// 3, get current items total for pagination
				if ( count($query_ids) ) {
					if ( !$this->_total ) $this->getTotal();
				} else {
					$this->_total = 0;
				}
			}
			// Assign total number of items found this will be used to decide whether to do item counting per filter value
			global $fc_catview;
			$fc_catview['view_total']  = $this->_total;
			
			/*if ((int)$this->getState('limitstart') < (int)$this->_total) {
				$this->_data = $this->_getList( $query, $limitstart, $limit );
			} else {
				$this->setState('limitstart', 0);
				$this->setState('start', 0);
				JRequest::setVar('start', 0);
				JRequest::setVar('limitstart', 0);
				$app->input->set('start', 0);
				$app->input->set('limitstart', 0);
				$this->_data = $this->_getList( $query, 0, $limit );
			}*/
			
			// 4, get item data
			if (count($query_ids)) $query = $this->_buildQuery($query_ids);
			$_data = array();
			if (count($query_ids)) {
				$this->_db->setQuery($query);
				$_data = $this->_db->loadObjectList('id');
				if ($this->_db->getErrorNum())  $app->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
			}
			
			// 5, reorder items
			$this->_data = array();
			if ($_data) foreach($query_ids as $item_id) {
				$this->_data[] = $_data[$item_id];
			}
			
			// Get Original content ids for creating some untranslatable fields that have share data (like shared folders)
			flexicontent_db::getOriginalContentItemids($this->_data);
			
			if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}
		
		// maybe removed in the future, this is useful in places that item data need to be retrieved again because item object was not given
		global $fc_list_items;
		foreach ($this->_data as $_item) $fc_list_items[$_item->id] = $_item;
		
		// Remove search prefix from 'search_index' column
		//$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		//if ($search_prefix) foreach ($this->_data as $_item) $_item->search_index = preg_replace('/\b'.$search_prefix.'/u', '', $_item->search_index);
		
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
		if ( $this->_total===null )
		{
			//$query = $this->_buildQuery();
			//$this->_total = (int) $this->_getListCount($query);
			
			$query_count = $this->_buildQuery(-1);
			$this->_db->setQuery($query_count);
			$this->_total = $this->_db->loadResult();
		}

		return $this->_total;
	}
	
	
	/**
	 * Method to get the pagination object
	 *
	 * @access	public
	 * @return	string
	 */
	public function getPagination()
	{
		// Load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			$limit = (int) $this->getState('limit');
			$limitstart = (int) $this->getState('limitstart');
			
			//jimport('cms.pagination.pagination');
			require_once (JPATH_COMPONENT.DS.'helpers'.DS.'pagination.php');
			$this->_pagination = new FCPagination($this->getTotal(), $limitstart, $limit);
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
	function _buildQuery( $query_ids=false, $count_total = true )
	{
		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		$option  = $this->getState('option');
		$params  = $this->_params;
		$counting=true;
		$extra_where = '';
		
		if ( $query_ids==-1 ) {
			// Get FROM and JOIN SQL CLAUSES
			$fromjoin = $this->_buildItemFromJoin($counting);
			
			// ... count rows
			// Create sql WHERE clause
			$where = $this->_buildItemWhere('where', $counting, $extra_where);
		} else if ( !$query_ids ) {
			// Get FROM and JOIN SQL CLAUSES
			$fromjoin = $this->_buildItemFromJoin($counting);
			
			// Create sql WHERE clause
			$where = $this->_buildItemWhere('where', $counting, $extra_where);
			
			// Create sql ORDERBY clause -and- set 'order' variable (passed by reference), that is, if frontend user ordering override is allowed
			$order = '';
			$orderby = $this->_buildItemOrderBy($order);
			$orderby_join = '';
			
			if ($this->_id) $app->setUserState( $option.'.'.$this->_id.'.nav_orderby',  $order );
			
			// Set order array (2-level) in case it is later needed
			$this->_category->_order_arr = $order;
			
			// Create JOIN for ordering items by a custom field (Level 1)
			if ( 'field' == $order[1] ) {
				$orderbycustomfieldid = (int)$params->get('orderbycustomfieldid', 0);
				$orderbycustomfieldint = (int)$params->get('orderbycustomfieldint', 0);
				if ($orderbycustomfieldint==4) {
					$orderby_join .= '
						LEFT JOIN (
							SELECT rf.item_id, SUM(fdat.hits) AS file_hits
							FROM #__flexicontent_fields_item_relations AS rf
							LEFT JOIN #__flexicontent_files AS fdat ON fdat.id = rf.value
					 		WHERE rf.field_id='.$orderbycustomfieldid.'
					 		GROUP BY rf.item_id
					 	) AS dl ON dl.item_id = i.id';
				}
				else $orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.$orderbycustomfieldid;
			}
			if ( 'custom:' == substr($order[1], 0, 7) ) {
				$order_parts = preg_split("/:/", $order[1]);
				$_field_id = (int) @ $order_parts[1];
				$_o_method = @ $order_parts[2];
				if ($_field_id && count($order_parts)==4) {
					if ($_o_method=='file_hits') {
						$orderby_join .= '
							LEFT JOIN (
								SELECT rf.item_id, SUM(fdat.hits) AS file_hits
								FROM #__flexicontent_fields_item_relations AS rf
								LEFT JOIN #__flexicontent_files AS fdat ON fdat.id = rf.value
						 		WHERE rf.field_id='.$_field_id.'
						 		GROUP BY rf.item_id
						 	) AS dl ON dl.item_id = i.id';
					}
					else $orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.$_field_id;
				}
			}
			
			// Create JOIN for ordering items by a custom field (Level 2)
			if ( 'field' == $order[2] ) {
				$orderbycustomfieldid_2nd = (int)$params->get('orderbycustomfieldid'.'_2nd', 0);
				$orderbycustomfieldint_2nd = (int)$params->get('orderbycustomfieldint'.'_2nd', 0);
				if ($orderbycustomfieldint_2nd==4) {
					$orderby_join .= '
						LEFT JOIN (
							SELECT f2.item_id, SUM(fdat2.hits) AS file_hits2
							FROM #__flexicontent_fields_item_relations AS f2
							LEFT JOIN #__flexicontent_files AS fdat2 ON fdat2.id = f2.value
					 		WHERE f2.field_id='.$orderbycustomfieldid_2nd.'
					 		GROUP BY f2.item_id
					 	) AS dl2 ON dl2.item_id = i.id';
				}
				else $orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f2 ON f2.item_id = i.id AND f2.field_id='.$orderbycustomfieldid_2nd;
			}
			if ( 'custom:' == substr($order[2], 0, 7) ) {
				$order_parts = preg_split("/:/", $order[2]);
				$_field_id = (int) @ $order_parts[1];
				$_o_method = @ $order_parts[2];
				if ($_field_id && count($order_parts)==4) {
					if ($_o_method=='file_hits') {
						$orderby_join .= '
							LEFT JOIN (
								SELECT f2.item_id, SUM(fdat2.hits) AS file_hits2
								FROM #__flexicontent_fields_item_relations AS f2
								LEFT JOIN #__flexicontent_files AS fdat2 ON fdat2.id = f2.value
						 		WHERE f2.field_id='.$_field_id.'
						 		GROUP BY f2.item_id
						 	) AS dl2 ON dl2.item_id = i.id';
					}
					else $orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f2 ON f2.item_id = i.id AND f2.field_id='.$_field_id;
				}
			}
			
			// Create JOIN for ordering items by author's name
			if ( in_array('author', $order) || in_array('rauthor', $order) ) {
				$orderby_col = '';
				$orderby_join .= ' LEFT JOIN #__users AS u ON u.id = i.created_by';
			}
			
			// Create JOIN for ordering items by a most commented
			if ( in_array('commented', $order) ) {
				$orderby_col   = ', COUNT(DISTINCT com.id) AS comments_total';
				$orderby_join .= ' LEFT JOIN #__jcomments AS com ON com.object_id = i.id AND com.object_group="com_flexicontent" AND com.published="1"';
			}
			
			// Create JOIN for ordering items by a most rated
			if ( in_array('rated', $order) ) {
				$orderby_col   = ', (cr.rating_sum / cr.rating_count) * 20 AS votes';
				$orderby_join .= ' LEFT JOIN #__content_rating AS cr ON cr.content_id = i.id';
			}
		} else {
			// Get FROM and JOIN SQL CLAUSES
			$fromjoin = $this->_buildItemFromJoin();
			
			// SELECT sub-clause to calculate details of the access level items in regards to current user
			$select_access = $this->_buildAccessSelect();
			
			// Create JOIN (and select column) of image field used as item image in RSS feed
			$feed_image_source = JRequest::getCmd("type", "") == "rss"  ?  (int) $params->get('feed_image_source', 0)  :  0;
			if ($feed_image_source) {
				$feed_img_join = ' LEFT JOIN #__flexicontent_fields_item_relations AS img ON img.item_id = i.id AND img.field_id='.$feed_image_source;
				$feed_img_col = ', img.value as image';
			}
		}
		
		if ( $query_ids==-1 ) {
			$query = ' SELECT count(i.id) ';
		} else if ( !$query_ids ) {
			$query = 'SELECT '.($count_total ? 'SQL_CALC_FOUND_ROWS' : '').' DISTINCT i.id ';  // SQL_CALC_FOUND_ROWS, will cause problems with 3rd-party extensions that modify the query, this will be tried with direct DB query
			$query .= @ $orderby_col;
		} else {
			$query = 'SELECT i.*, ie.*, u.name as author, ty.name AS typename, ty.alias AS typealias, rel.catid as rel_catid,'
				. ' c.title AS maincat_title, c.alias AS maincat_alias,'  // Main category data
				. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
				. @ $feed_img_col      // optional
				. $select_access
				;
		}
		
		$query .= $fromjoin;
		
		if ( $query_ids==-1 ) {
			$query .= ""
				. $where . $extra_where
				. ' GROUP BY i.id '
				;
		} else if ( !$query_ids ) {
			$query .= ""
				. @ $orderby_join  // optional
				. $where . $extra_where
				//. ' GROUP BY i.id '
				. $orderby
				;
		} else {
			$query .= ""
				. @ $feed_img_join    // optional
				. ' WHERE i.id IN ('. implode(',', $query_ids) .')'
				. ' GROUP BY i.id'
				//. ' ORDER BY FIELD(i.id, '. implode(',', $query_ids) .')'
				;
		}
		
		//echo $query."<br/><br/> \n";
		return $query;
	}
	
	
	
	/**
	 * Retrieve subcategory ids of a given category
	 *
	 * @access public
	 * @return string
	 */
	function &_getDataCats($id_arr)
	{
		if ( $this->_data_cats!==null ) return $this->_data_cats;

		global $globalcats;
		$cparams  = $this->_params;
		$user     = JFactory::getUser();
		$ordering = 'c.lft ASC';

		$show_noauth = $cparams->get('show_noauth', 0);   // show unauthorized items
		$display_subcats = $cparams->get('display_subcategories_items', 2);   // include subcategory items
		
		// Select only categories that user has view access, if listing of unauthorized content is not enabled
		$joinaccess = '';
		$andaccess = '';
		if (!$show_noauth) {
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$andaccess .= ' AND c.access IN (0,'.$aid_list.')';
		}
		
		// Calculate categories to use for retrieving items
		$query_catids = array();
		foreach ($id_arr as $id)
		{
			$query_catids[$id] = 1;
			if ( $display_subcats==2 && !empty($globalcats[$id]->descendantsarray) ) {
				foreach ($globalcats[$id]->descendantsarray as $subcatid) $query_catids[$subcatid] = 1;
			}
		}
		$query_catids = array_keys($query_catids);
		
		// Items in featured categories
		/*$cats_featured = $cparams->get('display_cats_featured', 0);
		$featured_cats_parent = $cparams->get('featured_cats_parent', 0);
		$query_catids_exclude = array();
		if ($cats_featured && $featured_cats_parent) {
			foreach ($globalcats[$featured_cats_parent]->descendantsarray as $subcatid) $query_catids_exclude[$subcatid] = 1;
		}*/
		
		// filter by depth level
		if ($display_subcats==0) {
			// Include categories
			$anddepth = ' AND c.id IN (' .implode(',', $query_catids). ')';
		} else if ($display_subcats==1) {
			// Include categories and their subcategories
			$anddepth  = ' AND ( c.parent_id IN (' .implode(',', $query_catids). ')  OR  c.id IN (' .implode(',', $query_catids). ') )';
		} else {
			// Include categories and their descendants
			$anddepth = ' AND c.id IN (' .implode(',', $query_catids). ')';
		}
		
		// Finally create the query to get the category ids.
		// NOTE: this query is not just needed to get 1st level subcats, but it always needed TO ALSO CHECK the ACCESS LEVEL
		$query = 'SELECT c.id'
			. ' FROM #__categories AS c'
			. $joinaccess
			. ' WHERE c.published = 1'
			. $andaccess
			. $anddepth
			. ' ORDER BY '.$ordering
			;
		
		$this->_db->setQuery($query);
		$this->_data_cats = $this->_db->loadColumn();
		if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
		
		return $this->_data_cats;
	}
	
	
	
	/**
	 * Method to build the part of SELECT clause that calculates item access
	 *
	 * @access private
	 * @return string
	 */
	function _buildAccessSelect()
	{
		$user    = JFactory::getUser();
		$cparams = $this->_params;
		$show_noauth = $cparams->get('show_noauth', 0);
		
		$select_access = '';
		
		// Extra access columns for main category and content type (item access will be added as 'access')
		$select_access .= ', c.access as category_access, ty.access as type_access';
		
		if ($show_noauth) {
			// Access Flags for: content type, main category, item
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$select_access .= ', '
				.' CASE WHEN '
				.'  ty.access IN (0,'.$aid_list.') AND '
				.'   c.access IN (0,'.$aid_list.') AND '
				.'   i.access IN (0,'.$aid_list.') '
				.' THEN 1 ELSE 0 END AS has_access';
		} else {
			$select_access .= ', 1 AS has_access';
		}
		
		return $select_access;
	}
	
	
	/**
	 * Build the order clause for item listing
	 *
	 * @access private
	 * @return string
	 */
	function _buildItemOrderBy(& $order='')
	{
		$request_var = $this->_params->get('orderby_override', 0) || $this->_params->get('orderby_override_2nd', 0) ? 'orderby' : '';
		$default_order = $this->getState('filter_order');
		$default_order_dir = $this->getState('filter_order_Dir');
		
		// Precedence: $request_var ==> $order ==> $config_param ==> $default_order
		return flexicontent_db::buildItemOrderBy(
			$this->_params,
			$order, $request_var, $config_param='orderby',
			$item_tbl_alias = 'i', $relcat_tbl_alias = 'rel',
			$default_order, $default_order_dir, $sfx='', $support_2nd_lvl=true
		);
	}
	
	
	/**
	 * Build the order clause for subcategory listing
	 *
	 * @access private
	 * @return string
	 */
	function _buildCatOrderBy()
	{
		$request_var = '';
		$config_param = 'subcat_orderby';
		$default_order = 'c.lft';
		$default_order_dir = 'ASC';
		
		// Precedence: $request_var ==> $order ==> $config_param ==> $default_order
		return flexicontent_db::buildCatOrderBy(
			$this->_params,
			$order='', $request_var, $config_param,
			$cat_tbl_alias = 'c', $user_tbl_alias = 'u',
			$default_order, $default_order_dir
		);
	}
	
	
	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return array
	 */
	function _buildItemFromJoin($counting=false)
	{
		static $fromjoin = null;
		if (!empty($fromjoin[$counting])) return $fromjoin[$counting];
		
		$text_search = $this->_buildTextSearch();
		
		$tmp_only = $counting && !$text_search;
		$from_clause = $counting ? ' FROM #__flexicontent_items_tmp AS i ' : ' FROM #__content AS i ';
		
		$_join_clauses = ''
			. ($this->_layout=='favs' ? ' JOIN #__flexicontent_favourites AS fav ON fav.itemid = i.id' : '')
			. ($this->_layout=='tags' ? ' JOIN #__flexicontent_tags_item_relations AS tag ON tag.itemid = i.id' : '')
			. ' JOIN #__flexicontent_types AS ty ON '. ($counting ? 'i.' : 'ie.') .'type_id = ty.id'
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
			. ' JOIN #__categories AS c ON c.id = i.catid'
			. ($counting ? '' : ' LEFT JOIN #__users AS u ON u.id = i.created_by')
			;
		$join_clauses =
			($counting ? '' : ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id ')
			.$_join_clauses;
		$join_clauses_with_text =
			($counting && !$text_search ? '' : ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id '.$text_search)
			.$_join_clauses;
		
		global $fc_catview;
		if ($counting) {
			$fc_catview['join_clauses'] = $join_clauses;
			$fc_catview['join_clauses_with_text'] = $join_clauses_with_text;
		}
		
		$fromjoin[$counting] = $from_clause.$join_clauses_with_text;
		return $fromjoin[$counting];
	}
	
	
	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return array
	 */
	function _buildItemWhere( $wherepart='where', $counting = false, &$extra_where = '' )
	{
		global $globalcats, $fc_catview;
		if ( isset($fc_catview[$wherepart]) ) return $fc_catview[$wherepart];
		
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $this->getState('option');
		$user		= JFactory::getUser();
		$db     = JFactory::getDBO();
		
		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		//  thus the items are published globally at the time the author specified in his/her local clock
		//$app  = JFactory::getApplication();
		//$now  = FLEXI_J16GE ? $app->requestTime : $app->get('requestTime');   // NOT correct behavior it should be UTC (below)
		//$date = JFactory::getDate();
		//$now  = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();              // NOT good if string passed to function that will be cached, because string continuesly different
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate = $db->getNullDate();
		
		$cparams = $this->_params;                      // Get the category parameters
		$lang = flexicontent_html::getUserCurrentLang();  // Get user current language
		$catlang = $cparams->get('language', '');         // Category language parameter, currently UNUSED
		$filtercat  = $cparams->get('filtercat', 0);      // Filter items using currently selected language
		$show_noauth = $cparams->get('show_noauth', 0);   // Show unauthorized items
		$use_tmp = $counting == true;
		
		// First thing we need to do is to select only the requested items
		$where = ' WHERE 1';
		if ($this->_authorid)
			$where .= ' AND i.created_by = ' . $db->Quote($this->_authorid);
		
		// Prevent author's profile item from appearing in the author listings
		if ($this->_authorid && (int)$this->_params->get('authordescr_itemid'))
			$where .= ' AND i.id != ' . (int)$this->_params->get('authordescr_itemid');
		
		// Limit to favourites
		if ($this->_layout=='favs')
			$where .= ' AND fav.userid = ' . $db->Quote($this->_uid);
		
		// Limit to give tag id
		if ($this->_layout=='tags')
			$where .= ' AND tag.tid = ' . $db->Quote($this->_tagid);
		
		if ($this->_id || count($this->_ids)) {
			$id_arr = $this->_id ? array($this->_id) : $this->_ids;
			// Get sub categories used to create items list, according to configuration and user access
			$this->_getDataCats($id_arr);
			$_data_cats = "'".implode("', '", $this->_data_cats)."'";
			$where .= ' AND rel.catid IN ('.$_data_cats.')';
		}
		
		// Get privilege to view non viewable items (upublished, archived, trashed, expired, scheduled).
		// NOTE:  ACL view level is checked at a different place
		$ignoreState = $user->authorise('flexicontent.ignoreviewstate', 'com_flexicontent');
		
		if (!$ignoreState && $this->_layout!='myitems') {
			$OR_isOwner = $user->id ? ' OR i.created_by = '.$user->id : '';
			//$OR_isModifier = $user->id ? ' OR i.modified_by = '.$user->id : '';
			
			// Limit by publication state. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( i.state IN (1, -5) '.$OR_isOwner.')';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			
			// Limit by publish up/down dates. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' ) '.$OR_isOwner.')';       // $OR_isModifier
			$where .= ' AND ( ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' ) '.$OR_isOwner.')';   // $OR_isModifier
		}
		
		// Filter the category view with the active language
		// But not language filter: favourites
		if ($filtercat && $this->_layout!='favs') {
			$lta = 'i';
			$where .= ' AND ( '.$lta.'.language LIKE ' . $db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR '.$lta.'.language="*" ' : '') . ' ) ';
		}
		
		$where .= !FLEXI_J16GE ? ' AND i.sectionid = ' . FLEXI_SECTION : '';

		// Select only items that user has view access, if listing of unauthorized content is not enabled
		// Checking item, category, content type access levels
		if (!$show_noauth && $this->_layout!='myitems') {
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$where .= ' AND ty.access IN (0,'.$aid_list.')';
			$where .= ' AND  c.access IN (0,'.$aid_list.')';
			$where .= ' AND  i.access IN (0,'.$aid_list.')';
		}
		
		
		// Calculate filters and alphaindex WHERE clauses
		$filters_where = $this->_buildFiltersWhere();
		$alpha_where   = $this->_buildAlphaIndexWhere();
		
		
		// All items / Normal only / Featured only
		$flag_featured = (int) $cparams->get('display_flag_featured', 0);
		
		
		// Check if doing a before FORM SUBMIT, "FEATURED ONLY" item list  --  then check if Text Search / Filters / AI are NOT active and LIST-ALL Flag not present
		$use_limit_before = (int) $cparams->get('use_limit_before_search_filt', 0);
		
		$app->setUserState('use_limit_before_search_filt', 0);
		if ( $use_limit_before )
		{
			if ( empty($this->_active_filts) && empty($this->_active_search) && empty($this->_active_ai) && empty($this->_listall) )
			{
				$app->setUserState('use_limit_before_search_filt', $use_limit_before);
				if ($use_limit_before==2) $flag_featured = 2;
			}
		}
		
		
		// Now include featured items according to state calculated above
		if ( !$app->getUserState('use_limit_before_search_filt', 0) )
			switch ($flag_featured)
			{
				case 1: $where .= ' AND i.featured=0'; break;   // 1: normal only
				case 2: $where .= ' AND i.featured=1'; break;   // 2: featured only
				default: break;  // 0: both normal and featured
			}
		else
			switch ($flag_featured)
			{
				case 1: $extra_where .= ' AND i.featured=0'; break;   // 1: normal only
				case 2: $extra_where .= ' AND i.featured=1'; break;   // 2: featured only
				default: break;  // 0: both normal and featured
			}
		
		
		$fc_catview['filters_where'] = $filters_where;
		$fc_catview['alpha_where'] = $alpha_where;
		
		$filters_where = implode(' ', $filters_where);
		
		$fc_catview['where_no_alpha']   = $where . $filters_where;
		$fc_catview['where_no_filters'] = $where . $alpha_where;
		$fc_catview['where_conf_only']  = $where;
		
		$fc_catview['where'] = $where . $filters_where . $alpha_where;
		
		return $fc_catview[$wherepart];
	}
	
	
	/**
	 * Method to build the part of WHERE clause related to Alpha Index
	 *
	 * @access private
	 * @return array
	 */
	function _buildTextSearch()
	{
		global $fc_catview;
		$app      = JFactory::getApplication();
		$option   = $this->getState('option');
		$cparams  = $this->_params;
		$db = $this->_db;
		
		static $text_search = null;
		if ($text_search !== null) return $text_search;
		$text_search = '';
		
		
		// ****************************************
		// Create WHERE clause part for Text Search 
		// ****************************************
		
		$text = JRequest::getString('filter', JRequest::getString('q', ''), 'default');
		
		// Check for LIKE %word% search, for languages without spaces
		$filter_word_like_any = $cparams->get('filter_word_like_any', 0);
		
		$phrase = $filter_word_like_any ?
			JRequest::getWord('searchphrase', JRequest::getWord('p', 'any'),   'default') :
			JRequest::getWord('searchphrase', JRequest::getWord('p', 'exact'), 'default');
		
		$si_tbl = 'flexicontent_items_ext';
		
		$search_prefix = $cparams->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		$text = !$search_prefix  ?  trim( $text )  :  preg_replace('/(\b[^\s,\.]+\b)/u', $search_prefix.'$0', trim($text));
		$words = preg_split('/\s\s*/u', $text);
		
		$this->_active_search = $text;  // Set _relevant _active_* FLAG
		
		if( strlen($text) )
		{
			$ts = 'ie';
			$escaped_text = $db->escape($text, true);
			$quoted_text = $db->Quote( $escaped_text, false );
			
			switch ($phrase)
			{
				case 'natural':
					$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.') ';
					break;
				
				case 'natural_expanded':
					$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' WITH QUERY EXPANSION) ';
					break;
				
				case 'exact':
					$stopwords = array();
					$shortwords = array();
					if (!$search_prefix) $words = flexicontent_db::removeInvalidWords($words, $stopwords, $shortwords, $si_tbl, 'search_index', $isprefix=0);
					if (empty($words)) {
						// All words are stop-words or too short, we could try to execute a query that only contains a LIKE %...% , but it would be too slow
						JRequest::setVar('ignoredwords', implode(' ', $stopwords));
						JRequest::setVar('shortwords', implode(' ', $shortwords));
						$_text_match = ' 0=1 ';
					} else {
						// speed optimization ... 2-level searching: first require ALL words, then require exact text
						$newtext = '+' . implode( ' +', $words );
						$quoted_text = $db->escape($newtext, true);
						$quoted_text = $db->Quote( $quoted_text, false );
						$exact_text  = $db->Quote( '%'. $escaped_text .'%', false );
						$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) AND '.$ts.'.search_index LIKE '.$exact_text;
					}
					break;
				
				case 'all':
					$stopwords = array();
					$shortwords = array();
					if (!$search_prefix) $words = flexicontent_db::removeInvalidWords($words, $stopwords, $shortwords, $si_tbl, 'search_index', $isprefix=1);
					JRequest::setVar('ignoredwords', implode(' ', $stopwords));
					JRequest::setVar('shortwords', implode(' ', $shortwords));
					
					$newtext = '+' . implode( '* +', $words ) . '*';
					$quoted_text = $db->escape($newtext, true);
					$quoted_text = $db->Quote( $quoted_text, false );
					$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					break;
				
				case 'any':
				default:
					// Check for LIKE %word% search, for languages without spaces
					if ($filter_word_like_any) {
						$_text_match = ' LOWER ('.$ts.'.search_index) LIKE '.$db->Quote( '%'.$escaped_text.'%', false );
					} else {
						$stopwords = array();
						$shortwords = array();
						if (!$search_prefix) $words = flexicontent_db::removeInvalidWords($words, $stopwords, $shortwords, $si_tbl, 'search_index', $isprefix=1);
						JRequest::setVar('ignoredwords', implode(' ', $stopwords));
						JRequest::setVar('shortwords', implode(' ', $shortwords));
						
						$newtext = implode( '* ', $words ) . '*';
						$quoted_text = $db->escape($newtext, true);
						$quoted_text = $db->Quote( $quoted_text, false );
						$_text_match = ' MATCH ('.$ts.'.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
					}
					break;
			}
			
			$text_search = ' AND '. $_text_match;
		}
		$fc_catview['search'] = $text_search;
		
		return $text_search;
	}
	
	
	/**
	 * Method to build the part of WHERE clause related to Alpha Index
	 *
	 * @access private
	 * @return array
	 */
	function _buildFiltersWhere()
	{
		global $fc_catview;
		$app      = JFactory::getApplication();
		$option   = $this->getState('option');
		$cparams  = $this->_params;
		$db = $this->_db;
		
		$filters_where = array();
		
		// Get filters these are EITHER (a) all filters (to do active only) OR (b) Locked filters
		// USING all filters here to allow filtering via module, thus category view can be filtered even if 'use_filters' is OFF
		$shown_filters  = FlexicontentFields::getFilters( 'filters', /*'use_filters'*/ '__ALL_FILTERS__', $cparams, $check_access=true );
		$locked_filters = FlexicontentFields::getFilters( 'persistent_filters', 'use_persistent_filters', $cparams, $check_access=false );
		$filters = array();
		if ($shown_filters)  foreach($shown_filters  as $_filter) $filters[$_filter->id] = $_filter;
		if ($locked_filters) foreach($locked_filters as $_filter) $filters[$_filter->id] = $_filter;
		
		// Override text search auto-complete category ids with those of filter 13
		$f13_val = JRequest::getVar('filter_13');
		if ( isset($filters[13]) && !empty($f13_val) )
		{
			$cparams->set('txt_ac_cid', 'NA');
			$cparams->set('txt_ac_cids', is_array($f13_val) ? $f13_val : array((string) $f13_val) );
		}
		
		// Get SQL clause for filtering via each field
		$return_sql = 2;
		if ($filters) foreach ($filters as $filter)
		{
			// Get filter values, setting into appropriate session variables
			$filt_vals  = JRequest::getVar('filter_'.$filter->id, '', '');
			
			// Skip filters without value
			$empty_filt_vals_array  = is_array($filt_vals)  && !strlen(trim(implode('',$filt_vals)));
			$empty_filt_vals_string = !is_array($filt_vals) && !strlen(trim($filt_vals));
			$allow_filtering_empty = $filter->parameters->get('allow_filtering_empty', 0);
			
			if ( !$allow_filtering_empty && ($empty_filt_vals_array || $empty_filt_vals_string) ) continue;
			if ( !$empty_filt_vals_array && !$empty_filt_vals_string) $this->_active_filts[ $filter->id ] = $filt_vals;  // Set _relevant _active_* FLAG
			
			//echo "category model found filters: "; print_r($filt_vals);
			$filters_where[ $filter->id ] = $this->_getFiltered($filter, $filt_vals, $return_sql);
		}
		
		return $filters_where;
	}
	
	
	/**
	 * Method to get the visible category filters according to category parameters
	 *
	 * @access private
	 * @return array
	 */
	function &getFilters() {
		return FlexicontentFields::getFilters('filters', 'use_filters', $this->_params, $check_access=true);
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
		
		if (StringHelper::strlen($alpha)==0) {
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
			$regexp = '"^('.StringHelper::substr($alpha,0,1);
			for($i=1; $i<StringHelper::strlen($alpha); $i++) :
				$regexp .= '|'.StringHelper::substr($alpha,$i,1);
			endfor;
			$regexp .= ')"';
		}
		
		else if (count($range) == 2)
		{
			
			// Get range characters
			$startletter = $range[0];  $endletter = $range[1];
			
			// ERROR CHECK: Range START and END are single character strings
			if (StringHelper::strlen($startletter) != 1 || StringHelper::strlen($endletter) != 1) {
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
			if ($alpha == '0') {
				$where = ' AND ( CONVERT (( i.title ) USING BINARY) REGEXP CONVERT ('.$regexp.' USING BINARY) )' ;
			}
			elseif (!empty($alpha)) {
				$where = ' AND ( CONVERT (LOWER( i.title ) USING BINARY) REGEXP CONVERT ('.$regexp.' USING BINARY) )' ;
			}
			//$alpha_term = $this->_db->escape( '^['.$alpha.']', true );
			//$where = ' AND LOWER( i.title ) RLIKE '.$this->_db->Quote( $alpha_term, false );
		}
		
		$this->_active_ai = $alpha;  // Set _relevant _active_* FLAG
		return $where;
	}
	
	
	/**
	 * Method to build the childs categories query
	 *
	 * @access private
	 * @return string
	 */
	function _buildChildsQuery($id=0)
	{
		$user    = JFactory::getUser();
		$cparams = $this->_params;
		$show_noauth = $cparams->get('show_noauth', 0);
		
		// Select only categories that user has view access, if listing of unauthorized content is not enabled
		$joinaccess = '';
		$andaccess = '';
		if (!$show_noauth) {
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$andaccess .= ' AND c.access IN (0,'.$aid_list.')';
		}
		
		// AND-WHERE clause : filter by parent category (-ies)
		if ($id) $id_arr = array($id);
		else $id_arr  = $this->_id ? array($this->_id) : $this->_ids;
		$id_list = implode(',', $id_arr);
		$andparent = ' AND c.parent_id IN ('. $id_list .')';
		
		// ORDER BY clause
		$orderby = $this->_buildCatOrderBy();
		
		// JOIN clause : category creator (needed for J2.5 category ordering)
		$creator_join = ' LEFT JOIN #__users AS u ON u.id = c.created_user_id';
		
		$query = 'SELECT c.*,'
			. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug'
			. ' FROM #__categories AS c'
			. $joinaccess
			. $creator_join
			. ' WHERE c.published = 1'
			.(FLEXI_J16GE ? ' AND c.extension='.$this->_db->Quote(FLEXI_CAT_EXTENSION) : '')
			. $andparent
			. $andaccess
			. $orderby
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
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		
		// Get the view's parameters
		$params = $this->_params;
		$use_tmp = true;
		
		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		//  thus the items are published globally at the time the author specified in his/her local clock
		//$app  = JFactory::getApplication();
		//$now  = FLEXI_J16GE ? $app->requestTime : $app->get('requestTime');   // NOT correct behavior it should be UTC (below)
		//$date = JFactory::getDate();
		//$now  = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();              // NOT good if string passed to function that will be cached, because string continuesly different
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate = $db->getNullDate();
	
		// Get some parameters and other info
		$catlang = $params->get('language', '');          // Category language (currently UNUSED)
		$lang = flexicontent_html::getUserCurrentLang();  // Get user current language
		$filtercat  = $params->get('filtercat', 0);       // Filter items using currently selected language
		$show_noauth = $params->get('show_noauth', 0);    // Show unauthorized items
	
		// First thing we need to do is to select only the requested items
		$where = ' WHERE 1 ';
		if ($this->_authorid)
			$where .= ' AND i.created_by = ' . $db->Quote($this->_authorid);
		
		// Filter the category view with the current user language
		if ($filtercat) {
			$lta = $use_tmp ? 'i': 'ie';
			$where .= ' AND ( '.$lta.'.language LIKE ' . $db->Quote( $lang .'%' ) . ' OR '.$lta.'.language="*" ) ';
		}
		
		// Get privilege to view non viewable items (upublished, archived, trashed, expired, scheduled).
		// NOTE:  ACL view level is checked at a different place
		$ignoreState = $user->authorise('flexicontent.ignoreviewstate', 'com_flexicontent');
		
		if (!$ignoreState) {
			// Limit by publication state. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( i.state IN (1, -5) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			
			// Limit by publish up/down dates. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' ) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';       //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			$where .= ' AND ( ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' ) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		}
		
		// Count items according to full depth level !!!
		$catlist = !empty($globalcats[$id]->descendants) ? $globalcats[$id]->descendants : $id;
		$where .= ' AND rel.catid IN ('.$catlist.')';
		
		// Select only items that user has view access, if listing of unauthorized content is not enabled
		// Checking item, category, content type access level
		if (!$show_noauth) {
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$where .= ' AND ty.access IN (0,'.$aid_list.')';
			$where .= ' AND mc.access IN (0,'.$aid_list.')';
			$where .= ' AND  i.access IN (0,'.$aid_list.')';
		}
		
		$query 	= 'SELECT COUNT(DISTINCT rel.itemid)'
			. ' FROM #__flexicontent_cats_item_relations AS rel'
			. (!$use_tmp ?
				' JOIN #__content AS i ON rel.itemid = i.id' :
				' JOIN #__flexicontent_items_tmp AS i ON rel.itemid = i.id' )
			. (!$use_tmp ? ' JOIN #__flexicontent_items_ext AS ie ON rel.itemid = ie.item_id' : '' )
			. ' JOIN #__flexicontent_types AS ty ON ' .(!$use_tmp ? 'ie' : 'i'). '.type_id = ty.id'
			. ' JOIN #__categories AS mc ON mc.id =   i.catid AND mc.published = 1'
			. $where
			;
		
		$db->setQuery($query);
		$assigneditems = $db->loadResult();
		if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		
		return $assigneditems;
	}

	/**
	 * Method to return sub categories of the give category id
	 * todo: see above and merge
	 *
	 * @access private
	 * @return array
	 */
	function _getsubs($id)
	{
		$cparams = $this->_params;
		$show_noauth	= $cparams->get('show_noauth', 0);
		$user			= JFactory::getUser();
		
		// Where
		$where = ' WHERE c.published = 1';
		$where .= ' AND c.parent_id = '. (int)$id;
		
		// Select only categories that user has view access, if listing of unauthorized content is not enabled
		$joinaccess = '';
		$andaccess = '';
		if (!$show_noauth) {
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$andaccess .= ' AND c.access IN (0,'.$aid_list.')';
		}
		
		// *** Removed : (a) Retrieving all category columns and (b) ordering categories,
		// since this is currently only used for counting subcategories ... so only category ids are retrieved
		$creator_join = FLEXI_J16GE ? ' LEFT JOIN #__users AS u ON u.id = c.created_user_id' : '';
		$orderby = $this->_buildCatOrderBy();
		
		$query = 'SELECT DISTINCT c.id '
			. ' FROM #__categories as c'
			. $joinaccess
			. $creator_join 
			. $where
			. $andaccess
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
		
		// Make sure category has been loaded (false means category view without current category)
		if ( $this->_category === null) $this->getCategory();
		$cparams = $this->_params;
		
		$show_itemcount   = $cparams->get('show_itemcount', 1);
		//$show_subcatcount = $cparams->get('show_subcatcount', 0);
		
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		$query = $this->_buildChildsQuery();
		$this->_childs = $this->_getList($query);
		$id = $this->_id;  // save id in case we need to change it
		$k = 0;
		$count = count($this->_childs);
		for($i = 0; $i < $count; $i++)
		{
			$category =& $this->_childs[$i];
			$category->assigneditems = null;
			if ($show_itemcount) {
				if ( $print_logging_info )  $start_microtime = microtime(true);
				$category->assigneditems = $this->_getassigned( $category->id );
				if ( $print_logging_info ) @$fc_run_times['item_counting_sub_cats'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			}
			$category->subcats       = $this->_getsubs( $category->id );
			//$this->_id        = $category->id;
			//$category->items  = $this->getData();
			//$this->_data      = null;
			$k = 1 - $k;
		}
		$this->_id = $id;  // restore id in case it has been changed
		
		return $this->_childs;
	}
	


	/**
	 * Method to get the children of a category
	 *
	 * @access private
	 * @return array
	 */

	function getPeers()
	{
		if ( !$this->_id || !$this->_category ) return array();
		
		// Make sure category has been loaded (false means category view without current category)
		if ( $this->_category === null) $this->getCategory();
		$cparams = $this->_params;
		
		$show_itemcount   = $cparams->get('show_itemcount_peercat', 1);
		//$show_subcatcount = $cparams->get('show_subcatcount_peercat', 0);
		
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		$query = $this->_buildChildsQuery($this->_category->parent_id);
		$this->_peers = $this->_getList($query);
		$id = $this->_id;  // save id in case we need to change it
		$k = 0;
		$count = count($this->_peers);
		for($i = 0; $i < $count; $i++)
		{
			$category =& $this->_peers[$i];
			$category->assigneditems = null;
			if ($show_itemcount) {
				if ( $print_logging_info )  $start_microtime = microtime(true);
				$category->assigneditems = $this->_getassigned( $category->id );
				if ( $print_logging_info ) @$fc_run_times['item_counting_peer_cats'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			}
			$category->subcats       = $this->_getsubs( $category->id );
			//$this->_id        = $category->id;
			//$category->items  = $this->getData();
			//$this->_data      = null;
			$k = 1 - $k;
		}
		$this->_id = $id;  // restore id in case it has been changed
		
		return $this->_peers;
	}

	
	/**
	 * Method to load the Category
	 *
	 * @access public
	 * @return array
	 */
	function getCategory($pk=null, $raiseErrors=true, $checkAccess=true)
	{
		//initialize some vars
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		
		$cparams = $this->_params;
		if ($pk) $this->_id = $pk;  // Set a specific id
		
		$cat_required = $this->_layout == '';
		$cat_usable   = !$this->_layout || $this->_layout!='mcats';
		
		// Clear category id, if current layout cannot be limited to a specific category
		$this->_id = $cat_usable ? $this->_id : 0;
		
		if ( $this->_id )
		{
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.categories.php');  // If category model is loaded from 3rd party code
			$catshelper = new flexicontent_cats($this->_id);
			$parents    = $catshelper->getParentlist($all_cols=false);
			
			$parents_published = true;
			foreach($parents as $parent) if ( !$parent->published )
				{ $parents_published = false; break; }
			
			if ($parents_published)
			{
				// ************************************************************************************************************
				// Retrieve category data, but ONLY if current layout can use it, ('mcats' does not since it uses multiple ids)
				// ************************************************************************************************************
				
				$query 	= 'SELECT c.*,'
					. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
					. ' FROM #__categories AS c'
					. ' WHERE c.id = '.$this->_id
					. ' AND c.published = 1 AND c.extension=' . $this->_db->Quote(FLEXI_CAT_EXTENSION)
					;
				$this->_db->setQuery($query);
				$_category = $this->_db->loadObject();   // False if not found or unpublished
				if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
			}
			
			else {
				$_category = false;  // A parent category is unpublished
			}
		}
		
		else {
			$_category = false;   // No category id given, or category id is not applicable for current layout
		}
		
		
		// *******************************************************************************
		// Check category was found / is published, and throw an error. Note that an empty
		// layout means single category view, so raise an error if category id is missing
		// *******************************************************************************
		
		if (($this->_id || $cat_required) && !$_category)
		{
			$err_mssg = $err_type = false;
			if (!$_category) {
				$err_mssg = JText::sprintf( 'FLEXI_CONTENT_CATEGORY_NOT_FOUND_OR_NOT_PUBLISHED', $this->_id );
				$err_type = 404;
			}
			
			// Throw error -OR- return if errors suppresed
			if ($err_mssg) {
				if (!$raiseErrors) return false;
				if (FLEXI_J16GE) throw new Exception($err_mssg, $err_type); else JError::raiseError($err_type, $err_mssg);
			}
		}
		
		
		// *********************************************************************
		// Some layouts optionally limit to a specific category, for these
		// create an empty category data object (if one was not created already)
		// *********************************************************************
		
		if ($this->_layout) {
			if ( $this->_layout!='mcats' && !empty($_category) ) {
				$this->_category = $_category;
			} else {
				$this->_category = new stdClass;
				$this->_category->published = 1;
				$this->_category->id = $this->_id;   // can be zero for layouts: author/myitems/favs/tags, etc 
				$this->_category->title = '';
				$this->_category->description = '';
				$this->_category->slug = '';
				$this->_category->ids = $this->_ids; // mcats layout but it can be empty, to allow all categories
			}
		}
		else {
			$this->_category = $_category;
		}
		
		
		// *****************************************************
		// Check for proper layout configuration and throw error
		// *****************************************************
		
		if ($this->_layout) {
			$err_mssg = $err_type = false;
			
			if ( !in_array($this->_layout, array('favs','tags','mcats','myitems','author')) ) {
				$err_mssg = JText::sprintf( 'FLEXI_CONTENT_LIST_LAYOUT_IS_NOT_SUPPORTED', $this->_layout );
				$err_type = 404;
			}
			else if ( $this->_layout=='author' && !$this->_authorid ) {
				$err_mssg = JText::_( 'FLEXI_CANNOT_LIST_CONTENT_AUTHORID_NOT_SET');
				$err_type = 404;
			}
			else if ( $this->_layout=='tags' && !$this->_tagid ) {
				$err_mssg = JText::_( 'FLEXI_CANNOT_LIST_CONTENT_TAGID_NOT_SET');
				$err_type = 404;
			}
			else if ( $this->_layout=='myitems' && !$this->_authorid ) {
				$err_mssg = JText::_( 'FLEXI_LOGIN_TO_DISPLAY_YOUR_CONTENT');
				$err_type = 403;
				$login_redirect = true;
			}
			else if ( $this->_layout=='favs' && !$this->_uid ) {
				$err_mssg = JText::_( 'FLEXI_LOGIN_TO_DISPLAY_YOUR_FAVOURED_CONTENT');
				$err_type = 403;
				$login_redirect = true;
			}
			
			// Raise a notice and redirect
			if ($err_mssg)
			{
				if (!$raiseErrors) return false;
				
				if (!empty($login_redirect)) {
					// redirect unlogged user to login
					$uri		= JFactory::getURI();
					$return	= $uri->toString();
					$com_users = FLEXI_J16GE ? 'com_users' : 'com_user';
					$url  = $cparams->get('login_page', 'index.php?option='.$com_users.'&view=login');
					$return = strtr(base64_encode($return), '+/=', '-_,');
					$url .= '&return='.$return; // '&return='.base64_encode($return);
					$url .= '&isfcurl=1';
					
					JError::raiseWarning( $err_type, $err_mssg);
					$app->redirect( $url );
				} else {
					if (FLEXI_J16GE) throw new Exception($err_mssg, $err_type); else JError::raiseError($err_type, $err_mssg);
				}
			}
		}
		
		
		// ************************************
		// Force loading of category parameters
		// ************************************
		
		$this->_loadCategoryParams($force=true);
		$this->_category->parameters = $this->_params;
		
		
		// ******************************************************************
		// Check whether category access level allows access and throw errors
		// but skip checking Access if so requested via function parameter
		// ******************************************************************
		
		if (!$checkAccess) return $this->_category;
		
		// Check access level of category and of its parents
		$canread = true;
		if ( $this->_id )
		{
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$allowed_levels = array_flip($aid_arr);
			$canread = isset($allowed_levels[$this->_category->access]);
			
			if ($canread) {
				foreach($parents as $parent) if ( !isset($allowed_levels[$parent->access]) )
					{ $canread = false; break; }
			}
		}
		
		// Handle unreadable category (issue 403 unauthorized error, redirecting unlogged users to login)
		if ( $this->_id && !$canread )
		{
			if($user->guest) {
				// Redirect to login
				$uri		= JFactory::getURI();
				$return	= $uri->toString();
				$com_users = FLEXI_J16GE ? 'com_users' : 'com_user';
				$url  = $cparams->get('login_page', 'index.php?option='.$com_users.'&view=login');
				$return = strtr(base64_encode($return), '+/=', '-_,');
				$url .= '&return='.$return; // '&return='.base64_encode($return);
				$url .= '&isfcurl=1';
				
				JError::raiseWarning( 403, JText::sprintf("FLEXI_LOGIN_TO_ACCESS", $url));
				$app->redirect( $url );
			} else {
				if ($cparams->get('unauthorized_page', '')) {
					$app->redirect($cparams->get('unauthorized_page'));				
				} else {
					JError::raiseWarning( 403, JText::_("FLEXI_ALERTNOTAUTH_VIEW"));
					$app->redirect( 'index.php' );
				}
			}
		}

		return $this->_category;
	}
	
	
	/**
	 * Method to load the Tags Data, when layout is 'tags'
	 *
	 * @access public
	 * @return array
	 */
	function getTag()
	{
		//get categories
		$query = 'SELECT t.name, t.id,'
				. ' CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
				. ' FROM #__flexicontent_tags AS t'
				. ' WHERE t.id = '.(int)$this->_tagid
				//. ' AND t.published = 1'
				;
		
		$this->_db->setQuery($query);
		$this->_tag = $this->_db->loadObject();       // Execute query to load tag properties
		if ( $this->_tag ) {
			$this->_tag->parameters = $this->_params;   // Assign tag parameters ( already load by setId() )
    }
    
		return $this->_tag;
	}
	
	
	/**
	 * Method to decide which item layout to use
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function decideLayout(&$params)
	{
		// *********************************************************************************
		// Get category layout from configuration if not already set (e.g. via HTTP Request)
		// *********************************************************************************
		
		$app = JFactory::getApplication();
		
		// Decide to use mobile or normal category template layout
		$useMobile = $params->get('use_mobile_layouts', 0 );
		if ($useMobile)
		{
			$force_desktop_layout = $params->get('force_desktop_layout', 0 );
			$mobileDetector = flexicontent_html::getMobileDetector();
			$isMobile = $mobileDetector->isMobile();
			$isTablet = $mobileDetector->isTablet();
			$useMobile = $force_desktop_layout  ?  $isMobile && !$isTablet  :  $isMobile;
		}
		
		// Get category layout (... if not already set), from the configuration parameter (that was decided above)
		$desktop_clayout = $params->get('clayout', 'blog');
		$clayout_default = !$useMobile ? $desktop_clayout : $params->get('clayout_mobile', $desktop_clayout);
		$params->set('clayout_default', $clayout_default);
		
		$clayout = $this->_clayout=='__request__' ?
			$app->input->get('clayout', $clayout_default, 'cmd') :
			$clayout_default ;
		if ( empty($clayout) )  $clayout = $clayout_default;
		
		// Get all templates from cache, (without loading any language file this will be done at the view)
		$themes = flexicontent_tmpl::getTemplates();
		
		// Verify the category layout exists
		if ( !isset($themes->category->{$clayout}) )
		{
			$cat_default_layout = 'blog';  // Layout default
			$fixed_clayout = isset($themes->category->{$cat_default_layout}) ? $cat_default_layout : 'default';
			JFactory::getApplication()->enqueueMessage("<small>Current category Layout (template) is '$clayout' does not exist<br/>- Please correct this in the URL or in Content Type configuration.<br/>- Using Template Layout: '$fixed_clayout'</small>", 'notice');
			$clayout = $fixed_clayout;
			FLEXIUtilities::loadTemplateLanguageFile( $clayout );  // Manually load Template-Specific language file of back fall clayout
		}
		
		
		// *****************************************************************************************
		// Finally set the clayout (template name) into model / category's parameters / HTTP Request
		// *****************************************************************************************
		
		$this->setCatLayout($clayout);
		
		// Maybe these should not be changed ... and instead the view will get correct value from state !!
		$params->set('clayout', $clayout);
		JRequest::setVar('clayout', $clayout);
		$app->input->set('clayout', $clayout);
	}
	
	
	/**
	 * Method to load content article parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadCategoryParams($force=false)
	{
		if ( $this->_params !== NULL && !$force ) return;
		$id = (int)$this->_id;
		
		$app  = JFactory::getApplication();
		$menu = $app->getMenu()->getActive();     // Retrieve active menu item
		if ($menu)
			$menu_params = FLEXI_J16GE ? $menu->params : new JParameter($menu->params);  // Get active menu item parameters
		
		// a. Clone component parameters ... we will use these as parameters base for merging
		$compParams = clone(JComponentHelper::getComponent('com_flexicontent')->params);     // Get the COMPONENT only parameters
		
		$debug_inheritcid = JRequest::getCmd('print') ? 0 : $compParams->get('debug_inheritcid');
		if ($debug_inheritcid) {
			$merge_stack = array();
			array_push($merge_stack, "CLONED COMPONENT PARAMETERS");
			array_push($merge_stack, "MERGED LAYOUT PARAMETERS");
		}
		
		// b. Retrieve category parameters and create parameter object
		if ($id) {
			$query = 'SELECT params FROM #__categories WHERE id = ' . $id;
			$this->_db->setQuery($query);
			$catParams = $this->_db->loadResult();
			$catParams = new JRegistry($catParams);
		} else {
			$catParams = new JRegistry();
		}
		
		// c. Retrieve author parameters if using displaying AUTHOR/MYITEMS layouts, and merge them into category parameters
		if ($this->_authorid!=0) {
			$query = 'SELECT author_basicparams, author_catparams FROM #__flexicontent_authors_ext WHERE user_id = ' . $this->_authorid;
			$this->_db->setQuery($query);
			$author_extdata = $this->_db->loadObject();
			if ($author_extdata)
			{
				// Merge author basic parameters
				$_author_basicreg = new JRegistry($author_extdata->author_basicparams);
				if ($_author_basicreg->get('orderbycustomfieldid')==="0") $_author_basicreg->set('orderbycustomfieldid', '');
				$catParams->merge( $_author_basicreg );
				
				// Merge author OVERRIDDEN category parameters
				$_author_catreg = new JRegistry($author_extdata->author_catparams);
				if ( $_author_basicreg->get('override_currcat_config',0) ) {
					if ($_author_catreg->get('orderbycustomfieldid')==="0") $_author_catreg->set('orderbycustomfieldid', '');
					$catParams->merge( $_author_catreg );
				}
			}
		}
		
		
		// d. Retrieve inherited parameter and create parameter objects
		global $globalcats;
		$heritage_stack = array();
		$inheritcid = $catParams->get('inheritcid', '');
		$inheritcid_comp = $compParams->get('inheritcid', -1);
		$inherit_parent = $inheritcid==='-1' || ($inheritcid==='' && $inheritcid_comp);
		
		// CASE A: inheriting from parent category tree
		if ( $id && $inherit_parent && !empty($globalcats[$id]->ancestorsonly) ) {
			$order_clause = 'level';  // 'FIELD(id, ' . $globalcats[$id]->ancestorsonly . ')';
			$query = 'SELECT title, id, params FROM #__categories'
				.' WHERE id IN ( ' . $globalcats[$id]->ancestorsonly . ')'
				.' ORDER BY '.$order_clause.' DESC';
			$this->_db->setQuery($query);
			$catdata = $this->_db->loadObjectList('id');
			if (!empty($catdata)) {
				foreach ($catdata as $parentcat) {
					$parentcat->params = new JRegistry($parentcat->params);
					array_push($heritage_stack, $parentcat);
					$inheritcid = $parentcat->params->get('inheritcid', '');
					$inherit_parent = $inheritcid==='-1' || ($inheritcid==='' && $inheritcid_comp);
					if ( !$inherit_parent ) break; // Stop inheriting from further parent categories
				}
			}
		}
		
		// CASE B: inheriting from specific category
		else if ( $id && $inheritcid > 0 && !empty($globalcats[$inheritcid]) ){
			$query = 'SELECT title, params FROM #__categories WHERE id = '. $inheritcid;
			$this->_db->setQuery($query);
			$catdata = $this->_db->loadObject();
			if ($catdata) {
				$catdata->params = new JRegistry($catdata->params);
				array_push($heritage_stack, $catdata);
			}
		}
		
		
		// *******************************************************************************************************************
		// Start merging of parameters, OVERRIDE ORDER: layout(template-manager)/component/ancestors-cats/category/author/menu
		// *******************************************************************************************************************
		
		// -1. layout parameters will be placed on top at end of this code ...
		
		// 0. Start from component parameters
		$params = new JRegistry();
		$params->merge($compParams);
		
		// 1. Merge category's inherited parameters (e.g. ancestor categories or specific category)
		while (!empty($heritage_stack)) {
			$catdata = array_pop($heritage_stack);
			if ($catdata->params->get('orderbycustomfieldid')==="0") $catdata->params->set('orderbycustomfieldid', '');
			$params->merge($catdata->params);
			if ($debug_inheritcid) array_push($merge_stack, "MERGED CATEGORY PARAMETERS of (inherit-from) category: ".$catdata->title);
		}
		
		// 2. Merge category parameters (potentially overriden via  author's category parameters)
		if ($catParams->get('orderbycustomfieldid')==="0") $catParams->set('orderbycustomfieldid', '');
		$params->merge($catParams);
		if ($debug_inheritcid && $id)
			array_push($merge_stack, "MERGED CATEGORY PARAMETERS of current category");
		if ($debug_inheritcid && $this->_authorid && !empty($_author_catreg) && $_author_catreg->get('override_currcat_config',0))
			array_push($merge_stack, "MERGED CATEGORY PARAMETERS of (current) author: {$this->_authorid}");
		
		// g. Verify menu item points to current FLEXIcontent object, and then merge menu item parameters
		if ( $menu && !empty($this->mergeMenuParams) ) 
		{
			$this->_menu_itemid = $menu->id;
			
			$view_ok      = @$menu->query['view']     == 'category';
			$cid_ok       = @$menu->query['cid']      == $this->_id;
			$layout_ok    = @$menu->query['layout']   == $this->_layout;
			// Examine author only for author layout, !! thus ignoring empty author_id when layout is 'myitems' or 'favs', for them this is set explicitely (* see populateCategoryState() function)
			$authorid_ok  = ($this->_layout!='author') || (@$menu->query['authorid'] == $this->_authorid);
			// Examine tagid only for tags layout
			$tagid_ok     = ($this->_layout!='tags')   || (@$menu->query['tagid'] == $this->_tagid);
			
			// We will merge menu parameters last, thus overriding the default categories parameters if either
			// (a) override is enabled in the menu or (b) category Layout is 'myitems' or 'favs' or 'tags' or 'mcats' which has no default parameters
			$overrideconf = $menu_params->get('override_defaultconf',0) || $this->_layout=='myitems' || $this->_layout=='favs' || $this->_layout=='mcats' || $this->_layout=='tags';
			$menu_matches = $view_ok && $cid_ok & $layout_ok && $authorid_ok;
			
			if ( $menu_matches && $overrideconf ) {
				// Add - all - menu parameters related or not related to category parameters override
				if ($menu_params->get('orderbycustomfieldid')==="0") $menu_params->set('orderbycustomfieldid', '');
				$params->merge($menu_params);
				if ($debug_inheritcid) array_push($merge_stack, "MERGED CATEGORY PARAMETERS of (current) menu item: ".$menu->id);
			} else if ($menu_matches) {
				// Add menu parameters - not - related to category parameters override
				$params->set( 'page_title', $menu_params->get('page_title') );
				$params->set( 'show_page_heading', $menu_params->get('show_page_heading') );
				$params->set( 'page_heading', $menu_params->get('page_heading') );
			}
			// Always add these
			$params->set( 'item_depth', $menu_params->get('item_depth') );
			$params->set( 'pageclass_sfx', $menu_params->get('pageclass_sfx') );
		}
		
		
		// Parameters meant for lists
		$params->set('show_editbutton', $params->get('show_editbutton_lists', 1));
		$params->set('show_deletebutton', $params->get('show_deletebutton_lists', 0));
		$params->set('show_state_icon', $params->get('show_state_icon_lists', 0));
		$params->set('show_title', $params->get('show_title_lists', 1));
		$params->set('link_titles', $params->get('link_titles_lists', 1));
		
		// Set filter values (initial or locked) via configuration parameters
		if ($params->get('use_persistent_filters')) FlexicontentFields::setFilterValues( $params, 'persistent_filters', $is_persistent=1);
		FlexicontentFields::setFilterValues( $params, 'initial_filters'   , $is_persistent=0);
		
		// Bugs of v2.0 RC2
		if (FLEXI_J16GE) {
			if ( is_array($orderbycustomfieldid = $params->get('orderbycustomfieldid', 0)) ) {
				JError::raiseNotice(0, "FLEXIcontent versions up to to v2.0 RC2a, had a bug, please open category and resave it, you can use 'copy parameters' to quickly update many categories");
				$params->set('orderbycustomfieldid', $orderbycustomfieldid[0]);
			}
			if ( preg_match("/option=com_user&/", $params->get('login_page', '')) ) {
				JError::raiseNotice(0, "The login url seems to be wrongly set in the FLEXIcontent component configuration.<br /> Please replace: <u>option=com_user</u> with <u>option=com_users</u>");
				$params->set( 'login_page', str_replace("com_user&", "com_users&", $params->get('login_page', '')) );
			}
		}
		
		
		// Retrieve Layout's parameters, also deciding the layout
		$this->decideLayout($params);
		$layoutParams = $this->getLayoutparams();
		$layoutParams = new JRegistry($layoutParams);  //print_r($layoutParams);
		
		// Allow global layout parameters to be inherited properly, placing on TOP of all others
		$this->_params = clone($layoutParams);
		$this->_params->merge($params);
		$merge_stack[1] = "MERGED LAYOUT PARAMETERS of '".$this->_clayout ."'";
		
		if ($debug_inheritcid) $app->enqueueMessage(implode("<br/>\n", $merge_stack));
		
		
		// Set category id for TEXT autocomplete (maybe overriden by category filter 13 in getFilters)
		$this->_params->set('txt_ac_cid',  (!empty($this->_id) ? $this->_id : 'NA') );
		// Set category ids for TEXT autocomplete (maybe overriden by category filter 13 in getFilters)
  	$this->_params->set('txt_ac_cids', (!empty($this->_ids) ? $this->_ids : array()) );
  	
		// Include subcat items
		$display_subcats = $this->_params->get('display_subcategories_items', 2);   // include subcategory items
		$this->_params->set('txt_ac_usesubs', (int) $display_subcats );
		
		// Also set into a global variable
		global $fc_catview;
		$fc_catview['params'] = $this->_params;
	}
	
	
	/**
	 * Method to get the active filter result
	 * 
	 * @access private
	 * @return string
	 * @since 1.5
	 */
	function _getFiltered( &$filter, $value, $return_sql=true )
	{
		$field_type = $filter->field_type;
		
		if ( in_array($field_type, array('createdby','modifiedby','type','categories','tags')) )
		{
			$values = is_array($value) ? $value : array($value);
		}
		
		switch($field_type) 
		{
			case 'language':
				$values_quoted = array();
				foreach ($values as $v) $values_quoted[] = $db->Quote($v);
				$query  = 'SELECT id'
						. ' FROM #__flexicontent_items_tmp'
						. ' WHERE language IN ('. implode(",", $values_quoted) .')';
				//$filter_query = ' AND i.created_by IN ('. implode(",", $values_quoted) .')';
				break;
				
			case 'createdby':
				JArrayHelper::toInteger($values);  // Sanitize filter values as integers
				$query  = 'SELECT id'
						. ' FROM #__flexicontent_items_tmp'
						. ' WHERE created_by IN ('. implode(",", $values) .')';
				//$filter_query = ' AND i.created_by IN ('. implode(",", $values) .')';   // no db quoting needed since these were typecasted to ints
				break;

			case 'modifiedby':
				JArrayHelper::toInteger($values);  // Sanitize filter values as integers
				$query  = 'SELECT id'
						. ' FROM #__flexicontent_items_tmp'
						. ' WHERE modified_by IN ('. implode(",", $values) .')';
				//$filter_query = ' AND i.modified_by IN ('. implode(",", $values) .')';   // no db quoting needed since these were typecasted to ints
				break;
			
			case 'type':
				JArrayHelper::toInteger($values);  // Sanitize filter values as integers
				$query  = 'SELECT id'
						. ' FROM #__flexicontent_items_tmp'
						. ' WHERE type_id IN ('. implode(",", $values) .')';
				//$filter_query = ' AND ie.type_id IN ('. implode(",", $values) .')';   // no db quoting needed since these were typecasted to ints
				break;
			
			case 'state':
				$stateids = array ( 'PE' => -3, 'OQ' => -4, 'IP' => -5, 'P' => 1, 'U' => 0, 'A' => 2, 'T' => -2 );
				$values = is_array($value) ? $value : array($value);
				$filt_states = array();
				foreach ($values as $i => $v) if (isset($stateids[$v])) $filt_states[] = $stateids[$v];
				$query  = 'SELECT id'
						. ' FROM #__flexicontent_items_tmp'
						. ' WHERE state IN ('. implode(",", $filt_states) .')';
				//$filter_query = !count($values) ? ' AND 1 ' : ' AND i.state IN ('. implode(",", $filt_states) .')';   // no db quoting needed since these were typecasted to ints
				break;
			
			case 'categories':
				JArrayHelper::toInteger($values);  // Sanitize filter values as integers
				global $globalcats;
				$display_subcats = $this->_params->get('display_subcategories_items', 2);   // include subcategory items
				$query_catids = array();
				foreach ($values as $id)
				{
					if (!$id) continue;
					$query_catids[$id] = 1;
					if ( $display_subcats==2 && !empty($globalcats[$id]->descendantsarray) ) {
						foreach ($globalcats[$id]->descendantsarray as $subcatid) $query_catids[$subcatid] = 1;
					}
				}
				$query_catids = array_keys($query_catids);
				$query  = 'SELECT itemid'
						. ' FROM #__flexicontent_cats_item_relations'
						. ' WHERE catid IN ('. implode(",", $query_catids) .')';
						;
				//$filter_query = ' AND rel.catid IN ('. implode(",", $values) .')';
				break;
			
			case 'tags':
				JArrayHelper::toInteger($values);  // Sanitize filter values as integers
				$query  = 'SELECT itemid'
						. ' FROM #__flexicontent_tags_item_relations'
						. ' WHERE tid IN ('. implode(",", $values) .')';  // no db quoting needed since these were typecasted to ints
						;
				//$filter_query = ' AND tag.tid IN ('. implode(",", $values) .')';
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
					FlexicontentFields::getFiltered($filter, $value, $return_sql) :
					FLEXIUtilities::call_FC_Field_Func($field_type_file, 'getFiltered', array( &$filter, &$value, &$return_sql ));
				break; 
		}
		
		if ( !isset($filter_query) )
		{
			if ( isset($filtered) ) {
				// nothing to do
			} else if ( !isset($query) ) {
				$filtered = '';
				//echo "Unhandled case for filter: ". $filter->name ." in 'FlexicontentModelCategory::getFiltered()', query variable not set<br/>\n";
			} else if ( !$return_sql ) {
				//echo "<br>GET FILTERED Items (cat model) -- [".$filter->name."] using in-query ids :<br>". $query."<br>\n";
				$db = JFactory::getDBO();
				$db->setQuery($query);
				$filtered = $db->loadColumn();
				if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
			}
			else if ($return_sql===2) {
				$db = JFactory::getDBO();
				static $iids_tblname  = array();
				if ( !isset($iids_tblname[$filter->id]) ) {
					$iids_tblname[$filter->id] = 'fc_filter_iids_'.$filter->id;
				}
				$tmp_tbl = $iids_tblname[$filter->id];
				
				try {
					// Use sub-query on temporary table
					$db->setQuery('CREATE TEMPORARY TABLE IF NOT EXISTS '.$tmp_tbl.' (id INT, KEY(`id`))');
					$db->execute();
					$db->setQuery('TRUNCATE TABLE '.$tmp_tbl);
					$db->execute();
					$db->setQuery('INSERT INTO '.$tmp_tbl.' '.$query);
					$db->execute();
					$_query = $query;
					$query = 'SELECT id FROM '.$tmp_tbl;   //echo "<br/><br/>GET FILTERED Items (cat model) -- [".$filter->name."] using temporary table: ".$query." for :".$_query ." <br/><br/>";
					/*$db->setQuery($query);
					$data = $db->loadObjectList();
					echo "<pre>";
					print_r($data);
					exit;*/
				}
				catch (Exception $e) {
					// Ignore table creation error, we will handle it below by creating a subquery
					//if ($db->getErrorNum())  echo 'SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
					//echo "<br/><br/>GET FILTERED Items (cat model) -- [".$filter->name."] using subquery: ".$query." <br/><br/>";
				}
			} else {
				//echo "<br/><br/>GET FILTERED Items (cat model) -- [".$filter->name."] using subquery: ".$query." <br/><br/>";
			}
			
			// Create subquery if temporary table creation failed
			if ( !isset($filtered) && isset($query) )  $filtered = ' AND i.id IN (' . $query . ')';
			
			// An empty return value means no matching values were found
			$filtered = empty($filtered) ? ' AND 0 ' : $filtered;
			
			// A string mean a subquery was returned, while an array means that item ids we returned
			$filter_query = is_array($filtered) ?  ' AND i.id IN ('. implode(',', $filtered) .')' : $filtered;
		}
		//echo "<br/>" .$filter->name. ': '. $filter_query."<br/>";
		
		return $filter_query;
	}
	
	
	/**
	 * Method to get the layout parameters of an item
	 * 
	 * @return string
	 * @since 1.5
	 */
	function getLayoutparams($force = false)
	{
		return $this->_clayout ? flexicontent_tmpl::getLayoutparams('category', $this->_clayout, '', $force) : '';
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
		// Make sure category has been loaded (false means category view without current category)
		if ( $this->_category === null) $this->getCategory();
		$cparams = $this->_params;
		
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		if ( $print_logging_info )  $start_microtime = microtime(true);
		
		$fromjoin = $this->_buildItemFromJoin($counting=true);
		$where    = $this->_buildItemWhere('where_no_alpha');
		
		$query	= //'SELECT DISTINCT i.title_ai AS alpha'
		'SELECT DISTINCT LOWER(SUBSTRING(i.title FROM 1 FOR 1)) AS alpha'
			. $fromjoin
			. $where
			//. ' GROUP BY alpha'
			. ' ORDER BY alpha ASC'
			;
		$this->_db->setQuery($query);
		$alpha = $this->_db->loadColumn();
		
		if ( $print_logging_info ) @$fc_run_times['execute_alphaindex_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
		
		return $alpha;
	}
	
	
	function &getCommentsInfo ( $item_ids = false)
	{
		// Return existing data and initialize array
		if ($this->_comments!==null) return $this->_comments;
		$this->_comments = array();
		
		// Check jcomments plugin is installed and enabled
		if ( !JPluginHelper::isEnabled('system', 'jcomments') )  return $this->_comments;
		
		// Normal case, item ids not given, we will retrieve comments information of cat/sub items
		if ( empty($item_ids) )
		{
			// First make sure item data have been retrieved
			$this->getData();
			if ( empty($this->_data) )  return $this->_comments;
			
			// Then get item ids
			$item_ids = array();
			foreach ($this->_data as $item) $item_ids[] = $item->id;
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
		
		if ( $comments ) $this->_comments = $comments;
		
		return $comments;
	}
	
	
	/**
	 * Increment the hit counter for the category.
	 * @param   int  $pk  Optional primary key of the category to increment.
	 * @return  boolean True if successful; false otherwise and internal error set.
	 */
	public function hit($pk = 0)
	{
		// Initialise variables.
		$pk = !empty($pk) ? $pk : $this->_id;
		if (!$pk) return;
		
		$db = $this->getDBO();
		$query = $db->getQuery(true)
			->update('#__categories')
			->set('hits = hits + 1')
			->where('id = ' . (int) $pk);
		$db->setQuery($query);
		
		try {
			$db->execute();
			if ($db->getErrorNum()) {
				$this->setError( nl2br($db->getErrorMsg()) );  // In case of error not throwing exception
				return false;
			}
		}
		catch (RuntimeException $e) {
			$this->setError( $e->getMessage() );
			return false;
		}
		
		return true;
	}
	
	
	/**
	 * Method to get the nr of favourites of anitem
	 *
	 * @access	public
	 * @return	integer on success
	 * @since	1.0
	 */
	function getFavourites()
	{
		return flexicontent_db::getFavourites($type=1, $this->_id);
	}
	
	
	/**
	 * Method to get the nr of favourites of an user
	 *
	 * @access	public
	 * @return	integer on success
	 * @since	1.0
	 */
	function getFavoured()
	{
		return flexicontent_db::getFavoured($type=1, $this->_id, JFactory::getUser()->id);
	}
	
	
	/**
	 * Method to remove a favourite
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function removefav()
	{
		return flexicontent_db::removefav($type=1, $this->_id, JFactory::getUser()->id);
	}
	
	
	/**
	 * Method to add a favourite
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function addfav()
	{
		return flexicontent_db::addfav($type=1, $this->_id, JFactory::getUser()->id);
	}
}
?>
