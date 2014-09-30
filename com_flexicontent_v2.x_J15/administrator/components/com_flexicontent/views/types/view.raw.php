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

jimport('joomla.application.component.view');

class FlexicontentViewTypes extends JViewLegacy{
	
	function display( $tpl = null ) {
		
		echo '<div id="flexicontent">';
		echo '<link rel="stylesheet" href="'.JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css" />';
		if      (FLEXI_J30GE) $fc_css = JURI::base().'components/com_flexicontent/assets/css/j3x.css';
		else if (FLEXI_J16GE) $fc_css = JURI::base().'components/com_flexicontent/assets/css/j25.css';
		else                  $fc_css = JURI::base().'components/com_flexicontent/assets/css/j15.css';
		echo '<link rel="stylesheet" href="'.$fc_css.'" />';
		
		$user = JFactory::getUser();
		$db = JFactory::getDBO();
		$query = 'SELECT id, name, itemscreatable'
				. ' FROM #__flexicontent_types'
				. ' WHERE published = 1'
				. ' ORDER BY name ASC'
				;
		$db->setQuery($query);
		$types = $db->loadObjectList();
		$types = is_array($types) ? $types : array();
		
		echo '<label class="flexi_label">'. JText::_( 'FLEXI_SELECT_TYPE' ).':</label><br/><br/>';

		$ctrl_task = FLEXI_J16GE ? 'items.add' : 'add';
		$icon = "components/com_flexicontent/assets/images/layout_add.png";
		
		foreach($types as $type)
		{
			if (FLEXI_J16GE)
				$allowed = ! $type->itemscreatable || $user->authorise('core.create', 'com_flexicontent.type.' . $type->id);
			else if (FLEXI_ACCESS && $user->gid < 25)
				$allowed = ! $type->itemscreatable || FAccess::checkAllContentAccess('com_content','submit','users', $user->gmid, 'type', $type->id);
			else
				$allowed = 1;
			
			if ( !$allowed && $type->itemscreatable == 1 ) continue;
			
			$link = "index.php?option=com_flexicontent&amp;controller=items&amp;task=".$ctrl_task."&amp;typeid=".$type->id."&amp;".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1";
			
			if ( !$allowed && $type->itemscreatable == 2 ) {
				?>
				<span class="fc_button">
					<img style="margin-bottom:-3px;" src="<?php echo $icon; ?>" width="16" height="16" border="0" alt="<?php echo $type->name; ?>" />&nbsp;
					<?php echo $type->name; ?>
				</span>
				<?php
			} else {
				?>
				<a class="fc_button" href="<?php echo $link; ?>" target="_parent">
					<img style="margin-bottom:-3px;" src="<?php echo $icon; ?>" width="16" height="16" border="0" alt="<?php echo $type->name; ?>" />&nbsp;
					<?php echo $type->name; ?>
				</a>
			<?php
			}
		}
		
		$link = "index.php?option=com_flexicontent&amp;controller=items&amp;task=".$ctrl_task."&amp;".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1";
		$_name = JText::_("FLEXI_ANY") .' ... '. JText::_("FLEXI_TYPE");
		?>
			<div class="fcclear"></div>
			<br/>
			<a class="fc_button fcsimple" href="<?php echo $link; ?>" target="_parent">
				<img style="margin-bottom:-3px;" src="<?php echo $icon; ?>" width="16" height="16" border="0" alt="<?php echo $_name; ?>" />&nbsp;
				<?php echo $_name; ?>
			</a>
		</div>
		<?php
	}
}
?>
