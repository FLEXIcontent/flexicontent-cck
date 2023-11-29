<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');


$state_names = array(
	 1  => JText::_('FLEXI_PUBLISHED'),
	-5  => JText::_('FLEXI_IN_PROGRESS'),
	 0  => JText::_('FLEXI_UNPUBLISHED'),
	-3  => JText::_('FLEXI_PENDING'),
	-4  => JText::_('FLEXI_TO_WRITE'),
	 2  => JText::_('FLEXI_ARCHIVED'),
	-2  => JText::_('FLEXI_TRASHED'),
	'u' => JText::_('FLEXI_UNKNOWN'),
);
$state_icons = array(
	 1  => 'icon-publish',
	-5  => 'icon-checkmark-2',
	 0  => 'icon-unpublish',
	-3  => 'icon-question',
	-4  => 'icon-pencil-2',
	 2  => 'icon-archive',
	-2  => 'icon-trash',
	'u' => 'icon-question-2',
);


/**
 * Warn about associations not enabled
 */
if ($this->behaviour === 'translate' && !flexicontent_db::useAssociations())
{
	JFactory::getApplication()->enqueueMessage(JText::_('FLEXI_LANGUAGE_ASSOCS_IS_OFF_ENABLE_HERE'));
}

$all_langs = FLEXIUtilities::getlanguageslist(true, $_add_all = true);
$langs_indexed = array();
foreach($all_langs as $lang)
{
	$langs_indexed[$lang->code] = $lang;
}


/**
 * Handle special translate of 'quicktranslate' to multiple languages (add multiple associations)
 */
if ($this->task === 'quicktranslate')
{
	// Helper
	JHtml::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/html');
	$hlpname  = 'fcitems';

	// JTable
	JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
	$record = JTable::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());
	$catRec = JTable::getInstance($type = 'flexicontent_categories', $prefix = '', $config = array());

	$assoc_items_arr = array();
	$assoc_cats_arr  = array();

	// Currently 'quicktranslate' is implemented for 1 row only
	if (count($this->rows) > 1)
	{
		echo '<div class="alert alert-info">Please select only 1 row</div>';
		return;
	}
	else
	{
		$row = reset($this->rows);

		$rowcat_isALL = $row->catid === '*';

		/**
		 * Actually we have only 1 row inside $this->rows, but we do a loop
		 * in case we allow this layout to support multiple row in the future
		 */
		$total = 0;
		foreach($this->rows as $row)
		{
			if ($row->language === '*')
			{
				echo '<div class="alert alert-info">Item: "' . $row->title. '"  has language \'ALL\'. It cannot be associated to other languages</div>';
			}
			$total++;

			// Existing associations of the item
			$associations = JLanguageAssociations::getAssociations('com_content', '#__content', 'com_content.item', $row->id);
			foreach ($associations as $tag => $association)
			{
				if ($record->load((int) $association->id))
				{
					$assoc_items_arr[$row->id][$tag] = clone($record);
				}
			}

			// Existing associations of the item's category
			if ($catRec->load((int) $row->catid) && $catRec->language !== '*')
			{
				$cat_associations = JLanguageAssociations::getAssociations('com_content', '#__categories', 'com_categories.item', $row->catid, 'id', 'alias', '');
				foreach ($cat_associations as $tag => $cat_association)
				{
					if ($catRec->load((int) $cat_association->id))
					{
						$assoc_cats_arr[$row->catid][$tag] = clone($catRec);
					}
				}
			}
			else
			{
				$row->hasCatAll = 1;
			}
		}

		// Nothing to do
		if (!$total) return;
	}
}


// We will have either 3 or 2 columns ...
$box_span_classes = $this->task !== 'quicktranslate' || count($this->rows) === 1
	? 'span6 col-6 '
	: 'span4 col-4 ';
?>

<style>
body .form-horizontal .control-group {
  margin-bottom: 0px;
}
#flexicontent .select2-container, .select2-container {
  height: 1.55rem;
  line-height: 1.55rem;
}
.select2-container .select2-choice {
  min-height: 1.55rem !important;
  line-height: 1.55rem !important;
}
</style>


