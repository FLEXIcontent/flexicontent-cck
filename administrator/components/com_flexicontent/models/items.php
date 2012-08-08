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
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');

/**
 * FLEXIcontent Component Items Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelItems extends JModel
{
	/**
	 * Items data
	 *
	 * @var object
	 */
	var $_data = null;

	/**
	 * Items total
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
	 * Item id
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

		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		$limit		= $mainframe->getUserStateFromRequest( $option.'.items.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
		$limitstart = $mainframe->getUserStateFromRequest( $option.'.items.limitstart', 'limitstart', 0, 'int' );

		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		$array = JRequest::getVar('cid',  0, '', 'array');
		$this->setId((int)$array[0]);

	}

	/**
	 * Method to set the Items identifier
	 *
	 * @access	public
	 * @param	int Category identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
		$this->_extra_fields = null;
	}

	/**
	 * Method to get item data
	 *
	 * @access public
	 * @return object
	 */
	function getData()
	{
		static $tconfig = array();
		
		// Lets load the Items if it doesn't already exist
		if ( $this->_data === null )
		{
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			$this->_db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $this->_db->loadResult();
			
			$k = 0;
			$count = count($this->_data);
			for($i = 0; $i < $count; $i++)
			{
				$item =& $this->_data[$i];
				$item->categories = $this->getCategories($item->id);
				
				// Parse item configuration for every row
	   		$item->config = new JParameter($item->config);
	   		
				// Parse item's TYPE configuration if not already parsed
				if ( isset($tconfig[$item->type_name]) ) {
		   		$item->tconfig = &$tconfig[$item->type_name];
					continue;
				}
				$tconfig[$item->type_name] = new JParameter($item->tconfig);
	   		$item->tconfig = &$tconfig[$item->type_name];
			}
			$k = 1 - $k;
		}
		return $this->_data;
	}			
	
	
	/**
	 * Method to get fields used as extra columns of the item list
	 *
	 * @access public
	 * @return object
	 */
	function getItemList_ExtraFields()
	{
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		$flexiparams = & JComponentHelper::getParams( 'com_flexicontent' );
		$filter_type = $mainframe->getUserStateFromRequest( $option.'.items.filter_type', 	'filter_type', '', 'int' );
		
		// Retrieve the custom field of the items list
		// STEP 1: Get the field properties
		if ( !empty($filter_type) ) {
			$query = 'SELECT t.attribs FROM #__flexicontent_types AS t WHERE t.id = ' . $filter_type;
			$this->_db->setQuery($query);
			$type_attribs = $this->_db->loadResult();
			if ($this->_db->getErrorNum()) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
			}
			$tparams = new JParameter($type_attribs);
			$im_extra_fields = $tparams->get("items_manager_extra_fields");
			$item_instance = new stdClass();
			$item->type_id = $filter_type;
		} else {
			$item_instance = null;
			$im_extra_fields = $flexiparams->get("items_manager_extra_fields");
		}
		$im_extra_fields = preg_split("/[\s]*,[\s]*/", $im_extra_fields);
		
		foreach($im_extra_fields as $im_extra_field) {
			@list($fieldname,$methodname) = preg_split("/[\s]*:[\s]*/", $im_extra_field);
			$methodnames[$fieldname] = empty($methodname) ? 'display' : $methodname;
		}
		
		$field_name = 'tstimga';
		$query = ' SELECT fi.*'
		   .' FROM #__flexicontent_fields AS fi'
		   .' WHERE fi.name IN '. '("' . implode('","',array_keys($methodnames)) . '")'
		   .' ORDER BY FIELD(fi.name, "'. implode('","',array_keys($methodnames)) . '" )';
		$this->_db->setQuery($query);
		$extra_fields = $this->_db->loadObjectList();
		if ($this->_db->getErrorNum()) {
			$jAp=& JFactory::getApplication();
			$jAp->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
		}
		foreach($extra_fields as $field) {
			$field->methodname = $methodnames[$field->name];
			FlexicontentFields::loadFieldConfig($field, $item_instance);
		}
		
		$this->_extra_fields = & $extra_fields;
		return $extra_fields;
	}
	
	
	/**
	 * Method to get fields values of the fields used as extra columns of the item list
	 *
	 * @access public
	 * @return object
	 */
	function getItemList_ExtraFieldValues()
	{
		if ( $this->_extra_fields== null)
			$this->getItemList_ExtraFields();
		if ( empty($this->_extra_fields) ) return;
		
		if ( $this->_data== null)
			$this->getData();
		if ( empty($this->_data) ) return;
		
		foreach($this->_data as $row)
		{
			foreach($this->_extra_fields as $field)
			{
		    // STEP 2: Get the field value for the current item
		    $query = ' SELECT v.value'
					 .' FROM #__flexicontent_fields_item_relations as v'
		       .' WHERE v.item_id = '.(int)$row->id
		       .'   AND v.field_id = '.$field->id
		       .' ORDER BY v.valueorder';
		    $this->_db->setQuery($query);
		    $values = $this->_db->loadResultArray();
				/*if ($this->_db->getErrorNum()) {
					$jAp=& JFactory::getApplication();
					$jAp->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($query."\n".$this->_db->getErrorMsg()."\n"),'error');
				}*/
				$row->extra_field_value[$field->name] = $values;
			}
		}
	}
	
	
	/**
	 * Method to set the default site language to an item with no language
	 * 
	 * @return boolean
	 * @since 1.5
	 */
	function setSiteDefaultLang($id)
	{
		$lang = flexicontent_html::getSiteDefaultLang();
		$query 	= 'UPDATE #__flexicontent_items_ext'
				. ' SET language = ' . $this->_db->Quote($lang)
				. ' WHERE item_id = ' . (int)$id
				;
		$this->_db->setQuery($query);
		$this->_db->query();
					
		return $lang;
	}

	/**
	 * Method to get the extended data associations
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getExtdataStatus()
	{
		$status = array();
		
		$query 	= 'SELECT c.id FROM #__content as c'
					. (FLEXI_J16GE ? ' JOIN #__categories as cat ON c.catid=cat.id' : '')
					. (!FLEXI_J16GE ? ' WHERE sectionid = ' . $this->_db->Quote(FLEXI_SECTION) : ' WHERE cat.extension="'.FLEXI_CAT_EXTENSION.'"')
				;
		$this->_db->setQuery($query);
		$allids = $this->_db->loadResultArray();
		$allids = is_array($allids)?$allids:array();// !important

		$query 	= 'SELECT item_id FROM #__flexicontent_items_ext';
		$this->_db->setQuery($query);
		$allext = $this->_db->loadResultArray();
		$allext = is_array($allext)?$allext:array();// !important

		$query 	= 'SELECT DISTINCT itemid FROM #__flexicontent_cats_item_relations';
		$this->_db->setQuery($query);
		$allcat = $this->_db->loadResultArray();
		$allcat = is_array($allcat)?$allcat:array();// !important

		$query 	= 'SELECT item_id FROM #__flexicontent_fields_item_relations'
				. ' GROUP BY item_id'
				. ' HAVING COUNT(field_id) >= 5'  // we set 5 instead of 7 for the new created items that doesn't have any created date
				;
		$this->_db->setQuery($query);
		$allfi = $this->_db->loadResultArray();
		$allfi = is_array($allfi)?$allfi:array();
		
		$status['allids'] 		= $allids;
		$status['allext'] 		= $allext;
		$status['noext'] 		= array_diff($allids,$allext);
		$status['countnoext'] 	= count($status['noext']);
		$status['allcat'] 		= $allcat;
		$status['nocat'] 		= array_diff($allids,$allcat);
		$status['countnocat'] 	= count($status['nocat']);
		$status['no'] 			= array_unique(array_merge($status['noext'],$status['nocat']));
		$status['countno'] 		= count($status['no']);
		
		return $status;
	}

	/**
	 * Method to get the new added items from the content table
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getUnassociatedItems($limit = 1000000)
	{
		$status = $this->getExtdataStatus();

		if ($status['no']) {
			$and = ' AND c.id IN ( ' . implode(',', $status['no']) . ' )';
			$query 	= 'SELECT c.id, c.title, c.introtext, c.`fulltext`, c.catid, c.created, c.created_by, c.modified, c.modified_by, c.version, c.state'
					. (FLEXI_J16GE ? ', c.language' : '')
					. ' FROM #__content as c'
					. (FLEXI_J16GE ? ' JOIN #__categories as cat ON c.catid=cat.id' : '')
					. (!FLEXI_J16GE ? ' WHERE sectionid = ' . $this->_db->Quote(FLEXI_SECTION) : ' WHERE cat.extension="'.FLEXI_CAT_EXTENSION.'"')
					. $and
					;
			$this->_db->setQuery($query, 0, $limit);
			$unassociated = $this->_db->loadObjectList();
		} else {
			$unassociated = '';
		}
		return $unassociated;
	}

	/**
	 * Method to add flexi extended datas to standard content
	 * 
	 * @params object	the unassociated items rows
	 * @params boolean	add the records from the items_ext table
	 * @return boolean
	 * @since 1.5
	 */
	function addFlexiData($rows)
	{
		if (!$rows) return;
		
		// insert items to category relations
		$catrel = array();
		foreach ($rows as $row) {
			$catrel[] = '('.(int)$row->catid.', '.(int)$row->id.')';
			// append the text property to the object
			if (JString::strlen($row->fulltext) > 1) {
				$row->text = $row->introtext = $row->introtext . '<hr id="system-readmore" />' . $row->fulltext;
			} else {
				$row->text = $row->introtext;
			}			
		}
		$catrel = implode(', ', $catrel);

		$nullDate	= $this->_db->getNullDate();
		
		$query = 'REPLACE INTO #__flexicontent_cats_item_relations (`catid`, `itemid`) VALUES ' . $catrel;
		$this->_db->setQuery($query);
		$this->_db->query();

		// insert items_ext datas
		$itemext = array();
		$typeid = JRequest::getVar('typeid',1);
		$lang = flexicontent_html::getSiteDefaultLang();
		foreach ($rows as $row) {
			if (FLEXI_J16GE) $ilang = $row->language ? $row->language : $lang;
			else $ilang = $lang;  // J1.5 has no language setting
			$itemext = '('.(int)$row->id.', '. $typeid .', '.$this->_db->Quote($ilang).', '.$this->_db->Quote($row->title.' | '.flexicontent_html::striptagsandcut($row->text)).')';
			$query = 'REPLACE INTO #__flexicontent_items_ext (`item_id`, `type_id`, `language`, `search_index`) VALUES ' . $itemext;
			$this->_db->setQuery($query);
			$this->_db->query();
		}

		return;
	}

	/**
	 * Method to get the total nr of the Items
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the Items if it doesn't already exist
		if ( $this->_total === null )
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}
		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the Items
	 *
	 * @access public
	 * @return object
	 */
	function getPagination()
	{
		// Lets load the Items if it doesn't already exist
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Method to build the query for the Items
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildQuery()
	{
		$mainframe = &JFactory::getApplication();
		
		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildContentWhere();
		$orderby	= $this->_buildContentOrderBy();
		$lang  = (FLEXI_FISH || FLEXI_J16GE) ? 'ie.language AS lang, ie.lang_parent_id, ' : '';
		$lang .= (FLEXI_FISH || FLEXI_J16GE) ? 'CASE WHEN ie.lang_parent_id=0 THEN i.id ELSE ie.lang_parent_id END AS lang_parent_id, ' : '';
		$filter_state = $mainframe->getUserStateFromRequest( 'com_flexicontent.items.filter_state', 	'filter_state', '', 'word' );
		
		$nullDate = $this->_db->Quote($this->_db->getNullDate());
		$nowDate = $this->_db->Quote(JFactory::getDate()->toMySQL());
		
		$ver_specific_joins = '';
		if (FLEXI_J16GE) {
			$ver_specific_joins .= ' LEFT JOIN #__viewlevels as level ON level.id=i.access';
			$ver_specific_joins .= ' LEFT JOIN #__usergroups AS g ON g.id = i.access';
			$ver_specific_joins .= ' LEFT JOIN #__categories AS cat ON i.catid=cat.id AND cat.extension='.$this->_db->Quote(FLEXI_CAT_EXTENSION);
		} else {
			$ver_specific_joins .= ' LEFT JOIN #__groups AS g ON g.id = i.access';
		}
		
		$subquery 	= 'SELECT name FROM #__users WHERE id = i.created_by';
		
		$query 		= 'SELECT SQL_CALC_FOUND_ROWS i.*, ie.search_index AS searchindex, ' . $lang . 'i.catid AS catid, u.name AS editor, '
				. (FLEXI_J16GE ? 'level.title as access_level, g.title AS groupname, ' : 'g.name AS groupname, ')
				. 'CASE WHEN i.publish_up = '.$nullDate.' OR i.publish_up <= '.$nowDate.' THEN 0 ELSE 1 END as publication_scheduled,'
				. 'CASE WHEN i.publish_down = '.$nullDate.' OR i.publish_down >= '.$nowDate.' THEN 0 ELSE 1 END as publication_expired,'
				. 't.name AS type_name, rel.ordering as catsordering, (' . $subquery . ') AS author, i.attribs AS config, t.attribs as tconfig'
				. ' FROM #__content AS i'
				. (($filter_state=='RV') ? ' LEFT JOIN #__flexicontent_versions AS fv ON i.id=fv.item_id' : '')
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
				. ' LEFT JOIN #__flexicontent_types AS t ON t.id = ie.type_id'
				. ' LEFT JOIN #__users AS u ON u.id = i.checked_out'
				. $ver_specific_joins
				. $where
				. ' GROUP BY i.id'
				. (($filter_state=='RV') ? ' HAVING i.version<>MAX(fv.version_id)' : '')
				. $orderby
				;
		return $query;
	}

	/**
	 * Method to build the orderby clause of the query for the Items
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentOrderBy()
	{
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$default_order_arr = array(""=>"i.ordering",  "lang"=>"lang", "type_name"=>"type_name",  "access"=>"i.access", "i.title"=>"i.title", "i.ordering"=>"i.ordering", "i.created"=>"i.created", "i.modified"=>"i.modified", "i.hits"=>"i.hits", "i.id"=>"i.id");
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		$default_order = $cparams->get('items_manager_order', 'i.ordering');
		$default_order_dir = $cparams->get('items_manager_order_dir', 'ASC');
		
		$filter_cats 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_cats', 'filter_cats', '', 'int' );
		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.items.filter_order', 'filter_order', $default_order, 'cmd' );
		if ($filter_cats && $filter_order == 'i.ordering') {
			$filter_order	= $mainframe->setUserState( $option.'.items.filter_order', 'catsordering' );
		} else if (!$filter_cats && $filter_order == 'catsordering') {
			$filter_order	= $mainframe->setUserState( $option.'.items.filter_order', $default_order );
		}
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.items.filter_order_Dir',	'filter_order_Dir',	$default_order_dir, 'word' );

		if($filter_order == 'ie.lang_parent_id') {
			$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir .", i.id ASC";
		} else {
			$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir;
		}

		return $orderby;
	}

	/**
	 * Method to build the where clause of the query for the Items
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentWhere()
	{
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		$nullDate = $this->_db->getNullDate();

		$filter_type 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_type', 	'filter_type', '', 'int' );
		$filter_cats 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_cats',	'filter_cats', '', 'int' );
		$filter_subcats 	= $mainframe->getUserStateFromRequest( $option.'.items.filter_subcats',	'filter_subcats', 1, 'int' );
		$filter_state 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_state', 	'filter_state', '', 'word' );
		$filter_stategrp	= $mainframe->getUserStateFromRequest( $option.'.items.filter_stategrp',	'filter_stategrp', '', 'word' );
		$filter_id	 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_id', 		'filter_id', '', 'int' );
		if (FLEXI_FISH || FLEXI_J16GE) {
			$filter_lang 	= $mainframe->getUserStateFromRequest( $option.'.items.filter_lang', 	'filter_lang', '', 'cmd' );
		}
		$filter_authors 	= $mainframe->getUserStateFromRequest( $option.'.items.filter_authors', 'filter_authors', '', 'int' );
		$scope			 	= $mainframe->getUserStateFromRequest( $option.'.items.scope', 			'scope', '', 'int' );
		$search 			= $mainframe->getUserStateFromRequest( $option.'.items.search', 		'search', '', 'string' );
		$search 			= $this->_db->getEscaped( trim(JString::strtolower( $search ) ) );
		$date	 			= $mainframe->getUserStateFromRequest( $option.'.items.date', 			'date', 	 1, 	'int' );
		$startdate	 		= $mainframe->getUserStateFromRequest( $option.'.items.startdate', 	'startdate', '', 	'cmd' );
		if ($startdate == JText::_('FLEXI_FROM')) { $startdate	= $mainframe->setUserState( $option.'.items.startdate', '' ); }
		$startdate 			= $this->_db->getEscaped( trim(JString::strtolower( $startdate ) ) );
		$enddate	 		= $mainframe->getUserStateFromRequest( $option.'.items.enddate', 		'enddate',	 '', 	'cmd' );
		if ($enddate == JText::_('FLEXI_TO')) { $enddate = $mainframe->setUserState( $option.'.items.enddate', '' ); }
		$enddate 			= $this->_db->getEscaped( trim(JString::strtolower( $enddate ) ) );

		$where = array();
		
		if (FLEXI_J16GE) {
			// Limit items to the children of the FLEXI_CATEGORY, currently FLEXI_CATEGORY is root category (id:1) ...
			$where[] = ' (cat.lft > ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND cat.rgt < ' . $this->_db->Quote(FLEXI_RGT_CATEGORY) . ')';
			$where[] = ' cat.extension = ' . $this->_db->Quote(FLEXI_CAT_EXTENSION);
		} else {
			// Limit items to FLEXIcontent Section
			$where[] = ' i.sectionid = ' . $this->_db->Quote(FLEXI_SECTION);
		}

		$user 	=& JFactory::getUser();
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$allitems	= $permission->DisplayAllItems;

			if (!@$allitems) {				
				$canEdit['item'] 	= FlexicontentHelperPerm::checkUserElementsAccess($user->id, 'core.edit', 'item');
				//$canEdit['category'] = FlexicontentHelperPerm::checkUserElementsAccess($user->id, 'core.edit', 'category');  // SHOULD not be used
				$canEditOwn['item']		= FlexicontentHelperPerm::checkUserElementsAccess($user->id, 'core.edit.own', 'item');
				//$canEditOwn['category']	= FlexicontentHelperPerm::checkUserElementsAccess($user->id, 'core.edit.own', 'category');  // SHOULD not be used
			}
		} else if (FLEXI_ACCESS) {
			$allitems	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'displayallitems', 'users', $user->gmid) : 1;
				
			if (!@$allitems) {				
				$canEdit 	= FAccess::checkUserElementsAccess($user->gmid, 'edit');
				$canEditOwn = FAccess::checkUserElementsAccess($user->gmid, 'editown');
			}
		} else {
			$allitems = 1;
		}
		
		if (FLEXI_J16GE) {
			if (!@$allitems) {
				$where_edit = array();
				//print_r($canEditOwn['item']);
				if (count($canEditOwn['item'])) {
					$where_edit[] = ' ( i.created_by = ' . $user->id . ' AND i.id IN (' . implode(',', $canEditOwn['item']) . ') )';
				}
				//print_r($canEdit['item']);
				if (count($canEdit['item']))  {
					$where_edit[] = ' i.id IN (' . implode(',', $canEdit['item']) . ')'; 
				}
				// Add limits to where ...
				if (count($where_edit)) {
					$where[] = ' ('.implode(' OR', $where_edit).')';
				}
			}
		} else if (FLEXI_ACCESS) {
			if (!@$allitems) {				
				if (!@$canEdit['content']) { // first exclude the users allowed to edit all items
					if (@$canEditOwn['content']) { // custom rules for users allowed to edit all their own items
						$allown = array();
						$allown[] = ' i.created_by = ' . $user->id;
						if (isset($canEdit['category'])) {
							if (count($canEdit['category']))		$allown[] = ' i.catid IN (' . implode(',', $canEdit['category']) . ')'; 
						}
						if (isset($canEdit['item'])) {
							if (count($canEdit['item']))				$allown[] = ' i.id IN (' . implode(',', $canEdit['item']) . ')'; 
						}
						if (count($allown) > 0) {
							$where[] = (count($allown) > 1) ? ' ('.implode(' OR', $allown).')' : $allown[0];
						}
					} else if ( ( isset($canEditOwn['category']) && count($canEditOwn['category']) ) || ( isset($canEditOwn['item']) && count($canEditOwn['item']) ) ) { // standard rules for the other users
						$allown = array();
						if (isset($canEditOwn['category'])) {
							if (count($canEditOwn['category']))	$allown[] = ' (i.catid IN (' . implode(',', $canEditOwn['category']) . ') AND i.created_by = ' . $user->id . ')'; 
						}
						
						if (isset($canEdit['category'])) {
							if (count($canEdit['category']))	$allown[] = ' i.catid IN (' . implode(',', $canEdit['category']) . ')'; 
						}
						if (isset($canEdit['item']))  {
							if (count($canEdit['item']))			$allown[] = ' i.id IN (' . implode(',', $canEdit['item']) . ')'; 
						}
						if (count($allown) > 0) {
							$where[] = (count($allown) > 1) ? ' ('.implode(' OR', $allown).')' : $allown[0];
						}
					} else {
						$jAp=& JFactory::getApplication();
						$jAp->enqueueMessage( JText::_('FLEXI_CANNOT_VIEW_EDIT_ANY_ITEMS'), 'notice' );
						$where[] = ' 0 ';
					}
				}
			}
		}

		// get not associated items to remove them from the displayed datas
		$unassociated = $this->getUnassociatedItems();
		if ($unassociated) {
			$notin = array();
			foreach ($unassociated as $ua) {
				$notin[] = $ua->id;
			}
			$where[] = ' i.id NOT IN (' . implode(', ', $notin) . ')';
		}

		if ( $filter_type ) {
			$where[] = 'ie.type_id = ' . $filter_type;
			}

		if ( $filter_cats ) {
			if ( $filter_subcats ) {
				global $globalcats;
				$where[] = 'rel.catid IN (' . $globalcats[$filter_cats]->descendants . ')';
			} else {
				$where[] = 'rel.catid = ' . $filter_cats;
			}
		}

		if ( $filter_authors ) {
			$where[] = 'i.created_by = ' . $filter_authors;
			}

		if ( $filter_id ) {
			$where[] = 'i.id = ' . $filter_id;
			}

		if (FLEXI_FISH || FLEXI_J16GE) {
			if ( $filter_lang ) {
				$where[] = 'ie.language = ' . $this->_db->Quote($filter_lang);
			}
		}
		
		if ( $filter_stategrp=='all' ) {
			// no limitations
		} else if ( $filter_stategrp=='trashed' ) {
			$where[] = 'i.state = -2';
		} else if ( $filter_stategrp=='archived' ) {
			$where[] = 'i.state = '.(FLEXI_J16GE ? 2:-1);
		} else if ( $filter_stategrp=='orphan' ) {
			$where[] = 'i.state NOT IN ('.(FLEXI_J16GE ? 2:-1).',-2,1,0,-3,-4,-5)';
		} else {
			$where[] = 'i.state <> -2';
			$where[] = 'i.state <> '.(FLEXI_J16GE ? 2:-1);
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
				} else if ($filter_state == 'RV' ) {
					$where[] = 'i.state = 1 OR i.state = -5';
				}
			}
		}
		
		if ($search && $scope == 1) {
			$where[] = ' LOWER(i.title) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $search, true ).'%', false );
		}

		if ($search && $scope == 2) {
			$where[] = ' LOWER(i.introtext) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $search, true ).'%', false );
		}

		if ($search && $scope == 4) {
			$where[] = ' MATCH (ie.search_index) AGAINST ('.$this->_db->Quote( $this->_db->getEscaped( $search, true ), false ).' IN BOOLEAN MODE)';
		}
		
		// date filtering
		if ($date == 1) {
			if ($startdate && !$enddate) {  // from only
				$where[] = ' i.created >= ' . $this->_db->Quote($startdate);
			}
			if (!$startdate && $enddate) { // to only
				$where[] = ' i.created <= ' . $this->_db->Quote($enddate);
			}
			if ($startdate && $enddate) { // date range
				$where[] = '( i.created >= ' . $this->_db->Quote($startdate) . ' AND i.created <= ' . $this->_db->Quote($enddate) . ' )';
			}
		}
		
		if ($date == 2) {
			if ($startdate && !$enddate) {  // from only
				$where[] = '( i.modified >= ' . $this->_db->Quote($startdate) . ' OR ( i.modified = ' . $this->_db->Quote($nullDate) . ' AND i.created >= ' . $this->_db->Quote($startdate) . '))';
			}
			if (!$startdate && $enddate) { // to only
				$where[] = '( i.modified <= ' . $this->_db->Quote($enddate) . ' OR ( i.modified = ' . $this->_db->Quote($nullDate) . ' AND i.created <= ' . $this->_db->Quote($enddate) . '))';
			}
			if ($startdate && $enddate) { // date range
				$where[] = '(( i.modified >= ' . $this->_db->Quote($startdate) . ' OR ( i.modified = ' . $this->_db->Quote($nullDate) . ' AND i.created >= ' . $this->_db->Quote($startdate) . ')) AND ( i.modified <= ' . $this->_db->Quote($enddate) . ' OR ( i.modified = ' . $this->_db->Quote($nullDate) . ' AND i.created <= ' . $this->_db->Quote($enddate) . ')))';
			}
		}

		$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );

		return $where;
	}

	/**
	 * Method to copy items
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function copyitems($cid, $keeptags = 1, $prefix, $suffix, $copynr = 1, $lang = null, $state = null, $method = 1, $maincat = null, $seccats = null)
	{
		// Get if translation is to be performed, 1: FLEXI_DUPLICATEORIGINAL,  2: FLEXI_USE_JF_DATA,  3: FLEXI_AUTO_TRANSLATION,  4: FLEXI_FIRST_JF_THEN_AUTO
		if ($method == 99) {   // 
			$translate_method = JRequest::getVar('translate_method',1);
		} else {
			$translate_method = 0;
		}
		// If translation method includes autotranslate ...
		if ($translate_method==3 || $translate_method==4) {
			require_once(JPATH_COMPONENT_SITE.DS.'helpers'.DS.'translator.php');
		}
		// If translation method load description field to allow some parsing according to parameters
		if ($translate_method==3 || $translate_method==4) {
			$this->_db->setQuery('SELECT id FROM #__flexicontent_fields WHERE name = "text" ');
			$desc_field_id = $this->_db->loadResult();
			$desc_field =& JTable::getInstance('flexicontent_fields', '');
			$desc_field->load($desc_field_id);
		}
		
		foreach ($cid as $itemid)
		{
			for( $i=0; $i < $copynr; $i++ )
			{
				$item =& JTable::getInstance('flexicontent_items', '');
				$item->load($itemid);
				
				$sourceid 	= (int)$item->id;
				$curversion = (int)$item->version;
				
				//Save target item
				$row  				=& JTable::getInstance('flexicontent_items', '');
				$row 					= $item;
				$row->id 				= null;
				$row->item_id 	= null;
				$row->title 		= ($prefix ? $prefix . ' ' : '') . $item->title . ($suffix ? ' ' . $suffix : '');
				$row->hits 			= 0;
				$row->version 	= 1;
				$datenow 				=& JFactory::getDate();
				$row->created 		= $datenow->toMySQL();
				$row->publish_up	= $datenow->toMySQL();
				$row->modified 		= $nullDate = $this->_db->getNullDate();
				$row->state			= $state ? $state : $row->state;
				$lang_from			= substr($row->language,0,2);
				$row->language	= $lang ? $lang : $row->language;
				$lang_to				= substr($row->language,0,2);
				
				$doauto['title'] = $doauto['introtext'] = $doauto['fulltext'] = $doauto['metakey'] = $doauto['metadesc'] = true;    // In case JF data is missing
				if ($translate_method == 2 || $translate_method == 4) {
					// a. Try to get joomfish translation from the item
					$query = "SELECT c.* FROM `#__jf_content` AS c "
					." LEFT JOIN #__languages AS lg ON c.language_id=lg.id "
					."WHERE c.reference_table = 'content' AND lg.code='".$row->language."' AND c.reference_id = ". $sourceid;
					$this->_db->setQuery($query);
					$jfitemfields = $this->_db->loadObjectList();
					
					// b. if joomfish translation found set for the new item
					if($jfitemfields) {
						$jfitemdata = new stdClass();
						foreach($jfitemfields as $jfitemfield) {
							$jfitemdata->{$jfitemfield->reference_field} = $jfitemfield->value;
						}
						
						if (isset($jfitemdata->title) && mb_strlen($jfitemdata->title)>2){
							$row->title = $jfitemdata->title;
							$row->title = ($prefix ? $prefix . ' ' : '') . $item->title . ($suffix ? ' ' . $suffix : '');
							$doauto['title'] = false;
						}
						
						if (isset($jfitemdata->alias) && $jfitemdata->alias) {
							$row->alias = $jfitemdata->alias;
						}
						
						if (isset($jfitemdata->introtext) && mb_strlen(strip_tags($jfitemdata->introtext))>2) {
							$row->introtext = $jfitemdata->introtext;
							$doauto['introtext'] = false;
						}
						
						if (isset($jfitemdata->fulltext) && mb_strlen(strip_tags($jfitemdata->fulltext))>2) {
							$row->fulltext = $jfitemdata->fulltext;
							$doauto['fulltext'] = false;
						}
						
						if (isset($jfitemdata->metakey) && mb_strlen($jfitemdata->metakey)>2) {
							$row->metakey = $jfitemdata->metakey;
							$doauto['metakey'] = false;
						}
						
						if (isset($jfitemdata->metadesc) && mb_strlen($jfitemdata->metadesc)>2) {
							$row->metadesc = $jfitemdata->metadesc;
							$doauto['metadesc'] = false;
						}
					}
				}
				

				// Try to do automatic translation from the item, if autotranslate is SET and --NOT found-- or --NOT using-- JoomFish Data
				if ($translate_method == 3 || $translate_method == 4) {
					
					// Translate fulltext item property, using the function for which handles custom fields TYPES: text, textarea, ETC
					if ($doauto['fulltext']) {
						$desc_field->value = $row->fulltext;
						$fields = array( &$desc_field );
						$this->translateFieldValues( $fields, $row, $lang_from, $lang_to);
						$row->fulltext = $desc_field->value;
					}
					
					// TRANSLATE basic item properties (if not already imported via Joomfish)
					$translatables = array('title', 'introtext', 'metakey', 'metadesc');
					
					$fieldnames_arr = array();
					$fieldvalues_arr = array();
					foreach($translatables as $translatable) {
						if ( !$doauto[$translatable] ) continue;
						
						$fieldnames_arr[] = $translatable;
						$translatable_obj = new stdClass(); 
						$translatable_obj->originalValue = $row->{$translatable};
						$translatable_obj->noTranslate = false;
						$fieldvalues_arr[] = $translatable_obj;
					}
					
					if (count($fieldvalues_arr)) {
						$result = autoTranslator::translateItem($fieldnames_arr, $fieldvalues_arr, $lang_from, $lang_to);
						
						if (intval($result)) {
							$i = 0;
							foreach($fieldnames_arr as $fieldname) {
								$row->{$fieldname} = $fieldvalues_arr[$i]->translationValue;
								$i++;
							}
						}
					}
				}
				//print_r($row->fulltext); exit;
				
				$row->store();
				$copyid = (int)$row->id;
				
				// Not doing a translation, we start a new language group for the new item
				if ($translate_method == 0) {
					$row->lang_parent_id = $copyid;
					$row->store();
				}

				// get the item fields
				$doTranslation = $translate_method == 3 || $translate_method == 4;
				$query 	= 'SELECT fir.*'
						. ($doTranslation ? ',  f.name, f.field_type ' : '')
						. ' FROM #__flexicontent_fields_item_relations as fir'
						. ($doTranslation ? ' LEFT JOIN #__flexicontent_fields as f ON f.id=fir.field_id' : '')
						. ' WHERE item_id = '. $sourceid
						;
				$this->_db->setQuery($query);
				$fields = $this->_db->loadObjectList();
				//echo "<pre>"; print_r($fields); exit;
				
				if ($doTranslation) {
					$this->translateFieldValues( $fields, $row, $lang_from, $lang_to);
				}
				foreach ($fields as $field) {
					if ($field->field_type!='text' && $field->field_type!='textarea') continue;
					//print_r($field->value); echo "<br><br>";
				}
				
				foreach($fields as $field)
				{
					if (!empty($field->value)) {
						$query 	= 'INSERT INTO #__flexicontent_fields_item_relations (`field_id`, `item_id`, `valueorder`, `value`)'
								.' VALUES(' . $field->field_id . ', ' . $copyid . ', ' . $field->valueorder . ', ' . $this->_db->Quote($field->value) . ')'
								;
						$this->_db->setQuery($query);
						$this->_db->query();
					}
				}
				
				// fix issue 39 => http://code.google.com/p/flexicontent/issues/detail?id=39
				$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
				$use_versioning = $cparams->get('use_versioning', 1);
				if($use_versioning) {
					$v = new stdClass();
					$v->item_id 		= (int)$item->id;
					$v->version_id		= 1;
					$v->created 	= $item->created;
					$v->created_by 	= $item->created_by;
					//$v->comment		= 'copy version.';
					$this->_db->insertObject('#__flexicontent_versions', $v);
				}

				// get the items versions
				$query 	= 'SELECT *'
						. ' FROM #__flexicontent_items_versions'
						. ' WHERE item_id = '. $sourceid
						. ' AND version = ' . $curversion
						;
				$this->_db->setQuery($query);
				$curversions = $this->_db->loadObjectList();

				foreach ($curversions as $cv) {
					$query 	= 'INSERT INTO #__flexicontent_items_versions (`version`, `field_id`, `item_id`, `valueorder`, `value`)'
							. ' VALUES(1 ,'  . $cv->field_id . ', ' . $copyid . ', ' . $cv->valueorder . ', ' . $this->_db->Quote($cv->value) . ')'
							;
					$this->_db->setQuery($query);
					$this->_db->query();
				}

				// get the item categories
				$query 	= 'SELECT catid'
						. ' FROM #__flexicontent_cats_item_relations'
						. ' WHERE itemid = '. $sourceid
						;
				$this->_db->setQuery($query);
				$cats = $this->_db->loadResultArray();
				
				foreach($cats as $cat)
				{
					$query 	= 'INSERT INTO #__flexicontent_cats_item_relations (`catid`, `itemid`)'
							.' VALUES(' . $cat . ',' . $copyid . ')'
							;
					$this->_db->setQuery($query);
					$this->_db->query();
				}
			
				if ($keeptags)
				{
					// get the item tags
					$query 	= 'SELECT tid'
							. ' FROM #__flexicontent_tags_item_relations'
							. ' WHERE itemid = '. $sourceid
							;
					$this->_db->setQuery($query);
					$tags = $this->_db->loadResultArray();
			
					foreach($tags as $tag)
					{
						$query 	= 'INSERT INTO #__flexicontent_tags_item_relations (`tid`, `itemid`)'
								.' VALUES(' . $tag . ',' . $copyid . ')'
								;
						$this->_db->setQuery($query);
						$this->_db->query();
					}
				}

				if ($method == 3)
				{				
					$this->moveitem($copyid, $maincat, $seccats);
				}
			}
		}
		return true;
	}
	
	
	function translateFieldValues( &$fields, &$row, $lang_from, $lang_to )
	{
		// Translate 'text' TYPE fields
		$fieldnames_arr = array();
		$fieldvalues_arr = array();
		foreach($fields as $field_index => $field)
		{
			if ( $field->field_type!='text' ) continue;
			$fieldnames_arr[] = 'field_value'.$field_index;
			$translatable_obj = new stdClass(); 
			$translatable_obj->originalValue = $field->value;
			$translatable_obj->noTranslate = false;
			$fieldvalues_arr[] = $translatable_obj;
		}
		
		if (count($fieldvalues_arr)) {
			$result = autoTranslator::translateItem($fieldnames_arr, $fieldvalues_arr, $lang_from, $lang_to);
			
			if (intval($result)) {
				foreach($fieldnames_arr as $index => $fieldname) {
					$field_index = str_replace('field_value', '', $fieldname);
					$fields[$field_index]->value = $fieldvalues_arr[$index]->translationValue;
				}
			}
		}
		
		// Translate 'textarea' TYPE fields
		$fieldnames_arr = array();
		$fieldvalues_arr = array();
		foreach($fields as $field_index => $field)
		{
			if ( $field->field_type!='textarea' && $field->field_type!='maintext' ) continue;
			if ( !is_array($field->value) ) $field->value = array($field->value);
			
			// Load field parameters
			FlexicontentFields::loadFieldConfig($field, $row);
			
			// Parse fulltext field into tabs to avoid destroying them during translation
			FLEXIUtilities::call_FC_Field_Func('textarea', 'parseTabs', array(&$field, &$row) );
			$dti = & $field->tab_info;
			
			if ( !$field->tabs_detected ) {
				$fieldnames_arr[] = $field->name;
				$translatable_obj = new stdClass(); 
				$translatable_obj->originalValue = $field->value[0];
				$translatable_obj->noTranslate = false;
				$fieldvalues_arr[] = $translatable_obj;
			} else {
				// BEFORE tabs
				$fieldnames_arr[] = 'beforetabs';
				$translatable_obj = new stdClass(); 
				$translatable_obj->originalValue = $dti->beforetabs;
				$translatable_obj->noTranslate = false;
				$fieldvalues_arr[] = $translatable_obj;
				
				// AFTER tabs
				$fieldnames_arr[] = 'aftertabs';
				$translatable_obj = new stdClass(); 
				$translatable_obj->originalValue = $dti->aftertabs;
				$translatable_obj->noTranslate = false;
				$fieldvalues_arr[] = $translatable_obj;
				
				// TAB titles
				foreach($dti->tab_titles as $i => $tab_title) {
					$fieldnames_arr[] = 'tab_titles_'.$i;
					$translatable_obj = new stdClass(); 
					$translatable_obj->originalValue = $tab_title;
					$translatable_obj->noTranslate = false;
					$fieldvalues_arr[] = $translatable_obj;
				}
				
				// TAB contents
				foreach($dti->tab_contents as $i => $tab_content) {
					$fieldnames_arr[] = 'tab_contents_'.$i;
					$translatable_obj = new stdClass(); 
					$translatable_obj->originalValue = $tab_content;
					$translatable_obj->noTranslate = false;
					$fieldvalues_arr[] = $translatable_obj;
				}
			}
			
			// Do Google Translation
			unset($translated_parts);
			if (count($fieldvalues_arr)) {
				$result = autoTranslator::translateItem($fieldnames_arr, $fieldvalues_arr, $lang_from, $lang_to);
				
				if (intval($result)) {
					$translated_parts = new stdClass();
					foreach($fieldnames_arr as $index => $fieldname) {
						$translated_parts->{$fieldname} = $fieldvalues_arr[$index]->translationValue;
					}
				}
			}
			//echo "<pre>"; print_r($translated_parts);
			
			// Reconstruct field value out of the translated tabs code and assign it back to the field
			if (isset($translated_parts)) {
				if (!$field->tabs_detected ) {
					$fields[$field_index]->value = $translated_parts->{$field->name};
				} else {
					$translated_value  = $translated_parts->beforetabs;
					$translated_value .= $dti->tabs_start;
					foreach ( $dti->tab_titles as $i => $tab_title ) {
						$translated_value .= str_replace( $tab_title, $translated_parts->{'tab_titles_'.$i}, $dti->tab_startings[$i]);
						$translated_value .= $translated_parts->{'tab_contents_'.$i};
						$translated_value .= $dti->tab_endings[$i];
					}
					$translated_value .= $dti->tabs_end;
					$translated_value .= $translated_parts->aftertabs;
					
					// Assign translated value back to the field
					$fields[$field_index]->value = $translated_value;
				}
			} else {
				// no translation performed, or translation unsuccessful
				$field->value = $field->value[0];
			}
		}
		
	}
				
	

	/**
	 * Method to copy items
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function moveitem($itemid, $maincat, $seccats = null)
	{
		if (!$maincat) return true;
		
		$item =& JTable::getInstance('flexicontent_items', '');
		$item->load($itemid);
		$item->catid = $maincat;
		$item->store();
		
		if ($seccats === null)
		{
			// draw an array of the item categories
			$query 	= 'SELECT catid'
					. ' FROM #__flexicontent_cats_item_relations'
					. ' WHERE itemid = '.$itemid
					;
			$this->_db->setQuery($query);
			$seccats = $this->_db->loadResultArray();
		}

		// Add the primary cat to the array if it's not already in
		if (!in_array($maincat, $seccats)) {
			$seccats[] = $maincat;
		}

		//At least one category needs to be assigned
		if (!is_array( $seccats ) || count( $seccats ) < 1) {
			$this->setError(JText::_('FLEXI_SELECT_CATEGORY'));
			return false;
		}
		
		// delete old relations
		$query 	= 'DELETE FROM #__flexicontent_cats_item_relations'
				. ' WHERE itemid = '.$itemid
				;
		$this->_db->setQuery($query);
		$this->_db->query();
		
		foreach($seccats as $cat)
		{
			$query 	= 'INSERT INTO #__flexicontent_cats_item_relations (`catid`, `itemid`)'
					.' VALUES(' . $cat . ',' . $itemid . ')'
					;
			$this->_db->setQuery($query);
			$this->_db->query();
		}
		
		// update version table
		if ($seccats) {
			$query 	= 'UPDATE #__flexicontent_items_versions SET value = ' . $this->_db->Quote(serialize($seccats))
					. ' WHERE version = 1'
					. ' AND item_id = ' . (int)$itemid
					. ' AND field_id = 13'
					. ' AND valueorder = 1'
					;
			$this->_db->setQuery($query);
			$this->_db->query();
		}
		
		return true;
	}
	
	
	/**
	 * Method to build an object with the items submitted to approval
	 * it also verifies if the item state are correct (draft state is -4) 
	 * and if it belongs to the user
	 *
	 * @access	public
	 * @params	array
	 * @return	object
	 * @since	1.5
	 */
	function isUserDraft($cid)
	{
		$user 	=& JFactory::getUser();

		if ($cid)
		{
			$query 	= 'SELECT c.id, c.catid, c.created_by, c.title, cat.title AS cattitle from #__content AS c'
					. ' LEFT JOIN #__categories AS cat on cat.id = c.catid'
					. ' WHERE c.state = -4'
					. ' AND c.created_by = ' . (int) $user->get('id')
					. (FLEXI_J16GE ? ' AND cat.extension="'.FLEXI_CAT_EXTENSION.'"' : '')
					. ' AND c.id IN ( '. implode(',', $cid).' )'
					. ' AND ( c.checked_out = 0 OR ( c.checked_out = ' . (int) $user->get('id'). ' ) )'
					;
			$this->_db->setQuery( $query );
			$cids = $this->_db->loadObjectList();
			
			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}

		return $cids;
	}

	/**
	 * Method to build an object with the items submitted to approval
	 * it also verifies if the item state are correct (draft state is -4) 
	 *
	 * NOT IN USE !!! note: at the moment all users can only submit their own contents to approval
	 *
	 * @access	public
	 * @params	array
	 * @return	object
	 * @since	1.5
	 */
	/*function isAllowedToSubmit($items)
	{ // INCOMPLETE MUST also check user->gid > 24 (J1.5),   also missing variables  ....
		if ($items)
		{
			$CanEdit	= FAccess::checkAllContentAccess('com_content','edit','users',$user->gmid,'content','all');
			$CanEditOwn	= FAccess::checkAllContentAccess('com_content','editown','users',$user->gmid,'content','all');
			
			foreach ($items as $item) {
				$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			}
		}
		return $cids;
	}*/

	/**
	 * Method to find validators for an item
	 *
	 * @access	public
	 * @params	int			the id of the item
	 * @params	int			the catid of the item
	 * @return	object		the validators object
	 * @since	1.5
	 */
	function getValidators($id, $catid)
	{
		global $globalcats;
		
		$query	= 'SELECT DISTINCT aro from #__flexiaccess_acl'
				. ' WHERE acosection = ' . $this->_db->Quote('com_content')
				. ' AND aco = ' . $this->_db->Quote('publish')
				// first step : get all groups that can publish everything
				. ' AND ( ( axosection = ' . $this->_db->Quote('content') . ' AND axo = ' . $this->_db->Quote('all') . ' )'
				// second step : get all groups that can publish in the item's cats (main cat and ancestors)
				. ' OR 	( axosection = ' . $this->_db->Quote('category') . ' AND axo IN ( ' . $globalcats[$catid]->ancestors . ') )'
				// third step : get all groups that can publish this specific item
				. ' OR 	( axosection = ' . $this->_db->Quote('item') . ' AND axo = ' . $id . ' ) )'
				;
		$this->_db->setQuery($query);
		$publishers = $this->_db->loadResultArray();
		
		// find all nested groups
		if ($publishers) {
			$users = $publishers;
			foreach ($publishers as $publisher) {
				$validators = FAccess::mgenfant($publisher);
				$users = array_merge($users, $validators);
			}
		}
		
		// get all users from these groups that wants to receive system emails
		$query	= 'SELECT DISTINCT m.member_id, u.name, u.username, u.email from #__flexiaccess_members AS m'
				. ' LEFT JOIN #__users AS u ON u.id = m.member_id'
				. ' WHERE m.group_id IN ( ' . implode(',', $users) . ' )'
				. ' AND u.sendEmail = 1'
				;		
		$this->_db->setQuery($query);
		$validators = $this->_db->loadObjectList();
		
		return $validators;
	}
	
	/**
	 * Method to notification to the validators for an item
	 *
	 * @access	public
	 * @params	object		the user object
	 * @params	object		the item object
	 * @return	boolean		true on success
	 * @since	1.5
	 */
	function sendNotification($users, $item)
	{
		$sender 	=& JFactory::getUser();

		// messaging for new items
		require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_messages'.DS.'tables'.DS.'message.php');

		// load language for messaging
		$lang =& JFactory::getLanguage();
		$lang->load('com_messages');
		
		$ctrl_task = FLEXI_J16GE ? '&task=items.edit' : '&controller=items&task=edit';
		$item->url = JURI::base() . 'index.php?option=com_flexicontent'.$ctrl_task .'&cid[]=' . $item->id;

		foreach ($users as $user)
		{
			$msg = new TableMessage($this->_db);
			$msg->send($sender->get('id'), $user->member_id, JText::_('FLEXI_APPROVAL_REQUEST'), JText::sprintf('FLEXI_APPROVAL_MESSAGE', $user->name, $sender->get('name'), $sender->get('username'), $item->id, $item->title, $item->cattitle, $item->url));
		}
		return true;
	}

	/**
	 * Method to move an item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function move($direction)
	{
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$filter_cats = $mainframe->getUserStateFromRequest( $option.'.items.filter_cats', 'filter_cats', '', 'int' );

		if ($filter_cats == '' || $filter_cats == 0)
		{
			$row =& JTable::getInstance('flexicontent_items', '');

			if (!$row->load( $this->_id ) ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			if (!$row->move( $direction )) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			return true;
		}
		else
		{
			$query = 'SELECT itemid, ordering'
					.' FROM #__flexicontent_cats_item_relations'
					.' WHERE catid = ' . $filter_cats
					.' AND itemid = ' . $this->_id
					;
			$this->_db->setQuery( $query, 0, 1 );
			$origin = $this->_db->loadObject();

			$sql = 'SELECT itemid, ordering FROM #__flexicontent_cats_item_relations';

			if ($direction < 0)
			{
				$sql .= ' WHERE ordering < '.(int) $origin->ordering;
				$sql .= ' AND catid = ' . $filter_cats;
				$sql .= ' ORDER BY ordering DESC';
			}
			else if ($direction > 0)
			{
				$sql .= ' WHERE ordering > '.(int) $origin->ordering;
				$sql .= ' AND catid = ' . $filter_cats;
				$sql .= ' ORDER BY ordering';
			}
			else
			{
				$sql .= ' WHERE ordering = '.(int) $origin->ordering;
				$sql .= ' AND catid = ' . $filter_cats;
				$sql .= ' ORDER BY ordering';
			}
			$this->_db->setQuery( $sql, 0, 1 );

			$row = null;
			$row = $this->_db->loadObject();
			
			if (isset($row))
			{
				$query = 'UPDATE #__flexicontent_cats_item_relations'
				. ' SET ordering = '. (int) $row->ordering
				. ' WHERE itemid = '. (int) $origin->itemid
				. ' AND catid = ' . $filter_cats
				;
				$this->_db->setQuery( $query );

				if (!$this->_db->query())
				{
					$err = $this->_db->getErrorMsg();
					JError::raiseError( 500, $err );
				}

				$query = 'UPDATE #__flexicontent_cats_item_relations'
				. ' SET ordering = '.(int) $origin->ordering
				. ' WHERE itemid = '. (int) $row->itemid
				. ' AND catid = ' . $filter_cats
				;
				$this->_db->setQuery( $query );
	
				if (!$this->_db->query())
				{
					$err = $this->_db->getErrorMsg();
					JError::raiseError( 500, $err );
				}

				$origin->ordering = $row->ordering;
			}
			else
			{
				$query = 'UPDATE #__flexicontent_cats_item_relations'
				. ' SET ordering = '.(int) $origin->ordering
				. ' WHERE itemid = '. (int) $origin->itemid
				. ' AND catid = ' . $filter_cats
				;
				$this->_db->setQuery( $query );
	
				if (!$this->_db->query())
				{
					$err = $this->_db->getErrorMsg();
					JError::raiseError( 500, $err );
				}
			}

		return true;
		}
	}

	/**
	 * Method to order items
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function saveorder($cid = array(), $order)
	{
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$filter_cats = $mainframe->getUserStateFromRequest( $option.'.items.filter_cats', 'filter_cats', '', 'int' );
		$filter_subcats 	= $mainframe->getUserStateFromRequest( $option.'.items.filter_subcats',	'filter_subcats', 1, 'int' );
		if ($filter_subcats) {
			$this->setError( JText::_( 'FLEXI_CANNOT_SAVEORDER_SUBCAT_ITEMS' ) );
			return false;
		}

		if ($filter_cats == '' || $filter_cats == 0)
		{

			$row =& JTable::getInstance('flexicontent_items', '');
		
			// update ordering values
			for( $i=0; $i < count($cid); $i++ )
			{
				$row->load( (int) $cid[$i] );
	
				if ($row->ordering != $order[$i])
				{
					$row->ordering = $order[$i];
					if (!$row->store()) {
						$this->setError($this->_db->getErrorMsg());
						return false;
					}
				}
			}
			
			$where = ''; 
			if (!FLEXI_J16GE) {
				$where = $this->_db->nameQuote('sectionid').' = '.$this->_db->Quote(FLEXI_SECTION);
			}
			$row->reorder($where);
			return true;

		}
		else
		{
			// Here goes the second method for saving order.
			// As there is a composite primary key in the relations table we aren't able to use the standard methods from JTable
		
			$query = 'SELECT itemid, ordering'
					.' FROM #__flexicontent_cats_item_relations'
					.' WHERE catid = ' . $filter_cats
					.' ORDER BY ordering'
					;
			// on utilise la methode _getList pour s'assurer de ne charger que les résultats compris entre les limites
			$rows = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));

			for( $i=0; $i < count($cid); $i++ )
			{
				if ($rows[$i]->ordering != $order[$i])
				{
					$rows[$i]->ordering = $order[$i];
					
					$query = 'UPDATE #__flexicontent_cats_item_relations'
							.' SET ordering=' . $order[$i]
							.' WHERE catid = ' . $filter_cats
							.' AND itemid = ' . $cid[$i]
							;

					$this->_db->setQuery($query);

					if (!$this->_db->query()) {
						$this->setError($this->_db->getErrorMsg());
						return false;
					}
				}
			}

			// Specific reorder procedure because the relations table has a composite primary key 
			$query 	= 'SELECT itemid, ordering'
					. ' FROM #__flexicontent_cats_item_relations'
					. ' WHERE ordering >= 0'
					. ' AND catid = '.(int) $filter_cats
					. ' ORDER BY ordering'
					;
			$this->_db->setQuery( $query );
			if (!($orders = $this->_db->loadObjectList()))
			{
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			// compact the ordering numbers
			for ($i=0, $n=count( $orders ); $i < $n; $i++)
			{
				if ($orders[$i]->ordering >= 0)
				{
					if ($orders[$i]->ordering != $i+1)
					{
						$orders[$i]->ordering = $i+1;
						$query 	= 'UPDATE #__flexicontent_cats_item_relations'
								. ' SET ordering = '. (int) $orders[$i]->ordering
								. ' WHERE itemid = '. (int) $orders[$i]->itemid
								. ' AND catid = '.(int) $filter_cats
								;
						$this->_db->setQuery( $query);
						$this->_db->query();
					}
				}
			}

			return true;
		}

	}

	/**
	 * Method to check if we can remove an item
	 * return false if the user doesn't have rights to do it
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function candelete($cid = array())
	{
		$user = JFactory::getUser();
		
		if (FLEXI_J16GE) {
			// Not needed we will check individual item's permissions
			//$permission = FlexicontentHelperPerm::getPerm();
		} else if ($user->gid > 24) {
			// Return true for super administrators
			return true;
		} else if (!FLEXI_ACCESS) {
			// Return true if flexi_access component is not used,
			// since all backend user groups can delete content (manager, administrator, super administrator)
			return true;
		}


		$n		= count( $cid );
		if ($n)
		{
			$query = 'SELECT id, catid, created_by FROM #__content'
			. ' WHERE id IN ( '. implode(',', $cid) . ' )'
			;
			$this->_db->setQuery( $query );
			$items = $this->_db->loadObjectList();
			
			// This is not needed since functionality is already included in checkAllItemAccess() ???
			//if (FLEXI_ACCESS) {
				//$canDeleteAll			= FAccess::checkAllContentAccess('com_content','delete','users',$user->gmid,'content','all');
				//$canDeleteOwnAll	= FAccess::checkAllContentAccess('com_content','deleteown','users',$user->gmid,'content','all');
			//}
			foreach ($items as $item)
			{
				if (FLEXI_J16GE) {
					$rights 		= FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $item->id);
					$canDelete 		= in_array('delete', $rights);
					$canDeleteOwn = in_array('delete.own', $rights) && $item->created_by == $user->id;
				} else if (FLEXI_ACCESS) {
					$rights 		= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
					$canDelete 		= in_array('delete', $rights) /*|| $canDeleteAll	*/;
					$canDeleteOwn	= (in_array('deleteown', $rights) /*|| $canDeleteOwnAll*/) && $item->created_by == $user->id;
				} else {
					// This should be unreachable
					return true;
				}
				if (!$canDelete && !$canDeleteOwn) return false;
			}
			return true;
		}
	}

	/**
	 * Method to remove an item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function delete($cid, &$itemmodel=null)
	{
		if (count( $cid ))
		{
			$cids = implode( ',', $cid );
			
			// **********************************************************************************
			// Trigger onBeforeDeleteField field event to allow fields to cleanup any custom data
			// **********************************************************************************
			if ($itemmodel)
			{
				foreach ($cid as $item_id)
				{
					$item = $itemmodel->getItem($item_id);
					$fields = $itemmodel->getExtrafields($force=true);
					foreach ($fields as $field) {
						$field_type = $field->iscore ? 'core' : $field->field_type;
						FLEXIUtilities::call_FC_Field_Func($field_type, 'onBeforeDeleteField', array( &$field, &$item ));
					}
				}
			}
			
			
			// *********************************************
			// Retrieve J2.5 asset before deleting the items
			// *********************************************
			if (FLEXI_J16GE) {
				$query = 'SELECT asset_id FROM #__content'
						. ' WHERE id IN ('. $cids .')'
						;
				$this->_db->setQuery( $query );
				
				if(!$this->_db->query()) {
					$this->setError($this->_db->getErrorMsg());
					return false;
				}
				$assetids = $this->_db->loadResultArray();
				$assetidslist = implode(',', $assetids );
			}
			
			
			// **********************
			// Remove basic item data
			// **********************
			$query = 'DELETE FROM #__content'
					. ' WHERE id IN ('. $cids .')'
					;
			$this->_db->setQuery( $query );
			
			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			
			// *************************
			// Remove extended item data
			// *************************
			$query = 'DELETE FROM #__flexicontent_items_ext'
					. ' WHERE item_id IN ('. $cids .')'
					;
			$this->_db->setQuery( $query );
			
			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			
			// ******************************
			// Remove assigned tag references
			// ******************************
			$query = 'DELETE FROM #__flexicontent_tags_item_relations'
					.' WHERE itemid IN ('. $cids .')'
					;
			$this->_db->setQuery($query);

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			
			// ***********************************
			// Remove assigned category references
			// ***********************************
			$query = 'DELETE FROM #__flexicontent_cats_item_relations'
					.' WHERE itemid IN ('. $cids .')'
					;
			$this->_db->setQuery($query);

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			
			// ****************************************************************
			// Delete field data in flexicontent_fields_item_relations DB Table
			// ****************************************************************
			$query = 'DELETE FROM #__flexicontent_fields_item_relations'
					. ' WHERE item_id IN ('. $cids .')'
					;
			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			
			// **************************************************************************
			// Delete VERSIONED field data in flexicontent_fields_item_relations DB Table
			// **************************************************************************
			$query = 'DELETE FROM #__flexicontent_items_versions'
					. ' WHERE item_id IN ('. $cids .')'
					;
			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			
			// ****************************
			// Delete item version METADATA
			// ****************************
			$query = 'DELETE FROM #__flexicontent_versions'
					. ' WHERE item_id IN ('. $cids .')'
					;
			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			
			// *****************************
			// Delete item asset/ACL records
			// *****************************
			if (FLEXI_J16GE) {
				$query 	= 'DELETE FROM #__assets'
						. ' WHERE id in ('.$assetidslist.')'
						;
			} else if (FLEXI_ACCESS) {
				$query 	= 'DELETE FROM #__flexiaccess_acl'
						. ' WHERE acosection = ' . $this->_db->Quote('com_content')
						. ' AND axosection = ' . $this->_db->Quote('item')
						. ' AND axo IN ('. $cids .')'
						;
			}
			$this->_db->setQuery( $query );
			
			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			return true;
		}

		return false;
	}
	
	/**
	 * Method to save the access level of the items
	 *
	 * @access	public
	 * @param 	integer id of the category
	 * @param 	integer access level
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function saveaccess($id, $access)
	{
		$mainframe = &JFactory::getApplication();
		$row =& JTable::getInstance('flexicontent_items', '');

		$row->load( $id );
		$row->id = $id;
		$row->access = $access;

		if ( !$row->check() ) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		if ( !$row->store() ) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		
		$mainframe->redirect( 'index.php?option=com_flexicontent&view=items' );
	}

	/**
	 * Method to fetch the assigned categories
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	function getCategories($id)
	{
		$query = 'SELECT DISTINCT c.id, c.title'
				. ' FROM #__categories AS c'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
					. (FLEXI_J16GE ? ' AND c.extension="'.FLEXI_CAT_EXTENSION.'"' : '')
				;
	
		$this->_db->setQuery( $query );

		$this->_cats = $this->_db->loadObjectList();

		return $this->_cats;
	}

	/**
	 * Method to get the name of the author of an item
	 *
	 * @access	public
	 * @return	object
	 * @since	1.5
	 */
	function getAuthor($createdby)
	{
		$query = 'SELECT u.name'
				. ' FROM #__users AS u'
				. ' WHERE u.id = '.(int)$createdby
				;
	
		$this->_db->setQuery( $query );

		$this->_author = $this->_db->loadResult();

		return $this->_author;
	}

	/**
	 * Method to get types list for filtering
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

	/**
	 * Method to get author list for filtering
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getAuthorslist ()
	{
		$query = 'SELECT i.created_by AS id, u.name AS name'
				. ' FROM #__content AS i'
				. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
				. ' GROUP BY i.created_by'
				. ' ORDER BY u.name'
				;
		$this->_db->setQuery($query);

		$authors = $this->_db->loadObjectList();

		return $authors;	
	}
	
	
	/**
	 * Method to import Joomla! com_content datas and structure
	 * 
	 * @return boolean true on success
	 * @since 1.5
	 */
	 
	function import()
	{
		// Get the site default language
		$lang = flexicontent_html::getSiteDefaultLang();
		
		// Get all Joomla sections
		$query = 'SELECT * FROM #__sections';
		$this->_db->setQuery($query);
		$sections = $this->_db->loadObjectList();
	
		$logs = new stdClass();
		$logs->sec = 0;
		$logs->cat = 0;
		$logs->art = 0;
		//$logs->err = new stdClass();
		
		// Create the new section for flexicontent items
		$flexisection =& JTable::getInstance('section');
		$flexisection->title		= 'FLEXIcontent';
		$flexisection->alias		= 'flexicontent';
		$flexisection->published	= 1;
		$flexisection->ordering		= $flexisection->getNextOrder();
		$flexisection->access		= 0;
		$flexisection->scope		= 'content';
		$flexisection->check();
		$flexisection->store();
		
		// Get the category default parameters in a string
		$xml = new JSimpleXML;
		$xml->loadFile(JPATH_COMPONENT.DS.'models'.DS.'category.xml');
		$catparams = new JParameter('');
		
		foreach ($xml->document->params as $paramGroup) {
			foreach ($paramGroup->param as $param) {
				if (!$param->attributes('name')) continue;  // FIX for empty name e.g. seperator fields
				$catparams->set($param->attributes('name'), $param->attributes('default'));
			}
		}
		$catparams_str = $catparams->toString();
		
		// Loop through the section object and create cat -> subcat -> items -> fields
		$k = 0;
		foreach ($sections as $section)
		{
			// Create a category for every imported section, to contain all categories of the section
			$cat = &JTable::getInstance('flexicontent_categories','');
			$cat->parent_id			= 0;
			$cat->title				= $section->title;
			$cat->name				= $section->name;
			$cat->alias 			= $section->alias;
			$cat->image 			= $section->image;
			$cat->section 			= $flexisection->id;
			$cat->image_position	= $section->image_position;
			$cat->description		= $section->description;
			$cat->published			= $section->published;
			$cat->ordering			= $section->ordering;
			$cat->access			= $section->access;
			$cat->params			= $catparams_str;
			$k++;
			$cat->check();
			if ($cat->store()) {
				$logs->sec++;
			} else {
				$logs->err->$k->type 	= JText::_( 'FLEXI_IMPORT_SECTION' ) . ' id';
				$logs->err->$k->id 		= $section->id;
				$logs->err->$k->title 	= $section->title;
			}
			
			// Get the categories of each imported section
			$query = 'SELECT * FROM #__categories WHERE section = ' . $section->id;
			$this->_db->setQuery($query);
			$categories = $this->_db->loadObjectList();
	
			// Loop throught the categories of the created section
			foreach ($categories as $category)
			{
				$subcat = &JTable::getInstance('flexicontent_categories','');
				$subcat->load($category->id);
				$subcat->id			= 0;
				$subcat->parent_id	= $cat->id;
				$subcat->section 	= $flexisection->id;
				$subcat->params		= $catparams_str;
				$k++;
				$subcat->check();
				if ($subcat->store()) {
					$logs->cat++;
				} else {
					$logs->err->$k->type 	= JText::_( 'FLEXI_IMPORT_CATEGORY' ) . ' id';
					$logs->err->$k->id 		= $category->id;
					$logs->err->$k->title 	= $category->title;
				}
				
				// Get the articles of the created category
				$query = 'SELECT * FROM #__content WHERE catid = ' . $category->id;
				$this->_db->setQuery($query);
				$articles = $this->_db->loadObjectList();
				
				// Loop throught the articles of the created category
				foreach ($articles as $article)
				{
					$item = &JTable::getInstance('content');
					$item->load($article->id);
					$item->id					= 0;
					$item->sectionid	= $flexisection->id;
					$item->catid			= $subcat->id;
					$k++;
					$item->check();
					if ($item->store()) {
						$logs->art++;
					} else {
						$logs->err->$k->type 	= JText::_( 'FLEXI_IMPORT_ARTICLE' ) . ' id';
						$logs->err->$k->id 		= $article->id;
						$logs->err->$k->title 	= $article->title;
					}
				} // end articles loop
			} // end categories loop
		} // end sections loop
		
		// Save the created section as flexi_section for the component
		$fparams =& JComponentHelper::getParams('com_flexicontent');
		$fparams->set('flexi_section', $flexisection->id);
		$fparams = $fparams->toString();
		
		$flexi =& JComponentHelper::getComponent('com_flexicontent');
		$query 	= 'UPDATE #__components'
			. ' SET params = ' . $this->_db->Quote($fparams)
			. ' WHERE id = ' . $flexi->id;
			;
		$this->_db->setQuery($query);
		$this->_db->query();
		return $logs;
	}
	
	/**
	 * Method to get advanced search fields which belongs to the item type
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getAdvSearchFields($key='name') {
		$typeid = intval(@$typeid);
		//$where = " WHERE ftrel.type_id='".(int)$typeid."' AND fi.isadvsearch='1'";
		$query = 'SELECT fi.*'
			.' FROM #__flexicontent_fields AS fi'
			.' LEFT JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id'
			//.' LEFT JOIN #__flexicontent_items_ext AS ie ON ftrel.type_id = ie.type_id'
			.' WHERE fi.isadvsearch= 1 AND fi.published = 1'
			.' GROUP BY fi.id'
			.' ORDER BY ftrel.ordering, fi.ordering, fi.name'
		;
		$this->_db->setQuery($query);
		$fields = $this->_db->loadObjectList($key);
		foreach ($fields as $field) {
			$field->item_id		= 0;
			//$field->value 		= $this->getExtrafieldvalue($field->id, 0);
			$field->parameters 	= new JParameter($field->attribs);
		}
		return $fields;
	}
	
	
	/**
	 * Method to get a list of items (ids) that have value for the given fields
	 * 
	 * @since 1.5
	 */
	function getFieldsItems($fields) {
		// Get field data, so that we can identify the fields and take special action for each of them
		$field_list = "'".implode("','", $fields)."'";
		$query = "SELECT * FROM #__flexicontent_fields WHERE id IN ({$field_list})";
		$field_data = $this->_db->loadObjectList();
		
		// Check the type of fields
		$check_items_for_tags = false;
		$use_all_items = false;
		$non_core_fields = array();
		foreach ($field_data as $field) {
			// tags
			if ($field->field_type == 'tags') {
				$get_items_with_tags = true;
				continue;
			}
			// other core fields
			if ($field->iscore) {
				$use_all_items = true;
				break;
			}
			// non core fields
			$non_core_fields[] = $field->id;
		}
		
		// Return all items, since we included a core field other than tag
		if ($use_all_items == true) {
			$query = "SELECT id FROM #__content";
			$this->_db->setQuery($query);
			return $this->_db->loadResultArray();
		}
		
		// Find item having tags
		$items_with_tags = array();
		if ($get_items_with_tags == true) {
			$query  = 'SELECT DISTINCT itemid FROM #__flexicontent_tags_item_relations';
			$this->_db->setQuery($query);
			$items_with_tags = $this->_db->loadResultArray();
		}
		
		// Find items having values for non core fields
		$items_with_noncore = array();
		if (count($non_core_fields)) {
			$non_core_fields_list = "'".implode("','", $non_core_fields)."'";
			$query = "SELECT DISTINCT firel.item_id FROM #__flexicontent_fields_item_relations as firel"
				." JOIN #__content as a ON firel.item_id=a.id "
				." WHERE firel.field_id IN ({$non_core_fields_list}) AND a.state IN (1, -5)" // ." AND ie.type_id='{$typeid}' "
				." AND firel.value<>'' "
			;
			//echo $query;
			$this->_db->setQuery($query);
			$items_with_noncore = $this->_db->loadResultArray();
		}
		
		$item_list = array_merge($items_with_tags,$items_with_noncore);
		//echo count($item_list);
		
		// array_unique() creates gaps in the index of the array, and if passed to json_encode it will output object !!! so we use array_values()
		return array_values(array_unique($item_list));
	}	
	
}
?>
