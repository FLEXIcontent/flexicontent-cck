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
class FlexicontentModelItems extends JModelLegacy
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
	 * Extra field columns to display in tems listing
	 *
	 * @var array
	 */
	var $_extra_cols = null;
	
	/**
	 * Category Data of listed items
	 *
	 * @var array
	 */
	var $_cats = null;
	
	/**
	 * Associated item translations
	 *
	 * @var array
	 */
	var $_translations = null;
	
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		$app     = JFactory::getApplication();
		$option  = JRequest::getVar('option');
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		
		$default_order     = $cparams->get('items_manager_order', 'i.ordering');
		$default_order_dir = $cparams->get('items_manager_order_dir', 'ASC');
		
		$filter_order_type = $app->getUserStateFromRequest( $option.'.items.filter_order_type',	'filter_order_type', 1, 'int' );
		$filter_order      = $app->getUserStateFromRequest( $option.'.items.filter_order', 'filter_order', $default_order, 'cmd' );
		$filter_order_Dir  = $app->getUserStateFromRequest( $option.'.items.filter_order_Dir', 'filter_order_Dir', $default_order_dir, 'word' );
		
		// Filter order is selected via current setting of filter_order_type selector
		$filter_order	= ($filter_order_type && ($filter_order == 'i.ordering')) ? 'catsordering' : $filter_order;
		$filter_order	= (!$filter_order_type && ($filter_order == 'catsordering')) ? 'i.ordering' : $filter_order;
		JRequest::setVar( 'filter_order', $filter_order );
		JRequest::setVar( 'filter_order_Dir', $filter_order_Dir );
		
		$filter_cats      = $app->getUserStateFromRequest( $option.'.items.filter_cats',	'filter_cats', '', 'int' );
		$filter_subcats   = $app->getUserStateFromRequest( $option.'.items.filter_subcats',	'filter_subcats', 1, 'int' );
		if ($filter_order_type && $filter_cats && ($filter_order=='i.ordering' || $filter_order=='catsordering')) {
			JRequest::setVar( 'filter_subcats',	0 );
		}
		
		$limit      = $app->getUserStateFromRequest( $option.'.items.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( $option.'.items.limitstart', 'limitstart', 0, 'int' );

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
		$this->_extra_cols = null;
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
		
		$task = JRequest::getCmd('task');
		$cid  = JRequest::getVar('cid', array());
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		// Lets load the Items if it doesn't already exist
		if ( $this->_data === null )
		{
			if ($task=='copy') {
				$query_ids = $cid;
			} else {
				// 1, get filtered, limited, ordered items
				$query = $this->_buildQuery();
				
				if ( $print_logging_info )  $start_microtime = microtime(true);
				$this->_db->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));
				$rows = $this->_db->loadObjectList();
				if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
				if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
				
				// 2, get current items total for pagination
				$this->_db->setQuery("SELECT FOUND_ROWS()");
				$this->_total = $this->_db->loadResult();
				
				// 3, get item ids
				$query_ids = array();
				foreach ($rows as $row) {
					$query_ids[] = $row->id;
				}
			}
			
			// 4, get item data
			if (count($query_ids)) $query = $this->_buildQuery($query_ids);
			if ( $print_logging_info )  $start_microtime = microtime(true);
			$_data = array();
			if (count($query_ids)) {
				$this->_db->setQuery($query);
				$_data = $this->_db->loadObjectList('item_id');
			}
			if ( $print_logging_info ) @$fc_run_times['execute_sec_queries'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
			
			// 5, reorder items and get cat ids
			$this->_data = array();
			$this->_catids = array();
			foreach($query_ids as $item_id) {
				$item = $_data[$item_id];
				
				$item->categories = preg_split("/[\s]*,[\s]*/", $item->relcats);
				foreach ($item->categories as $item_cat) {
					if ($item_cat) $this->_catids[$item_cat] = 1;
				}
				
				$this->_data[] = $item;
			}
			$this->_catids = array_keys($this->_catids);
			
			// 6, get other item data
			$k = 0;
			foreach ($this->_data as $item)
			{
				// Parse item configuration for every row
				$item->config = FLEXI_J16GE ? new JRegistry($item->config) : new JParameter($item->config);
	   		
				// Parse item's TYPE configuration if not already parsed
				if ( isset($tconfig[$item->type_name]) ) {
		   		$item->tconfig = &$tconfig[$item->type_name];
					continue;
				}
				$tconfig[$item->type_name] = FLEXI_J16GE ? new JRegistry($item->tconfig) : new JParameter($item->tconfig);
	   		$item->tconfig = $tconfig[$item->type_name];
			}
			$k = 1 - $k;
		}
		
		return $this->_data;
	}
	
	
	function getLangAssocs()
	{
		if ($this->_translations!==null) return $this->_translations;
		$this->_translations = array();
		
		// Make sure we item list is populased and non-empty
		if ( empty($this->_data) )  return $this->_translations;
		
		// Get associated translations
		$lang_parent_ids = array();
		foreach ($this->_data as $_item_data) {
			if ($_item_data->lang_parent_id) $lang_parent_ids[] = $_item_data->lang_parent_id;
		}
		if ( empty($lang_parent_ids) )  return $this->_translations;
		
		$query = 'SELECT i.id, i.title, i.created, i.modified, ie.lang_parent_id, ie.language as language, ie.language as lang '
			//. ', CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug '
			//. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug '
		  . ' FROM #__content AS i '
		  . ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id '
		  . ' WHERE ie.lang_parent_id IN ('.implode(',', $lang_parent_ids).')'
		  ;
		$this->_db->setQuery($query);
		$translations = $this->_db->loadObjectList();
		if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
		
		if ( empty($translations) )  return $this->_translations;
		
		foreach ($translations as $translation)
			$this->_translations[$translation->lang_parent_id][] = $translation;
		return $this->_translations;
	}
	
	
	/**
	 * Method to get fields used as extra columns of the item list
	 *
	 * @access public
	 * @return object
	 */
	function getExtraCols()
	{
		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');
		$flexiparams = JComponentHelper::getParams( 'com_flexicontent' );
		$filter_type = $app->getUserStateFromRequest( $option.'.items.filter_type', 	'filter_type', '', 'int' );
		
		if ( $this->_extra_cols !== null) return $this->_extra_cols;
		
		// Retrieve the custom field of the items list
		// STEP 1: Get the field properties
		if ( !empty($filter_type) ) {
			$query = 'SELECT t.attribs FROM #__flexicontent_types AS t WHERE t.id = ' . $filter_type;
			$this->_db->setQuery($query);
			$type_attribs = $this->_db->loadResult();
			if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
			
			$tparams = FLEXI_J16GE ? new JRegistry($type_attribs) : new JParameter($type_attribs);
			$im_extra_fields = $tparams->get("items_manager_extra_fields");
			$item_instance = new stdClass();
		} else {
			$item_instance = null;
			$im_extra_fields = $flexiparams->get("items_manager_extra_fields");
		}
		$im_extra_fields = preg_split("/[\s]*,[\s]*/", $im_extra_fields);
		
		foreach($im_extra_fields as $im_extra_field) {
			@list($fieldname,$methodname) = preg_split("/[\s]*:[\s]*/", $im_extra_field);
			$methodnames[$fieldname] = empty($methodname) ? 'display' : $methodname;
		}
		
		$query = ' SELECT fi.*'
		   .' FROM #__flexicontent_fields AS fi'
		   .' WHERE fi.name IN ("' . implode('","',array_keys($methodnames)) . '")'
		   .' ORDER BY FIELD(fi.name, "'. implode('","',array_keys($methodnames)) . '" )';
		$this->_db->setQuery($query);
		$extra_fields = $this->_db->loadObjectList();
		if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
		
		foreach($extra_fields as $field) {
			$field->methodname = $methodnames[$field->name];
			FlexicontentFields::loadFieldConfig($field, $item_instance);
		}
		
		$this->_extra_cols = & $extra_fields;
		$this->getExtraColValues();
		return $this->_extra_cols;
	}
	
	
	/**
	 * Method to get fields values of the fields used as extra columns of the item list
	 *
	 * @access public
	 * @return object
	 */
	function getExtraColValues()
	{
		if ( $this->_extra_cols== null) $this->getExtraCols();
		
		if ( empty($this->_extra_cols) ) return;
		if ( empty($this->_data) ) return;
		
		foreach($this->_data as $row)
		{
			foreach($this->_extra_cols as $field)
			{
		    // STEP 2: Get the field value for the current item
		    $query = ' SELECT v.value'
					 .' FROM #__flexicontent_fields_item_relations as v'
		       .' WHERE v.item_id = '.(int)$row->id
		       .'   AND v.field_id = '.$field->id
		       .' ORDER BY v.valueorder';
		    $this->_db->setQuery($query);
		    $values = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
				//if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
				
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
	 * Method to get items not having extended data associations
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getUnboundedItems($limit=25, $count_only=true, $checkNoExtData=true, $checkInvalidCat=true, $noCache=false)
	{
		if (!$checkNoExtData && !$checkInvalidCat) return $count_only ? 0 : array();
		
		$session = JFactory::getSession();
		$configured = FLEXI_J16GE ? FLEXI_CAT_EXTENSION : FLEXI_SECTION;
		if ( !$configured ) return null;
		
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		if ( $print_logging_info )  $start_microtime = microtime(true);
		
		$done = true;
		if ($checkNoExtData)  $done = $done && ($session->get('unbounded_noext',  false, 'flexicontent') !== false);
		if ($checkInvalidCat) $done = $done && ($session->get('unbounded_badcat', false, 'flexicontent') !== false);
		
		if ( !$noCache && $done ) return $count_only ? 0 : array();
		
		$match_rules = array();
		if ($checkNoExtData)  $match_rules[] = 'ie.item_id IS NULL';
		if ($checkInvalidCat) $match_rules[] = 'cat.id IS NULL';
		if ( empty($match_rules) ) return $count_only ? 0 : array();
		$query 	= 'SELECT '. ($count_only ? 'COUNT(*)' : 'c.id, c.title, c.introtext, c.`fulltext`, c.catid, c.created, c.created_by, c.modified, c.modified_by, c.version, c.state')
			. (FLEXI_J16GE ? ', c.language' : '')
			. ' FROM #__content as c'
			. ($checkNoExtData  ? ' LEFT JOIN #__flexicontent_items_ext as ie ON c.id=ie.item_id' : '')
			. ($checkInvalidCat ? ' LEFT JOIN #__categories as cat ON c.catid=cat.id' : '')
			. (!FLEXI_J16GE ? ' WHERE sectionid = ' . (int)FLEXI_SECTION : ' WHERE 1')
			. ' AND ('.implode(' OR ',$match_rules).')'
			;
		$this->_db->setQuery($query, 0, $limit);
		
		if ($count_only) {
			$unbounded_count = (int) $this->_db->loadResult();
			if ($checkNoExtData)  $session->set('unbounded_noext', $unbounded_count, 'flexicontent');
			if ($checkInvalidCat) $session->set('unbounded_badcat', $unbounded_count, 'flexicontent');
		} else {
			$unbounded = $this->_db->loadObjectList();
		}
		
		if ( $print_logging_info ) @$fc_run_times['unassoc_items_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		return $count_only ? $unbounded_count : $unbounded;
	}
	
	
	
	function fixMainCat($default_cat)
	{
		// Correct non-existent main category in content table
		$query = 'UPDATE #__content as c '
					.' LEFT JOIN #__categories as cat ON c.catid=cat.id'
					.' SET c.catid=' .$default_cat
					.' WHERE cat.id IS NULL';
		$this->_db->setQuery($query);
		$this->_db->query();
		
		// Correct non-existent main category in content table
		$query = 'UPDATE #__flexicontent_items_tmp as c '
					.' LEFT JOIN #__categories as cat ON c.catid=cat.id'
					.' SET c.catid=' .$default_cat
					.' WHERE cat.id IS NULL';
		$this->_db->setQuery($query);
		$this->_db->query();
	}
	
	
	/**
	 * Method to add flexi extended datas to standard content
	 * 
	 * @params object	the unassociated items rows
	 * @params boolean	add the records from the items_ext table
	 * @return boolean
	 * @since 1.5
	 */
	function bindExtData($rows)
	{
		if (!$rows || !count($rows)) return;
		
		$default_lang = flexicontent_html::getSiteDefaultLang();
		$typeid = JRequest::getVar('typeid',1);
		$default_cat = (int)JRequest::getVar('default_cat', '');
		
		// Get invalid cats, to avoid using them during binding, this is only done once
		$session = JFactory::getSession();
		$badcats_fixed = $session->get('badcats', null, 'flexicontent');
		if ( $badcats_fixed === null ) {
			// Correct non-existent main category in content table
			$query = 'UPDATE #__content as c '
						.' LEFT JOIN #__categories as cat ON c.catid=cat.id'
						.' SET c.catid=' .$default_cat
						.' WHERE cat.id IS NULL';
			$this->_db->setQuery($query);
			$this->_db->query();
			$session->set('badcats_fixed', 1, 'flexicontent');
		}
		
		// Calculate item data to be used for current bind STEP
		$catrel = array();
		foreach ($rows as $row) {
			$row_catid = (int)$row->catid;
			$catrel[] = '('.$row_catid.', '.(int)$row->id.')';
			// append the text property to the object
			if (JString::strlen($row->fulltext) > 1) {
				$row->text_stripped = $row->introtext . '<hr id="system-readmore" />' . $row->fulltext;
			} else {
				$row->text_stripped = flexicontent_html::striptagsandcut($row->introtext);
			}			
		}
		
		// Insert main category-item relation via single query
		$catrel = implode(', ', $catrel);
		$query = "INSERT INTO #__flexicontent_cats_item_relations (`catid`, `itemid`) "
				."  VALUES ".$catrel
				." ON DUPLICATE KEY UPDATE ordering=ordering";
		$this->_db->setQuery($query);
		$this->_db->query();
		
		// Insert items_ext datas,
		// NOTE: we will not use a single query for creating multiple records, instead we will create only e.g. 100 at once,
		// because of the column search_index which can be quite long
		$itemext = array();
		$id_arr  = array();
		$row_count = count($rows);
		$n = 0;
		foreach ($rows as $row) {
			if (FLEXI_J16GE) $ilang = $row->language ? $row->language : $default_lang;
			else $ilang = $default_lang;  // J1.5 has no language setting
			$itemext[] = '('.(int)$row->id.', '. $typeid .', '.$this->_db->Quote($ilang).', '.$this->_db->Quote($row->title.' | '.$row->text_stripped).', '.(int)$row->id.')';
			$id_arr[] = (int)$row->id;
			$n++;
			if ( ($n%101 == 0) || ($n==$row_count) ) {
				$itemext_list = implode(', ', $itemext);
				$query = "INSERT INTO #__flexicontent_items_ext (`item_id`, `type_id`, `language`, `search_index`, `lang_parent_id`)"
						." VALUES " . $itemext_list
						." ON DUPLICATE KEY UPDATE type_id=VALUES(type_id), language=VALUES(language), search_index=VALUES(search_index)";
				$this->_db->setQuery($query);
				$this->_db->query();
				$itemext = array();
				
				$query = "UPDATE #__flexicontent_items_tmp"
					." SET type_id=".$typeid
					." WHERE id IN(".implode(',',$id_arr).")";
				$this->_db->setQuery($query);
				$this->_db->query();
			}
		}
		// Update temporary item data
		$this->updateItemCountingData($rows);
	}
	
	
	function updateItemCountingData($rows = false)
	{
		$app = JFactory::getApplication();
		$db = JFactory::getDBO();
		
		$cache_tbl = "#__flexicontent_items_tmp";
		$tbls = array($cache_tbl);
		if (!FLEXI_J16GE) $tbl_fields = $db->getTableFields($tbls);
		else foreach ($tbls as $tbl) $tbl_fields[$tbl] = $db->getTableColumns($tbl);
		
		// Get the column names
		$tbl_fields = array_keys($tbl_fields[$cache_tbl]);
		$tbl_fields_sel = array();
		foreach ($tbl_fields as $tbl_field) {
			if ( (!FLEXI_J16GE && $tbl_field=='language') || $tbl_field=='type_id' || $tbl_field=='lang_parent_id')
				$tbl_fields_sel[] = 'ie.'.$tbl_field;
			else
				$tbl_fields_sel[] = 'c.'.$tbl_field;
		}
		
		// Copy data into it
		$query 	= 'INSERT INTO '.$cache_tbl.' (';
		$query .= "`".implode("`, `", $tbl_fields)."`";
		$query .= ") SELECT ";
		
		$cols_select = array();
		$query .= implode(", ", $tbl_fields_sel);
		$query .= " FROM #__content AS c";
		$query .= " JOIN #__flexicontent_items_ext AS ie ON c.id=ie.item_id";
		if ( !empty($rows) ) {
			$row_ids = array();
			foreach ($rows as $row) $row_ids[] = $row->id;
			$query .= " WHERE c.id IN (".implode(',', $row_ids).")";
		}
		
		$db->setQuery($query);
		
		try { $result = $db->query(); } catch (Exception $e) { $result = false; }
		if ($db->getErrorNum()) echo $db->getErrorMsg();
		
		return $result;
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
	function _buildQuery( $query_ids=false )
	{
		$app    = JFactory::getApplication();
		$option = JRequest::getCmd( 'option' );
		
		// Get the WHERE and ORDER BY clauses for the query
		if ( !$query_ids ) {
			$where		= $this->_buildContentWhere();
			$orderby	= $this->_buildContentOrderBy();
		}
		
		$lang  = (FLEXI_FISH || FLEXI_J16GE) ? 'ie.language AS lang, ie.lang_parent_id, ' : '';
		$lang .= (FLEXI_FISH || FLEXI_J16GE) ? 'CASE WHEN ie.lang_parent_id=0 THEN i.id ELSE ie.lang_parent_id END AS lang_parent_id, ' : '';
		
		$filter_cats      = $app->getUserStateFromRequest( $option.'.items.filter_cats',	'filter_cats', '', 'int' );
		$filter_subcats   = $app->getUserStateFromRequest( $option.'.items.filter_subcats',	'filter_subcats', 1, 'int' );
		$filter_state     = $app->getUserStateFromRequest( $option.'.items.filter_state',			'filter_state',			'',		'word' );
		$filter_order     = $app->getUserStateFromRequest( $option.'.items.filter_order',			'filter_order',			'',		'cmd' );
		$filter_stategrp  = $app->getUserStateFromRequest( $option.'.items.filter_stategrp',	'filter_stategrp',	'',		'word' );
		
		$nullDate = $this->_db->Quote($this->_db->getNullDate());
		$nowDate = $this->_db->Quote( FLEXI_J16GE ? JFactory::getDate()->toSql() : JFactory::getDate()->toMySQL() );
		
		$ver_specific_joins = '';
		if (FLEXI_J16GE) {
			$ver_specific_joins .= ' LEFT JOIN #__viewlevels AS level ON level.id=i.access';
			$ver_specific_joins .= ' LEFT JOIN #__categories AS cat ON i.catid=cat.id AND cat.extension='.$this->_db->Quote(FLEXI_CAT_EXTENSION);
		} else {
			$ver_specific_joins .= ' LEFT JOIN #__groups AS g ON g.id = i.access';
		}
		
		$subquery 	= 'SELECT name FROM #__users WHERE id = i.created_by';
		
		if ( !$query_ids ) {
			$query = 'SELECT SQL_CALC_FOUND_ROWS i.id '
				. ', t.name AS type_name, rel.ordering as catsordering '
				. (($filter_state=='RV') ? ', i.version' : '')
				. ( in_array($filter_order, array('i.ordering','catsordering')) ? 
					', CASE WHEN i.state IN (1,-5) THEN 0 ELSE (CASE WHEN i.state IN (0,-3,-4) THEN 1 ELSE (CASE WHEN i.state IN ('.(FLEXI_J16GE ? 2:-1).') THEN 2 ELSE (CASE WHEN i.state IN (-2) THEN 3 ELSE 4 END) END) END) END as state_order ' : ''
					)
				;
		} else {
			$query =
				'SELECT i.*, ie.item_id as item_id, ie.search_index AS search_index, ie.type_id, '. $lang .' u.name AS editor, rel.catid as rel_catid, '
				.' GROUP_CONCAT(DISTINCT rel.catid SEPARATOR  ",") AS relcats, '
				. (FLEXI_J16GE ? 'level.title AS access_level, ' : 'g.name AS groupname, ')
				. ( in_array($filter_order, array('i.ordering','catsordering')) ? 
					'CASE WHEN i.state IN (1,-5) THEN 0 ELSE (CASE WHEN i.state IN (0,-3,-4) THEN 1 ELSE (CASE WHEN i.state IN ('.(FLEXI_J16GE ? 2:-1).') THEN 2 ELSE (CASE WHEN i.state IN (-2) THEN 3 ELSE 4 END) END) END) END as state_order, ' : ''
					)
				. 'CASE WHEN i.publish_up = '.$nullDate.' OR i.publish_up <= '.$nowDate.' THEN 0 ELSE 1 END as publication_scheduled, '
				. 'CASE WHEN i.publish_down = '.$nullDate.' OR i.publish_down >= '.$nowDate.' THEN 0 ELSE 1 END as publication_expired, '
				. 't.name AS type_name, rel.ordering as catsordering, (' . $subquery . ') AS author, i.attribs AS config, t.attribs as tconfig'
				;
		}
		
		$use_tmp = !$query_ids;
		$tmp_only = $use_tmp && !JRequest::getVar('search');
		$query .= ""
				. ( $use_tmp ? ' FROM #__flexicontent_items_tmp AS i' :' FROM #__content AS i')
				. ( $tmp_only ? '' : ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id')
				. ' JOIN #__categories AS c ON c.id = i.catid'
				. (($filter_state=='RV') ? ' LEFT JOIN #__flexicontent_versions AS fv ON i.id=fv.item_id' : '')
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id' // left join needed to INCLUDE items do not have records in the multi-cats-items TABLE
				.    ($filter_cats && !$filter_subcats ? ' AND rel.catid='.$filter_cats : '')
				. ' LEFT JOIN #__flexicontent_types AS t ON t.id = '.( $tmp_only ? 'i.' : 'ie.').'type_id'   // left join needed to detect items without type !!
				. ( $use_tmp ? '' : ' LEFT JOIN #__users AS u ON u.id = i.checked_out')
				. $ver_specific_joins
				;
		if ( !$query_ids ) {
			$query .= ""
				. $where
				. ' GROUP BY i.id'
				. (($filter_state=='RV') ? ' HAVING i.version<>MAX(fv.version_id)' : '')
				. $orderby
				;
		} else {
			$query .= ''
				. ' WHERE i.id IN ('. implode(',', $query_ids) .')'
				. ' GROUP BY i.id'
				;
		}
		//echo $query ."<br/><br/>";
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
		$app     = JFactory::getApplication();
		$option  = JRequest::getVar('option');
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$use_tmp = true;
		
		$filter_order_type= $app->getUserStateFromRequest( $option.'.items.filter_order_type',	'filter_order_type', 0, 'int' );
		$filter_order     = $app->getUserStateFromRequest( $option.'.items.filter_order', 'filter_order', '', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.items.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		
		$filter_stategrp = $app->getUserStateFromRequest( $option.'.items.filter_stategrp',	'filter_stategrp', '', 'word' );
		$extra_order  = 'state_order, ';
		if (
			($filter_order_type && (FLEXI_FISH || FLEXI_J16GE)) ||   // FLEXIcontent order supports language in J1.5 too
			(!$filter_order_type && FLEXI_J16GE)   // Joomla order does not support language in J1.5
		) {
			$extra_order .= FLEXI_J16GE || $use_tmp ? ' i.language, ' : ' ie.language, ';
		}
		
		if ($filter_order == 'ie.lang_parent_id') {
			$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir .", i.id ASC";
		} else if ($filter_order == 'i.ordering') {
			$orderby 	= ' ORDER BY i.catid, ' .$extra_order. $filter_order .' '. $filter_order_Dir .", i.id ASC";
		} else if ($filter_order == 'catsordering') {
			$orderby 	= ' ORDER BY rel.catid, ' .$extra_order. $filter_order.' '.$filter_order_Dir .", i.id ASC";
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
		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');
		$session = JFactory::getSession();
		
		// Check for SPECIAL item listing CASES, in which the item ids are alredy calculated
		$filter_fileid = JRequest::getInt('filter_fileid', 0);
		if ($filter_fileid)
		{
			$fileid_to_itemids = $session->get('fileid_to_itemids', array(),'flexicontent');
			$itemids =  $fileid_to_itemids[$filter_fileid];
			if ( empty($itemids) ) {
				return ' WHERE 0 ';
			} else {
				return ' WHERE i.id IN ('. implode(',', $itemids) .') ';
			}
		}
		
		$nullDate = $this->_db->getNullDate();

		$filter_type 		= $app->getUserStateFromRequest( $option.'.items.filter_type', 	'filter_type', '', 'int' );
		$filter_cats 		= $app->getUserStateFromRequest( $option.'.items.filter_cats',	'filter_cats', '', 'int' );
		$filter_subcats	= $app->getUserStateFromRequest( $option.'.items.filter_subcats',	'filter_subcats', 1, 'int' );
		$filter_catsinstate = $app->getUserStateFromRequest( $option.'.items.filter_catsinstate',	'filter_catsinstate', 1, 'int' );
		$filter_state 	= $app->getUserStateFromRequest( $option.'.items.filter_state', 	'filter_state', '', 'word' );
		$filter_stategrp= $app->getUserStateFromRequest( $option.'.items.filter_stategrp',	'filter_stategrp', '', 'word' );
		$filter_id	 		= $app->getUserStateFromRequest( $option.'.items.filter_id', 		'filter_id', '', 'int' );
		if (FLEXI_FISH || FLEXI_J16GE) {
			$filter_lang 	= $app->getUserStateFromRequest( $option.'.items.filter_lang', 	'filter_lang', '', 'string' );
		}
		$filter_authors = $app->getUserStateFromRequest( $option.'.items.filter_authors', 'filter_authors', '', 'int' );
		$scope     = $app->getUserStateFromRequest( $option.'.items.scope', 			'scope', '', 'int' );
		$search    = $app->getUserStateFromRequest( $option.'.items.search', 		'search', '', 'string' );
		$search    = trim( JString::strtolower( $search ) );
		$date      = $app->getUserStateFromRequest( $option.'.items.date', 			'date', 	 1, 	'int' );
		$startdate = $app->getUserStateFromRequest( $option.'.items.startdate', 	'startdate', '', 	'cmd' );
		if ($startdate == JText::_('FLEXI_FROM')) { $startdate	= $app->setUserState( $option.'.items.startdate', '' ); }
		$startdate = trim( JString::strtolower( $startdate ) );
		$enddate   = $app->getUserStateFromRequest( $option.'.items.enddate', 		'enddate',	 '', 	'cmd' );
		if ($enddate == JText::_('FLEXI_TO')) { $enddate = $app->setUserState( $option.'.items.enddate', '' ); }
		$enddate   = trim( JString::strtolower( $enddate ) );

		$where = array();
		
		if (FLEXI_J16GE) {
			// Limit items to the children of the FLEXI_CATEGORY, currently FLEXI_CATEGORY is root category (id:1) ...
			$where[] = ' (cat.lft > ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND cat.rgt < ' . $this->_db->Quote(FLEXI_RGT_CATEGORY) . ')';
			$where[] = ' cat.extension = ' . $this->_db->Quote(FLEXI_CAT_EXTENSION);
		} else {
			// Limit items to FLEXIcontent Section
			$where[] = ' i.sectionid = ' . $this->_db->Quote(FLEXI_SECTION);
		}

		$user = JFactory::getUser();
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$allitems	= $permission->DisplayAllItems;
			
			if (!@$allitems) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				
				//$canEdit['item'] 	= FlexicontentHelperPerm::checkUserElementsAccess($user->id, 'core.edit', 'item');
				//$canEdit['category'] = FlexicontentHelperPerm::checkUserElementsAccess($user->id, 'core.edit', 'category');  // SHOULD not be used
				//$canEditOwn['item']		= FlexicontentHelperPerm::checkUserElementsAccess($user->id, 'core.edit.own', 'item');
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
				/*$where_edit = array();
				if (count($canEditOwn['item'])) {
					$where_edit[] = ' ( i.created_by = ' . $user->id . ' AND i.id IN (' . implode(',', $canEditOwn['item']) . ') )';
				}
				if (count($canEdit['item']))  {
					$where_edit[] = ' i.id IN (' . implode(',', $canEdit['item']) . ')'; 
				}
				// Add limits to where ...
				if (count($where_edit)) {
					$where[] = ' ('.implode(' OR', $where_edit).')';
				}*/
				$where[] = ' t.access IN (0,'.$aid_list.')';
				$where[] = ' c.access IN (0,'.$aid_list.')';
				$where[] = ' i.access IN (0,'.$aid_list.')';
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
						$jAp= JFactory::getApplication();
						$jAp->enqueueMessage( JText::_('FLEXI_CANNOT_VIEW_EDIT_ANY_ITEMS'), 'notice' );
						$where[] = ' 0 ';
					}
				}
			}
		}
		
		if ( $filter_type ) {
			$where[] = 'i.type_id = ' . $filter_type;
		}

		if ( $filter_cats ) {
			// Limit sub-category items by main or by main/secondary item's cats , TODO: add if ... needed
			//$cat_type = ($filter_maincat) ? 'i.catid' : 'rel.catid';
			$cat_type = 'rel.catid';
			
			if ( $filter_subcats ) {
				global $globalcats;
				
				$_sub_cids = array();
				if ($filter_catsinstate == 2) {
					$_sub_cids = $globalcats[$filter_cats]->descendantsarray;
				} else if ($filter_catsinstate == 1) {
					foreach( $globalcats[$filter_cats]->descendantsarray as $_dcatid) {
						if ($globalcats[$_dcatid]->published) $_sub_cids[] = $_dcatid;
					}
				} else if ($filter_catsinstate == 0) {
					foreach( $globalcats[$filter_cats]->descendantsarray as $_dcatid) {
						if ($globalcats[$_dcatid]->published!=1) $_sub_cids[] = $_dcatid;
					}
				}
				if ( empty ($_sub_cids) ) $where[] = ' FALSE  ';
				else $where[] = '('.$cat_type.' IN (' . implode( ', ', $_sub_cids ) . ')' .' OR '. 'c.id IN (' . implode( ', ', $_sub_cids ) . '))';
				
			} else {
				$where[] = $cat_type.' = ' . $filter_cats;
			}
		} else {
			if ($filter_catsinstate == 1) {
				$where[] = '(rel.catid IN ( SELECT id FROM #__categories WHERE published=1 )' .' OR '. 'c.published = 1)';
			} else if ($filter_catsinstate == 0) {
				$where[] = '(rel.catid IN ( SELECT id FROM #__categories WHERE published=0 )' .' OR '. 'c.published = 0)';
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
				$where[] = 'i.language = ' . $this->_db->Quote($filter_lang);
			}
		}
		
		if ( $filter_stategrp=='all' ) {
			// no limitations
		} else if ( $filter_stategrp=='published' ) {
			$where[] = 'i.state IN (1,-5)';
		} else if ( $filter_stategrp=='unpublished' ) {
			$where[] = 'i.state IN (0,-3,-4)';
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
		
		if ($search) {
			$escaped_search = FLEXI_J16GE ? $this->_db->escape( $search, true ) : $this->_db->getEscaped( $search, true );
		}
		
		if ($search && $scope == 1) {
			$where[] = ' LOWER(i.title) LIKE '.$this->_db->Quote( '%'.$escaped_search.'%', false );
		}

		if ($search && $scope == 2) {
			$where[] = ' LOWER(i.introtext) LIKE '.$this->_db->Quote( '%'.$escaped_search.'%', false );
		}

		if ($search && $scope == 4) {
			$where[] = ' MATCH (ie.search_index) AGAINST ('.$this->_db->Quote( $escaped_search.'*', false ).' IN BOOLEAN MODE)';
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
		$app = JFactory::getApplication();
		$dbprefix = $app->getCfg('dbprefix');
		
		// Try to find falang
		$_FALANG = false;
		if (FLEXI_J16GE) {
			$this->_db->setQuery('SHOW TABLES LIKE "'.$dbprefix.'falang_content"');
			$_FALANG = (boolean) count($this->_db->loadObjectList());
		}
		
		// Try to find old joomfish tables (with current DB prefix)
		$this->_db->setQuery('SHOW TABLES LIKE "'.$dbprefix.'jf_content"');
		$_FISH = (boolean) count($this->_db->loadObjectList());
	
		// Try to find old joomfish tables (with J1.5 jos prefix)
		if (!$_FISH) {
			$this->_db->setQuery('SHOW TABLES LIKE "jos_jf_content"');
			if ( count($this->_db->loadObjectList()) ) {
				$_FISH = true;
				$dbprefix = 'jos_';
			}
		}
		
		// Detect version of joomfish tables
		$_FISH22GE = false;
		if ($_FISH) {
			$this->_db->setQuery('SHOW TABLES LIKE "'.$dbprefix.'jf_languages_ext"');
			$_FISH22GE = (boolean) count($this->_db->loadObjectList());
		}
		
		$_NEW_LANG_TBL = FLEXI_J16GE || _FISH22GE;
		
		
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
			$desc_field = JTable::getInstance('flexicontent_fields', '');
			$desc_field->load($desc_field_id);
		}
		
		foreach ($cid as $itemid)
		{
			for( $i=0; $i < $copynr; $i++ )
			{
				// (a) Get existing item
				$item = JTable::getInstance('flexicontent_items', '');
				$item->load($itemid);
				// Some shortcuts
				$sourceid 	= (int)$item->id;
				$curversion = (int)$item->version;
				
				// (b) We create copy so that the original data are always available
				$row = clone($item);
				
				// (c) Force creation & assigning of new records by cleaning the primary keys
				$row->id 				= null;    // force creation of new record in _content DB table
				$row->item_id 	= null;    // force creation of new record in _flexicontent_ext DB table
				if (FLEXI_J16GE)
					$row->asset_id 	= null;  // force creation of new record in _assets DB table
					
				// (d) Start altering the properties of the cloned item
				$row->title 		= ($prefix ? $prefix . ' ' : '') . $item->title . ($suffix ? ' ' . $suffix : '');
				$row->hits 			= 0;
				if (FLEXI_J16GE)  // cleared featured flag
					$row->featured  = 0;
				$row->version 	= 1;
				$datenow 				= JFactory::getDate();
				$row->created 		= FLEXI_J16GE ? $datenow->toSql() : $datenow->toMySQL();
				$row->publish_up	= FLEXI_J16GE ? $datenow->toSql() : $datenow->toMySQL();
				$row->modified 		= $nullDate = $this->_db->getNullDate();
				$row->state			= $state ? $state : $row->state;
				$lang_from			= substr($row->language,0,2);
				$row->language	= $lang ? $lang : $row->language;
				$lang_to				= substr($row->language,0,2);
				
				$doauto['title'] = $doauto['introtext'] = $doauto['fulltext'] = $doauto['metakey'] = $doauto['metadesc'] = true;    // In case JF data is missing
				if ($translate_method == 2 || $translate_method == 4) {
					// a. Try to get joomfish/falang translation from the item
					$jfitemfields = false;
					
					if ($_FALANG) {
						$query = "SELECT c.* FROM `#__falang_content` AS c "
							." LEFT JOIN #__languages AS lg ON c.language_id=lg.lang_id"
							." WHERE c.reference_table = 'content' AND lg.lang_code='".$row->language."' AND c.reference_id = ". $sourceid;
						$this->_db->setQuery($query);
						$jfitemfields = $this->_db->loadObjectList();
					}
					
					if ( !$jfitemfields && $_FISH) {
						$query = "SELECT c.* FROM `".$dbprefix."jf_content` AS c "
							." LEFT JOIN #__languages AS lg ON c.language_id=".($_NEW_LANG_TBL ? "lg.lang_id" : "lg.id")
							." WHERE c.reference_table = 'content' AND ".($_NEW_LANG_TBL ? "lg.lang_code" : "lg.code")."='".$row->language."' AND c.reference_id = ". $sourceid;
						$this->_db->setQuery($query);
						$jfitemfields = $this->_db->loadObjectList();
					}
					
					// b. if joomfish translation found set for the new item
					if($jfitemfields) {
						$jfitemdata = new stdClass();
						foreach($jfitemfields as $jfitemfield) {
							$jfitemdata->{$jfitemfield->reference_field} = $jfitemfield->value;
						}
						
						if (isset($jfitemdata->title) && mb_strlen($jfitemdata->title)>0){
							$row->title = $jfitemdata->title;
							$doauto['title'] = false;
						}
						
						if (isset($jfitemdata->alias) && mb_strlen($jfitemdata->alias)>0) {
							$row->alias = $jfitemdata->alias;
						}
						
						if (isset($jfitemdata->introtext) && mb_strlen(strip_tags($jfitemdata->introtext))>0) {
							$row->introtext = $jfitemdata->introtext;
							$doauto['introtext'] = false;
						}
						
						if (isset($jfitemdata->fulltext) && mb_strlen(strip_tags($jfitemdata->fulltext))>0) {
							$row->fulltext = $jfitemdata->fulltext;
							$doauto['fulltext'] = false;
						}
						
						if (isset($jfitemdata->metakey) && mb_strlen($jfitemdata->metakey)>0) {
							$row->metakey = $jfitemdata->metakey;
							$doauto['metakey'] = false;
						}
						
						if (isset($jfitemdata->metadesc) && mb_strlen($jfitemdata->metadesc)>0) {
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
				$query 	= 'SELECT fir.*, f.* '
						. ' FROM #__flexicontent_fields_item_relations as fir'
						. ' LEFT JOIN #__flexicontent_fields as f ON f.id=fir.field_id'
						. ' WHERE item_id = '. $sourceid
						;
				$this->_db->setQuery($query);
				$fields = $this->_db->loadObjectList();
				//echo "<pre>"; print_r($fields); exit;
				
				if ($doTranslation) {
					$this->translateFieldValues( $fields, $row, $lang_from, $lang_to);
				}
				//foreach ($fields as $field)  if ($field->field_type!='text' && $field->field_type!='textarea') { print_r($field->value); echo "<br><br>"; }
				
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
				$cparams = JComponentHelper::getParams( 'com_flexicontent' );
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
				$cats = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
				
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
					$tags = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
			
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
				else if ($method == 99 && ($maincat || $seccats))
				{
					$row->catid = $maincat ? $maincat : $row->catid;
					$this->moveitem($copyid, $row->catid, $seccats);
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
		
		$item = JTable::getInstance('flexicontent_items', '');
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
			$seccats = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
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
		$sender = JFactory::getUser();

		// messaging for new items
		require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_messages'.DS.'tables'.DS.'message.php');

		// load language for messaging
		$lang = JFactory::getLanguage();
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
	function move($direction, $ord_catid, $prev_order)
	{
		$app     = JFactory::getApplication();
		$option  = JRequest::getVar('option');
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		
		// Every state group has different ordering
		$row = JTable::getInstance('flexicontent_items', '');
		if (!$row->load( $this->_id ) ) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		$stategrps = array(1=>'published', 0=>'unpublished', -2=>'trashed', -3=>'unpublished', -4=>'unpublished', -5=>'published');
		$row_stategrp = @ $stategrps[$row->state];
		
		switch ($row_stategrp)
		{
		case 'published':
			$item_states = JText::_( 'FLEXI_GRP_PUBLISHED' );
			$state_where = 'state IN (1,-5)';
			break;
		case 'unpublished':
			$item_states = JText::_( 'FLEXI_GRP_UNPUBLISHED' );
			$state_where = 'state IN (0,-3,-4)';
			break;
		case 'trashed':
			$item_states = JText::_( 'FLEXI_GRP_TRASHED' );
			$state_where = 'state = -2';
			break;
		case 'archived':
			$item_states = JText::_( 'FLEXI_GRP_ARCHIVED' );
			$state_where = 'state = '.(FLEXI_J16GE ? 2:-1);
			break;
		default:
			JError::raiseWarning( 500, 'Item state seems to be unknown. Ordering groups include items in state groups: (a) published (b) unpublished (c) archived (d) trashed"');
			return false;
			break;
		}
		
		$filter_order_type= $app->getUserStateFromRequest( $option.'.items.filter_order_type',	'filter_order_type', 0, 'int' );
		
		$filter_order_Dir	= $app->getUserStateFromRequest( $option.'.items.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		$direction = strtolower($filter_order_Dir) == 'desc' ? - $direction : $direction;
		
		if ( !$filter_order_type )
		{
			$where = 'catid = '. $row->catid .' AND '. $state_where .((FLEXI_FISH || FLEXI_J16GE) ? ' AND language ='. $this->_db->Quote($row->language) : '');
			
			if ( !$row->move($direction, $where) ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			//$app->enqueueMessage(JText::sprintf('Current re-ordering involved items in %s item states',$item_states) ,'message');
			return true;
		}
		else
		{
			$item_cb = JRequest::getVar( 'item_cb', array(0), 'post', 'array' );
			$row_item_cb = array_search($this->_id, $item_cb);
			$row_ord_catid  = $ord_catid [$row_item_cb];
			$row_prev_order = $prev_order[$row_item_cb];
			
			// Verify currently moved item exists in given category !!!
			$query = 'SELECT itemid, ordering'
					.' FROM #__flexicontent_cats_item_relations'
					.' WHERE catid = ' . $row_ord_catid
					.' AND itemid = ' . $this->_id
					;
			$this->_db->setQuery( $query, 0, 1 );  //echo "<pre>". $query."\n";
			$origin = $this->_db->loadObject();
			
			if (!$origin) {
				$this->setError('Some error occured item to move is not assigned to given category: '.$this->_db->getErrorMsg());
				return false;
			} else if ($row_prev_order != (int) $origin->ordering) {
				JError::raiseNotice( 500, 'Someone has already changed order of this item, but doing reordering anyway' );
				$row_prev_order = (int) $origin->ordering;
			}
			
			// Find the NEXT or PREVIOUS item in category to use it for swapping the ordering numbers
			$sql = 'SELECT rel.itemid, rel.ordering, i.state' . ((FLEXI_FISH || FLEXI_J16GE) ? ' , ie.language' : '')
					. ' FROM #__flexicontent_cats_item_relations AS rel'
					. ((FLEXI_FISH || FLEXI_J16GE) ? ' JOIN #__flexicontent_items_ext AS ie ON rel.itemid=ie.item_id' : '')
					. ' JOIN #__content AS i ON i.id=rel.itemid'
					;
			if ($direction < 0)
			{
				$sql .= ' WHERE rel.ordering >= 0 AND rel.ordering < '.(int) $origin->ordering;
				$sql .= ' AND rel.catid = ' . $row_ord_catid .' AND '. $state_where .((FLEXI_FISH || FLEXI_J16GE) ? ' AND ie.language ='. $this->_db->Quote($row->language) : '');
				$sql .= ' ORDER BY ordering DESC';
			}
			else if ($direction > 0)
			{
				$sql .= ' WHERE rel.ordering >= 0 AND rel.ordering > '.(int) $origin->ordering;
				$sql .= ' AND rel.catid = ' . $row_ord_catid .' AND '. $state_where .((FLEXI_FISH || FLEXI_J16GE) ? ' AND ie.language ='. $this->_db->Quote($row->language) : '');
				$sql .= ' ORDER BY rel.ordering';
			}
			else
			{
				JError::raiseWarning( 500, 'Cannot move item, neither UP nor Down, because given direction is zero' );
				return false;
			}
			
			$this->_db->setQuery( $sql, 0, 1 );  //echo $sql."\n";
			$row = $this->_db->loadObject();
			
			if ( $this->_db->getErrorNum() ) {
				$msg = $this->_db->getErrorMsg();
				$this->setError( $msg );
				return false;
			}
			
			if (isset($row))
			{
				// NEXT or PREVIOUS item found, swap its order with currently moved item
				$query = 'UPDATE #__flexicontent_cats_item_relations'
					. ' SET ordering = '. (int) $row->ordering
					. ' WHERE itemid = '. (int) $origin->itemid
					. ' AND catid = ' . $row_ord_catid
					;
				$this->_db->setQuery( $query );  //echo $query."\n";
				$this->_db->query();

				if ( $this->_db->getErrorNum() ) {
					$msg = $this->_db->getErrorMsg();
					$this->setError( $msg );
					return false;
				}

				$query = 'UPDATE #__flexicontent_cats_item_relations'
					. ' SET ordering = '.(int) $origin->ordering
					. ' WHERE itemid = '. (int) $row->itemid
					. ' AND catid = ' . $row_ord_catid
					;
				$this->_db->setQuery( $query );  //echo $query."\n";
				$this->_db->query();

				if ( $this->_db->getErrorNum() ) {
					$msg = $this->_db->getErrorMsg();
					$this->setError( $msg );
					return false;
				}

				$origin->ordering = $row->ordering;
			}
			else
			{
				// NEXT or PREVIOUS item NOT found, raise a notice
				JError::raiseNotice( 500, JText::sprintf('Previous/Next item in category and in STATE group (%s) was not found or has same ordering,
					trying saving ordering to create incrementing ordering numbers for those items that have positive orderings
					NOTE: negative are reserved as "sticky" and are not automatically reordered', $filter_stategrp) );
				return true;
			}
			//exit;
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
	function saveorder($cid = array(), $order, $ord_catid=array(), $prev_order=array())
	{
		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$filter_order_type= $app->getUserStateFromRequest( $option.'.items.filter_order_type',	'filter_order_type', 0, 'int' );
		
		$state_grp_arr   = array(1=>'published', 0=>'unpublished', (FLEXI_J16GE ? 2:-1)=>'archived', -2=>'trashed', -3=>'unpublished', -4=>'unpublished', -5=>'published');
		$state_where_arr = array(
			'published'=>'state IN (1,-5)', 'unpublished'=>'state IN (0,-3,-4)', 'trashed'=>'state = -2',
			'archived'=>'state = '.(FLEXI_J16GE ? 2:-1), ''=> 'state NOT IN ('.(FLEXI_J16GE ? 2:-1).',1,0,-2,-3,-4,-5)'
		);
		
		if (!$filter_order_type)
		{

			$row = JTable::getInstance('flexicontent_items', '');
		
			// Update ordering values
			$altered_catids = array();
			$ord_count = array();
			for( $i=0; $i < count($cid); $i++ )
			{
				$row->load( (int) $cid[$i] );
				$row_stategrp = @ $state_grp_arr[$row->state];
				
				if ($row->ordering != $order[$i]) {
					$altered_catids[$row->catid][$row_stategrp][ FLEXI_J16GE ? $row->language : '_' ] = 1;
					$row->ordering = $order[$i];
					if (!$row->store()) {
						$this->setError($this->_db->getErrorMsg());
						return false;
					}
					
				} else {
					// Detect columns with duplicate orderings, to force reordering them
					$_cid = $row->catid;  $_ord = $row->ordering;

					if ( isset( $ord_count[$_cid][$row_stategrp][ FLEXI_J16GE ? $row->language : '_' ][$_ord] ) ) {
						$altered_catids[$_cid][$row_stategrp][ FLEXI_J16GE ? $row->language : '_' ] = 1;
						$ord_count[$_cid][$row_stategrp][ FLEXI_J16GE ? $row->language : '_' ][$_ord] ++;
					} else {
						$ord_count[$_cid][$row_stategrp][ FLEXI_J16GE ? $row->language : '_' ][$_ord] = 1;
					}
					
				}
				
			}
			
			//echo "<pre>"; print_r($altered_catids);
			
			foreach ($altered_catids as $altered_catid => $state_groups)
			{
				foreach ($state_groups as $state_group => $lang_groups)
				{
					foreach ($lang_groups as $lang_group => $ignore)
					{
						if ( $lang_group != '_' ) {
							$app->enqueueMessage(JText::sprintf('FLEXI_ITEM_REORDER_GROUP_RESULTS_LANG', JText::_('FLEXI_ORDER_JOOMLA'), $state_group, $lang_group, $altered_catid), "message");
							$row->reorder('catid = '.$altered_catid.' AND '.$state_where_arr[$state_group] .' AND language ='. $this->_db->Quote($lang_group));
						} else {
							$app->enqueueMessage(JText::sprintf('FLEXI_ITEM_REORDER_GROUP_RESULTS', JText::_('FLEXI_ORDER_JOOMLA'), $state_group, $altered_catid), "message");
							$row->reorder('catid = '.$altered_catid.' AND '.$state_where_arr[$state_group]);
						}
					}
				}
			}
			
			//exit;
			return true;
		}
		else
		{
			$row = JTable::getInstance('flexicontent_items', '');
			
			// Here goes the second method for saving order.
			// As there is a composite primary key in the relations table we aren't able to use the standard methods from JTable
			$altered_catids = array();
			$ord_count = array();
			for( $i=0; $i < count($cid); $i++ )
			{
				$query 	= 'SELECT rel.itemid, rel.ordering, i.state' . ((FLEXI_FISH || FLEXI_J16GE) ? ' , ie.language' : '')
						. ' FROM #__flexicontent_cats_item_relations AS rel'
						. ((FLEXI_FISH || FLEXI_J16GE) ? ' JOIN #__flexicontent_items_ext AS ie ON rel.itemid=ie.item_id' : '')
						. ' JOIN #__content AS i ON i.id=rel.itemid'
						. ' WHERE rel.ordering >= 0'
						. ' AND rel.itemid = ' . (int)$cid[$i]
						;
				$this->_db->setQuery( $query );  //echo "<pre>". $query."\n";
				$row = $this->_db->loadObject();
				
				if ( $this->_db->getErrorNum() ) {
					$msg = $this->_db->getErrorMsg();
					$this->setError( $msg );
					return false;
				}
				
				$row_stategrp = @ $state_grp_arr[$row->state];
				
				if ($prev_order[$i] != $order[$i]) {
					
					$altered_catids[ (int)$ord_catid[$i] ][$row_stategrp][ (FLEXI_FISH || FLEXI_J16GE) ? $row->language : '_' ] = 1;
					$query = 'UPDATE #__flexicontent_cats_item_relations'
							.' SET ordering=' . (int)$order[$i]
							.' WHERE catid = ' . (int)$ord_catid[$i]
							.' AND itemid = ' . (int)$cid[$i]
							;
					$this->_db->setQuery($query);  //echo "$query <br/>";
					$this->_db->query();
					
					if ( $this->_db->getErrorNum() ) {
						$msg = $this->_db->getErrorMsg();
						$this->setError( $msg );
						return false;
					}
					
				} else {
					// Detect columns with duplicate orderings, to force reordering them
					$_cid = $ord_catid[$i];  $_ord = $prev_order[$i];
					if ( isset( $ord_count[$_cid][$row_stategrp][ (FLEXI_FISH || FLEXI_J16GE) ? $row->language : '_' ][$_ord] ) ) {
						$altered_catids[$_cid][$row_stategrp][ (FLEXI_FISH || FLEXI_J16GE) ? $row->language : '_' ] = 1;
						$ord_count[$_cid][$row_stategrp][ (FLEXI_FISH || FLEXI_J16GE) ? $row->language : '_' ][$_ord] ++;
					} else {
						$ord_count[$_cid][$row_stategrp][ (FLEXI_FISH || FLEXI_J16GE) ? $row->language : '_' ][$_ord] = 1;
					}
				}
			}
			
			//echo "<pre>"; print_r($altered_catids); echo "</pre>";
			
			foreach ($altered_catids as $altered_catid => $state_groups)
			{
				foreach ($state_groups as $state_group => $lang_groups)
				{
					foreach ($lang_groups as $lang_group => $ignore)
					{
						// Specific reorder procedure because the relations table has a composite primary key 
						$query 	= 'SELECT rel.itemid, rel.ordering, state'
								. ' FROM #__flexicontent_cats_item_relations AS rel'
								. ((FLEXI_FISH || FLEXI_J16GE) ? ' JOIN #__flexicontent_items_ext AS ie ON rel.itemid=ie.item_id' : '')
								. ' JOIN #__content AS i ON i.id=rel.itemid'
								. ' WHERE rel.ordering >= 0'
								. ' AND rel.catid = '. $altered_catid .' AND '. $state_where_arr[$state_group] . ( $lang_group != '_' ? ' AND ie.language ='. $this->_db->Quote($lang_group) : '')
								. ' ORDER BY rel.ordering'
								;
						$this->_db->setQuery( $query );  //echo "$query <br/>";
						$rows = $this->_db->loadObjectList();
						
						if ( $this->_db->getErrorNum() ) {
							$msg = $this->_db->getErrorMsg();
							$this->setError( $msg );
							return false;
						}
						
						// Compact the ordering numbers
						$cnt = 0;
						foreach ($rows as $row)
						{
							if ($row->ordering >= 0)
							{
								if ($row->ordering != $i+1)
								{
									$row->ordering = $cnt++;
									$query 	= 'UPDATE #__flexicontent_cats_item_relations'
											. ' SET ordering = '. (int) $row->ordering
											. ' WHERE itemid = '. (int) $row->itemid
											. ' AND catid = '. $altered_catid
											;
									$this->_db->setQuery( $query);  //echo "$query <br/>";
									$this->_db->query();
									
									if ( $this->_db->getErrorNum() ) {
										$msg = $this->_db->getErrorMsg();
										$this->setError( $msg );
										return false;
									}
								}
							}
						}
						if ( $lang_group != '_' )
							$app->enqueueMessage(JText::sprintf('FLEXI_ITEM_REORDER_GROUP_RESULTS_LANG', JText::_('FLEXI_ORDER_FLEXICONTENT'), $state_group, $lang_group, $altered_catid), "message");
						else
							$app->enqueueMessage(JText::sprintf('FLEXI_ITEM_REORDER_GROUP_RESULTS', JText::_('FLEXI_ORDER_FLEXICONTENT'), $state_group, $altered_catid), "message");
					}
				}
			}
			
			//exit;
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
		if (FLEXI_J16GE) {
			$dispatcher = JDispatcher::getInstance();  // Get event dispatcher and load all content plugins for triggering their delete events
			JPluginHelper::importPlugin('content');      // Load all content plugins for triggering their delete events
			$item_arr = array();                         // We need an array of items to use for calling the 'onContentAfterDelete' Event
		}
		
		if ( !count( $cid ) ) return false;
		
		$cids = implode( ',', $cid );
		
		if ($itemmodel)
		{
			foreach ($cid as $item_id)
			{
				$item = $itemmodel->getItem($item_id);
				
				// *****************************************************************
				// Trigger Event 'onContentBeforeDelete' of Joomla's Content plugins
				// *****************************************************************
				if (FLEXI_J16GE) {
					$event_before_delete = 'onContentBeforeDelete';  // NOTE: $itemmodel->event_before_delete is protected property
					$dispatcher->trigger($event_before_delete, array('com_content.article', $item));
					$item_arr[] = clone($item);  // store object so that we can call after delete event
				}
				
				// **********************************************************************************
				// Trigger onBeforeDeleteField field event to allow fields to cleanup any custom data
				// **********************************************************************************
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
			$assetids = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
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
		
		
		// **************************
		// Remove temporary item data
		// **************************
		$query = 'DELETE FROM #__flexicontent_items_tmp'
				. ' WHERE id IN ('. $cids .')'
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
		
		
		// **********************************
		// Delete favoured record of the item
		// **********************************
		$query = 'DELETE FROM #__flexicontent_favourites'
				. ' WHERE itemid IN ('. $cids .')'
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
		
		
		// ****************************************************************
		// Trigger Event 'onContentAfterDelete' of Joomla's Content plugins
		// ****************************************************************
		if (FLEXI_J16GE) {
			$event_after_delete = 'onContentAfterDelete';  // NOTE: $itemmodel->event_after_delete is protected property
			foreach($item_arr as $item) {
				$dispatcher->trigger($event_after_delete, array('com_content.article', $item));
			}
		}
		
		return true;
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
		$row = JTable::getInstance('flexicontent_items', '');

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
		return true;
	}

	/**
	 * Method to fetch the assigned categories
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	function getItemCats($catids=null)
	{
		if ($this->_cats !== null) return $this->_cats;
		
		if (empty($catids)) $catids = $this->_catids;
		if (empty($catids)) return array();
		
		$query = 'SELECT DISTINCT c.id, c.title'
				. ' FROM #__categories AS c'
			//. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
			//. ' WHERE rel.itemid = '.(int)$id
				. ' WHERE c.id IN ('. implode(',', $catids) .') ' 
				. (FLEXI_J16GE ? ' AND c.extension="'.FLEXI_CAT_EXTENSION.'"' : '')
				;
		$this->_db->setQuery( $query );
		$this->_cats = $this->_db->loadObjectList('id');
		//print_r($this->_cats);
		
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
	 * this is UNUSED in J2.5+, it may be used in the future
	 * 
	 * @return boolean true on success
	 * @since 1.5
	 */
	 
	function import()
	{
		jimport('joomla.utilities.simplexml');  // Deprecated J2.5, removed J3.x
		// Get the site default language
		$lang = flexicontent_html::getSiteDefaultLang();
		
		if (!FLEXI_J16GE) {
			// Get all Joomla sections
			$query = 'SELECT * FROM #__sections';
			$this->_db->setQuery($query);
			$sections = $this->_db->loadObjectList();
		}
		
		$logs = new stdClass();
		if (!FLEXI_J16GE) $logs->sec = 0;
		$logs->cat = 0;
		$logs->art = 0;
		//$logs->err = new stdClass();
		
		// Create the new section for flexicontent items
		$flexisection = JTable::getInstance('section');
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
		$catparams = FLEXI_J16GE ? new JRegistry() : new JParameter("");
		
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
			$cat = JTable::getInstance('flexicontent_categories','');
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
			$query = "SELECT * FROM #__categories WHERE section = " . $section->id;
			$this->_db->setQuery($query);
			$categories = $this->_db->loadObjectList();
			
			// Loop throught the categories of the created section
			foreach ($categories as $category)
			{
				$subcat = JTable::getInstance('flexicontent_categories','');
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
					$item = JTable::getInstance('content');
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
		$fparams = JComponentHelper::getParams('com_flexicontent');
		$fparams->set('flexi_section', $flexisection->id);
		$fparams_str = $fparams->toString();
		
		$flexi = JComponentHelper::getComponent('com_flexicontent');
		$query = 'UPDATE '. (FLEXI_J16GE ? '#__extensions' : '#__components')
			. ' SET params = ' . $this->_db->Quote($fparams_str)
			. ' WHERE '. (FLEXI_J16GE ? 'extension_id' : 'id') .'='. $flexi->id
			;
		$this->_db->setQuery($query);
		$this->_db->query();
		return $logs;
	}
	
	
	/**
	 * Method to get a list of items (ids) that have value for the given fields
	 * 
	 * @since 1.5
	 */
	function getFieldsItems($fields) {
		if ( !count($fields) ) return array();
		
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
			return FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
		}
		
		// Find item having tags
		$items_with_tags = array();
		if ( !empty($get_items_with_tags) ) {
			$query  = 'SELECT DISTINCT itemid FROM #__flexicontent_tags_item_relations';
			$this->_db->setQuery($query);
			$items_with_tags = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
		}
		
		// Find items having values for non core fields
		$items_with_noncore = array();
		if (count($non_core_fields)) {
			$non_core_fields_list = "'".implode("','", $non_core_fields)."'";
			$query = "SELECT DISTINCT firel.item_id FROM #__flexicontent_fields_item_relations as firel"
				." JOIN #__content as a ON firel.item_id=a.id "
				." WHERE firel.field_id IN ({$non_core_fields_list}) "
				." AND firel.value<>'' "
				// NOTE: Must include all items regardless of state to avoid problems when
				// (a) item changes state and (b) to allow priveleged users to search any item
				//."  AND a.state IN (1, -5)"
			;
			//echo $query;
			$this->_db->setQuery($query);
			$items_with_noncore = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
		}
		
		$item_list = array_merge($items_with_tags,$items_with_noncore);
		//echo count($item_list);
		
		// NOTE: array_unique() creates gaps in the index of the array,
		// and if passed to json_encode it will output object !!! so we use array_values()
		return array_values(array_unique($item_list));
	}
	
	
	/**
	 * Method to get an array of DB file data for the given file ids
	 * 
	 * @since 1.5
	 */
	function getFileData($fileids) {
		$query = 'SELECT * '
			. ' FROM #__flexicontent_files AS f'
			. ' WHERE f.id IN ('.implode(',', $fileids).')'
			;
		$this->_db->setQuery($query);
		$filedata= $this->_db->loadObjectList();
		return $filedata;
	}
}
?>
