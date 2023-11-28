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

defined('_JEXEC') or die;

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentControllerBaseAdmin', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php');

// Manually import models in case used by frontend, then models will not be autoloaded correctly via getModel('name')
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'flexicontent.php';

/**
 * FLEXIcontent Dashboard Controller (RAW)
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerFlexicontent extends FlexicontentControllerBaseAdmin
{
	/**
	 * Constructor
	 *
	 * @param   array   $config    associative array of configuration settings.
	 *
	 * @since 3.3
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Register task aliases
		$this->registerTask('createdefaultfields',		'createDefaultFields');
		$this->registerTask('createdefaultcpfields',  'createDefaultCpFields');
		$this->registerTask('createmenuitems',				'createMenuItems');
		$this->registerTask('createdefaultype', 			'createDefaultType');
		$this->registerTask('publishplugins', 				'publishPlugins');
		$this->registerTask('addmcatitemrelations',		'addMcatItemRelations');
		$this->registerTask('updatelanguageData',			'updateLanguageData');
		$this->registerTask('createdbindexes',				'createDBindexes');
		$this->registerTask('createversionstable',		'createVersionsTable');
		$this->registerTask('populateversionstable',	'populateVersionsTable');
		$this->registerTask('createauthorstable',			'createauthorstable');
		$this->registerTask('setcachethumbperms',			'setCacheThumbPerms');
		$this->registerTask('updateitemcountingdata',	'updateItemCountingData');
		$this->registerTask('deletedeprecatedfiles',	'deleteDeprecatedFiles');
		$this->registerTask('cleanupoldtables',				'cleanupOldTables');
		$this->registerTask('addcurrentversiondata',	'addCurrentVersionData');
		$this->registerTask('updateinitialpermission', 'updateInitialPermission');

		$this->registerTask('createlanguagepack',			'createLanguagePack');
		$this->registerTask('fcversioncompare',				'FCVersionCompare');
	}


	/**
	 * Method to create default (CORE) fields
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createDefaultFields($skip_success_msg = false)
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$db = JFactory::getDbo();

		$existing = $db->setQuery($db->getQuery(true)
            ->select('id')
            ->from('#__flexicontent_fields')
            ->where('id < 15')
        )->loadColumn();
		$existing = array_flip($existing);

		$query = $db->getQuery(true)
            ->insert('#__flexicontent_fields')
            ->columns('
                `id`, `field_type`, `name`, `label`, `description`, `isfilter`, `iscore`, `issearch`, `isadvsearch`,
                `untranslatable`, `formhidden`, `valueseditable`, `edithelp`, `positions`, `published`, `attribs`,
                `checked_out`, `checked_out_time`, `access`, `ordering`
            ');

		$values = array();

		!isset($existing[1])
			? $values[1] = '1,"maintext","text","Description","Main description text (introtext/fulltext)",0,1,1,0,0,0,0,1,"description.items.default",1,\'{"display_label":"0","trigger_onprepare_content":"1"}\',0,"0000-00-00 00:00:00",1,2'
			: null;
		!isset($existing[2])
			? $values[2] = '2,"created","created","Created","Date this item was created",0,1,1,0,0,0,0,1,"top.items.default\nabove-description-line1-nolabel.category.blog",1,\'{"display_label":"1","date_format":"DATE_FORMAT_LC1","custom_date":"","pretext":"","posttext":""}\',0,"0000-00-00 00:00:00",1,3'
			: null;
		!isset($existing[3])
			? $values[3] = '3,"createdby","created_by","Created by","User (owner) who created this item",0,1,1,0,0,0,0,1,"top.items.default\nabove-description-line1-nolabel.category.blog",1,\'{"display_label":"1","pretext":"","posttext":""}\',0,"0000-00-00 00:00:00",1,4'
			: null;
		!isset($existing[4])
			? $values[4] = '4,"modified","modified","Last modified","Date this item was last modified",0,1,1,0,0,0,0,1,"top.items.default",1,\'{"display_label":"1","date_format":"DATE_FORMAT_LC1","custom_date":"","pretext":"","posttext":""}\',0,"0000-00-00 00:00:00",1,5'
			: null;
		!isset($existing[5])
			? $values[5] = '5,"modifiedby","modified_by","Revised by","User who last modified this item",0,1,1,0,0,0,0,1,"top.items.default",1,\'{"display_label":"1","pretext":"","posttext":""}\',0,"0000-00-00 00:00:00",1,6'
			: null;
		!isset($existing[6])
			? $values[6] = '6,"title","title","Title","Item title",0,1,1,0,0,0,0,1,"",1,\'{"display_label":"1"}\',0,"0000-00-00 00:00:00",1,1'
			: null;
		!isset($existing[7])
			? $values[7] = '7,"hits","hits","Hits","Number of hits",0,1,1,0,0,0,0,1,"",1,\'{"display_label":"1","pretext":"","posttext":"views"}\',0,"0000-00-00 00:00:00",1,7'
			: null;
		!isset($existing[8])
			? $values[8] = '8,"type","document_type","Document type","Document type",0,1,1,0,0,0,0,1,"",1,\'{"display_label":"1","pretext":"","posttext":""}\',0,"0000-00-00 00:00:00",1,8'
			: null;
		!isset($existing[9])
			? $values[9] = '9,"version","version","Version","Latest version number",0,1,1,0,0,0,0,1,"",1,\'{"display_label":"1","pretext":"","posttext":""}\',0,"0000-00-00 00:00:00",1,9'
			: null;
		!isset($existing[10])
			? $values[10] = '10,"state","state","State","Publication status",0,1,1,0,0,0,0,1,"",1,\'{"display_label":"1"}\',0,"0000-00-00 00:00:00",1,10'
			: null;
		!isset($existing[11])
			? $values[11] = '11,"voting","voting","Voting","Voting buttons",0,1,1,0,0,0,0,1,"top.items.default\nabove-description-line2-nolabel.category.blog",1,\'{"display_label":"1","dimension":"16","image":"components/com_flexicontent/assets/images/star-small.png"}\',0,"0000-00-00 00:00:00",1,11'
			: null;
		!isset($existing[12])
			? $values[12] = '12,"favourites","favourites","Favourites","Add to favourites button",0,1,1,0,0,0,0,1,"top.items.default\nabove-description-line2-nolabel.category.blog",1,\'{"display_label":"1"}\',0,"0000-00-00 00:00:00",1,12'
			: null;
		!isset($existing[13])
			? $values[13] = '13,"categories","categories","Categories","Categories this item is assigned to",0,1,1,0,0,0,0,1,"top.items.default\nunder-description-line1.category.blog",1,\'{"display_label":"1","separatorf":"2"}\',0,"0000-00-00 00:00:00",1,13'
			: null;
		!isset($existing[14])
			? $values[14] = '14,"tags","tags","Tags","Tags assigned to this item",0,1,1,0,0,0,0,1,"top.items.default\nunder-description-line2.category.blog",1,\'{"display_label":"1","separatorf":"2"}\',0,"0000-00-00 00:00:00",1,14'
			: null;

        // Check if nothing to add
        if (!$values)
        {
            if (!$skip_success_msg) echo '<span class="install-ok"></span>';
            return;
        }

		try
		{
            foreach($values as $value) $query->values($value);
            $db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			jexit('<span class="install-notok"></span>' . $e->getMessage());
		}

		$type_ids = $db->setQuery('SELECT id FROM `#__flexicontent_types`')->loadColumn();

		if ($type_ids)
		{
			$query = 'REPLACE INTO `#__flexicontent_fields_type_relations` (`field_id`,`type_id`,`ordering`) VALUES';
			$rels = array();

			foreach ($type_ids as $type_id)
			{
				foreach ($values as $field_id => $i)
				{
					$rels[] = '(' . (int) $field_id . ',' . (int) $type_id . ', 0)';
				}
			}
			$query .= implode(', ', $rels);

			try
			{
				$db->setQuery($query)->execute();
			}
			catch (Exception $e)
			{
				jexit('<span class="install-notok"></span>' . $e->getMessage());
			}
		}

		if (!$skip_success_msg) echo '<span class="install-ok"></span>';
	}




	/**
	 * Method to create default (CORE property) fields
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createDefaultCpFields()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$db = JFactory::getDbo();

		JFactory::getLanguage()->load('plg_flexicontent_fields_coreprops', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('plg_flexicontent_fields_coreprops', JPATH_ADMINISTRATOR, null, true);
		
		// !! IMPORTANT core fields have specific fields ID, ranging from 1 - 14
		// !! Make sure these have been creating before trying to add any other fields into the flexicontent_fields DB table
		$this->createDefaultFields($_skip_success_msg = true);

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


		/**
		 * First try to publish existing coreprops fields with name 'form_*' that are not published
		 */
		$query = 'UPDATE #__flexicontent_fields SET published = 1 '
			. ' WHERE field_type = "coreprops" AND name LIKE "form_%"'
			;
		$db->setQuery($query)->execute();


		/**
		 * Now find coreprops fields that are missing
		 */
		$query = 'SELECT name'
			. ' FROM #__flexicontent_fields'
			. ' WHERE field_type = "coreprops" AND name LIKE "form_%"'
			;
		$existing = $db->setQuery($query)->loadColumn();
		$existing = array_flip($existing);

		$vals = array();
		$n = 14;

		foreach($coreprop_names as $prop_name => $prop_label)
		{
			$name  = 'form_' . $prop_name;
			$label = JText::_($prop_label);   // Maybe use language string
			if (!isset($existing[$name]))
			{
				$vals[$name] = ' ("coreprops","' . $name . '", ' . $db->Quote($label). ',"",0,0,0,0,0,0,0,1,"",1,'
					. '\'{"display_label":"1","props_type":"'.$prop_name.'"}\',0,"0000-00-00 00:00:00",1,'. ($n + 1) . ')';
			}
		}


		/**
		 * Create any missing fields
		 */
		if (count($vals) > 0)
		{
			$query 	= '
			INSERT INTO `#__flexicontent_fields` (
				`field_type`,`name`,`label`,`description`,`isfilter`,`iscore`,`issearch`,`isadvsearch`,
				`untranslatable`,`formhidden`,`valueseditable`,`edithelp`,`positions`,`published`,
				`attribs`,`checked_out`,`checked_out_time`,`access`,`ordering`
			) VALUES '	. implode(', ', $vals);

			try
			{
				$db->setQuery($query)->execute();
			}
			catch (Exception $e)
			{
				jexit('<span class="install-notok"></span>' . $e->getMessage());
			}
		}


		/**
		 * Make sure all coreprops fields with name 'form_*' are assigned to all types
		 */
		$coreprops_field_ids = $db->setQuery('SELECT id FROM #__flexicontent_fields WHERE field_type = "coreprops" AND name LIKE "form_%"')->loadColumn();
		$coreprops_field_ids = array_flip($coreprops_field_ids);

		$type_ids = $db->setQuery('SELECT id FROM `#__flexicontent_types`')->loadColumn();

		if ($type_ids)
		{
			$query = 'REPLACE INTO `#__flexicontent_fields_type_relations` (`field_id`,`type_id`,`ordering`) VALUES';
			$rels = array();

			foreach ($type_ids as $type_id)
			{
				foreach ($coreprops_field_ids as $field_id => $i)
				{
					$rels[] = '(' . (int) $field_id . ',' . (int) $type_id . ', 0)';
				}
			}
			$query .= implode(', ', $rels);

			try
			{
				$db->setQuery($query)->execute();
			}
			catch (Exception $e)
			{
				jexit('<span class="install-notok"></span>' . $e->getMessage());
			}
		}

		echo '<span class="install-ok"></span>';
	}



	/**
	 * Method to create default menu items used for SEF links
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createMenuItems()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$db = JFactory::getDbo();
		$db->setQuery("SELECT extension_id FROM #__extensions WHERE element='com_flexicontent' AND type='component' ");
		$flexi_comp_id = $db->loadResult();

		$db->setQuery("DELETE FROM #__menu_types WHERE menutype='flexihiddenmenu' ");
		$db->execute();

		$db->setQuery("INSERT INTO #__menu_types (`menutype`,`title`,`description`) " .
			"VALUES ('flexihiddenmenu', 'FLEXIcontent Hidden Menu', 'A hidden menu to host Flexicontent needed links')"
		);
		$db->execute();

		$db->setQuery("DELETE FROM #__menu WHERE menutype='flexihiddenmenu' ");
		$db->execute();

		$query 	= "INSERT INTO #__menu ("
			. "`menutype`,`title`,`alias`,`path`,`link`,`type`,`published`,`parent_id`,`component_id`,`level`,"
			. "`checked_out`,`checked_out_time`,`browserNav`,`access`,`params`,`lft`,`rgt`,`home`, `language`"
			. (FLEXI_J40GE ? ", `img`" : '')
		. ") VALUES ("
			. "'flexihiddenmenu','Content','content_page','content_page','index.php?option=com_flexicontent&view=flexicontent','component',1,1,$flexi_comp_id,1,"
			. "0,'0000-00-00 00:00:00',0,1,'rootcat=0',0,0,0,'*'"
			. (FLEXI_J40GE ? ", ''" : "")
		. ")";

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			jexit('<span class="install-notok"></span>' . $e->getMessage());
		}

		// Save the created menu item as default_menu_itemid for the component
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$cparams->set('default_menu_itemid', $db->insertid());
		$cparams_str = $cparams->toString();

		$flexi = JComponentHelper::getComponent('com_flexicontent');
		$query = 'UPDATE ' . (FLEXI_J16GE ? '#__extensions' : '#__components')
				. ' SET params = ' . $db->Quote($cparams_str)
				. ' WHERE ' . (FLEXI_J16GE ? 'extension_id' : 'id') . '=' . $flexi->id;

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			jexit('<span class="install-notok"></span>' . $e->getMessage());
		}

		echo '<span class="install-ok"></span>';

		// This is necessary as extension data are cached ... and just above we updated the component parameters -manually- (and (also added menu item)
		$cache = JFactory::getCache();
		$cache->clean('_system');
	}


	/**
	 * Method to create default type : article
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createDefaultType()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$db = JFactory::getDbo();

		$query 	= '
		INSERT INTO `#__flexicontent_types`
			(id, asset_id, name, alias, description, published, checked_out, checked_out_time, access, attribs)
		VALUES
		(
			1, 0, "Article", "article", "", 1, 0, "0000-00-00 00:00:00", 1,
			\'{"ilayout":"grid","hide_maintext":"0","hide_html":"0","maintext_label":"","maintext_desc":"","comments":"","top_cols":"two","bottom_cols":"two","allow_jview":"1"}\'
		)
		';

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			jexit('<span class="install-notok"></span>' . $e->getMessage());
		}

		$query 	= '
		INSERT INTO `#__flexicontent_fields_type_relations`
			(`field_id`,`type_id`,`ordering`)
		VALUES
			(1,1,1), (2,1,2), (3,1,3), (4,1,4), (5,1,5), (6,1,6), (7,1,7), (8,1,8), (9,1,9), (10,1,10), (11,1,11), (12,1,12), (13,1,13), (14,1,14)
		';

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			jexit('<span class="install-notok"></span>' . $e->getMessage());
		}

		echo '<span class="install-ok"></span>';
	}


	/**
	 * Publish FLEXIcontent plugins
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function publishPlugins()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$format = strtolower($this->input->get('format', 'html', 'CMD'));
		$db = JFactory::getDbo();

		$query	= 'UPDATE #__extensions'
			. ' SET enabled = 1'
			. ' WHERE `type`= ' . $db->Quote('plugin')
			. ' AND (`folder` = ' . $db->Quote('flexicontent_fields')
			. ' OR `element` = ' . $db->Quote('flexisearch')
			. ' OR `element` = ' . $db->Quote('flexisystem')
			. ' OR `element` = ' . $db->Quote('flexiadvsearch')
			. ' OR `element` = ' . $db->Quote('flexiadvroute')
			. ' OR (`folder` = ' . $db->Quote('osmap') . ' AND `element` = ' . $db->Quote('com_flexicontent') . ')'
			. ')';

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			if ($format == 'raw')
			{
				echo '<span class="install-notok"></span>';
				echo $e->getMessage();
				jexit();
			}
			else
			{
				$db_err_msg = $e->getMessage();
				JFactory::getApplication()->enqueueMessage(JText::_('FLEXI_COULD_NOT_PUBLISH_PLUGINS') . $db_err_msg, 'notice');

				return false;
			}
		}

		if ($format == 'raw')
		{
			echo '<span class="install-ok"></span>';
		}
		else
		{
			return true;
		}
	}


	/**
	 * Method to set the default site language the items with no language
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function addMcatItemRelations()
	{
		$db = JFactory::getDbo();

		// 1st: remove orphan relations
		$query = "DELETE rel.*"
			. " FROM #__flexicontent_cats_item_relations AS rel"
			. " LEFT JOIN #__content AS i ON i.id = rel.itemid"
			. " WHERE i.id IS NULL";

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			jexit('<span class="install-notok"></span>' . $e->getMessage());
		}

		// 2nd: add missing main category relations
		$subquery 	= "SELECT i.catid, i.id, 0 FROM #__flexicontent_items_ext as ie "
			. " JOIN #__content as i ON i.id=ie.item_id "
			. " LEFT JOIN #__flexicontent_cats_item_relations as rel ON rel.catid=i.catid AND i.id=rel.itemid "
			. " WHERE rel.catid IS NULL";

		// Set default language for items that do not have their language set
		$query 	= 'INSERT INTO #__flexicontent_cats_item_relations'
			. ' (catid, itemid, ordering) ' . $subquery;

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			jexit('<span class="install-notok"></span>' . $e->getMessage());
		}

		echo '<span class="install-ok"></span>';
	}


	/**
	 * Method to update / sync language data
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function updateLanguageData()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$db = JFactory::getDbo();
		$nullDate	= $db->getNullDate();

		// Add language column
		$columns = $db->getTableColumns('#__flexicontent_items_ext');
		$language_col = array_key_exists('language', $columns) ? true : false;

		if (!$language_col)
		{
			$query 	= "ALTER TABLE #__flexicontent_items_ext ADD `language` VARCHAR( 11 ) NOT NULL DEFAULT '' AFTER `type_id`";
			$db->setQuery($query);
			$result_lang_col = $db->execute();

			if (!$result_lang_col)
			{
				echo "Cannot add language column<br>";
			}
		}
		else
		{
			$result_lang_col = true;
		}

		// Add translation group column
		$lang_parent_id_col = array_key_exists('lang_parent_id', $columns) ? true : false;

		if (!$lang_parent_id_col)
		{
			$query 	= "ALTER TABLE #__flexicontent_items_ext ADD `lang_parent_id` INT NOT NULL DEFAULT 0 AFTER `language`";
			$db->setQuery($query);
			$result_tgrp_col = $db->execute();

			if (!$result_tgrp_col)
			{
				echo "Cannot add translation group column<br>";
			}
		}
		else
		{
			$result_tgrp_col = true;
		}

		// Add default language for items that do not have one, and add translation group to items that do not have one set
		$model = $this->getModel('flexicontent');

		if ($model->getItemsBadLang())
		{
			// 1. copy language from __flexicontent_items_ext table into __content
			$model->syncItemsLang();

			// 2. then for those that are still empty, add site default language to the language field if empty
			$lang = flexicontent_html::getSiteDefaultLang();
			$result_items_default_lang = $model->setItemsDefaultLang($lang);

			if (!$result_items_default_lang)
			{
				echo "Cannot set default language or set default translation group<br>";
			}
		}
		else
		{
			$result_items_default_lang = true;
		}

		$query 	= "
			INSERT INTO `#__associations` (`id`, `context`, `key`)
				SELECT DISTINCT ie.item_id, 'com_content.item', ie.lang_parent_id
				FROM `#__flexicontent_items_ext` AS ie
				JOIN `#__flexicontent_items_ext` AS j ON ie.lang_parent_id = j.lang_parent_id AND ie.item_id<>j.item_id
				WHERE ie.lang_parent_id <> 0
			ON DUPLICATE KEY UPDATE id=id";
		$db->setQuery($query);

		try
		{
			$convert_assocs = $db->execute();
			$query 	= "UPDATE `#__flexicontent_items_ext` SET lang_parent_id = 0";
			$db->setQuery($query);
			$clear_assocs = $db->execute();
		}
		catch (Exception $e)
		{
			echo "Cannot convert FLEXIcontent associations to Joomla associations<br>";
			JFactory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
			$convert_assocs = $clear_assocs = false;
		}

		if (!$result_lang_col
			|| !$result_tgrp_col
			|| !$result_items_default_lang
			|| !$convert_assocs
			|| !$clear_assocs
		)
		{
			echo '<span class="install-notok"></span>';
			jexit();
		}
		else
		{
			echo '<span class="install-ok"></span>';
		}
	}


	/**
	 * Method to create missing DB indexes
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createDBindexes()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$db = JFactory::getDbo();
		$nullDate	= $db->getNullDate();

		$model  = $this->getModel('flexicontent');

		$update_queries = array();
		$missing_indexes = $model->getExistDBindexes($check_only = false, $update_queries);

		if (!empty($missing_indexes))
		{
			$app = JFactory::getApplication();

			foreach ($missing_indexes as $tblname => $indexnames)
			{
				$index_cmds = array();
				$index_cmds['indexdrop'] = array();   // !! important add 'indexdrop' to 'index_cmds', before 'indexadd' !!
				$index_cmds['indexadd'] = array();

				if (isset($indexnames['__indexing_started__']))
				{
					continue;
				}

				foreach ($indexnames as $indexname => $iconf)
				{
					if (!is_array($iconf))
					{
						$indexlen = $iconf ? "(" . $iconf . ")" : "";
						$index_cmds['indexadd'][] = " ADD INDEX " . $indexname . "(`" . $indexname . "`" . $indexlen . ")";
					}
					else
					{
						$indexdrop = !empty($iconf['custom_drop'])
							? $iconf['custom_drop']
							: '';

						if ($indexdrop)
						{
							$index_cmds['indexdrop'][] = $indexdrop;
						}

						$indexadd  = !empty($iconf['custom_add'])
							? $iconf['custom_add']
							: ' ADD INDEX ' . $indexname;

						if ($indexadd && !empty($iconf['cols']))
						{
							$_col_list = array();

							foreach ($iconf['cols'] as $indexcol => $len)
							{
								$indexlen  = $len ? "(" . $len . ")" : "";
								$_col_list[] = "`" . $indexcol . "`" . $indexlen;
							}

							$index_cmds['indexadd'][]   = $indexadd . "(" . implode(", ", $_col_list) . ")";
						}
					}
				}

				// For MyISAM the table is copied for the purpose of adding indexes and then old table is dropped
				// so it is better to add ALL table indexes via single command ?
				// For InnoDB in MySQL 5.1+, table is not copied so these when adding indexes it is better to have InnoDB tables
				if (!empty($index_cmds))
				{
					$file = JPATH_SITE . DS . 'tmp' . DS . 'tbl_indexes_' . $tblname;
					$file_contents = "" . time();
					JFile::write($file, $file_contents);

					if (isset($update_queries[$tblname]))
					{
						try
						{
							$db->setQuery($update_queries[$tblname])->execute();
						}
						catch (Exception $e)
						{
							jexit('<span class="install-notok"></span>' . $e->getMessage());
						}
					}

					// Allow dropping of duplicate rows using ALTER IGNORE TABLE
					// $db->setQuery('SET session old_alter_table=1');
					// $db->execute();

					foreach ($index_cmds as $index_type => $index_clause)
					{
						$query  = 'ALTER TABLE #__' . $tblname . ' ';  // 'ALTER IGNORE TABLE #__' . $tblname . ' ';
						$query .= implode(', ', $index_clause);

						try
						{
							$db->setQuery($query)->execute();
						}
						catch (Exception $e)
						{
							if ($index_type != 'indexdrop')
							{
								jexit('<span class="install-notok"></span>' . $e->getMessage());
							}
						}
					}

					JFile::delete($file);
				}
			}
		}

		echo '<span class="install-ok"></span>';
	}


	/**
	 * Method to create the versions table
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createVersionsTable()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$db = JFactory::getDbo();
		$nullDate	= $db->getNullDate();

		$query 	= " CREATE TABLE IF NOT EXISTS #__flexicontent_versions (
	  				`id` int(11) unsigned NOT NULL auto_increment,
					`item_id` int(11) unsigned NOT NULL default '0',
					`version_id` int(11) unsigned NOT NULL default '0',
					`comment` mediumtext NOT NULL,
					`created` datetime NOT NULL default '0000-00-00 00:00:00',
					`created_by` int(11) unsigned NOT NULL default '0',
					`state` int(3) NOT NULL default '0',
					PRIMARY KEY  (`id`),
					KEY `version2item` (`item_id`,`version_id`)
					) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`";

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			jexit('<span class="install-notok"></span>' . $e->getMessage());
		}

		echo '<span class="install-ok"></span>';
	}


	/**
	 * Method to handle the versions data and to populate the new beta4 versions table
	 * From #__flexicontent_items_versions to #__flexicontent_versions
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function populateVersionsTable()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$db = JFactory::getDbo();
		$nullDate	= $db->getNullDate();

		$query 	= 'SELECT item_id, version FROM #__flexicontent_items_versions'
				. ' WHERE field_id = 2'
				. ' ORDER BY item_id, version';
		$db->setQuery($query);
		$cvs = $db->loadObjectList();

		$query 	= 'SELECT * FROM #__flexicontent_items_versions'
				. ' WHERE field_id IN ( 2,3,4,5 )'
				. ' ORDER BY item_id, version, field_id';
		$db->setQuery($query);
		$fvs = $db->loadObjectList();

		$_fvs = array();

		foreach ($fvs as $fv)
		{
			$_fvs[$fv->item_id][$fv->version][$fv->field_id] = $fv;
		}

		$fvs = $_fvs;

		$versioned_fids = array(2 => 'created', 3 => 'created_by', 4 => 'modified', 5 => 'modified_by');

		for ($i = 0; $i < count($cvs); $i++)
		{
			foreach ($versioned_fids as $fid => $fname)
			{
				if (isset($fvs[$cvs[$i]->item_id][$cvs[$i]->version][$fid]))
				{
					$cvs[$i]->$fname = $fvs[$cvs[$i]->item_id][$cvs[$i]->version][$fid]->value;
				}
				else
				{
					$cvs[$i]->$fname = 0;
				}
			}
		}

		$versions = new stdClass;
		$n = 0;

		foreach ($cvs as $cv)
		{
			$versions->$n = new stdClass;
			$versions->$n->item_id    = $cv->item_id;
			$versions->$n->version_id = $cv->version;
			$versions->$n->comment    = '';
			$versions->$n->created    = (isset($cv->modified) && ($cv->modified != $nullDate)) ? $cv->modified : $cv->created;
			$versions->$n->created_by = (isset($cv->modified_by) && $cv->modified_by) ? $cv->modified_by : $cv->created_by;
			$versions->$n->state      = 1;
			$db->insertObject('#__flexicontent_versions', $versions->$n);
			$n++;
		}

		echo '<span class="install-ok"></span>';
	}


	/**
	 * Method to create the authors table
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createAuthorsTable()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$db = JFactory::getDbo();
		$nullDate	= $db->getNullDate();

		$query 	= " CREATE TABLE IF NOT EXISTS #__flexicontent_authors_ext (
  				`user_id` int(11) unsigned NOT NULL,
  				`author_basicparams` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  				`author_catparams` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
					PRIMARY KEY  (`user_id`)
					) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`";
		$db->setQuery($query);

		try
		{
			$result = $db->execute();
		}
		catch (Exception $e)
		{
			$result = false;
		}

		if (!$result)
		{
			echo '<span class="install-notok"></span>';
		}
		else
		{
			echo '<span class="install-ok"></span>';
		}
	}


	/**
	 * Set phpThumb cache permissions
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function setCacheThumbPerms()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$format = strtolower($this->input->get('format', 'html', 'CMD'));

		// PhpThumb cache directory
		$phpthumbcache 	= JPath::clean(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'librairies' . DS . 'phpthumb' . DS . 'cache');
		$success = JPath::setPermissions($phpthumbcache, '0600', '0700');

		if (!$success)
		{
			if ($format == 'raw')
			{
				echo '<span class="install-notok"></span>';
				jexit();
			}
			else
			{
				JFactory::getApplication()->enqueueMessage(JText::_('FLEXI_COULD_NOT_SET_PHPTHUMB_PERMS'), 'notice');

				return false;
			}
		}

		if ($format == 'raw')
		{
			echo '<span class="install-ok"></span>';
		}
		else
		{
			return true;
		}
	}


	/**
	 * Method to update table for item counting (temporary) data
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function updateItemCountingData()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$db = JFactory::getDbo();
		$cache_tbl = "#__flexicontent_items_tmp";

		// Truncate the table, this will handle redudant columns too
		$db->setQuery('TRUNCATE TABLE ' . $cache_tbl);
		$db->execute();
		$model = $this->getModel('items');
		$result = $model->updateItemCountingData($rows = false);

		if (!$result)
		{
			echo '<span class="install-notok"></span>';
			jexit();
		}
		else
		{
			echo '<span class="install-ok"></span>';
		}
	}


	/**
	 * Method to check if the deprecated files still exist and delete them
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function deleteDeprecatedFiles()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		// Get deprecated files and folders that still exist
		$model = $this->getModel('flexicontent');
		$deprecated = null;
		$model->getDeprecatedFiles($deprecated);

		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		// Delete deprecated files that were found
		foreach ($deprecated['files'] as $file)
		{
			if (!JFile::delete($file))
			{
				echo 'Cannot delete legacy file: ' . $file . '<br />';
			}
		}

		// Delete deprecated folders that were found
		foreach ($deprecated['folders'] as $folder)
		{
			if (!JFolder::delete($folder))
			{
				echo 'Cannot delete legacy folder: ' . $folder . '<br />';
			}
		}

		// Always set this to prevent task from blocking UI
		$model->getDeprecatedFiles($deprecated, $_done = true);

		if ($model->getDeprecatedFiles())
		{
			echo '<span class="install-ok"></span>';
		}
		else
		{
			echo '<span class="install-notok"></span>';
		}
	}


	/**
	 * Method to delete old core fields data in the fields_items_relations table
	 * Delete also old versions fields data
	 * Alter value fields to mediumtext in order to store large items
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function cleanupOldTables()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$db  = JFactory::getDbo();
		$app = JFactory::getApplication();

		$queries 	= array();

		// Alter some table field types
		$queries[] 	= "ALTER TABLE #__flexicontent_fields_item_relations CHANGE `value` `value` MEDIUMTEXT";
		$queries[] 	= "ALTER TABLE #__flexicontent_items_versions CHANGE `value` `value` MEDIUMTEXT";
		$queries[] 	= "ALTER TABLE #__flexicontent_items_ext"
			. "  CHANGE `search_index` `search_index` MEDIUMTEXT"
			. ", CHANGE `sub_items` `sub_items` TEXT"
			. ", CHANGE `sub_categories` `sub_categories` TEXT"
			. ", CHANGE `related_items` `related_items` TEXT";

		foreach ($queries as $query)
		{
			$db->setQuery($query);
			$db->execute();
		}

		$query = "SELECT id,version,created,created_by FROM #__content " . (!FLEXI_J16GE ? "WHERE sectionid='" . FLEXI_SECTION . "'" : "");
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		// $jcorefields = flexicontent_html::getJCoreFields();
		$add_cats = true;
		$add_tags = true;
		$clean_database = true;

		// For all items not having the current version, add it
		$last_versions = FLEXIUtilities::getLastVersions();

		foreach ($rows as $row)
		{
			$lastversion = @$last_versions[$row->id]['version'];

			if ($row->version > $lastversion)
			{
				// Get field values of the current item version
				$query = "SELECT f.id,fir.value,f.field_type,f.name,fir.valueorder,fir.suborder,f.iscore "
						. " FROM #__flexicontent_fields_item_relations as fir"
						. " JOIN #__flexicontent_fields as f on f.id=fir.field_id "
						. " WHERE fir.item_id=" . $row->id . " AND f.iscore=0";  // Old versions stored categories & tags into __flexicontent_fields_item_relations
				$db->setQuery($query);
				$fields = $db->loadObjectList();

				// Delete old data
				if ($clean_database && $fields)
				{
					$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = ' . $row->id;
					$db->setQuery($query);
					$db->execute();
				}

				// Add the 'maintext' field to the fields array for adding to versioning table
				$f = new stdClass;
				$f->id					= 1;
				$f->iscore			= 1;
				$f->valueorder	= 1;
				$f->suborder    = 1;
				$f->field_type	= "maintext";
				$f->name				= "text";
				$f->value				= $row->introtext;

				if (StringHelper::strlen($row->fulltext) > 1)
				{
					$f->value .= '<hr id="system-readmore" />' . $row->fulltext;
				}

				if (substr($f->value, 0, 3) != "<p>")
				{
					$f->value = "<p>" . $f->value . "</p>";
				}

				$fields[] = $f;

				// Add the 'categories' field to the fields array for adding to versioning table
				$query = "SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid='" . $row->id . "';";
				$db->setQuery($query);
				$categories = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();

				if (!$categories || !count($categories))
				{
					$categories = array($catid = $row->catid);
					$query = "INSERT INTO #__flexicontent_cats_item_relations VALUES('$catid','" . $row->id . "', '0');";
					$db->setQuery($query);
					$db->execute();
				}

				$f = new stdClass;
				$f->id					= 13;
				$f->iscore			= 1;
				$f->valueorder	= 1;
				$f->suborder    = 1;
				$f->version		= (int) $row->version;
				$f->value		= serialize($categories);

				if ($add_cats)
				{
					$fields[] = $f;
				}

				// Add the 'tags' field to the fields array for adding to versioning table
				$query = "SELECT tid FROM #__flexicontent_tags_item_relations WHERE itemid='" . $row->id . "';";
				$db->setQuery($query);
				$tags = $db->loadColumn();
				$f = new stdClass;
				$f->id					= 14;
				$f->iscore			= 1;
				$f->valueorder	= 1;
				$f->suborder    = 1;
				$f->version		= (int) $row->version;
				$f->value		= serialize($tags);

				if ($add_tags)
				{
					$fields[] = $f;
				}

				// Add field values to field value versioning table
				foreach ($fields as $field)
				{
					// Add the new values to the database
					$obj = new stdClass;
					$obj->field_id   = $field->id;
					$obj->item_id    = $row->id;
					$obj->valueorder = $field->valueorder;
					$obj->suborder   = $field->suborder;
					$obj->version    = (int) $row->version;
					$obj->value      = $field->value;

					// echo "version: ".$obj->version.",fieldid : ".$obj->field_id.",value : ".$obj->value.",valueorder : ".$obj->valueorder.",suborder : ".$obj->suborder."<br />";
					// echo "inserting into __flexicontent_items_versions<br />";
					$db->insertObject('#__flexicontent_items_versions', $obj);

					if (!$field->iscore)
					{
						unset($obj->version);

						// echo "inserting into __flexicontent_fields_item_relations<br />";
						$db->insertObject('#__flexicontent_fields_item_relations', $obj);
						flexicontent_db::setValues_commonDataTypes($obj);
					}

					// $searchindex  .= @$field->search;
				}

				// **********************************************************************************
				// Add basic METADATA of current item version (kept in table #__flexicontent_versions)
				// **********************************************************************************
				$v = new stdClass;
				$v->item_id    = (int) $row->id;
				$v->version_id = (int) $row->version;
				$v->created    = ($row->modified && ($row->modified != $nullDate)) ? $row->modified : $row->created;
				$v->created_by = $row->created_by;
				$v->comment    = '';

				// echo "inserting into __flexicontent_versions<br />";
				$db->insertObject('#__flexicontent_versions', $v);
			}
		}

		$queries 	= array();

		// Delete unused records
		// 1,'maintext',  2,'created',  3,'createdby',  4,'modified',  5,'modifiedby',  6,'title',  7,'hits'
		// 8,'type',  9,'version',  10,'state',   11,'voting',  12,'favourites',  13,'categories',  14,'tags'
		$queries[] 	= "DELETE FROM #__flexicontent_fields_item_relations WHERE field_id < 15";
		$queries[] 	= "DELETE FROM #__flexicontent_items_versions WHERE field_id IN ( 7, 9, 11, 12 )";

		foreach ($queries as $query)
		{
			$db->setQuery($query);
			$db->execute();
		}

		$catscache = JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();

		$model = $this->getModel('flexicontent');

		if ($model->getNoOldFieldsData())
		{
			echo '<span class="install-ok"></span>';
		}
		else
		{
			echo '<span class="install-notok"></span>';
			jexit();
		}
	}


	/**
	 * (UNNEEDED ??) Method to add missing current version data in the versions TABLE
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function addCurrentVersionData()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$model = $this->getModel('flexicontent');

		if ($model->addCurrentVersionData())
		{
			echo '<span class="install-ok"></span>';
		}
		else
		{
			echo '<span class="install-notok"></span>';
			jexit();
		}
	}


	/**
	 * Method to update initial permissions for component asset and other important assets
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function updateInitialPermission()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$model = $this->getModel('flexicontent');

		if ($model->updateInitialPermission())
		{
			echo '<span class="install-ok"></span>';
		}
		else
		{
			echo '<span class="install-notok"></span>';
			jexit();
		}
	}


	/**
	 * Method to pack language files and download them
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createLanguagePack()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$code    = $this->input->get('code', 'en-GB', 'STRING');
		$method  = $this->input->get('method', '', 'STRING');
		$name    = $this->input->get('name', '', 'STRING');
		$email   = $this->input->get('email', '', 'STRING');
		$web     = $this->input->get('web', '', 'STRING');
		$message = $this->input->get('message', '', 'STRING');

		$formparams = array();
		$formparams['name'] 	= $name;
		$formparams['email'] 	= $email;
		$formparams['web'] 		= $web;
		$formparams['message'] 	= $message;

		$model 	= $this->getModel('flexicontent');

		$missing = $model->createLanguagePack($code, $method, $formparams);

		if (is_array($missing) && $method != 'zip')
		{
			if (@$missing['admin'])
			{
				echo '<h3>' . JText::_('Folder: administrator/languages/') . $code . '/</h3>';

				foreach ($missing['admin'] as $a)
				{
					echo '<p>' . $code . '.' . $a . '.ini' . (($method == 'create') ? ' <span class="lang-success">created</span>' : ' <span class="lang-fail">is missing</span>') . '</p>';
				}
			}

			if (@$missing['site'])
			{
				echo '<h3>' . JText::_('Folder: languages/') . $code . '/</h3>';

				foreach ($missing['site'] as $s)
				{
					echo '<p>' . $code . '.' . $s . '.ini' . (($method == 'create') ? ' <span class="lang-success">created</span>' : ' <span class="lang-fail">is missing</span>') . '</p>';
				}
			}

			if ($method != 'create')
			{
				echo '<style>#missing {display:block;}</style>';
			}
		}
		else
		{
			echo $missing;
			echo '<style>#missing {display:none;}</style>';
		}
	}


	function fcVersionCompare()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		@ob_end_clean();
		$this->input->set('layout', 'fversion');
		parent::display();
		exit;
	}
}
