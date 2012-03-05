<?php
/**
 * @version 1.5 stable $Id: copy.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
?>
<script>
function submitbutton(task) {
	if(task=='cancel') {
		submitform(task);
		return;
	}
	if(!$("type_id").value) {
		alert("Please select item type.");
		$("type_id").focus();
		return;
	}
	var isselected = false;
	for(i=0;i<$('seccats').length;i++) {
		if($('seccats')[i].selected) {
			isselected = true;
			break;
		}
	};
	if(($("maincat").value.length<=0) && !isselected) {
		alert("Please select primary category, or secondary at lease one category.");
		$("maincat").focus();
		return;
	}
	if($("csvfile").value=="") {
		alert("Please select your csv file that you want to import.");
		$("csvfile").focus();
		return;
	}
	submitform(task);
}
</script>
<div class="flexicontent">
<form action="index.php" method="post" enctype="multipart/form-data" name="adminForm" id="adminForm">
	<table cellspacing="10" cellpadding="0" border="0" width="100%">
		<tr>
			<td value="top" colspan="2">
			<fieldset>
			<legend><?php echo JText::_( '1°- Choose the type of the items you wish to import' ); ?></legend>
				<label class="fcimport" for="type_id">
					<?php echo JText::_("FLEXI_ITEM_TYPE");?><span style="color:red;"> *</span>
				</label>
				<?php echo $this->lists['type_id'];?>
			</fieldset>
			</td>
		</tr>
		<tr>
			<td valign="top" width="50%">
			<fieldset>
			<legend><?php echo JText::_( '2°- Choose the category(ies) you wish to import the items in' ); ?></legend>
				<table>
					<tr valign="top">
						<td class="key"><label class="fcimport" for="maincat"><?php echo JText::_( 'FLEXI_PRIMARY_CATEGORY' ); ?></label></td>
						<td><?php echo $this->lists['maincat']; ?></td>
						<td>&nbsp;</td>
						<td class="key"><label class="fcimport" for="seccat"><?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ); ?></label></td>
						<td><?php echo $this->lists['seccats']; ?></td>
					</tr>
				</table>
			</fieldset>
			</td>
		</tr>
		<tr>
			<td valign="top">
			<fieldset>
			<legend><?php // echo JText::_( 'FLEXI_IMPORT_FILE' ); ?><?php echo JText::_( '3°- Upload your csv file' ); ?></legend>
				<table>
					<tr>
						<td class="key">
							<label class="fcimport" for="csvfile"><?php echo JText::_( 'FLEXI_CSVFILE' ); ?>
							<span style="color:red;"> *</span>
							</label>
						</td>
						<td>
							<input type="file" name="csvfile" id="csvfile" value="" />
						</td>
					</tr>
				</table>
			</fieldset>
			</td>
		</tr>
	</table>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="items" />
	<input type="hidden" name="view" value="items" />
	<input type="hidden" name="task" value="" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>
</div>