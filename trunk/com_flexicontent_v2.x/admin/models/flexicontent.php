<?php
/**
 * @version 1.5 stable $Id: flexicontent.php 1216 2012-03-22 04:06:29Z ggppdk $
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
	function getPending()
	{
		$user = & JFactory::getUser();
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$allitems	= $permission->DisplayAllItems;
		} else if (FLEXI_ACCESS) {
			$allitems	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'displayallitems', 'users', $user->gmid) : 1;
		} else {
			$allitems 	= 1;
		}
		
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
		$user = & JFactory::getUser();
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$allitems	= $permission->DisplayAllItems;
		} else if (FLEXI_ACCESS) {
			$allitems	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'displayallitems', 'users', $user->gmid) : 1;
		} else {
			$allitems 	= 1;
		}
		
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
		$user = & JFactory::getUser();
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$allitems	= $permission->DisplayAllItems;
		} else if (FLEXI_ACCESS) {
			$allitems	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'displayallitems', 'users', $user->gmid) : 1;
		} else {
			$allitems 	= 1;
		}

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
	function getInprogress()
	{
		$user = & JFactory::getUser();
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$allitems	= $permission->DisplayAllItems;
		} else if (FLEXI_ACCESS) {
			$allitems	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'displayallitems', 'users', $user->gmid) : 1;
		} else {
			$allitems 	= 1;
		}

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
		// Try to get id of Flexicontent component
		$flexi =& JComponentHelper::getComponent('com_flexicontent');
		$flexi_comp_id = $flexi->id;
		// Try to get params of Flexicontent component, and then 'default_menu_itemid' parameter
		$params =& JComponentHelper::getParams('com_flexicontent');
		if ($params) {
			$_component_default_menuitem_id = $params->get('default_menu_itemid', false);
		} else {
			$_component_default_menuitem_id = '';
		}
		
		$query 	= 'SELECT COUNT( * )'
				. ' FROM #__menu as m'
				. ' WHERE m.published=1 AND m.id="'.$_component_default_menuitem_id.'"'
				. ' AND m.access=1 AND m.component_id="'.$flexi_comp_id.'" AND m.type="component" '
				;
		$this->_db->setQuery( $query );
		$count = $this->_db->loadResult();
			
		if ($count >= 1) {
			return true;
		}
		return false;
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
				. ' WHERE `type`='.$this->_db->Quote('plugin').' AND '
				. ' ( folder = ' . $this->_db->Quote('flexicontent_fields')
				. ' OR element = ' . $this->_db->Quote('flexisearch')
				. ' OR element = ' . $this->_db->Quote('flexisystem')
				. ' OR element = ' . $this->_db->Quote('flexiadvsearch')
				. ' OR element = ' . $this->_db->Quote('flexiadvroute') . ')'
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
			$result_lang_col = (array_key_exists('language', $fields['#__flexicontent_items_ext'])) ? true : false;
			$result_tgrp_col = (array_key_exists('lang_parent_id', $fields['#__flexicontent_items_ext'])) ? true : false;
		}
		return $result_lang_col && $result_tgrp_col;
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
		if($return === NULL) {
			$enable_language_groups = JComponentHelper::getParams( 'com_flexicontent' )->get("enable_language_groups") && ( FLEXI_J16GE || FLEXI_FISH ) ;
			$db =& JFactory::getDBO();
			$query 	= "SELECT count(*) FROM #__flexicontent_items_ext as ie "
				. (FLEXI_J16GE ? " LEFT JOIN #__content as i ON i.id=ie.item_id " : "")
				. " WHERE ie.language='' "
				. ($enable_language_groups ? " OR ie.lang_parent_id='0' " : "")
				. (FLEXI_J16GE ? " OR i.language<>ie.language " : "")
				;
			$db->setQuery($query);
			$return = $db->loadResult();
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
	 * Method to check if the versions table is created
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistAuthorsTable()
	{
		static $return;
		if($return === NULL) {
			$query = 'SHOW TABLES LIKE ' . $this->_db->Quote('%flexicontent_authors_ext');
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
				'author.xml',
				'author.php',
				'myitems.xml',
				'myitems.php',
				'default.xml',
				'default.php',
				'index.html',
				'form.php',
				'form.xml'
				);
			$catdir 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'category'.DS.'tmpl');
			$cattmpl 	= JFolder::files($catdir);		
			$ctmpl 		= array_diff($cattmpl,$files);
			
			$itemdir 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.FLEXI_ITEMVIEW.DS.'tmpl');
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

		if(FLEXI_J16GE) {
			$flexi =& JComponentHelper::getComponent('com_flexicontent');
			$query 	=	"SELECT COUNT(*) FROM #__menu WHERE `type`='component' AND `published`=1 AND `component_id`='{$flexi->id}' ";
			$this->_db->setQuery($query);
			$count = $this->_db->loadResult();
		} else {
			$menus	= &JApplication::getMenu('site', array());
			$items	= $menus->getItems('componentid', $component->id);
			$count = count($items);
		}
		
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
		return diff_version($current_versions, $last_versions);
	}
	function checkCurrentVersionData() {
		// verify that every current version is in the versions table and it's data in the flexicontent_items_versions table
		//$and = "";

		// check if the section was chosen to avoid adding data on static contents
		if (!FLEXI_CAT_EXTENSION) return false;
		return FLEXIUtilities::currentMissing();
	}
	
	
	function addCurrentVersionData()
	{
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

/*
		// Remove this part at it seems to cause triple versions
		// Additionnal check from Suriya is required
		
		$query 	= "SELECT c.id,c.version,iv.version as iversion FROM #__content as c " .
			" LEFT JOIN #__flexicontent_items_versions as iv ON c.id=iv.item_id AND c.version=iv.version" .
			" WHERE sectionid='".FLEXI_SECTION."' AND c.version > '1' AND iv.version IS NULL;";
		$db->setQuery($query);
		$newrows = $db->loadAssocList();
		foreach($newrows as $r) {
			$newrows[$r["id"]] = $r;
		}
		$diff_arrays = array_merge_recursive($diff_arrays, $newrows);
*/

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
	
	function formatFlexiPlugins()
	{
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
			'com_flexicontent',
			'plg_flexicontent_fields_checkbox',
			'plg_flexicontent_fields_checkboximage',
			'plg_flexicontent_fields_core',
			'plg_flexicontent_fields_date',
			'plg_flexicontent_fields_email',
			'plg_flexicontent_fields_extendedweblink',
			'plg_flexicontent_fields_fcloadmodule',
			'plg_flexicontent_fields_fcpagenav',
			'plg_flexicontent_fields_file',
			'plg_flexicontent_fields_image',
			'plg_flexicontent_fields_linkslist',
			'plg_flexicontent_fields_minigallery',
			'plg_flexicontent_fields_radio',
			'plg_flexicontent_fields_radioimage',
			'plg_flexicontent_fields_relateditems',
			'plg_flexicontent_fields_select',
			'plg_flexicontent_fields_selectmultiple',
			'plg_flexicontent_fields_text',
			'plg_flexicontent_fields_textarea',
			'plg_flexicontent_fields_textselect',
			'plg_flexicontent_fields_toolbar',
			'plg_flexicontent_fields_weblink',
			'plg_flexicontent_flexinotify',
			'plg_search_flexiadvsearch',
			'plg_search_flexisearch',
			'plg_system_flexiadvroute',
			'plg_system_flexisystem'
		);

		$sitepath 		= JPATH_SITE.DS.'language'.DS.$code.DS;
		$refsitepath 	= JPATH_SITE.DS.'language'.DS.'en-GB'.DS;
		$sitefiles 	= array(
			'com_flexicontent',
			'mod_flexiadvsearch',
			'mod_flexicontent',
			'mod_flexitagcloud'
		);
		$targetfolder = JPATH_SITE.DS.'tmp'.DS.$code."_".time();
		
		if ($method == 'zip') {
			if (count($adminfiles))
				JFolder::create($targetfolder.DS.'admin', 0775);
			if (count($sitefiles))
				JFolder::create($targetfolder.DS.'site', 0775);
		}
		
		foreach ($adminfiles as $file) {
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
			$date =& JFactory::getDate();
		
			$xmlfile = $targetfolder.DS.'install.xml';
			
			$xml = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
			<install type="language" version="1.5" client="both" method="upgrade">
			    <name>FLEXIcontent '.$code.'</name>
			    <tag>'.$code.'</tag>
			    <creationDate>'.$date->toFormat("%Y-%m-%d").'</creationDate>
			    <author>'.$fromname.'</author>
			    <authorEmail>'.$mailfrom.'</authorEmail>
			    <authorUrl>'.$website.'</authorUrl>
			    <copyright>(C) '.$date->toFormat("%Y").' '.$fromname.'</copyright>
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
				if (!JFolder::delete(($targetfolder)) ) {
					echo JText::_('FLEXI_SEND_DELETE_TMP_FOLDER_FAILED');
				}
			}
		}
		
		// messages
		if ($method == 'zip') {
			return '<h3 class="lang-success">' . JText::_( 'FLEXI_SEND_LANGUAGE_ARCHIVE_SUCCESS' ) . '</span>';
		}
		return (count($missing) > 0) ? $missing : '<h3 class="lang-success">'. JText::sprintf( 'FLEXI_SEND_LANGUAGE_NO_MISSING', $code ) .'</h3>';
	}

	
	function checkInitialPermission() {
		jimport('joomla.access.rules');
		$db = &JFactory::getDBO();
		$component_name = 'com_flexicontent';
		
		// DELETE old namespace (flexicontent.*) permissions of v2.0beta, we do not try to rename them ... instead we will use com_content (for some of them),
		$query = $db->getQuery(true)->delete('#__assets')->where('name LIKE ' . $db->quote('flexicontent.%'));
		$db->setQuery($query);
		if(!$db->query()) return false;
		
		// SET Access View Level to public (=1) for fields that do not have their Level set
		$query = $db->getQuery(true)->update('#__flexicontent_fields')->set('access = 1')->where('access = 0');
		$db->setQuery($query);
		$db->query();
		
		// CHECK that we have the same Component Actions in assets DB table with the Actions as in component's access.xml file
		$asset	= JTable::getInstance('asset');
		if ($comp_section = $asset->loadByName($component_name)) {  // Try to load component asset, if missing it returns false
			// ok, component asset not missing, proceed to cross check for deleted / added actions
			$rules = new JRules($asset->rules);
			$rules_data = $rules->getData();
			$component_actions = JAccess::getActions($component_name, 'component');
			
			$db_action_names = array();
			foreach ($rules_data as $action_name => $data)  $db_action_names[]   = $action_name;
			foreach ($component_actions as $action)         $file_action_names[] = $action->name;
			$deleted_actions =  array_diff($db_action_names,   $file_action_names);
			$added_actions   =  array_diff($file_action_names, $db_action_names  );
			
			$comp_section = ! ( count($deleted_actions) || count($added_actions) );  // false if deleted or addeded actions exist
		}
		
		//echo ( ($comp_section) ? count($rules->getData()) : 0 ) . "<br />";
		//echo count(JAccess::getActions('com_flexicontent', 'component')) . "<br />";
		
		// CHECK if some categories don't have permissions set, , !!! WARNING this query must be same like the one USED in function initialPermission()
		$query = $db->getQuery(true)
			->select('c.id')
			->from('#__assets AS se')->join('RIGHT', '#__categories AS c ON se.id=c.asset_id AND se.name=concat("com_content.category.",c.id)')
			->where('se.id is NULL')->where('c.extension = ' . $db->quote('com_content'));
		$db->setQuery($query);
		$result = $db->loadObjectList();					if ($db->getErrorNum()) echo $db->getErrorMsg();
		//if (count($result)) { echo "bad assets for categories: "; print_r($result); echo "<br>"; }
		$category_section = count($result) == 0 ? 1 : 0;

		// CHECK if some items don't have permissions set, , !!! WARNING this query must be same like the one USED in function initialPermission()
		$query = $db->getQuery(true)
			->select('c.id')
			->from('#__assets AS se')->join('RIGHT', '#__content AS c ON se.id=c.asset_id AND se.name=concat("com_content.article.",c.id)')
			->where('se.id is NULL');
		$db->setQuery($query);
		$result = $db->loadObjectList();					if ($db->getErrorNum()) echo $db->getErrorMsg();
		//if (count($result)) { echo "bad assets for items: "; print_r($result); echo "<br>"; }
		$article_section = count($result) == 0 ? 1 : 0;

		// CHECK if some fields don't have permissions set, !!! WARNING this query must be same like the one USED in function initialPermission()
		$query = $db->getQuery(true)
			->select('se.id')
			->from('#__assets AS se')->join('RIGHT', '#__flexicontent_fields AS ff ON se.id=ff.asset_id AND se.name=concat("com_flexicontent.field.",ff.id)')
			->where('se.id is NULL');
		$db->setQuery($query);
		$result = $db->loadObjectList();					if ($db->getErrorNum()) echo $db->getErrorMsg();
		//if (count($result)) { echo "bad assets for fields: "; print_r($result); echo "<br>"; }
		$field_section = count($result) == 0 ? 1 : 0;
		
		//echo "$comp_section && $category_section && $article_section && $field_section<br>";

		return ($comp_section && $category_section && $article_section && $field_section);
	}
	
	function initialPermission() {
		jimport('joomla.access.rules');
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
			$component_rules = new JRules(json_encode($initial_rules));
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
			$existing_rules = new JRules($asset->rules);
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
				$component_rules = new JRules(json_encode($initial_rules));
				$component_rules->merge($existing_rules);
				
				// Second, check if obsolete rules are contained in the existing component (db) rules, if so create a new rules object without the obsolete rules
				if ($deleted_actions) {
					$rules_data = $component_rules->getData();
					foreach($deleted_actions as $action_name) {
						unset($rules_data[$action_name]);
					}
					$component_rules = new JRules($rules_data);
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
			->where('se.id is NULL')->where('c.extension = ' . $db->quote('com_content'))
			->order('c.level ASC');   // IMPORTANT create categories asset using increasing depth level, so that get parent assetid will not fail
		$db->setQuery($query);
		$results = $db->loadObjectList();					if ($db->getErrorNum()) echo $db->getErrorMsg();
		
		// Add an asset to every category that doesnot have one
		if(count($results)>0) {
			foreach($results as $category) {
				$parentId = $this->_getAssetParentId(null, $category);
				$name = "com_content.category.{$category->id}";
				
				// Test if an asset for the current CATEGORY ID already exists and load it instead of creating a new asset
				if ( ! $asset->loadByName($name) ) {
					if ($category->asset_id) {
						// asset name not found but category has an asset id set ?, we could delete it here
						// but it maybe dangerous to do so ... it might be a legitimate asset_id for something else
					}
					
					// Initialize category asset
					$asset->id 		= null;
					$asset->name	= $name;
					$asset->title	= $category->title;
					$asset->setLocation($parentId, 'last-child');     // Permissions of categories are inherited by parent category, or from component if no parent category exists
					
					// Set asset rules to empty, (DO NOT set any ACTIONS, just let them inherit ... from parent)
					$asset->rules = new JRules();
					/*
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
					*/
					
					// Save the asset
					if (!$asset->check() || !$asset->store(false)) {
						echo $asset->getError();
						$this->setError($asset->getError());
						return false;
					}
				}
				
				// Assign the asset to the category
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
				
				// Test if an asset for the current CATEGORY ID already exists and load it instead of creating a new asset
				if ( ! $asset->loadByName($name) ) {
					if ($item->asset_id) {
						// asset name not found but item has an asset id set ?, we could delete it here
						// but it maybe dangerous to do so ... it might be a legitimate asset_id for something else
					}
					
					// Initialize item asset
					$asset->id 		= null;
					$asset->name	= $name;
					$asset->title	= $item->title;
					$asset->setLocation($parentId, 'last-child');     // Permissions of items are inherited from their main category
					
					// Set asset rules to empty, (DO NOT set any ACTIONS, just let them inherit ... from parent)
					$asset->rules = new JRules();
					/*
					if ($parentId == $component_asset->id) {				
						$actions	= JAccess::getActions($component_name, 'article');
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
					*/
					
					// Save the asset
					if (!$asset->check() || !$asset->store(false)) {
						echo $asset->getError();
						$this->setError($asset->getError());
						return false;
					}
				}
				
				// Assign the asset to the item
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
					$asset->rules = new JRules();
					/*
					$actions	= JAccess::getActions($component_name, 'field');
					$rules 		= json_decode($component_asset->rules);		
					foreach ($actions as $action) {
						$fieldrules[$action->name] = $rules->{$action->name};
					}
					$rules = new JRules(json_encode($fieldrules));
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
		$joomla_rules = new JRules( $root->rules );
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
			// NOTE that actions that do not exist in global configuration, will not be set here, so they will default to the the setting received by STEP 1
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
		$db		= JFactory::getDBO();
		$query	= $db->getQuery(true);
		$query->select('a.id, a.title, COUNT(DISTINCT b.id) AS level, a.parent_id')
			->from('#__usergroups AS a')
			->leftJoin('#__usergroups AS b ON a.lft > b.lft AND a.rgt < b.rgt')
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
