<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('legacy.model.legacy');
jimport('joomla.access.rules');
use Joomla\String\StringHelper;

/**
 * FLEXIcontent Component Dashboard Model
 *
 */
class FlexicontentModelFlexicontent extends JModelLegacy
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);
	}

	/**
	 * Method to get items in pending state, waiting for publication approval
	 *
	 * @access public
	 * @return array
	 */
	function getPending(&$total=null)
	{
		$user = JFactory::getUser();
		$permission = FlexicontentHelperPerm::getPerm();
		$allitems	= $permission->DisplayAllItems;

		$use_tmp = true;
		$query_select_ids = 'SELECT SQL_CALC_FOUND_ROWS c.id';
		$query_select_data = 'SELECT c.id, c.title, c.catid, c.created, cr.name as creator, c.created_by, c.modified, c.modified_by, mr.name as modifier, c.checked_out';

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
		$ids = $this->_db->loadColumn();

		// Get total
		$this->_db->setQuery("SELECT FOUND_ROWS()");
		$total = $this->_db->loadResult();

		$genstats = array();

		if (!empty($ids))
		{
			$query = $query_select_data . str_replace('#__flexicontent_items_tmp', '#__content', $query_from_join) . ' WHERE c.id IN ('.implode(',',$ids).')';
			$this->_db->SetQuery($query, 0, 5);
			$_data = $this->_db->loadObjectList('id');

			foreach($ids as $id) $genstats[] = $_data[$id];
			unset($_data);
		}
		return $genstats;
	}

	/**
	 * Method to get items revised, having unapproved version, waiting to be reviewed and approved
	 *
	 * @access public
	 * @return array
	 */
	function getRevised(&$total=null)
	{
		$user = JFactory::getUser();
		$permission = FlexicontentHelperPerm::getPerm();
		$allitems	= $permission->DisplayAllItems;

		$use_tmp = true;
		$query_select_ids = 'SELECT SQL_CALC_FOUND_ROWS c.id'.', c.version, MAX(fv.version_id)';
		$query_select_data = 'SELECT c.id, c.title, c.catid, c.created, cr.name as creator, c.created_by, c.modified, c.modified_by, mr.name as modifier, c.checked_out'
			.', c.version, MAX(fv.version_id)';

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
		$ids = $this->_db->loadColumn();

		// Get total
		$this->_db->setQuery("SELECT FOUND_ROWS()");
		$total = $this->_db->loadResult();

		$genstats = array();

		if (!empty($ids))
		{
			$query = $query_select_data . str_replace('#__flexicontent_items_tmp', '#__content', $query_from_join) . ' WHERE c.id IN ('.implode(',',$ids).')'
				.' GROUP BY fv.item_id ';
			$this->_db->SetQuery($query, 0, 5);
			$_data = $this->_db->loadObjectList('id');

			foreach($ids as $id) $genstats[] = $_data[$id];
			unset($_data);
		}
		return $genstats;
	}

	/**
	 * Method to get items in draft state, waiting to be written (and published)
	 *
	 * @access public
	 * @return array
	 */
	function getDraft(&$total=null)
	{
		$user = JFactory::getUser();
		$permission = FlexicontentHelperPerm::getPerm();
		$allitems	= $permission->DisplayAllItems;
		$requestApproval = @ $permission->RequestApproval;

		$use_tmp = true;
		$query_select_ids = 'SELECT SQL_CALC_FOUND_ROWS c.id';
		$query_select_data = 'SELECT c.id, c.title, c.catid, c.created, cr.name as creator, c.created_by, c.modified, c.modified_by, mr.name as modifier, c.checked_out';

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
		$ids = $this->_db->loadColumn();

		// Get total
		$this->_db->setQuery("SELECT FOUND_ROWS()");
		$total = $this->_db->loadResult();

		$genstats = array();

		if (!empty($ids))
		{
			$query = $query_select_data . str_replace('#__flexicontent_items_tmp', '#__content', $query_from_join) . ' WHERE c.id IN ('.implode(',',$ids).')';
			$this->_db->SetQuery($query, 0, 5);
			$_data = $this->_db->loadObjectList('id');

			foreach($ids as $id) $genstats[] = $_data[$id];
			unset($_data);
		}
		return $genstats;
	}

	/**
	 * Method to get items in progress state, (published but) waiting to be completed
	 *
	 * @access public
	 * @return array
	 */
	function getInprogress(&$total=null)
	{
		$user = JFactory::getUser();
		$permission = FlexicontentHelperPerm::getPerm();
		$allitems	= $permission->DisplayAllItems;

		$use_tmp = true;
		$query_select_ids = 'SELECT SQL_CALC_FOUND_ROWS c.id';
		$query_select_data = 'SELECT c.id, c.title, c.catid, c.created, cr.name as creator, c.created_by, c.modified, c.modified_by, mr.name as modifier, c.checked_out';

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
		$ids = $this->_db->loadColumn();

		// Get total
		$this->_db->setQuery("SELECT FOUND_ROWS()");
		$total = $this->_db->loadResult();

		$genstats = array();

		if (!empty($ids))
		{
			$query = $query_select_data . str_replace('#__flexicontent_items_tmp', '#__content', $query_from_join) . ' WHERE c.id IN ('.implode(',',$ids).')';
			$this->_db->SetQuery($query, 0, 5);
			$_data = $this->_db->loadObjectList('id');

			foreach($ids as $id) $genstats[] = $_data[$id];
			unset($_data);
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
			$result = $this->_db->execute();
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
	function getExistCoreFields()
	{
		static $return;
		if ($return !== NULL) return $return;
		$return = false;

		$query = 'SELECT COUNT(id)'
			. ' FROM #__flexicontent_fields'
			. ' WHERE iscore = 1'
			;
		$this->_db->setQuery( $query );
		$count = $this->_db->loadResult();
		$return = $count > 13;

		return $return;
	}


	/**
	 * Method to check if there is at least the default fields in the FLEXIcontent fields TABLE
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getExistCpFields()
	{
		static $return;
		if ($return !== NULL) return $return;
		$return = false;

		//JFactory::getLanguage()->load('plg_flexicontent_fields_coreprops', JPATH_ADMINISTRATOR, 'en-GB', true);
		//JFactory::getLanguage()->load('plg_flexicontent_fields_coreprops', JPATH_ADMINISTRATOR, null, true);

		$p = 'FLEXI_COREPROPS_';
		$coreprop_names = array
		(
			// Single properties
			'id' => 'FLEXI_ID', 'alias' => 'FLEXI_ALIAS', 'category' => $p.'CATEGORY_MAIN', 'lang' => $p.'LANGUAGE', 'vstate' => $p.'PUBLISH_CHANGES',
			'disable_comments' => $p.'DISABLE_COMMENTS', 'notify_subscribers' => $p.'NOTIFY_SUBSCRIBERS', 'notify_owner' => $p.'NOTIFY_OWNER',
			'captcha' => $p.'CAPTCHA', 'layout_selection' => $p.'LAYOUT_SELECT',

			//Publishing
			'timezone_info' => $p.'TIMEZONE', 'created_by_alias' => $p.'CREATED_BY_ALIAS',
			'publish_up' => $p.'PUBLISH_UP', 'publish_down' => $p.'PUBLISH_DOWN',
			'access' => $p.'VIEW_ACCESS',

			// Attibute groups or other composite data
			'item_screen' => $p.'VERSION_INFOMATION', 'lang_assocs' => $p.'LANGUAGE_ASSOCS',
			'jimages' => $p.'JIMAGES', 'jurls' => $p.'JURLS',
			'metadata' => $p.'META', 'seoconf' => $p.'SEO',
			'display_params' => $p.'DISP_PARAMS', 'layout_params' => $p.'LAYOUT_PARAMS',
			'perms' => 'FLEXI_PERMISSIONS', 'versions' => 'FLEXI_VERSIONS',
		);

		$types_count = (int) $this->_db->setQuery('SELECT COUNT(id) FROM `#__flexicontent_types`')->loadResult();

		$query = 'SELECT f.name, COUNT(rel.type_id) as types_assigned'
			. ' FROM #__flexicontent_fields AS f '
			. ' LEFT JOIN #__flexicontent_fields_type_relations AS rel ON f.id = rel.field_id'
			. ' WHERE f.field_type = "coreprops" AND f.name LIKE "form_%" AND f.published = 1'
			. ' GROUP BY f.id'
			;
		$fields = $this->_db->setQuery( $query )->loadObjectList();

		/**
		 * Unset any $coreprop_names that have been found assigned to all types
		 */
		foreach ($fields as $i => $field)
		{
			$propname = str_replace('form_', '', $field->name);
			if ((int) $field->types_assigned === $types_count && isset($coreprop_names[$propname]))
			{
				unset($coreprop_names[$propname]);
			}
		}
		//echo $query . '<br><pre>'; print_r($fields); echo '</pre><br>' .count($coreprop_names) . '<br>'; exit;

		// All coreprop_names should have been eliminated
		$return = count($coreprop_names) ? false : true;
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

		$query = 'SELECT COUNT(extension_id)'
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
		$this->_db->setQuery($q)->execute();

		$query 	= 'SELECT COUNT( extension_id )'
			. ' FROM #__extensions'
			. ' WHERE `type`=' . $this->_db->Quote('plugin')
			. ' AND (folder = ' . $this->_db->Quote('flexicontent_fields')
			. ' OR element = ' . $this->_db->Quote('flexisystem')
			. ' OR element = ' . $this->_db->Quote('flexiadvroute')
			. ' OR (folder = ' . $this->_db->Quote('osmap') . ' AND element = ' . $this->_db->Quote('com_flexicontent') . ')'
			//. ' OR element = ' . $this->_db->Quote('flexiadvsearch')
			//. ' OR element = ' . $this->_db->Quote('flexisearch')
			. ')'
			. ' AND enabled <> 1'
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
	 * Method to sync language between Joomla and FLEXIcontent tables
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function syncItemsLang()
	{
		$db = JFactory::getDbo();

		// This should be match items that come from J1.5 upgrade only
		// and copy the J1.5 language from items_ext table into the content table
		$query 	= 'UPDATE #__content AS i'
					.' LEFT JOIN #__flexicontent_items_ext as ie ON i.id=ie.item_id'
				. ' SET i.language = ie.language'
				. ' WHERE ie.language<>"" AND ('
				. '  i.language="" OR (i.language="*" AND i.language<>ie.language)'
				. ' )'
				;
		$db->setQuery($query);
		$result1 = $db->execute();

		// Sync language of items_ext table using the language from Joomla content table
		$query 	= 'UPDATE #__flexicontent_items_ext AS ie'
					.' LEFT JOIN #__content as i ON i.id=ie.item_id'
				. ' SET ie.language = i.language'
				. ' WHERE i.language<>ie.language'
				;
		$db->setQuery($query);
		$result1 = $db->execute();
	}


	/**
	 * Method to set the default site language the items with no language
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function setItemsDefaultLang($lang)
	{
		$db = JFactory::getDbo();

		// Set default language for items that do not have their language set
		$query 	= 'UPDATE #__flexicontent_items_ext'
				. ' SET language = ' . $db->Quote($lang)
				. ' WHERE language = ""'
				;
		$db->setQuery($query);
		$result1 = $db->execute();

		$query 	= 'UPDATE #__flexicontent_items_tmp'
				. ' SET language = ' . $db->Quote($lang)
				. ' WHERE language = ""'
				;
		$db->setQuery($query);
		$result1a = $db->execute();

		$query 	= 'UPDATE #__content'
				. ' SET language = ' . $db->Quote($lang)
				. ' WHERE language = ""'
				;
		$db->setQuery($query);
		$result2 = $db->execute();

		return $result1 && $result1a && $result2;
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


	// Check and if needed install Joomla template overrides into current Joomla template
	function install_template_overrides()
	{
		flexicontent_html::install_template_overrides(true);
	}


	// Check and if needed install 3rd party plugins that do not use Joomla plugin system
	function install_3rdParty_plugins()
	{
		jimport('joomla.filesystem.path' );
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');

		// ****************************
		// Handle jcomments integration
		// ****************************

		$destpath = JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'plugins';

		// Check if JComments installed and active
		if (JFolder::exists($destpath) && JPluginHelper::isEnabled('system', 'jcomments'))
		{
			$dest   = $destpath.DS.'com_flexicontent.plugin.php';
			$source = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'jcomments'.DS.'com_flexicontent.plugin.php';
			$plg_exists = JFile::exists($dest);

			if (!$plg_exists || filemtime(__FILE__) > filemtime($dest))
			{
				if (!JFolder::exists($destpath)) {
					if (!JFolder::create($destpath)) {
						JFactory::getApplication()->enqueueMessage(JText::sprintf('JLIB_INSTALLER_ABORT_PLG_INSTALL_CREATE_DIRECTORY', 'jComments plugin for FLEXIcontent', $destpath), 'warning');
					}
				}
				if (!JFile::copy($source, $dest)) {
					JFactory::getApplication()->enqueueMessage(JText::_('FLEXI_FAILED_TO') . JText::_(!$plg_exists ? 'JLIB_INSTALLER_INSTALL' : 'JLIB_INSTALLER_UPDATE') . ' jComments plugin for FLEXIcontent', 'warning');
				} else {
					JFactory::getApplication()->enqueueMessage('<span class="badge">' . JText::_(!$plg_exists ? 'FLEXI_INSTALLED' : 'FLEXI_UPDATED') . '</span> jComments plugin for FLEXIcontent', 'message');
				}
			}
		}


		// *************************
		// Handle falang integration
		// *************************

		$destpath = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_falang'.DS.'contentelements';

		if (JFolder::exists($destpath) && JPluginHelper::isEnabled('system', 'falangdriver'))
		{
			$sourcepath = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'joomfish';
			$files = glob($sourcepath."/*.xml");
			$elements_count[0] = $elements_count[1] = 0;
			foreach ($files as $file)
			{
				$file_dest = $destpath.DS.basename($file);
				$plg_exists = JFile::exists($file_dest);
				if (!$plg_exists || filemtime($file) > filemtime($file_dest))
				{
					JFile::copy($file, $file_dest);
					$elements_count[$plg_exists ? 1 : 0]++;
				}
			}
			if ($elements_count[0]) JFactory::getApplication()->enqueueMessage('<span class="badge">' . JText::_('FLEXI_INSTALLED') . '</span> ' . $elements_count[0] . ' Falang elements for FLEXIcontent', 'message');
			if ($elements_count[1]) JFactory::getApplication()->enqueueMessage('<span class="badge">' . JText::_('FLEXI_UPDATED')   . '</span> ' . $elements_count[1] . ' Falang elements for FLEXIcontent', 'message');
		}


		// **********************
		// Handle JCE integration
		// **********************

		$pathDestName   = JPath::clean(JPATH_ROOT.'/components/com_jce/editor/extensions/links');
		$pathSourceName = JPath::clean(JPATH_ROOT.'/components/com_flexicontent/librairies/JCE/links');

		// Check if JCE installed and active
		if (!JFolder::exists($pathDestName) || !JPluginHelper::isEnabled('editors', 'jce'))
		{
			return true; // Nothing to do, JCE not installed
		}

		$plg_exists = JFile::exists($pathDestName.'/flexicontentlinks.php');
		if ($plg_exists && filemtime(__FILE__) < filemtime($pathDestName.'/flexicontentlinks.php'))
		{
			return true; // Nothing to do, already up-to-date
		}

		// 2. Copy all files
		$files = glob($pathSourceName."/*.*");
		foreach ($files as $file)
		{
			$file_dest = $pathDestName .DS. basename($file);
			JFile::copy($file, $file_dest);
		}

		// Check DESTINATION folder
		$pathSourceName = JPath::clean($pathSourceName.'/flexicontentlinks');
		$pathDestName   = JPath::clean($pathDestName.'/flexicontentlinks');
		if ( !JFolder::exists($pathDestName) && !JFolder::create($pathDestName) )
		{
			JFactory::getApplication()->enqueueMessage(JText::sprintf('JLIB_INSTALLER_ABORT_PLG_INSTALL_CREATE_DIRECTORY', 'JCE Links plugin for FLEXIcontent', $pathDestName), 'warning');
		}

		// Copy all files
		$files = glob($pathSourceName."/*.*");
		foreach ($files as $file)
		{
			$file_dest = $pathDestName .DS. basename($file);
			JFile::copy($file, $file_dest);
		}

		$folders = array('css', 'images');
		foreach ($folders as $folder)
		{
			// Check DESTINATION folder
			$sub_pathSourceName = JPath::clean($pathSourceName.'/'.$folder);
			$sub_pathDestName   = JPath::clean($pathDestName.'/'.$folder);
			if ( !JFolder::exists($sub_pathDestName) && !JFolder::create($sub_pathDestName) )
			{
				JFactory::getApplication()->enqueueMessage(JText::sprintf('JLIB_INSTALLER_ABORT_PLG_INSTALL_CREATE_DIRECTORY', 'JCE Links plugin for FLEXIcontent', $sub_pathDestName), 'warning');
			}

			// Copy all files
			$files = glob($sub_pathSourceName."/*.*");
			foreach ($files as $file)
			{
				$file_dest = $sub_pathDestName .DS. basename($file);
				JFile::copy($file, $file_dest);
			}
		}

		JFactory::getApplication()->enqueueMessage('<span class="badge">' . JText::_(!$plg_exists ? 'FLEXI_INSTALLED' : 'FLEXI_UPDATED') . '</span> JCE Links plugin for FLEXIcontent', 'message');
	}


	/**
	 * Method to fix collations
	 *
	 */
	function checkCollations()
	{
		$db = $this->_db;
		$session = JFactory::getSession();
		$app = JFactory::getApplication();

		$dbprefix = $app->getCfg('dbprefix');
		$dbname   = $app->getCfg('db');

		$jversion = new JVersion;

		$collation_version = $session->get('flexicontent.collation_version');
		if ($collation_version == $jversion->getShortVersion())  return;

		$docheck = version_compare( $jversion->getShortVersion(), '3.4.99', '>' );
		if ($docheck)
		{
			$full_tbl_name = $dbprefix . 'content';
			$query = "SELECT COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '".$dbname."' AND TABLE_NAME = '".$full_tbl_name."' AND COLUMN_NAME IN ('language')";
			$db->setQuery($query);
			$col_data = $db->loadAssocList('COLUMN_NAME');
			$jchset = $col_data['language']['CHARACTER_SET_NAME'];   // ? 'utf8mb4'
			$jcname = $col_data['language']['COLLATION_NAME'];   // ? 'utf8mb4_unicode_ci'

			// Data Types of columns
			$tbl_names_arr = array(
				'flexicontent_items_ext'=>array(
					'language' => "VARCHAR(7) CHARACTER SET ".$jchset." COLLATE ".$jcname." NOT NULL DEFAULT '*'"
				),
				'flexicontent_items_tmp'=>array(
					'title' => "VARCHAR(255) CHARACTER SET ".$jchset." COLLATE ".$jcname." NOT NULL",
					'alias' => "VARCHAR(400) CHARACTER SET ".$jchset." COLLATE ".$jcname." NOT NULL",
					'language' => "VARCHAR(7) CHARACTER SET ".$jchset." COLLATE ".$jcname." NOT NULL DEFAULT '*'"
				),
				'flexicontent_fields'=>array(
					'field_type' => "VARCHAR(50) CHARACTER SET ".$jchset." COLLATE ".$jcname." NOT NULL default ''"
				)
			);

			foreach ($tbl_names_arr as $tbl_name => $tbl_cols)
			{
				$full_tbl_name = $dbprefix . $tbl_name;
				$query = "SELECT COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '".$dbname."' AND TABLE_NAME = '".$full_tbl_name."' AND COLUMN_NAME IN ('".implode("','", array_keys($tbl_cols))."')";
				$db->setQuery($query);
				$col_data = $db->loadAssocList('COLUMN_NAME');
				foreach($col_data as $col => $data) {
					if ($data['CHARACTER_SET_NAME'] <> $jchset || $data['COLLATION_NAME'] <> $jcname) {
						$query = "ALTER TABLE ".$full_tbl_name." MODIFY `".$col."` ". $tbl_cols[$col];
						$db->setQuery($query);
						$col_data = $db->execute();
					}
				}
			}
		}

		$session->set('flexicontent.collation_version', $jversion->getShortVersion());
	}


	/**
	 * Method to get if language of items is initialized properly
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function getItemsBadLang()
	{
		static $return;
		if ($return !== NULL) return $return;
		$return = false;

		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		//$useAssocs = flexicontent_db::useAssociations();

		// Clean orphan assosiations
		$query = 'DELETE e.* FROM #__associations AS e WHERE e.context = "com_content.item" AND e.id IN (SELECT tmp.id FROM (' .
			'SELECT a.id AS id FROM #__associations AS a ' .
			'LEFT JOIN #__content AS c ON a.id = c.id ' .
			'WHERE a.context = "com_content.item" AND c.id IS NULL' .
		') AS tmp )';
		try { $this->_db->setQuery($query)->execute(); }
		catch (Exception $e) {
			echo '<div class="fcclear"></div><div class="alert alert-warning">' . $e->getMessage() . '<br>' . $query . '</div>';
		}

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
		$this->_db->execute();

		// Clear orphan data from the TMP data tables
		$query = "DELETE d.* FROM #__flexicontent_items_ext AS d"
			." LEFT JOIN #__content AS c ON d.item_id = c.id"
			." WHERE c.id IS NULL";
		$this->_db->setQuery($query);
		$this->_db->execute();

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
			else if ( $col_name=='publish_up' || $col_name=='publish_down')
				$query .= " AND (i.`".$col_name."`=ca.`".$col_name."` OR (i.`".$col_name."` IS NULL AND ca.`".$col_name."` IS NULL))";
			else
				$query .= " AND i.`".$col_name."`=ca.`".$col_name."`";
		}

		// Check missing in item counting table
		$this->_db->setQuery($query);
		$res = $this->_db->loadObject();

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
	function getExistDBindexes($check_only=true, & $update_queries = array())
	{
		static $missing;
		if ($missing !== NULL) return $check_only ? empty($missing) : $missing;

		jimport('joomla.filesystem.file');
		$app = JFactory::getApplication();
		$dbprefix = $app->getCfg('dbprefix');
		$dbname   = $app->getCfg('db');

		$tblname_indexnames = array(
			'flexicontent_tags'=>array('name'=>0, 'alias'=>0, 'published'=>0, 'jtag_id'=>0),
			'flexicontent_types'=>array('name'=>0, 'alias'=>0, 'published'=>0, 'access'=>0),
			'flexicontent_items_ext'=>array('type_id'=>0, 'is_uptodate'=>0, 'lang_parent_id'=>0),
			'flexicontent_items_tmp'=>array(
				'title'=>array(
					'custom_add'=>'ADD FULLTEXT KEY',
					'cols'=>array('title'=>0)
				),
				'alias'=>64, 'state'=>0, 'catid'=>0,
				'created_by'=>0, 'access'=>0, 'featured'=>0,
				'language'=>0, 'type_id'=>0, 'lang_parent_id'=>0
			),

			'flexicontent_reviews'=>array(
				'title'=>array(
					'custom_add'=>'ADD FULLTEXT KEY',
					'cols'=>array('title'=>0)
				),
				'text'=>array(
					'custom_add'=>'ADD FULLTEXT KEY',
					'cols'=>array('text'=>0)
				),
				'state'=>0, 'approved'=>0, 'verified'=>0,
				'useful_yes'=>0, 'useful_no'=>0,
			),

			'flexicontent_fields_item_relations'=>array(
				'value'=>32,
				'value_integer'=>array('update'=>'CAST(value AS SIGNED)'),
				'value_decimal'=>array('update'=>'CAST(value AS DECIMAL(65,15))'),
				'value_datetime'=>array('update'=>'CAST(value AS DATETIME)'),
				//'reference_1'=>0,
				'PRIMARY'=>array(
					'custom_drop'=>'DROP PRIMARY KEY',
					'custom_add'=>'ADD PRIMARY KEY',
					'cols'=>array('field_id'=>0, 'item_id'=>0, 'valueorder'=>0, 'suborder'=>0)
				)
			),
			'flexicontent_mediadatas'=>array(
				'bits_per_sample'=>0,
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
				'configuration'=>array(
					'cols'=>array('template'=>0, 'cfgname'=>0, 'layout'=>0, 'position'=>0)
				)
			),
			'flexicontent_favourites'=>array(
				'PRIMARY'=>array(
					'custom_drop'=>'DROP PRIMARY KEY',
					'custom_add'=>'ADD PRIMARY KEY',
					'cols'=>array('id'=>0,'itemid'=>0, 'userid'=>0, 'type'=>0)
				),
				'unique_rec'=>array(
					'custom_drop'=>'DROP INDEX `unique_rec`',
					//'cols'=>array()  // Empty cols, we only want to drop the index if it exists
				)
			),
			'flexicontent_items_extravote'=>array(
				'PRIMARY'=>array(
					'custom_add'=>'ADD PRIMARY KEY',
					'cols'=>array('content_id'=>0, 'field_id'=>0)
				)
			)
		);

		$missing = array();
		$all_started = true;
		foreach($tblname_indexnames as $tblname => $indexnames)
		{
			$indexing_started = false;
			$file = JPATH_SITE.DS.'tmp'.DS.'tbl_indexes_'.$tblname;
			if ( JFile::exists($file) )
			{
				$indexing_start_secs = (int) file_get_contents($file);
				$indexing_started = $indexing_start_secs + 3600 > time();
				if (!$indexing_started) {
					JFile::delete($file);
				}
			}

			$_update_clauses = array();
			foreach($indexnames as $indexname => $iconf)
			{
				$query = "SELECT 1"
					. " FROM INFORMATION_SCHEMA.STATISTICS"
					. " WHERE TABLE_SCHEMA = '".$dbname."' AND TABLE_NAME = '".$dbprefix.$tblname."' AND index_name='".$indexname."'"
					. (is_array($iconf) && !empty($iconf['cols'])
						? " AND COLUMN_NAME IN ('" . implode("','", array_keys($iconf['cols'])) . "')"
						: "")
					. " GROUP BY TABLE_NAME"
					. (is_array($iconf) && !empty($iconf['cols'])
						? " HAVING COUNT(TABLE_NAME) = ".count($iconf['cols'])
						: "")
					;
				//if (is_array($iconf) && !empty($iconf['cols'])) echo $query ."<br/>";

				$this->_db->setQuery($query);
				$exists = $this->_db->loadResult();
				$exists = is_array($iconf) && !empty($iconf['custom_drop']) && empty($iconf['cols']) ? !$exists : $exists;   // If 'custom_drop' exists but we have zero 'cols' it means we want to drop the index

				if ($indexing_started)
				{
					if ($exists) {
						JFile::delete($file);
						continue;
					}
					else $missing[$tblname]['__indexing_started__'] = 1;
				}

				else if (!$exists)
				{
					$all_started = false;

					// Check for case that index is a column name needed an UPDATE query
					if ( is_array($iconf) && !empty($iconf['update']))
					{
						$colname = $indexname;
						$_update_clauses[] = $colname . ' = ' . $iconf['update'];
						unset($iconf['update']);
						if (empty($iconf)) $iconf = 0;
					}

					$missing[$tblname][$indexname] = $iconf;
				}
			}

			if (count($_update_clauses))
			{
				$update_queries[$tblname] = 'UPDATE #__' . $tblname . ' SET ' . implode(' , ', $_update_clauses);
			}
		}

		// Indexing for all tables has started, clear the 'missing' array ... so that post-installation task will not appear
		if ($all_started)
		{
			$missing = array();
		}

		return $check_only
			? empty($missing)
			: $missing;
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
	function getCacheThumbPerms()
	{
		static $return;
		if ($return!==null) return $return;

		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.path');

		$conf_override_file = JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'phpThumb.config_OVERRIDE.php');

		// CHECK phpThumb configuration override file exists and create it, if it does not exist
		if ( !JFile::exists($conf_override_file) )
		{
			$file_contents =
				'<?php'."\n".
				'// THIS FILES IS NOT OVERWRITTEN BY FLEXICONTENT'."\n".
				'// PLEASE ADD YOUR CUSTOM CONFIGURATION STRINGS HERE'."\n".
				''."\n".
				'// * DocumentRoot configuration'."\n".
				'// phpThumb() depends on $_SERVER[\'DOCUMENT_ROOT\'] to resolve path/filenames. This value is usually correct,'."\n".
				'// but has been known to be broken on some servers. This value allows you to override the default value.'."\n".
				'// Do not modify from the auto-detect default value unless you are having problems.'."\n".
				''."\n".
				'// $PHPTHUMB_CONFIG[\'document_root\'] =  ...;'."\n";

			// write .htaccess file
			$fh = @ fopen($conf_override_file, 'w');
			if (!$fh) {
				JFactory::getApplication()->enqueueMessage( 'Cannot create/write file: '.$conf_override_file, 'notice' );
			} else {
				fwrite($fh, $file_contents);
				fclose($fh);
			}
		}


		$phpthumbcache 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'cache');

		// CHECK phpThumb cache exists and create the folder
		if ( !JFolder::exists($phpthumbcache) && !JFolder::create($phpthumbcache) )
		{
			JError::raiseWarning(100, 'Error: Unable to create phpThumb folder: '. $phpthumbcache .' image thumbnail will not work properly' );
			$return = true;  // Cancel task !! to allow user to continue
			return;
		}

		// CHECK phpThumb cache permissions
		$perms = JPath::getPermissions($phpthumbcache);
		$return = preg_match('/rwx....../i', $perms) ? true : false;  //'/rwxr.xr.x/i'
		if ( $return && preg_match('/....w..w./i', $perms) )
		{
			JPath::setPermissions($phpthumbcache, '0600', '0700');
		}

		// If permissions not good check if we can change them
		if ( !$return && !JPath::canChmod($phpthumbcache) )
		{
			JError::raiseWarning(100, 'Error: Unable to change phpThumb folder permissions: '. $phpthumbcache .' there maybe a wrong owner of the folder. Correct permissions are important for proper thumbnails and for -security-' );
			$return = true;  // Cancel task !! to allow user to continue
			return;
		}

		return $return;
	}

	/**
	 * TODO IF/WHEN THIS LIST GROWS: Move it to an optional "Cleanup task" or use both session and joomla caching with long-expire to recheck it
	 *
	 * Method to check if the files from beta3 still exist in the category and item view
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function getDeprecatedFiles(&$deprecated = null, $done = false)
	{
		// Store JPATH_BASE variable (needed in case of hard linking to flexicontent files outside of Joomla folder)
		if (JPATH_BASE != realpath(__DIR__.'/../..') || !file_exists(__DIR__.'/tasks/defines.php') || filemtime(__FILE__) >= filemtime(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tasks'.DS.'defines.php'))
		{
			file_put_contents(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tasks'.DS.'defines.php', '<?php'."\n".'define(\'JPATH_BASE\', \''.JPATH_SITE.'\');'."\n");
		}
		if (JPATH_BASE != realpath(__DIR__.'/..') || !file_exists(__DIR__.'/tasks/defines.php') || filemtime(__FILE__) >= filemtime(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tasks'.DS.'defines.php'))
		{
			file_put_contents(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tasks'.DS.'defines.php', '<?php'."\n".'define(\'JPATH_BASE\', \''.JPATH_ADMINISTRATOR.'\');'."\n");
		}

		$deprecated = array();
		static $finished;

		if ($finished===true)
		{
			return $finished;
		}

		$finished = false;

		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		$deprecated['files'] = array();
		$deprecated['folders'] = array();

		$done_file = JPath::clean(JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/deprecated_done');
		$depr_file = JPath::clean(JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/deprecated_list.php');

		// If file / folder removal already done then stop further steps
		if (JFile::exists($done_file) && (filemtime($done_file) > filemtime($depr_file)))
		{
			$finished = true;
			return $finished;
		}

		// Mark file / folder removal as finished and stop further steps
		if ($done)
		{
			touch ($done_file);
			$finished = true;
			return $finished;
		}

		// Load list of depreacted files and folders
		include($depr_file);

		foreach ($files as $file)
		{
			if (JFile::exists($file))
			{
				$deprecated['files'][] = $file;
			}
		}

		foreach ($folders as $folder)
		{
			if (JFolder::exists($folder))
			{
				$deprecated['folders'][] = $folder;
			}
		}

		// Calculate if finished
		$deprecated_count = count($deprecated['files']) + count($deprecated['folders']);
		$finished = $deprecated_count ? false : true;

		// Mark file / folder removal as finished if zero files and folders need to be removed
		if ($finished)
		{
			touch ($done_file);
		}

		return $finished;
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
		$this->_db->execute();
		$query = "
				CREATE TABLE #__flexicontent_positions_tmp (
				  `field` varchar(100) NOT NULL default '',
				  `view` varchar(30) NOT NULL default '',
				  `folder` varchar(100) NOT NULL default '',
				  `position` varchar(255) NOT NULL default ''
				) ENGINE=MyISAM DEFAULT CHARSET=utf8
				";
		$this->_db->setQuery( $query );
		$this->_db->execute();

		foreach ($fields as $field) {
			$field->positions = explode("\n", $field->positions);
			foreach ($field->positions as $pos) {
				$pos = explode('.', $pos);
				$query = 'INSERT INTO #__flexicontent_positions_tmp (`field`, `view`, `folder`, `position`) VALUES(' . $this->_db->Quote($field->name) . ',' . $this->_db->Quote($pos[1]) . ',' . $this->_db->Quote($pos[2]) . ',' . $this->_db->Quote($pos[0]) . ')';
				$this->_db->setQuery($query);
				$this->_db->execute();
			}
		}

		$tmpls   = flexicontent_tmpl::getTemplates();
		$folders = flexicontent_tmpl::getThemes();
		$views   = array('items', 'category');

		foreach ($folders as $folder)
		{
			foreach ($views as $view)
			{
				$groups = @ $tmpls->{$view}->{$folder}->positions;
				if ($groups) {
					foreach ($groups as $group) {
						$query 	= 'SELECT field'
								. ' FROM #__flexicontent_positions_tmp'
								. ' WHERE view = ' . $this->_db->Quote($view)
								. ' AND folder = ' . $this->_db->Quote($folder)
								. ' AND position = ' . $this->_db->Quote($group)
								;
						$this->_db->setQuery( $query );
						$fieldstopos = $this->_db->loadColumn();

						if ($fieldstopos)
						{
							$field = implode(',', $fieldstopos);

							$query = 'INSERT INTO #__flexicontent_templates (`template`, `layout`, `position`, `fields`) VALUES(' . $this->_db->Quote($folder) . ',' . $this->_db->Quote($view) . ',' . $this->_db->Quote($group) . ',' . $this->_db->Quote($field) . ')';
							$this->_db->setQuery($query);

							// By catching SQL error (e.g. layout configuration of template already exists),
							// we will allow execution to continue, thus clearing "positions" column in fields table
							try { $this->_db->execute(); } catch (Exception $e) { echo $e->getMessage(); }
						}
					}
				}
			}
		}

		// delete the temporary table
		$query = 'DROP TABLE #__flexicontent_positions_tmp';
		$this->_db->setQuery( $query );
		$this->_db->execute();

		// delete the old positions
		$query 	= "UPDATE #__flexicontent_fields SET positions = ''";
		$this->_db->setQuery( $query );
		$this->_db->execute();

		// alter ordering field for releases prior to beta5
		$query 	= "ALTER TABLE #__flexicontent_cats_item_relations MODIFY `ordering` int(11) NOT NULL default '0'";
		$this->_db->setQuery( $query );
		$this->_db->execute();
		$query 	= "ALTER TABLE #__flexicontent_fields_type_relations MODIFY `ordering` int(11) NOT NULL default '0'";
		$this->_db->setQuery( $query );
		$this->_db->execute();

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

		$db = $this->_db;
		$nullDate	= $db->getNullDate();

		// @TODO: move somewhere else
		$this->formatFlexiPlugins();

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
					$db->execute();
				}

				// Delete any existing versioned field values to avoid conflicts, this maybe redudant, since they should not exist,
				// but we do it anyway because someone may have truncated or delete records only in table 'flexicontent_versions' ...
				// NOTE: we do not delete data with field_id negative as these exist only in the versioning table
				$query = 'DELETE FROM #__flexicontent_items_versions WHERE item_id = '.$row->id .' AND version= '.$row->version.' AND field_id > 0';
				$db->setQuery($query);
				$db->execute();

				// Add the 'maintext' field to the fields array for adding to versioning table
				$f = new stdClass();
				$f->id					= 1;
				$f->iscore			= 1;
				$f->valueorder	= 1;
				$f->suborder		= 1;
				$f->field_type	= "maintext";
				$f->name				= "text";
				$f->value				= $row->introtext;
				if ( StringHelper::strlen($row->fulltext) > 1 ) {
					$f->value .= '<hr id="system-readmore" />' . $row->fulltext;
				}
				if(substr($f->value, 0, 3)!="<p>") {
					$f->value = "<p>".$f->value."</p>";
				}
				$fieldsvals[] = $f;

				// Add the 'categories' field to the fields array for adding to versioning table
				$query = "SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid='".$row->id."';";
				$db->setQuery($query);
				$categories = $db->loadColumn();
				if (!$categories || !count($categories))
				{
					$categories = array($catid = $row->catid);
					$query = "INSERT INTO #__flexicontent_cats_item_relations VALUES('$catid','".$row->id."', '0');";
					$db->setQuery($query);
					$db->execute();
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
				$tags = $db->loadColumn();
				$f = new stdClass();
				$f->id 					= 14;
				$f->iscore			= 1;
				$f->valueorder	= 1;
				$f->suborder		= 1;
				$f->version		= (int)$row->version;
				$f->value		= serialize($tags);
				if ($add_tags) $fieldsvals[] = $f;

				// Add field values to field value versioning table
				foreach($fieldsvals as $fieldval)
				{
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
					if( $clean_database && !$fieldval->iscore )  // If clean_database is on we need to re-add the deleted values
					{
						unset($obj->version);
						//echo "inserting into __flexicontent_fields_item_relations<br />";
						$db->insertObject('#__flexicontent_fields_item_relations', $obj);
						flexicontent_db::setValues_commonDataTypes($obj);
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
			. '  AND folder =' . $db->Quote('flexicontent_fields')
			. '  AND `type`=' . $db->Quote('plugin')
			;
		if ( count($cases) )
		{
			$db->setQuery($query)->execute();
		}

		/*$map = array(
			'account_via_submit'=>'FLEXIcontent - User account via submit',
			'authoritems'=>'FLEXIcontent - Author Items (More items by this Author)',
			'addressint'=>'FLEXIcontent - Address International / Google Maps',
			'checkbox'=>'FLEXIcontent - Checkbox',
			'checkboximage'=>'FLEXIcontent - Checkbox Image',
			'color'=>'FLEXIcontent - Color',
			'core'=>'FLEXIcontent - Core Fields (Joomla article properties)',
			'date'=>'FLEXIcontent - Date / Timestamp / Publish Up-Down Dates',
			'email'=>'FLEXIcontent - Email',
			'fcloadmodule'=>'FLEXIcontent - Load Module / Module position',
			'fcpagenav'=>'FLEXIcontent - Navigation (Next/Previous Item)',
			'file'=>'FLEXIcontent - File (Download/View/Share/Download cart)',
			'custom_form_html'=>'FLEXIcontent - Custom HTML / Item Form Tab / Fieldset',
			'image'=>'FLEXIcontent - Image or Gallery (image + details)',
			'linkslist'=>'FLEXIcontent - HTML list of URLs/Anchors/JS links',
			'mediafile'=>'FLEXIcontent - Media file player',
			'phonenumbers'=>'FLEXIcontent - Phone Numbers',
			'radio'=>'FLEXIcontent - Radio',
			'radioimage'=>'FLEXIcontent - Radio Image',
			'relation'=>'FLEXIcontent - Relation (List of related items)',
			'relation_reverse'=>'FLEXIcontent - Relation - Reverse',
			'select'=>'FLEXIcontent - Select',
			'selectmultiple'=>'FLEXIcontent - Select Multiple',
			'sharedmedia'=>'FLEXIcontent - Shared Video/Audio (Youtube,etc / SoundCloud,Last.fm,etc)',
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
		if ( count($cases) )
		{
			$db->setQuery($query);
			$db->execute();
		}*/
	}

	function createLanguagePack($code = 'en-GB', $method = '', $params = array())
	{
		jimport('joomla.filesystem.file');

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
			'com_flexicontent.sys',
		// plugin files  --  flexicontent_fields
			'plg_flexicontent_fields_addressint',
			'plg_flexicontent_fields_checkbox',
			'plg_flexicontent_fields_checkboximage',
			'plg_flexicontent_fields_color',
			'plg_flexicontent_fields_core',
			'plg_flexicontent_fields_date',
			'plg_flexicontent_fields_email',
			'plg_flexicontent_fields_fcloadmodule',
			'plg_flexicontent_fields_fcpagenav',
			'plg_flexicontent_fields_file',
			'plg_flexicontent_fields_custom_form_html',
			'plg_flexicontent_fields_image',
			'plg_flexicontent_fields_linkslist',
			'plg_flexicontent_fields_mediafile',
			'plg_flexicontent_fields_phonenumbers',
			'plg_flexicontent_fields_radio',
			'plg_flexicontent_fields_radioimage',
			'plg_flexicontent_fields_relation',
			'plg_flexicontent_fields_relation_reverse',
			'plg_flexicontent_fields_select',
			'plg_flexicontent_fields_selectmultiple',
			'plg_flexicontent_fields_sharemedia',
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

			$files = array();
			foreach ($fileslist as $i => $filename) {
				$files[$i]=array();
				$files[$i]['name'] = preg_replace("%^(\\\|/)%", "", str_replace($targetfolder, "", $filename) );  // STRIP PATH for filename inside zip
				$files[$i]['data'] = implode('', file($filename));   // READ contents into string, here we use full path
				$files[$i]['time'] = time();
			}

			jimport('joomla.archive.archive');
			$packager = JArchive::getAdapter('zip');
			if (!$packager->create($archivename, $files)) {
				echo JText::_('FLEXI_OPERATION_FAILED');
				return false;
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


	function checkInitialPermission()
	{
		$debug_initial_perms = JComponentHelper::getParams('com_flexicontent')->get('debug_initial_perms');

		static $init_required = null;
		if ( $init_required !== null ) return $init_required;

		$db = $this->_db;
		$component_name = 'com_flexicontent';


		// DELETE old namespace (flexicontent.*) permissions of v2.0beta, we do not try to rename them ... instead we will use com_content (for some of them),
		$query = $db->getQuery(true)->delete('#__assets')->where('name LIKE ' . $db->quote('flexicontent.%'));
		$db->setQuery($query);

		try { $db->execute(); } catch (Exception $e) { echo $e->getMessage(); }

		// DELETE bad top-level assets, only root.1 should exist
		$query = $db->getQuery(true)->delete('#__assets')->where('(parent_id=0 OR level=0) AND name<>' . $db->quote('root.1'));
		$db->setQuery($query);

		try { $db->execute(); } catch (Exception $e) { echo $e->getMessage(); }

		// SET Access View Level to public (=1) for fields that do not have their Level set
		$query = $db->getQuery(true)->update('#__flexicontent_fields')->set('access = 1')->where('access = 0');
		$db->setQuery($query);

		try { $db->execute(); } catch (Exception $e) { echo $e->getMessage(); }

		// SET Access View Level to public (=1) for types that do not have their Level set
		$query = $db->getQuery(true)->update('#__flexicontent_types')->set('access = 1')->where('access = 0');
		$db->setQuery($query);

		try { $db->execute(); } catch (Exception $e) { echo $e->getMessage(); }

		// CHECK that we have the same Component Actions in assets DB table with the Actions as in component's access.xml file
		$asset	= JTable::getInstance('asset');
		if ($comp_section = $asset->loadByName($component_name))    // Try to load component asset, if missing it returns false
		{
			$this->verifyExtraRules();  // Verify that com_content component asset has the extra FC specific rules

			// ok, component asset not missing, proceed to cross check for deleted / added actions
			$rules = new JAccessRules($asset->rules);
			$rules_data = $rules->getData();
			$component_actions = JAccess::getActionsFromFile(
				JPATH_ADMINISTRATOR . '/components/' . $component_name . '/access.xml',
				"/access/section[@name='component']/"
			);

			$db_action_names = array();
			foreach ($rules_data as $action_name => $data)  $db_action_names[]   = $action_name;
			foreach ($component_actions as $action)         $file_action_names[] = $action->name;
			$deleted_actions =  array_diff($db_action_names,   $file_action_names);
			$added_actions   =  array_diff($file_action_names, $db_action_names  );
			foreach($added_actions as $i => $action_name)
			{
				if (substr($action_name, 0, 5) === 'core.') unset($added_actions[$i]);
			}

			$comp_section = ! ( count($deleted_actions) || count($added_actions) );  // false if deleted or addeded actions exist
			if ($debug_initial_perms) { echo "Deleted actions: "; print_r($deleted_actions); echo "<br> Added actions: "; print_r($added_actions); echo "<br>"; }
		}

		if ($debug_initial_perms)
		{
			echo "Component DB Rule Count " . ( ($comp_section) ? count($rules->getData()) : 0 ) . "<br />";
			echo "Component File Rule Count " .
				count(JAccess::getActionsFromFile(
					JPATH_ADMINISTRATOR . '/components/' . $component_name . '/access.xml',
					"/access/section[@name='component']/"
				)) .
				"<br />";
		}

		// Get a list com_content TOP-LEVEL categories that have wrong asset names (do not point to 'com_content' asset)
		// (NOTE: we do not longer force category asset creation if asset is not set, so we will not check them here)
		// !!! WARNING this query must be same like the one USED in function initialPermission()
		$com_content_asset	= JTable::getInstance('asset');
		$com_content_asset->loadByName('com_content');
		$query = $db->getQuery(true)
			->select('c.id')
			->from('#__assets AS se')->join('RIGHT', '#__categories AS c ON se.id=c.asset_id AND se.name=concat("com_content.category.",c.id)')
			->where( '(c.parent_id=1 AND se.parent_id!='.(int)$com_content_asset->id.')' )
			//->where( '(se.id is NULL OR (c.parent_id=1 AND se.parent_id!='.(int)$com_content_asset->id.') )' )
			->where( 'c.extension = ' . $db->quote('com_content') );

		$db->setQuery($query);

		try { $result = $db->loadObjectList(); } catch (Exception $e) { $result = array(); echo $e->getMessage(); }

		if (!empty($result) && $debug_initial_perms) { echo "bad assets for categories: "; print_r($result); echo "<br>"; }
		$category_section = count($result) == 0 ? 1 : 0;

		// CHECK if some fields don't have permissions set, !!! WARNING this query must be same like the one USED in function initialPermission()
		$query = $db->getQuery(true)
			->select('se.id')
			->from('#__assets AS se')->join('RIGHT', '#__flexicontent_fields AS ff ON se.id=ff.asset_id AND se.name=concat("com_flexicontent.field.",ff.id)')
			->where('se.id is NULL');
		$db->setQuery($query);

		try { $result = $db->loadObjectList(); } catch (Exception $e) { $result = array(); echo $e->getMessage(); }

		if (!empty($result) && $debug_initial_perms) { echo "bad assets for fields: "; print_r($result); echo "<br>"; }
		$field_section = count($result) == 0 ? 1 : 0;

		// CHECK if some types don't have permissions set, !!! WARNING this query must be same like the one USED in function initialPermission()
		$query = $db->getQuery(true)
			->select('se.id')
			->from('#__assets AS se')->join('RIGHT', '#__flexicontent_types AS ff ON se.id=ff.asset_id AND se.name=concat("com_flexicontent.type.",ff.id)')
			->where('se.id is NULL');
		$db->setQuery($query);

		try { $result = $db->loadObjectList(); } catch (Exception $e) { $result = array(); echo $e->getMessage(); }

		if (!empty($result) && $debug_initial_perms) { echo "bad assets for types: "; print_r($result); echo "<br>"; }
		$type_section = count($result) == 0 ? 1 : 0;

		if ($debug_initial_perms) { echo "PASSED comp_section:$comp_section && category_section:$category_section && field_section:$field_section && type_section:$type_section <br>"; }

		$init_required = $comp_section && $category_section && $field_section && $type_section;
		return $init_required;
	}


	function updateInitialPermission()
	{
		$app = JFactory::getApplication();
		$component_name	= $app->input->get('option', '', 'CMD');
		$db = $this->_db;
		$asset	= JTable::getInstance('asset');   // Create an asset object

		/*** Component assets ***/
		$asset_exists = $asset->loadByName($component_name);
		if ($asset_exists)
		{
			$rules_arr = strlen(trim($asset->rules))  ?  json_decode($asset->rules, true)  :  array();
		}

		if ( !$asset_exists || !count($rules_arr) )
		{
			// Component asset entry does not exist or is empty: We will create initial rules for all component's actions

			// Get root asset
			$root = JTable::getInstance('asset');
			$root->loadByName('root.1');

			// Initialize component asset
			$asset->name = $component_name;
			$asset->title = $component_name;
			$asset->setLocation($root->id,'last-child');  // father of compontent asset it the root asset

			// Create initial component rules and set them into the asset
			$component_rules_arr = $this->_createComponentRules($component_name);
			$component_rules = new JAccessRules($component_rules_arr);
			$asset->rules = (string) $component_rules;

			// Save the asset into the DB
			if (!$asset->check() || !$asset->store())
			{
				echo $asset->getError();
				$this->setError($asset->getError());
				return false;
			}
		}

		else
		{
			// Component assets entry already exists and is non-empty: We will check if it has exactly the actions specified in component's access.xml file

			// Get existing DB rules and component's actions from the access.xml file
			$existing_rules = new JAccessRules($asset->rules);
			$rules_data = $existing_rules->getData();
			$component_actions = JAccess::getActionsFromFile(
				JPATH_ADMINISTRATOR . '/components/' . $component_name . '/access.xml',
				"/access/section[@name='component']/"
			);

			// Find any deleted / added actions ...
			$db_action_names = array();
			foreach ($rules_data as $action_name => $data)  $db_action_names[]   = $action_name;
			foreach ($component_actions as $action)         $file_action_names[] = $action->name;
			$deleted_actions =  array_diff($db_action_names,   $file_action_names);
			$added_actions   =  array_diff($file_action_names, $db_action_names  );
			foreach($added_actions as $i => $action_name)
			{
				if (substr($action_name, 0, 5) === 'core.') unset($added_actions[$i]);
			}

			if ( count($deleted_actions) || count($added_actions) )
			{
				// We have changes in the component actions

				// First merge the existing component (db) rules into the initial rules
				$component_rules_arr = $this->_createComponentRules($component_name, $added_actions);
				$component_rules = new JAccessRules($component_rules_arr);
				$component_rules->merge($existing_rules);

				// Second, check if obsolete rules are contained in the existing component (db) rules, if so create a new rules object without the obsolete rules
				if ($deleted_actions)
				{
					$rules_data = $component_rules->getData();
					foreach($deleted_actions as $action_name)
					{
						unset($rules_data[$action_name]);
					}
					$component_rules = new JAccessRules($rules_data);
				}

				// Set asset rules
				$asset->rules = (string) $component_rules;

				// Save the asset
				if (!$asset->check() || !$asset->store())
				{
					echo $asset->getError();
					$this->setError($asset->getError());
					return false;
				}
			}
		}

		// Load component asset
		$component_asset = JTable::getInstance('asset');
		$component_asset->loadByName($component_name);

		// Load com_content asset
		$com_content_asset = JTable::getInstance('asset');
		$com_content_asset->loadByName('com_content');
		$com_content_name = 'com_content';

		/*** CATEGORY assets ***/

		// Get a list com_content TOP-LEVEL categories that have wrong asset names (do not point to 'com_content' asset)
		// (NOTE: we do not longer force category asset creation if asset is not set)
		$query = $db->getQuery(true)
			->select('c.id, c.parent_id, c.title, c.asset_id')
			->from('#__assets AS se')->join('RIGHT', '#__categories AS c ON se.id=c.asset_id AND se.name=concat("com_content.category.",c.id)')
			->where( '(c.parent_id=1 AND se.parent_id!='.(int)$com_content_asset->id.')' )
			//->where( '(se.id is NULL OR (c.parent_id=1 AND se.parent_id!='.(int)$com_content_asset->id.') )' )
			->where( 'c.extension = ' . $db->quote('com_content') )
			->order('c.level ASC');   // IMPORTANT create categories asset using increasing depth level, so that get parent assetid will not fail

		$results = $db->setQuery($query)->loadObjectList();

		// Check that any assets of top-level categories point to the correct component (we used to make these point to 'com_flexicontent' asset)
		$bad_cat_assets = array();
		foreach($results as $category)
		{
			$parentId = $this->_getAssetParentId(null, $category);
			$name = "com_content.category.{$category->id}";

			// Try to load asset for the current CATEGORY ID
			$asset_found = $asset->loadByName($name);
			if ( !$asset_found ) continue; // nothing to do

			// Since this is a top level category make sure, that the asset points to the correct component asset (com_content)
			$asset->setLocation($parentId, 'last-child');

			// Save the category asset (create or update it)
			if (!$asset->check() || !$asset->store(false))
			{
				//echo $asset->getError();
				//echo " Problem for asset with id: ".$asset ->id;
				//echo " Deleting but category asset for category #: ".$category->id. "(".$category->title.")";
				//$this->setError($asset->getError());
				//return false;
				$bad_cat_assets[] = $category->id;

				// DELETE bad category asset
				$query = $db->getQuery(true)
					->delete('#__assets')
					->where( ($asset->id ? ' id = ' . (int)$asset->id .' AND ' : '') . ' name = ' . $db->quote($name) );
				$db->setQuery($query);

				try { $db->execute(); } catch (Exception $e) { echo $e->getMessage(); }

				// Nothing more to do
				continue;
			}

			// Assign the asset to the category, if it is not already assigned
			$query = $db->getQuery(true)
				->update('#__categories')
				->set('asset_id = ' . (int)$asset->id)
				->where('id = ' . (int)$category->id);
			$db->setQuery($query);
			try { $db->execute(); } catch (Exception $e) { echo $e->getMessage(); }
		}
		if (!empty($bad_cat_assets))
		{
			echo '
			<div class="alert alert-warning">Please refresh this page, deleted bad assets for category IDs:
				'. print_r($bad_cat_assets, true) .'
			</div>';
		}


		/*** FLEXIcontent FIELDS assets ***/

		// Get a list flexicontent fields that do not have assets
		$query = $db->getQuery(true)
			->select('ff.id, ff.name, ff.label, ff.asset_id')
			->from('#__assets AS se')->join('RIGHT', '#__flexicontent_fields AS ff ON se.id=ff.asset_id AND se.name=concat("com_flexicontent.field.",ff.id)')
			->where('se.id is NULL');
		$db->setQuery($query);
		$results = $db->loadObjectList();

		// Add an asset to every field that doesnot have one
		if ($results)
		{
			foreach($results as $field)
			{
				$name = "com_flexicontent.field.{$field->id}";

				// Test if an asset for the current FIELD ID already exists and load it instead of creating a new asset
				if ( ! $asset->loadByName($name) )
				{
					if ($field->asset_id)
					{
						// asset name not found but field has an asset id set ?, we could delete it here
						// but it maybe dangerous to do so ... it might be a legitimate asset_id for something else
					}

					// Initialize field asset
					$asset->id = null;
					$asset->name		= $name;
					$asset->title		= $field->label;
					$asset->setLocation($component_asset->id, 'last-child');     // Permissions of fields are directly inheritted by component

					// Set asset rules to empty, (DO NOT set any ACTIONS, just let them inherit ... from parent)
					$asset->rules = new JAccessRules();
					/*
					$actions = JAccess::getActionsFromFile(
						JPATH_ADMINISTRATOR . '/components/' . $component_name . '/access.xml',
						"/access/section[@name='field']/"
					);
					$rules 		= json_decode($component_asset->rules);
					foreach ($actions as $action) {
						$fieldrules[$action->name] = $rules->{$action->name};
					}
					$asset->rules = new JAccessRules(json_encode($fieldrules));
					*/
					$asset->rules = (string) $asset->rules;

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
				try { $db->execute(); } catch (Exception $e) { echo $e->getMessage(); }
			}
		}


		/*** FLEXIcontent TYPES assets ***/

		// Get a list flexicontent types that do not have assets
		$query = $db->getQuery(true)
			->select('ff.id, ff.alias, ff.name, ff.asset_id')
			->from('#__assets AS se')->join('RIGHT', '#__flexicontent_types AS ff ON se.id=ff.asset_id AND se.name=concat("com_flexicontent.type.",ff.id)')
			->where('se.id is NULL');
		$db->setQuery($query);
		$results = $db->loadObjectList();

		// Add an asset to every type that doesnot have one
		if($results)
		{
			foreach($results as $type)
			{
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
					$actions = JAccess::getActionsFromFile(
						JPATH_ADMINISTRATOR . '/components/' . $component_name . '/access.xml',
						"/access/section[@name='type']/"
					);
					$rules 		= json_decode($component_asset->rules);
					foreach ($actions as $action) {
						$typerules[$action->name] = $rules->{$action->name};
					}
					$asset->rules = new JAccessRules(json_encode($typerules));
					*/
					$asset->rules = (string) $asset->rules;

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
				try { $db->execute(); } catch (Exception $e) { echo $e->getMessage(); }
			}
		}

		/**
		 * Clean component's VIEW Cache and categories cache
		 * so that per user permissions objects are recalculated
		 */
		$this->cleanCache(null, $client = 0);
		$this->cleanCache(null, $client = 1);
		$this->cleanCache($group = 'com_flexicontent_cats', $client = 0);
		$this->cleanCache($group = 'com_flexicontent_cats', $client = 1);

		// Indicate to our system plugin that its category cache needs to be cleaned
		JFactory::getSession()->set('clear_cats_cache', 1, 'flexicontent');

		return true;
	}


	/**
	 * Method to check if search indexes need to be updated because of changing publication state or search flags of fields
	 *
	 * @access public
	 * @return array
	*/
	function checkDirtyFields()
	{
		if ( !FlexicontentHelperPerm::getPerm()->CanFields ) return;

		$db  = JFactory::getDbo();
		$app = JFactory::getApplication();

		/**
		 * GET fields having dirty field properties,
		 * NOTE: a dirty field property means that search index must be updated,
		 *   even if the field was unpublished,
		 *   because the field values may still exists in the search index for some items,
		 */

		// Regardless publication state
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from('#__flexicontent_fields')
			->where('issearch=-1 OR issearch=2 OR isfilter=-1 OR isfilter=2')
			;
		$dirty_basic = $db->setQuery($query)->loadResult();

		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from('#__flexicontent_fields')
			->where('isadvsearch=-1 OR isadvsearch=2 OR isadvfilter=-1 OR isadvfilter=2')
			;
		$dirty_advanced = $db->setQuery($query)->loadResult();

		if ($dirty_basic)
		{
			echo '
			<div class="fcclear"></div>
			<div class="alert alert-info">' .
				JText::sprintf('FLEXI_ALERT_UPDATE_SINDEX_BASIC',
					$dirty_basic,
					' href="index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=basic" '.
					' class="btn" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 550, 420, function(){window.location.reload(false)}); return false;" '
				) . '
			</div>';
		}
		if ($dirty_advanced)
		{
			echo '
			<div class="fcclear"></div>
			<div class="alert alert-info">' .
				JText::sprintf('FLEXI_ALERT_UPDATE_SINDEX_ADVANCED',
					$dirty_advanced,
					' href="index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=advanced" ' .
					' class="btn" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 550, 420, function(){window.location.reload(false)}); return false;" '
				) . '
			</div>';
		}
	}


	/**
	 * Creates initial component actions based on global config and on some ... logic
	 *
	 * @return  array
	 * @since   11.1
	 */
	protected function _createComponentRules($component, $added_actions=false)
	{
		// ***
		// *** Get com_flexicontent asset
		// ***

		$comp_asset = JTable::getInstance('asset');
		$existing_rules = $comp_asset->loadByName('com_flexicontent') && !empty($comp_asset->rules)
			? json_decode($comp_asset->rules, true)
			: array();
		$asset_is_empty = !count($existing_rules);


		// ***
		// *** Get flexicontent ACTION names
		// ***

		$flexi_actions = JAccess::getActionsFromFile(
			JPATH_ADMINISTRATOR . '/components/' . $component . '/access.xml',
			"/access/section[@name='component']/"
		);

		foreach($flexi_actions as $action)
		{
			$flexi_action_names[$action->name] = 1;
		}

		// We will either populate all action names or just those that were given (new actions)
		$new_actions = is_array($added_actions)
			? array_flip($added_actions)
			: $flexi_action_names;

		// Initialize non-existing flexicontent ACL rules to empty
		$flexi_rules = array();
		foreach($new_actions as $action_name => $_i)
		{
			// * WE NEED THIS (even if it remains empty array), because we will compare COMPONENT actions in DB when checking initial permissions
			$flexi_rules[$action_name] = !isset($flexi_rules[$action_name])
				? array()
				: $flexi_rules[$action_name];
		}


		// ***
		// *** Get com_content asset and its rule names
		// ***

		$com_content_asset = JTable::getInstance('asset');
		$com_content_asset->loadByName('com_content');
		$com_content_rules = json_decode($com_content_asset->rules, true);

		foreach ($com_content_rules as $action_name => $data)
		{
			$joomla_action_names[$action_name] = 1;
		}
		//echo "<pre>"; print_r($com_content_rules); echo "</pre>"; exit;


		// ***
		// *** If com_flexicontent ASSET was empty then copy rules from com_content asset
		// ***

		if ( $asset_is_empty )
		{
			// Handle some special case of custom-added ACTIONs
			// e.g. Grant --OWNED-- actions if they have the corresponding --GENERAL-- actions
			if ( !isset($com_content_rules['core.delete.own']) )
			{
				$com_content_rules['core.delete.own'] = isset($com_content_rules['core.delete']) ? $com_content_rules['core.delete'] : array();
			}
			if ( !isset($com_content_rules['core.edit.state.own']) )
			{
				$com_content_rules['core.edit.state.own'] = isset($com_content_rules['core.edit.state']) ? $com_content_rules['core.edit.state'] : array();
			}

			// Copy rules from com_content asset
			foreach ($com_content_rules as $action_name => $data)
			{
				$flexi_rules[$action_name] = $data;
			}

			// Save the asset into the DB
			$com_content_asset->rules = json_encode($com_content_rules);
			if (!$com_content_asset->check() || !$com_content_asset->store())
			{
				die($com_content_asset->getError());
			}

			// By default DO NOT SET the edit field values privilege, because we have another parameter "allow any editor" and also to allow easier configuration via SOFT DENY
			//$flexi_rules['flexicontent.editfieldvalues'] = $flexi_rules['core.edit'];  // can EditFieldValues
		}


		// ***
		// *** Grant FLEXIcontent specific rules to user having GLOBAL "core.manage"
		// ***

		$groups = $this->_getUserGroups();
		$added = false;

		foreach($groups as $group)
		{
			// This unlike JUser::authorize will not return true for super-user, (we don't need to set anything for super-user group, because its users will be authorized by default)
			if ( JAccess::checkGroup($group->id, 'core.manage') ) foreach($new_actions as $action_name => $_i)
			{
				// Set flexicontent specific rule
				$flexi_rules[$action_name][$group->id] = 1;
				$added = true;
			}
		}

		if (!$added) foreach($groups as $group)
		{
			// Since there is none User Group with core.manage, we will have to grant these to superadmins so that they are not empty ...
			if ( JAccess::checkGroup($group->id, 'core.admin') ) foreach($new_actions as $action_name => $_i)
			{
				// Set flexicontent specific rule
				$flexi_rules[$action_name][$group->id] = 1;
				$added = true;
			}
		}
		//echo "<pre>"; print_r($flexi_rules); $component_rules = new JAccessRules($flexi_rules); echo $asset_rules = (string) $component_rules; echo "</pre>"; exit;


		// ***
		// *** Rules that should be allowed by default, give these to the "Public" and "Registered" user groups
		// ***

		$grant_to_all = array('flexicontent.change.cat', 'flexicontent.change.cat.sec', 'flexicontent.change.cat.feat', 'flexicontent.uploadfiles', 'flexicontent.editownfile', 'flexicontent.publishownfile', 'flexicontent.deleteownfile');
		foreach($grant_to_all as $_name)
		{
			if ( !isset($new_actions[$_name] ) ) continue;
			$flexi_rules[$_name][1] = $flexi_rules[$_name][2] = 1;
		}
		//echo "<pre>"; print_r($flexi_rules); echo "</pre>"; exit;

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
				->where('name = '.$this->_db->quote('com_content'));
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


	protected function verifyExtraRules()
	{
		$debug_initial_perms = JComponentHelper::getParams('com_flexicontent')->get('debug_initial_perms');

		$fc_asset = JTable::getInstance('asset');
		$fc_asset->loadByName('com_flexicontent');
		$fc_asset_rules = json_decode($fc_asset->rules, true);
		if ( !count($fc_asset_rules) )  return; // The asset is empty do not try anything

		$com_content_asset = JTable::getInstance('asset');
		$com_content_asset->loadByName('com_content');
		$com_content_rules = json_decode($com_content_asset->rules, true);

		$save_asset = false;

		foreach($com_content_rules as $rule_name => $rule_data)
		{
			if ( isset($fc_asset_rules[$rule_name]) && $com_content_rules[$rule_name] != $fc_asset_rules[$rule_name] )
			{
				$com_content_rules[$rule_name] = $fc_asset_rules[$rule_name];
				$save_asset = true;
			}
		}

		if ( isset($fc_asset_rules['core.delete.own']) )
		{
			if ( !isset($com_content_rules['core.delete.own']) || $com_content_rules['core.delete.own'] != $fc_asset_rules['core.delete.own'] )
			{
				$com_content_rules['core.delete.own'] = $fc_asset_rules['core.delete.own'];
				$save_asset = true;
			}
		}

		if ( isset($fc_asset_rules['core.edit.state.own']) )
		{
			if ( !isset($com_content_rules['core.edit.state.own']) || $com_content_rules['core.edit.state.own'] != $fc_asset_rules['core.edit.state.own'] )
			{
				$com_content_rules['core.edit.state.own'] = $fc_asset_rules['core.edit.state.own'];
				$save_asset = true;
			}
		}

		if ( !isset($com_content_rules['core.delete.own']) )       $com_content_rules['core.delete.own'] = array();
		if ( !isset($com_content_rules['core.edit.state.own']) )   $com_content_rules['core.edit.state.own'] = array();

		if ($save_asset)
		{
			$rules = new JAccessRules($com_content_rules);
			$com_content_asset->rules = (string) $rules;
			if (!$com_content_asset->check() || !$com_content_asset->store())
			{
				throw new RuntimeException($com_content_asset->getError());
			}
			if ($debug_initial_perms) JFactory::getApplication()->enqueueMessage( 'Updated component asset with extra rules', 'notice' );
		}
		else {
			if ($debug_initial_perms) JFactory::getApplication()->enqueueMessage( 'No update needed for component asset with extra rules', 'notice' );
		}
	}
}
