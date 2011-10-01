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
	$document =& JFactory::getDocument(); 
	$document->addStyleSheet($css);	
	
	// we use the static loadLanguage() method in the JPlugin class - a little tricky
	jimport('joomla.plugin.plugin');
	JPlugin::loadLanguage('com_flexicontent', JPATH_ADMINISTRATOR);

	echo '<div class="fc-error">';
	echo JText::_( 'FLEXI_UPGRADE_PHP' ) . '<br />';
	echo '</div>';
	return false;
}

// init vars
$error = false;
$extensions = array();

// clear a cache
$cache = & JFactory::getCache();
$cache->clean( 'com_flexicontent' );
$cache->clean( 'com_flexicontent_tmpl' );
$cache->clean( 'com_flexicontent_cats' );
$cache->clean( 'com_flexicontent_items' );

// reseting post installation session variables
$session  =& JFactory::getSession();
$session->set('flexicontent.postinstall', false);
$session->set('flexicontent.allplgpublish', false);

// fix joomla 1.5 bug
$this->parent->getDBO =& $this->parent->getDBO();

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
       	 	<span>Flexible content management system for Joomla! 1.5</span><br />
        	<font class="small">by <a href="http://www.vistamedia.fr" target="_blank">Emmanuel Danan</a><br/>
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
