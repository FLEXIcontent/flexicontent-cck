<?php
/**
 * @version 1.5 stable $Id: uninstall.php 1438 2012-08-18 01:53:18Z ggppdk $
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
if (!defined('FLEXI_J16GE')) {
	//define('FLEXI_J16GE' , 1 );
	jimport( 'joomla.version' );  $jversion = new JVersion;
	define('FLEXI_J16GE', version_compare( $jversion->getShortVersion(), '1.6.0', 'ge' ) );
	define('FLEXI_J30GE', version_compare( $jversion->getShortVersion(), '3.0.0', 'ge' ) );
}

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
