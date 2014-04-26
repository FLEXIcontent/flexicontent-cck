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

		//error_reporting(E_ALL & ~E_STRICT);
		//ini_set('display_errors',1);

		// Extra CSS needed for J3.x+
		if (FLEXI_J30GE)  echo '<link type="text/css" href="components/com_flexicontent/assets/css/j3x.css" rel="stylesheet">';
		
		// Installed component manifest file version
		$this->release = FLEXI_J16GE ? $parent->get( "manifest" )->version : $this->manifest->getElementByPath('version')->data();
		echo '<p>' . JText::_('Uninstalling FLEXIcontent ' . $this->release) . '</p>';
		
		// init vars
		$error = false;
		$extensions = array();
		$db = JFactory::getDBO();
		
		// Uninstall additional flexicontent modules/plugins found in Joomla DB,
		// This code part (for uninstalling additional extensions) originates from Zoo Component:
		// Original uninstall.php file
		// @package   Zoo Component
		// @author    YOOtheme http://www.yootheme.com
		// @copyright Copyright (C) 2007 - 2009 YOOtheme GmbH
		// @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
		if (FLEXI_J16GE) {
			$manifest = isset($parent) ? $parent->getParent()->manifest : $this->manifest;
			$add_array = $manifest->xpath('additional');
			$add = NULL;
			if(count($add_array)) $add = $add_array[0];
		} else {
			$add =& $this->manifest->getElementByPath('additional');
		}
		
		if ( is_object($add) && count( $add->children() ) )
		{
			$exts = $add->children();
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
					<td class="key">Restore com_content top-level category assets</td>
					<td>
						<?php
						$asset	= JTable::getInstance('asset');
						$asset_loaded = $asset->loadByName('com_content');  // Try to load component asset for com_content
						if (!$asset_loaded) {
							$result = 0;
						} else {
							$cc_asset	= JTable::getInstance('asset');
							$query = 'SELECT s.id FROM #__assets AS s'
								.' JOIN #__categories AS c ON s.id=c.asset_id'
								.' WHERE c.parent_id=1 AND c.extension="com_content"';
							$db->setQuery($query);
							$asset_ids = $db->loadColumn();
							
							$result = 2;
							foreach ($asset_ids as $asset_id) 
							{
								//echo $asset_id." parent to -> " .$asset->id ."<br/>";
								$cc_asset->load($asset_id);
								$cc_asset->parent_id = $asset->id;
								$cc_asset->lft = $asset->rgt;
								$cc_asset->setLocation($asset->id, 'last-child');
								
								// Save the category asset (create or update it)
								if (!$cc_asset->check() || !$cc_asset->store(false)) {
									echo $cc_asset->getError();
									echo " Problem restoring asset with id: ".$cc_asset ->id;
									//echo " Problem for category with id: ".$category->id. "(".$category->title.")";
									//echo $cc_asset->getError();
									$result = 1;
								}
							}
						}
						
						$style = $result==2 ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;';
						?>
						<span style="<?php echo $style; ?>"><?php
						if ($result==2) {
							echo JText::_("Assets restored");
						} else if ($result==1) {
							echo JText::_("Failed to restore some assets");
						} else {
							echo JText::_("Failed to load asset for com_content.");
						}
						?></span>
					</td>
				</tr>
				
			<?php endif; /* if FLEXI_J16GE */?>
			</tbody>
		</table>
