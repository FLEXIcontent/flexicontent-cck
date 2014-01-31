<?php
/**
 * @version 1.5 stable $Id: tags.php 1775 2013-09-27 02:04:02Z ggppdk $
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
 * @since		1.5
 */
class FlexicontentModelTags extends JModelLegacy
{
	/**
	 * Current Tag properties
	 *
	 * @var mixed
	 */
	var $_tag = null;
	
	/**
	 * Item list data
	 *
	 * @var array
	 */
	var $_data = null;
	
	/**
	 * Items list total
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
	 * Tags view parameters via menu item or via tag cloud module or ... via global configuration selected menu item
	 *
	 * @var object
	 */
	var $_params = null;
	
	/**
	 * Constructor
	 *
	 * @since 1.5
	 */
	function __construct()
	{
		parent::__construct();
		
		// Set id and load parameters
		$id = JRequest::getInt('id', 0);		
		$this->setId((int)$id);
		$params = & $this->_params;
		
		// Set the pagination variables into state (We get them from http request OR use default tags view parameters)
		$limit = JRequest::getVar('limit') ? JRequest::getVar('limit') : $params->get('limit');
		$limitstart = JRequest::getInt('limitstart');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
		
		// Get the filter request variables
		$this->setState('filter_order', 'i.title');
		$this->setState('filter_order_dir', 'ASC');
	}
	
	
	/**
	 * Method to set initialize data, setting an element id for the view
	 *
	 * @access	public
	 * @param	int
	 */
	function setId($id)
	{
		// Set new tag ID, wipe member variables and load parameters
		$this->_id      = $id;
		$this->_tag     = null;
		$this->_data    = null;
		$this->_total   = null;
		$this->_pagination = null;
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
			if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
		}
		
