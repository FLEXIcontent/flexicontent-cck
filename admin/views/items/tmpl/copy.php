<?php
/**
 * @version 1.5 stable $Id: copy.php 1902 2014-05-10 16:06:11Z ggppdk $
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
if ($this->behaviour === 'translate' && !flexicontent_db::useAssociations())
{
	JFactory::getApplication()->enqueueMessage(JText::_('FLEXI_LANGUAGE_ASSOCS_IS_OFF_ENABLE_HERE'));
}
?>

<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post"  name="adminForm" id="adminForm" class="form-validate form-horizontal">

	<div class="container-fluid" style="padding: 0px; margin-bottom: 24px; max-width: 1200px;">

		<div class="span6 full_width_980" style="margin-bottom: 16px !important;">

			<fieldset>
			<legend style="text-align: center;"><?php echo JText::_( 'FLEXI_CONTENTS_LIST' ); ?></legend>
				<table class="fc-table-list" style="margin-top: 0px;">
					<thead>
						<tr>
							<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
							<th><?php echo JText::_( 'FLEXI_MAIN_CATEGORY' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ($this->rows as $row) :
							if (in_array($row->id, $this->cid)) :
								foreach ($row->catids as $catid) :
									if ($catid == $row->catid) :
										$maincat = $this->itemCats[$catid]->title;
						?>
						<tr>
							<td><?php echo $row->title; ?></td>
							<td><?php echo $maincat; ?><input type="hidden" name="cid[]" value="<?php echo $row->id; ?>" /></td>
						</tr>
						<?php
									endif;
								endforeach;
							endif;
						endforeach;
						?>
					</tbody>
				</table>
			</fieldset>

	</div>
	<div class="span6 full_width_980" style="margin-bottom: 16px !important;">

			<fieldset>
			
			<?php if ($this->behaviour == 'translate') : ?>
				<legend><?php echo JText::_( 'FLEXI_TRANSLATE_OPTIONS' ); ?></legend>
			<?php else : ?>
				<legend><?php echo JText::_( 'FLEXI_BATCH_OPTIONS' ); ?></legend>
			<?php endif; ?>
		
				<table class="fc-form-tbl">
					<tr>
					
					<?php if ($this->behaviour == 'translate') : ?>
						<td class="key" style="vertical-align:top;">
							<label class="label">
								<?php echo JText::_( 'FLEXI_METHOD' ); ?>
							</label>
							<input type="hidden" name="method" value="99" /> <!-- METHOD number for traslate -->
							<input type="hidden" name="initial_behaviour" value="copymove" /> <!-- a hidden field to give info to JS initialization code -->
						</td>
						<td style="vertical-align:top;">
							<fieldset class="radio btn-group btn-group-yesno">
								<input id="method-duplicateoriginal" type="radio" name="translate_method" value="1" onclick="copymove();" checked="checked" />
								<label for="method-duplicateoriginal" class="btn">
									<?php echo JText::_( 'FLEXI_DUPLICATEORIGINAL' ); ?>
								</label>
								
								<input id="method-usejoomfish" type="radio" name="translate_method" value="2" onclick="copymove();" />
								<label for="method-usejoomfish" class="btn">
									<?php echo JText::_( 'FLEXI_USE_JF_FL_DATA' ); ?> *
								</label>
								
							<?php if ( JFile::exists(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'translator.php') ) : /* if automatic translator installed ... */ ?>
							
								<input id="method-autotranslation" type="radio" name="translate_method" value="3" onclick="copymove();" />
								<label for="method-autotranslation" class="btn">
									<?php echo JText::_( 'FLEXI_AUTO_TRANSLATION' ); ?>
								</label>
								
								<input id="method-firstjf-thenauto" type="radio" name="translate_method" value="4" onclick="copyonly();" />
								<label for="method-firstjf-thenauto" class="btn">
									<?php echo JText::_( 'FLEXI_FIRST_JF_FL_THEN_AUTO' ); ?> *
								</label>
								
							<?php endif; ?>
							</fieldset>
							
							<div class="fcclear"></div>
							<div id="falang-import-info" class="fc-mssg fc-note" style="display:none; margin-top: 4px;">
								<?php echo JText::_( 'FLEXI_USE_JF_FL_DATA_INFO' ); ?>
							</div>
							
						</td>
					<?php else : ?>
					
						<td class="key" style="vertical-align:top;">
							<label class="label">
								<?php echo JText::_( 'FLEXI_METHOD' ); ?>
							</label>
							<input type="hidden" name="initial_behaviour" value="copyonly" /> <!-- a hidden field to give info to JS initialization code -->
						</td>
						<td style="vertical-align:top;">
							<fieldset class="radio btn-group btn-group-yesno">
								<input id="menus-copy" type="radio" name="method" value="1" onclick="copyonly();" checked="checked" />
								<label for="menus-copy" class="btn" >
									<?php echo JText::_( 'FLEXI_COPY' ); ?>
								</label>
									
								<input id="method-move" type="radio" name="method" value="2" onclick="moveonly();" />
								<label for="method-move" class="btn"  >
									<?php echo JText::_( 'FLEXI_UPDATE' ); ?>
								</label>
								
								<input id="method-copymove" type="radio" name="method" value="3" onclick="copymove();" />
								<label for="method-copymove" class="btn" >
									<?php echo JText::_( 'FLEXI_COPYUPDATE' ); ?>
								</label>
							</fieldset>
						</td>
						
					<?php endif; ?>
					
					</tr>
				</table>


				<fieldset class="panelform" id="row_copy_options">
					<br/>
					<span class="alert alert-info fc-iblock" style="margin-bottom: 4px;"><?php echo JText::_( 'FLEXI_COPY_OPTIONS'); ?></span>
				</fieldset>

				<fieldset class="panelform" id="row_prefix">
					<span class="label-fcouter"><label class="label" for="prefix"><?php echo JText::_( 'FLEXI_ADD_PREFIX' ); ?></label></span>
					<div class="container_fcfield">
						<?php
						if ($this->behaviour == 'translate') $defprefix = JText::_( 'FLEXI_DEFAULT_TRANSLATE_PREFIX' );
						else $defprefix = JText::_( 'FLEXI_DEFAULT_PREFIX');
						?>
						<input type="text" id="prefix" name="prefix" value="<?php echo $defprefix; ?>" size="15" />
					</div>
				</fieldset>

				<fieldset class="panelform" id="row_suffix">
					<span class="label-fcouter"><label class="label" for="suffix"><?php echo JText::_( 'FLEXI_ADD_SUFFIX' ); ?></label></span>
					<div class="container_fcfield">
						<input type="text" id="suffix" name="suffix" value="" size="15" />
					</div>
				</fieldset>

				<fieldset class="panelform" id="row_copynr">
					<span class="label-fcouter"><label class="label"><?php echo JText::_( 'FLEXI_COPIES_NR' ); ?></label></span>
					<div class="container_fcfield">
						<input type="text" id="copynr" name="copynr" value="1" size="3" />
					</div>
				</fieldset>

				<fieldset class="panelform">
					<br/>
					<span class="alert alert-info fc-iblock" style="margin-bottom: 4px;"><?php echo JText::_( 'FLEXI_COPY_UPDATE_OPTIONS'); ?></span>
				</fieldset>

				<fieldset class="panelform" id="row_language">
					<span class="label-fcouter"><label class="label" for="language"><?php echo ($this->behaviour == 'translate' ? JText::_( 'NEW' )." " : '').JText::_( 'FLEXI_LANGUAGE' ); ?></label></span>
					<div class="container_fcfield">
						<?php echo $this->lists['language']; ?>
					</div>
				</fieldset>

				<fieldset class="panelform" id="row_state">
					<span class="label-fcouter"><label class="label"><?php echo JText::_( 'FLEXI_STATE' ); ?></label></span>
					<div class="container_fcfield">
						<?php echo $this->lists['state']; ?>
					</div>
				</fieldset>

				<fieldset class="panelform" id="row_type_id">
					<span class="label-fcouter"><label class="label"><?php echo JText::_( 'FLEXI_TYPE' ); ?></label></span>
					<div class="container_fcfield">
						<?php echo $this->lists['type_id']; ?>
						<div id="fc-change-warning" class="fc-mssg fc-warning" style="display:none; float:left;"><?php echo JText::_( 'FLEXI_TAKE_CARE_CHANGING_FIELD_TYPE' ); ?></div>
					</div>
				</fieldset>

				<fieldset class="panelform" id="row_access">
					<span class="label-fcouter"><label class="label"><?php echo JText::_( 'FLEXI_ACCESS' ); ?></label></span>
					<div class="container_fcfield">
						<?php echo $this->lists['access']; ?>
					</div>
				</fieldset>

				<fieldset class="panelform">
					<br/>
					<span class="alert alert-info fc-iblock" style="margin-bottom: 4px;"><?php echo JText::_( 'FLEXI_ASSIGNMENTS'); ?></span>
				</fieldset>

				<fieldset class="panelform" id="row_keeptags">
					<span class="label-fcouter"><label class="label"><?php echo JText::_( 'FLEXI_KEEP_TAGS' ); ?></label></span>
					<div class="container_fcfield">
						<input id="keeptags0" type="radio" name="keeptags" value="0"/>
						<label for="keeptags0">
							<?php echo JText::_( 'FLEXI_NO' ); ?>
						</label>

						<input id="keeptags1" type="radio" name="keeptags" value="1" checked="checked" />
						<label for="keeptags1">
							<?php echo JText::_( 'FLEXI_YES' ); ?>
						</label>
					</div>
				</fieldset>

				<fieldset class="panelform" id="row_maincat">
					<span class="label-fcouter"><label class="label"><?php echo JText::_( 'FLEXI_MAIN_CATEGORY' ); ?></label></span>
					<div class="container_fcfield">
						<?php echo $this->lists['maincat']; ?>
					</div>
				</fieldset>

				<fieldset class="panelform" id="row_keepseccats">
					<span class="label-fcouter"><label class="label"><?php echo JText::_( 'FLEXI_KEEP_SEC_CATS' ); ?></label></span>
					<div class="container_fcfield">
						<input id="keepseccats0" type="radio" name="keepseccats" value="0" onclick="seccats_on();" />
						<label for="keepseccats0">
							<?php echo JText::_( 'FLEXI_NO' ); ?>
						</label>

						<input id="keepseccats1" type="radio" name="keepseccats" value="1" onclick="seccats_off();" />
						<label for="keepseccats1">
							<?php echo JText::_( 'FLEXI_YES' ); ?>
						</label>
					</div>
				</fieldset>

				<fieldset class="panelform" id="row_seccats">
					<span class="label-fcouter"><label class="label"><?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ); ?></label></span>
					<div class="container_fcfield">
						<?php echo $this->lists['seccats']; ?>
					</div>
				</fieldset>

			</fieldset>

		</div>

	</div>

	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="items" />
	<input type="hidden" name="view" value="items" />
	<input type="hidden" name="task" value="" />
	<?php echo JHtml::_( 'form.token' ); ?>
</form>
</div>