<?php
/**
 * @version 1.5 stable $Id: script.php 1579 2012-12-03 03:37:21Z ggppdk $
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

class com_flexicontentInstallerScript
{
	/*
	* $parent is the class calling this method.
	* $type is the type of change (install, update or discover_install, not uninstall).
	* preflight runs before anything else and while the extracted files are in the uploaded temp folder.
	* If preflight returns false, Joomla will abort the update and undo everything already done.
	*/
	function preflight( $type, $parent ) {
		
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
	
		$jversion = new JVersion();
		
		// File version of new manifest file
		$this->release = $parent->get( "manifest" )->version;
		
		// File version of existing manifest file
		$this->release_existing = $this->getParam('version');
		
		// Manifest file minimum Joomla version
		$this->minimum_joomla_release = $parent->get( "manifest" )->attributes()->version;
		
		// Show the essential information at the install/update back-end
		if ($this->release_existing) {
			echo '<br /> &nbsp; Updating FLEXIcontent from '.$this->release_existing.' to version ' . $this->release;
		} else {
			echo '<br /> &nbsp; Installing FLEXIcontent version '.$this->release;
		}
		echo '<br /> &nbsp; Minimum Joomla version = ' . $this->minimum_joomla_release .' &nbsp Current is ' . $jversion->getShortVersion();
		echo '<p> -- ' . JText::_('Performing PRE-installation Tasks/Checks') .'</p>';
		
		// Abort if the current Joomla release is older
		if( version_compare( $jversion->getShortVersion(), $this->minimum_joomla_release, 'lt' ) ) {
			Jerror::raiseWarning(null, 'Cannot install com_flexicontent in a Joomla release prior to '.$this->minimum_joomla_release);
			return false;
		}

		// Abort if the component being installed is not newer than the currently installed version
		if ( $type == 'update' ) {
			$oldRelease = $this->getParam('version');
			$rel = $oldRelease . ' to ' . $this->release;
			if ( version_compare( $this->release, $oldRelease, 'l' ) ) {
				Jerror::raiseNotice(null, 'Downgrading component from ' . $rel);
				//return false;  // Returning false here would abort
			}
		}
		
		// first check if PHP5 is running
		if (version_compare(PHP_VERSION, '5.0.0', '<')) {
			// we add the component stylesheet to the installer
			$document = JFactory::getDocument(); 
			$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
			if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
			else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
			else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');
			
			// load english language file for 'com_flexicontent' component then override with current language file
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		
			Jerror::raiseWarning(null, JText::_( 'FLEXI_UPGRADE_PHP' ));
			return false;
		}
	}

	/*
	* $parent is the class calling this method.
	* install runs after the database scripts are executed.
	* If the extension is new, the install method is run.
	* If install returns false, Joomla will abort the install and undo everything already done.
	*/
	function install( $parent ) {
		echo '<p> -- ' . JText::_('Installing ' . $this->release) . '</p>';
		if ( ! $this->do_extra( $parent ) ) return false;
		
		// You can have the backend jump directly to the newly installed component configuration page
		// $parent->getParent()->setRedirectURL('index.php?option=com_flexicontent');
	}
	
	
	/*
	* $parent is the class calling this method.
	* update runs after the database scripts are executed.
	* If the extension exists, then the update method is run.
	* If this returns false, Joomla will abort the update and undo everything already done.
	*/
	function update( $parent ) {
		echo '<p> -- ' . JText::_('Updating to ' . $this->release) . '</p>';
		if ( ! $this->do_extra( $parent ) ) return false;
		
		// You can have the backend jump directly to the newly updated component configuration page
		// $parent->getParent()->setRedirectURL('index.php?option=com_flexicontent');
	}
	
	
	function do_extra( $parent ) {
		// init vars
		$error = false;
		$extensions = array();
		
		// clear a cache
		$cache = JFactory::getCache();
		$cache->clean( '_system' );  // This might be necessary as installing-uninstalling in same session may result in wrong extension ids, etc
		$cache->clean( 'com_flexicontent' );
		$cache->clean( 'com_flexicontent_tmpl' );
		$cache->clean( 'com_flexicontent_cats' );
		$cache->clean( 'com_flexicontent_items' );
		$cache->clean( 'com_flexicontent_filters' );
		
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
		// All OK, or acceptable errors
		return true;
	}
	
	
	/*
	* $parent is the class calling this method.
	* $type is the type of change (install, update or discover_install, not uninstall).
	* postflight is run after the extension is registered in the database.
	*/
	function postflight( $type, $parent ) {
		/*
		// always create or modify these parameters
		$params['my_param0'] = 'Component version ' . $this->release;
		$params['my_param1'] = 'Another value';

		// define the following parameters only if it is an original install
		if ( $type == 'install' ) {
			$params['my_param2'] = '4';
			$params['my_param3'] = 'Star';
		}
		
		$this->setParams( $params );*/
		
		echo '<p> -- ' . JText::_('Performing POST-installation Task/Checks') .'</p>';
		
		$db = JFactory::getDBO();
		
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
							echo JText::_("Task <b>SUCCESSFUL</b>");
						} else {
							echo JText::_("Setting phpThumb Cache folder permissions UNSUCCESSFUL.");
						}
						?></span>
					</td>
				</tr>

		<?php
		$deprecated_fields = array('hidden'=>'text', 'relateditems'=>'relation', 'relateditems_backlinks'=>'relation_reverse');
		$app = JFactory::getApplication();
		
		// Get DB table information
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_files"';
		$db->setQuery($query);
		$files_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_fields"';
		$db->setQuery($query);
		$fields_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_types"';
		$db->setQuery($query);
		$types_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_advsearch_index"';
		$db->setQuery($query);
		$advsearch_index_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_authors_ext"';
		$db->setQuery($query);
		$authors_ext_tbl_exists = (boolean) count($db->loadObjectList());
		
		$failure_style = 'display:block; width:100%; font-weight: bold; color: red;';
		$success_style = 'font-weight: bold; color: green;';
		?>
		
		<?php
		// Update DB table flexicontent_fields: Convert deprecated fields types to 'text' field type
		?>
				<tr class="row0">
					<td class="key">Converting deprecated fields
					<?php
					$msg = array();
					if ($fields_tbl_exists) foreach ($deprecated_fields as $old_type => $new_type)
					{
						$query = 'UPDATE #__flexicontent_fields'
							.' SET field_type = ' .$db->Quote($new_type)
							.' WHERE field_type = ' .$db->Quote($old_type);
						$db->setQuery($query);
						$result = $db->query();
						if( !$result ) {
							$msg[] = "<span style='$failure_style'>UPDATE TABLE failed: ". $query ."</span>";
							continue;
						}
						
						$msg[] = $db->getAffectedRows($result)." deprecated fields '".$old_type."' were converted.";
						
						$query = 'SELECT *, extension_id AS id '
							. ' FROM '.( FLEXI_J16GE ? '#__extensions' : '#__plugins' )
							.' WHERE '. (FLEXI_J16GE ? 'type="plugin"' : '1')
							.'  AND element='.$db->Quote( $old_type )
							.'  AND folder='.$db->Quote( 'flexicontent_fields' );
						$db->setQuery($query);
						$ext = $db->loadAssoc();
						
						if ($ext && $ext['id'] > 0) {
							$installer = new JInstaller();
							if ( $installer->uninstall($ext['type'], $ext['id'], (int)$ext['client_id']) )
								$msg[] = " -- Uninstalled deprecated plugin: '".$old_type."'";
							else
								$msg[] = "<span style='$failure_style'> -- Failed to uninstalled deprecated plugin: '".$old_type."'";
						}
					}
					?>
					</td>
					<td> <?php echo implode("<br/>\n", $msg); ?> </td>
				</tr>
				
		<?php
		// Upgrade DB tables: ADD new columns
		?>
				<tr class="row1">
					<td class="key">Upgrading DB tables (adding new columns): </td>
					<td>
					<?php
					$tbls = array();
					if ($files_tbl_exists)   $tbls[] = "#__flexicontent_files";
					if ($fields_tbl_exists)  $tbls[] = "#__flexicontent_fields";
					if ($types_tbl_exists)   $tbls[] = "#__flexicontent_types";
					if ($advsearch_index_tbl_exists)
						$tbls[] = "#__flexicontent_advsearch_index";
					if (!FLEXI_J16GE) $tbl_fields = $db->getTableFields($tbls);
					else foreach ($tbls as $tbl) $tbl_fields[$tbl] = $db->getTableColumns($tbl);
					
					$queries = array();
					if ( $files_tbl_exists && !array_key_exists('description', $tbl_fields['#__flexicontent_files'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_files` ADD `description` TEXT NOT NULL AFTER `altname`";
					}
					if ( $fields_tbl_exists && !array_key_exists('untranslatable', $tbl_fields['#__flexicontent_fields'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields` ADD `untranslatable` TINYINT(1) NOT NULL DEFAULT '0' AFTER `isadvsearch`";
					}
					if ( $fields_tbl_exists && !array_key_exists('isadvfilter', $tbl_fields['#__flexicontent_fields'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields` ADD `isadvfilter` TINYINT(1) NOT NULL DEFAULT '0' AFTER `isfilter`";
					}
					if ( $fields_tbl_exists && !array_key_exists('formhidden', $tbl_fields['#__flexicontent_fields'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields` ADD `formhidden` SMALLINT(8) NOT NULL DEFAULT '0' AFTER `untranslatable`";
					}
					if ( $fields_tbl_exists && !array_key_exists('valueseditable', $tbl_fields['#__flexicontent_fields'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields` ADD `valueseditable` SMALLINT(8) NOT NULL DEFAULT '0' AFTER `formhidden`";
					}
					if ( $fields_tbl_exists && !array_key_exists('edithelp', $tbl_fields['#__flexicontent_fields'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields` ADD `edithelp` SMALLINT(8) NOT NULL DEFAULT '2' AFTER `formhidden`";
					}
					if ( $fields_tbl_exists && !array_key_exists('asset_id', $tbl_fields['#__flexicontent_fields']) && FLEXI_J16GE) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields` ADD `asset_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `id`";
					}
					if ( $types_tbl_exists && !array_key_exists('asset_id', $tbl_fields['#__flexicontent_types']) && FLEXI_J16GE) {
						$queries[] = "ALTER TABLE `#__flexicontent_types` ADD `asset_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `id`";
					}
					if ( $types_tbl_exists && !array_key_exists('itemscreatable', $tbl_fields['#__flexicontent_types'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_types` ADD `itemscreatable` SMALLINT(8) NOT NULL DEFAULT '0' AFTER `published`";
					}
					
					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							if ( !($result = $db->query()) ) {
								$result = false;
								echo "<span style='$failure_style'>ALTER TABLE failed: ". $query ."</span>";
							}
						}
						if ( $result !== false ) {
							echo "<span style='$success_style'>columns added</span>";
						}
					}
					else echo "<span style='$success_style'>nothing to do</span>";
					?>
					</td>
				</tr>
		
		<?php
		// Create/Upgrade ADVANCED index DB table: ADD column and indexes
		// Because Adding indexes can be heavy to the SQL server (if not done asychronously ??) we truncate table OR drop it and recreate it
		?>
				<tr class="row0">
					<td class="key">Create/Upgrade advanced search index table: </td>
					<td>
					<?php
					
			    $queries = array();
					if ( $advsearch_index_tbl_exists ) {
				    $db->setQuery("SHOW INDEX FROM #__flexicontent_advsearch_index");
				    $_indexes = $db->loadObjectList();
				    foreach ($_indexes as $tbl_index) $tbl_indexes['#__flexicontent_advsearch_index'][$tbl_index->Key_name] = true;
				  }
				  
					if ( !$advsearch_index_tbl_exists || !array_key_exists('sid', $tbl_fields['#__flexicontent_advsearch_index']) ) {
						if ( $advsearch_index_tbl_exists) $queries[] = "DROP TABLE `#__flexicontent_advsearch_index`";
						$queries[] = "CREATE TABLE `#__flexicontent_advsearch_index` (
							`sid` int(11) NOT NULL auto_increment,
							`field_id` int(11) NOT NULL, `item_id` int(11) NOT NULL, `extraid` int(11) NOT NULL,
							`search_index` longtext NOT NULL, `value_id` varchar(255) NULL,
							PRIMARY KEY (`field_id`,`item_id`,`extraid`),
							KEY `sid` (`sid`),
							KEY `field_id` (`field_id`),
							KEY `item_id` (`item_id`),
							FULLTEXT `search_index` (`search_index`),
							KEY `value_id` (`value_id`)
							) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`";
					}
					
					/*
					$_add_indexes = array();
					if ( $advsearch_index_tbl_exists && !array_key_exists('field_id', $tbl_indexes['#__flexicontent_advsearch_index'])) {
						$_add_indexes[] = " ADD KEY ( `field_id` ) ";
					}
					if ( $advsearch_index_tbl_exists && !array_key_exists('item_id', $tbl_indexes['#__flexicontent_advsearch_index'])) {
						$_add_indexes[] = " ADD KEY ( `item_id` ) ";
					}
					if ( $advsearch_index_tbl_exists && !array_key_exists('search_index', $tbl_indexes['#__flexicontent_advsearch_index'])) {
						$_add_indexes[] = " ADD FULLTEXT ( `search_index` ) ";
					}
					if ( $advsearch_index_tbl_exists && !array_key_exists('value_id', $tbl_indexes['#__flexicontent_advsearch_index'])) {
						$_add_indexes[] = " ADD KEY ( `value_id` ) ";
					}
					
					if (count($_add_indexes)) {
						$db->setQuery('TRUNCATE TABLE #__flexicontent_advsearch_index');
						$db->query();   // Truncate table of search index to avoid long-delay on indexing
						$queries[] = "ALTER TABLE `#__flexicontent_advsearch_index` ". implode(",", $_add_indexes);
					}
					*/
					
					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							if ( !($result = $db->query()) ) {
								$result = false;
								echo "<span style='$failure_style'>SQL QUERY failed: ". $query ."</span>";
							}
						}
						if ( $result !== false ) {
							echo "<span style='$success_style'>columns/indexes added, table was truncated or recreated, please re-index your content</span>";
						}
					}
					else echo "<span style='$success_style'>nothing to do</span>";
					?>
					</td>
				</tr>

		<?php
		// Create authors_ext table if it does not exist
		?>
				<tr class="row1">
					<td class="key">Create/Upgrade authors extended data table: </td>
					<td>
					<?php
					
			    $queries = array();
					if ( !$authors_ext_tbl_exists ) {
						$queries[] = "CREATE TABLE IF NOT EXISTS `#__flexicontent_authors_ext` (
						  `user_id` int(11) unsigned NOT NULL,
						  `author_basicparams` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
						  `author_catparams` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
						  PRIMARY KEY  (`user_id`)
						) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`";
					}
					
					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							if ( !($result = $db->query()) ) {
								$result = false;
								echo "<span style='$failure_style'>SQL QUERY failed: ". $query ."</span>";
							}
						}
						if ( $result !== false ) {
							echo "<span style='$success_style'>table created</span>";
						}
					}
					else echo "<span style='$success_style'>nothing to do</span>";
					?>
					</td>
				</tr>
				
			</tbody>
		</table>
		<?php
		
	}

	/*
	* $parent is the class calling this method
	* uninstall runs before any other action is taken (file removal or database processing).
	*/
	function uninstall( $parent ) {
		// Installing component manifest file version
		$this->release = $parent->get( "manifest" )->version;

		echo '<p>' . JText::_('Uninstalling FLEXIcontent ' . $this->release) . '</p>';
		
		// init vars
		$error = false;
		$extensions = array();
		$db =& JFactory::getDBO();
		
		// Uninstall additional flexicontent modules/plugins found in Joomla DB,
		// This code part (for uninstalling additional extensions) originates from Zoo Component:
		// Original uninstall.php file
		// @package   Zoo Component
		// @author    YOOtheme http://www.yootheme.com
		// @copyright Copyright (C) 2007 - 2009 YOOtheme GmbH
		// @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
		if (FLEXI_J16GE) {
			$manifest = isset($parent) ? $parent->getParent()->manifest : $this->manifest;
			$add_array =& $manifest->xpath('additional');
			$add = NULL;
			if(count($add_array)) $add = $add_array[0];
		} else {
			$add =& $this->manifest->getElementByPath('additional');
		}
		
		if ( is_object($add) && count( $add->children() ) )
		{
			$exts =& $add->children();
			foreach ($exts as $ext)
			{
				// set query
				switch ( FLEXI_J16GE ? $ext->getName() : $ext->name() ) {
					case 'plugin':
						if( FLEXI_J16GE ? $ext->attributes()->instfolder : $ext->attributes('instfolder') )
						{
							$query = 'SELECT * FROM '. ( FLEXI_J16GE ? '#__extensions' : '#__plugins' )
								.' WHERE '. (FLEXI_J16GE ? 'type='.$db->Quote($ext->getName()) : '1')
								.'  AND element='.$db->Quote( FLEXI_J16GE ? $ext->attributes()->name : $ext->attributes('name') )
								.'  AND folder='.$db->Quote( FLEXI_J16GE ? $ext->attributes()->instfolder : $ext->attributes('instfolder') )
								;
							// query extension id and client id
							$db->setQuery($query);
							$res = $db->loadObject();
		
							$res_id = (int)(FLEXI_J16GE ? @$res->extension_id : @$res->id);
							$extensions[] = array(
								'name' => (FLEXI_J16GE ? $ext->asXml() : $ext->data()),
								'type' => (FLEXI_J16GE ? $ext->getName() : $ext->name()),
								'id' => $res_id,
								'client_id' => isset($res->client_id) ? $res->client_id : 0,
								'installer' => new JInstaller(),
								'status' => false);
						}
						break;
					case 'module':
						$query = 'SELECT * FROM '. ( FLEXI_J16GE ? '#__extensions' : '#__modules' )
							.' WHERE '. (FLEXI_J16GE ? 'type='.$db->Quote($ext->getName()) : '1')
							.'  AND '. ( FLEXI_J16GE ? 'element='.$db->Quote($ext->attributes()->name) : 'module='.$db->Quote($ext->attributes('name')) )
							;
						// query extension id and client id
						$db->setQuery($query);
						$res = $db->loadObject();
						
						$res_id = (int)(FLEXI_J16GE ? @$res->extension_id : @$res->id);
						$extensions[] = array(
							'name' => (FLEXI_J16GE ? $ext->asXml() : $ext->data()),
							'type' => (FLEXI_J16GE ? $ext->getName() : $ext->name()),
							'id' => $res_id,
							'client_id' => isset($res->client_id) ? $res->client_id : 0,
							'installer' => new JInstaller(),
							'status' => false);
						break;
				}
			}
		}
		
		// uninstall additional extensions
		for ($i = 0; $i < count($extensions); $i++) {
			$extension =& $extensions[$i];
			
			if ($extension['id'] > 0 && $extension['installer']->uninstall($extension['type'], $extension['id'], $extension['client_id'])) {
				$extension['status'] = true;
			}
		}
		
		?>
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
							<span style="<?php echo $style; ?>"><?php echo $ext['status'] ? JText::_('Uninstalled successfully') : JText::_('Uninstall FAILED'); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

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
		// Alter com_flexicontent jcomments comments to be com_content comments
		?>
				<tr class="row0">
					<td class="key">Restore jComments comment to be of com_content Type</td>
					<td>
						<?php
						$query = 'SHOW TABLES LIKE "' . JFactory::getApplication()->getCfg('dbprefix') . 'jcomments"';
						$db->setQuery($query);
						$jcomments_tbl_exists = (boolean) count($db->loadObjectList());
						
						if (!$jcomments_tbl_exists) {
							$result = 0;
						} else {
							$query = 'UPDATE #__jcomments AS j'
								.' SET j.object_group="com_content" '
								.' WHERE j.object_group="com_flexicontent" ';
							$db->setQuery($query);
							$db->query();
							if ($db->getErrorNum()) {
								echo $db->getErrorMsg();
								$result = 1;
							} else {
								$result = 2;
							}
						}
						
						$style = ($result==2 || $result==0) ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;';
						?>
						<span style="<?php echo $style; ?>"><?php
						if ($result==2) {
							echo JText::_("Comments restored");
						} else if ($result==1) {
							echo JText::_("Failed to set comments as com_content comments");
						} else {
							echo JText::_("No jcomments table found");
						}
						?></span>
					</td>
				</tr>
		<?php
		if (FLEXI_J16GE) :
		// Restore com_content component asset, as asset parent_id, for the top-level 'com_content' categories
		?>
				<tr class="row1">
					<td class="key">Restore com_content assets</td>
					<td>
						<?php
						$asset	= JTable::getInstance('asset');
						$asset_loaded = $asset->loadByName('com_content');  // Try to load component asset for com_content
						if (!$asset_loaded) {
							$result = 0;
						} else {
							$query = 'UPDATE #__assets AS s'
								.' JOIN #__categories AS c ON s.id=c.asset_id'
								.' SET s.parent_id='.$db->Quote($asset->id)
								.' WHERE c.parent_id=1 AND c.extension="com_content"';
							$db->setQuery($query);
							$db->query();
							if ($db->getErrorNum()) {
								echo $db->getErrorMsg();
								$result = 1;
							} else {
								$result = 2;
							}
						}
						
						$style = $result==2 ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;';
						?>
						<span style="<?php echo $style; ?>"><?php
						if ($result==2) {
							echo JText::_("Assets restored");
						} else if ($result==1) {
							echo JText::_("Failed to set assets for com_content categories");
						} else {
							echo JText::_("Failed to load asset for com_content.");
						}
						?></span>
					</td>
				</tr>
				
			<?php endif; /* if FLEXI_J16GE */?>
			</tbody>
		</table>
			<?php
	}

	/*
	* get a variable from the manifest file (actually, from the manifest cache).
	*/
	function getParam( $name ) {
		$db = JFactory::getDBO();
		$db->setQuery('SELECT manifest_cache FROM #__extensions WHERE element = "com_flexicontent"');
		$str =  $db->loadResult();
		$manifest = json_decode($str, true );
		return $manifest[ $name ];
	}

	/*
	* sets parameter values in the component's row of the extension table
	*/
	function setParams($param_array) {
		if ( count($param_array) > 0 ) {
			// read the existing component value(s)
			$db = JFactory::getDBO();
			$db->setQuery('SELECT params FROM #__extensions WHERE element = "com_flexicontent"');
			$params = json_decode( $db->loadResult(), true );
			// add the new variable(s) to the existing one(s)
			foreach ( $param_array as $name => $value ) {
				$params[ (string) $name ] = (string) $value;
			}
			// store the combined new and existing values back as a JSON string
			$paramsString = json_encode( $params );
			$db->setQuery('UPDATE #__extensions SET params = ' .
			$db->quote( $paramsString ) .
			' WHERE name = "com_flexicontent"' );
			$db->query();
		}
	}
}