		return $this->_data;
	}
	
	
	/**
	 * Method to get the total number of items
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
	 * Method to get the pagination object
	 *
	 * @access	public
	 * @return	object
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
	 * Method to build the query
	 *
	 * @access public
	 * @return string
	 */
	function _buildQuery()
	{   	
		$user		= JFactory::getUser();
		$params = & $this->_params;
		
		// image for an image field
		/*$use_image    = (int)$params->get('use_image', 1);
		$image_source = $params->get('image_source');

		// EXTRA select and join for special fields: --image--
		if ($use_image && $image_source) {
			$select_image = ' img.value AS image,';
			$join_image   = '	LEFT JOIN #__flexicontent_fields_item_relations AS img'
				. '	ON ( i.id = img.item_id AND img.valueorder = 1 AND img.field_id = '.$image_source.' )';
		} else {
			$select_image	= '';
			$join_image		= '';
		}*/
		
		// show unauthorized items
		$show_noauth = $params->get('show_noauth', 0);
		
		// Select only items that user has view access, if listing of unauthorized content is not enabled
		$joinaccess	 = '';
		$andaccess   = '';
		$select_access  = '';
		
		// Extra access columns for main category and content type (item access will be added as 'access')
		$select_access .= ', c.access as category_access, ty.access as type_access';
		
		if ( !$show_noauth ) {   // User not allowed to LIST unauthorized items
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$andaccess .= ' AND ty.access IN (0,'.$aid_list.')';
				$andaccess .= ' AND  c.access IN (0,'.$aid_list.')';
				$andaccess .= ' AND  i.access IN (0,'.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gt ON ty.id = gt.axo AND gt.aco = "read" AND gt.axosection = "type"';
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gc ON  c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gi ON  i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
					$andaccess	.= ' AND (gt.aro IN ( '.$user->gmid.' ) OR ty.access <= '. $aid . ')';
					$andaccess	.= ' AND (gc.aro IN ( '.$user->gmid.' ) OR  c.access <= '. $aid . ')';
					$andaccess  .= ' AND (gi.aro IN ( '.$user->gmid.' ) OR  i.access <= '. $aid . ')';
				} else {
					$andaccess  .= ' AND ty.access <= '.$aid;
					$andaccess  .= ' AND  c.access <= '.$aid;
					$andaccess  .= ' AND  i.access <= '.$aid;
				}
			}
			$select_access .= ', 1 AS has_access';
		}
		else {
			// Access Flags for: content type, main category, item
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$select_access .= ', '
					.' CASE WHEN '
					.'  ty.access IN (0,'.$aid_list.') AND '
					.'   c.access IN (0,'.$aid_list.') AND '
					.'   i.access IN (0,'.$aid_list.') '
					.' THEN 1 ELSE 0 END AS has_access';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gt ON ty.id = gt.axo AND gt.aco = "read" AND gt.axosection = "type"';
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gc ON  c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gi ON  i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
					$select_access .= ', '
						.' CASE WHEN '
						.'  (gt.aro IN ( '.$user->gmid.' ) OR ty.access <= '. (int) $aid . ') AND '
						.'  (gc.aro IN ( '.$user->gmid.' ) OR  c.access <= '. (int) $aid . ') AND '
						.'  (gi.aro IN ( '.$user->gmid.' ) OR  i.access <= '. (int) $aid . ') '
						.' THEN 1 ELSE 0 END AS has_access';
				} else {
					$select_access .= ', '
						.' CASE WHEN '
						.'  (ty.access <= '. (int) $aid . ') AND '
						.'  ( c.access <= '. (int) $aid . ') AND '
						.'  ( i.access <= '. (int) $aid . ') '
						.' THEN 1 ELSE 0 END AS has_access';
				}
			}
		}
		
		// Create sql WHERE clause
		$where = $this->_buildItemWhere();
		
		// Create sql ORDERBY clause -and- set 'order' variable (passed by reference), that is, if frontend user ordering override is allowed
		$order = '';
		$orderby = $this->_buildItemOrderBy($order);
		$orderby_join = '';
		
		// Create JOIN for ordering items by a custom field (Level 1)
		if ( 'field' == $order[1] ) {
			$orderbycustomfieldid = (int)$params->get('orderbycustomfieldid', 0);
			$orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id AND f.field_id='.$orderbycustomfieldid;
		}
		
		// Create JOIN for ordering items by a custom field (Level 2)
		if ( 'field' == $order[2] ) {
			$orderbycustomfieldid_2nd = (int)$params->get('orderbycustomfieldid'.'_2nd', 0);
			$orderby_join .= ' LEFT JOIN #__flexicontent_fields_item_relations AS f2 ON f2.item_id = i.id AND f2.field_id='.$orderbycustomfieldid_2nd;
		}
		
		// Create JOIN for ordering items by a most commented
		if ( in_array('commented', $order) ) {
			$orderby_col   = ', count(com.object_id) AS comments_total';
			$orderby_join .= ' LEFT JOIN #__jcomments AS com ON com.object_id = i.id';
		}
		
		// Create JOIN for ordering items by a most rated
		if ( in_array('rated', $order) ) {
			$orderby_col   = ', (cr.rating_sum / cr.rating_count) * 20 AS votes';
			$orderby_join .= ' LEFT JOIN #__content_rating AS cr ON cr.content_id = i.id';
		}
		
		// Create JOIN for ordering items by their ordering attribute (in item's main category)
		if ($order=='order') {
			$orderby_join .= ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id AND rel.catid = i.catid';
		}
		
		$query = 'SELECT i.id, i.*, ie.* '
			//. @ $select_image
			. @ $orderby_col
			. $select_access
			. ', CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug'
			. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ' FROM #__content AS i'
			. ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' JOIN #__flexicontent_tags_item_relations AS tag ON tag.itemid = i.id'
			. ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			. ' JOIN #__categories AS c ON c.id = i.catid'
			. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			//. $join_image
			. @ $orderby_join
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
	function _buildItemOrderBy(& $order='')
	{
		$request_var = $this->_params->get('orderby_override') ? 'orderby' : '';
		$default_order = $this->getState('filter_order');
		$default_order_dir = $this->getState('filter_order_dir');
		
		// Precedence: $request_var ==> $order ==> $config_param ==> $default_order
		return flexicontent_db::buildItemOrderBy(
			$this->_params,
			$order, $request_var, $config_param='orderby',
			$item_tbl_alias = 'i', $relcat_tbl_alias = 'rel',
			$default_order, $default_order_dir
		);
	}
	
	
	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildItemWhere( )
	{
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		
		// Get the view's parameters
		$params = $this->_params;
		
		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		//  thus the items are published globally at the time the author specified in his/her local clock
		//$app  = JFactory::getApplication();
		//$now  = FLEXI_J16GE ? $app->requestTime : $app->get('requestTime');   // NOT correct behavior it should be UTC (below)
		//$date = JFactory::getDate();
		//$now  = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();              // NOT good if string passed to function that will be cached, because string continuesly different
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate = $db->getNullDate();
		
		// First thing we need to do is to select only the requested TAGGED items
		$where = ' WHERE tag.tid = '.$this->_id;
		
		// User current language
		$lang = flexicontent_html::getUserCurrentLang();
		$filtertag  = $params->get('filtertag', 0);
		
		// Filter the tag view with the active language
		if ((FLEXI_FISH || FLEXI_J16GE) && $filtertag) {
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
			$where .= ' AND ( i.state IN (1, -5) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			
			// Limit by publish up/down dates. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' ) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';       //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			$where .= ' AND ( ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' ) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		}
		
		$where .= !FLEXI_J16GE ? ' AND i.sectionid = ' . FLEXI_SECTION : '';

		/*
		 * If we have a filter, and this is enabled... lets tack the AND clause
		 * for the filter onto the WHERE clause of the item query.
		 */
		if ($params->get('use_search'))
		{
			$filter 		= JRequest::getString('filter', '', 'request');

			if ($filter) {
				$search_term = FLEXI_J16GE ? $this->_db->escape( $filter, true ) : $this->_db->getEscaped( $filter, true );
				$where .= ' AND MATCH (ie.search_index) AGAINST ('.$this->_db->Quote( $search_term, false ).' IN BOOLEAN MODE)';
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
		if ( $this->_params !== NULL ) return;
		
		$app  = JFactory::getApplication();
		$menu = $app->getMenu()->getActive();     // Retrieve active menu
		
		// Get the COMPONENT only parameters, then merge the menu parameters
		$comp_params = JComponentHelper::getComponent('com_flexicontent')->params;
		$params = FLEXI_J16GE ? clone ($comp_params) : new JParameter( $comp_params ); // clone( JComponentHelper::getParams('com_flexicontent') );
		if ($menu) {
			$menu_params = FLEXI_J16GE ? $menu->params : new JParameter($menu->params);
			$params->merge($menu_params);
		}
		
		// b. Merge module parameters overriding current configuration
		//   (this done when module id is present in the HTTP request) (tags cloud module include tags view configuration)
		if ( JRequest::getInt('module', 0 ) )
		{
			// load by module name, not used
			//jimport( 'joomla.application.module.helper' );
			//$module_name = JRequest::getInt('module', 0 );
			//$module = JModuleHelper::getModule('mymodulename');
			
			// load by module id
			$module_id = JRequest::getInt('module', 0 );
			$module = JTable::getInstance ( 'Module', 'JTable' );
			
			if ( $module->load($module_id) ) {
				$moduleParams = FLEXI_J16GE ? new JRegistry($module->params) : new JParameter($module->params);
				$params->merge($moduleParams);
			} else {
				JError::raiseNotice ( 500, $module->getError() );
			}
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
				. ' WHERE t.id = '.(int)$this->_id
				. ' AND t.published = 1'
				;
		
		$this->_db->setQuery($query);
		$this->_tag = $this->_db->loadObject();       // Execute query to load tag properties
		if ( $this->_tag ) {
			$this->_tag->parameters = $this->_params;   // Assign tag parameters ( already load by setId() )
    }
    
		return $this->_tag;
	}
	
	
	/**
	 * Method to store the tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function storetag($data)
	{
		$row  =& $this->getTable('flexicontent_tags', '');
		
		// bind it to the table
		if (!$row->bind($data)) {
			$msg = $this->_db->getErrorMsg();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}
		
		// Make sure the data is valid
		if (!$row->check()) {
			$this->setError($row->getError());
			return false;
		}
		
		// Store it in the db
		if (!$row->store()) {
			$msg = $this->_db->getErrorMsg();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}
		$this->_tag = &$row;
		return $row->id;
	}
	
	
	/**
	 * Method to add a tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function addtag($name) {
		$obj = new stdClass();
		$obj->name	 	= $name;
		$obj->published	= 1;
		
		if($this->storetag($obj)) {
			return true;
		}
		return false;
	}
}
?>