<?php
$submit_cids = array();
ob_start(); ?>
<?php if ($this->task === 'quicktranslate'): ?>

			<table class="adminlist table fcmanlist" style="margin-top: 0px; color: black; font-weight: bold">
				<thead>
					<tr>
						<th></th>
						<th><?php echo JText::_( 'FLEXI_LANGUAGE' ); ?></th>
						<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
						<th><?php echo JText::_( 'FLEXI_CATEGORY' ); ?></th>
					</tr>
				</thead>
				<tbody>

				<?php
				foreach ($this->rows as $row) :
					if (in_array($row->id, $this->cid)) :
						foreach ($row->catids as $catid) :
							if ($catid == $row->catid) :
								$submit_cids[] = '<input type="hidden" name="cid[]" value="' . $row->id . '" />';
								$maincat = $this->itemCats[$catid]->title; ?>
								<tr>
									<td><?php echo '<span title="' . $state_names[$row->state] . '" class="' . $state_icons[$row->state] . '"></span>'; ?></td>
									<td><?php echo $langs_indexed[$row->language]->name; ?></td>
									<td><?php echo $row->title; ?> <!-- cid[] <?php echo $row->id; ?> --></td>
									<td><?php echo $maincat; ?></td>
								</tr>
								<?php
							endif;
						endforeach;
					endif;
				endforeach;
				?>

				</tbody>
			</table>

<?php else: ?>

			<table class="adminlist table fcmanlist" style="margin-top: 0px;">
				<thead>
					<tr>
						<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
						<th><?php echo JText::_( 'FLEXI_CATEGORY' ); ?></th>
						<th><?php echo JText::_( 'FLEXI_LANGUAGE' ); ?></th>
					</tr>
				</thead>
				<tbody>

				<?php
				foreach ($this->rows as $row) :
					if (in_array($row->id, $this->cid)) :
						foreach ($row->catids as $catid) :
							if ($catid == $row->catid) :
								$submit_cids[] = '<input type="hidden" name="cid[]" value="' . $row->id . '" />';
								$maincat = $this->itemCats[$catid]->title; ?>
								<tr>
									<td><?php echo $row->title; ?> <!-- cid[] <?php echo $row->id; ?> --></td>
									<td><?php echo $maincat; ?></td>
									<td><?php echo $langs_indexed[$row->language]->name; ?></td>
								</tr>
								<?php
							endif;
						endforeach;
					endif;
				endforeach;
				?>

				</tbody>
			</table>

<?php endif; ?>
<?php $items_info_html = ob_get_clean(); ?>




<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post"  name="adminForm" id="adminForm" class="form-validate form-horizontal">


<?php if ($this->task === 'quicktranslate'): ?>
	<input type="submit" value="<?php echo JText::_('FLEXI_ADD_TRANSLATIONS') ?>" class="btn btn-success" onclick="this.form.task.value='batchprocess';" />
	<div class="fcclear"></div>

	<div class="toggle_all_values_buttons_box" style="display: inline-block; float: right;">
		<span id="advanced_ops_hide_vals_btn" class="btn fc-hide-vals-btn" style="display:none;"
		 onclick="fc_toggle_box_via_btn(jQuery('#advanced_ops_box'), this, '', jQuery(this).next(), 0); return false;" 
		 title="<?php echo JText::_( 'FLEXI_ADVANCED_OPTIONS' ); ?> <?php echo JText::_( 'FLEXI_HIDE' ); ?>"
		>
			<i class="icon-uparrow"></i> <i class="icon-cog"></i>
		</span>
		<span id="advanced_ops_show_vals_btn" class="btn btn-success fc-show-vals-btn"
		 onclick="fc_toggle_box_via_btn(jQuery('#advanced_ops_box'), this, '', jQuery(this).prev(), 1); return false;"
		 title="<?php echo JText::_( 'FLEXI_ADVANCED_OPTIONS' ); ?> <?php echo JText::_( 'FLEXI_SHOW' ); ?>"
		>
			<i class="icon-downarrow"></i> <i class="icon-cog"></i>
		</span>
	</div>
<?php endif; ?>


