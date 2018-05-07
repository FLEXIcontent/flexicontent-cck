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

class FlexicontentViewTypes extends JViewLegacy
{
	function display( $tpl = null )
	{
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

		$ctrl_task = 'items.add';
		$icon = "components/com_flexicontent/assets/images/layout_add.png";
		$btn_class = 'choose_type';

		echo '
<div id="flexicontent" style="margin:32px;" >
	<ul class="nav nav-tabs nav-stacked">
		';
		$link = "index.php?option=com_flexicontent&amp;controller=items&amp;task=".$ctrl_task."&amp;". JSession::getFormToken() ."=1";
		$_name = '- ' . JText::_("FLEXI_NO_TYPE") . ' -';
		?>
			<li>
				<a class="<?php echo $btn_class; ?>" href="<?php echo $link; ?>" target="_parent">
					<?php echo $_name; ?>
					<small class="muted">
						<?php echo JText::_('FLEXI_NEW_ITEM_FORM_NO_TYPE_DESC'); ?>
					</small>
				</a>
			</li>

		<?php
		foreach($types as $type)
		{
			$allowed = ! $type->itemscreatable || $user->authorise('core.create', 'com_flexicontent.type.' . $type->id);

			/*
			 * Creation not allowed, and item type is not visible
			 */
			if (!$allowed && $type->itemscreatable == 1)
			{
				continue;
			}

			/*
			 * Creation not allowed, but item type is visible
			 */
			elseif (!$allowed && $type->itemscreatable == 2)
			{
				$link = "javascript:;";
				?>
			<li>
				<a class="<?php echo $btn_class; ?>" href="<?php echo $link; ?>" target="_parent" style="color: gray; cursor: not-allowed;">
					<?php echo $type->name; ?>
					<small class="muted">
						<?php echo $type->description ?: JText::_('FLEXI_NO_DESCRIPTION'); ?>
					</small>
				</a>
			</li>
			<?php
			}

			/*
			 * Creation (of this item type) is allowed
			 */
			else
			{
				$link = "index.php?option=com_flexicontent&amp;controller=items&amp;task=".$ctrl_task."&amp;typeid=".$type->id."&amp;". JSession::getFormToken() ."=1";
				?>
			<li>
				<a class="<?php echo $btn_class; ?>" href="<?php echo $link; ?>" target="_parent">
					<?php echo JText::_($type->name); ?>
					<small class="muted">
						<?php echo $type->description ?: JText::_('FLEXI_NO_DESCRIPTION'); ?>
					</small>
				</a>
			</li>
			<?php
			}
		}
		echo '
	</ul>
</div>
		';
	}
}
?>
