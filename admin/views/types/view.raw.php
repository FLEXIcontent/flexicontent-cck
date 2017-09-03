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

jimport('legacy.view.legacy');

class FlexicontentViewTypes extends JViewLegacy{
	
	function display( $tpl = null ) {
		
		$fc_css = JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css';
		echo '
		<link rel="stylesheet" href="'.JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css?'.FLEXI_VHASH.'" />
		<link rel="stylesheet" href="'.$fc_css.'?'.FLEXI_VHASH.'" />
		<link rel="stylesheet" href="'.JUri::root(true).'/media/jui/css/bootstrap.min.css" />
		';
		
		$user = JFactory::getUser();
		
		// Get types
		$types = flexicontent_html::getTypesList( $_type_ids=false, $_check_perms = false, $_published=true);
		$types = is_array($types) ? $types : array();
		
		$ctrl_task = FLEXI_J16GE ? 'items.add' : 'add';
		$icon = "components/com_flexicontent/assets/images/layout_add.png";
		$btn_class = FLEXI_J30GE ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
		
		echo '
<div id="flexicontent">
	<table class="fc-table-list">
		<tr>
			<th>'.JText::_( 'FLEXI_SELECT_TYPE' ).'</th>
		</tr>
		<tr>
			<td>
		';
		$link = "index.php?option=com_flexicontent&amp;controller=items&amp;task=".$ctrl_task."&amp;". JSession::getFormToken() ."=1";
		$_name = '- '.JText::_("FLEXI_ANY") .' -';//.' ... '. JText::_("FLEXI_TYPE");
		?>
			<a class="<?php echo $btn_class; ?> btn-info" href="<?php echo $link; ?>" style="min-width:60px;" target="_parent">
				<!--<img style="margin-bottom:-3px;" src="<?php echo $icon; ?>" width="16" height="16" border="0" alt="<?php echo $_name; ?>" />&nbsp;-->
				<?php echo $_name; ?>
			</a>
		<?php
		
		foreach($types as $type)
		{
			$allowed = ! $type->itemscreatable || $user->authorise('core.create', 'com_flexicontent.type.' . $type->id);
			if ( !$allowed && $type->itemscreatable == 1 ) continue;
			
			$link = "index.php?option=com_flexicontent&amp;controller=items&amp;task=".$ctrl_task."&amp;typeid=".$type->id."&amp;". JSession::getFormToken() ."=1";
			
			if ( !$allowed && $type->itemscreatable == 2 ) {
				?>
				<span class="badge badge-warning">
					<!--<img style="margin-bottom:-3px;" src="<?php echo $icon; ?>" width="16" height="16" border="0" alt="<?php echo $type->name; ?>" />&nbsp;-->
					<?php echo $type->name; ?>
				</span>
				<?php
			} else {
				?>
				<a class="<?php echo $btn_class; ?> btn-success" href="<?php echo $link; ?>" target="_parent">
					<!--<img style="margin-bottom:-3px;" src="<?php echo $icon; ?>" width="16" height="16" border="0" alt="<?php echo $type->name; ?>" />&nbsp;-->
					<?php echo $type->name; ?>
				</a>
			<?php
			}
		}
		echo '
			</td>
		</tr>
	</table>
</div>
		';
	}
}
?>
