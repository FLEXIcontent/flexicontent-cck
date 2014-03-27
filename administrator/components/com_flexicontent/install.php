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

defined( '_JEXEC' ) or die( 'Restricted access' );


// Joomla version variables
if (!defined('FLEXI_J16GE') || !defined('FLEXI_J30GE')) {
	jimport( 'joomla.version' );  $jversion = new JVersion;
}
if (!defined('FLEXI_J16GE'))   define('FLEXI_J16GE', version_compare( $jversion->getShortVersion(), '1.6.0', 'ge' ) );
if (!defined('FLEXI_J30GE'))   define('FLEXI_J30GE', version_compare( $jversion->getShortVersion(), '3.0.0', 'ge' ) );

if (FLEXI_J16GE) {
	echo "Wrong Joomla version, this FLEXIcontent package is for J1.5";
	exit();
}
		
		// Make sure that fatal errors are printed
		error_reporting(E_ERROR);
		ini_set('display_errors',1);
		
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
		$PHP_VERSION_NEEDED = '5.1.0';
		if (version_compare(PHP_VERSION, $PHP_VERSION_NEEDED, '<'))
		{
			// load english language file for 'com_flexicontent' component then override with current language file
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		
			Jerror::raiseWarning(null, JText::sprintf( 'FLEXI_UPGRADE_PHP_VERSION_GE', $PHP_VERSION_NEEDED ));
			return false;
		}
		
		// Get Joomla version
		$jversion = new JVersion();
		
		// File version of new manifest file
		$this->release = FLEXI_J16GE ?  $parent->get( "manifest" )->version : $this->manifest->getElementByPath('version')->data();
		
		// File version of existing manifest file
		$this->release_existing = FLEXI_J16GE ? $this->getParam('version') : 0;
		
		// Manifest file minimum Joomla version
		$this->minimum_joomla_release = FLEXI_J16GE ? $parent->get( "manifest" )->attributes()->version : $this->manifest->attributes('version');
		
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
			$rel = $this->release_existing . ' to ' . $this->release;
			if ( version_compare( $this->release, $oldRelease, 'l' ) ) {
				Jerror::raiseNotice(null, 'Downgrading component from ' . $rel);
				//return false;  // Returning false here would abort
			}
		}
		
		// Detect FLEXIcontent installed
		if (FLEXI_J16GE)
			define('FLEXI_INSTALLED', $this->release_existing ? 1 : 0); 
		else
			define('FLEXI_INSTALLED', JPluginHelper::isEnabled('system', 'flexisystem') );
		
		
		
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
		$db = JFactory::getDBO();
		
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
						'name' => strip_tags(FLEXI_J16GE ? $ext->asXml() : $ext->data()),
						'type' => (FLEXI_J16GE ? $ext->getName() : $ext->name()),
						'folder' => $source.'/'.(FLEXI_J16GE ? $ext->attributes()->folder : $ext->attributes('folder')),
						'ext_name' => ''.(FLEXI_J16GE ? $ext->attributes()->name : $ext->attributes('name')),  // concat to empty string to convert to string
						'ext_folder' => ''.(FLEXI_J16GE ? $ext->attributes()->instfolder : $ext->attributes('instfolder')),  // concat to empty string to convert to string
						'installer' => new JInstaller(),
						'status' => null);
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
				
				$ext_manifest = $jinstaller->getManifest();
				$ext_manifest_name = FLEXI_J16GE ? $ext_manifest->name : $ext_manifest->document->getElementByPath('name')->data();
				//if ($ext_manifest_name!=$extensions[$i]['name'])  echo $ext_manifest_name." - ".$extensions[$i]['name'] . "<br/>";
				
				// Force existing plugins/modules to use name found in each extension's manifest.xml file
				if (FLEXI_J16GE || $extensions[$i]['ext_folder'] == 'flexicontent_fields') {
					$ext_tbl   = FLEXI_J16GE ? '#__extensions' : '#__plugins';
					$query = 'UPDATE '.$ext_tbl
						//.' SET name = '.$db->Quote($extensions[$i]['name'])
						.' SET name = '.$db->Quote($ext_manifest_name)
						.' WHERE element = '.$db->Quote($extensions[$i]['ext_name'])
						.'  AND folder = '.$db->Quote($extensions[$i]['ext_folder'])
						.(FLEXI_J16GE ? '  AND type = '.$db->Quote($extensions[$i]['type']) : '')
						;
					$db->setQuery($query);
					$db->query();
				}
			} else {
				$extensions[$i]['status'] = false;
				if ( !FLEXI_INSTALLED ) {
					$error = true;
					break;
				}
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
		       	 	<span>Flexible content management system for Joomla! J1.5/J2.5/J3.2</span><br />
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
						<td class="key">[<?php echo JText::_($ext['type']); ?>] <?php echo $ext['name']; ?></td>
						<td>
							<?php
							if ($ext['status']===null) $status_color = 'black';
							else if ($ext['status']) $status_color = 'green';
							else $status_color = 'red';
							$style = 'font-weight: bold; color: '.$status_color.';';
							?>
							<span style="<?php echo $style; ?>">
								<?php
									if ( $ext['status'] === null ) {
										echo JText::_('Installation skipped');
									} else if ($ext['status']) {
										echo JText::_('Installation successful');
									} else {
										$msg = JText::_(FLEXI_INSTALLED ? 'Upgrade ERROR (extension removed)' : 'Installation -- FAILED --' ) ."<br/>";
										if (FLEXI_INSTALLED) $msg .= "FLEXIcontent may not work properly, please install an older or newer FLEXIcontent package";
										echo $msg;
										Jerror::raiseWarning(null, '<br/>'.$extensions[$i]['name'] .' '. JText::_($extensions[$i]['type']) .': '. $msg);
									}
								?>
							</span>
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
					$extensions[$i]['installer']->abort('<span style="color:black">'.
						$extensions[$i]['name'] .' '. JText::_($extensions[$i]['type']) .' '. JText::_('Install') .':</span>'.
						' <span style="color:green">'. JText::_('rolling back').'</span>',
						$extensions[$i]['type']
					);
					//$extensions[$i]['status'] = false;
				} /*else if ( $extensions[$i]['status'] === false ) {
					$msg = ' <span style="color:red">'. JText::_('-- FAILED --').'</span>';
					Jerror::raiseWarning(null, '<span style="color:black">'.$extensions[$i]['name'] .' '. JText::_($extensions[$i]['type']) .' '. JText::_('Install') .':</span>'.$msg);
				} else {
					$msg = ' <span style="color:darkgray">'. JText::_('Skipped').'</span>';
					Jerror::raiseWarning(null, '<span style="color:black">'.$extensions[$i]['name'] .' '. JText::_($extensions[$i]['type']) .' '. JText::_('Install') .':</span>'.$msg);
				}*/
			}
			if (!FLEXI_J16GE) {
				$this->parent->abort("<br/>".JText::_('Component installation aborted'), 'component');
			} else {
				return false;  // In J1.6+ , returning false here will cancel (abort) component installation and rollback changes
			}
		}
		
		if (FLEXI_J30GE)  echo '<link type="text/css" href="components/com_flexicontent/assets/css/j3x.css" rel="stylesheet">';
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
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_items_ext"';
		$db->setQuery($query);
		$iext_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_advsearch_index"';
		$db->setQuery($query);
		$advsearch_index_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_authors_ext"';
		$db->setQuery($query);
		$authors_ext_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_items_tmp"';
		$db->setQuery($query);
		$content_cache_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_download_history"';
		$db->setQuery($query);
		$dl_history_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_download_coupons"';
		$db->setQuery($query);
		$dl_coupons_tbl_exists = (boolean) count($db->loadObjectList());
		
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
					if ($iext_tbl_exists)    $tbls[] = "#__flexicontent_items_ext";
					if ($advsearch_index_tbl_exists)
						$tbls[] = "#__flexicontent_advsearch_index";
					if (!FLEXI_J16GE) $tbl_fields = $db->getTableFields($tbls);
					else foreach ($tbls as $tbl) $tbl_fields[$tbl] = $db->getTableColumns($tbl);
					
					$queries = array();
					if ( $iext_tbl_exists ) {
						$_query = "ALTER TABLE `#__flexicontent_items_ext`";
						$_querycols = array();
						if (array_key_exists('cnt_state', $tbl_fields['#__flexicontent_items_ext'])) $_querycols[] = "  DROP `cnt_state`";
						if (array_key_exists('cnt_access', $tbl_fields['#__flexicontent_items_ext'])) $_querycols[] = " DROP `cnt_access`";
						if (array_key_exists('cnt_publish_up', $tbl_fields['#__flexicontent_items_ext'])) $_querycols[] = " DROP `cnt_publish_up`";
						if (array_key_exists('cnt_publish_down', $tbl_fields['#__flexicontent_items_ext'])) $_querycols[] = " DROP `cnt_publish_down`";
						if (array_key_exists('cnt_created_by', $tbl_fields['#__flexicontent_items_ext'])) $_querycols[] = " DROP `cnt_created_by`";
						if (empty($_querycols)) $queries[] = $_query . implode(",", $_querycols);
					}
					if ( $files_tbl_exists && !array_key_exists('description', $tbl_fields['#__flexicontent_files'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_files` ADD `description` TEXT NOT NULL DEFAULT '' AFTER `altname`";
					}
					if ( $files_tbl_exists && !array_key_exists('language', $tbl_fields['#__flexicontent_files'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_files` ADD `language` CHAR(7) NOT NULL DEFAULT '*' AFTER `published`";
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
					<td class="key">Create/Upgrade authors extended DB table: </td>
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
				
		<?php
		// Create content_cache table if it does not exist
		?>
				<tr class="row0">
					<td class="key">Create/Upgrade content cache DB table: </td>
					<td>
					<?php
					
			    $queries = array();
					if ( !$content_cache_tbl_exists ) {
						$queries[] = "
							CREATE TABLE `#__flexicontent_items_tmp` (
							 `id` int(10) unsigned NOT NULL,
							 `title` VARCHAR(255) NOT NULL,
							 `state` tinyint(3) NOT NULL DEFAULT '0',
							 `catid` int(10) unsigned NOT NULL DEFAULT '0',
							 `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							 `created_by` int(10) unsigned NOT NULL DEFAULT '0',
							 `modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							 `modified_by` int(10) unsigned NOT NULL DEFAULT '0',
							 `publish_up` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							 `publish_down` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							 `version` int(10) unsigned NOT NULL DEFAULT '1',
							 `ordering` int(11) NOT NULL DEFAULT '0',
							 `access` int(10) unsigned NOT NULL DEFAULT '0',
							 `hits` int(10) unsigned NOT NULL DEFAULT '0',
							 `language` char(7) NOT NULL,
							 PRIMARY KEY (`id`)
							) ENGINE=MyISAM DEFAULT CHARSET=utf8;
						";
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
				
		<?php
		// Create/Upgrade DB tables for downloads enhancements
		?>
				<tr class="row1">
					<td class="key">Create/Upgrade DB tables for downloads enhancements: </td>
					<td>
					<?php
					
			    $queries = array();
					
					if ( !$dl_history_tbl_exists ) {
						$queries[] = "
						CREATE TABLE `#__flexicontent_download_history` (
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`user_id` int(11) NOT NULL,
							`file_id` int(11) NOT NULL,
							`hits` int(11) NOT NULL,
							`last_hit_on` datetime NOT NULL,
							PRIMARY KEY (`id`)
						) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`";
					}
					
					if ( !$dl_coupons_tbl_exists ) {
						$queries[] = "
						CREATE TABLE `#__flexicontent_download_coupons` (
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`user_id` int(11) NOT NULL,
							`file_id` int(11) NOT NULL,
							`token` varchar(255) NOT NULL,
							`hits` int(11) NOT NULL,
							`hits_limit` int(11) NOT NULL,
							`expire_on` datetime NOT NULL default '0000-00-00 00:00:00',
							PRIMARY KEY (`id`)
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
							echo "<span style='$success_style'>tables was created / upgraded</span>";
						}
					}
					else echo "<span style='$success_style'>nothing to do</span>";
					?>
					</td>
				</tr>
				
			</tbody>
		</table>
