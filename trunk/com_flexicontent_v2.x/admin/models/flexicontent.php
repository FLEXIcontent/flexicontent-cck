<?php
/**
 * @version 1.5 stable $Id: flexicontent.php 262 2010-06-11 05:02:20Z enjoyman $
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
 * @since		1.0
 */
class FlexicontentModelFlexicontent extends JModel
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
	function getPending() {
		$permission = FlexicontentHelperPerm::getPerm();
		$user = &JFactory::getUser();
		$allitems	= !$permission->CanConfig ? $permission->DisplayAllItems : 0;
		
		$query = 'SELECT SQL_CALC_FOUND_ROWS c.id, c.title, c.catid, c.created_by'
				. ' FROM #__content as c'
				. ' LEFT JOIN #__categories as cat ON c.catid=cat.id'
				. ' WHERE state = -3'
				. ' AND cat.extension="'.FLEXI_CAT_EXTENSION.'" ' //AND cat.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND cat.rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
				. ($allitems ? '' : ' AND c.created_by = '.$user->id)
				. ' ORDER BY c.created DESC'
				;

		$this->_db->SetQuery($query, 0, 5);
		$genstats = $this->_db->loadObjectList();
		
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
		$permission = FlexicontentHelperPerm::getPerm();
		$user = &JFactory::getUser();
		$allitems	= !$permission->CanConfig ? $permission->DisplayAllItems : 0;
		
		$query = 'SELECT SQL_CALC_FOUND_ROWS c.id, c.title, c.catid, c.created_by, c.version, MAX(fv.version_id) '
				. ' FROM #__content AS c'
				. ' LEFT JOIN #__flexicontent_versions AS fv ON c.id=fv.item_id'
				. ' LEFT JOIN #__categories as cat ON c.catid=cat.id'
				. ' WHERE c.state = -5 OR c.state = 1'
				. ' AND cat.extension="'.FLEXI_CAT_EXTENSION.'" ' //AND cat.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND cat.rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
				. ($allitems ? '' : ' AND c.created_by = '.$user->id)
				. ' GROUP BY fv.item_id '
				. ' HAVING c.version<>MAX(fv.version_id) '
				. ' ORDER BY c.created DESC'
				;

		$this->_db->SetQuery($query, 0, 5);
		$genstats = $this->_db->loadObjectList();
		
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
		$permission = FlexicontentHelperPerm::getPerm();
		$user	= &JFactory::getUser();
		$allitems	= !$permission->CanConfig ? $permission->DisplayAllItems : 0;

		$query = 'SELECT SQL_CALC_FOUND_ROWS c.id, c.title, c.catid, c.created_by'
				. ' FROM #__content as c'
				. ' LEFT JOIN #__categories as cat ON c.catid=cat.id'
				. ' WHERE c.state = -4'
				. ' AND cat.extension="'.FLEXI_CAT_EXTENSION.'" ' //AND cat.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND cat.rgt <= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY)
				. ($allitems ? '' : ' AND c.created_by = '.$user->id)
				. ' ORDER BY c.created DESC'
				;

		$this->_db->SetQuery($query, 0, 5);
		$genstats = $this->_db->loadObjectList();
		
		return $genstats;
	}

	/**
	 * Method to get items in progress state, (published but) waiting to be completed
	 *
	 * @access public
	 * @return array
	 */
	function getInprogress() {
		$permission = FlexicontentHelperPerm::getPerm();
		$user = &JFactory::getUser();
		$allitems	= !$permission->CanConfig ? $permission->DisplayAllItems : 0;

		$query = 'SELECT SQL_CALC_FOUND_ROWS c.id, c.title, c.catid, c.created_by'
				. ' FROM #__content as c'
				. ' LEFT JOIN #__categories as cat ON c.catid=cat.id'
				. ' WHERE c.state = -5'
				. ' AND cat.extension="'.FLEXI_CAT_EXTENSION.'" ' //AND cat.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND cat.rgt <= '. $this->_db->Quote(FLEXI_RGT_CATEGORY)
				. ($allitems ? '' : ' AND c.created_by = '.$user->id)
				. ' ORDER BY c.created DESC'
				;

		$this->_db->SetQuery($query, 0, 5);
		$genstats = $this->_db->loadObjectList();
		
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
		$params =& JComponentHelper::getParams('com_flexicontent');
		if ($params) {
			$_component_default_menuitem_id = $params->get('default_menu_itemid', false);
		} else {
			$_component_default_menuitem_id = '';
		}
		
		/*$menus	= &JApplication::getMenu('site', array());
		$menuitem = $menus->getItem($_component_default_menuitem_id);
		if (!$menuitem || $menuitem->component != 'com_flexicontent') {
			return false;
		}*/
		
		$flexi =& JComponentHelper::getComponent('com_flexicontent');
		$query 	=	"SELECT COUNT(*) FROM #__menu WHERE `type`='component' AND `published`=1 AND `component_id`='{$flexi->id}' AND id='{$_component_default_menuitem_id}'";
		$this->_db->setQuery($query);
		$count = $this->_db->loadResult();
			
		if (!$count) {
			return false;
		}
		return true;
	}
	
	/**
	 * Method to check if there is at least one type created
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistType() {
		static $return;
		if($return===NULL) {
			$return = false;
			$query = 'SELECT COUNT( id )'
			. ' FROM #__flexicontent_types'
			;
			$this->_db->setQuery( $query );
			$count = $this->_db->loadResult();
			if ($count > 0) {
				$return = true;
			}
		}
		return $return;
	}

	/**
	 * Method to check if there is at least the default fields
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistFields()
	{
		static $return;
		if($return===NULL) {
			$return = false;
			$query = 'SELECT COUNT( id )'
			. ' FROM #__flexicontent_fields'
			;
			$this->_db->setQuery( $query );
			$count = $this->_db->loadResult();
				
			if ($count > 13) {
				$return = true;
			}
		}
		return $return;
	}

	/**
	 * Method to check if there is at least the default fields
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistFieldsPlugins()
	{
		$query = 'SELECT COUNT( extension_id )'
		. ' FROM #__extensions'
		. ' WHERE `type`= '.$this->_db->Quote('plugin').' AND folder = ' . $this->_db->Quote('flexicontent_fields')
		;
		$this->_db->setQuery( $query );
		$count = $this->_db->loadResult();
			
		if ($count > 13) {
			return true;
		}
		return false;
	}

	/**
	 * Method to check if the search plugin is installed
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistSearchPlugin()
	{
		$query = 'SELECT COUNT( extension_id )'
		. ' FROM #__extensions'
		. ' WHERE `type`='.$this->_db->Quote('plugin').' AND element = ' . $this->_db->Quote('flexisearch')
		;
		$this->_db->setQuery( $query );
		return $this->_db->loadResult() ? true : false;
	}

	/**
	 * Method to check if the system plugin is installed
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistSystemPlugin()
	{
		$query = 'SELECT COUNT( extension_id )'
		. ' FROM #__extensions'
		. ' WHERE `type`='.$this->_db->Quote('plugin').' AND element = ' . $this->_db->Quote('flexisystem')
		;
		$this->_db->setQuery( $query );
		return $this->_db->loadResult() ? true : false;
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
		if($return === NULL) {
			$query 	= 'SELECT COUNT( extension_id )'
					. ' FROM #__extensions'
					. ' WHERE `type`='.$this->_db->Quote('plugin').' AND ( folder = ' . $this->_db->Quote('flexicontent_fields')
					. ' OR element = ' . $this->_db->Quote('flexisearch')
					. ' OR element = ' . $this->_db->Quote('flexisystem') . ')'
					. ' AND enabled <> 1'
					;
			$this->_db->setQuery( $query );
			$return = $this->_db->loadResult() ? false : true;
		}
		return $return;
	}

	/**
	 * Method to check if the language column exists
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistLanguageColumn()
	{
		static $return;
		if($return === NULL) {
			$fields = $this->_db->getTableFields('#__flexicontent_items_ext');
			$return = (array_key_exists('language', $fields['#__flexicontent_items_ext'])) ? true : false;
		}
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
		if($return === NULL) {
			$query = 'SHOW TABLES LIKE ' . $this->_db->Quote('%flexicontent_versions');
			$this->_db->setQuery($query);
			$return = $this->_db->loadResult() ? true : false;
		}
		return $return;
	}

	/**
	 * Method to check if the system plugin is installed
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistVersionsPopulated() {
		static $return;
		if($return === NULL) {
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
		// Open phpThumb cache directory
		$phpthumbcache 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'cache');
		return (JPath::getPermissions($phpthumbcache) == 'rwxrwxrwx') ? true : false;
	}

	/**
	 * Method to check if the files from beta3 still exist in the category and item view
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getOldBetaFiles() {
		static $return;
		if($return===NULL) {
			$files 	= array (
				'default.xml',
				'default.php',
				'index.html',
				'form.php',
				'form.xml'
				);
			$catdir 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'category'.DS.'tmpl');
			$cattmpl 	= JFolder::files($catdir);		
			$ctmpl 		= array_diff($cattmpl,$files);
			
			$itemdir 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'item'.DS.'tmpl');
			$itemtmpl 	= JFolder::files($itemdir);		
			$itmpl 		= array_diff($itemtmpl,$files);
			
			$return = ($ctmpl || $itmpl) ? false : true;
		}
		return $return;
	}

	/**
	 * Method to check if the field positions were converted
	 * and if not, convert them
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getFieldsPositions()
	{
		$query 	= "SELECT name, positions"
				. " FROM #__flexicontent_fields"
				. " WHERE positions <> ''"
				;
		$this->_db->setQuery( $query );
		$fields = $this->_db->loadObjectList();
		
		if ($fields) {
			// create a temporary table to store the positions
			$query = "
					CREATE TABLE IF NOT EXISTS #__flexicontent_positions_tmp (
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
							$fieldstopos = $this->_db->loadResultArray();
							
							if ($fieldstopos) {
								$field = implode(',', $fieldstopos);

								$query = 'REPLACE INTO #__flexicontent_templates (`template`, `layout`, `position`, `fields`) VALUES(' . $this->_db->Quote($folder) . ',' . $this->_db->Quote($view) . ',' . $this->_db->Quote($group) . ',' . $this->_db->Quote($field) . ')';
								$this->_db->setQuery($query);
								$this->_db->query();
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
		}
		return $fields;
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
		if($return===NULL) {
			$query 	= 'SELECT COUNT( item_id )'
					. ' FROM #__flexicontent_fields_item_relations'
					. ' WHERE field_id < 13'
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
	function getExistcat() {
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
	 * Method to check if FLEXI_CAT_EXTENSION still exists
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistsec() {
		if (FLEXI_CAT_EXTENSION) {
			$query = 'SELECT COUNT( id )'
			. ' FROM #__categories'
			. ' WHERE id = 1 AND extension=\'system\''
			;
			$this->_db->setQuery( $query );
			$count = $this->_db->loadResult();
				
			if ($count > 0) {
				return true;
			} else {
				die("Category table corrupterd, SYSTEM root category not found");
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
		$component =& JComponentHelper::getComponent('com_flexicontent');
		
		// This code doesn't work as in J1.5 ...
		/*$menus	= &JApplication::getMenu('site', array());
		$items	= $menus->getItems('component_id', $component->id);*/
		
		$flexi =& JComponentHelper::getComponent('com_flexicontent');
		$query 	=	"SELECT COUNT(*) FROM #__menu WHERE `type`='component' AND `published`=1 AND `component_id`='{$flexi->id}' ";
		$this->_db->setQuery($query);
		$count = $this->_db->loadResult();
		
		if ($count > 0) {
			return true;
		}
		return false;
	}



	/**
	 * Fetch the version from the flexicontent.org server
	 * TODO: Cleanup
	 */
	function getUpdate()
	{
		$url = 'http://update.flexicontent.org/flexicontent_update.xml';
		$data = '';
		$check = array();
		$check['connect'] = 0;
		$check['version_current'] = '1.5.1';
		$check['versionread_current'] = '1.5.1';

		//try to connect via cURL
		if(function_exists('curl_init') && function_exists('curl_exec')) {
			$ch = @curl_init();
			
			@curl_setopt($ch, CURLOPT_URL, $url);
			@curl_setopt($ch, CURLOPT_HEADER, 0);
			//http code is greater than or equal to 300 ->fail
			@curl_setopt($ch, CURLOPT_FAILONERROR, 1);
			@curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			//timeout of 5s just in case
			@curl_setopt($ch, CURLOPT_TIMEOUT, 5);
						
			$data = @curl_exec($ch);
						
			@curl_close($ch);
		}

		//try to connect via fsockopen
		if(function_exists('fsockopen') && $data == '') {

			$errno = 0;
			$errstr = '';

			//timeout handling: 5s for the socket and 5s for the stream = 10s
			$fsock = @fsockopen("update.flexicontent.org", 80, $errno, $errstr, 5);
		
			if ($fsock) {
				@fputs($fsock, "GET /flexicontent_update.xml HTTP/1.1\r\n");
				@fputs($fsock, "HOST: update.flexicontent.org\r\n");
				@fputs($fsock, "Connection: close\r\n\r\n");

				//force stream timeout...bah so dirty
				@stream_set_blocking($fsock, 1);
				@stream_set_timeout($fsock, 5);
				 
				$get_info = false;
				while (!@feof($fsock))
				{
					if ($get_info)
					{
						$data .= @fread($fsock, 1024);
					}
					else
					{
						if (@fgets($fsock, 1024) == "\r\n")
						{
							$get_info = true;
						}
					}
				}
				@fclose($fsock);
				
				//need to check data cause http error codes aren't supported here
				if(!strstr($data, '<?xml version="1.0" encoding="utf-8"?><update>')) {
					$data = '';
				}
			}
		}

		//try to connect via fopen
		if (function_exists('fopen') && ini_get('allow_url_fopen') && $data == '') {
		
			//set socket timeout
			ini_set('default_socket_timeout', 5);
			
			$handle = @fopen ($url, 'r');
			
			//set stream timeout
			@stream_set_blocking($handle, 1);
			@stream_set_timeout($handle, 5);
			
			$data	= @fread($handle, 1000);
			
			@fclose($handle);
		}
						
		/* try to connect via file_get_contents..k..a bit stupid
		if(function_exists('file_get_contents') && ini_get('allow_url_fopen') && $data == '') {
			$data = @file_get_contents($url);
		}
		*/
		
		if( $data && strstr($data, '<?xml version="1.0" encoding="utf-8"?><update>') ) {
			$xml = & JFactory::getXMLparser('Simple');
			$xml->loadString($data);
			
			$version 				= & $xml->document->version[0];
			$check['version'] 		= & $version->data();
			$versionread 			= & $xml->document->versionread[0];
			$check['versionread'] 	= & $versionread->data();
			$released 				= & $xml->document->released[0];
			$check['released'] 		= & $released->data();
			$check['connect'] 		= 1;
			$check['enabled'] 		= 1;
			
			$check['current'] 		= version_compare( $check['version_current'], $check['version'] );
		}
		
		return $check;
	}
	
	function getDiffVersions($current_versions=array(), $last_versions=array()) {
		// check if the category was chosen to avoid adding data on static contents
		if (!FLEXI_CAT_EXTENSION) return array();

		if(!$current_versions) {
			$current_versions = FLEXIUtilities::getCurrentVersions();
		}
		if(!$last_versions) {
			$last_versions = FLEXIUtilities::getLastVersions();
		}
		return diff_version($current_versions, $last_versions);
	}
	function checkCurrentVersionData() {
		// verify that every current version is in the versions table and it's data in the flexicontent_items_versions table
		//$and = "";

		// check if the section was chosen to avoid adding data on static contents
		if (!FLEXI_CAT_EXTENSION) return false;
		return FLEXIUtilities::currentMissing();
	}
	function addCurrentVersionData() {
		// check if the section was chosen to avoid adding data on static contents
		if (!FLEXI_CAT_EXTENSION) return true;

		// @TODO: move somewhere else
		$this->formatFlexiPlugins();
		
		//clean old categories cache.
		$catscache 	=& JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();
		// add the current version data
		$db 		= &$this->_db;
		$nullDate	= $db->getNullDate();
		$query = "SELECT c.id,c.catid,c.version,c.created,c.modified,c.created_by,c.introtext,c.`fulltext` FROM #__content as c"
				. " JOIN #__categories as cat ON c.catid=cat.id "
				." WHERE cat.extension='".FLEXI_CAT_EXTENSION."'";// ."AND cat.lft >= ".$this->_db->Quote(FLEXI_LFT_CATEGORY)." AND cat.rgt <= ".$this->_db->Quote(FLEXI_RGT_CATEGORY).";";

		$db->setQuery($query); 
		$rows = $db->loadObjectList('id');
		$diff_arrays = $this->getDiffVersions();
		$query = "SELECT c.id,c.version,iv.version as iversion FROM #__content as c " .
			" LEFT JOIN #__flexicontent_items_versions as iv ON c.id=iv.item_id AND c.version=iv.version"
				. " JOIN #__categories as cat ON c.catid=cat.id "
				." WHERE cat.extension='".FLEXI_CAT_EXTENSION."' AND c.version > '1' AND iv.version IS NULL;";
		$db->setQuery($query);
		$newrows = $db->loadAssocList();
		foreach($newrows as $r) {
			$newrows[$r["id"]] = $r;
		}
		$diff_arrays = array_merge_recursive($diff_arrays, $newrows);
		foreach($diff_arrays as $row) {
			if(isset($row["id"]) && $row["id"] && isset($rows[$row["id"]])) {
				$query = "SELECT f.id,fir.value,f.field_type,f.name,fir.valueorder "
						." FROM #__flexicontent_fields_item_relations as fir"
						//." LEFT JOIN #__flexicontent_items_versions as iv ON iv.field_id="
						." LEFT JOIN #__flexicontent_fields as f on f.id=fir.field_id "
						." WHERE fir.item_id='".$row["id"]."';";

				$db->setQuery($query);
				$fields = $db->loadObjectList();
				$jcorefields = flexicontent_html::getJCoreFields();
				$catflag = false;
				$tagflag = false;
				$f = new stdClass();
				$f->id=1;
				$f->valueorder=1;
				$f->field_type="maintext";
				$f->name="text";
				 // append the text property to the object
				if (JString::strlen($rows[$row['id']]->fulltext) > 1) {
					$f->value = $rows[$row['id']]->introtext . '<hr id="system-readmore" />' . $rows[$row['id']]->fulltext;
				} else {
					$f->value = $rows[$row['id']]->introtext;
				}
				if(substr($f->value, 0, 3)!="<p>") {
					$f->value = "<p>".$f->value."</p>";
				}
				$fields[] = $f;
				foreach($fields as $field) {
					// process field mambots onBeforeSaveField
					//$results = $mainframe->triggerEvent('onBeforeSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));

					// add the new values to the database 
					$obj = new stdClass();
					$obj->field_id 		= $field->id;
					$obj->item_id 		= $row["id"];
					$obj->valueorder	= $field->valueorder;
					$obj->version		= (int)$rows[$row['id']]->version;
					// @TODO : move in the plugin code
					if( ($field->field_type=='categories') && ($field->name=='categories') ) {
						continue;
						//$obj->value = serialize($item->categories);
						//$catflag = true;
					}elseif( ($field->field_type=='tags') && ($field->name=='tags') ) {
						continue;
						//$obj->value = serialize($item->tags);
						//$tagflag = true;
					}else{
						$obj->value			= $field->value;
					}
					//echo "version: ".$obj->version.",fieldid : ".$obj->field_id.",value : ".$obj->value.",valueorder : ".$obj->valueorder."<br />";
					$db->insertObject('#__flexicontent_items_versions', $obj);
					//echo "insert into __flexicontent_items_versions<br />";
					if( !isset($jcorefields[$field->name]) && !in_array($field->field_type, $jcorefields)) {
						unset($obj->version);
						$db->insertObject('#__flexicontent_fields_item_relations', $obj);
						//echo "insert into __flexicontent_fields_item_relations<br />";
					}
					// process field mambots onAfterSaveField
					//$results		 = $dispatcher->trigger('onAfterSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));
					//$searchindex 	.= @$field->search;
				}
				if(!$catflag) {
					$query = "SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid='".$row["id"]."';";
					$db->setQuery($query);
					$categories = $db->loadResultArray();

					if(!$categories || !count($categories)) {
						$categories = array($catid = $rows[$row["id"]]->catid);
						$query = "INSERT INTO #__flexicontent_cats_item_relations VALUES('$catid','".$row["id"]."', '0');";
						$db->setQuery($query);
						$db->query();
					}
					$obj = new stdClass();
					$obj->field_id 		= 13;
					$obj->item_id 		= $row["id"];
					$obj->valueorder	= 1;
					$obj->version		= (int)$rows[$row["id"]]->version;
					$obj->value		= serialize($categories);
					$db->insertObject('#__flexicontent_items_versions', $obj);
					//unset($obj->version);
					//$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
				}
				if(!$tagflag) {
					$query = "SELECT tid FROM #__flexicontent_tags_item_relations WHERE itemid='".$row["id"]."';";
					$db->setQuery($query);
					$tags = $db->loadResultArray();
					$obj = new stdClass();
					$obj->field_id 		= 14;
					$obj->item_id 		= $row["id"];
					$obj->valueorder	= 1;
					$obj->version		= (int)$rows[$row["id"]]->version;
					$obj->value		= serialize($tags);
					$db->insertObject('#__flexicontent_items_versions', $obj);
					//unset($obj->version);
					//$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
				}
				$v = new stdClass();
				$v->item_id 		= (int)$row["id"];
				$v->version_id		= (int)$rows[$row["id"]]->version;
				$v->created 		= ($rows[$row['id']]->modified && ($rows[$row['id']]->modified != $nullDate)) ? $rows[$row['id']]->modified : $rows[$row['id']]->created;
				$v->created_by 		= $rows[$row['id']]->created_by;
				//$v->comment		= 'kept current version to version table.';
				//echo "insert into __flexicontent_versions<br />";
				$db->insertObject('#__flexicontent_versions', $v);
			}
		}
		return true;
	}
	
	function formatFlexiPlugins() {
		$db 	= & $this->_db;
		$query	= 'SELECT extension_id, name FROM #__extensions'
				. ' WHERE folder = ' . $db->Quote('flexicontent_fields')
				. ' AND `type`=' . $db->Quote('plugin')
				;
		$db->setQuery($query);
		$flexiplugins = $db->loadObjectList();
		foreach ($flexiplugins as $fp) {
			if (substr($fp->name, 0, 15) != 'FLEXIcontent - ') {
				$query = 'UPDATE #__extensions SET name = ' . $db->Quote('FLEXIcontent - '.$fp->name) . ' WHERE `type`='.$db->Quote('plugin').' AND extension_id = ' . (int)$fp->id;
				$db->setQuery($query);
				$db->Query();
			}
		}
	}
	
	function checkInitialPermission() {
		jimport('joomla.access.rules');
		$db = &JFactory::getDBO();
		
		// DELETE old namespace (flexicontent.*) permissions of v2.0beta, we do not try to rename them ... instead we will use com_content (for some of them),
		$query = $db->getQuery(true)->delete('#__assets')->where('name LIKE ' . $db->quote('flexicontent.%'));
		$db->setQuery($query);
		if(!$db->query()) return false;
		
		// SET Access View Level to public (=1) for fields that do not have their Level set
		$query = $db->getQuery(true)->update('#__flexicontent_fields')->set('access = 1')->where('access = 0');
		$db->setQuery($query);
		$db->query();
		
		// COUNT Component Section Actions, to check if we have in assets DB table the same number of actions as in access.xml file
		$query = $db->getQuery(true)
			->select('rules')
			->from('#__assets')
			->where('name = ' . $db->quote(JRequest::getCmd('option')));
		$db->setQuery($query);
		$rules = $db->loadResult();
		$rule = new JRules($rules);
		$comp_section = count($rule->getData()) == count(JAccess::getActions('com_flexicontent', 'component')) ? 1 : 0;
		
		// CHECK if some categories don't have permissions set
		$query = $db->getQuery(true)
			->select('c.id')
			->from('#__assets AS se')->join('RIGHT', '#__categories AS c ON se.id=c.asset_id AND se.name=concat("com_content.category.",c.id)')
			->where('se.id is NULL')->where('c.extension = ' . $db->quote('com_content'));
		$db->setQuery($query);
		$result = $db->loadObjectList();
		$category_section = count($result) == 0 ? 1 : 0;

		// CHECK if some fields don't have permissions set
		$query = $db->getQuery(true)
			->select('se.id')
			->from('#__assets AS se')->join('RIGHT', '#__flexicontent_fields AS ff ON se.id=ff.asset_id AND se.name=concat("com_flexicontent.field.",ff.id)')
			->where('se.id is NULL');
		$db->setQuery($query);
		$result = $db->loadObjectList();
		$field_section = count($result) == 0 ? 1 : 0;
		
		return ($comp_section && $category_section && $field_section);
	}
	
	function initialPermission() {
		jimport('joomla.access.rules');
		$component_name	= JRequest::getCmd('option');
		$db 		= JFactory::getDBO();
		$asset	= JTable::getInstance('asset');
		
		/*** Component assets ***/
		
		if (!$asset->loadByName($component_name)) {
			// The assets entry already exists, we will check if it has rules for all component's actions 
			$root = JTable::getInstance('asset');
			$root->loadByName('root.1');
			$asset->name = $component_name;
			$asset->title = $component_name;
			$asset->setLocation($root->id,'last-child');
						
			$groups 	= $this->_getUserGroups();
			$actions	= JAccess::getActions($component_name, 'component');
			$rules 		= $this->_initRules($component_name);
			
			$rules = new JRules(json_encode($rules));
			$asset->rules = $rules->__toString();

			if (!$asset->check() || !$asset->store()) {
				echo $asset->getError();
				$this->setError($asset->getError());
				return false;
			}
		} else {
			// The assets entry does not exist, we will add default rules for all component's actions 
			$rules = new JRules($asset->rules);
			if (count($rules->getData()) != count(JAccess::getActions('com_flexicontent', 'component'))) {
				$initerules = $this->_initRules($component_name);
				$initerules = new JRules(json_encode($initerules));
				
				$rules->merge($initerules);
						
				$asset->rules = $rules->__toString();

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
		
		/*** CATEGORY assets ***/ /*** THESE MAY NO LONGER BE NEEDED ? ***/
		
		// Get a list com_content categories that do not have assets
		$query = $db->getQuery(true)
			->select('c.id, c.parent_id, c.title')
			->from('#__assets AS se')->join('RIGHT', '#__categories AS c ON se.id=c.asset_id')
			->where('se.id is NULL')->where('c.extension = ' . $db->quote('com_content'))
			->order('c.lft ASC');
		$db->setQuery($query);
		$results = $db->loadObjectList();
		
		// Add an asset to category that doesnot have one
		if(count($results)>0) {
			foreach($results as $category) {
				$parentId = $this->_getAssetParentId(null, $category);
				$name = "com_content.category.{$category->id}";
				
				// Test if an asset for the current CATEGORY ID already exists and load it instead of creating a new asset
				if ( ! $asset->loadByName($name) ) {
				
					$asset->id 		= null;
					$asset->setLocation($parentId, 'last-child');
					$asset->name	= $name;
					$asset->title	= $category->title;

					if ($parentId == $component_asset->id) {				
						$actions	= JAccess::getActions($component_name, 'category');
						$rules 		= json_decode($component_asset->rules);		
						foreach ($actions as $action) {
							$catrules[$action->name] = $rules->{$action->name};
						}
						$rules = new JRules(json_encode($catrules));
						$asset->rules = $rules->__toString();
					} else {
						$parent = JTable::getInstance('asset');
						$parent->load($parentId);
						$asset->rules = $parent->rules;
					}
					
					if (!$asset->check() || !$asset->store(false)) {
						echo $asset->getError();
						$this->setError($asset->getError());
						return false;
					}
				}
				
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
		
		
		/*** FLEXIcontent FIELDS assets ***/
		
		// Get a list flexicontent fields that do not have assets
		$query = $db->getQuery(true)
			->select('ff.id, ff.name')
			->from('#__assets AS se')->join('RIGHT', '#__flexicontent_fields AS ff ON se.id=ff.asset_id AND se.name= concat("com_flexicontent.field.",ff.id)')
			->where('se.id is NULL');
		$db->setQuery($query);
		$results = $db->loadObjectList();
		
		// Add an asset to every field that doesnot have one
		if(count($results)>0) {
			foreach($results as $field) {
				$name = "com_flexicontent.field.{$field->id}";
				
				// Test if an asset for the current FIELD ID already exists and load it instead of creating a new asset
				if ( ! $asset->loadByName($name) ) {

					$asset->id = null;
					$asset->setLocation($component_asset->id, 'last-child');     // Permissions of fields are directly inheritted by component
					$asset->name		= $name;
					$asset->title		= $field->name;
					
					$actions	= JAccess::getActions($component_name, 'fields');
					$rules 		= json_decode($component_asset->rules);		
					foreach ($actions as $action) {
						$fieldrules[$action->name] = $rules->{$action->name};
					}
					$rules = new JRules(json_encode($fieldrules));
					$asset->rules = $rules->__toString();
					
					if (!$asset->check() || !$asset->store(false)) {
						echo $asset->getError();
						$this->setError($asset->getError());
						return false;
					}
				}
				
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

		return true;
	}

	protected function _initRules($component) {
		$groups 	= $this->_getUserGroups();
		$actions	= JAccess::getActions($component, 'component');
		$rules 		= array();

		foreach($groups as $group) {
			if(JAccess::checkGroup($group->id, 'core.admin')) { // Super User Privelege (can do anything, all other permissions are ignored)
				$rules['core.admin'][$group->id] = 1;  //CanConfig
			}
			if(JAccess::checkGroup($group->id, 'core.manage')) { // Backend Access Privelege (can access/manage the component in the backend)
				$rules['core.manage'][$group->id] = 1;  //CanManage
				foreach($actions as $action) {   // INITIALLY , WE WILL GIVE ALL PERMISSIONS TO ANYONE THAT CAN MANAGE THE COMPONENT
					if ($action->name == 'core.admin') continue;
					$rules[$action->name][$group->id] = 1;
				}
			}
			if(JAccess::checkGroup($group->id, 'core.create')) {
				$rules['core.create'][$group->id] = 1;//CanAdd
			}
			if(JAccess::checkGroup($group->id, 'core.delete')) {
				$rules['core.delete'][$group->id] = 1;//CanDelete
			}
			if(JAccess::checkGroup($group->id, 'core.edit')) {
				$rules['core.edit'][$group->id] = 1;//CanEdit
			}
			if(JAccess::checkGroup($group->id, 'core.edit.state')) {
				$rules['core.edit.state'][$group->id] = 1;//CanPublish
			}
			if(JAccess::checkGroup($group->id, 'core.edit.own')) {
				$rules['core.edit.own'][$group->id] = 1;//CanEditOwn
			}
			$rules['flexicontent.readfield'][$group->id] = 1;//CanViewField
		}
		
		return $rules;
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
		$db		= JFactory::getDBO();
		$query	= $db->getQuery(true);
		$query->select('a.id, a.title, COUNT(DISTINCT b.id) AS level, a.parent_id')
			->from('#__usergroups AS a')
			->leftJoin($query->qn('#__usergroups').' AS b ON a.lft > b.lft AND a.rgt < b.rgt')
			->group('a.id')
			->order('a.lft ASC');

		$db->setQuery($query);
		$options = $db->loadObjectList();

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
		$assetId 	= null;
		$db			= JFactory::getDbo();

		// This is a category under a category.
		if ($data->parent_id > 1) {
			// Build the query to get the asset id for the parent category.
			$query	= $db->getQuery(true);
			$query->select('asset_id');
			$query->from('#__categories');
			$query->where('id = '.(int) $data->parent_id);

			// Get the asset id from the database.
			$db->setQuery($query);
			if ($result = $db->loadResult()) {
				$assetId = (int) $result;
			}
		}
		// This is a category that needs to parent with the extension.
		elseif ($assetId === null) {
			// Build the query to get the asset id for the parent category.
			$query	= $db->getQuery(true)
				->select('id')
				->from('#__assets')
				->where('name = '.$db->quote(JRequest::getCmd('option')));
			$db->setQuery($query);
			if ($result = $db->loadResult()) {
				$assetId = (int) $result;
			}
		}

		// Return the asset id.
		if ($assetId) {
			return $assetId;
		} else {
			return parent::_getAssetParentId($table, $id);
		}
	}
}
?>
