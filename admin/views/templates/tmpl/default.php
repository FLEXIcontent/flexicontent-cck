<?php
/**
 * @version 1.5 stable $Id: default.php 1793 2013-10-20 02:22:05Z ggppdk $
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

defined('_JEXEC') or die('Restricted access');

$basetemplates = array('default', 'blog', 'faq', 'items-tabbed', 'presentation');
$ctrl_task = FLEXI_J16GE ? 'task=templates.' : 'controller=templates&task=';
$form_token = FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken();
$js = "
jQuery(document).ready(function() {
	jQuery('a.deletable-template').click(function( event ) {
		var answer = confirm('".JText::_( 'FLEXI_TEMPLATE_DELETE_CONFIRM',true )."')
		if (!answer) return;
		var el = jQuery(this);
		var tmpl_name = el.attr('id').replace('del-','');
		
		el.html('<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\">');
		jQuery.ajax({
			type: \"GET\",
			url:  \"index.php?option=com_flexicontent&".$ctrl_task."remove&format=raw&dir=\" + tmpl_name + \"&".$form_token."=1\",
			success: function(str) {
				el.parent().css('width','200px');
				el.parent().html(str);
			}
		});
	});
});
";
JFactory::getDocument()->addScriptDeclaration($js);
?>

<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table class="adminlist" cellspacing="1">
	
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th width="42"></th>
			<th class="title" align="left"><?php echo JText::_( 'FLEXI_TEMPLATE_NAME' ); ?></th>
			<th width="100"><?php echo JText::_( 'FLEXI_TEMPLATE_ITEM' ); ?></th>
			<th width="100"><?php echo JText::_( 'FLEXI_TEMPLATE_CAT' ); ?></th>
		</tr>
	</thead>

	<tbody>
		<?php
		$editlayout = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/layout_edit.png', JText::_( 'FLEXI_EDIT_LAYOUT' ) );
		$noeditlayout = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/layout_link.png', JText::_( 'FLEXI_NOEDIT_LAYOUT' ) );
		$copytmpl = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/layout_add.png', JText::_( 'FLEXI_DUPLICATE' ) );
		$deltmpl = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/layout_delete.png', JText::_( 'FLEXI_REMOVE' ) );
		$k = 0;
		$i = 1;
		foreach ($this->rows as $row) :
			$copylink 	= 'index.php?option=com_flexicontent&amp;view=templates&amp;layout=duplicate&amp;tmpl=component&amp;source='. $row->name;
			$itemlink	= 'index.php?option=com_flexicontent&amp;view=template&amp;type=items&amp;folder='.$row->name;
			$catlink	= 'index.php?option=com_flexicontent&amp;view=template&amp;type=category&amp;folder='.$row->name;
			?>
		<tr class="<?php echo "row$k"; ?>" id="<?php echo 'up-'.$row->name ?>">
			<td><?php echo $i; ?></td>
			<td align="right">
				<?php if (!in_array($row->name, $basetemplates)) :?>
					<a style="margin-right: 5px" id="<?php echo 'del-' . $row->name ?>" class="deletable-template" href="javascript:;">
						<?php echo $deltmpl; ?>
					</a>
			 	<?php endif; ?>
				<a class="modal" rel="{handler: 'iframe', size: {x: 390, y: 210}}" href="<?php echo $copylink; ?>">  <?php echo $copytmpl; ?> </a>
			</td>
			<td align="left"><?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8'); ?></td>
			<td align="center"><?php echo @$row->items ? ((isset($row->items->positions)) ? '<a href="'.$itemlink.'">'.$editlayout.'</a>' : $noeditlayout) : ''; ?></td>
			<td align="center"><?php echo @$row->category ? ((isset($row->category->positions)) ? '<a href="'.$catlink.'">'.$editlayout.'</a>' : $noeditlayout) : ''; ?></td>
		</tr>
		<?php
		$k = 1 - $k;
		$i++;
		endforeach;
		?>
	</tbody>

	</table>
	
	<div class="clear"></div>
	
	<table class="admintable" style="margin: 0 auto !important;">
		<tr>
			<td>
			<?php echo $copytmpl; ?>
			</td>
			<td>
			<?php echo JText::_( 'FLEXI_DUPLICATE_TEMPLATE' ); ?>
			</td>
			<td>
			<?php echo $editlayout; ?>
			</td>
			<td>
			<?php echo JText::_( 'FLEXI_EDIT_LAYOUT' ); ?>
			</td>
		</tr>
		<tr>
			<td>
			<?php echo $deltmpl; ?>
			</td>
			<td>
			<?php echo JText::_( 'FLEXI_REMOVE_TEMPLATE' ); ?>
			</td>
			<td>
			<?php echo $noeditlayout; ?>
			</td>
			<td>
			<?php echo JText::_( 'FLEXI_NOEDIT_LAYOUT' ); ?>
			</td>
		</tr>
	</table>

	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="templates" />
	<input type="hidden" name="view" value="templates" />
	<input type="hidden" name="task" value="" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>