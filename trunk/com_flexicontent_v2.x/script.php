<?php
/**
 * @version 1.5 stable $Id: install.php 1304 2012-05-14 20:54:07Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * Original install.php file
 * @package   Zoo Component
 * @author    YOOtheme http://www.yootheme.com
 * @copyright Copyright (C) 2007 - 2009 YOOtheme GmbH
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
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

class com_flexicontentInstallerScript
{
	/*
	* $parent is the class calling this method.
	* $type is the type of change (install, update or discover_install, not uninstall).
	* preflight runs before anything else and while the extracted files are in the uploaded temp folder.
	* If preflight returns false, Joomla will abort the update and undo everything already done.
	*/
	function preflight( $type, $parent ) {
		$jversion = new JVersion();

		// Installing component manifest file version
		$this->release = $parent->get( "manifest" )->version;

		// Manifest file minimum Joomla version
		$this->minimum_joomla_release = $parent->get( "manifest" )->attributes()->version;

		echo '<p> -- ' . JText::_('Performing task/checks prior to ' . $type . ' ' . $this->release) . '</p>';
		
		// Show the essential information at the install/update back-end
		echo '<br /> &nbsp; Installing component manifest file version = ' . $this->release;
		echo '<br /> &nbsp; Current manifest cache component version = ' . $this->getParam('version');
		echo '<br /> &nbsp; Installing component manifest file minimum Joomla version = ' . $this->minimum_joomla_release;
		echo '<br /> &nbsp; Current Joomla version = ' . $jversion->getShortVersion();

		// abort if the current Joomla release is older
		if( version_compare( $jversion->getShortVersion(), $this->minimum_joomla_release, 'lt' ) ) {
			Jerror::raiseWarning(null, 'Cannot install com_democompupdate in a Joomla release prior to '.$this->minimum_joomla_release);
			return false;
		}

		// abort if the component being installed is not newer than the currently installed version
		if ( $type == 'update' ) {
			$oldRelease = $this->getParam('version');
			$rel = $oldRelease . ' to ' . $this->release;
			if ( version_compare( $this->release, $oldRelease, 'le' ) ) {
				Jerror::raiseWarning(null, 'Incorrect version sequence. Cannot upgrade ' . $rel);
				return false;
			}
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
	}

	/*
	* $parent is the class calling this method.
	* install runs after the database scripts are executed.
	* If the extension is new, the install method is run.
	* If install returns false, Joomla will abort the install and undo everything already done.
	*/
	function install( $parent ) {
		echo '<p> -- ' . JText::_('Installing ' . $this->release) . '</p>';
		// You can have the backend jump directly to the newly installed component configuration page
		// $parent->getParent()->setRedirectURL('index.php?option=com_democompupdate');
		
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
		//$this->parent->getDBO = $this->parent->getDBO();
		
		// additional extensions
    $source = $parent->getParent()->getPath('source');
		//$manifest = & $parent->get( "manifest" );
    $manifest = $parent->getParent()->manifest;
    //$plugins = $manifest->xpath('additional/plugin');
		$add_array =& $manifest->xpath('additional');
		//echo "<pre>"; print_r($add_array); echo "</pre>";
		
		$add = NULL;
		if(count($add_array)) $add = $add_array[0];
		if (is_a($add, 'SimpleXMLElement') && count($add->children())) {
		    $exts =& $add->children();
		    foreach ($exts as $ext) {
					$extensions[] = array(
						'name' => $ext->asXml(),
						'type' => $ext->getName(),
						'folder' => $source.'/'.$ext->attributes()->folder,
						'status' => false);
		    }
				//echo "<pre>"; print_r($extensions); echo "</pre>";
		}
		
		// install additional extensions
		foreach ($extensions as $i => $extension) {
			$jinstaller = new JInstaller();
			$jinstaller->setOverwrite(true);
			$jinstaller->setUpgrade(true);
			if ($jinstaller->install($extensions[$i]['folder'])) {
				$extensions[$i]['status'] = true;
			} else {
				$error = true;
				break;
			}
		}
		
		// rollback on installation errors, FOR J1.6+ commented out until we can test as the Joomla installer interface was changed
		/*if ($error) {
			$this->parent->abort(JText::_('Component').' '.JText::_('Install').': '.JText::_('Error'), 'component');
			for ($i = 0; $i < count($extensions); $i++) { 
				if ($extensions[$i]['status']) {
					$extensions[$i]['installer']->abort(JText::_($extensions[$i]['type']).' '.JText::_('Install').': '.JText::_('Error'), $extensions[$i]['type']);
					$extensions[$i]['status'] = false;
				}
			}
		}*/
		
		?>
		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="adminlist">
			<tr>
				<td valign="top">
		    		<img src="<?php echo 'components/com_flexicontent/assets/images/logo.png'; ?>" height="96" width="300" alt="FLEXIcontent Logo" align="left" />
				</td>
				<td valign="top" width="100%">
		       	 	<strong>FLEXIcontent</strong><br/>
		       	 	<span>Flexible content management system for Joomla! J1.5/J2.5</span><br />
		        	<font class="small">by <a href="http://www.vistamedia.fr" target="_blank">Emmanuel Danan</a><br/>
		        	<font class="small">and Georgios Papadakis<br/>
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

		<?
	}

	/*
	* $parent is the class calling this method.
	* update runs after the database scripts are executed.
	* If the extension exists, then the update method is run.
	* If this returns false, Joomla will abort the update and undo everything already done.
	*/
	function update( $parent ) {
		echo '<p> -- ' . JText::_('Updating to ' . $this->release) . '</p>';
		$this->install( $parent );
		
		// You can have the backend jump directly to the newly updated component configuration page
		// $parent->getParent()->setRedirectURL('index.php?option=com_democompupdate');
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

		echo '<p> -- ' . JText::_('Performing task/checks after ' . $type . ' to ' . $this->release) . '</p>';

		$db = &JFactory::getDBO();
		
		// Delete orphan entries ?
		$query="DELETE FROM `#__extensions` WHERE folder='flexicontent_fields' AND element IN ('flexisystem', 'flexiadvroute', 'flexisearch', 'flexiadvsearch', 'flexinotify')";
		$db->setQuery($query);
		$result = $db->query();

		// System plugins must be enabled
		$query = "UPDATE #__extensions SET enabled=1 WHERE type='plugin' AND element=".$db->Quote('flexisystem')." AND folder=".$db->Quote('system');
		$db->setQuery($query);
		$db->query();
		$query = "UPDATE #__extensions SET enabled=1 WHERE type='plugin' AND element=".$db->Quote('flexiadvroute')." AND folder=".$db->Quote('system');
		$db->setQuery($query);
		$db->query();

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
		$deprecated_fields_arr = array('hidden');
		$deprecated_fields = "'". implode("','", $deprecated_fields_arr) ."'";
		
		// Get DB table information
		
    $files_tbl_cols = $db->getTableColumns('#__flexicontent_files');
    $fields_tbl_cols = $db->getTableColumns('#__flexicontent_fields');
    $advsearch_index_tbl_cols = $db->getTableColumns('#__flexicontent_advsearch_index');
		
		$query = "SELECT COUNT(*) FROM `#__flexicontent_fields` WHERE field_type IN (".$deprecated_fields.")";
		$db->setQuery($query);
		$deprecated_fields = $db->loadResult();
		?>
		
		<?php
		// Update DB table flexicontent_fields: Convert deprecated fields types to 'text' field type
		?>
				<tr class="row0">
					<td class="key">Run SQL "UPDATE `...__flexicontent_fields` SET field_type=`text` WHERE field_type IN (<?php echo $deprecated_fields; ?>)"
					<?php
					$already = true;
					$result = false;
					if( $deprecated_fields ) {
						$already = false;
						$query = "UPDATE `#__flexicontent_fields` SET field_type=`text` WHERE field_type IN (".$deprecated_fields.")";
						$db->setQuery($query);
						$result = $db->query();
					}
					?>
					</td>
					<td>
						<?php $style = ($already||$result) ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;'; ?>
						<span style="<?php echo $style; ?>"><?php
						if($already) {
							echo JText::_("Task <b>SUCCESSFUL</b>: No deprecated fields found.");
						} elseif($result) {
							echo JText::_("Task <b>SUCCESSFUL</b>: Deprecated Fields converted to 'text' field type.");
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
					<td class="key">Run SQL "ALTER TABLE `..._flexicontent_advsearch_index` ADD `value_id` TEXT NULL AFTER `search_index`"
					<?php
					$already = true;
					$result = false;
					if (!array_key_exists('value_id', $advsearch_index_tbl_cols)) {
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
							echo JText::_("Task <b>SUCCESSFUL</b>: Column 'value_id' already exists.");
						} elseif($result) {
							echo JText::_("Task <b>SUCCESSFUL</b>: Column 'value_id' added.");
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
					<td class="key">Run SQL "ALTER TABLE `..._flexicontent_files` ADD `description` TEXT NOT NULL AFTER `altname`"
					<?php
					$already = true;
					$result = false;
					if (!array_key_exists('description', $files_tbl_cols)) {
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
							echo JText::_("Task <b>SUCCESSFUL</b>: Column 'description' already exists.");
						} elseif($result) {
							echo JText::_("Task <b>SUCCESSFUL</b>: Column 'description' added.");
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
					<td class="key">Run SQL "ALTER TABLE `...__flexicontent_fields` ADD `untranslatable` TEXT NOT NULL AFTER `isadvsearch`"
					<?php
					$already = true;
					$result = false;
					if (!array_key_exists('untranslatable', $fields_tbl_cols)) {
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
							echo JText::_("Task <b>SUCCESSFUL</b>: Column 'untranslatable' already exists.");
						} elseif($result) {
							echo JText::_("Task <b>SUCCESSFUL</b>: Column 'untranslatable' added.");
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
					<td class="key">Run SQL "ALTER TABLE `...__flexicontent_fields` ADD `formhidden` TEXT NOT NULL AFTER `untranslatable`"
					<?php
					$already = true;
					$result = false;
					if (!array_key_exists('formhidden', $fields_tbl_cols)) {
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
							echo JText::_("Task <b>SUCCESSFUL</b>: Column 'formhidden' already exists.");
						} elseif($result) {
							echo JText::_("Task <b>SUCCESSFUL</b>: Column 'formhidden' added.");
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
					<td class="key">Run SQL "ALTER TABLE `...__flexicontent_fields` ADD `valueseditable` TEXT NOT NULL AFTER `formhidden`"
					<?php
					$already = true;
					$result = false;
					if (!array_key_exists('valueseditable', $fields_tbl_cols)) {
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
							echo JText::_("Task <b>SUCCESSFUL</b>: Column 'valueseditable' already exists.");
						} elseif($result) {
							echo JText::_("Task <b>SUCCESSFUL</b>: Column 'valueseditable' added.");
						} else {
							echo JText::_("ALTER TABLE command UNSUCCESSFUL.");
						}
						?></span>
					</td>
				</tr>
		
		<?php
		// Alter table __flexicontent_fields: Add asset_id column
		?>
				<tr class="row0">
					<td class="key">Run SQL "ALTER TABLE `..._flexicontent_fields` ADD `asset_id` INT NULL DEFAULT NULL AFTER `id`,<br> ADD UNIQUE ( `asset_id` )"
					<?php
					$already = true;
					$result = false;
					if (!array_key_exists('asset_id', $fields_tbl_cols)) {
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
							echo JText::_("Task <b>SUCCESSFUL</b>: Column 'asset_id' already exists.");
						} elseif($result) {
							echo JText::_("Task <b>SUCCESSFUL</b>: Column 'asset_id' added.");
						} else {
							echo JText::_("ALTER TABLE command UNSUCCESSFUL.");
						}
						?></span>
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
		echo '<p>' . JText::_('COM_DEMOCOMPUPDATE_UNINSTALL ' . $this->release) . '</p>';
		
		// init vars
		$error = false;
		$extensions = array();
		$db =& JFactory::getDBO();
		
		// Restore com_content component asset, as asset parent_id, for the top-level 'com_content' categories
		$asset	= JTable::getInstance('asset');
		$asset_loaded = $asset->loadByName('com_content');  // Try to load component asset for com_content
		if (!$asset_loaded) {
			echo '<span style="font-weight: bold; color: darkred;" >Failed to load asset for com_content</span><br/>';
		} else {
			$query = 'UPDATE #__assets AS s'
				.' JOIN #__categories AS c ON s.id=c.asset_id'
				.' SET s.parent_id='.$db->Quote($asset->id)
				.' WHERE c.parent_id=1 AND c.extension="com_content"';
			$db->setQuery($query);
			$db->query();
			if ($db->getErrorNum()) {
				echo $db->getErrorMsg();
				echo '<span style="font-weight: bold; color: darkred;" >Failed to load asset for com_content</span><br/>';
			} else {
				echo '<span style="font-weight: bold; color: darkgreen;" >Restored parent asset for top level categories</span><br/>';
			}
		}
		
		
		
		// additional extensions
    $manifest = $parent->getParent()->manifest;
		$add_array =& $manifest->xpath('additional');
		$add = NULL;
		if(count($add_array)) $add = $add_array[0];
		if ( is_a($add, 'JXMLElement') && count($add->children()) )
		{
			$exts =& $add->children();
			foreach ($exts as $ext)
			{
				// set query
				switch ($ext->name()) {
					case 'plugin':
						$attribute_name = $ext->getAttribute('name');
						if( $ext->getAttribute('instfolder') ) {
							$query = 'SELECT * FROM #__extensions'
								.' WHERE type='.$db->Quote($ext->name())
								.'  AND element='.$db->Quote($ext->getAttribute('name'))
								.'  AND folder='.$db->Quote($ext->getAttribute('instfolder'));
							// query extension id and client id
							$db->setQuery($query);
							$res = $db->loadObject();
		
							$extensions[] = array(
								'name' => $ext->data(),
								'type' => $ext->name(),
								'id' => isset($res->extension_id) ? $res->extension_id : 0,
								'client_id' => isset($res->client_id) ? $res->client_id : 0,
								'installer' => new JInstaller(),
								'status' => false);
						}
						break;
					case 'module':
						$query = 'SELECT * FROM #__extensions WHERE type='.$db->Quote($ext->name()).' AND element='.$db->Quote($ext->getAttribute('name'));
						// query extension id and client id
						$db->setQuery($query);
						$res = $db->loadObject();
						$extensions[] = array(
							'name' => $ext->data(),
							'type' => $ext->name(),
							'id' => isset($res->extension_id) ? $res->extension_id : 0,
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
		<?php
	}

	/*
	* get a variable from the manifest file (actually, from the manifest cache).
	*/
	function getParam( $name ) {
		$db = JFactory::getDbo();
		$db->setQuery('SELECT manifest_cache FROM #__extensions WHERE name = "com_democompupdate"');
		$manifest = json_decode( $db->loadResult(), true );
		return $manifest[ $name ];
	}

	/*
	* sets parameter values in the component's row of the extension table
	*/
	function setParams($param_array) {
		if ( count($param_array) > 0 ) {
			// read the existing component value(s)
			$db = JFactory::getDbo();
			$db->setQuery('SELECT params FROM #__extensions WHERE name = "com_democompupdate"');
			$params = json_decode( $db->loadResult(), true );
			// add the new variable(s) to the existing one(s)
			foreach ( $param_array as $name => $value ) {
				$params[ (string) $name ] = (string) $value;
			}
			// store the combined new and existing values back as a JSON string
			$paramsString = json_encode( $params );
			$db->setQuery('UPDATE #__extensions SET params = ' .
			$db->quote( $paramsString ) .
			' WHERE name = "com_democompupdate"' );
			$db->query();
		}
	}
}