<?php
/**
 * @version 1.5 stable $Id$
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

		/**
		 * Executes additional installation processes
		 *
		 * @since 1.0
		 */
		
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
		$this->parent->getDBO = $this->parent->getDBO();
		
		// additional extensions
		$add =& $this->manifest->getElementByPath('additional');
		if (is_a($add, 'JSimpleXMLElement') && count($add->children())) {
		    $exts =& $add->children();
		    foreach ($exts as $ext) {
				$extensions[] = array(
					'name' => $ext->data(),
					'type' => $ext->name(),
					'folder' => $this->parent->getPath('source').'/'.$ext->attributes('folder'),
					'installer' => new JInstaller(),
					'status' => false);
		    }
		}
		
		// install additional extensions
		for ($i = 0; $i < count($extensions); $i++) {
			$extension =& $extensions[$i];
			if ($extension['installer']->install($extension['folder'])) {
				$extension['status'] = true;
			} else {
				$error = true;
				break;
			}
		}
		
		// rollback on installation errors
		if ($error) {
			$this->parent->abort(JText::_('Component').' '.JText::_('Install').': '.JText::_('Error'), 'component');
			for ($i = 0; $i < count($extensions); $i++) { 
				if ($extensions[$i]['status']) {
					$extensions[$i]['installer']->abort(JText::_($extensions[$i]['type']).' '.JText::_('Install').': '.JText::_('Error'), $extensions[$i]['type']);
					$extensions[$i]['status'] = false;
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
		$db = &JFactory::getDBO();
		
		// Delete orphan entries ?
		$query="DELETE FROM `#__extensions` WHERE folder='flexicontent_fields' AND element IN ('flexisystem', 'flexiadvroute', 'flexisearch', 'flexiadvsearch', 'flexinotify')";
		$db->setQuery($query);
		$result = $db->query();
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
		
		$query = "SHOW COLUMNS FROM #__flexicontent_files";
		$db->setQuery($query);
		$tbl_cols = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
		foreach ($tbl_cols as $tbl_col) $files_tbl_cols[$tbl_col] = 1;
		
		$query = "SHOW COLUMNS FROM #__flexicontent_fields";
		$db->setQuery($query);
		$tbl_cols = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
		foreach ($tbl_cols as $tbl_col) $fields_tbl_cols[$tbl_col] = 1;
		
		$query = "SHOW COLUMNS FROM #__flexicontent_advsearch_index";
		$db->setQuery($query);
		$tbl_cols = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
		foreach ($tbl_cols as $tbl_col) $advsearch_index_tbl_cols[$tbl_col] = 1;
		
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
		
			</tbody>
		</table>
