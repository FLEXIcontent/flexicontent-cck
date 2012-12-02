<?php
/**
 * @version 1.5 stable $Id: items.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.application.component.view');

class FlexicontentViewTypes extends JViewLegacy{
	
	function display( $tpl = null ) {
		
		echo '<link rel="stylesheet" href="'.JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css">';
		
		$db = &JFactory::getDBO();
		$query = "SELECT id,name FROM #__flexicontent_types WHERE published='1' ORDER BY name ASC;";
		$db->setQuery($query);
		$itemtypes = $db->loadObjectList();
		$itemtypes = is_array($itemtypes) ? $itemtypes : array();
		
		echo "<b>". JText::_( 'FLEXI_SELECT_TYPE' ).":</b><br /><br />";

		$ctrl_task = FLEXI_J16GE ? 'items.add' : 'add';
		foreach($itemtypes as $itemtype) {
			$css = "width:auto; margin:0px 1% 12px 1%; padding:1%; ";
			$link = "index.php?option=com_flexicontent&amp;controller=items&amp;task=".$ctrl_task."&amp;typeid=".$itemtype->id."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1";
			$icon = "components/com_flexicontent/assets/images/layout_add.png";
	?>
			<a style="<?php echo $css; ?>" class="fc_select_button" href="<?php echo $link; ?>" target="_parent">
				<img style="margin-bottom:-3px;" src="<?php echo $icon; ?>" width="16" height="16" border="0" alt="<?php echo $itemtype->name; ?>" />&nbsp;
				<?php echo $itemtype->name; ?>
			</a>
	<?php
		}
	}
}
?>
