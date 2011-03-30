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
	 * Method to get waiting for approval items data
	 *
	 * @access public
	 * @return array
	 */
	function getPending() {
		$permission = FlexicontentHelperPerm::getPerm();
		$user = &JFactory::getUser();
		$allitems	= !JAccess::check($user->id, 'core.admin', 'root.1') ? $permission->DisplayAllItems : 1;
		
		$query = 'SELECT c.id, c.title, c.catid, c.created_by'
				. ' FROM #__content as c'
				. ' JOIN #__categories as cat ON c.id=cat.id'
				. ' WHERE state = -3'
				. ' AND cat.lft >= ' . (int)FLEXI_CATEGORY_LFT . ' AND cat.rgt <= ' . (int)FLEXI_CATEGORY_RGT
				. ($allitems ? '' : ' AND c.created_by = '.$user->id)
				. ' ORDER BY c.created DESC'
				;

		$this->_db->SetQuery($query, 0, 5);
		$genstats = $this->_db->loadObjectList();
		
		return $genstats;
	}
	
	/**
	 * Method to get open questions data
	 *
	 * @access public
	 * @return array
	 */
	function getOpenquestions() {
		$permission = FlexicontentHelperPerm::getPerm();
		$user	= &JFactory::getUser();
		$allitems	= !JAccess::check($user->id, 'core.admin', 'root.1') ? $permission->DisplayAllItems : 1;

		$query = 'SELECT c.id, c.title, c.catid, c.created_by'
				. ' FROM #__content as c'
				. ' JOIN #__categories as cat ON c.id=cat.id'
				. ' WHERE c.state = -4'
				. ' AND cat.lft >= ' . (int)FLEXI_CATEGORY_LFT . ' AND cat.rgt <= ' . (int)FLEXI_CATEGORY_RGT
				. ($allitems ? '' : ' AND c.created_by = '.$user->id)
				. ' ORDER BY c.created DESC'
				;

		$this->_db->SetQuery($query, 0, 5);
		$genstats = $this->_db->loadObjectList();
		
		return $genstats;
	}

	/**
	 * Method to get in progress items data
	 *
	 * @access public
	 * @return array
	 */
	function getInprogress() {
		$permission = FlexicontentHelperPerm::getPerm();
		$user = &JFactory::getUser();
		$allitems	= !JAccess::check($user->id, 'core.admin', 'root.1') ? $permission->DisplayAllItems : 1;

		$query = 'SELECT c.id, c.title, c.catid, c.created_by'
				. ' FROM #__content as c'
				. ' JOIN #__categories as cat ON c.id=cat.id'
				. ' WHERE c.state = -5'
				. ' AND cat.lft >= ' . (int)FLEXI_CATEGORY_LFT . ' AND cat.rgt <= '. (int)FLEXI_CATEGORY_RGT
				. ($allitems ? '' : ' AND c.created_by = '.$user->id)
				. ' ORDER BY c.created DESC'
				;

		$this->_db->SetQuery($query, 0, 5);
		$genstats = $this->_db->loadObjectList();
		
		return $genstats;
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
			
			$itemdir 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'items'.DS.'tmpl');
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
							$fieldstopos = $this->_db->loadResultArray();
							
							if ($fieldstopos) {
								$field = implode(',', $fieldstopos);

								$query = 'INSERT INTO #__flexicontent_templates (`template`, `layout`, `position`, `fields`) VALUES(' . $this->_db->Quote($folder) . ',' . $this->_db->Quote($view) . ',' . $this->_db->Quote($group) . ',' . $this->_db->Quote($field) . ')';
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
				. ' FROM #__categories'
				. ' WHERE lft> ' . FLEXI_CATEGORY_LFT . ' AND rgt<' . FLEXI_CATEGORY_RGT
				;
		$this->_db->setQuery( $query );
		$count = $this->_db->loadResult();
			
		if ($count > 0) {
			return true;
		}
		return false;
	}

	/**
	 * Method to check if FLEXI_CATEGORY still exists
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistsec() {
		if (FLEXI_CATEGORY) {
			$query = 'SELECT COUNT( id )'
			. ' FROM #__sections'
			. ' WHERE id = ' . FLEXI_CATEGORY
			;
			$this->_db->setQuery( $query );
			$count = $this->_db->loadResult();
				
			if ($count > 0) {
				return true;
			} else {
				// Save the created category as flexi_category for the component
				$component =& JComponentHelper::getParams('com_flexicontent');
				$component->set('flexi_category', '');
				$cparams = $component->toString();

				$flexi =& JComponentHelper::getComponent('com_flexicontent');

				$query 	= 'UPDATE #__components'
						. ' SET params = ' . $this->_db->Quote($cparams)
						. ' WHERE id = ' . $flexi->id;
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
		$component =& JComponentHelper::getComponent('com_flexicontent');

		$menus	= &JApplication::getMenu('site', array());
		$items	= $menus->getItems('component_id', $component->id);
		if (count($items) > 0) {
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
		if (!FLEXI_CATEGORY) return array();

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
		if (!FLEXI_CATEGORY) return false;
		return FLEXIUtilities::currentMissing();
	}
	function addCurrentVersionData() {
		// check if the section was chosen to avoid adding data on static contents
		if (!FLEXI_CATEGORY) return true;

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
				." WHERE cat.lft >= '".FLEXI_CATEGORY_LFT."' AND cat.rgt <= '".FLEXI_CATEGORY_RGT."';";

		$db->setQuery($query);
		$rows = $db->loadObjectList('id');
		$diff_arrays = $this->getDiffVersions();
		//echo "<xmp>";var_dump(FLEXIUtilities::currentMissing());echo "</xmp>";
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
		$query = "SELECT rules FROM #__assets WHERE name='com_flexicontent';";
		$db->setQuery($query);
		$rules = $db->loadResult();
		$rule = new JRules($rules);
		
		$query = "SELECT count(asset_id) FROM #__flexicontent_fields WHERE asset_id='0';";
		$db->setQuery($query);
		$num = (int)$db->loadResult();
		return ((count($rule->getData())>0) && ($num===0));
	}
	function initialPermission() {
		jimport('joomla.access.rules');
		$db = &JFactory::getDBO();

		$query = "SELECT rules FROM #__assets WHERE name='com_flexicontent';";
		$db->setQuery($query);
		$rules = $db->loadResult();
		$rule = new JRules($rules);
		$query = "SELECT * FROM #__usergroups";
		$db->setQuery($query);
		$groups = $db->loadObjectList();
		if(count($rule->getData())<=0) {
			
			$rules = array();
			$rule = array();
			foreach($groups as $g) {
				//{"core.admin":{"7":1},"core.manage":{"6":1},"core.create":{"3":1},"core.delete":[],"core.edit":{"4":1},"core.edit.state":{"5":1},"core.edit.own":[]}
				if(JAccess::checkGroup($g->id, 'core.admin')) {//super user
					$rule['flexicontent.admin'][$g->id] = 1;//can config
				}
				if(JAccess::checkGroup($g->id, 'core.manage')) {
					$rule['flexicontent.manage'][$g->id] = 1;//CanRights
					$rule['flexicontent.manageitem'][$g->id] = 1;
					$rule['flexicontent.managetype'][$g->id] = 1;//CanTypes
					$rule['flexicontent.createtype'][$g->id] = 1;
					$rule['flexicontent.deletetype'][$g->id] = 1;
					$rule['flexicontent.edittype'][$g->id] = 1;
					$rule['flexicontent.edittype.state'][$g->id] = 1;
					$rule['flexicontent.fields'][$g->id] = 1;//CanFields
					$rule['flexicontent.archives'][$g->id] = 1;//CanArchives
					$rule['flexicontent.stats'][$g->id] = 1;//CanStats
					$rule['flexicontent.templates'][$g->id] = 1;//CanTemplates
					$rule['flexicontent.versioning'][$g->id] = 1;//CanVersion
					$rule['flexicontent.tags'][$g->id] = 1;//CanTags
					$rule['flexicontent.usetags'][$g->id] = 1;//CanUseTags
					$rule['flexicontent.newtag'][$g->id] = 1;//CanNewTag
					$rule['flexicontent.order'][$g->id] = 1;//CanOrder
					$rule['flexicontent.copyitems'][$g->id] = 1;//CanCopy
					$rule['flexicontent.paramsitem'][$g->id] = 1;//CanParams
					$rule['flexicontent.displayallitems'][$g->id] = 1;//DisplayAllItems
					$rule['flexicontent.managecat'][$g->id] = 1;//CanCats
					$rule['flexicontent.usercats'][$g->id] = 1;//CanUserCats
					$rule['flexicontent.viewtree'][$g->id] = 1;//CanViewTree
					$rule['flexicontent.createcat'][$g->id] = 1;//CanAddCats
					$rule['flexicontent.editallcat'][$g->id] = 1;//CanEditAllCats
					$rule['flexicontent.deleteallcat'][$g->id] = 1;
					$rule['flexicontent.deleteown'][$g->id] = 1;
					$rule['flexicontent.editallcat.state'][$g->id] = 1;//CanPublishAllCats
					$rule['flexicontent.editown'][$g->id] = 1;
					$rule['flexicontent.edit.state'][$g->id] = 1;
					$rule['flexicontent.editown.state'][$g->id] = 1;
					$rule['flexicontent.deletecat'][$g->id] = 1;
					$rule['flexicontent.deleteowncat'][$g->id] = 1;
					$rule['flexicontent.editcat'][$g->id] = 1;
					$rule['flexicontent.editcat.state'][$g->id] = 1;
					$rule['flexicontent.editowncat.state'][$g->id] = 1;
					$rule['flexicontent.editowncat'][$g->id] = 1;
					$rule['flexicontent.managefile'][$g->id] = 1;//CanFiles
					$rule['flexicontent.uploadfiles'][$g->id] = 1;//CanUpload
					$rule['flexicontent.viewallfiles'][$g->id] = 1;//CanViewAllFiles
				}
				if(JAccess::checkGroup($g->id, 'core.create')) {
					$rule['flexicontent.create'][$g->id] = 1;//CanAdd
				}
				if(JAccess::checkGroup($g->id, 'core.delete')) {
					$rule['flexicontent.deleteall'][$g->id] = 1;//CanDelete
				}
				if(JAccess::checkGroup($g->id, 'core.edit')) {
					$rule['flexicontent.editall'][$g->id] = 1;//CanEdit
				}
				if(JAccess::checkGroup($g->id, 'core.edit.state')) {
					$rule['flexicontent.editall.state'][$g->id] = 1;//CanPublish
				}
				if(JAccess::checkGroup($g->id, 'core.edit.own')) {
					$rule['flexicontent.editown'][$g->id] = 1;
				}
			}
			foreach($rule as $key=>$ar) {
				$rules[$key] = new JRule($ar);
			}
			$jrules = new JRules($rules);
		
			$query = "SELECT id FROM #__assets WHERE name='com_flexicontent';";
			$db->setQuery($query);
			$id = $db->loadResult();
			if($id) {
				$query = "UPDATE #__assets SET rules='".$jrules->__toString()."' WHERE id='{$id}';";
				$db->setQuery($query);
				if(!$db->query()) return false;
			}
		}
		$query = "SELECT id FROM #__flexicontent_fields WHERE asset_id='0';";
		$db->setQuery($query);
		$rows = $db->loadResultArray();
		if(count($rows)>0) {
			$asset	= JTable::getInstance('Asset');
			$parentId = 1;
			foreach($rows as $id) {
				$name = "flexicontent.field.{$id}";
				$query = "SELECT id FROM #__assets WHERE name='{$name}';";
				$db->setQuery($query);
				$asset_id = $db->loadResult();
				if(!$asset_id) {
					$asset->loadByName($name);
					$asset->id = null;
					if ($asset->parent_id != $parentId) {
						$asset->setLocation($parentId, 'last-child');
					}
					// Prepare the asset to be stored.
					$asset->parent_id	= $parentId;
					$asset->name		= $name;
					$asset->title		= $name;
					$rules = array();
					$rule = array();
					foreach($groups as $g) {
						$rule['flexicontent.readfield'][$g->id] = 1;
					}
					$rules['flexicontent.readfield'] = new JRule($rule['flexicontent.readfield']);
					$jrules = new JRules($rules);
					$asset->rules = $jrules->__toString();
					if (!$asset->check() || !$asset->store(false)) {
						return false;
					}
					$asset_id = $asset->id;
				}
				$query = "UPDATE #__flexicontent_fields SET asset_id='{$asset_id}' WHERE id='{$id}';";
				$db->setQuery($query);
				$db->query();
			}
		}
		return true;
	}
}
?>
