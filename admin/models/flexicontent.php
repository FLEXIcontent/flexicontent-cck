<?php
/**
 * @version 1.5 stable $Id: flexicontent.php 1910 2014-06-08 17:48:19Z ggppdk $
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
if (FLEXI_J16GE) {
	jimport('joomla.access.rules');
}

/**
 * FLEXIcontent Component Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelFlexicontent extends JModelLegacy
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Method to get items in pending state, waiting for publication approval
	 *
	 * @access public
	 * @return array
	 */
	function getPending()
	{
		$user = JFactory::getUser();
		$permission = FlexicontentHelperPerm::getPerm();
		$allitems	= $permission->DisplayAllItems;
		
		$use_tmp = true;
		$query_select_ids = 'SELECT c.id'; //SQL_CALC_FOUND_ROWS
		$query_select_data = 'SELECT c.id, c.title, c.catid, c.created, cr.name as creator, c.created_by, c.modified, c.modified_by, mr.name as modifier';
		
		$query_from_join = ''
				. ' FROM '.($use_tmp ? '#__flexicontent_items_tmp' : '#__content').' as c'
				. ' LEFT JOIN #__users AS cr ON cr.id = c.created_by'
				. ' LEFT JOIN #__users AS mr ON mr.id = c.modified_by'
				;
		$query_where_orderby_having = ''
				. ' WHERE state = -3'
				. (!FLEXI_J16GE ? ' AND c.sectionid = ' . (int)FLEXI_SECTION : '')
				. ($allitems ? '' : ' AND c.created_by = '.$user->id)
				. ' ORDER BY c.created DESC'
				;
		
		$query = $query_select_ids . $query_from_join . $query_where_orderby_having;
		$this->_db->SetQuery($query, 0, 5);
		$ids = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
		
		$genstats = array();
		if ( !empty($ids) ) {
			$query = $query_select_data . $query_from_join . ' WHERE c.id IN ('.implode(',',$ids).')';
			$this->_db->SetQuery($query, 0, 5);
			$genstats = $this->_db->loadObjectList();
		}
		return $genstats;
	}
	
	/**
	 * Method to get items revised, having unapproved version, waiting to be reviewed and approved
	 *
	 * @access public
	 * @return array
	 */
	function getRevised()
	{
		$user = JFactory::getUser();
		$permission = FlexicontentHelperPerm::getPerm();
		$allitems	= $permission->DisplayAllItems;
		
		$use_tmp = true;
		$query_select_ids = 'SELECT c.id'.', c.version, MAX(fv.version_id)'; //SQL_CALC_FOUND_ROWS
		$query_select_data = 'SELECT c.id, c.title, c.catid, c.created, cr.name as creator, c.created_by, c.modified, c.modified_by, mr.name as modifier'.', c.version, MAX(fv.version_id)';
		
		$query_from_join = ''
				. ' FROM '.($use_tmp ? '#__flexicontent_items_tmp' : '#__content').' as c'
				. ' LEFT JOIN #__flexicontent_versions AS fv ON c.id=fv.item_id'
				. ' LEFT JOIN #__users AS cr ON cr.id = c.created_by'
				. ' LEFT JOIN #__users AS mr ON mr.id = c.modified_by'
				;
		$query_where_orderby_having = ''
				. ' WHERE c.state = -5 OR c.state = 1'
				. (!FLEXI_J16GE ? ' AND c.sectionid = ' . (int)FLEXI_SECTION : '')
				. ($allitems ? '' : ' AND c.created_by = '.$user->id)
				. ' GROUP BY fv.item_id '
				. ' HAVING c.version<>MAX(fv.version_id) '
				. ' ORDER BY c.modified DESC'
				;
		
		$query = $query_select_ids . $query_from_join . $query_where_orderby_having;
		$this->_db->SetQuery($query, 0, 5);
		$ids = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
		
		$genstats = array();
		if ( !empty($ids) ) {
			$query = $query_select_data . $query_from_join . ' WHERE c.id IN ('.implode(',',$ids).')';
			$this->_db->SetQuery($query, 0, 5);
			$genstats = $this->_db->loadObjectList();
		}
		return $genstats;
	}
	
	/**
	 * Method to get items in draft state, waiting to be written (and published)
	 *
	 * @access public
	 * @return array
	 */
	function getDraft()
	{
		$user = JFactory::getUser();
		$permission = FlexicontentHelperPerm::getPerm();
		$allitems	= $permission->DisplayAllItems;
		$requestApproval = @ $permission->RequestApproval;
		
		$use_tmp = true;
		$query_select_ids = 'SELECT c.id'; //SQL_CALC_FOUND_ROWS
		$query_select_data = 'SELECT c.id, c.title, c.catid, c.created, cr.name as creator, c.created_by, c.modified, c.modified_by, mr.name as modifier';
		
		$query_from_join = ''
				. ' FROM '.($use_tmp ? '#__flexicontent_items_tmp' : '#__content').' as c'
				. ' LEFT JOIN #__users AS cr ON cr.id = c.created_by'
				. ' LEFT JOIN #__users AS mr ON mr.id = c.modified_by'
				;
		$query_where_orderby_having = ''
				. ' WHERE state = -4'
				. (!FLEXI_J16GE ? ' AND c.sectionid = ' . (int)FLEXI_SECTION : '')
				. (($allitems || $requestApproval) ? '' : ' AND c.created_by = '.$user->id)
				. ' ORDER BY c.created DESC'
				;
		
		$query = $query_select_ids . $query_from_join . $query_where_orderby_having;
		$this->_db->SetQuery($query, 0, 5);
		$ids = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
		
		$genstats = array();
		if ( !empty($ids) ) {
			$query = $query_select_data . $query_from_join . ' WHERE c.id IN ('.implode(',',$ids).')';
			$this->_db->SetQuery($query, 0, 5);
			$genstats = $this->_db->loadObjectList();
		}
		return $genstats;
	}

	/**
	 * Method to get items in progress state, (published but) waiting to be completed
	 *
	 * @access public
	 * @return array
	 */
	function getInprogress()
	{
		$user = JFactory::getUser();
		$permission = FlexicontentHelperPerm::getPerm();
		$allitems	= $permission->DisplayAllItems;
		
		$use_tmp = true;
		$query_select_ids = 'SELECT c.id'; //SQL_CALC_FOUND_ROWS
		$query_select_data = 'SELECT c.id, c.title, c.catid, c.created, cr.name as creator, c.created_by, c.modified, c.modified_by, mr.name as modifier';
		
		$query_from_join = ''
				. ' FROM '.($use_tmp ? '#__flexicontent_items_tmp' : '#__content').' as c'
				. ' LEFT JOIN #__users AS cr ON cr.id = c.created_by'
				. ' LEFT JOIN #__users AS mr ON mr.id = c.modified_by'
				;
		$query_where_orderby_having = ''
				. ' WHERE c.state = -5'
				. (!FLEXI_J16GE ? ' AND c.sectionid = ' . (int)FLEXI_SECTION : '')
				. ($allitems ? '' : ' AND c.created_by = '.$user->id)
				. ' ORDER BY c.created DESC'
				;
		
		$query = $query_select_ids . $query_from_join . $query_where_orderby_having;
		$this->_db->SetQuery($query, 0, 5);
		$ids = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
		
		$genstats = array();
		if ( !empty($ids) ) {
			$query = $query_select_data . $query_from_join . ' WHERE c.id IN ('.implode(',',$ids).')';
			$this->_db->SetQuery($query, 0, 5);
			$genstats = $this->_db->loadObjectList();
		}
		return $genstats;
	}
	
	
	/**
	 * Method to check if default Flexi Menu Items exist
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistMenuItems()
	{
		static $return;
		if ($return !== null) return $return;
		$return = false;
		
		$app = JFactory::getApplication();
		
		// Get 'default_menu_itemid' parameter
		$params = JComponentHelper::getParams('com_flexicontent');
		$default_menu_itemid = $params->get('default_menu_itemid', false);
		
		// Load the default menu item
		$menus = $app->getMenu('site', array());
		$menu = $menus->getItem($default_menu_itemid);
		
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		$prompt  = '<br>'.JText::_('FLEXI_DEFAULT_MENU_ITEM_PROMPT');
		
		// Check menu item exists
		$config_saved = (bool) $params->get('flexi_cat_extension');
		if ( !$menu ) {
			if ( $config_saved ) {
				$app->enqueueMessage( JText::_('FLEXI_DEFAULT_MENU_ITEM_MISSING_DISABLED').$prompt, 'notice' );
			}
			return $return = false;
		}
		// Check pointing to FLEXIcontent
		if ( @$menu->query['option']!='com_flexicontent' ) {
			$app->enqueueMessage( JText::_('FLEXI_DEFAULT_MENU_ITEM_ISNON_FLEXI').$prompt, 'notice' );
			return $return = false;
		}
		// Check public access level
		if ( $menu->access!=$public_acclevel ) {
			$app->enqueueMessage( JText::_('FLEXI_DEFAULT_MENU_ITEM_ISNON_PUBLIC').$prompt, 'notice' );
			return $return = false;
		}
		
		// Verify that language of menu item is not empty
		if ( FLEXI_J16GE && $menu->language=='' ) {
			$query 	=	"UPDATE #__menu SET `language`='*' WHERE id=". (int)$default_menu_itemid;
			$this->_db->setQuery($query);
			$result = $this->_db->query();
		}
		
		// All checks passed
		return $return = true;
	}
	
	/**
	 * Method to check if there is at least one type created
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistType() {
		static $return;
		if ($return !== NULL) return $return;
		$return = false;
		
		$query = 'SELECT COUNT( id )'
			. ' FROM #__flexicontent_types'
			;
		$this->_db->setQuery( $query );
		$count = $this->_db->loadResult();
		$return = $count > 0;
		
		return $return;
	}

	/**
	 * Method to check if there is at least the default fields in the FLEXIcontent fields TABLE
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistFields()
	{
		static $return;
		if ($return !== NULL) return $return;
		$return = false;
		
		$query = 'SELECT COUNT( id )'
			. ' FROM #__flexicontent_fields'
			;
		$this->_db->setQuery( $query );
		$count = $this->_db->loadResult();
		$return = $count > 13;
		
		return $return;
	}

	/**
	 * Method to check if there is at least the default flexicontent_fields PLUGINs
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistFieldsPlugins()
	{
		static $return;
		if ($return !== NULL) return $return;
		$return = false;
		
		$query = 'SELECT COUNT( extension_id )'
			. ' FROM #__extensions'
			. ' WHERE `type`= '.$this->_db->Quote('plugin').' AND folder = ' . $this->_db->Quote('flexicontent_fields')
			;
		$this->_db->setQuery( $query );
		$count = $this->_db->loadResult();
		$return = $count > 13;
		
		return $return;
	}

	/**
	 * Method to check if the search plugin is installed
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistSearchPlugin()
	{
		static $return;
		if ($return !== NULL) return $return;
		$return = false;
		
		$query = 'SELECT COUNT( extension_id )'
		. ' FROM #__extensions'
		. ' WHERE `type`='.$this->_db->Quote('plugin').' AND element = ' . $this->_db->Quote('flexisearch')
		;
		$this->_db->setQuery( $query );
		$return = $this->_db->loadResult() ? true : false;
		
		return $return;
	}

	/**
	 * Method to check if the system plugin is installed
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistSystemPlugin()
	{
		static $return;
		if ($return !== NULL) return $return;
		$return = false;
		
		$query = 'SELECT COUNT( extension_id )'
		. ' FROM #__extensions'
		. ' WHERE `type`='.$this->_db->Quote('plugin').' AND element = ' . $this->_db->Quote('flexisystem')
		;
		$this->_db->setQuery( $query );
		$return = $this->_db->loadResult() ? true : false;
		
		return $return;
	}

	/**
	 * Method to check if all plugins are published
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getAllPluginsPublished()
	{
		static $return;
		if ($return !== NULL) return $return;
		$return = false;
		
		// Make sure basic CORE fields are published
		$q = 'UPDATE #__flexicontent_fields SET published=1 WHERE id > 0 AND id < 7';
		$this->_db->setQuery( $q );
		$this->_db->query();
		
		$tbl   = FLEXI_J16GE ? '#__extensions' : '#__plugins';
		$idcol = FLEXI_J16GE ? 'extension_id' : 'id';
		$stcol = FLEXI_J16GE ? 'enabled' : 'published';
		$query 	= 'SELECT COUNT( '.$idcol.' )'
			. ' FROM '.$tbl
			. ' WHERE 1 '. (FLEXI_J16GE ? ' AND `type`=' . $this->_db->Quote('plugin') : '')
			. ' AND ( folder = ' . $this->_db->Quote('flexicontent_fields')
			. ' OR element = ' . $this->_db->Quote('flexisystem')
			. ' OR element = ' . $this->_db->Quote('flexiadvroute')
			//. ' OR element = ' . $this->_db->Quote('flexiadvsearch')
			//. ' OR element = ' . $this->_db->Quote('flexisearch')
			. ')'
			. ' AND '.$stcol.' <> 1'
			;
		$this->_db->setQuery( $query );
		$return = $this->_db->loadResult() ? false : true;
		
		return $return;
	}

	/**
	 * Method to check if the language column exists
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistLanguageColumns()
	{
		static $return;
		if ($return !== NULL) return $return;
		$return = false;
		
		$columns = $this->_db->getTableColumns('#__flexicontent_items_ext');
		$result_lang_col = array_key_exists('language', $columns) ? true : false;
		$result_tgrp_col = array_key_exists('lang_parent_id', $columns) ? true : false;
		
		$return = $result_lang_col && $result_tgrp_col;
		
		return $return;
	}
	
	/**
	 * Method to get if main category of items exists in both category table and in flexicontent category-items relation table
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function getItemsNoCat()
	{
		static $return;
		if ($return !== NULL) return $return;
		$return = false;
		
		$query = "SELECT rel.itemid"
			." FROM #__flexicontent_cats_item_relations AS rel"
			." LEFT JOIN #__content AS i ON i.id = rel.itemid"
			." WHERE i.id IS NULL"
			." LIMIT 1";
		$this->_db->setQuery($query);
		$item_id = $this->_db->loadResult();
		if ($item_id) return $return = true;
		
		$query = "SELECT i.id"
			." FROM #__content AS i"
			." JOIN #__flexicontent_items_ext as ie ON i.id=ie.item_id "
			." LEFT JOIN #__flexicontent_cats_item_relations as rel ON rel.catid=i.catid AND i.id=rel.itemid "
			." WHERE rel.catid IS NULL"
			." LIMIT 1";
		$this->_db->setQuery($query);
		$item_id = $this->_db->loadResult();
		if ($item_id) return $return = true;
		
		return $return;
	}
	
	
	/**
	 * Method to get if language of items is initialized properly
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function getItemsNoLang()
	{
		static $return;
		if ($return !== NULL) return $return;
		$return = false;
		
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$enable_translation_groups = flexicontent_db::useAssociations(); //$cparams->get("enable_translation_groups");
		
		// Check for emtpy language in flexicontent EXT table
		$query = "SELECT COUNT(*)"
			." FROM #__flexicontent_items_ext as ie"
			." WHERE ie.language='' "
			." LIMIT 1";
		$this->_db->setQuery($query);
		$cnt = $this->_db->loadResult();
		if ($cnt) return $return = true;
		
		$query = "SELECT COUNT(*)"  // Check for emtpy language in Joomla content EXT table
			." FROM #__content as i"
			." WHERE i.language=''"
			." LIMIT 1";
		$this->_db->setQuery($query);
		$cnt = $this->_db->loadResult();
		if ($cnt) return $return = true;
		
		$query = "SELECT COUNT(*)"
			." FROM #__content as i"
			." JOIN #__flexicontent_items_ext as ie ON i.id=ie.item_id "
			." WHERE i.language<>ie.language"
			." LIMIT 1";
		$this->_db->setQuery($query);
		$cnt = $this->_db->loadResult();
		if ($cnt) return $return = true;
		
		// Check for not yet transfered language associations
		$query = "SELECT COUNT(*)"
			." FROM #__flexicontent_items_ext as ie"
			." WHERE ie.lang_parent_id <> 0"
			." LIMIT 1";
		$this->_db->setQuery($query);
		$cnt = $this->_db->loadResult();
		if ($cnt) return $return = true;
		
		return $return;
	}
	
	
	/**
	 * Method to get if temporary item data table is up-to-date
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function getItemCountingDataOK()
	{
		static $return;
		if ($return !== NULL) return $return;
		$return = false;
		
		// Clear orphan data from the EXTENDED data tables
		$query = "DELETE d.* FROM #__flexicontent_items_tmp AS d"
			." LEFT JOIN #__content AS c ON d.id = c.id"
			." WHERE c.id IS NULL";
		$this->_db->setQuery($query);
		$this->_db->query();
		
		// Clear orphan data from the TMP data tables
		$query = "DELETE d.* FROM #__flexicontent_items_ext AS d"
			." LEFT JOIN #__content AS c ON d.item_id = c.id"
			." WHERE c.id IS NULL";
		$this->_db->setQuery($query);
		$this->_db->query();

		// Find columns cached
		$cache_tbl = "#__flexicontent_items_tmp";
		$tbls = array($cache_tbl);
		foreach ($tbls as $tbl) $tbl_fields[$tbl] = $this->_db->getTableColumns($tbl);
		
		// Get the column names
		$tbl_fields = array_keys($tbl_fields[$cache_tbl]);
		
		$subquery = " SELECT COUNT(*) "
			." FROM `#__flexicontent_items_ext` AS ie"
			." JOIN `#__content` AS i ON ie.item_id=i.id"
			." WHERE 1 ".(!FLEXI_J16GE ? ' AND i.sectionid = ' . (int)FLEXI_SECTION : '')
			;
		$query = "SELECT COUNT(*) AS total1, (".$subquery.") AS total2"
			." FROM `#__content` AS i"
			." JOIN `#__flexicontent_items_tmp` AS ca ON i.id=ca.id"
			." JOIN `#__flexicontent_items_ext` AS ie ON ie.item_id=i.id"
			." WHERE 1 " . (!FLEXI_J16GE ? ' AND i.sectionid = ' . (int)FLEXI_SECTION : '')
			;
		foreach ($tbl_fields as $col_name) {
			if ($col_name == "id" || $col_name == "hits")
				continue;
			else if ( $col_name=='type_id' || $col_name=='lang_parent_id')
				$query .= " AND ie.`".$col_name."`=ca.`".$col_name."`";
			else
				$query .= " AND i.`".$col_name."`=ca.`".$col_name."`";
		}
		
		// Check missing in item counting table
		$this->_db->setQuery($query);
		$res = $this->_db->loadObject();
		if ($this->_db->getErrorNum()) echo $this->_db->getErrorMsg();
		
		$return = $res->total1 == $res->total2;
		return $return;
	}
	
	
	/**
	 * Method to check if the versions table is created
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistVersionsTable()
	{
		static $return;
		if ($return === NULL) {
			$query = 'SHOW TABLES LIKE ' . $this->_db->Quote('%flexicontent_versions');
			$this->_db->setQuery($query);
			$return = $this->_db->loadResult() ? true : false;
		}
		return $return;
	}

	/**
	 * Method to check if the versions table is created
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistAuthorsTable()
	{
		static $return;
		if ($return === NULL) {
			$query = 'SHOW TABLES LIKE ' . $this->_db->Quote('%flexicontent_authors_ext');
			$this->_db->setQuery($query);
			$return = $this->_db->loadResult() ? true : false;
		}
		return $return;
	}
	
	
	/**
	 * Method to check if the versions table is created
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistDBindexes($check_only=true)
	{
		static $missing;
		if ($missing !== NULL) return $check_only ? empty($missing) : $missing;
		
		jimport('joomla.filesystem.file');
		$app = JFactory::getApplication();
		$dbprefix = $app->getCfg('dbprefix');
		$dbname = $app->getCfg('db');
		
		$tblname_indexnames = array(
			'flexicontent_items_ext'=>array('lang_parent_id'=>0, 'type_id'=>0),
			'flexicontent_items_tmp'=>array('state'=>0, 'catid'=>0, 'created_by'=>0, 'access'=>0, 'featured'=>0, 'language'=>0, 'type_id'=>0, 'lang_parent_id'=>0),
			'flexicontent_fields_item_relations'=>array(
				'value'=>32,
				'PRIMARY'=>array(
					'custom_drop'=>'DROP PRIMARY KEY',
					'custom_add'=>'ADD PRIMARY KEY',
					'cols'=>array('field_id'=>0, 'item_id'=>0, 'valueorder'=>0, 'suborder'=>0)
				)
			),
			'flexicontent_items_versions'=>array(
				'value'=>32,
				'PRIMARY'=>array(
					'custom_drop'=>'DROP PRIMARY KEY',
					'custom_add'=>'ADD PRIMARY KEY',
					'cols'=>array('version'=>0, 'field_id'=>0, 'item_id'=>0, 'valueorder'=>0, 'suborder'=>0)
				)
			),
			'flexicontent_download_history'=>array('user_id'=>0, 'file_id'=>0),
			'flexicontent_download_coupons'=>array('user_id'=>0, 'file_id'=>0, 'token'=>0, 'expire_on'=>0),
			'flexicontent_templates'=>array(
				'PRIMARY'=>array(
					'custom_drop'=>'DROP PRIMARY KEY',
					'custom_add'=>'ADD PRIMARY KEY',
					'cols'=>array('template'=>0, 'cfgname'=>0, 'layout'=>0, 'position'=>0)
				)
			),
			'flexicontent_favourites'=>array(
				'PRIMARY'=>array(
					'custom_drop'=>'DROP PRIMARY KEY',
					'custom_add'=>'ADD PRIMARY KEY',
					'cols'=>array('id'=>0,'itemid'=>0, 'userid'=>0, 'type'=>0)
				)
			)
		);
		
		$missing = array();
		$all_started = true;
		foreach($tblname_indexnames as $tblname => $indexnames) {
			$indexing_started = false;
			$file = JPATH_SITE.DS.'tmp'.DS.'tbl_indexes_'.$tblname;
			if ( JFile::exists($file) ) {
				$indexing_start_secs = (int)JFile::read($file);
				$indexing_started = $indexing_start_secs + 3600 > time();
				if (!$indexing_started) {
					JFile::delete($file);
				}
			}
			
			foreach($indexnames as $indexname => $iconf) {
				$query = "SELECT COUNT(1) AS IndexIsThere "
					." FROM INFORMATION_SCHEMA.STATISTICS"
					." WHERE table_schema='".$dbname."' AND table_name='".$dbprefix.$tblname."' AND index_name='".$indexname."'"
					.(is_array($iconf) && !empty($iconf['cols'])  ? 
						" AND COLUMN_NAME IN ('".implode("','", array_keys($iconf['cols']))."')".
						" HAVING IndexIsThere = ".count($iconf['cols'])
					: "");
				//if (is_array($iconf) && !empty($iconf['cols'])) echo $query ."<br/>";
				$this->_db->setQuery($query);
				$exists = $this->_db->loadResult();
				if ($indexing_started) {
					if ($exists) {
						JFile::delete($file);
						continue;
					}
					else $missing[$tblname]['__indexing_started__'] = 1;
				} else if (!$exists) {
					$all_started = false;
					$missing[$tblname][$indexname] = $iconf;
				}
			}
		}
		
		if ($all_started) $missing = array();  // Indexing for all tables has started, clear the 'missing' array ... so that post-installation task will not appear
		return $check_only ? empty($missing) : $missing;
	}
	
	
	/**
	 * Method to check if the system plugin is installed
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistVersionsPopulated() {
		static $return;
		if ($return === NULL) {
			$query 	= 'SELECT COUNT( version )'
					. ' FROM #__flexicontent_items_versions'
					;
			$this->_db->setQuery( $query );
			if(!$this->_db->loadResult()) {
				$return = true;
			} else {
				$query 	= 'SELECT COUNT( id )'
						. ' FROM #__flexicontent_versions'
						;
				$this->_db->setQuery( $query );
				$return = $this->_db->loadResult() ? true : false;
			}
		}
		return $return;
	}

	/**
	 * Method to check if the permissions of Phpthumb cache folder
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getCacheThumbChmod()
	{
		static $return;
		if ($return!==null) return $return;
		
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.jpath');
		
		$phpthumbcache 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'cache');
		
		// CHECK phpThumb cache exists and create the folder
		if ( !JFolder::exists($phpthumbcache) && !JFolder::create($phpthumbcache) ) {
			JError::raiseWarning(100, 'Error: Unable to create phpThumb folder: '. $phpthumbcache .' image thumbnail will not work properly' );
			$return = true;  // Cancel task !! to allow user to continue
			return;
		}
		
		// CHECK phpThumb cache permissions
		$perms = JPath::getPermissions($phpthumbcache);
		$return = preg_match('/rwx....../i', $perms) ? true : false;  //'/rwxr.xr.x/i'
		if ( $return && preg_match('/....w..w./i', $perms) ) {
			JPath::setPermissions($phpthumbcache, '0600', '0700');
		}
		// If permissions not good check if we can change them
		if ( !$return && !JPath::canChmod($phpthumbcache) ) {
			JError::raiseWarning(100, 'Error: Unable to change phpThumb folder permissions: '. $phpthumbcache .' there maybe a wrong owner of the folder. Correct permissions are important for proper thumbnails and for -security-' );
			$return = true;  // Cancel task !! to allow user to continue
			return;
		}
		
		return $return;
	}

	/**
	 * Method to check if the files from beta3 still exist in the category and item view
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getDeprecatedFiles(&$deprecated=null)
	{
		$deprecated = array();
		return true;  // Do not execute
		
		static $return;
		if ($return===true) return $return;
		
		jimport('joomla.filesystem.folder');
		$files 	= array (
			'author.xml',
			'author.php',
			'myitems.xml',
			'myitems.php',
			'mcats.xml',
			'mcats.php',
			'default.xml',
			'default.php',
			'tags.xml',
			'tags.php',
			'favs.xml',
			'favs.php',
			'index.html'
			);
		$catdir 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'category'.DS.'tmpl');
		$cattmpl 	= JFolder::files($catdir);		
		$ctmpl 		= array_diff($cattmpl,$files);
		foreach ($ctmpl as $c) {
			$deprecated[$catdir][] = $c;
		}
		
		$files 	= array (
			'default.xml',
			'default.php',
			'form.php',
			'form.xml',
			'index.html'
			);
		$itemdir 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.FLEXI_ITEMVIEW.DS.'tmpl');
		$itemtmpl	= JFolder::files($itemdir);		
		$itmpl 		= array_diff($itemtmpl,$files);
		foreach ($itmpl as $c) {
			$deprecated[$itemdir][] = $c;
		}
		
		$return = count($deprecated) ? false : true;
		return $return;
	}

	/**
	 * Method to check if the field positions were converted
	 * and if not, convert them
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function convertOldFieldsPositions()
	{
		static $return;
		if ($return!==null) return $return;
		$return = true;  // only call once
		
		$query 	= "SELECT name, positions"
				. " FROM #__flexicontent_fields"
				. " WHERE positions <> ''"
				;
		$this->_db->setQuery( $query );
		$fields = $this->_db->loadObjectList();
		
		if (empty($fields)) return $return;
		
		// create a temporary table to store the positions
		$this->_db->setQuery( "DROP TABLE IF EXISTS #__flexicontent_positions_tmp" );
		$this->_db->query();
		$query = "
				CREATE TABLE #__flexicontent_positions_tmp (
				  `field` varchar(100) NOT NULL default '',
				  `view` varchar(30) NOT NULL default '',
				  `folder` varchar(100) NOT NULL default '',
				  `position` varchar(255) NOT NULL default ''
				) ENGINE=MyISAM DEFAULT CHARSET=utf8
				";
		$this->_db->setQuery( $query );
		$this->_db->query();

		foreach ($fields as $field) {			
			$field->positions = explode("\n", $field->positions);	
			foreach ($field->positions as $pos) {
				$pos = explode('.', $pos);
				$query = 'INSERT INTO #__flexicontent_positions_tmp (`field`, `view`, `folder`, `position`) VALUES(' . $this->_db->Quote($field->name) . ',' . $this->_db->Quote($pos[1]) . ',' . $this->_db->Quote($pos[2]) . ',' . $this->_db->Quote($pos[0]) . ')';
				$this->_db->setQuery($query);
				$this->_db->query();
			}
		}

		$templates	= flexicontent_tmpl::getTemplates();
		$folders 	= flexicontent_tmpl::getThemes();
		$views		= array('items', 'category');
		
		foreach ($folders as $folder) {
			foreach ($views as $view) {
				$groups = @$templates->{$view}->{$folder}->positions;
				if ($groups) {
					foreach ($groups as $group) {
						$query 	= 'SELECT field'
								. ' FROM #__flexicontent_positions_tmp'
								. ' WHERE view = ' . $this->_db->Quote($view)
								. ' AND folder = ' . $this->_db->Quote($folder)
								. ' AND position = ' . $this->_db->Quote($group)
								;
						$this->_db->setQuery( $query );
						$fieldstopos = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
						
						if ($fieldstopos) {
							$field = implode(',', $fieldstopos);

							$query = 'INSERT INTO #__flexicontent_templates (`template`, `layout`, `position`, `fields`) VALUES(' . $this->_db->Quote($folder) . ',' . $this->_db->Quote($view) . ',' . $this->_db->Quote($group) . ',' . $this->_db->Quote($field) . ')';
							$this->_db->setQuery($query);
							// By catching SQL error (e.g. layout configuration of template already exists),
							// we will allow execution to continue, thus clearing "positions" column in fields table
							try { $this->_db->query(); } catch (Exception $e) { }
							if ($this->_db->getErrorNum()) echo $this->_db->getErrorMsg();
						}
					}
				}
			}				
		}
		
		// delete the temporary table
		$query = 'DROP TABLE #__flexicontent_positions_tmp';
		$this->_db->setQuery( $query );
		$this->_db->query();
		
		// delete the old positions
		$query 	= "UPDATE #__flexicontent_fields SET positions = ''";
		$this->_db->setQuery( $query );
		$this->_db->query();
		
		// alter ordering field for releases prior to beta5
		$query 	= "ALTER TABLE #__flexicontent_cats_item_relations MODIFY `ordering` int(11) NOT NULL default '0'";
		$this->_db->setQuery( $query );
		$this->_db->query();
		$query 	= "ALTER TABLE #__flexicontent_fields_type_relations MODIFY `ordering` int(11) NOT NULL default '0'";
		$this->_db->setQuery( $query );
		$this->_db->query();
		
		return $return;
	}


	/**
	 * Method to check if there are still old core fields data in the fields_items_relations table
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getNoOldFieldsData()
	{
		static $return;
		if ($return === NULL) {
			$query 	= 'SELECT COUNT( item_id )'
					. ' FROM #__flexicontent_fields_item_relations'
					. ' WHERE field_id < 15'
					. ' LIMIT 1';
					;
			$this->_db->setQuery( $query );
			$return = $this->_db->loadResult() ? false : true;
		}
		return $return;
	}

	
	/**
	 * Method to check if there is at least one category created
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistcat()
	{
		$query 	= 'SELECT COUNT( id )'
				. ' FROM #__categories as cat'
				. ' WHERE cat.extension="'.FLEXI_CAT_EXTENSION.'" AND lft> ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND rgt<' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
				;
		$this->_db->setQuery( $query );
		$count = $this->_db->loadResult();
			
		if ($count > 0) {
			return true;
		}
		return false;
	}

	/**
	 * Method to check if FLEXI_SECTION (or FLEXI_CAT_EXTENSION for J1.6+) still exists
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistsec()
	{
		if (FLEXI_CAT_EXTENSION) {
			$query = 'SELECT COUNT( id )'
			. ' FROM #__categories'
			. ' WHERE id = 1 AND extension=\'system\''
			;
			$this->_db->setQuery( $query );
			$count = $this->_db->loadResult();
				
			if ($count > 0) {
				return true;
			} else if (FLEXI_J16GE) {
				die("Category table corrupted, SYSTEM root category not found");
			} else {
				// Save the created section as flexi_section for the component
				$cparams = JComponentHelper::getParams('com_flexicontent');
				$cparams->set('flexi_section', '');
				$cparams_str = $cparams->toString();
				
				$flexi = JComponentHelper::getComponent('com_flexicontent');
				$query = 'UPDATE '. (FLEXI_J16GE ? '#__extensions' : '#__components')
						. ' SET params = ' . $this->_db->Quote($cparams_str)
						. ' WHERE '. (FLEXI_J16GE ? 'extension_id' : 'id') .'='. $flexi->id
						;
				$this->_db->setQuery($query);
				$this->_db->query();
				return true;
			}
		}
		return false;
	}

	/**
	 * Method to check if there is at list one menu item is created
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistmenu()
	{
		$component = JComponentHelper::getComponent('com_flexicontent');
		$app = JFactory::getApplication();
		
		if(FLEXI_J16GE) {
			$query 	=	"SELECT COUNT(*) FROM #__menu WHERE `type`='component' AND `published`=1 AND `component_id`='{$component->id}' ";
			$this->_db->setQuery($query);
			$count = $this->_db->loadResult();
		} else {
			$menus = $app->getMenu('site', array());
			$items = $menus->getItems('componentid', $component->id);
			$count = count($items);
		}
		
		if ($count > 0) {
			return true;
		}
		return false;
	}
	
	
	function getDiffVersions($current_versions=array(), $last_versions=array())
	{
		// check if the category was chosen to avoid adding data on static contents
		if (!FLEXI_CAT_EXTENSION) return array();
		
		if(!$current_versions) {
			$current_versions = FLEXIUtilities::getCurrentVersions();
		}
		if(!$last_versions) {
			$last_versions = FLEXIUtilities::getLastVersions();
		}
		
		$difference = $current_versions;
		foreach($current_versions as $key1 => $value1) {
			foreach($last_versions as $key2 => $value2) {
				if( ($value1["id"]==$value2["id"]) && ($value1["version"]==$value2["version"]) ) {
					unset($difference[$key1]);
				}
			}
		}
		return $difference;
	}
	
	
	function checkCurrentVersionData() {
		// verify that every current version is in the versions table and it's data in the flexicontent_items_versions table
		//$and = "";

		// check if the section was chosen to avoid adding data on static contents
		if (!FLEXI_CAT_EXTENSION) return false;
		return FLEXIUtilities::currentMissing();
	}
	
	
	function addCurrentVersionData($item_id = null, $clean_database = false)
	{
		// check if the section was chosen to avoid adding data on static contents
		if (!FLEXI_CAT_EXTENSION) return true;
		
		$db 		= &$this->_db;
		$nullDate	= $db->getNullDate();

		// @TODO: move somewhere else
		$this->formatFlexiPlugins();
		
		// Clean categories cache
		$catscache = JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();
		
		// Get some basic info of all items, or of the given item
		$query = "SELECT c.id,c.catid,c.version,c.created,c.modified,c.created_by,c.introtext,c.`fulltext`"
			." FROM #__content as c"
			. " JOIN #__categories as cat ON c.catid=cat.id "
			." WHERE cat.extension='".FLEXI_CAT_EXTENSION."'" // ."AND cat.lft >= ".$this->_db->Quote(FLEXI_LFT_CATEGORY)." AND cat.rgt <= ".$this->_db->Quote(FLEXI_RGT_CATEGORY).";";
			.($item_id ? " AND c.id=".$item_id : "")
			;

		$db->setQuery($query);
		$rows = $db->loadObjectList('id');
		
		if (!$item_id) {
			// Get current version ids of ALL ITEMS not having current versions
			$diff_arrays = $this->getDiffVersions();
		} else {
			// Get current version id of a SPECIFIC ITEM
			$diff_arrays = array( FLEXIUtilities::getCurrentVersions($item_id) );
		}
		
		//$jcorefields = flexicontent_html::getJCoreFields();
		$add_cats = true;
		$add_tags = true;
		
		// For all items not having the current version, add it
		foreach($diff_arrays as $item)
		{
			$item_id = @ (int)$item["id"];
			if( isset( $rows[$item_id] ) )
			{
				$row = & $rows[$item_id];
				
				// Get field values of the current item version
				$query = "SELECT f.id,fir.value,f.field_type,f.name,fir.valueorder,fir.suborder,f.iscore "
						." FROM #__flexicontent_fields_item_relations as fir"
					//." LEFT JOIN #__flexicontent_items_versions as iv ON iv.field_id="
						." LEFT JOIN #__flexicontent_fields as f on f.id=fir.field_id "
						." WHERE fir.item_id=".$row->id." AND f.iscore=0";  // old versions stored categories & tags into __flexicontent_fields_item_relations
				$db->setQuery($query);
				$fieldsvals = $db->loadObjectList();
				
				// Delete existing unversioned (current version) field values ONLY if we are asked to 'clean' the database
				if ($clean_database && $fieldsvals) {
					$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.$row->id;
					$db->setQuery($query);
					$db->query();
				}
				
				// Delete any existing versioned field values to avoid conflicts, this maybe redudant, since they should not exist,
				// but we do it anyway because someone may have truncated or delete records only in table 'flexicontent_versions' ...
				// NOTE: we do not delete data with field_id negative as these exist only in the versioning table
				$query = 'DELETE FROM #__flexicontent_items_versions WHERE item_id = '.$row->id .' AND version= '.$row->version.' AND field_id > 0';
				$db->setQuery($query);
				$db->query();
				
				// Add the 'maintext' field to the fields array for adding to versioning table
				$f = new stdClass();
				$f->id					= 1;
				$f->iscore			= 1;
				$f->valueorder	= 1;
				$f->suborder		= 1;
				$f->field_type	= "maintext";
				$f->name				= "text";
				$f->value				= $row->introtext;
				if ( JString::strlen($row->fulltext) > 1 ) {
					$f->value .= '<hr id="system-readmore" />' . $row->fulltext;
				}
				if(substr($f->value, 0, 3)!="<p>") {
					$f->value = "<p>".$f->value."</p>";
				}
				$fieldsvals[] = $f;

				// Add the 'categories' field to the fields array for adding to versioning table
				$query = "SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid='".$row->id."';";
				$db->setQuery($query);
				$categories = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				if(!$categories || !count($categories)) {
					$categories = array($catid = $row->catid);
					$query = "INSERT INTO #__flexicontent_cats_item_relations VALUES('$catid','".$row->id."', '0');";
					$db->setQuery($query);
					$db->query();
				}
				$f = new stdClass();
				$f->id 					= 13;
				$f->iscore			= 1;
				$f->valueorder	= 1;
				$f->suborder		= 1;
				$f->version		= (int)$row->version;
				$f->value		= serialize($categories);
				if ($add_cats) $fieldsvals[] = $f;
				
				// Add the 'tags' field to the fields array for adding to versioning table
				$query = "SELECT tid FROM #__flexicontent_tags_item_relations WHERE itemid='".$row->id."';";
				$db->setQuery($query);
				$tags = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				$f = new stdClass();
				$f->id 					= 14;
				$f->iscore			= 1;
				$f->valueorder	= 1;
				$f->suborder		= 1;
				$f->version		= (int)$row->version;
				$f->value		= serialize($tags);
				if ($add_tags) $fieldsvals[] = $f;

				// Add field values to field value versioning table
				foreach($fieldsvals as $fieldval) {
					// add the new values to the database 
					$obj = new stdClass();
					$obj->field_id   = $fieldval->id;
					$obj->item_id    = $row->id;
					$obj->valueorder = $fieldval->valueorder;
					$obj->suborder   = $fieldval->suborder;
					$obj->version    = (int)$row->version;
					$obj->value      = $fieldval->value;
					//echo "version: ".$obj->version.",fieldid : ".$obj->field_id.",value : ".$obj->value.",valueorder : ".$obj->valueorder.",suborder : ".$obj->suborder."<br />";
					//echo "inserting into __flexicontent_items_versions<br />";
					$db->insertObject('#__flexicontent_items_versions', $obj);
					if( $clean_database && !$fieldval->iscore ) { // If clean_database is on we need to re-add the deleted values
						unset($obj->version);
						//echo "inserting into __flexicontent_fields_item_relations<br />";
						$db->insertObject('#__flexicontent_fields_item_relations', $obj);
					}
					//$searchindex 	.= @$fieldval->search;
				}
				
				// **********************************************************************************
				// Add basic METADATA of current item version (kept in table #__flexicontent_versions)
				// **********************************************************************************
				$v = new stdClass();
				$v->item_id    = (int)$row->id;
				$v->version_id = (int)$row->version;
				$v->created    = ($row->modified && ($row->modified != $nullDate)) ? $row->modified : $row->created;
				$v->created_by = $row->created_by;
				$v->comment    = '';
				//echo "inserting into __flexicontent_versions<br />";
				$db->insertObject('#__flexicontent_versions', $v);
			}
		}
		return true;
	}
	
	
	function formatFlexiPlugins()
	{
		$db = $this->_db;
		$tbl   = FLEXI_J16GE ? '#__extensions' : '#__plugins';
		$idcol = FLEXI_J16GE ? 'extension_id' : 'id';
		
		$query	= 'SELECT '.$idcol.' AS id, name, element FROM '.$tbl
				. ' WHERE folder =' . $db->Quote('flexicontent_fields')
				. (FLEXI_J16GE ? ' AND `type`=' . $db->Quote('plugin') : '')
				;
		$db->setQuery($query);
		$flexiplugins = $db->loadObjectList();
		
		$query = 'UPDATE '.$tbl.' SET name = CASE '.$idcol;
		$cases   = array();
		$ext_ids = array();
		foreach ($flexiplugins as $fp) {
			if ( substr($fp->name, 0, 15) == 'FLEXIcontent - ' ) continue;
			$cases[] = '	WHEN '.(int)$fp->id.' THEN '.$db->Quote('FLEXIcontent - '.$fp->name);
			$ext_ids[] = $fp->id;
		}
		$ext_ids_list = implode(', ', $ext_ids);
		$query .= implode(' ',$cases)
			. ' END'
			. ' WHERE '.$idcol.' IN ('.$ext_ids_list.')'
			. ' AND folder =' . $db->Quote('flexicontent_fields')
			. (FLEXI_J16GE ? ' AND `type`=' . $db->Quote('plugin') : '')
			;
		if ( count($cases) ) {
			$db->setQuery($query);
			$db->query();
			if ($db->getErrorNum()) echo $db->getErrorMsg();
		}
		
		/*$map = array(
			'account_via_submit'=>'FLEXIcontent - User account via submit',
			'authoritems'=>'FLEXIcontent - Author Items (More items by this Author)',
			'addressint'=>'FLEXIcontent - Address International / Google Maps',
			'checkbox'=>'FLEXIcontent - Checkbox',
			'checkboximage'=>'FLEXIcontent - Checkbox Image',
			'core'=>'FLEXIcontent - Core Fields (Joomla article properties)',
			'date'=>'FLEXIcontent - Date / Publish Up-Down Dates',
			'email'=>'FLEXIcontent - Email',
			'extendedweblink'=>'FLEXIcontent - Extended Weblink',
			'fcloadmodule'=>'FLEXIcontent - Load Module / Module position',
			'fcpagenav'=>'FLEXIcontent - Navigation (Next/Previous Item)',
			'file'=>'FLEXIcontent - File (Download/View/Share/Download cart)',
			'groupmarker'=>'FLEXIcontent - Item Form Tab / Fieldset / Custom HTML',
			'image'=>'FLEXIcontent - Image or Gallery (image + details)',
			'linkslist'=>'FLEXIcontent - HTML list of URLs/Anchors/JS links',
			'minigallery'=>'FLEXIcontent - Mini-Gallery (image-only slideshow)',
			'phonenumbers'=>'FLEXIcontent - Phone Numbers',
			'radio'=>'FLEXIcontent - Radio',
			'radioimage'=>'FLEXIcontent - Radio Image',
			'relation'=>'FLEXIcontent - Relation (List of related items)',
			'relation_reverse'=>'FLEXIcontent - Relation - Reverse',
			'select'=>'FLEXIcontent - Select',
			'selectmultiple'=>'FLEXIcontent - Select Multiple',
			'sharedaudio'=>'FLEXIcontent - Shared Audio (youtube,vimeo,dailymotion,etc)',
			'sharedvideo'=>'FLEXIcontent - Shared Video (youtube,vimeo,dailymotion,etc)',
			'text'=>'FLEXIcontent - Text (number/time/etc/custom validation)',
			'textarea'=>'FLEXIcontent - Textarea',
			'textselect'=>'FLEXIcontent - TextSelect (Text with existing value selection)',
			'toolbar'=>'FLEXIcontent - Toolbar (social share/other tools)',
			'weblink'=>'FLEXIcontent - Weblink'
		);
		
		$query = 'UPDATE '.$tbl.' SET name = CASE element';
		$cases    = array();
		$elements = array();
		foreach ($flexiplugins as $fp) {
			if ( empty($map[$fp->element]) ) continue;
			$cases[] = '	WHEN '.$db->Quote($fp->element).' THEN '.$db->Quote($map[$fp->element]);
			$elements[] = $db->Quote($fp->element);
		}
		$elements_list = implode(', ', $elements);
		$query .= implode(' ',$cases)
			. ' END'
			. ' WHERE folder =' . $db->Quote('flexicontent_fields')
			. (FLEXI_J16GE ? ' AND `type`='. $db->Quote('plugin') : '')
			. ' AND element IN ('.$elements_list.')'
			;
		if ( count($cases) ) {
			$db->setQuery($query);
			$db->query();
			if ($db->getErrorNum()) echo $db->getErrorMsg();
		}*/
	}

	function processLanguageFiles($code = 'en-GB', $method = '', $params = array())
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.archive');
		
		$prefix 	= $code . '.';
		$suffix 	= '.ini';
		$missing 	= array();
		$namea		= '';
		$names		= '';

		$adminpath 		= JPATH_ADMINISTRATOR.DS.'language'.DS.$code.DS;
		$refadminpath 	= JPATH_ADMINISTRATOR.DS.'language'.DS.'en-GB'.DS;

		$adminfiles = array(
		// component files
			'com_flexicontent',
			(FLEXI_J16GE ? 'com_flexicontent.sys' : ''),
		// plugin files  --  flexicontent_fields
			'plg_flexicontent_fields_addressint',
			'plg_flexicontent_fields_checkbox',
			'plg_flexicontent_fields_checkboximage',
			'plg_flexicontent_fields_core',
			'plg_flexicontent_fields_date',
			'plg_flexicontent_fields_email',
			'plg_flexicontent_fields_extendedweblink',
			'plg_flexicontent_fields_fcloadmodule',
			'plg_flexicontent_fields_fcpagenav',
			'plg_flexicontent_fields_file',
			'plg_flexicontent_fields_groupmarker',
			'plg_flexicontent_fields_image',
			'plg_flexicontent_fields_linkslist',
			'plg_flexicontent_fields_minigallery',
			'plg_flexicontent_fields_phonenumbers',
			'plg_flexicontent_fields_radio',
			'plg_flexicontent_fields_radioimage',
			'plg_flexicontent_fields_relation',
			'plg_flexicontent_fields_relation_reverse',
			'plg_flexicontent_fields_select',
			'plg_flexicontent_fields_selectmultiple',
			'plg_flexicontent_fields_shareaudio',
			'plg_flexicontent_fields_sharevideo',
			'plg_flexicontent_fields_text',
			'plg_flexicontent_fields_textarea',
			'plg_flexicontent_fields_textselect',
			'plg_flexicontent_fields_toolbar',
			'plg_flexicontent_fields_weblink',
		// plugin files  --  finder
			(FLEXI_J16GE ? 'plg_finder_flexicontent' : ''),
			(FLEXI_J16GE ? 'plg_finder_flexicontent.sys' : ''),
		// plugin files  --  content
			'plg_content_flexibreak',
		// plugin files  --  flexicontent
			'plg_flexicontent_flexinotify',
		// plugin files  --  search
			'plg_search_flexiadvsearch',
			'plg_search_flexisearch',
		// plugin files  --  system
			'plg_system_flexiadvroute',
			'plg_system_flexisystem'
		);

		$sitepath 		= JPATH_SITE.DS.'language'.DS.$code.DS;
		$refsitepath 	= JPATH_SITE.DS.'language'.DS.'en-GB'.DS;
		$sitefiles 	= array(
		// component files
			'com_flexicontent',
		// module files
			'mod_flexiadvsearch',
			'mod_flexicontent',
			'mod_flexitagcloud',
			'mod_flexifilter'
		);
		$targetfolder = JPATH_SITE.DS.'tmp'.DS.$code."_".time();
		
		if ($method == 'zip') {
			if (count($adminfiles))
				JFolder::create($targetfolder.DS.'admin', 0755);
			if (count($sitefiles))
				JFolder::create($targetfolder.DS.'site', 0755);
		}
		
		foreach ($adminfiles as $file) {
			if (!$file) continue;
			if (!JFile::exists($adminpath.$prefix.$file.$suffix)) {
				$missing['admin'][] = $file;
				if ($method == 'create') 
					JFile::copy($refadminpath.'en-GB.'.$file.$suffix, $adminpath.$prefix.$file.$suffix); 
			} else {
				if ($method == 'zip') {
					JFile::copy($adminpath.$prefix.$file.$suffix, $targetfolder.DS.'admin'.DS.$prefix.$file.$suffix);
					$namea .= "\n".'			            <filename>'.$prefix.$file.$suffix.'</filename>';
				}
			}
		}
		foreach ($sitefiles as $file) {
			if (!$file) continue;
			if (!JFile::exists($sitepath.$prefix.$file.$suffix)) {
				$missing['site'][] = $file;
				if ($method == 'create') 
					JFile::copy($refsitepath.'en-GB.'.$file.$suffix, $sitepath.$prefix.$file.$suffix);
			} else {
				if ($method == 'zip') {
					JFile::copy($sitepath.$prefix.$file.$suffix, $targetfolder.DS.'site'.DS.$prefix.$file.$suffix);
					$names .= "\n".'			            <filename>'.$prefix.$file.$suffix.'</filename>';
				}
			}
		}
		
		if ($method == 'zip') 
		{
			$mailfrom 	= @$params['email']	? $params['email']	: 'emmanuel.danan@gmail.com';
			$fromname 	= @$params['name'] 	? $params['name'] 	: 'Emmanuel Danan';
			$website 	= @$params['web'] 	? $params['web'] 	: 'http://www.flexicontent.org';
			
			// prepare the manifest of the language archive
			$date = JFactory::getDate();
		
			$xmlfile = $targetfolder.DS.'install.xml';
			
			$xml = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
			<install type="language" version="1.5" client="both" method="upgrade">
			    <name>FLEXIcontent '.$code.'</name>
			    <tag>'.$code.'</tag>
			    <creationDate>'.(FLEXI_J16GE ? $date->format('Y-M-d', $local = true) : $date->toFormat("%Y-%m-%d")).'</creationDate>
			    <author>'.$fromname.'</author>
			    <authorEmail>'.$mailfrom.'</authorEmail>
			    <authorUrl>'.$website.'</authorUrl>
			    <copyright>(C) '.(FLEXI_J16GE ? $date->format('Y', $local = true) : $date->toFormat("%Y")).' '.$fromname.'</copyright>
			    <license>http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL</license>
			    <description>'.$code.' language pack for FLEXIcontent</description>
			    <administration>
			        <files folder="admin">'.$namea.'
			        </files>
			    </administration>
			    <site>
			        <files folder="site">'.$names.'
			        </files>
			    </site>
			</install>'
			   ;
			// save xml manifest
			JFile::write($xmlfile, $xml);
			
			
			$fileslist  = JFolder::files($targetfolder, '.', true, true, array('.svn', 'CVS', '.DS_Store'));
			$archivename = $targetfolder.'.com_flexicontent'. (FLEXI_J16GE ? '.zip' : '.tar.gz');
			
			// Create the archive
			echo JText::_('FLEXI_SEND_LANGUAGE_CREATING_ARCHIVE')."<br>";
			if (!FLEXI_J16GE) {
				JArchive::create($archivename, $fileslist, 'gz', '', $targetfolder);
			} else {
				$app = JFactory::getApplication('administrator');
				$files = array();
				foreach ($fileslist as $i => $filename) {
					$files[$i]=array();
					$files[$i]['name'] = preg_replace("%^(\\\|/)%", "", str_replace($targetfolder, "", $filename) );  // STRIP PATH for filename inside zip
					$files[$i]['data'] = implode('', file($filename));   // READ contents into string, here we use full path
					$files[$i]['time'] = time();
				}
				
				$packager = JArchive::getAdapter('zip');
				if (!$packager->create($archivename, $files)) {
					echo JText::_('FLEXI_OPERATION_FAILED');
					return false;
				}
			}
			
			// Remove temporary folder structure
			if (!JFolder::delete(($targetfolder)) ) {
				echo JText::_('FLEXI_SEND_DELETE_TMP_FOLDER_FAILED');
			}
		}
		
		// messages
		if ($method == 'zip') {
			return '<h3 class="lang-success">' . JText::_( 'FLEXI_SEND_LANGUAGE_ARCHIVE_SUCCESS' ) . '</span>';
		}
		return (count($missing) > 0) ? $missing : '<span class="fc-mssg fc-success">'. JText::sprintf( 'FLEXI_SEND_LANGUAGE_NO_MISSING', $code ) .'</span>';
	}

	
	function checkInitialPermission() {
		$debug_initial_perms = JComponentHelper::getParams('com_flexicontent')->get('debug_initial_perms');
		
		static $init_required = null;
		if ( $init_required !== null ) return $init_required;
		
		$db = JFactory::getDBO();
		$component_name = 'com_flexicontent';
		
		// DELETE old namespace (flexicontent.*) permissions of v2.0beta, we do not try to rename them ... instead we will use com_content (for some of them),
		$query = $db->getQuery(true)->delete('#__assets')->where('name LIKE ' . $db->quote('flexicontent.%'));
		$db->setQuery($query);
		
		try { $db->query(); } catch (Exception $e) { }
		if ($db->getErrorNum()) echo $db->getErrorMsg();
		
		// SET Access View Level to public (=1) for fields that do not have their Level set
		$query = $db->getQuery(true)->update('#__flexicontent_fields')->set('access = 1')->where('access = 0');
		$db->setQuery($query);
		
		try { $db->query(); } catch (Exception $e) { }
		if ($db->getErrorNum()) echo $db->getErrorMsg();
		
		// SET Access View Level to public (=1) for types that do not have their Level set
		$query = $db->getQuery(true)->update('#__flexicontent_types')->set('access = 1')->where('access = 0');
		$db->setQuery($query);
		
		try { $db->query(); } catch (Exception $e) { }
		if ($db->getErrorNum()) echo $db->getErrorMsg();
		
		// CHECK that we have the same Component Actions in assets DB table with the Actions as in component's access.xml file
		$asset	= JTable::getInstance('asset');
		if ($comp_section = $asset->loadByName($component_name)) {  // Try to load component asset, if missing it returns false
			// ok, component asset not missing, proceed to cross check for deleted / added actions
			$rules = new JAccessRules($asset->rules);
			$rules_data = $rules->getData();
			$component_actions = JAccess::getActions($component_name, 'component');
			
			$db_action_names = array();
			foreach ($rules_data as $action_name => $data)  $db_action_names[]   = $action_name;
			foreach ($component_actions as $action)         $file_action_names[] = $action->name;
			$deleted_actions =  array_diff($db_action_names,   $file_action_names);
			$added_actions   =  array_diff($file_action_names, $db_action_names  );
			
			$comp_section = ! ( count($deleted_actions) || count($added_actions) );  // false if deleted or addeded actions exist
			if ($debug_initial_perms) { echo "Deleted actions: "; print_r($deleted_actions); echo "<br> Added actions: "; print_r($added_actions); echo "<br>"; }
		}
		
		if ($debug_initial_perms) { echo "Component DB Rule Count " . ( ($comp_section) ? count($rules->getData()) : 0 ) . "<br />"; }
		if ($debug_initial_perms) { echo "Component File Rule Count " . count(JAccess::getActions('com_flexicontent', 'component')) . "<br />"; }
		
		// CHECK if some categories don't have permissions set, , !!! WARNING this query must be same like the one USED in function initialPermission()
		$query = $db->getQuery(true)
			->select('c.id')
			->from('#__assets AS se')->join('RIGHT', '#__categories AS c ON se.id=c.asset_id AND se.name=concat("com_content.category.",c.id)')
			->where( '(se.id is NULL OR (c.parent_id=1 AND se.parent_id!='.(int)$asset->id.') )' )
			->where( 'c.extension = ' . $db->quote('com_content') );
		
		$db->setQuery($query);
		
		try { $result = $db->loadObjectList(); } catch (Exception $e) { $result = array(); }
		if ($db->getErrorNum()) echo $db->getErrorMsg();
		
		if (!empty($result) && $debug_initial_perms) { echo "bad assets for categories: "; print_r($result); echo "<br>"; }
		$category_section = count($result) == 0 ? 1 : 0;

		// CHECK if some items don't have permissions set, , !!! WARNING this query must be same like the one USED in function initialPermission()
		/*$query = $db->getQuery(true)
			->select('c.id')
			->from('#__assets AS se')->join('RIGHT', '#__content AS c ON se.id=c.asset_id AND se.name=concat("com_content.article.",c.id)')
			->where('se.id is NULL');
		$db->setQuery($query);
		$result = $db->loadObjectList();					if ($db->getErrorNum()) echo $db->getErrorMsg();
		if (count($result) && $debug_initial_perms) { echo "bad assets for items: "; print_r($result); echo "<br>"; }*/
		$article_section = 1; //count($result) == 0 ? 1 : 0;

		// CHECK if some fields don't have permissions set, !!! WARNING this query must be same like the one USED in function initialPermission()
		$query = $db->getQuery(true)
			->select('se.id')
			->from('#__assets AS se')->join('RIGHT', '#__flexicontent_fields AS ff ON se.id=ff.asset_id AND se.name=concat("com_flexicontent.field.",ff.id)')
			->where('se.id is NULL');
		$db->setQuery($query);
		
		try { $result = $db->loadObjectList(); } catch (Exception $e) { $result = array(); }
		if ($db->getErrorNum()) echo $db->getErrorMsg();
		
		if (!empty($result) && $debug_initial_perms) { echo "bad assets for fields: "; print_r($result); echo "<br>"; }
		$field_section = count($result) == 0 ? 1 : 0;

		// CHECK if some types don't have permissions set, !!! WARNING this query must be same like the one USED in function initialPermission()
		$query = $db->getQuery(true)
			->select('se.id')
			->from('#__assets AS se')->join('RIGHT', '#__flexicontent_types AS ff ON se.id=ff.asset_id AND se.name=concat("com_flexicontent.type.",ff.id)')
			->where('se.id is NULL');
		$db->setQuery($query);
		
		try { $result = $db->loadObjectList(); } catch (Exception $e) { $result = array(); }
		if ($db->getErrorNum()) echo $db->getErrorMsg();
		
		if (!empty($result) && $debug_initial_perms) { echo "bad assets for types: "; print_r($result); echo "<br>"; }
		$type_section = count($result) == 0 ? 1 : 0;
		
		if ($debug_initial_perms) { echo "PASSED comp_section:$comp_section && category_section:$category_section && article_section:$article_section && field_section:$field_section && type_section:$type_section <br>"; }
		
		$init_required = $comp_section && $category_section && $article_section && $field_section && $type_section;
		return $init_required;
	}
	
	
	function initialPermission() {
		$component_name	= JRequest::getCmd('option');
		$db 		= JFactory::getDBO();
		$asset	= JTable::getInstance('asset');   // Create an asset object
		
		/*** Component assets ***/
		
		if (!$asset->loadByName($component_name)) {
			// The assets entry does not exist: We will create initial rules for all component's actions
			
			// Get root asset
			$root = JTable::getInstance('asset');
			$root->loadByName('root.1');
			
			// Initialize component asset
			$asset->name = $component_name;
			$asset->title = $component_name;
			$asset->setLocation($root->id,'last-child');  // father of compontent asset it the root asset
			
			// Create initial component rules and set them into the asset
			$initial_rules = $this->_createComponentRules($component_name);
			$component_rules = new JAccessRules(json_encode($initial_rules));
			$asset->rules = $component_rules->__toString();
			
			// Save the asset into the DB
			if (!$asset->check() || !$asset->store()) {
				echo $asset->getError();
				$this->setError($asset->getError());
				return false;
			}
		} else {
			// The assets entry already exists: We will check if it has exactly the actions specified in component's access.xml file
			
			// Get existing DB rules and component's actions from the access.xml file
			$existing_rules = new JAccessRules($asset->rules);
			$rules_data = $existing_rules->getData();
			$component_actions = JAccess::getActions('com_flexicontent', 'component');
			
			// Find any deleted / added actions ...
			$db_action_names = array();
			foreach ($rules_data as $action_name => $data)  $db_action_names[]   = $action_name;
			foreach ($component_actions as $action)         $file_action_names[] = $action->name;
			$deleted_actions =  array_diff($db_action_names,   $file_action_names);
			$added_actions   =  array_diff($file_action_names, $db_action_names  );
			
			if ( count($deleted_actions) || count($added_actions) ) {
				// We have changes in the component actions
				
				// First merge the existing component (db) rules into the initial rules
				$initial_rules = $this->_createComponentRules($component_name);
				$component_rules = new JAccessRules(json_encode($initial_rules));
				$component_rules->merge($existing_rules);
				
				// Second, check if obsolete rules are contained in the existing component (db) rules, if so create a new rules object without the obsolete rules
				if ($deleted_actions) {
					$rules_data = $component_rules->getData();
					foreach($deleted_actions as $action_name) {
						unset($rules_data[$action_name]);
					}
					$component_rules = new JAccessRules($rules_data);
				}
				
				// Set asset rules
				$asset->rules = $component_rules->__toString();
				
				// Save the asset
				if (!$asset->check() || !$asset->store()) {
					echo $asset->getError();
					$this->setError($asset->getError());
					return false;
				}
			}
		}
		
		// Load component asset
		$component_asset = JTable::getInstance('asset');
		$component_asset->loadByName($component_name);
		
		/*** CATEGORY assets ***/
		
		// Get a list com_content categories that do not have assets (or have wrong asset names)
		$query = $db->getQuery(true)
			->select('c.id, c.parent_id, c.title, c.asset_id')
			->from('#__assets AS se')->join('RIGHT', '#__categories AS c ON se.id=c.asset_id AND se.name=concat("com_content.category.",c.id)')
			->where( '(se.id is NULL OR (c.parent_id=1 AND se.parent_id!='.(int)$asset->id.') )' )
			->where( 'c.extension = ' . $db->quote('com_content') )
			->order('c.level ASC');   // IMPORTANT create categories asset using increasing depth level, so that get parent assetid will not fail
		$db->setQuery($query);
		$results = $db->loadObjectList();					if ($db->getErrorNum()) echo $db->getErrorMsg();
		
		// Add an asset to every category that doesnot have one
		if(count($results)>0) {
			foreach($results as $category) {
				$parentId = $this->_getAssetParentId(null, $category);
				$name = "com_content.category.{$category->id}";
				
				// Try to load asset for the current CATEGORY ID
				$asset_found = $asset->loadByName($name);
				
				if ( !$asset_found ) {
					if ($category->asset_id) {
						// asset name not found but category has an asset id set ?, we could delete it here
						// but it maybe dangerous to do so ... it might be a legitimate asset_id for something else
					}
					
					// Set id to null since we will be creating a new asset on store
					$asset->id 		= null;
					
					// Set asset rules to empty, (DO NOT set any ACTIONS, just let them inherit ... from parent)
					$asset->rules = new JAccessRules();
					
					/*if ($parentId == $component_asset->id) {				
						$actions	= JAccess::getActions($component_name, 'category');
						$rules 		= json_decode($component_asset->rules);		
						foreach ($actions as $action) {
							$catrules[$action->name] = $rules->{$action->name};
						}
						$rules = new JAccessRules(json_encode($catrules));
						$asset->rules = $rules->__toString();
					} else {
						$parent = JTable::getInstance('asset');
						$parent->load($parentId);
						$asset->rules = $parent->rules;
					}*/
				} else {
					// do not change (a) the id OR (b) the rules, of the asset
				}
				
				// Initialize appropriate asset properties
				$asset->name	= $name;
				$asset->title	= $category->title;
				$asset->setLocation($parentId, 'last-child');     // Permissions of categories are inherited by parent category, or from component if no parent category exists
				
				// Save the category asset (create or update it)
				if (!$asset->check() || !$asset->store(false)) {
					echo $asset->getError();
					echo " Problem for asset with id: ".$asset ->id;
					echo " Problem for category with id: ".$category->id. "(".$category->title.")";
					$this->setError($asset->getError());
					return false;
				}
				
				// Assign the asset to the category, if it is not already assigned
				$query = $db->getQuery(true)
					->update('#__categories')
					->set('asset_id = ' . (int)$asset->id)
					->where('id = ' . (int)$category->id);
				$db->setQuery($query);
				
				if (!$db->query()) {
					echo JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED', get_class($this), $db->getErrorMsg());
					$this->setError(JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED', get_class($this), $db->getErrorMsg()));
					return false;
				}
			}
		}
		
		
		
		/*** ITEM assets ***/
		/*
		// Get a list com_content items that do not have assets (or have wrong asset names)
		$query = $db->getQuery(true)
			->select('c.id, c.catid as parent_id, c.title, c.asset_id')
			->from('#__assets AS se')->join('RIGHT', '#__content AS c ON se.id=c.asset_id AND se.name=concat("com_content.article.",c.id)')
			->where('se.id is NULL');//->where('c.extension = ' . $db->quote('com_content'));
		$db->setQuery($query);
		$results = $db->loadObjectList();					if ($db->getErrorNum()) echo $db->getErrorMsg();
		
		// Add an asset to every item that doesnot have one
		if(count($results)>0) {
			foreach($results as $item) {
				$parentId = $this->_getAssetParentId(null, $item);
				$name = "com_content.article.{$item->id}";
				
				// Try to load asset for the current CATEGORY ID
				$asset_found = $asset->loadByName($name);
				
				if ( !$asset_found ) {
					if ($item->asset_id) {
						// asset name not found but item has an asset id set ?, we could delete it here
						// but it maybe dangerous to do so ... it might be a legitimate asset_id for something else
					}
					
					// Set id to null since we will be creating a new asset on store
					$asset->id 		= null;
					
					// Set asset rules to empty, (DO NOT set any ACTIONS, just let them inherit ... from parent)
					$asset->rules = new JAccessRules();
					
					//if ($parentId == $component_asset->id) {				
					//	$actions	= JAccess::getActions($component_name, 'article');
					//	$rules 		= json_decode($component_asset->rules);		
					//	foreach ($actions as $action) {
					//		$catrules[$action->name] = $rules->{$action->name};
					//	}
					//	$rules = new JAccessRules(json_encode($catrules));
					//	$asset->rules = $rules->__toString();
					//} else {
					//	$parent = JTable::getInstance('asset');
					//	$parent->load($parentId);
					//	$asset->rules = $parent->rules;
					//}
				} else {
					// do not change (a) the id OR (b) the rules, of the asset
				}
				
				// Initialize appropriate asset properties
				$asset->name	= $name;
				$asset->title	= $item->title;
				$asset->setLocation($parentId, 'last-child');     // Permissions of items are inherited from their main category
				
				// Save the item asset (create or update it)
				if (!$asset->check() || !$asset->store(false)) {
					echo $asset->getError();
					$this->setError($asset->getError());
					return false;
				}
				
				// Assign the asset to the item, if it is not already assigned
				$query = $db->getQuery(true)
					->update('#__content')
					->set('asset_id = ' . (int)$asset->id)
					->where('id = ' . (int)$item->id);
				$db->setQuery($query);
				
				if (!$db->query()) {
					echo JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED', get_class($this), $db->getErrorMsg());
					$this->setError(JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED', get_class($this), $db->getErrorMsg()));
					return false;
				}
			}
		}
		*/
		
		
		/*** FLEXIcontent FIELDS assets ***/
		
		// Get a list flexicontent fields that do not have assets
		$query = $db->getQuery(true)
			->select('ff.id, ff.name, ff.asset_id')
			->from('#__assets AS se')->join('RIGHT', '#__flexicontent_fields AS ff ON se.id=ff.asset_id AND se.name=concat("com_flexicontent.field.",ff.id)')
			->where('se.id is NULL');
		$db->setQuery($query);
		$results = $db->loadObjectList();					if ($db->getErrorNum()) echo $db->getErrorMsg();
		
		// Add an asset to every field that doesnot have one
		if(count($results)>0) {
			foreach($results as $field) {
				$name = "com_flexicontent.field.{$field->id}";
				
				// Test if an asset for the current FIELD ID already exists and load it instead of creating a new asset
				if ( ! $asset->loadByName($name) ) {
					if ($field->asset_id) {
						// asset name not found but field has an asset id set ?, we could delete it here
						// but it maybe dangerous to do so ... it might be a legitimate asset_id for something else
					}

					// Initialize field asset
					$asset->id = null;
					$asset->name		= $name;
					$asset->title		= $field->name;
					$asset->setLocation($component_asset->id, 'last-child');     // Permissions of fields are directly inheritted by component
					
					// Set asset rules to empty, (DO NOT set any ACTIONS, just let them inherit ... from parent)
					$asset->rules = new JAccessRules();
					/*
					$actions	= JAccess::getActions($component_name, 'field');
					$rules 		= json_decode($component_asset->rules);		
					foreach ($actions as $action) {
						$fieldrules[$action->name] = $rules->{$action->name};
					}
					$rules = new JAccessRules(json_encode($fieldrules));
					$asset->rules = $rules->__toString();
					*/
					
					// Save the asset
					if (!$asset->check() || !$asset->store(false)) {
						echo $asset->getError();
						$this->setError($asset->getError());
						return false;
					}
				}
				
				// Assign the asset to the field
				$query = $db->getQuery(true)
					->update('#__flexicontent_fields')
					->set('asset_id = ' . (int)$asset->id)
					->where('id = ' . (int)$field->id);
				$db->setQuery($query);
				
				if (!$db->query()) {
					echo JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED', get_class($this), $db->getErrorMsg());
					$this->setError(JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED', get_class($this), $db->getErrorMsg()));
					return false;
				}
			}
		}


		/*** FLEXIcontent TYPES assets ***/
		
		// Get a list flexicontent types that do not have assets
		$query = $db->getQuery(true)
			->select('ff.id, ff.name, ff.asset_id')
			->from('#__assets AS se')->join('RIGHT', '#__flexicontent_types AS ff ON se.id=ff.asset_id AND se.name=concat("com_flexicontent.type.",ff.id)')
			->where('se.id is NULL');
		$db->setQuery($query);
		$results = $db->loadObjectList();					if ($db->getErrorNum()) echo $db->getErrorMsg();
		
		// Add an asset to every type that doesnot have one
		if(count($results)>0) {
			foreach($results as $type) {
				$name = "com_flexicontent.type.{$type->id}";
				
				// Test if an asset for the current TYPE ID already exists and load it instead of creating a new asset
				if ( ! $asset->loadByName($name) ) {
					if ($type->asset_id) {
						// asset name not found but type has an asset id set ?, we could delete it here
						// but it maybe dangerous to do so ... it might be a legitimate asset_id for something else
					}

					// Initialize type asset
					$asset->id = null;
					$asset->name		= $name;
					$asset->title		= $type->name;
					$asset->setLocation($component_asset->id, 'last-child');     // Permissions of types are directly inheritted by component
					
					// Set asset rules to empty, (DO NOT set any ACTIONS, just let them inherit ... from parent)
					$asset->rules = new JAccessRules();
					/*
					$actions	= JAccess::getActions($component_name, 'type');
					$rules 		= json_decode($component_asset->rules);		
					foreach ($actions as $action) {
						$typerules[$action->name] = $rules->{$action->name};
					}
					$rules = new JAccessRules(json_encode($typerules));
					$asset->rules = $rules->__toString();
					*/
					
					// Save the asset
					if (!$asset->check() || !$asset->store(false)) {
						echo $asset->getError();
						$this->setError($asset->getError());
						return false;
					}
				}
				
				// Assign the asset to the type
				$query = $db->getQuery(true)
					->update('#__flexicontent_types')
					->set('asset_id = ' . (int)$asset->id)
					->where('id = ' . (int)$type->id);
				$db->setQuery($query);
				
				if (!$db->query()) {
					echo JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED', get_class($this), $db->getErrorMsg());
					$this->setError(JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED', get_class($this), $db->getErrorMsg()));
					return false;
				}
			}
		}
		
		// Clear cache so that per user permissions objects are recalculated
		$cache = FLEXIUtilities::getCache($group='', 0);
		$cache->clean('com_flexicontent_cats');
		$cache = FLEXIUtilities::getCache($group='', 1);
		$cache->clean('com_flexicontent_cats');
		
		return true;
	}
	
	
	/**
	 * Creates initial component actions based on global config and on some ... logic
	 *
	 * @return  array
	 * @since   11.1
	 */
	protected function _createComponentRules($component) {
		
		$groups 	= $this->_getUserGroups();
		
		// Get flexicontent ACTION names, and initialize flexicontent rules to empty *
		$flexi_actions	= JAccess::getActions($component, 'component');
		$flexi_rules		= array();
		foreach($flexi_actions as $action) {
			$flexi_rules[$action->name] = array();  // * WE NEED THIS (even if it remains empty), because we will compare COMPONENT actions in DB when checking initial permissions
			$flexi_action_names[] = $action->name;  // Create an array of all COMPONENT actions names
		}
		
		// Get Joomla ACTION names
		$root = JTable::getInstance('asset');
		$root->loadByName('root.1');
		$joomla_rules = new JAccessRules( $root->rules );
		foreach ($joomla_rules->getData() as $action_name => $data) {
			$joomla_action_names[] = $action_name;
		}
		//echo "<pre>"; print_r($rules->getData()); echo "</pre>";
		
		
		// Decide the actions to grant (give) to each user group
		foreach($groups as $group) {
			
			// STEP 1: we will -grant- all NON-STANDARD component ACTIONS to any user group, that has 'core.manage' ACTION in the Global Configuration
			// NOTE (a): if some user group has the --Super Admin-- Global Configuration ACTION (aka 'core.admin' for asset root.1), then it also has 'core.manage'
			// NOTE (b):  The STANDARD Joomla ACTIONs will not be set thus they will default to value -INHERIT- (=value "")
			if(JAccess::checkGroup($group->id, 'core.manage')) {
				//$flexi_rules['core.manage'][$group->id] = 1;
				foreach($flexi_action_names as $action_name) {
					//if ($action_name == 'core.admin') continue;  // component CONFIGURE action, skip it, this will can only be granted by STEP 2
					if (in_array($action_name, $joomla_action_names)) continue;  // Skip Joomla STANDARD rules allowing them to inherit
					$flexi_rules[$action_name][$group->id] = 1;
				}
			}
			
			// STEP 2: we will set ACTIONS already granted in GLOBAL CONFIGURATION (this include the COMPONENT CONFIGURE 'core.admin' action)
			// NOTE: that actions that do not exist in global configuration, will not be set here, so they will default to the the setting received by STEP 1
			// NOTE: this was commented out and thus heritage will be used instead for existing Global ACTIONS
			/*foreach($flexi_action_names as $action_name) {
				if (JAccess::checkGroup($group->id, $action_name)) {
					$flexi_rules[$action_name][$group->id] = 1;
				}
			}*/
			
			// STEP 3: Handle some special case of custom-added ACTIONs
			// e.g. Grant --OWNED-- actions if they have the corresponding --GENERAL-- actions
			if( !empty($flexi_rules['core.delete'][$group->id]) ) {
				if (in_array('core.delete.own', $flexi_action_names)) $flexi_rules['core.delete.own'][$group->id] = 1;          //CanDeleteOwn
			}
			if( !empty($flexi_rules['core.edit.state'][$group->id]) ) {
				if (in_array('core.edit.state.own', $flexi_action_names)) $flexi_rules['core.edit.state.own'][$group->id] = 1;  //CanPublishOwn
			}
			// Give these regardless of edit privelege, since if the do not have edit then they cannot access item form and save task anyway
			//if( !empty($flexi_rules['core.edit'][$group->id]) || !empty($flexi_rules['core.edit.own'][$group->id])) {
			if( 1 ) {
				if (in_array('flexicontent.change.cat', $flexi_action_names)) $flexi_rules['flexicontent.change.cat'][$group->id] = 1;  // CanChangeCat
				if (in_array('flexicontent.change.cat.sec', $flexi_action_names)) $flexi_rules['flexicontent.change.cat.sec'][$group->id] = 1;  // CanChangeSecCat
				if (in_array('flexicontent.change.cat.feat', $flexi_action_names)) $flexi_rules['flexicontent.change.cat.feat'][$group->id] = 1;  // CanChangeFeatCat
				if (in_array('flexicontent.uploadfiles', $flexi_action_names)) $flexi_rules['flexicontent.uploadfiles'][$group->id] = 1;  // CanUploadFiles
			}
			// By default give to everybody the edit field values privelege
			if (in_array('flexicontent.editfieldvalues', $flexi_action_names)) $flexi_rules['flexicontent.editfieldvalues'][$group->id] = 1;  //CanEditFieldValues
		}
		
		// return rules, a NOTE: MAYBE in future we create better initial permissions by checking allow/deny/inherit values instead of just HAS ACTION ...
		return $flexi_rules;
	}
	
	/**
	 * Get a list of the user groups.
	 *
	 * @return  array
	 * @since   11.1
	 */
	protected function _getUserGroups()
	{
		// Initialise variables.
		$query	= $this->_db->getQuery(true);
		$query->select('a.id, a.title, COUNT(DISTINCT b.id) AS level, a.parent_id')
			->from('#__usergroups AS a')
			->leftJoin('#__usergroups AS b ON a.lft > b.lft AND a.rgt < b.rgt')
			->group('a.id')
			->order('a.lft ASC');

		$this->_db->setQuery($query);
		$options = $this->_db->loadObjectList();

		return $options;
	}

	/**
	 * Get the parent asset id for the record
	 *
	 * @param   JTable   $table  A JTable object for the asset parent.
	 * @param   object  $data     
	 * 
	 * @return  integer  The id of the asset's parent
	 */
	protected function _getAssetParentId($table = null, $data = null)
	{
		// Initialise variables.
		$assetId = null;
		static $comp_assetid = null;
		
		// Get asset id of the component, if we have done already
		if ( $comp_assetid===null ) {
			$query	= $this->_db->getQuery(true)
				->select('id')
				->from('#__assets')
				->where('name = '.$this->_db->quote(JRequest::getCmd('option')));
			$this->_db->setQuery($query);
			$comp_assetid = (int) $this->_db->loadResult();
		}

		// This is a category under a category with id 'parent_id', or an item assigned to a category with id 'parent_id' (... see query above)
		if ($data->parent_id > 1) {
			// Build the query to get the asset id for the parent category.
			$query	= $this->_db->getQuery(true);
			$query->select('asset_id');
			$query->from('#__categories');
			$query->where('id = '.(int) $data->parent_id);

			// Get the asset id from the database.
			$this->_db->setQuery($query);
			if ($result = $this->_db->loadResult()) {
				$assetId = (int) $result;
			}
		}
		
		// This is a category that needs to parent with the extension.
		if ($assetId === null || $assetId === false) {
			$assetId = $comp_assetid;
		}
		
		// Return the asset id.
		return $assetId;
		/*if ($assetId) {
			return $assetId;
		} else {
			return parent::_getAssetParentId($table, $id);
		}*/
	}
}
?>
