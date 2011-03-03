<?php
/**
 * @version 1.5 stable $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * Original uninstall.php file
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
 * Executes additional uninstallation processes
 *
 * @since 1.0
 */
// init vars
$error = false;
$extensions = array();
$db =& JFactory::getDBO();

//if you have new flexi plugins add here: name => folder
$flexiplugins = array(
	"checkbox"			=>	"flexicontent_fields",
	"checkboximage"		=>	"flexicontent_fields",
	"core"				=>	"flexicontent_fields",
	"date"				=>	"flexicontent_fields",
	"email"				=>	"flexicontent_fields",
	"file"				=>	"flexicontent_fields",
	"image"				=>	"flexicontent_fields",
	"radio"				=>	"flexicontent_fields",
	"radioimage"		=>	"flexicontent_fields",
	"select"			=>	"flexicontent_fields",
	"selectmultiple"	=>	"flexicontent_fields",
	"text"				=>	"flexicontent_fields",
	"textarea"			=>	"flexicontent_fields",
	"weblink"			=>	"flexicontent_fields",
	"extendedweblink"	=>	"flexicontent_fields",
	"linkslist"			=>	"flexicontent_fields",
	"minigallery"		=>	"flexicontent_fields",
	"toolbar"			=>	"flexicontent_fields",
	"flexisearch"		=>	"search",
	"flexisystem"		=>	"system",
	"flexiadvroute"		=>	"system"
);
// additional extensions
$add =& $this->manifest->getElementByPath('additional');
if (is_a($add, 'JSimpleXMLElement') && count($add->children())) {
    $exts =& $add->children();
    foreach ($exts as $ext) {

		// set query
		switch ($ext->name()) {
			case 'plugin':
				$attribute_name = $ext->attributes('name');
				if(array_key_exists($attribute_name, $flexiplugins)) {
					$query = 'SELECT * FROM #__plugins WHERE element='.$db->Quote($ext->attributes('name'))." AND folder='".$flexiplugins[$attribute_name]."';";
					// query extension id and client id
					$db->setQuery($query);
					$res = $db->loadObject();

					$extensions[] = array(
						'name' => $ext->data(),
						'type' => $ext->name(),
						'id' => isset($res->id) ? $res->id : 0,
						'client_id' => isset($res->client_id) ? $res->client_id : 0,
						'installer' => new JInstaller(),
						'status' => false);
				}
				break;
			case 'module':
				$query = 'SELECT * FROM #__modules WHERE module='.$db->Quote($ext->attributes('name'));
		// query extension id and client id
		$db->setQuery($query);
		$res = $db->loadObject();
		$extensions[] = array(
			'name' => $ext->data(),
			'type' => $ext->name(),
			'id' => isset($res->id) ? $res->id : 0,
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