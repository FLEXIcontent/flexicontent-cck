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
if (FLEXI_J16GE) jimport('joomla.access.accessrules');

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
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$allitems	= $permission->DisplayAllItems;
		} else if (FLEXI_ACCESS) {
			$allitems	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'displayallitems', 'users', $user->gmid) : 1;
		} else {
			$allitems 	= 1;
		}
		
		$query = 'SELECT SQL_CALC_FOUND_ROWS c.id, c.title, c.catid, c.created, cr.name as creator, c.created_by, c.modified, c.modified_by, mr.name as modifier'
				. ' FROM #__content as c'
				. ' LEFT JOIN #__users AS cr ON cr.id = c.created_by'
				. ' LEFT JOIN #__users AS mr ON mr.id = c.modified_by'
				. ' WHERE state = -3'
				. ' AND sectionid = ' . (int)FLEXI_SECTION
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
		$user = JFactory::getUser();
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$allitems	= $permission->DisplayAllItems;
		} else if (FLEXI_ACCESS) {
			$allitems	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'displayallitems', 'users', $user->gmid) : 1;
		} else {
			$allitems 	= 1;
		}
		
		$query = 'SELECT SQL_CALC_FOUND_ROWS c.id, c.title, c.catid, c.created, cr.name as creator, c.created_by, c.modified, c.modified_by, mr.name as modifier, c.version, MAX(fv.version_id) '
				. ' FROM #__content AS c'
				. ' LEFT JOIN #__flexicontent_versions AS fv ON c.id=fv.item_id'
				. ' LEFT JOIN #__users AS cr ON cr.id = c.created_by'
				. ' LEFT JOIN #__users AS mr ON mr.id = c.modified_by'
				. ' WHERE c.state = -5 OR c.state = 1'
				. ' AND c.sectionid = ' . (int)FLEXI_SECTION
				. ($allitems ? '' : ' AND c.created_by = '.$user->id)
				. ' GROUP BY fv.item_id '
				. ' HAVING c.version<>MAX(fv.version_id) '
				. ' ORDER BY c.modified DESC'
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
		$user = JFactory::getUser();
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$allitems	= $permission->DisplayAllItems;
		} else if (FLEXI_ACCESS) {
			$allitems	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'displayallitems', 'users', $user->gmid) : 1;
		} else {
			$allitems 	= 1;
		}

		$query = 'SELECT SQL_CALC_FOUND_ROWS c.id, c.title, c.catid, c.created, cr.name as creator, c.created_by, c.modified, c.modified_by, mr.name as modifier'
				. ' FROM #__content as c'
				. ' LEFT JOIN #__users AS cr ON cr.id = c.created_by'
				. ' LEFT JOIN #__users AS mr ON mr.id = c.modified_by'
				. ' WHERE state = -4'
				. ' AND sectionid = ' . (int)FLEXI_SECTION
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
		$user = JFactory::getUser();
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$allitems	= $permission->DisplayAllItems;
		} else if (FLEXI_ACCESS) {
			$allitems	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'displayallitems', 'users', $user->gmid) : 1;
		} else {
			$allitems 	= 1;
		}

		$query = 'SELECT SQL_CALC_FOUND_ROWS c.id, c.title, c.catid, c.created, cr.name as creator, c.created_by, c.modified, c.modified_by, mr.name as modifier'
				. ' FROM #__content as c'
				. ' LEFT JOIN #__users AS cr ON cr.id = c.created_by'
				. ' LEFT JOIN #__users AS mr ON mr.id = c.modified_by'
				. ' WHERE c.state = -5'
				. ' AND sectionid = ' . (int)FLEXI_SECTION
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
		static $return;
		if ($return !== null) return $return;
		
		$public_acclevel = !FLEXI_J16GE ? 0 : 1;
		$app = JFactory::getApplication();
		
		// Get 'default_menu_itemid' parameter
		$params = JComponentHelper::getParams('com_flexicontent');
		if ($params) {
			$menus = $app->getMenu('site', array());
			$_component_default_menuitem_id = $params->get('default_menu_itemid', false);
			$menu = $menus->getItem($_component_default_menuitem_id);
		} else {
			$_component_default_menuitem_id = '';
			return $return = false;
		}
		
		$prompt  = '<br>'.JText::_('FLEXI_DEFAULT_MENU_ITEM_PROMPT');
		
		// Check menu item exists
		$config_saved = (bool) $params->get('flexi_section');
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
		if ($return === NULL) {
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
	 * Method to check if there is at least the default fields in the FLEXIcontent fields TABLE
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistFields()
	{
		static $return;
		if ($return === NULL) {
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
	 * Method to check if there is at least the default flexicontent_fields PLUGINs
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistFieldsPlugins()
	{
		static $return;
		if ($return === NULL) {
			$return = false;
			$query = 'SELECT COUNT( id )'
				. ' FROM #__plugins'
				. ' WHERE folder = ' . $this->_db->Quote('flexicontent_fields')
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
	 * Method to check if the search plugin is installed
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistSearchPlugin()
	{
		static $return;
		if ($return === NULL) {
			$query = 'SELECT COUNT( id )'
			. ' FROM #__plugins'
			. ' WHERE element = ' . $this->_db->Quote('flexisearch')
			;
			$this->_db->setQuery( $query );
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
	function getExistSystemPlugin()
	{
		static $return;
		if ($return === NULL) {
			$query = 'SELECT COUNT( id )'
			. ' FROM #__plugins'
			. ' WHERE element = ' . $this->_db->Quote('flexisystem')
			;
			$this->_db->setQuery( $query );
			$return = $this->_db->loadResult() ? true : false;
		}
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
		if ($return === NULL) {
			// Make sure basic CORE fields are published
			$q = 'UPDATE #__flexicontent_fields SET published=1 WHERE id > 0 AND id < 7';
			$this->_db->setQuery( $q );
			$this->_db->query();
			
			$query 	= 'SELECT COUNT( id )'
				. ' FROM #__plugins'
				. ' WHERE '
				. ' ( folder = ' . $this->_db->Quote('flexicontent_fields')
				//. ' OR element = ' . $this->_db->Quote('flexisearch')
				. ' OR element = ' . $this->_db->Quote('flexisystem')
				//. ' OR element = ' . $this->_db->Quote('flexiadvsearch')
				. ' OR element = ' . $this->_db->Quote('flexiadvroute') . ')'
				. ' AND published <> 1'
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
		if ($return === NULL) {
			if (FLEXI_J16GE) {
				$columns = $this->_db->getTableColumns('#__flexicontent_items_ext');
				$result_lang_col = array_key_exists('language', $columns) ? true : false;
				$result_tgrp_col = array_key_exists('lang_parent_id', $columns) ? true : false;
			} else {
				$fields = $this->_db->getTableFields('#__flexicontent_items_ext');
				$result_lang_col = array_key_exists('language', $fields['#__flexicontent_items_ext']) ? true : false;
				$result_tgrp_col = array_key_exists('lang_parent_id', $fields['#__flexicontent_items_ext']) ? true : false;
			}
			$return = $result_lang_col && $result_tgrp_col;
		}
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
		if ($return === NULL) {
			$db = JFactory::getDBO();
			$query = "SELECT i.id"
				." FROM #__content AS i"
				." LEFT JOIN #__flexicontent_cats_item_relations as rel ON rel.catid=i.catid AND i.id=rel.itemid "
				." WHERE rel.catid IS NULL"
				." LIMIT 1";
			$db->setQuery($query);
			$item_id = $db->loadResult();
			
			if ($item_id) {
				$query = "SELECT item_id "
					." FROM #__flexicontent_items_ext as ie"
					." WHERE item_id = ". $item_id;
				$db->setQuery($query);
				$item_id = $db->loadResult();
			}
			$return = $item_id ? 1 : 0;
		}
		
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
		if ($return === NULL) {
			$enable_translation_groups = JComponentHelper::getParams( 'com_flexicontent' )->get("enable_translation_groups") && ( FLEXI_J16GE || FLEXI_FISH ) ;
			$db = JFactory::getDBO();
			/*$query = "SELECT COUNT(*) FROM #__flexicontent_items_ext as ie "
				. (FLEXI_J16GE ? " LEFT JOIN #__content as i ON i.id=ie.item_id " : "")
				. " WHERE ie.language='' " . ($enable_translation_groups ? " OR ie.lang_parent_id='0' " : "")
				. (FLEXI_J16GE ? " OR i.language='' OR i.language<>ie.language " : "")
				;*/
			$query = "SELECT COUNT(*)"
				." FROM #__flexicontent_items_ext as ie"
				." WHERE ie.language='' " . ($enable_translation_groups ? " OR ie.lang_parent_id='0' " : "")
				." LIMIT 1";
			$db->setQuery($query);
			$cnt1 = $db->loadResult();
			$cnt2 = 0;
			if (FLEXI_J16GE) {
				$query = "SELECT COUNT(*)"
					." FROM #__content as i"
					." WHERE i.language=''"
					." LIMIT 1";
				$db->setQuery($query);
				$cnt2 = $db->loadResult();
			}
			$return = $cnt1 || $cnt2;
		}
		
		return $return;
	}
	
	
	/**
	 * Method to get if some items do not have their no index columns up to date with the main content table (these are used for item counting)
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function getItemCountingDataOK()
	{
		static $return;
		if ($return === NULL) {
			$db = JFactory::getDBO();
			
			// Find columns cached
			$cache_tbl = "#__flexicontent_items_tmp";
			$tbls = array($cache_tbl);
			if (!FLEXI_J16GE) $tbl_fields = $db->getTableFields($tbls);
			else foreach ($tbls as $tbl) $tbl_fields[$tbl] = $db->getTableColumns($tbl);
			
			// Get the column names
			$tbl_fields = array_keys($tbl_fields[$cache_tbl]);
			
			$query = "SELECT COUNT(*)"
				. " FROM #__content AS i "
				. " LEFT JOIN ".$cache_tbl." AS ca ON i.id=ca.id "
				. " WHERE ca.id IS NULL ";
			foreach ($tbl_fields as $col_name) {
				if ($col_name == "id" || $col_name == "hits") continue;
				else $query .= " OR i.`".$col_name."`<>ca.`".$col_name."`";
			}
			
			$db->setQuery($query);
			$return = $this->_db->loadResult() ? false : true;
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
	function getExistDBindexes()
	{
		static $return;
		if ($return === NULL) {
			$app = JFactory::getApplication();
			$dbprefix = $app->getCfg('dbprefix');
			$dbname = $app->getCfg('db');
			
			$query = "SELECT COUNT(1) IndexIsThere "
				." FROM INFORMATION_SCHEMA.STATISTICS"
				." WHERE table_schema='".$dbname."' AND table_name='".$dbprefix."flexicontent_fields_item_relations' AND index_name='value'";
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
		if ($return === null) {
			// Try to open phpThumb cache directory
			$phpthumbcache 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'cache');
			$return = preg_match('/rwxr.xr.x/i', JPath::getPermissions($phpthumbcache) ) ? true : false;
		}
		return $return;
	}

	/**
	 * Method to check if the files from beta3 still exist in the category and item view
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getOldBetaFiles() {
		static $return;
		if ($return!==null) return $return;
		
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
							$fieldstopos = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
							
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
		if ($return === NULL) {
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
				. ' FROM #__categories'
				. ' WHERE section = ' .$this->_db->Quote(FLEXI_SECTION)
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
		if (FLEXI_SECTION) {
			$query = 'SELECT COUNT( id )'
			. ' FROM #__sections'
			. ' WHERE id = ' . FLEXI_SECTION
			;
			$this->_db->setQuery( $query );
			$count = $this->_db->loadResult();
				
			if ($count > 0) {
				return true;
			} else {
				// Save the created section as flexi_section for the component
				$params = JComponentHelper::getParams('com_flexicontent');
				$params->set('flexi_section', '');
				$cparams = $component->toString();
	
				$flexi = JComponentHelper::getComponent('com_flexicontent');
				
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

	/**
	 * Method to check and add some extra FLEXIcontent specific ACL rules
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function checkExtraAclRules()
	{
		if (FLEXI_ACCESS)
		{
			$db = $this->_db;
			
			// COMMENTED out, instead we will use field's 'submit' privilege
			/*$query = "SELECT count(*) FROM `#__flexiaccess_rules` WHERE acosection='com_flexicontent' AND aco='editvalue' AND axosection='fields'";
			$db->setQuery($query);
			$editvalue_rule = $db->loadResult();
			
			if (!$editvalue_rule)
			{
				$query = "INSERT INTO #__flexiaccess_rules (`acosection`, `variable`, `aco`, `axosection`, `axo`, `label`, `source`, `ordering`)"
					." VALUES ('com_flexicontent', '', 'editvalue', 'fields', '', 'Edit Field Values', '', '')";
				$db->setQuery($query);
				$db->query();
				JFactory::getApplication()->enqueueMessage( 'Added ACL Rule: '. JText::_('FLEXI_EDIT_FIELD_VALUE'), 'message' );
			}*/
			
			// Delete wrong rule names
			$query = "DELETE FROM #__flexiaccess_rules WHERE acosection='com_flexicontent' AND aco='associateanyitem'";
			$db->setQuery($query);
			$db->query();
			
			// Check for assocanytrans : Allow users to associate translations (items) authored by any user
			$query = "SELECT COUNT(*) FROM `#__flexiaccess_rules` WHERE acosection='com_flexicontent' AND aco='assocanytrans'";
			$db->setQuery($query);
			$assocanytrans_rule = $db->loadResult();
			
			if (!$assocanytrans_rule)
			{
				$query = "INSERT INTO #__flexiaccess_rules (`acosection`, `variable`, `aco`, `axosection`, `axo`, `label`, `source`, `ordering`)"
					." VALUES ('com_flexicontent', '', 'assocanytrans', '', '', 'Associate any translation (items)', '', '')";
				$db->setQuery($query);
				$db->query();
				JFactory::getApplication()->enqueueMessage( 'Added ACL Rule: '. JText::_('FLEXI_ASSOCIATE_ANY_TRANSLATION'), 'message' );
			}
			
			// Check for editcreationdate : Allow users to edit creation date of an item
			/*$query = "SELECT COUNT(*) FROM `#__flexiaccess_rules` WHERE acosection='com_flexicontent' AND aco='editcreationdate'";
			$db->setQuery($query);
			$editcreationdate_rule = $db->loadResult();
			
			if (!$editcreationdate_rule)
			{
				$query = "INSERT INTO #__flexiaccess_rules (`acosection`, `variable`, `aco`, `axosection`, `axo`, `label`, `source`, `ordering`)"
					." VALUES ('com_flexicontent', '', 'editcreationdate', '', '', 'Edit creation date (items)', '', '')";
				$db->setQuery($query);
				$db->query();
				JFactory::getApplication()->enqueueMessage( 'Added ACL Rule: '. JText::_('FLEXI_EDIT_CREATION_DATE'), 'message' );
			}*/
			
			// Check for ignoreviewstate : Allow users to view unpublished, archived, trashed, scheduled, expired items in frontend content lists e.g. category view
			$query = "SELECT COUNT(*) FROM `#__flexiaccess_rules` WHERE acosection='com_flexicontent' AND aco='ignoreviewstate'";
			$db->setQuery($query);
			$ignoreviewstate_rule = $db->loadResult();
			
			if (!$ignoreviewstate_rule)
			{
				$query = "INSERT INTO #__flexiaccess_rules (`acosection`, `variable`, `aco`, `axosection`, `axo`, `label`, `source`, `ordering`)"
					." VALUES ('com_flexicontent', '', 'ignoreviewstate', '', '', 'Ignore view state (items)', '', '')";
				$db->setQuery($query);
				$db->query();
				JFactory::getApplication()->enqueueMessage( 'Added ACL Rule: '. JText::_('FLEXI_IGNORE_VIEW_STATE'), 'message' );
			}
			
			// Check for import : Allow management of (Content) Import
			$query = "SELECT COUNT(*) FROM `#__flexiaccess_rules` WHERE acosection='com_flexicontent' AND aco='import'";
			$db->setQuery($query);
			$import_rule = $db->loadResult();
			
			if (!$import_rule)
			{
				$query = "INSERT INTO #__flexiaccess_rules (`acosection`, `variable`, `aco`, `axosection`, `axo`, `label`, `source`, `ordering`)"
					." VALUES ('com_flexicontent', '', 'import', '', '', 'Manage (Content) Import', '', '')";
				$db->setQuery($query);
				$db->query();
				JFactory::getApplication()->enqueueMessage( 'Added ACL Rule: '. JText::_('FLEXI_MANAGE_CONTENT_IMPORT'), 'message' );
			}
		}
	}
	
	
	function getDiffVersions($current_versions=array(), $last_versions=array())
	{
		// check if the section was chosen to avoid adding data on static contents
		if (!FLEXI_SECTION) return array();
		
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
		if (!FLEXI_SECTION) return false;
		return FLEXIUtilities::currentMissing();
	}
	
	
	function addCurrentVersionData($item_id = null, $clean_database = false)
	{
		// check if the section was chosen to avoid adding data on static contents
		if (!FLEXI_SECTION) return true;
		
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
			." WHERE sectionid='".FLEXI_SECTION."'"
			.($item_id ? " AND id=".$item_id : "")
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
		
		$jcorefields = flexicontent_html::getJCoreFields();
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
				$query = "SELECT f.id,fir.value,f.field_type,f.name,fir.valueorder,f.iscore "
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
					$obj->version    = (int)$row->version;
					$obj->value      = $fieldval->value;
					//echo "version: ".$obj->version.",fieldid : ".$obj->field_id.",value : ".$obj->value.",valueorder : ".$obj->valueorder."<br />";
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
			'plg_flexicontent_fields_radio',
			'plg_flexicontent_fields_radioimage',
			'plg_flexicontent_fields_relation',
			'plg_flexicontent_fields_relation_reverse',
			'plg_flexicontent_fields_select',
			'plg_flexicontent_fields_selectmultiple',
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

}
?>
