<?php
/**
 * @version 1.5 stable $Id: import.php 1883 2014-04-09 17:49:21Z ggppdk $
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
$document	= JFactory::getDocument();
$cparams  = JComponentHelper::getParams('com_flexicontent');

if (FLEXI_J40GE) JToolbarHelper::inlinehelp();

// For tabsets/tabs ids (focusing, etc)
$tabSetCnt = -1;
$tabSetMax = -1;
$tabCnt = array();
$tabSetStack = array();

// Load JS tabber lib
$this->document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
$this->document->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
?>


<div id="flexicontent" class="flexicontent fcconfig-form">

<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal" enctype="multipart/form-data" >


<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>">

<?php if (!empty( $this->sidebar) && FLEXI_J40GE == false) : ?>

	<div id="j-sidebar-container" class="span2 col-md-2">

		<?php echo str_replace('type="button"', '', $this->sidebar); ?>

	</div>
	
	<div id="j-main-container" class="span10 col-md-10">

	<?php else : ?>

		<div id="j-main-container" class="span12 col-md-12">

<?php endif;?>


<?php
array_push($tabSetStack, $tabSetCnt);
$tabSetCnt = ++$tabSetMax;
$tabCnt[$tabSetCnt] = 0;
?>


<!-- tabber start -->
<div class="fctabber fields_tabset" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-home-2">
		<h3 class="tabberheading"><?php echo JText::_("FLEXI_BASIC");?></h3>
		
		<br/>
		<table class="fc-form-tbl align-top">
			
			<tr>
				<td class="key"><label class="label">Id</label></td>
				<td class="data" style="width: 300px;">
					<?php
						$dv = $this->model->getState('id_col');
					?>
					<div class="group-fcset fc_input_set fc-cleared">
						<input type="radio" id="id_col0" name="id_col" value="0" <?php echo $dv==0 ? 'checked="checked"' : ''; ?> />
						<label for="id_col0"><?php echo JText::_("FLEXI_IMPORT_AUTO_NEW_ID");?> - <?php echo JText::_("FLEXI_IMPORT_CREATE_ITEMS");?></label> <br/>

						<div style="border: 1px dashed; padding: 12px">
							<b><?php echo JText::_("FLEXI_IMPORT_USE_ID_COL");?></b><br/>
							<input type="radio" id="id_col1" name="id_col" value="1" <?php echo $dv==1 ? 'checked="checked"' : ''; ?> />
							<label for="id_col1"><?php echo JText::_("FLEXI_IMPORT_CREATE_ITEMS");?></label> <br/>
		
							<input type="radio" id="id_col2" name="id_col" value="2" <?php echo $dv==2 ? 'checked="checked"' : ''; ?> />
							<label for="id_col2"><?php echo JText::_("FLEXI_IMPORT_CREATE_UPDATE_ITEMS");?></label> <br/>
		
							<input type="radio" id="id_col3" name="id_col" value="3" <?php echo $dv==3 ? 'checked="checked"' : ''; ?> />
							<label for="id_col3"><?php echo JText::_("FLEXI_IMPORT_UPDATE_ITEMS");?></label>
						</div>
					</div>
				</td>
				<td class="data">
					<span class="fc-mssg fc-info fc-nobgimage"><?php echo JText::_("FLEXI_IMPORT_ALL_IDS_CHECKED_BEFORE_IMPORT");?></span>
				</td>
			</tr>

			<tr>
				<td class="key"><label class="label" id="type_id-lbl" for="type_id"><?php echo JText::_("FLEXI_ITEM_TYPE");?></label></td>
				<td class="data" colspan="2">
					<?php echo $this->lists['type_id'];?>
					<span class="fc-mssg-inline fc-info fc-nobgimage"><?php echo JText::_("Used by NEW items only");?></span>
					<span class="fc-mssg-inline fc-warning fc-nobgimage"><?php echo JText::_("For existing items. It is only used to identify field names in column header row. Type will not be modified.");?></span>
				</td>
			</tr>

			<tr>
				<td colspan="3">
					<div class="fcsep_level2"><?php echo JText::_('Defaults'); ?></div>
					<div class="alert alert-info fc-iblock">
						<?php echo JText::_("- <b>'Use column'</b> effects both NEW / Existing items. <br> - <b>Specific</b> value effects <b>only NEW</b> items");?>
					</div>
				</td>
			</tr>

			<tr>
				<td class="key"><label class="label"><?php echo JText::_("FLEXI_LANGUAGE");?></label></td>
				<td class="data" colspan="2">
					<?php echo str_replace('<br />', '', $this->lists['languages']); ?>
				</td>
			</tr>
			
			<tr>
				<td class="key"><label class="label"><?php echo JText::_("FLEXI_STATE");?></label></td>
				<td class="data" colspan="2">
					<?php echo str_replace('<br />', '', $this->lists['states']); ?>
				</td>
			</tr>
			
			<tr>
				<td class="key"><label class="label" id="access-lbl" for="access"><?php echo JText::_("FLEXI_ACCESS_LEVEL");?></label></td>
				<td class="data" colspan="2">
					<?php echo str_replace('<br />', '', $this->lists['access']); ?>
				</td>
			</tr>
			
		</table>

		<br/><br/>
		<table style="border-collapse: collapse; border: 0; border-spacing: 0;">
			<tr>
				<td style="vertical-align:top; font-family:tahoma; font-size:12px;">
					
					<fieldset>
						<legend style="color: darkgreen;"><?php echo JText::_( 'FLEXI_IMPORT_CSV_FILE_EXAMPLE' ); ?></legend>
						<div class="alert alert-info">BASIC format, 1st row consists of <br/> - either CUSTOM field names &nbsp; (e.g. mygallery) <br/> - or CORE properties names &nbsp; (e.g. catid)</div>
						<span class="fcimport_sampleline">title ~~ text ~~ catid ~~ textfield3 ~~ emailfield6 ~~ weblinkfld8 </span><br/>
						<span class="fcimport_sampleline">~~ title 4 ~~ description 4 ~~ 31 ~~ textfield3 value ~~ userd@somedomain.com ~~ www.somedomaina.com</span><br/>
						<span class="fcimport_sampleline">~~ title 5 ~~ description 5 ~~ 54 ~~ textfield3 value ~~ usere@somedomain.com ~~ www.somedomainb.com</span><br/>
					</fieldset>
				</td>
			</tr>
		</table>

	</div>
	
	
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-calendar">
		<h3 class="tabberheading"><?php echo JText::_("FLEXI_PUBLISHING");?></h3>
		
		<br/>
		<table class="fc-form-tbl align-top">
			
			<tr>
				<td class="key"><label class="label"><?php echo JText::_("FLEXI_CREATOR_AUTHOR");?></label></td>
				<td class="data">
					<?php
						$dv = $this->model->getState('created_by_col');
						$checked0 = $dv==0 ? 'checked="checked"' : '';
						$checked1 = $dv==1 ? 'checked="checked"' : '';
					?>
					<div class="group-fcset fc_input_set fc-cleared">
						<input type="radio" id="created_by_col0" name="created_by_col" value="0" <?php echo $checked0; ?> />
						<label for="created_by_col0"><?php echo JText::_("FLEXI_IMPORT_CREATOR_CURR_USER");?></label>
						<input type="radio" id="created_by_col1" name="created_by_col" value="1" <?php echo $checked1; ?> />
						<label for="created_by_col1"><?php echo JText::_("FLEXI_IMPORT_CREATOR_USE_COL");?></label>
					</div>
				</td>
			</tr>
			
			<tr>
				<td class="key"><label class="label"><?php echo JText::_("FLEXI_MODIFIER");?></label></td>
				<td class="data">
					<?php
						$dv = $this->model->getState('modified_by_col');
						$checked0 = $dv==0 ? 'checked="checked"' : '';
						$checked1 = $dv==1 ? 'checked="checked"' : '';
					?>
					<div class="group-fcset fc_input_set fc-cleared">
						<input type="radio" id="modified_by_col0" name="modified_by_col" value="0" <?php echo $checked0; ?> />
						<label for="modified_by_col0"><?php echo JText::_("FLEXI_IMPORT_MODIFIER_NONE_USER");?></label>
						<input type="radio" id="modified_by_col1" name="modified_by_col" value="1" <?php echo $checked1; ?> />
						<label for="modified_by_col1"><?php echo JText::_("FLEXI_IMPORT_MODIFIER_USE_COL");?></label>
					</div>
				</td>
			</tr>
			
			<tr>
				<td class="key"><label class="label"><?php echo JText::_("FLEXI_META_DATA");?></label></td>
				<td class="data">
					<?php
						$_desc_checked = $this->model->getState('metadesc_col') == 1 ? 'checked="checked"' : '';
						$_key_checked  = $this->model->getState('metakey_col') == 1 ? 'checked="checked"' : '';
					?>
					<div class="group-fcset fc_input_set fc-cleared">
						<input type="checkbox" id="metadesc_col" name="metadesc_col" value="1" <?php echo $_desc_checked; ?> />
						<label for="metadesc_col"><?php echo JText::_("FLEXI_IMPORT_USE_METADESC_COL");?></label>
						<input type="checkbox" id="metakey_col" name="metakey_col" value="1" <?php echo $_key_checked; ?> />
						<label for="metakey_col"><?php echo JText::_("FLEXI_IMPORT_USE_METAKEY_COL");?></label>
					</div>
				</td>
			</tr>
			
			<tr>
				<td class="key"><label class="label"><?php echo JText::_("FLEXI_CUSTOM_TITLE");?></label></td>
				<td class="data">
					<?php
						$_desc_checked = $this->model->getState('custom_ititle_col') == 1 ? 'checked="checked"' : '';
					?>
					<div class="group-fcset fc_input_set fc-cleared">
						<input type="checkbox" id="custom_ititle_col" name="custom_ititle_col" value="1" <?php echo $_desc_checked; ?> />
						<label for="custom_ititle_col"><?php echo JText::_("FLEXI_IMPORT_USE_CUSTOM_TITLE_COL");?></label>
						<span class="icon-info hasTooltip" style="font-size: 18px;" title="<?php echo htmlspecialchars(JText::_('FLEXI_CUSTOM_TITLE_DESC'), ENT_COMPAT, 'UTF-8'); ?>"></span>
					</div>
				</td>
			</tr>
			
			<tr>
				<td colspan="2">
					<br/>
					<div class="fc-mssg fc-info"><?php echo JText::_("FLEXI_IMPORT_ENTER_VALID_DATES");?></div>
				</td>
			</tr>
			
			<tr>
				<td class="key"><label class="label"><?php echo JText::_("FLEXI_CREATION_DATE");?></label></td>
				<td class="data">
					<?php
						$dv = $this->model->getState('created_col');
						$checked0 = $dv==0 ? 'checked="checked"' : '';
						$checked1 = $dv==1 ? 'checked="checked"' : '';
					?>
					<div class="group-fcset fc_input_set fc-cleared">
						<input type="radio" id="created_col0" name="created_col" value="0" <?php echo $checked0; ?> />
						<label for="created_col0"><?php echo JText::_("FLEXI_IMPORT_CREATION_CURR_DATE");?></label>
						<input type="radio" id="created_col1" name="created_col" value="1" <?php echo $checked1; ?> />
						<label for="created_col1"><?php echo JText::_("FLEXI_IMPORT_CREATION_USE_COL");?></label>
					</div>
				</td>
			</tr>
			
			<tr>
				<td class="key"><label class="label"><?php echo JText::_("FLEXI_MODIFICATION_DATE");?></label></td>
				<td class="data">
					<?php
						$dv = $this->model->getState('modified_col');
						$checked0 = $dv==0 ? 'checked="checked"' : '';
						$checked1 = $dv==1 ? 'checked="checked"' : '';
					?>
					<div class="group-fcset fc_input_set fc-cleared">
						<input type="radio" id="modified_col0" name="modified_col" value="0" <?php echo $checked0; ?> />
						<label for="modified_col0"><?php echo JText::_("FLEXI_IMPORT_MODIFICATION_NEVER_DATE");?></label>
						<input type="radio" id="modified_col1" name="modified_col" value="1" <?php echo $checked1; ?> />
						<label for="modified_col1"><?php echo JText::_("FLEXI_IMPORT_MODIFICATION_USE_COL");?></label>
					</div>
				</td>
			</tr>
			
			<tr>
				<td class="key"><label class="label"><?php echo JText::_("FLEXI_PUBLICATION_DATES");?></label></td>
				<td class="data">
					<?php
						$_up_checked   = $this->model->getState('publish_up_col') == 1 ? 'checked="checked"' : '';
						$_down_checked = $this->model->getState('publish_down_col') == 1 ? 'checked="checked"' : '';
					?>
					<div class="group-fcset fc_input_set fc-cleared">
						<input type="checkbox" id="publish_up_col" name="publish_up_col" value="1" <?php echo $_up_checked; ?> />
						<label for="publish_up_col"><?php echo JText::_("FLEXI_IMPORT_USE_PUBLISH_UP_COL");?></label>
						<input type="checkbox" id="publish_down_col" name="publish_down_col" value="1" <?php echo $_down_checked; ?> />
						<label for="publish_down_col"><?php echo JText::_("FLEXI_IMPORT_USE_PUBLISH_DOWN_COL");?></label>
					</div>
				</td>
			</tr>
			
		</table>
		
	</div>
	
	
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-tree-2">
		<h3 class="tabberheading hasTooltip" title="<?php echo JText::_( 'FLEXI_IMPORT_CATS_TIP' ); ?>"><?php echo JText::_("FLEXI_CATEGORIES");?></h3>

		<br/>
		<table class="fc-form-tbl align-top">
			
			<tr>
				<td class="" colspan="2">
					<div class="alert alert-info fc-iblock">
						<?php echo JText::_("- <b>'Use column'</b> effects both NEW / Existing items. <br> - <b>Specific</b> value effects <b>only NEW</b> items");?>
					</div>
					<div class="fcsep_level2"><?php echo JText::_('Defaults'); ?></div>
				</td>
			</tr>

			<tr>
				<td class="key"><label class="label" for="maincat"><?php echo JText::_( 'FLEXI_MAIN_CATEGORY' ); ?></label></td>
				<td class="data"><?php echo $this->lists['maincat']; ?></td>
			</tr>
			
			<tr>
				<td class="key"><label class="label" for="seccats"><?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ); ?></label></td>
				<td class="data"><?php echo $this->lists['seccats']; ?></td>
			</tr>
			
			<tr>
				<td class="" colspan="2">
					<br/><div class="fcsep_level2"><?php echo JText::_('Override defaults via file columns'); ?></div>
				</td>
			</tr>
			
			<tr>
				<td class="key" style="text-align:left;">
					<label class="label" for="maincat_col" style="clear:both;"><?php echo JText::_( 'FLEXI_IMPORT_FILE_OVERRIDE' ); ?> <?php echo JText::_( 'FLEXI_MAIN_CATEGORY' ); ?></label>
				</td>
				<td class="data">
					<?php
						$checked = $this->model->getState('maincat_col') == 1 ? 'checked="checked"' : '';
					?>
					<div class="group-fcset fc_input_set fc-cleared">
						<input type="checkbox" id="maincat_col" name="maincat_col" value="1" <?php echo $checked; ?> /> <label for="maincat_col">(Use 'catid' column, e.g. 54)</label>
					</div>
				</td>
			</tr>
			
			<tr>
				<td class="key" style="text-align:left;">
					<label class="label" for="seccats_col" style="clear:both;"><?php echo JText::_( 'FLEXI_IMPORT_FILE_OVERRIDE' ); ?> <?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ); ?></label>
				</td>
				<td class="data">
					<?php
						$checked = $this->model->getState('seccats_col') == 1 ? 'checked="checked"' : '';
					?>
					<div class="group-fcset fc_input_set fc-cleared">
						<input type="checkbox" id="seccats_col" name="seccats_col" value="1" <?php echo $checked; ?> /> <label for="seccats_col">(Use 'cid' column, e.g. 54,14,51)</label>
					</div>
				</td>
			</tr>
			
		</table>
		
	</div>


	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-tags-2">
		<h3 class="tabberheading hasTooltip" title="<?php echo JText::_( 'FLEXI_IMPORT_TAGS_TIP' ); ?>"><?php echo JText::_("FLEXI_TAGS");?></h3>
		
		<br/>
		<div class="fc-mssg-inline fc-info"><?php echo JText::_("FLEXI_IMPORT_TAGS_WILL_BE_CREATED_BEFORE_IMPORT");?></div>

		<table class="fc-form-tbl align-top">
			
			<tr>
				<td class="key"><label class="label"><?php echo JText::_("FLEXI_TAGS");?></label></td>
				<td class="data">
					<?php
						$dv = $this->model->getState('tags_col');
						$checked0 = $dv==0 ? 'checked="checked"' : '';
						$checked1 = $dv==1 ? 'checked="checked"' : '';
						$checked2 = $dv==2 ? 'checked="checked"' : '';
					?>
					<div class="group-fcset fc_input_set fc-cleared">
						<input type="radio" id="tags_col0" name="tags_col" value="0" <?php echo $checked0; ?> />
						<label for="tags_col0">a. <?php echo JText::_("FLEXI_IMPORT_DO_NOT_IMPORT_TAGS");?></label>
						<div class="fcclear"></div>
						<input type="radio" id="tags_col1" name="tags_col" value="1" <?php echo $checked1; ?> />
						<label for="tags_col1">b. <?php echo JText::_("FLEXI_IMPORT_USE_TAG_NAMES_COL");?></label>
						<div class="fcclear"></div>
						<input type="radio" id="tags_col2" name="tags_col" value="2" <?php echo $checked2; ?> />
						<label for="tags_col2">c. <?php echo JText::_("FLEXI_IMPORT_USE_TAG_IDS_COL");?></label>
					</div>
				</td>
			</tr>
		</table>
		
	</div>


	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-images">
		<h3 class="tabberheading hasTooltip" title="<?php echo JText::_( 'FLEXI_IMPORT_ABOUT_NEW_FILES' ); ?>">Media / Document fields</h3>
		
		<table class="fc-form-tbl align-top">
			
			<tr>
				<td class="" colspan="3">
					<br/>
					<div class="fc-mssg fc-info"> <?php echo JText::_( 'FLEXI_IMPORT_ABOUT_NEW_FILES' ); ?> </div>
				</td>
			</tr>
			
			<tr>
				<td class="key">
					<label class="label" for="media_folder"><?php echo JText::_( 'FLEXI_IMPORT_MEDIA_FOLDER' ); ?></label>
				</td>
				<td class="data">
					<input type="text" name="media_folder" id="media_folder" value="<?php echo $this->model->getState('media_folder'); ?>" class="fcfield_textval" size="40"/>
				</td>
				<td class="data" rowspan="2">
					<div class="fc-mssg fc-info fc-nobgimage">
						<?php echo JText::_( 'FLEXI_IMPORT_FOLDER_DESC' ); ?><br/><br/>
						<?php echo JText::_( 'FLEXI_IMPORT_FILE_IN_SUBFOLDER_DESC' ); ?>
					</div>
				</td>
			</tr>
			
			<tr>
				<td class="key">
					<label class="label" for="docs_folder"><?php echo JText::_( 'FLEXI_IMPORT_DOCUMENTS_FOLDER' ); ?></label>
				</td>
				<td class="data">
					<input type="text" name="docs_folder" id="docs_folder" value="<?php echo $this->model->getState('docs_folder'); ?>" class="fcfield_textval" size="40"/>
				</td>
			</tr>
			
			<tr>
				<td class="" colspan="3">
					<br/>
					<div class="fc-mssg-inline fc-note"> <?php echo JText::_( 'FLEXI_IMPORT_SKIP_FILE_CHECK_DESC' ); ?> </div>
				</td>
			</tr>
			
			<tr>
				<td class="key">
					<label class="label" for="docs_folder"><?php echo JText::_( 'FLEXI_IMPORT_SKIP_FILE_CHECK' ); ?></label>
				</td>
				<td class="data" style="max-width:40%; white-space: unset;">
					<div class="group-fcset fc_input_set fc-cleared">
						<?php foreach ($this->file_fields as $i=> $file_fieid) : ?>
							<input type="checkbox" id="skip_file_field_<?php echo $i; ?>" name="skip_file_field[]" value="<?php echo $file_fieid->name; ?>" />
							<label for="skip_file_field_<?php echo $i; ?>" class=""><?php echo $file_fieid->label." <small>[".$file_fieid->name."]</small>"; ?></label>
						<?php endforeach; ?>
					</div>
				</td>
				<td class="data">
				</td>
			</tr>
			
		</table>
		
	</div>


	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-options">
		<h3 class="tabberheading hasTooltip" title="<?php echo JText::_( '' ); ?>"><?php echo JText::_( 'FLEXI_IMPORT_CSV_FILE_N_FORMAT' ); ?></h3>
		
		<br/>
		<table class="fc-form-tbl align-top">
			<tr>
				<td class="key">
					<label class="label" for="csvfile"><?php echo JText::_( 'FLEXI_CSVFILE' ); ?></label>
				</td>
				<td class="data">
					<input type="file" name="csvfile" id="csvfile" class="required" />
				</td>
				<td class="data">
				</td>
			</tr>
			
			<tr>
				<td class="key">
					<label class="label" for="debug_records"><?php echo JText::_( 'FLEXI_CSV_DISPLAY_FIELDS_OF_FIRST_RECORDS' ); ?></label>
				</td>
				<td class="data">
					<input type="text" name="debug_records" id="debug_records" value="<?php echo (int)$this->model->getState('debug_records'); ?>" class="fcfield_textval" /> &nbsp;
				</td>
				<td class="data">
					<span class="fc-mssg fc-warning fc-nobgimage"><?php echo JText::_( 'FLEXI_CSV_DISPLAY_FIELDS_OF_FIRST_RECORDS_DESC' ); ?></span>
				</td>
			</tr>
			
			<tr>
				<td class="" colspan="3">
					<div class="fcsep_level2"> <?php echo JText::_( 'FLEXI_IMPORT_CSV_FILE_FORMAT' ); ?> </div>
					<div class="alert alert-info fcpadded"> <b>NOTE:</b> Format compatible to EXCEL CSV <b>and</b> FC CSV export file is<br>
						<b><?php echo JText::_( 'FLEXI_CSV_FIELD_SEPARATOR' ); ?></b>: <span class="badge">,</span>
						<b><?php echo JText::_( 'FLEXI_CSV_FIELD_ENCLOSE_CHAR' ); ?></b>: <span class="badge">"</span>
						<b><?php echo JText::_( 'FLEXI_CSV_ITEM_SEPARATOR' ); ?></b>: <span class="badge">\n</span>
					</div>
				</td>
			</tr>
			<tr>
				<td class="key">
					<label class="label" for="field_separator"><?php echo JText::_( 'FLEXI_CSV_FIELD_SEPARATOR' ); ?> </label>
				</td>
				<td class="data">
					<input type="text" name="field_separator" id="field_separator" value="<?php echo htmlspecialchars($this->model->getState('field_separator')); ?>" class="fcfield_textval required" /> &nbsp;
				</td>
				<td class="data">
					<span class="fc-mssg fc-info fc-nobgimage"><?php echo JText::_( 'FLEXI_CSV_FIELD_SEPARATOR_DESC' ); ?></span>
				</td>
			</tr>
			<tr>
				<td class="key">
					<label class="label" for="enclosure_char"><?php echo JText::_( 'FLEXI_CSV_FIELD_ENCLOSE_CHAR' ); ?></label>
				</td>
				<td class="data">
					<input type="text" name="enclosure_char" id="enclosure_char" value="<?php echo htmlspecialchars($this->model->getState('enclosure_char')); ?>" class="fcfield_textval" /> &nbsp;
				</td>
				<td class="data">
					<span class="fc-mssg fc-info fc-nobgimage"><?php echo JText::_( 'FLEXI_CSV_FIELD_ENCLOSE_CHAR_DESC' ); ?></span>
				</td>
			</tr>
			<tr>
				<td class="key">
					<label class="label" for="record_separator"><?php echo JText::_( 'FLEXI_CSV_ITEM_SEPARATOR' ); ?> </label>
				</td>
				<td class="data">
					<input type="text" name="record_separator" id="record_separator" value="<?php echo htmlspecialchars($this->model->getState('record_separator')); ?>" class="fcfield_textval required" /> &nbsp;
				</td>
				<td class="data">
					<span class="fc-mssg fc-info fc-nobgimage"><?php echo JText::_( 'FLEXI_CSV_ITEM_SEPARATOR_DESC' ); ?></span>
				</td>
			</tr>

			<tr>
				<td colspan="3">
					<br/><br/>
					<div class="fcsep_level2"><?php echo JText::_('FLEXI_IMPORT_MVAL_MPROP_FIELDS'); ?></div>
				</td>
			</tr>

			<tr>
				<td class="key">
					<label class="label" for="mval_separator"><?php echo JText::_( 'FLEXI_CSV_MVAL_SEPARATOR' ); ?> </label>
				</td>
				<td class="data">
					<input type="text" name="mval_separator" id="mval_separator" value="<?php echo htmlspecialchars($this->model->getState('mval_separator')); ?>" class="fcfield_textval required" /> &nbsp;
				</td>
				<td class="data">
					<span class="fc-mssg fc-info fc-nobgimage"><?php echo JText::_( 'FLEXI_CSV_MVAL_SEPARATOR_DESC' ); ?></span>
				</td>
			</tr>

			<tr>
				<td class="key">
					<label class="label" for="mprop_separator"><?php echo JText::_( 'FLEXI_CSV_MPROP_SEPARATOR' ); ?> </label>
				</td>
				<td class="data">
					<input type="text" name="mprop_separator" id="mprop_separator" value="<?php echo htmlspecialchars($this->model->getState('mprop_separator')); ?>" class="fcfield_textval required" /> &nbsp;
				</td>
				<td class="data">
					<span class="fc-mssg fc-info fc-nobgimage"><?php echo JText::_( 'FLEXI_CSV_MPROP_SEPARATOR_DESC' ); ?></span>
				</td>
			</tr>
			
		</table>
		
	</div>


	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-wrench">
		<h3 class="tabberheading hasTooltip" title="<?php echo JText::_( '' ); ?>"><?php echo JText::_( 'FLEXI_ADVANCED' ); ?></h3>
		
		<br/>
		<table class="fc-form-tbl align-top">
								
			<tr>
				<td class="key"><label class="label"><?php echo JText::_("FLEXI_IMPORT_IGNORE_UNUSED_COLUMNS");?></label></td>
				<td class="data">
					<?php
						$_ignore_unused_cols_checked = $this->model->getState('ignore_unused_cols') ? 'checked="checked"' : '';
					?>
					<div class="group-fcset fc_input_set fc-cleared">
						<input type="checkbox" id="ignore_unused_cols" name="ignore_unused_cols" value="1" <?php echo $_ignore_unused_cols_checked; ?> />
						<label for="ignore_unused_cols"><?php echo JText::_( 'FLEXI_IMPORT_IGNORE_REDUDANT_COLS' ); ?></label>
					</div>
				</td>
				<td class="data">
					<span class="fc-mssg fc-info fc-nobgimage"><?php echo JText::_("FLEXI_IMPORT_IGNORE_REDUDANT_COLS_DESC");?></span>
				</td>
			</tr>
			
			<tr>
				<td class="key">
					<label class="label" for="items_per_step"><?php echo JText::_( 'FLEXI_IMPORT_ITEMS_PER_STEP' ); ?></label>
				</td>
				<td class="data">
					<input type="text" name="items_per_step" id="items_per_step" value="<?php echo $this->model->getState('items_per_step'); ?>" class="fcfield_textval required" size="40"/>
				</td>
				<td class="data">
					<span class="fc-mssg fc-info fc-nobgimage"><?php echo JText::_( 'FLEXI_IMPORT_ITEMS_PER_STEP_DESC' ); ?></span>
				</td>
			</tr>
			
		</table>
		
	</div>


	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-book">
		<h3 class="tabberheading hasTooltip" title="<?php echo JText::_( 'FLEXI_IMPORT_CSV_FILE_EXAMPLE' ).' / '.JText::_( 'FLEXI_IMPORT_CSV_FILE_FORMAT_EXPLANATION' ); ?>">Example</h3>
		
		<table style="border-collapse: collapse; border: 0; border-spacing: 0;">
			<tr>
				<td style="vertical-align:top; font-family:tahoma; font-size:12px;">
					<br/>
					<fieldset>
						<legend style='color: darkgreen;'><?php echo JText::_( 'FLEXI_IMPORT_CSV_FILE_EXAMPLE' ); ?></legend>
						<span class="fcimport_sampleline">title ~~ text ~~ catid ~~ textfield3 ~~ emailfield6 ~~ weblinkfld8 ~~ single_value_field22 ~~ multi_value_field24 </span><br/>
						<span class="fcimport_sampleline">~~ title 1 ~~ description 1 ~~ 31 ~~ textfield3 value ~~ [-addr-]=usera@somedomain.com!![-text-]=usera ~~ www.somedomaina.com ~~ f22_valuea ~~ f24_value01%%f24_value02%%f24_value03 </span><br/>
						<span class="fcimport_sampleline">~~ title 2 ~~ description 2 ~~ 54 ~~ textfield3 value ~~ [-addr-]=userb@somedomain.com!![-text-]=userb ~~ www.somedomainb.com ~~ f22_valuea ~~ f24_value04%%f24_value05%%f24_value06 </span><br/>
						<span class="fcimport_sampleline">~~ title 3 ~~ description 3 ~~ 67 ~~ textfield3 value ~~ [-addr-]=userc@somedomain.com!![-text-]=userc ~~ www.somedomainc.com ~~ f22_valuea ~~ f24_value07%%f24_value08%%f24_value09 </span><br/>
						<span class="fcimport_sampleline">~~ title 4 ~~ description 4 ~~ 12 ~~ textfield3 value ~~ userd@somedomain.com ~~ [-link-]=www.somedomaind.com!![-title-]=somedomainD ~~ f22_valuea ~~ f24_value10%%f24_value11%%f24_value12 </span><br/>
						<span class="fcimport_sampleline">~~ title 5 ~~ description 5 ~~ 19 ~~ textfield3 value ~~ usere@somedomain.com ~~ [-link-]=www.somedomaine.com!![-title-]=somedomainE ~~ f22_valuea ~~ f24_value13%%f24_value14%%f24_value15 </span><br/>
					</fieldset>
				</td>
			</tr>
			
			<tr>
				<td style="vertical-align:top;">
					<br/>
					<fieldset>
						<legend style='color: darkgreen;'><?php echo JText::_( 'FLEXI_IMPORT_CSV_FILE_FORMAT_EXPLANATION' ); ?></legend>
						
						<br/>			
						<ol>
							<li>
								<b>First line</b> of the CSV file: &nbsp; &nbsp; must contain the <b>field names</b> <u>(and not the field labels!)</u><br/><br/>
							</li>
							<li>
								<b>Field separator</b> of the CSV file: &nbsp; &nbsp; separate fields with a string<b>, that does not appear inside the data</b>, e.g. &nbsp; ~~<br/><br/>
							</li>
							<li>
								<b>Item separator</b> of the CSV file: &nbsp; &nbsp; separate items with a string<b>, that does not appear inside the data</b>, e.g. &nbsp; \n~~<br/><br/>
							</li>
							<li>
								<b>Supported fields:</b><br/><br/>
								<ol type="a">
									<li>
										<span class="fc-mssg-inline fc-info fc-nobgimage">Item properties</span>
										<br/> - title, description, alias, access, language, 
										<br/> - created_by (user id), modified_by (user id),
										<br/> - created (date), modified (date), publish_up (date), publish_down (date)
										<br/> - etc
									</li>
									<li>
										<span class="fc-mssg-inline fc-info fc-nobgimage">Indexable fields</span>
										<br/> select, radio, radioimage (single value)
										<br/> selectmultiple, selectmultple, checkbox, checkboximage (multi-value)
										<br/> <b>Note</b>:
										<br/>  - use their "value" and not their label
										<br/>  - separate multiple values with %%
										<br/> e.g. 4%%2%%5
									</li>
									<li>
										<span class="fc-mssg-inline fc-info fc-nobgimage">Multi-value fields</span>
										<br/> text, date, email, weblink, ... etc
										<br/> <b>Usage</b>: separate multiple values with <span class="badge">%%</span>
									</li>
									<li>
										<span class="fc-mssg-inline fc-info fc-nobgimage">Multi-property per value fields</span>
										<br/> email, weblink, international address, termlist, etc
										<br/> <b>Usage</b>: <span class="badge">[-propertyname-]=propertyvalue</span>, and <b>separate</b> mutliple properties with <span class="badge">!!</span>
										<br/> - <b>email field</b> properties: addr, text
										<br/> - <b>weblink field</b> properties: link, title, hits
										<br/> - <b>extended weblink field</b> properties: link, title, linktext, class, id
										<br/> - <b>international address field</b> properties: name, addr1, addr2, addr3, city, province, state, country, zip, zip_suffix,lat, lon, url, zoom
										<br/> - <b>termlist field</b> properties: title, text
										<br/> - ... etc
										<br/> <b>Note</b>: Some support special "shorthand" formats: e.g. email and weblink allow (respectively) to enter 
										<br/>  - just email: <u>usera@somedomain.com</u>
										<br/>  - just url: <u>www.some-address-some-where.com</u>
										<br/>
									</li>
									<li>
										<span class="fc-mssg-inline fc-info fc-nobgimage">Related items field</span> , use item ids, e.g. to add items 451 , 567, 321 as related items use:
										<br/> <u>451%%567%%321</u>
									</li>
									<li>
										<span class="fc-mssg-inline fc-info fc-nobgimage">Image / gallery field</span> must contain the file name (<b>new file</b>)
										<br/> <b>Note</b>:
										<br/>  - new files must be placed inside media folder
										<br/>  - it can be the name of an existing image name (if image field is in DB-mode)
										<br/>  - since image field is multi-property / multi-value it can use format of these fields too, <b>properties</b> are: originalname, alt, title, desc, urllink
									</li>
									<li>
										<span class="fc-mssg-inline fc-info fc-nobgimage">File field</span> must contain the file name (<b>new file</b>), OR it can be the id of an existing document (filemanager 's file ID)
										<br/> <b>Note:</b>
										<br/>  - new files must be placed inside the document folder<br/>
									</li>
								</ol>
							</li>
						</ol>
					</fieldset>
				</td>
			</tr>
		</table>
		
	</div>
</div>
<!-- tabber end -->
<?php $tabSetCnt = array_pop($tabSetStack); ?>


	<!-- Common management form fields -->
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="import" />
	<input type="hidden" name="view" value="import" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHtml::_('form.token'); ?>

	<!-- fc_perf -->

	</div>  <!-- j-main-container -->
</div>  <!-- row / row-fluid-->

</form>
</div><!-- #flexicontent end -->
<?php 
$sidebar_state = $cparams->get('sidebar_state', 'closed');
if(($sidebar_state) == 'closed') : ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var sidebar = document.querySelector('#sidebar-wrapper');
  var wrapper = document.querySelector('#wrapper');
  var menuCollapse = document.querySelector('#menu-collapse');
  var menuIcon = document.querySelector('#menu-collapse-icon');
  var navLogo = document.querySelector('#header .logo');
  // Retrieve sidebar state from localStorage
  var sidebarState = localStorage.getItem('sidebar');
  console.log(sidebarState);


// Apply initial sidebar state
if (sidebarState === 'closed') {
  wrapper.classList.add('closed');
  menuIcon.classList.remove('icon-toggle-on');
  menuIcon.classList.add('icon-toggle-off');
  navLogo.classList.add('small');
} else if (sidebarState === 'open') {
  wrapper.classList.remove('closed');
  menuIcon.classList.remove('icon-toggle-off');
  menuIcon.classList.add('icon-toggle-on');
  navLogo.classList.remove('small');
} else {
  wrapper.classList.add('closed');
  menuIcon.classList.remove('icon-toggle-on');
  menuIcon.classList.add('icon-toggle-off');
  navLogo.classList.add('small');
}


  // Handle menu click
  menuCollapse.addEventListener('click', function() {
    // Toggle wrapper class
   
    // Update icon
    if (wrapper.classList.contains('closed')) {
      menuIcon.classList.remove('icon-toggle-on');
      menuIcon.classList.add('icon-toggle-off');
	  navLogo.classList.add('small');
      localStorage.setItem('sidebar', 'closed');
    } else {
      menuIcon.classList.remove('icon-toggle-off');
      menuIcon.classList.add('icon-toggle-on');
      localStorage.setItem('sidebar', 'open');
	  navLogo.classList.remove('small');
    }
  });
});
</script>
<?php endif; ?>
