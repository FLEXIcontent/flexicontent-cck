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

		global $mainframe, $option;

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
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			$db =& JFactory::getDBO();
			$db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $db->loadResult();
			
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
	 * Method to set the default site language to an item with no language
	 * 
	 * @return boolean
	 * @since 1.5
	 */
	function setSiteDefaultLang($id)
	{
		$languages =& JComponentHelper::getParams('com_languages');
		$lang 		= $languages->get('site', 'en-GB');

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
		
		$query 	= 'SELECT id FROM #__content'
				. ' WHERE sectionid = ' . $this->_db->Quote(FLEXI_SECTION)
				;
		$this->_db->setQuery($query);
		$allids = $this->_db->loadResultArray();

		$query 	= 'SELECT item_id FROM #__flexicontent_items_ext';
		$this->_db->setQuery($query);
		$allext = $this->_db->loadResultArray();

		$query 	= 'SELECT DISTINCT itemid FROM #__flexicontent_cats_item_relations';
		$this->_db->setQuery($query);
		$allcat = $this->_db->loadResultArray();

		$query 	= 'SELECT item_id FROM #__flexicontent_fields_item_relations'
				. ' GROUP BY item_id'
				. ' HAVING COUNT(field_id) >= 5'  // we set 5 instead of 7 for the new created items that doesn't have any created date
				;
		$this->_db->setQuery($query);
		$allfi = $this->_db->loadResultArray();
		
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
			$and = ' AND id IN ( ' . implode(',', $status['no']) . ' )';
			$query 	= 'SELECT id, title, introtext, `fulltext`, catid, created, created_by, modified, modified_by, version, state FROM #__content'
					. ' WHERE sectionid = ' . $this->_db->Quote(FLEXI_SECTION)
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

		$languages =& JComponentHelper::getParams('com_languages');
		$lang = $languages->get('site', 'en-GB');

		// insert items_ext datas
		$itemext = array();
		$typeid = JRequest::getVar('typeid',1);
		foreach ($rows as $row) {
		    $itemext = '('.(int)$row->id.', '. $typeid .', '.$this->_db->Quote($lang).', '.$this->_db->Quote($row->title.' | '.flexicontent_html::striptagsandcut($row->text)).')';
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
		if (empty($this->_total))
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
		global $mainframe;
		
		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildContentWhere();
		$orderby	= $this->_buildContentOrderBy();
		$lang		= FLEXI_FISH ? 'ie.language AS lang, ' : '';
		$filter_state = $mainframe->getUserStateFromRequest( 'com_flexicontent.items.filter_state', 	'filter_state', '', 'word' );
		
		$subquery 	= 'SELECT name FROM #__users WHERE id = i.created_by';
		
		$query 		= 'SELECT SQL_CALC_FOUND_ROWS i.*, ie.search_index AS searchindex, ' . $lang . 'i.catid AS maincat, rel.catid AS catid, u.name AS editor, '
					. 't.name AS type_name, g.name AS groupname, rel.ordering as catsordering, (' . $subquery . ') AS author, i.attribs AS config, t.attribs as tconfig'
					. ' FROM #__content AS i'
					. (($filter_state=='RV') ? ' LEFT JOIN #__flexicontent_versions AS fv ON i.id=fv.item_id' : '')
					. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
					. ' LEFT JOIN #__flexicontent_types AS t ON t.id = ie.type_id'
					. ' LEFT JOIN #__groups AS g ON g.id = i.access'
					. ' LEFT JOIN #__users AS u ON u.id = i.checked_out'
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
		global $mainframe, $option;
		
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

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir;

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
		global $mainframe, $option;
		$nullDate = $this->_db->getNullDate();

		$filter_type 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_type', 	'filter_type', '', 'int' );
		$filter_cats 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_cats',	'filter_cats', '', 'int' );
		$filter_subcats 	= $mainframe->getUserStateFromRequest( $option.'.items.filter_subcats',	'filter_subcats', 1, 'int' );
		$filter_state 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_state', 	'filter_state', '', 'word' );
		$filter_id	 		= $mainframe->getUserStateFromRequest( $option.'.items.filter_id', 		'filter_id', '', 'int' );
		if (FLEXI_FISH) {
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
		
		$where[] = ' i.state != -1';
		$where[] = ' i.state != -2';
		$where[] = ' i.sectionid = ' . $this->_db->Quote(FLEXI_SECTION);

		// if FLEXIaccess only authorize users to see their own items
		if (FLEXI_ACCESS) {
			$user 	=& JFactory::getUser();
			$allitems	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'displayallitems', 'users', $user->gmid) : 1;
				
			if (!@$allitems) {				

				$canEdit 	= FAccess::checkUserElementsAccess($user->gmid, 'edit');
				$canEditOwn = FAccess::checkUserElementsAccess($user->gmid, 'editown');

				if (!@$canEdit['content']) { // first exclude the users allowed to edit all items
					if (@$canEditOwn['content']) { // custom rules for users allowed to edit all their own items
						$allown = array();
						$allown[] = ' i.created_by = ' . $user->id;
						if (isset($canEdit['category'])) {
							if (count($canEdit['category']) == 1) {
								$allown[] = ' i.catid = ' . $canEdit['category'][0]; 
							} else if (count($canEdit['category']) > 1) {
								$allown[] = ' i.catid IN (' . implode(',', $canEdit['category']) . ')'; 
							}
						}
						if (isset($canEdit['item'])) {
							if (count($canEdit['item']) == 1) {
								$allown[] = ' i.id = ' . $canEdit['item'][0]; 
							} else if (count($canEdit['item']) > 1) {
								$allown[] = ' i.id IN (' . implode(',', $canEdit['item']) . ')'; 
							}
						}
						$where[] = (count($allown) > 1) ? ' ('.implode(' OR', $allown).')' : $allown[0];
					} else { // standard rules for the other users
						$allown = array();
						if (isset($canEditOwn['category'])) {
							if (count($canEditOwn['category']) == 1) {
								$allown[] = ' (i.catid = ' . $canEditOwn['category'][0]. ' AND i.created_by = ' . $user->id . ')'; 
							} else if (count($canEditOwn['category']) > 1) {
								$allown[] = ' (i.catid IN (' . implode(',', $canEditOwn['category']) . ') AND i.created_by = ' . $user->id . ')'; 
							}
						}
						if (isset($canEdit['category'])) {
							if (count($canEdit['category']) == 1) {
								$allown[] = ' i.catid = ' . $canEdit['category'][0]; 
							} else if (count($canEdit['category']) > 1) {
								$allown[] = ' i.catid IN (' . implode(',', $canEdit['category']) . ')'; 
							}
						}
						if (isset($canEdit['item'])) {
							if (count($canEdit['item']) == 1) {
								$allown[] = ' i.id = ' . $canEdit['item'][0]; 
							} else if (count($canEdit['item']) > 1) {
								$allown[] = ' i.id IN (' . implode(',', $canEdit['item']) . ')'; 
							}
						}
						if (count($allown) > 0) {
							$where[] = (count($allown) > 1) ? ' ('.implode(' OR', $allown).')' : $allown[0];
						}
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

		if (FLEXI_FISH) {
			if ( $filter_lang ) {
				$where[] = 'ie.language = ' . $this->_db->Quote($filter_lang);
			}
		}
		
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
		
		if ($search && $scope == 1) {
			$where[] = ' LOWER(i.title) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $search, true ).'%', false );
		}

		if ($search && $scope == 2) {
			$where[] = ' LOWER(i.introtext) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $search, true ).'%', false );
		}

		if ($search && $scope == 4) {
			$where[] = ' MATCH (ie.search_index) AGAINST ('.$this->_db->Quote( $this->_db->getEscaped( $search, true ), false ).' IN BOOLEAN MODE)';
//			$where[] = ' MATCH (ie.search_index) AGAINST ('.$this->_db->Quote( $this->_db->getEscaped( $search, true ), false ).')';
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
				$row->language	= $lang ? $lang : $row->language;
				
				$row->store();
				$copyid = (int)$row->id;

				// get the item fields
				$query 	= 'SELECT *'
						. ' FROM #__flexicontent_fields_item_relations'
						. ' WHERE item_id = '. $sourceid
						;
				$this->_db->setQuery($query);
				$fields = $this->_db->loadObjectList();
				
				foreach($fields as $field)
				{
					if ($field->iscore != 1 && !empty($field->value)) {
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
		
		if (!$seccats[0])
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
			$this->setError('FLEXI_SELECT_CATEGORY');
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
	 * Method to change the state of an item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function setitemstate($id, $state = 1)
	{
		$user 	=& JFactory::getUser();

		if ( $id )
		{
			$v = FLEXIUtilities::getCurrentVersions((int)$id);
			
			$query = 'UPDATE #__content'
				. ' SET state = ' . (int)$state
				. ' WHERE id = '.(int)$id
				. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
			;
			$this->_db->setQuery( $query );
			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			$query = 'UPDATE #__flexicontent_items_versions'
				. ' SET value = ' . (int)$state
				. ' WHERE item_id = '.(int)$id
				. ' AND valueorder = 1'
				. ' AND field_id = 10'
				. ' AND version = ' . $v['version']
				;
			$this->_db->setQuery( $query );
			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
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
	function isAllowedToSubmit($items)
	{
		if ($items)
		{
			$CanEdit	= FAccess::checkAllContentAccess('com_content','edit','users',$user->gmid,'content','all');
			$CanEditOwn	= FAccess::checkAllContentAccess('com_content','editown','users',$user->gmid,'content','all');
			
			foreach ($items as $item) {
				$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			}
		}
		return $cids;
	}

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
		
		$item->url = JURI::base() . 'index.php?option=com_flexicontent&controller=items&task=edit&cid[]=' . $item->id;

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
		global $mainframe, $option;
		
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
		global $mainframe, $option;
		
		$filter_cats = $mainframe->getUserStateFromRequest( $option.'.items.filter_cats', 'filter_cats', '', 'int' );

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
			
			$where = $this->_db->nameQuote('sectionid').' = '.$this->_db->Quote(FLEXI_SECTION);
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
		
		// return true if flexi_access component is not used
		if (!FLEXI_ACCESS) return true;

		// return true for super administrators
		if ($user->gid >= 24) return true;

		$n		= count( $cid );
		if ($n)
		{
			$query = 'SELECT id, catid, created_by FROM #__content'
			. ' WHERE id IN ( '. implode(',', $cid) . ' )'
			;
			$this->_db->setQuery( $query );
			$items = $this->_db->loadObjectList();
			
			foreach ($items as $item)
			{
				$rights 		= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
				$canDelete 		= in_array('delete', $rights) || (FAccess::checkAllContentAccess('com_content','delete','users',$user->gmid,'content','all'));
				$canDeleteOwn	= (in_array('deleteown', $rights) && ($item->created_by == $user->id)) || ((FAccess::checkAllContentAccess('com_content','deleteown','users',$user->gmid,'content','all')) && ($item->created_by == $user->id));				
				
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
	function delete($cid)
	{
		if (count( $cid ))
		{
			$cids = implode( ',', $cid );
			$query = 'DELETE FROM #__content'
					. ' WHERE id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );
			
			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// remove items extended
			$query = 'DELETE FROM #__flexicontent_items_ext'
					. ' WHERE item_id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );
			
			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			//remove assigned tag references
			$query = 'DELETE FROM #__flexicontent_tags_item_relations'
					.' WHERE itemid IN ('. $cids .')'
					;
			$this->_db->setQuery($query);

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			//remove assigned category references
			$query = 'DELETE FROM #__flexicontent_cats_item_relations'
					.' WHERE itemid IN ('. $cids .')'
					;
			$this->_db->setQuery($query);

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// delete also fields in fields_item relation
			$query = 'DELETE FROM #__flexicontent_fields_item_relations'
					. ' WHERE item_id IN ('. $cids .')'
					;
			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			// delete also versions
			$query = 'DELETE FROM #__flexicontent_items_versions'
					. ' WHERE item_id IN ('. $cids .')'
					;
			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			$query = 'DELETE FROM #__flexicontent_versions'
					. ' WHERE item_id IN ('. $cids .')'
					;
			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			// delete also item ACL
			if (FLEXI_ACCESS) {
				$query 	= 'DELETE FROM #__flexiaccess_acl'
						. ' WHERE acosection = ' . $this->_db->Quote('com_content')
						. ' AND axosection = ' . $this->_db->Quote('item')
						. ' AND axo IN ('. $cids .')'
						;
				$this->_db->setQuery( $query );
			}
			
			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			return true;
		}

		return false;
	}
	
	/**
	 * Method to set the access level of the items
	 *
	 * @access	public
	 * @param 	integer id of the category
	 * @param 	integer access level
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function access($id, $access)
	{
		global $mainframe;
		$row =& JTable::getInstance('flexicontent_items', '');

		$row->load( $this->_id );
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
	 * Method to build the Joomfish languages list
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getLanguages()
	{
		$query = 'SELECT *'
				.' FROM #__languages'
//				.' ORDER BY ordering ASC'
				;
		$this->_db->setQuery($query);
		$languages = $this->_db->loadObjectList();
		
		$langs = new stdClass();
		if (isset($languages[0]->sef)) {
			require_once(JPATH_ROOT.DS.'administrator'.DS.'components'.DS.'com_joomfish'.DS.'helpers'.DS.'extensionHelper.php' );
			foreach ($languages as $lang) {
				$lang->code = $lang->lang_code;
				$lang->name = $lang->title;
				$lang->shortcode = $lang->sef;
				$lang->id = $lang->lang_id;
				$lang->imageurl = JURI::root().JoomfishExtensionHelper::getLanguageImageSource($lang);
				$langs->{$lang->code} = $lang;
			}
		}
		foreach ($languages as $language) {
			$name		 	= $language->code;
			$langs->$name 	= $language;			
		}
		
		return $langs;
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
		$languages =& JComponentHelper::getParams('com_languages');
		$lang = $languages->get('site', 'en-GB');

		// Get all Joomla sections
	    $query = 'SELECT * FROM #__sections';
    	$this->_db->setQuery($query);
		$sections = $this->_db->loadObjectList();
		
		$logs = new stdClass();
		$logs->sec = 0;
		$logs->cat = 0;
		$logs->art = 0;
//		$logs->err = new stdClass();
		
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
				$catparams->set($param->attributes('name'), $param->attributes('default'));
			}
		}
    	$catparams = $catparams->toString();		
		
		// Loop throught the section object and create cat -> subcat -> items -> fields
		$k = 0;
		foreach ($sections as $section)
		{
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
			$cat->params			= $catparams;
	   		$k++;
	   		$cat->check();
			if ($cat->store()) {
				$logs->sec++;
			} else {
				$logs->err->$k->type 	= JText::_( 'FLEXI_IMPORT_SECTION' ) . ' id';
				$logs->err->$k->id 		= $section->id;
				$logs->err->$k->title 	= $section->title;
			}
			
			// Get the categories of the created section
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
				$subcat->params		= $catparams;
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
					$item->id				= 0;
					$item->sectionid		= $flexisection->id;
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
		$where = " WHERE fi.isadvsearch='1'";
		$query = 'SELECT fi.*'
			.' FROM #__flexicontent_fields AS fi'
			.' LEFT JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id'
			//.' LEFT JOIN #__flexicontent_items_ext AS ie ON ftrel.type_id = ie.type_id'
			.$where
			.' AND fi.published = 1'
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
	
	function getFieldsItems_old($fields) {
		$fields = "'".implode("','", $fields)."'";
		$query = "SELECT DISTINCT firel.item_id FROM #__flexicontent_fields_item_relations as firel"
		//$query = "SELECT DISTINCT firel.item_id FROM #__flexicontent_items_versions as firel"
			//." JOIN #__flexicontent_items_ext as ie ON firel.item_id=ie.item_id"
			." JOIN #__content as a ON firel.item_id=a.id "//AND firel.version=a.version"
			//." WHERE firel.field_id IN ({$fields}) AND ie.type_id='{$typeid}' AND a.state IN (1, -5);"
			." WHERE firel.field_id IN ({$fields}) AND a.state IN (1, -5)"
		;
		$this->_db->setQuery($query);
		return $this->_db->loadResultArray();// or die($this->_db->getErrorMsg());
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
			return $this->_db->loadResultArray();// or die($this->_db->getErrorMsg());
		}
		
		// Find item having tags
		$items_with_tags = array();
		if ($get_items_with_tags == true) {
			$query  = 'SELECT DISTINCT itemid FROM #__flexicontent_tags_item_relations';
			$this->_db->setQuery($query);
			$items_with_tags = $this->_db->loadResultArray();// or die($this->_db->getErrorMsg());
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
			$items_with_noncore = $this->_db->loadResultArray();// or die($this->_db->getErrorMsg());
		}
		
		$item_list = array_merge($items_with_tags,$items_with_noncore);
		//echo count($item_list);
		return array_unique($item_list);
	}	
	
}
?>
