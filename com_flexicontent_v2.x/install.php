<?php
/**
 * @version 1.5 stable $Id: install.php 1579 2012-12-03 03:37:21Z ggppdk $
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

defined( '_JEXEC' ) or die( 'Restricted access' );

?>
<style type="text/css">
table.adminlist tbody tr td {
	height: auto!important;
}
</style>
<?php

// Joomla version variables
if (!defined('FLEXI_J16GE') || !defined('FLEXI_J30GE')) {
	jimport( 'joomla.version' );  $jversion = new JVersion;
}
if (!defined('FLEXI_J16GE'))   define('FLEXI_J16GE', version_compare( $jversion->getShortVersion(), '1.6.0', 'ge' ) );
if (!defined('FLEXI_J30GE'))   define('FLEXI_J30GE', version_compare( $jversion->getShortVersion(), '3.0.0', 'ge' ) );

		
		// Try to increment some limits
		
		@set_time_limit( 240 );    // execution time 5 minutes
		ignore_user_abort( true ); // continue execution if client disconnects
		
		// Try to increment memory limits
		$memory_limit	= trim( @ini_get( 'memory_limit' ) );
		if ( $memory_limit ) {
			switch (strtolower(substr($memory_limit, -1)))
			{
				case 'm': $memory_limit = (int)substr($memory_limit, 0, -1) * 1048576; break;
				case 'k': $memory_limit = (int)substr($memory_limit, 0, -1) * 1024; break;
				case 'g': $memory_limit = (int)substr($memory_limit, 0, -1) * 1073741824; break;
				case 'b':
				switch (strtolower(substr($memory_limit, -2, 1)))
				{
					case 'm': $memory_limit = (int)substr($memory_limit, 0, -2) * 1048576; break;
					case 'k': $memory_limit = (int)substr($memory_limit, 0, -2) * 1024; break;
					case 'g': $memory_limit = (int)substr($memory_limit, 0, -2) * 1073741824; break;
					default : break;
				} break;
				default: break;
			}
			if ( $memory_limit < 16000000 ) @ini_set( 'memory_limit', '16M' );
			if ( $memory_limit < 32000000 ) @ini_set( 'memory_limit', '32M' );
			if ( $memory_limit < 64000000 ) @ini_set( 'memory_limit', '64M' );
		}
		
		
		// first check if PHP5 is running
		if (version_compare(PHP_VERSION, '5.0.0', '<')) {
			// we add the component stylesheet to the installer
			$css = JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css'; 
			$document = JFactory::getDocument(); 
			$document->addStyleSheet($css);	
			
			// load english language file for 'com_flexicontent' component then override with current language file
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		
			Jerror::raiseWarning(null, JText::_( 'FLEXI_UPGRADE_PHP' ));
			return false;
		}
		
		// init vars
		$error = false;
		$extensions = array();
		
		// clear a cache
		$cache = JFactory::getCache();
		$cache->clean( 'com_flexicontent' );
		$cache->clean( 'com_flexicontent_tmpl' );
		$cache->clean( 'com_flexicontent_cats' );
		$cache->clean( 'com_flexicontent_items' );
		
		// reseting post installation session variables
		$session  = JFactory::getSession();
		$session->set('flexicontent.postinstall', false);
		$session->set('flexicontent.allplgpublish', false);
		
		// fix joomla 1.5 bug
		if ( !FLEXI_J16GE ) {
			$this->parent->getDBO = $this->parent->getDBO();
		}
		
		// Parse XML file to identify additional extensions,
		// This code part (for installing additional extensions) originates from Zoo Component:
		// Original install.php file
		// @package   Zoo Component
		// @author    YOOtheme http://www.yootheme.com
		// @copyright Copyright (C) 2007 - 2009 YOOtheme GmbH
		// @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
		if (FLEXI_J16GE) {
			$manifest = isset($parent) ? $parent->getParent()->manifest : $this->manifest;
			$source = isset($parent) ? $parent->getParent()->getPath('source') : $this->parent->getPath('source');
			$add_array =& $manifest->xpath('additional');
			$add = NULL;
			if(count($add_array)) $add = $add_array[0];
		} else {
			$source = $this->parent->getPath('source');
			$add =& $this->manifest->getElementByPath('additional');
		}
		
		if ( is_object($add) && count( $add->children() ) ) {
		    $exts =& $add->children();
		    foreach ($exts as $ext) {
					$extensions[] = array(
						'name' => (FLEXI_J16GE ? $ext->asXml() : $ext->data()),
						'type' => (FLEXI_J16GE ? $ext->getName() : $ext->name()),
						'folder' => $source.'/'.(FLEXI_J16GE ? $ext->attributes()->folder : $ext->attributes('folder')),
						'installer' => new JInstaller(),
						'status' => false);
		    }
				//echo "<pre>"; print_r($extensions); echo "</pre>"; exit;
		}
		
		// Install discovered extensions
		foreach ($extensions as $i => $extension) {
			//$jinstaller = new JInstaller();
			$jinstaller = & $extensions[$i]['installer'];
			if (FLEXI_J16GE) {  // J1.6+ installer requires that we explicit set override/upgrade options
				$jinstaller->setOverwrite(true);
				$jinstaller->setUpgrade(true);
			}
			if ($jinstaller->install($extensions[$i]['folder'])) {
				$extensions[$i]['status'] = true;
			} else {
				$error = true;
				break;
			}
		}
		
		?>
		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="adminlist">
			<tr>
				<td valign="top">
		    		<img src="<?php echo 'components/com_flexicontent/assets/images/logo.png'; ?>" height="96" width="300" alt="FLEXIcontent Logo" align="left" />
				</td>
				<td valign="top" width="100%">
		       	 	<strong>FLEXIcontent</strong><br/>
		       	 	<span>Flexible content management system for Joomla! J1.5/J2.5</span><br />
		        	<font class="small">by <a href="http://www.vistamedia.fr" target="_blank">Emmanuel Danan</a>,
							Georgios Papadakis<br/>
		        	<font class="small">and <a href="http://www.marvelic.co.th" target="_blank">Marvelic Engine Co.,Ltd.</a><br/>
		       	 	<span>Logo and icons</span><br />
		        	<font class="small">by <a href="http://www.artefact-design.com" target="_blank">Greg Berthelot</a><br/>
				</td>
			</tr>
		<!--
			<tr>
				<td valign="top" style="font-weight: bold;">
		    		<?php // echo JText::_('Choose an option to finish the install :'); ?>
				</td>
				<td valign="top" width="100%" style="font-weight: bold; color: red; font-size: 14px;">
					<a href="index.php?option=com_flexicontent&task=finishinstall&action=newinstall" style="font-weight: bold; color: red; font-size: 14px;">
		    		<?php // echo JText::_('New install'); ?>
		    		</a>&nbsp;&nbsp;|&nbsp;&nbsp; 
					<a href="index.php?option=com_flexicontent&task=finishinstall&action=update" style="font-weight: bold; color: red; font-size: 14px;">
		    		<?php // echo JText::_('Update an existing install'); ?>
		    		</a>
				</td>
			</tr>
		-->
		</table>
		<h3><?php echo JText::_('Additional Extensions'); ?></h3>
		<table class="adminlist">
			<thead>
				<tr>
					<th class="title"><?php echo JText::_('Extension'); ?></th>
					<th width="60%"><?php echo JText::_('Status'); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="2">&nbsp;</td>
				</tr>
			</tfoot>
			<tbody>
				<?php foreach ($extensions as $i => $ext) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="key"><?php echo $ext['name']; ?> (<?php echo JText::_($ext['type']); ?>)</td>
						<td>
							<?php $style = $ext['status'] ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;'; ?>
							<span style="<?php echo $style; ?>"><?php echo $ext['status'] ? JText::_('Installed successfully') : JText::_('NOT Installed'); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		// Rollback on installation errors, abort() will be called on every additional extension installed above
		if ($error) {
			for ($i = 0; $i < count($extensions); $i++) { 
				if ( $extensions[$i]['status'] ) {
					$extensions[$i]['installer']->abort(
						$extensions[$i]['name'] .' '. JText::_($extensions[$i]['type']) .' '. JText::_('Install') .': <span style="color:green">'. JText::_('rolling back').'</span>',
						$extensions[$i]['type']
					);
					//$extensions[$i]['status'] = false;
				} else {
					Jerror::raiseWarning(null, $extensions[$i]['name'] .' '. JText::_($extensions[$i]['type']) .' '. JText::_('Install') .': <span style="color:red">'. JText::_('Failed').'</span>');
				}
			}
			if (!FLEXI_J16GE) {
				$this->parent->abort(JText::_('Component').' '.JText::_('Install').': '.JText::_('Error'), 'component');
			} else {
				return false;  // In J1.6+ , returning false here will cancel (abort) component installation and rollback changes
			}
		}
		
		$db = &JFactory::getDBO();
		
		// Delete orphan entries ?
		$query="DELETE FROM `#__extensions` WHERE folder='flexicontent_fields' AND element IN ('flexisystem', 'flexiadvroute', 'flexisearch', 'flexiadvsearch', 'flexinotify')";
		$db->setQuery($query);
		$result = $db->query();
		
		if (FLEXI_J30GE) {
			// System plugins must be enabled
			$query = "UPDATE #__extensions SET enabled=1 WHERE type='plugin' AND element=".$db->Quote('flexisystem')." AND folder=".$db->Quote('system');
			$db->setQuery($query);
			$db->query();
			$query = "UPDATE #__extensions SET enabled=1 WHERE type='plugin' AND element=".$db->Quote('flexiadvroute')." AND folder=".$db->Quote('system');
			$db->setQuery($query);
			$db->query();
		}
		?>
		
		<h3><?php echo JText::_('Actions'); ?></h3>
		<table class="adminlist">
			<thead>
				<tr>
					<th class="title"><?php echo JText::_('Actions'); ?></th>
					<th width="60%"><?php echo JText::_('Status'); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="2">&nbsp;</td>
				</tr>
			</tfoot>
			<tbody>
				
		<?php
		// Set phpThumb Cache folder permissions
		?>
				<tr class="row1">
					<td class="key">Setting phpThumb Cache folder permissions</td>
					<td>
						<?php
						if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
						$phpthumbcache 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'cache');
						$success = JPath::setPermissions($phpthumbcache, '0644', '0755');
						$style = $success ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;';
						?>
						<span style="<?php echo $style; ?>"><?php
						if($success) {
							echo JText::_("Task Successful");
						} else {
							echo JText::_("Setting phpThumb Cache folder permissions UNSUCCESSFUL.");
						}
						?></span>
					</td>
				</tr>

		<?php
		$deprecated_fields_arr = array('hidden');
		$deprecated_fields_list = "'". implode("','", $deprecated_fields_arr) ."'";
		
		// Get DB table information
		
		$query = 'SHOW TABLES LIKE "' . JFactory::getApplication()->getCfg('dbprefix') . 'flexicontent_files"';
		$db->setQuery($query);
		$files_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . JFactory::getApplication()->getCfg('dbprefix') . 'flexicontent_fields"';
		$db->setQuery($query);
		$fields_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . JFactory::getApplication()->getCfg('dbprefix') . 'flexicontent_advsearch_index"';
		$db->setQuery($query);
		$advsearch_index_tbl_exists = (boolean) count($db->loadObjectList());
		
		if ($files_tbl_exists) {
			$query = "SHOW COLUMNS FROM #__flexicontent_files";
			$db->setQuery($query);
			$tbl_cols = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
			if ($tbl_cols) {
				$files_tbl_cols = array();
				foreach ($tbl_cols as $tbl_col) $files_tbl_cols[$tbl_col] = 1;
			}
		}
		
		if ($fields_tbl_exists) {
			$query = "SHOW COLUMNS FROM #__flexicontent_fields";
			$db->setQuery($query);
			$tbl_cols = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
			if ($tbl_cols) {
				$fields_tbl_cols = array();
				foreach ($tbl_cols as $tbl_col) $fields_tbl_cols[$tbl_col] = 1;
			}

			$query = "SELECT COUNT(*) FROM `#__flexicontent_fields` WHERE field_type IN (".$deprecated_fields_list.")";
			$db->setQuery($query);
			$deprecated_fields_count = $db->loadResult();
		}
		
		if ($advsearch_index_tbl_exists) {
			$query = "SHOW COLUMNS FROM #__flexicontent_advsearch_index";
			$db->setQuery($query);
			$tbl_cols = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
			if ($tbl_cols) {
				$advsearch_index_tbl_cols = array();
				foreach ($tbl_cols as $tbl_col) $advsearch_index_tbl_cols[$tbl_col] = 1;
			}
		}
		?>
		
		<?php
		// Update DB table flexicontent_fields: Convert deprecated fields types to 'text' field type
		?>
				<tr class="row0">
					<td class="key">Converting deprecated fields"
					<?php
					$already = true;
					$result = false;
					if( !empty($deprecated_fields_count) ) {
						$already = false;
						$query = "UPDATE `#__flexicontent_fields` SET field_type=`text` WHERE field_type IN (".$deprecated_fields_list.")";
						$db->setQuery($query);
						$result = $db->query();
					}
					?>
					</td>
					<td>
						<?php $style = ($already||$result) ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;'; ?>
						<span style="<?php echo $style; ?>"><?php
						if($already) {
							echo JText::_("Task Successful: No deprecated fields found.");
						} elseif($result) {
							echo JText::_("Task Successful: Deprecated Fields converted to 'text' field type.");
						} else {
							echo JText::_("UPDATE TABLE command UNSUCCESSFUL.");
						}
						?></span>
					</td>
				</tr>
				
		<?php
		// Alter DB table flexicontent_advsearch_index: Add value_id column
		?>
				<tr class="row1">
					<td class="key">Adding column `value_id` to TABLE #__flexicontent_advsearch_index"
					<?php
					$already = true;
					$result = false;
					if ( $advsearch_index_tbl_exists && !array_key_exists('value_id', $advsearch_index_tbl_cols)) {
						$already = false;
						$query = "ALTER TABLE `#__flexicontent_advsearch_index` ADD `value_id` TEXT NULL AFTER `search_index`";
						$db->setQuery($query);
						$result = $db->query();
					}
					?>
					</td>
					<td>
						<?php $style = ($already||$result) ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;'; ?>
						<span style="<?php echo $style; ?>"><?php
						if($already) {
							echo JText::_("Task Successful: Column 'value_id' already exists.");
						} elseif($result) {
							echo JText::_("Task Successful: Column 'value_id' added.");
						} else {
							echo JText::_("ALTER TABLE command UNSUCCESSFUL.");
						}
						?></span>
					</td>
				</tr>
				
		<?php
		// Alter DB table flexicontent_files: Add description column
		?>
				<tr class="row0">
					<td class="key">Adding column `description` to TABLE #__flexicontent_files"
					<?php
					$already = true;
					$result = false;
					if ( $files_tbl_exists && !array_key_exists('description', $files_tbl_cols)) {
						$already = false;
						$query = "ALTER TABLE `#__flexicontent_files` ADD `description` TEXT NOT NULL AFTER `altname`";
						$db->setQuery($query);
						$result = $db->query();
					}
					?>
					</td>
					<td>
						<?php $style = ($already||$result) ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;'; ?>
						<span style="<?php echo $style; ?>"><?php
						if($already) {
							echo JText::_("Task Successful: Column 'description' already exists.");
						} elseif($result) {
							echo JText::_("Task Successful: Column 'description' added.");
						} else {
							echo JText::_("ALTER TABLE command UNSUCCESSFUL.");
						}
						?></span>
					</td>
				</tr>
		
		<?php
		// Alter DB table flexicontent_fields: Add untranslatable column
		?>
				<tr class="row1">
					<td class="key">Adding column `untranslatable` to TABLE #__flexicontent_fields"
					<?php
					$already = true;
					$result = false;
					if ( $fields_tbl_exists && !array_key_exists('untranslatable', $fields_tbl_cols)) {
						$already = false;
						$query = "ALTER TABLE `#__flexicontent_fields` ADD `untranslatable` TINYINT(1) NOT NULL DEFAULT '0' AFTER `isadvsearch`";
						$db->setQuery($query);
						$result = $db->query();
					}
					?>
					</td>
					<td>
						<?php $style = ($already||$result) ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;'; ?>
						<span style="<?php echo $style; ?>"><?php
						if($already) {
							echo JText::_("Task Successful: Column 'untranslatable' already exists.");
						} elseif($result) {
							echo JText::_("Task Successful: Column 'untranslatable' added.");
						} else {
							echo JText::_("ALTER TABLE command UNSUCCESSFUL.");
						}
						?></span>
					</td>
				</tr>
		
		<?php
		// Alter DB table flexicontent_fields: Add formhidden column
		?>
				<tr class="row0">
					<td class="key">Adding column `formhidden` to TABLE #__flexicontent_fields"
					<?php
					$already = true;
					$result = false;
					if ( $fields_tbl_exists && !array_key_exists('formhidden', $fields_tbl_cols)) {
						$already = false;
						$query = "ALTER TABLE `#__flexicontent_fields` ADD `formhidden` SMALLINT(8) NOT NULL DEFAULT '0' AFTER `untranslatable`";
						$db->setQuery($query);
						$result = $db->query();
					}
					?>
					</td>
					<td>
						<?php $style = ($already||$result) ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;'; ?>
						<span style="<?php echo $style; ?>"><?php
						if($already) {
							echo JText::_("Task Successful: Column 'formhidden' already exists.");
						} elseif($result) {
							echo JText::_("Task Successful: Column 'formhidden' added.");
						} else {
							echo JText::_("ALTER TABLE command UNSUCCESSFUL.");
						}
						?></span>
					</td>
				</tr>
		
		<?php
		// Alter DB table flexicontent_fields: Add valueseditable column
		?>
				<tr class="row1">
					<td class="key">Adding column `valueseditable` to TABLE #__flexicontent_fields"
					<?php
					$already = true;
					$result = false;
					if ( $fields_tbl_exists && !array_key_exists('valueseditable', $fields_tbl_cols)) {
						$already = false;
						$query = "ALTER TABLE `#__flexicontent_fields` ADD `valueseditable` SMALLINT(8) NOT NULL DEFAULT '0' AFTER `formhidden`";
						$db->setQuery($query);
						$result = $db->query();
					}
					?>
					</td>
					<td>
						<?php $style = ($already||$result) ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;'; ?>
						<span style="<?php echo $style; ?>"><?php
						if($already) {
							echo JText::_("Task Successful: Column 'valueseditable' already exists.");
						} elseif($result) {
							echo JText::_("Task Successful: Column 'valueseditable' added.");
						} else {
							echo JText::_("ALTER TABLE command UNSUCCESSFUL.");
						}
						?></span>
					</td>
				</tr>
		
		<?php
		// Alter DB table flexicontent_fields: Add edithelp column
		?>
				<tr class="row0">
					<td class="key">Adding column `edithelp` to TABLE #__flexicontent_fields"
					<?php
					$already = true;
					$result = false;
					if ( $fields_tbl_exists && !array_key_exists('edithelp', $fields_tbl_cols)) {
						$already = false;
						$query = "ALTER TABLE `#__flexicontent_fields` ADD `edithelp` SMALLINT(8) NOT NULL DEFAULT '2' AFTER `formhidden`";
						$db->setQuery($query);
						$result = $db->query();
					}
					?>
					</td>
					<td>
						<?php $style = ($already||$result) ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;'; ?>
						<span style="<?php echo $style; ?>"><?php
						if($already) {
							echo JText::_("Task Successful: Column 'edithelp' already exists.");
						} elseif($result) {
							echo JText::_("Task Successful: Column 'edithelp' added.");
						} else {
							echo JText::_("ALTER TABLE command UNSUCCESSFUL.");
						}
						?></span>
					</td>
				</tr>
		
		<?php
		if (FLEXI_J16GE) :
		// Alter table __flexicontent_fields: Add asset_id column
		?>
				<tr class="row0">
					<td class="key">Adding column `asset_id` to TABLE #__flexicontent_fields"
					<?php
					$already = true;
					$result = false;
					if ( $fields_tbl_exists && !array_key_exists('asset_id', $fields_tbl_cols)) {
						$already = false;
						$query = "ALTER TABLE `#__flexicontent_fields` ADD COLUMN `asset_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `id`";
						$db->setQuery($query);
						$result = $db->query();
					}
					?>
					</td>
					<td>
						<?php $style = ($already||$result) ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;'; ?>
						<span style="<?php echo $style; ?>"><?php
						if($already) {
							echo JText::_("Task Successful: Column 'asset_id' already exists.");
						} elseif($result) {
							echo JText::_("Task Successful: Column 'asset_id' added.");
						} else {
							echo JText::_("ALTER TABLE command UNSUCCESSFUL.");
						}
						?></span>
					</td>
				</tr>
				
		<?php endif; /* if FLEXI_J16GE */?>
			</tbody>
		</table>
