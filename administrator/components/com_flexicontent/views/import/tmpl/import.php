<?php
/**
 * @version 1.5 stable $Id$
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

$params = $this->cparams;
?>
<script type="text/javascript">
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

	radio_ischecked = 0;
	for (i = 0; i < document.getElementsByName('language').length; i++) {
		if (document.getElementsByName('language')[i].checked) {
			radio_ischecked = 1;
		}
	}
	if( !radio_ischecked ) {
		alert("Please select item language.");
		return;
	}

	radio_ischecked = 0;
	for (i = 0; i < document.getElementsByName('state').length; i++) {
		if (document.getElementsByName('state')[i].checked) {
			radio_ischecked = 1;
		}
	}
	if( !radio_ischecked ) {
		alert("Please select item state.");
		return;
	}

	if( $("maincat").value.length<=0 && !$("maincat_col").checked ) {
		alert("Please select a primary category or select to use 'catid' column");
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
<div class="flexicontent" id="flexicontent">

<form action="index.php" method="post" enctype="multipart/form-data" name="adminForm" id="adminForm">
	<table cellspacing="10" cellpadding="0" border="0" width="100%">
		<tr>
			<td value="top" colspan="2">
			<div style="width:95%; text-align:right; clear:both; float:right;">
				<a href="#tools_3rd_party" ><?php echo JText::_( 'FLEXI_3RD_PARTY_DEV_IMPORT_EXPORT_TOOLS' ); ?></a>
			</div>
			<fieldset>
				<legend><?php echo JText::_( 'FLEXI_IMPORT_TYPE_AND_CORE_PROPS' ); ?></legend>
				<table>
					<tr valign="top">
						<td class="key"><label class="fckey" for="type_id"><?php echo JText::_("FLEXI_ITEM_TYPE");?><span style="color:red;"> *</span></label></td>
						<td class="fcimportdata">
							<?php echo $this->lists['type_id'];?>
						</td>
					</tr>
					<tr valign="top">
						<td class="key"><label class="fckey"><?php echo JText::_("FLEXI_IMPORT_IGNORE_UNUSED_COLUMNS");?></label></td>
						<td class="fcimportdata">
							<input type="checkbox" id="ignore_unused_columns" name="ignore_unused_columns" value="1" />
							<label for="ignore_unused_columns">
								<?php echo JText::_( 'FLEXI_IMPORT_IGNORE_REDUDANT_COLS' ); ?>
							</label>
						</td>
					</tr>

					<tr valign="top">
						<td class="key"> &nbsp; </td>
						<td class="fcimportdata">-------------------------------</td>
					</tr>
					<tr valign="top">
						<td class="key"> &nbsp; </td>
						<td class="fcimportdata"><span style="color:darkgreen; font-weight:bold;"><?php echo JText::_("FLEXI_IMPORT_CUSTOM_COLS");?></span></td>
					</tr>
					<tr valign="top">
						<td class="key"> &nbsp; </td>
						<td class="fcimportdata">-------------------------------</td>
					</tr>

					<tr valign="top">
						<td class="key"><label class="fckey"><?php echo JText::_("FLEXI_IMPORT_CUSTOM_ITEM_ID");?></label></td>
						<td class="fcimportdata">
							(<?php echo JText::_("FLEXI_IMPORT_ALL_IDS_CHECKED_BEFORE_IMPORT");?>) <br/>
							<input type="checkbox" id="id_col" name="id_col" value="1" />
							<label for="id_col"><?php echo JText::_("FLEXI_IMPORT_USE_ID_COL");?></label>
						</td>
					</tr>
					<tr valign="top">
						<td class="key"><label class="fckey" for="language"><?php echo JText::_("FLEXI_LANGUAGE");?><span style="color:red;"> *</span></label></td>
						<td class="fcimportdata">
							<?php echo str_replace('<br />', '', $this->lists['languages']); ?>
						</td>
					</tr>
					<tr valign="top">
						<td class="key"><label class="fckey" for="created_col"><?php echo JText::_("FLEXI_STATE");?></label></td>
						<td class="fcimportdata">
							<?php echo str_replace('<br />', '', $this->lists['states']); ?>
						</td>
					</tr>
					<tr valign="top">
						<td class="key"><label class="fckey" for="created_col"><?php echo JText::_("FLEXI_CREATION_DATE");?></label></td>
						<td class="fcimportdata">
							<input type="radio" id="created_col0" name="created_col" value="0" checked="checked" /> <label for="created_col0" class="fcdata">a. <?php echo JText::_("FLEXI_IMPORT_CREATION_CURR_DATE");?></label>
							<input type="radio" id="created_col1" name="created_col" value="1" /> <label for="created_col1" class="fcdata">b. <?php echo JText::_("FLEXI_IMPORT_CREATION_USE_COL");?></label>
						</td>
					</tr>
					<tr valign="top">
						<td class="key"><label class="fckey" for="created_by_col"><?php echo JText::_("FLEXI_CREATOR_AUTHOR");?></label></td>
						<td class="fcimportdata">
							<input type="radio" id="created_by_col0" name="created_by_col" value="0" checked="checked" /> <label for="created_by_col0" class="fcdata">a. <?php echo JText::_("FLEXI_IMPORT_CREATOR_CURR_USER");?></label>
							<input type="radio" id="created_by_col1" name="created_by_col" value="1" /> <label for="created_by_col1" class="fcdata">b. <?php echo JText::_("FLEXI_IMPORT_CREATOR_USE_COL");?></label>
						</td>
					</tr>
					<tr valign="top">
						<td class="key"><label class="fckey"><?php echo JText::_("FLEXI_META_DATA");?></label></td>
						<td class="fcimportdata">
							<input type="checkbox" id="metadesc_col" name="metadesc_col" value="1" /> <label for="metadesc_col" class="fcdata"><?php echo JText::_("FLEXI_IMPORT_USE_METADESC_COL");?></label>
							<input type="checkbox" id="metakey_col" name="metakey_col" value="1" /> <label for="metakey_col" class="fcdata"><?php echo JText::_("FLEXI_IMPORT_USE_METAKEY_COL");?></label>
						</td>
					<tr valign="top">
						<td class="key"><label class="fckey"><?php echo JText::_("FLEXI_PUBLICATION_DATES");?></label></td>
						<td class="fcimportdata">
							(<?php echo JText::_("FLEXI_IMPORT_ENTER_VALID_DATES");?>) <br/>
							<input type="checkbox" id="publish_up_col" name="publish_up_col" value="1" /> <label for="publish_up_col" class="fcdata"><?php echo JText::_("FLEXI_IMPORT_USE_PUBLISH_UP_COL");?></label>
							<input type="checkbox" id="publish_down_col" name="publish_down_col" value="1" /> <label for="publish_down_col" class="fcdata"><?php echo JText::_("FLEXI_IMPORT_USE_PUBLISH_DOWN_COL");?></label>
						</td>
					</tr>
					<tr valign="top">
						<td class="key"><label class="fckey"><?php echo JText::_("FLEXI_TAGS");?></label></td>
						<td class="fcimportdata">
							(<?php echo JText::_("FLEXI_IMPORT_TAGS_WILL_BE_CREATED_BEFORE_IMPORT");?>) <br/>
							<input type="radio" id="tags_col0" name="tags_col" value="0" checked="checked" /> <label for="tags_col0" class="fcdata">a. <?php echo JText::_("FLEXI_IMPORT_DO_NOT_IMPORT_TAGS");?></label>
							<input type="radio" id="tags_col1" name="tags_col" value="1" /> <label for="tags_col1" class="fcdata">b. <?php echo JText::_("FLEXI_IMPORT_USE_TAG_NAMES_COL");?></label>
							<input type="radio" id="tags_col2" name="tags_col" value="2" /> <label for="tags_col2" class="fcdata">c. <?php echo JText::_("FLEXI_IMPORT_USE_TAG_IDS_COL");?></label>
						</td>
					</tr>
				</table>
			</fieldset>
			</td>
		</tr>
		<tr>
			<td valign="top" colspan="2">
				<fieldset style="min-height:220px;">
					<legend><?php echo JText::_( 'FLEXI_IMPORT_CATS' ); ?></legend>
					<table>
						<tr valign="top">
							<td class="key"><label class="fckey" for="maincat"><?php echo JText::_( 'FLEXI_PRIMARY_CATEGORY' ); ?></label></td>
							<td class="fcimportdata"><?php echo $this->lists['maincat']; ?></td>
							<td class="fcimportdata">&nbsp;</td>
							<td class="key"><label class="fckey" for="seccats"><?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ); ?></label></td>
							<td class="fcimportdata"><?php echo $this->lists['seccats']; ?></td>
						</tr>
						<tr valign="top">
							<td class="key" align="left">
								<label class="fckey" for="maincat_col" style="clear:both;"><?php echo JText::_( 'FLEXI_IMPORT_FILE_OVERRIDE' ); ?> <?php echo JText::_( 'FLEXI_PRIMARY_CATEGORY' ); ?></label>
							</td>
							<td class="fcimportdata">
								<input type="checkbox" id="maincat_col" name="maincat_col" value="1" /> <label for="maincat_col" class="fcdata">(Use 'catid' column, e.g. 54)</label>
							</td>
							<td class="fcimportdata">&nbsp;</td>
							<td class="key" align="left">
								<label class="fckey" for="seccats_col" style="clear:both;"><?php echo JText::_( 'FLEXI_IMPORT_FILE_OVERRIDE' ); ?> <?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ); ?></label>
							</td>
							</td>
							<td class="fcimportdata">
								<input type="checkbox" id="seccats_col" name="seccats_col" value="1" /> <label for="seccats_col" class="fcdata">(Use 'cid' column, e.g. 54,14,51)</label>
							</td>
						</tr>
					</table>
				</fieldset>
			</td>
		</tr>
		<tr>
			<td valign="top" colspan="2" >
			<fieldset>
				<legend><?php echo JText::_( 'FLEXI_IMPORT_CSV_FILE_AND_FORMAT' ); ?></legend>
				<table>
					<tr>
						<td class="key">
							<label class="fckey" for="field_separator"><?php echo JText::_( 'FLEXI_CSV_FIELD_SEPARATOR' ); ?>
							<span style="color:red;"> *</span>
							</label>
						</td>
						<td class="fcimportdata">
							<input type="text" name="field_separator" id="field_separator" value="<?php echo $params->get('csv_field_sep','~~'); ?>" class="fcfield_textval" /> &nbsp;
							<span style='font-weight:bold; color:green'><?php echo JText::_( 'FLEXI_CSV_FIELD_SEPARATOR_DESC' ); ?></span>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label class="fckey" for="enclosure_char"><?php echo JText::_( 'FLEXI_CSV_FIELD_ENCLOSE_CHAR' ); ?>
							</label>
						</td>
						<td class="fcimportdata">
							<input type="text" name="enclosure_char" id="enclosure_char" value="<?php echo $params->get('csv_field_enclose_char',''); ?>" class="fcfield_textval" /> &nbsp;
							<span style='font-weight:bold; color:green'><?php echo JText::_( 'FLEXI_CSV_FIELD_ENCLOSE_CHAR_DESC' ); ?></span>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label class="fckey" for="record_separator"><?php echo JText::_( 'FLEXI_CSV_ITEM_SEPARATOR' ); ?>
							<span style="color:red;"> *</span>
							</label>
						</td>
						<td class="fcimportdata">
							<input type="text" name="record_separator" id="record_separator" value="<?php echo $params->get('csv_item_record_sep','\n~~'); ?>" class="fcfield_textval" /> &nbsp;
							<span style='font-weight:bold; color:green'><?php echo JText::_( 'FLEXI_CSV_ITEM_SEPARATOR_DESC' ); ?></span>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label class="fckey" for="csvfile"><span style="color:red; font-weight:bold;"><?php echo JText::_( 'FLEXI_CSV_DEBUG_FIRST_RECORDS' ); ?></span></label>
						</td>
						<td class="fcimportdata">
							<input type="text" name="debug" id="debug" value="<?php echo $params->get('csv_debug_records','2'); ?>" class="fcfield_textval" /> &nbsp;
							<span style='font-weight:bold; color:green'><?php echo JText::_( 'FLEXI_CSV_DEBUG_FIRST_RECORDS_DESC' ); ?></span>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label class="fckey" for="csvfile"><span style="font-weight:bold;"><?php echo JText::_( 'FLEXI_CSVFILE' ); ?></span>
							<span style="color:red;"> *</span>
							</label>
						</td>
						<td class="fcimportdata">
							<input type="file" name="csvfile" id="csvfile" value="" class="" />
						</td>
					</tr>
				</table>
			</fieldset>
			</td>
		</tr>

		<tr>
			<td valign="top" colspan="2">
			<fieldset>
				<legend style='color: darkgreen;'><?php echo JText::_( 'FLEXI_IMPORT_CSV_FILE_FORMAT_EXPLANATION' ); ?></legend>
<b>1. First line</b> of the CSV file: &nbsp; &nbsp; must contain the <b>field names</b> <u>(and not the field labels!)</u><br/><br/>
<b>2. Field separator</b> of the CSV file: &nbsp; &nbsp; separate fields with a string<b>, that does not appear inside the data</b>, e.g. &nbsp; ~~<br/><br/>
<b>3. Item separator</b> of the CSV file: &nbsp; &nbsp; separate items with a string<b>, that does not appear inside the data</b>, e.g. &nbsp; \n~~<br/><br/>
<b>4. Supported fields:</b><br/><br/>
-- a. <b>basic fields</b> fields: title, description, text fields, <br/><br/>
-- b. <b>single-value</b> fields: select, radio, radioimage, <br/><br/>
-- c. <b>multi-value</b> fields: selectmultiple, selectmultple, checkbox, checkboximage, email, weblink, <b>separate multiple values with %%</b>, <br/><br/>
-- d. <b>multi-property per value</b> fields, e.g. email fields & weblink fields: write email/weblink property only e.g. <b>usera@somedomain.com</b>, OR write multple properties like <b>[-propertyname-]=propertyvalue</b>, and <b>separate mutliple properties with !!</b></><br/>
<br/>
<u>CSV example Format:</u> <br/>
<small style='font-size:10px; font-familiy:tahoma;'>
title ~~ text ~~ textfield3 ~~ emailfield6 ~~ weblinkfld8 ~~ single_value_field22 ~~ multi_value_field24 <br/>
~~ title 1 ~~ description 1 ~~ textfield3 value ~~ [-addr-]=usera@somedomain.com!![-text-]=usera ~~ www.somedomaina.com ~~ f22_valuea ~~ f24_value01%%f24_value02%%f24_value03 <br/>
~~ title 2 ~~ description 2 ~~ textfield3 value ~~ [-addr-]=userb@somedomain.com!![-text-]=userb ~~ www.somedomainb.com ~~ f22_valuea ~~ f24_value04%%f24_value05%%f24_value06 <br/>
~~ title 3 ~~ description 3 ~~ textfield3 value ~~ [-addr-]=userc@somedomain.com!![-text-]=userc ~~ www.somedomainc.com ~~ f22_valuea ~~ f24_value07%%f24_value08%%f24_value09 <br/>
~~ title 4 ~~ description 4 ~~ textfield3 value ~~ userd@somedomain.com ~~ [-link-]=www.somedomaind.com!![-title-]=somedomainD ~~ f22_valuea ~~ f24_value10%%f24_value11%%f24_value12 <br/>
~~ title 5 ~~ description 5 ~~ textfield3 value ~~ usere@somedomain.com ~~ [-link-]=www.somedomaine.com!![-title-]=somedomainE ~~ f22_valuea ~~ f24_value13%%f24_value14%%f24_value15 <br/>
</small>
<br/>
			</fieldset>
			</td>
		</tr>
		<tr>
			<td value="top" width="">
				<fieldset class="fleximport" style="min-height:220px; background-color:white;">
					<legend style="color:darkred; background-color:white;" ><?php echo JText::_( 'FLEXI_3RD_PARTY_DEV_IMPORT_EXPORT_TOOLS' ); ?></legend>
					<a id="tools_3rd_party"></a>
					<?php echo $this->fleximport; ?>
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
<style>
#adminForm table fieldset.fleximport ul {
	padding: 0px 0px 0px 24px;
	margin: 12px 0px 12px 0px;
}
#adminForm table fieldset.fleximport li {
	list-style: disc inside none !important;
	margin: 0 !important;
	padding: 1px 0 0 8px !important;
}
</style>