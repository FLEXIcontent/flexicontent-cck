<?php
/**
 * @version 1.5 stable $Id: import.php 1193 2012-03-14 09:20:15Z emmanuel.danan@gmail.com $
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

$css = 'td label.fcimport { display:inline-block; float:right; white-space:nowrap; width:auto; font-weight:bold; }';
$document	= & JFactory::getDocument();
$document->addStyleDeclaration($css);

?>
<script>
<?php if (FLEXI_J16GE) echo "Joomla.submitform = "; ?>

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
	if( $("maincat").value.length<=0 && !$("maincat_col").checked ) {
		alert("Please select primary category");
		$("maincat").focus();
		return;
	}
	if($("field_separator").value=="") {
		alert("Please select your FLEXIcontent field separator string e.g ~~");
		$("csvfile").focus();
		return;
	}
	if($("record_separator").value=="") {
		alert("Please select your FLEXIcontent item separator string e.g \n~~");
		$("csvfile").focus();
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
				<legend><?php echo JText::_( 'FLEXI_IMPORT_TYPE' ); ?></legend>
				<table>
					<tr valign="top">
						<td class="key"><label class="fcimport" for="type_id"><?php echo JText::_("FLEXI_ITEM_TYPE");?><span style="color:red;"> *</span></label></td>
						<td><?php echo $this->lists['type_id'];?></td>
						<td>&nbsp;</td>
					</tr>
					<tr valign="top">
						<td class="key"><label class="fcimport" for="language"><?php echo JText::_("FLEXI_LANGUAGE");?><span style="color:red;"> *</span></label></td>
						<td><?php echo $this->lists['languages'];?></td>
						<td><span style='font-weight:bold; color:green'> Keep source language means that your data will include a column with named 'language', with language codes e.g. en-GB, fr-FR, etc</span></td>
					</tr>
				</table>
			</fieldset>
			</td>
		</tr>
		<tr>
			<td valign="top" width="50%">
			<fieldset>
				<legend><?php echo JText::_( 'FLEXI_IMPORT_CATS' ); ?></legend>
				<table>
					<tr valign="top">
						<td class="key"><label class="fcimport" for="maincat"><?php echo JText::_( 'FLEXI_PRIMARY_CATEGORY' ); ?></label></td>
						<td><?php echo $this->lists['maincat']; ?></td>
						<td>&nbsp;</td>
						<td class="key"><label class="fcimport" for="seccats"><?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ); ?></label></td>
						<td><?php echo $this->lists['seccats']; ?></td>
					</tr>
					<tr valign="top">
						<td class="key"><label class="fcimport" for="maincat_col">File override <?php echo JText::_( 'FLEXI_PRIMARY_CATEGORY' ); ?></label><br />(Use 'catid' column, e.g. 54)</label></td>
						<td><input type="checkbox" id="maincat_col" name="maincat_col" value="1" /></td>
						<td>&nbsp;</td>
						<td class="key"><label class="fcimport" for="seccats_col">File override <?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ); ?></label><br />(Use 'cid' column, e.g. 54,14,51)</td>
						<td><input type="checkbox" id="seccats_col" name="seccats_col" value="1" /></td>
					</tr>
				</table>
			</fieldset>
			</td>
		</tr>
		<tr>
			<td valign="top">
			<fieldset>
				<legend><?php // echo JText::_( 'FLEXI_IMPORT_FILE' ); ?><?php echo JText::_( 'FLEXI_IMPORT_CSV' ); ?></legend>
				<table>
					<tr>
						<td class="key">
							<label class="fcimport" for="field_separator"><?php echo JText::_( 'FLEXI_CSV_FIELD_SEPARATOR' ); ?>
							<span style="color:red;"> *</span>
							</label>
						</td>
						<td>
							<input type="text" name="field_separator" id="field_separator" value="~~" /> &nbsp; <span style='font-weight:bold; color:green'>MULTIPLE characters recommended e.g : ~~ &nbsp; for tab enter: \t</span>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label class="fcimport" for="enclosure_char"><?php echo JText::_( 'FLEXI_CSV_FIELD_ENCLOSURER_CHAR' ); ?>
							</label>
						</td>
						<td>
							<input type="text" name="enclosure_char" id="enclosure_char" value='' /> &nbsp; <span style='font-weight:bold; color:green'>Use <u>ONLY IF</u> your data have one, e.g for double quote enter: "</span>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label class="fcimport" for="record_separator"><?php echo JText::_( 'FLEXI_CSV_ITEM_SEPARATOR' ); ?>
							<span style="color:red;"> *</span>
							</label>
						</td>
						<td>
							<input type="text" name="record_separator" id="record_separator" value="\n~~" /> &nbsp; <span style='font-weight:bold; color:green'>MULTIPLE characters recommended e.g : \n~~ &nbsp; For new line enter: \n </span>
						</td>
					</tr>
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
					<tr>
						<td class="key">
							<label class="fcimport" for="csvfile"><span style="color:red;">Debug first records</span>
							</label>
						</td>
						<td>
							<input type="text" name="debug" id="debug" value="0" /> &nbsp; <span style='font-weight:bold; color:green'> Leave zero for no debugging, print the first nn records (items), without trying to insert any data </span>
						</td>
					</tr>
				</table>
			</fieldset>
			</td>
		</tr>

		<tr>
			<td valign="top">
			<fieldset>
				<legend style='color: darkgreen;'><?php echo JText::_( 'FLEXI_CSV_IMPORT_FILE_FORMAT' ); ?></legend>
<b>1. First line</b> of the CSV file: &nbsp; &nbsp; must contain the <b>field names</b> <u>(and not the field labels!)</u><br/><br/>
<b>2. Field separator</b> of the CSV file: &nbsp; &nbsp; separate fields with a string<b>, that does not appear inside the data</b>, e.g. &nbsp; ~~<br/><br/>
<b>3. Item separator</b> of the CSV file: &nbsp; &nbsp; separate items with a string<b>, that does not appear inside the data</b>, e.g. &nbsp; \n~~<br/><br/>
<b>4. Supported fields:</b><br/><br/>
-- a. <b>basic fields</b> fields: title, description, text fields, <br/><br/>
-- b. <b>single-value</b> fields: select, radio, radioimage, <br/><br/>
-- c. <b>multi-value</b> fields: selectmultiple, selectmultple, checkbox, checkboximage, email, weblink, <b>separate multiple values with %%</b>, <br/><br/>
-- d. <b>multi-property per value</b> fields, e.g. email fields & weblink fields: write email/weblink property only e.g. <b>usera@somedomain.com</b>, OR write multple properties like <b>[-propertyname-]=propertyvalue</b>, and <b>separate mutliple properties with !!</b></><br/>
<br/>
CSV example Format:
<?php echo "<pre style='font-size:11px;'>"; ?>
title ~~ text ~~ textfield3 ~~ emailfield6 ~~ weblinkfld8 ~~ single_value_field22 ~~ multi_value_field24
~~ title 1 ~~ description 1 ~~ textfield3 value ~~ [-email-]=usera@somedomain.com!![-text-]=usera ~~ www.somedomaina.com ~~ f22_valuea ~~ f24_value01%%f24_value02%%f24_value03
~~ title 2 ~~ description 2 ~~ textfield3 value ~~ [-email-]=userb@somedomain.com!![-text-]=userb ~~ www.somedomainb.com ~~ f22_valuea ~~ f24_value04%%f24_value05%%f24_value06
~~ title 3 ~~ description 3 ~~ textfield3 value ~~ [-email-]=userc@somedomain.com!![-text-]=userc ~~ www.somedomainc.com ~~ f22_valuea ~~ f24_value07%%f24_value08%%f24_value09
~~ title 4 ~~ description 4 ~~ textfield3 value ~~ userd@somedomain.com ~~ [-addr-]=www.somedomaind.com!![-text-]=somedomainD ~~ f22_valuea ~~ f24_value10%%f24_value11%%f24_value12
~~ title 5 ~~ description 5 ~~ textfield3 value ~~ usere@somedomain.com ~~ [-addr-]=www.somedomaine.com!![-text-]=somedomainE ~~ f22_valuea ~~ f24_value13%%f24_value14%%f24_value15
<?php echo "</pre>"; ?>
<br/>
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