<div class="container-fluid row" style="padding: 0px !important; margin: 0px !important; max-width: 1200px;">



	<?php if ($this->task === 'quicktranslate'):?>

	<div class="<?php echo $box_span_classes; ?> full_width_980" style="margin-bottom: 16px !important;">
		<fieldset>

			<?php
				global $globalcats;

				foreach($this->rows as $row):
					$assoc_items = isset($assoc_items_arr[$row->id]) ? $assoc_items_arr[$row->id] : array();
					$assoc_cats  = isset($assoc_cats_arr[$row->catid]) ? $assoc_cats_arr[$row->catid] : array();
					$catLangAll  = $globalcats[$row->catid]->language === '*';
					$i = 1;
			?>
					<table class="adminlist table fcmanlist">
						<thead>
							<tr>
								<th class="col_cb left">
									<div class="group-fcset">
										<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
										<label for="checkall-toggle" class="green single"></label>
									</div>
								</th>
								<th><?php echo JText::_('FLEXI_LANGUAGE'); ?></th>
								<th><?php echo JText::_('FLEXI_ITEM'); ?></th>
								<th class="center">&nbsp;&nbsp; <?php echo '<span class="icon-flag"></span>'; ?></th>
								<th><?php echo JText::_( 'FLEXI_CATEGORY' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($all_langs as $lang): ?>
							<?php
								if ($lang->code === '*') continue;
								$is_current = $row->language === $lang->code;
								$item = $is_current
									? $row
									: (isset($assoc_items[$lang->code]) ? $assoc_items[$lang->code] : false);
								$cat  = $item ? $globalcats[$item->catid]
									:	(isset($assoc_cats[$lang->code])
										? $assoc_cats[$lang->code]
										: ($catLangAll ? $globalcats[$row->catid] : false)
										);
							$td_css = $is_current ? ' class="text-white bg-dark _ALIGN_" ' : ' class="text-dark _ALIGN_" ';
							?>
							<tr>
								<td <?php echo $td_css; ?>>
								<?php if ($cat)
								{
									echo $item
										? '<span title="' . $state_names[$item->state] . '" class="' . $state_icons[$item->state] . '"></span>'
										: JHtml::_($hlpname . '.grid_id', $i++, $lang->code, $_checkedOut = false, $_name = 'languages');
								} ?>
								</td>
								<td <?php echo str_replace('_ALIGN_', '', $td_css); ?>><?php echo $lang->name; ?></td>
								<td <?php echo str_replace('_ALIGN_', '', $td_css); ?>><?php echo $item ? $item->title : '-'; ?></td>
								<td <?php echo str_replace('_ALIGN_', 'center', $td_css); ?>><?php echo $cat ? ($cat->language !== '*' ? $cat->language : JText::_('FLEXI_ALL')) : '-'; ?></td>
								<td <?php echo str_replace('_ALIGN_', '', $td_css); ?>><?php echo $cat ? $cat->title : '<small class="text-muted">'. JText::_('FLEXI_NO_ASSOCIATED_CATEGORY') . '</small>'; ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

				<?php endforeach; ?>

		</fieldset>
	</div>

	<?php endif; ?>



	<div class="<?php echo $box_span_classes; ?> full_width_980" style="margin-bottom: 16px !important;">

		<fieldset id="advanced_ops_box" style="<?php echo $this->task === 'quicktranslate' ? 'display: none;' : ''; ?>">

			<legend><?php echo JText::_( $this->behaviour == 'translate' ? 'FLEXI_TRANSLATE_OPTIONS' : 'FLEXI_BATCH_OPTIONS' ); ?></legend>

			<div class="control-group" id="row_method" style="margin: 0;">

				<div class="control-label" style="display: none;">
					<label class="label-fcinner">
						<?php echo JText::_( 'FLEXI_METHOD' ); ?>
					</label>
				</div>

				<div class="controls" style="margin: 0px 24px;">

					<?php if ($this->behaviour == 'translate') : ?>

						<input type="hidden" name="method" value="99" /> <!-- METHOD number for traslate -->
						<input type="hidden" name="initial_behaviour" value="copymove" /> <!-- a hidden field to give info to JS initialization code -->

						<fieldset class="fc-cleared group-fcset fc_input_set">
							<div>
								<input id="method-duplicateoriginal" type="radio" name="translate_method" value="1" onclick="copymove();" checked="checked" />
								<label for="method-duplicateoriginal"><?php echo JText::_( 'FLEXI_DUPLICATEORIGINAL' ); ?></label>
							</div>

							<div>
								<input id="method-useempty" type="radio" name="translate_method" value="5" onclick="copymove();" />
								<label for="method-useempty"><?php echo JText::_( 'FLEXI_EMPTY' ); ?></label>
							</div>

							<?php if ($this->task !== 'quicktranslate') : ?>

								<div>
									<input id="method-usejoomfish" type="radio" name="translate_method" value="2" onclick="copymove();" />
									<label for="method-usejoomfish"><?php echo JText::_( 'FLEXI_USE_JF_FL_DATA' ); ?> *</label>
								</div>

								<?php if ( JFile::exists(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'translator.php') ) :
								/* if automatic translator installed ... */ ?>

									<div>
										<input id="method-autotranslation" type="radio" name="translate_method" value="3" onclick="copymove();" />
										<label for="method-autotranslation"><?php echo JText::_( 'FLEXI_AUTO_TRANSLATION' ); ?></label>
									</div>

									<div>
										<input id="method-firstjf-thenauto" type="radio" name="translate_method" value="4" onclick="copyonly();" />
										<label for="method-firstjf-thenauto"><?php echo JText::_( 'FLEXI_FIRST_JF_FL_THEN_AUTO' ); ?> *</label>
									</div>

								<?php endif; ?>

							<?php endif; ?>

						</fieldset>

						<div class="fcclear"></div>
						<div id="falang-import-info" class="fc-mssg fc-note" style="display:none; margin-top: 4px;">
							<?php echo JText::_( 'FLEXI_USE_JF_FL_DATA_INFO' ); ?>
						</div>

					<?php else : ?>
						<input type="hidden" name="initial_behaviour" value="copyonly" /> <!-- a hidden field to give info to JS initialization code -->

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

					<?php endif; ?>

				</div>
			</div>


			<fieldset class="panelform" id="row_copy_options">
				<br/><span class="alert alert-info fc-iblock" style="margin-bottom: 4px;"><?php echo JText::_( 'FLEXI_COPY_OPTIONS'); ?></span>
			</fieldset>


			<div class="control-group" id="row_prefix">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo JText::_( 'FLEXI_ADD_PREFIX' ); ?>
					</label>
				</div>
				<div class="controls">
					<?php $defprefix = JText::_( $this->behaviour == 'translate'
						? '[_lang_code_]' //'FLEXI_DEFAULT_TRANSLATE_PREFIX'
						: 'FLEXI_DEFAULT_PREFIX' );
					?>
					<input type="text" id="prefix" name="prefix" value="<?php echo $defprefix; ?>" size="15" />
				</div>
			</div>


			<div class="control-group" id="row_suffix">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo JText::_( 'FLEXI_ADD_SUFFIX' ); ?>
					</label>
				</div>
				<div class="controls">
					<input type="text" id="suffix" name="suffix" value="" size="15" />
				</div>
			</div>

			<?php if ($this->behaviour !== 'translate') : ?>

				<div class="control-group" id="row_copynr">
					<div class="control-label">
						<label class="label-fcinner">
							<?php echo JText::_( 'FLEXI_COPIES_NR' ); ?>
						</label>
					</div>
					<div class="controls">
						<input type="text" id="copynr" name="copynr" value="1" size="3" />
					</div>
				</div>

			<?php endif; ?>


			<fieldset class="panelform">
				<br/><span class="alert alert-info fc-iblock" style="margin-bottom: 4px;"><?php echo JText::_( 'FLEXI_COPY_UPDATE_OPTIONS'); ?></span>
			</fieldset>


			<?php if ($this->task !== 'quicktranslate') : ?>

				<div class="control-group" id="row_language">
					<div class="control-label">
						<label class="label-fcinner" for="language">
							<?php echo ($this->behaviour == 'translate' ? JText::_( 'NEW' ) . ' ' : '') . JText::_( 'FLEXI_LANGUAGE' ); ?>
						</label>
					</div>
					<div class="controls">
						<?php echo $this->lists['language']; ?>
					</div>
				</div>

			<?php endif; ?>


			<div class="control-group" id="row_state">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo JText::_( 'FLEXI_STATE' ); ?>
					</label>
				</div>
				<div class="controls">
					<?php echo $this->lists['state']; ?>
				</div>
			</div>


			<?php if ($this->behaviour !== 'translate') : ?>

				<div class="control-group" id="row_type_id">
					<div class="control-label">
						<label class="label-fcinner">
							<?php echo JText::_( 'FLEXI_TYPE' ); ?>
						</label>
					</div>
					<div class="controls">
						<?php echo $this->lists['type_id']; ?>
						<div id="fc-change-warning" class="fc-mssg fc-warning" style="display:none; float:left;">
							<?php echo JText::_( 'FLEXI_TAKE_CARE_CHANGING_FIELD_TYPE' ); ?>
						</div>
					</div>
				</div>

			<?php endif; ?>


			<div class="control-group" id="row_access">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo JText::_( 'FLEXI_ACCESS' ); ?>
					</label>
				</div>
				<div class="controls">
					<?php echo $this->lists['access']; ?>
				</div>
			</div>


			<fieldset class="panelform">
				<br/><span class="alert alert-info fc-iblock" style="margin-bottom: 4px;"><?php echo JText::_( 'FLEXI_ASSIGNMENTS'); ?></span>
			</fieldset>


			<div class="control-group" id="row_keeptags">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo JText::_( 'FLEXI_KEEP_TAGS' ); ?>
					</label>
				</div>
				<div class="controls">
					<div class="group-fcset fc_input_set">
						<input id="keeptags0" type="radio" name="keeptags" value="0"/>
						<label for="keeptags0"><?php echo JText::_( 'FLEXI_NO' ); ?></label>

						<input id="keeptags1" type="radio" name="keeptags" value="1" checked="checked" />
						<label for="keeptags1"><?php echo JText::_( 'FLEXI_YES' ); ?></label>
					</div>
				</div>
			</div>


			<div class="control-group" id="row_maincat">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo JText::_( 'FLEXI_MAIN_CATEGORY' ); ?>
					</label>
				</div>
				<div class="controls">
					<?php echo $this->lists['maincat']; ?>
				</div>
			</div>


			<div class="control-group" id="row_keepseccats">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo JText::_( 'FLEXI_KEEP_SEC_CATS' ); ?>
					</label>
				</div>
				<div class="controls">
					<div class="group-fcset fc_input_set">
						<input id="keepseccats0" type="radio" name="keepseccats" value="0" onclick="seccats_on();" />
						<label for="keepseccats0"><?php echo JText::_( 'FLEXI_NO' ); ?></label>

						<input id="keepseccats1" type="radio" name="keepseccats" value="1" onclick="seccats_off();" />
						<label for="keepseccats1"><?php echo JText::_( 'FLEXI_YES' ); ?></label>
					</div>
				</div>
			</div>


			<div class="control-group" id="row_seccats">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ); ?>
					</label>
				</div>
				<div class="controls">
					<?php echo $this->lists['seccats']; ?>
				</div>
			</div>

		</fieldset>

	</div>



	<?php if ($this->task !== 'quicktranslate' || count($this->rows) > 1): ?>
	<div class="<?php echo $box_span_classes; ?> full_width_980" style="margin-bottom: 16px !important;">

		<fieldset>
			<legend><?php echo JText::_( 'FLEXI_ITEMS' ); ?></legend>
			<?php echo $items_info_html; ?>
		</fieldset>

	</div>
	<?php endif; ?>



</div>

<?php echo implode("\n", $submit_cids); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="controller" value="items" />
<input type="hidden" name="view" value="items" />
<input type="hidden" name="task" value="" />
<?php echo JHtml::_( 'form.token' ); ?>
</form>
</div>
