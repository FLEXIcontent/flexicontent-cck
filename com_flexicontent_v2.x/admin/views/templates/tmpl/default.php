<?php
/**
 * @version 1.5 stable $Id: default.php 1746 2013-09-01 21:44:12Z ggppdk $
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
		$form_token = FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken();
		foreach ($this->rows as $row) {
			$copylink 	= 'index.php?option=com_flexicontent&amp;view=templates&amp;layout=duplicate&amp;tmpl=component&amp;source='. $row->name;
			$itemlink	= 'index.php?option=com_flexicontent&amp;view=template&amp;type=items&amp;folder='.$row->name;
			$catlink	= 'index.php?option=com_flexicontent&amp;view=template&amp;type=category&amp;folder='.$row->name;
			if (!in_array($row->name, $basetemplates)) {
				$dellink 	= '#';
		?>
		<script type="text/javascript">
			jQuery(document).ready(function() {
				var tmpl_el = jQuery('#<?php echo 'del-'.$row->name ?>');
				tmpl_el.click(function( event ) {
					var answer = confirm('<?php echo JText::_( 'FLEXI_TEMPLATE_DELETE_CONFIRM',true ); ?>')
					if (!answer) return;
					
					tmpl_el.html('<td colspan="5" align="center"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></td>');
					jQuery.ajax({
						type: "GET",
						url:  "index.php?option=com_flexicontent&<?php echo $ctrl_task;?>remove&format=raw&dir=<?php echo $row->name ?>&<?php echo $form_token; ?>=1",
						success: function(str) {
							tmpl_el.html(str);
						}
					});
				});
			});
		</script>
		<?php }	?>
		<tr class="<?php echo "row$k"; ?>" id="<?php echo 'up-'.$row->name ?>">
			<td><?php echo $i; ?></td>
			<td align="right"><span style="padding-right: 5px">
			<a id="<?php echo 'del-' . $row->name ?>" href="<?php echo @$dellink; ?>"><?php if (!in_array($row->name, $basetemplates)) echo $deltmpl; ?></a></span><a class="modal" rel="{handler: 'iframe', size: {x: 390, y: 210}}" href="<?php echo $copylink; ?>"><?php echo $copytmpl; ?></a></span>
			<td align="left">
			<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8'); ?>
			</td>
			<td align="center"><?php echo @$row->items ? ((isset($row->items->positions)) ? '<a href="'.$itemlink.'">'.$editlayout.'</a>' : $noeditlayout) : ''; ?></td>
			<td align="center"><?php echo @$row->category ? ((isset($row->category->positions)) ? '<a href="'.$catlink.'">'.$editlayout.'</a>' : $noeditlayout) : ''; ?></td>
		</tr>
		<?php
		$k = 1 - $k;
		$i++;
		} ?>
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