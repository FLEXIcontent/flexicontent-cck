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

$isQuicktranslateSingle = $this->task === 'quicktranslate';
$isQuicktranslateAll = $this->task === 'quicktranslateall';
$isQuicktranslateMode = $isQuicktranslateSingle || $isQuicktranslateAll;


$state_names = array(
	 1  => \Joomla\CMS\Language\Text::_('FLEXI_PUBLISHED'),
	-5  => \Joomla\CMS\Language\Text::_('FLEXI_IN_PROGRESS'),
	 0  => \Joomla\CMS\Language\Text::_('FLEXI_UNPUBLISHED'),
	-3  => \Joomla\CMS\Language\Text::_('FLEXI_PENDING'),
	-4  => \Joomla\CMS\Language\Text::_('FLEXI_TO_WRITE'),
	 2  => \Joomla\CMS\Language\Text::_('FLEXI_ARCHIVED'),
	-2  => \Joomla\CMS\Language\Text::_('FLEXI_TRASHED'),
	'u' => \Joomla\CMS\Language\Text::_('FLEXI_UNKNOWN'),
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
	\Joomla\CMS\Factory::getApplication()->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_LANGUAGE_ASSOCS_IS_OFF_ENABLE_HERE'));
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
if ($isQuicktranslateMode)
{
	// Helper
	\Joomla\CMS\HTML\HTMLHelper::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/html');
	$hlpname  = 'fcitems';
}

if ($isQuicktranslateSingle)
{
	// \Joomla\CMS\Table\Table
	\Joomla\CMS\Table\Table::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
	$record = \Joomla\CMS\Table\Table::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());
	$catRec = \Joomla\CMS\Table\Table::getInstance($type = 'flexicontent_categories', $prefix = '', $config = array());

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
			$associations = \Joomla\CMS\Language\Associations::getAssociations('com_content', '#__content', 'com_content.item', $row->id);
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
				$cat_associations = \Joomla\CMS\Language\Associations::getAssociations('com_content', '#__categories', 'com_categories.item', $row->catid, 'id', 'alias', '');
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
$box_span_classes = !$isQuicktranslateMode || count($this->rows) === 1
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
#flexicontent .choices, .choices {
  height: 1.55rem;
  line-height: 1.55rem;
}
.choices .choices__inner {
  min-height: 1.55rem !important;
  line-height: 1.55rem !important;
}
/* Multi-selects (tags, secondary categories): grow with the number of selected values instead of clipping them */
#flexicontent .choices[data-type*="select-multiple"], .choices[data-type*="select-multiple"] {
  height: auto !important;
  line-height: normal;
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
						<th><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_LANGUAGE' ); ?></th>
						<th><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_TITLE' ); ?></th>
						<th><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_CATEGORY' ); ?></th>
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
						<th><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_TITLE' ); ?></th>
						<th><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_CATEGORY' ); ?></th>
						<th><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_LANGUAGE' ); ?></th>
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


<?php if ($isQuicktranslateMode): ?>
	<input type="submit" id="fc_add_translations_btn" value="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_ADD_TRANSLATIONS') ?>" class="btn btn-success" onclick="this.form.task.value='batchprocess';" />
	<div class="fcclear"></div>

	<div class="toggle_all_values_buttons_box" style="display: inline-block; float: right;">
		<span id="advanced_ops_hide_vals_btn" class="btn fc-hide-vals-btn" style="display:none;"
		 onclick="fc_toggle_box_via_btn(jQuery('#advanced_ops_box'), this, '', jQuery(this).next(), 0); return false;" 
		 title="<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_ADVANCED_OPTIONS' ); ?> <?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_HIDE' ); ?>"
		>
			<i class="icon-uparrow"></i> <i class="icon-cog"></i>
		</span>
		<span id="advanced_ops_show_vals_btn" class="btn btn-success fc-show-vals-btn"
		 onclick="fc_toggle_box_via_btn(jQuery('#advanced_ops_box'), this, '', jQuery(this).prev(), 1); return false;"
		 title="<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_ADVANCED_OPTIONS' ); ?> <?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_SHOW' ); ?>"
		>
			<i class="icon-downarrow"></i> <i class="icon-cog"></i>
		</span>
	</div>
<?php endif; ?>


<div class="container-fluid row" style="padding: 0px !important; margin: 0px !important; max-width: 1200px;">



<?php if ($isQuicktranslateSingle):?>

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
										<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo \Joomla\CMS\Language\Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
										<label for="checkall-toggle" class="green single"></label>
									</div>
								</th>
								<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_LANGUAGE'); ?></th>
								<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_ITEM'); ?></th>
								<th class="center">&nbsp;&nbsp; <?php echo '<span class="icon-flag"></span>'; ?></th>
								<th><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_CATEGORY' ); ?></th>
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
										: \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.grid_id', $i++, $lang->code, $_checkedOut = false, $_name = 'languages');
								} ?>
								</td>
								<td <?php echo str_replace('_ALIGN_', '', $td_css); ?>><?php echo $lang->name; ?></td>
								<td <?php echo str_replace('_ALIGN_', '', $td_css); ?>><?php echo $item ? $item->title : '-'; ?></td>
								<td <?php echo str_replace('_ALIGN_', 'center', $td_css); ?>><?php echo $cat ? ($cat->language !== '*' ? $cat->language : \Joomla\CMS\Language\Text::_('FLEXI_ALL')) : '-'; ?></td>
								<td <?php echo str_replace('_ALIGN_', '', $td_css); ?>><?php echo $cat ? $cat->title : '<small class="text-muted">'. \Joomla\CMS\Language\Text::_('FLEXI_NO_ASSOCIATED_CATEGORY') . '</small>'; ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

				<?php endforeach; ?>

		</fieldset>
	</div>

	<?php elseif ($isQuicktranslateAll): ?>

	<div class="<?php echo $box_span_classes; ?> full_width_980" style="margin-bottom: 16px !important;">
		<fieldset>
			<legend><?php echo \Joomla\CMS\Language\Text::_('FLEXI_LANGUAGES'); ?></legend>

			<table class="adminlist table fcmanlist" style="margin-top: 0px;">
				<thead>
					<tr>
						<th class="col_cb left">
							<div class="group-fcset">
								<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo \Joomla\CMS\Language\Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
								<label for="checkall-toggle" class="green single"></label>
							</div>
						</th>
						<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_LANGUAGE'); ?></th>
						<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_CODE'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $i = 1; ?>
					<?php foreach ($all_langs as $lang): ?>
						<?php if ($lang->code === '*') continue; ?>
						<tr>
							<td>
								<?php echo \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.grid_id', $i++, $lang->code, $_checkedOut = false, $_name = 'languages'); ?>
							</td>
							<td><?php echo $lang->name; ?></td>
							<td><?php echo $lang->code; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</fieldset>
	</div>

	<?php endif; ?>



	<div class="<?php echo $box_span_classes; ?> full_width_980" style="margin-bottom: 16px !important;">

		<fieldset id="advanced_ops_box" style="<?php echo $isQuicktranslateMode ? 'display: none;' : ''; ?>">

			<legend><?php echo \Joomla\CMS\Language\Text::_( $this->behaviour == 'translate' ? 'FLEXI_TRANSLATE_OPTIONS' : 'FLEXI_BATCH_OPTIONS' ); ?></legend>

			<div class="control-group" id="row_method" style="margin: 0;">

				<div class="control-label" style="display: none;">
					<label class="label-fcinner">
						<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_METHOD' ); ?>
					</label>
				</div>

				<div class="controls" style="margin: 0px 24px;">

					<?php if ($this->behaviour == 'translate') : ?>

						<input type="hidden" name="method" value="99" /> <!-- METHOD number for traslate -->
						<input type="hidden" name="initial_behaviour" value="copymove" /> <!-- a hidden field to give info to JS initialization code -->

						<fieldset class="fc-cleared group-fcset fc_input_set">
							<div>
								<input id="method-duplicateoriginal" type="radio" name="translate_method" value="1" onclick="copymove();" checked="checked" />
								<label for="method-duplicateoriginal"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_DUPLICATEORIGINAL' ); ?></label>
							</div>

							<div>
								<input id="method-useempty" type="radio" name="translate_method" value="5" onclick="copymove();" />
								<label for="method-useempty"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_EMPTY' ); ?></label>
							</div>

							<?php if (!$isQuicktranslateMode) : ?>

								<div>
									<input id="method-usejoomfish" type="radio" name="translate_method" value="2" onclick="copymove();" />
									<label for="method-usejoomfish"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_USE_JF_FL_DATA' ); ?> *</label>
								</div>

								<?php if ( file_exists(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'translator.php') ) :
								/* if automatic translator installed ... */ ?>

									<div>
										<input id="method-autotranslation" type="radio" name="translate_method" value="3" onclick="copymove();" />
										<label for="method-autotranslation"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_AUTO_TRANSLATION' ); ?></label>
									</div>

									<div>
										<input id="method-firstjf-thenauto" type="radio" name="translate_method" value="4" onclick="copyonly();" />
										<label for="method-firstjf-thenauto"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_FIRST_JF_FL_THEN_AUTO' ); ?> *</label>
									</div>

								<?php endif; ?>

							<?php endif; ?>

						</fieldset>

						<div class="fcclear"></div>
						<div id="falang-import-info" class="fc-mssg fc-note" style="display:none; margin-top: 4px;">
							<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_USE_JF_FL_DATA_INFO' ); ?>
						</div>

					<?php else : ?>
						<input type="hidden" name="initial_behaviour" value="copyonly" /> <!-- a hidden field to give info to JS initialization code -->

						<fieldset class="radio btn-group btn-group-yesno">
							<input id="menus-copy" type="radio" name="method" value="1" onclick="copyonly();" checked="checked" />
							<label for="menus-copy" class="btn" >
							<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_COPY' ); ?>
							</label>

							<input id="method-move" type="radio" name="method" value="2" onclick="moveonly();" />
							<label for="method-move" class="btn"  >
							<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ); ?>
							</label>

							<input id="method-copymove" type="radio" name="method" value="3" onclick="copymove();" />
							<label for="method-copymove" class="btn" >
							<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_COPYUPDATE' ); ?>
							</label>
						</fieldset>

					<?php endif; ?>

				</div>
			</div>


			<fieldset class="panelform" id="row_copy_options">
				<br/><span class="alert alert-info fc-iblock" style="margin-bottom: 4px;"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_COPY_OPTIONS'); ?></span>
			</fieldset>


			<div class="control-group" id="row_prefix">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_ADD_PREFIX' ); ?>
					</label>
				</div>
				<div class="controls">
					<?php $defprefix = \Joomla\CMS\Language\Text::_( $this->behaviour == 'translate'
						? '[_lang_code_]' //'FLEXI_DEFAULT_TRANSLATE_PREFIX'
						: 'FLEXI_DEFAULT_PREFIX' );
					?>
					<input type="text" id="prefix" name="prefix" value="" placeholder="<?php echo htmlspecialchars($defprefix, ENT_QUOTES, 'UTF-8'); ?>" size="15" />
				</div>
			</div>


			<div class="control-group" id="row_suffix">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_ADD_SUFFIX' ); ?>
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
							<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_COPIES_NR' ); ?>
						</label>
					</div>
					<div class="controls">
						<input type="text" id="copynr" name="copynr" value="1" size="3" />
					</div>
				</div>

			<?php endif; ?>


			<fieldset class="panelform">
				<br/><span class="alert alert-info fc-iblock" style="margin-bottom: 4px;"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_COPY_UPDATE_OPTIONS'); ?></span>
			</fieldset>


			<?php if (!$isQuicktranslateMode) : ?>

				<div class="control-group" id="row_language">
					<div class="control-label">
						<label class="label-fcinner" for="language">
							<?php echo ($this->behaviour == 'translate' ? \Joomla\CMS\Language\Text::_( 'NEW' ) . ' ' : '') . \Joomla\CMS\Language\Text::_( 'FLEXI_LANGUAGE' ); ?>
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
						<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_STATE' ); ?>
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
							<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_TYPE' ); ?>
						</label>
					</div>
					<div class="controls">
						<?php echo $this->lists['type_id']; ?>
						<div id="fc-change-warning" class="fc-mssg fc-warning" style="display:none; float:left;">
							<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_TAKE_CARE_CHANGING_FIELD_TYPE' ); ?>
						</div>
					</div>
				</div>

			<?php endif; ?>


			<div class="control-group" id="row_access">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_ACCESS' ); ?>
					</label>
				</div>
				<div class="controls">
					<?php echo $this->lists['access']; ?>
				</div>
			</div>


			<fieldset class="panelform">
				<br/><span class="alert alert-info fc-iblock" style="margin-bottom: 4px;"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_ASSIGNMENTS'); ?></span>
			</fieldset>


			<div class="control-group" id="row_keeptags">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_TAGS_ACTION' ); ?>
					</label>
				</div>
				<div class="controls">
					<div class="group-fcset fc_input_set">
						<input id="keeptags0" type="radio" name="keeptags" value="0" onclick="tags_update();" />
						<label for="keeptags0"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_TAGS_REMOVE_ONLY' ); ?></label>

						<input id="keeptags1" type="radio" name="keeptags" value="1" onclick="tags_update();" />
						<label for="keeptags1"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_TAGS_REPLACE' ); ?></label>

						<input id="keeptags2" type="radio" name="keeptags" value="2" checked="checked" onclick="tags_update();" />
						<label for="keeptags2"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_TAGS_ADD_NO_DUPLICATES' ); ?></label>
					</div>
				</div>
			</div>

			<div class="control-group" id="row_tags">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_TAGS' ); ?>
					</label>
				</div>
				<div class="controls">
					<?php echo $this->lists['tags']; ?>
				</div>
			</div>


			<div class="control-group" id="row_maincat">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_MAIN_CATEGORY' ); ?>
					</label>
				</div>
				<div class="controls">
					<?php echo $this->lists['maincat']; ?>
				</div>
			</div>


			<div class="control-group" id="row_keepseccats">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_KEEP_SEC_CATS' ); ?>
					</label>
				</div>
				<div class="controls">
					<div class="group-fcset fc_input_set">
						<input id="keepseccats0" type="radio" name="keepseccats" value="0" onclick="seccats_on();" />
						<label for="keepseccats0"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_NO' ); ?></label>

						<input id="keepseccats1" type="radio" name="keepseccats" value="1" onclick="seccats_off();" />
						<label for="keepseccats1"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_YES' ); ?></label>
					</div>
				</div>
			</div>


			<div class="control-group" id="row_seccats">
				<div class="control-label">
					<label class="label-fcinner">
						<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_SECONDARY_CATEGORIES' ); ?>
					</label>
				</div>
				<div class="controls">
					<?php echo $this->lists['seccats']; ?>
				</div>
			</div>


			<?php if ($this->behaviour !== 'translate' && !empty($this->batch_fields)) :
				$batch_types = flexicontent_html::getTypesList();
				$batch_field_url = 'index.php?option=com_flexicontent&task=items.batchfieldform&format=raw&' . \Joomla\CMS\Session\Session::getFormToken() . '=1';
				$batch_modes = array(
					'add'     => 'FLEXI_BATCH_FIELD_MODE_ADD',
					'replace' => 'FLEXI_BATCH_FIELD_MODE_REPLACE',
					'fill'    => 'FLEXI_BATCH_FIELD_MODE_FILL',
					'clear'   => 'FLEXI_BATCH_FIELD_MODE_CLEAR',
				);
			?>

				<style>
					#batch_field_cards { margin: 12px 0 0 0; }
					#batch_field_cards .batch-field-card { border: 1px solid #c4cdd9; border-radius: 4px; background: rgba(255,255,255,0.55); padding: 10px 14px 12px; margin: 0 0 12px 0; }
					#batch_field_cards .batch-field-head { display: flex; flex-wrap: nowrap; align-items: center; gap: 12px; padding-bottom: 8px; margin-bottom: 10px; border-bottom: 1px solid #d5dce6; }
					#batch_field_cards .batch-field-label { flex: 1 1 auto; min-width: 0; font-size: 1.05em; }
					#batch_field_cards .batch-field-mode { flex: 0 0 auto; width: auto; min-width: 190px; margin: 0; }
					#batch_field_cards .batch-field-remove { flex: 0 0 auto; margin: 0; line-height: 1; }
					#batch_field_cards .batch-field-body { min-width: 0; display: flow-root; }
					#batch_field_cards .batch-field-body .choices,
					#batch_field_cards .batch-field-body .select2-container { width: 100% !important; max-width: 100%; }
					#batch_field_cards .batch-field-body input[type=text],
					#batch_field_cards .batch-field-body textarea { max-width: 100%; }
					#batch_field_cards .batch-field-card.fc-loading .batch-field-body { min-height: 36px; opacity: .5; }
				</style>

				<fieldset class="panelform" id="row_batch_fields_title">
					<br/><span class="alert alert-info fc-iblock" style="margin-bottom: 4px;"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_BATCH_CUSTOM_FIELDS'); ?></span>
				</fieldset>

				<div class="control-group" id="row_batch_fields">
					<div class="control-label">
						<label class="label-fcinner" for="batch_field_picker">
							<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_BATCH_ADD_FIELD' ); ?>
						</label>
					</div>
					<div class="controls">
						<select id="batch_field_picker" class="use_select2_lib" data-placeholder="<?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('FLEXI_BATCH_SELECT_FIELD'), ENT_QUOTES, 'UTF-8'); ?>">
							<option value=""></option>
							<?php foreach ($this->batch_fields as $batch_field) :
								$type_names = array();
								foreach ($batch_field->type_ids as $tid)
								{
									if (isset($batch_types[$tid])) $type_names[] = \Joomla\CMS\Language\Text::_($batch_types[$tid]->name);
								}
							?>
								<option value="<?php echo (int) $batch_field->id; ?>" data-types="<?php echo implode(',', $batch_field->type_ids); ?>" data-multiple="<?php echo (int) $batch_field->multiple; ?>">
									<?php echo htmlspecialchars($batch_field->label . ($type_names ? '  (' . implode(', ', $type_names) . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<div class="fc-small" style="margin-top: 4px;"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_BATCH_FIELDS_INFO'); ?></div>
					</div>
				</div>

				<div id="batch_field_cards" data-url="<?php echo htmlspecialchars($batch_field_url, ENT_QUOTES, 'UTF-8'); ?>">
					<template id="batch_field_card_tmpl">
						<div class="batch-field-card" data-field-id="">
							<div class="batch-field-head">
								<strong class="batch-field-label"></strong>
								<select class="batch-field-mode" disabled="disabled">
									<?php foreach ($batch_modes as $mode => $mode_lbl) : ?>
										<option value="<?php echo $mode; ?>"><?php echo \Joomla\CMS\Language\Text::_($mode_lbl); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="button" class="btn btn-small batch-field-remove" title="<?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('JACTION_DELETE'), ENT_QUOTES, 'UTF-8'); ?>">&times;</button>
							</div>
							<div class="batch-field-body"></div>
						</div>
					</template>
				</div>

			<?php endif; ?>

		</fieldset>

	</div>



	<?php if (!$isQuicktranslateSingle || count($this->rows) > 1): ?>
	<div class="<?php echo $box_span_classes; ?> full_width_980" style="margin-bottom: 16px !important;">

		<fieldset>
			<legend><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_ITEMS' ); ?></legend>
			<?php echo $items_info_html; ?>
		</fieldset>

	</div>
	<?php endif; ?>



</div>

<?php echo implode("\n", $submit_cids); ?>
<?php if ($this->behaviour === 'translate'): ?>
<style>
#fc_batch_progress { display: none; position: fixed; z-index: 99999; left: 0; top: 0; right: 0; bottom: 0; background: rgba(255,255,255,.9); overflow: auto; }
#fc_batch_progress.fc-active { display: block; }
#fc_batch_progress .fc-batch-progress-box { max-width: 640px; margin: 24px auto; padding: 0 16px; }
#fc_batch_progress .fc-batch-progress-head { margin-bottom: 16px; }
#fc_batch_progress #fc_bp_bar { width: 302px; height: 17px; border: 1px solid #000; padding: 0; margin: 0 10px 0 0; float: left; clear: left; background: #fff; }
#fc_batch_progress #fc_bp_inside { width: 0; height: 15px; margin: 1px; padding: 0; background-color: #000; transition: width .2s; }
#fc_batch_progress #fc_bp_percent { clear: right; }
#fc_batch_progress #fc_bp_status { clear: both; color: darkgreen; padding-top: 24px; }
#fc_batch_progress #fc_bp_errors { color: #b00; margin-top: 12px; }
#fc_batch_progress #fc_bp_close { display: none; margin-top: 20px; }
</style>
<div id="fc_batch_progress" role="alert" aria-live="polite">
	<div class="fc-batch-progress-box">
		<div class="fc-batch-progress-head" id="fc_bp_head"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_TASK_RUNNING'); ?> ...</div>
		<div id="fc_bp_bar"><div id="fc_bp_inside"></div></div>
		<div id="fc_bp_percent">0 %</div>
		<div id="fc_bp_status"></div>
		<div id="fc_bp_errors"></div>
		<button type="button" class="btn btn-success" id="fc_bp_close"><?php echo \Joomla\CMS\Language\Text::_('JTOOLBAR_CLOSE'); ?></button>
	</div>
</div>
<script>
/**
 * Translate to other languages, one request per (item, language) pair, so that a real progress can be shown
 * (same approach as the Re-Index tools, which call the server in small steps). Each request is the same
 * 'batchprocess' task as the normal form submit, just with a single item and a single language.
 */
(function () {
	var form = document.getElementById('adminForm');
	if (!form || !window.fetch || !window.FormData || !window.Joomla) return;

	var itemTitles = <?php
		$_fc_titles = array();
		foreach ($this->rows as $_fc_row) $_fc_titles[(int) $_fc_row->id] = $_fc_row->title;
		echo json_encode($_fc_titles, JSON_FORCE_OBJECT);
	?>;
	var txt = {
		done:  <?php echo json_encode(\Joomla\CMS\Language\Text::_('FLEXI_BATCH_TRANSLATING_DONE')); ?>,
		items: <?php echo json_encode(\Joomla\CMS\Language\Text::_('FLEXI_ITEMS')); ?>,
		created: <?php echo json_encode(\Joomla\CMS\Language\Text::_('FLEXI_BATCH_TRANSLATING_CREATED')); ?>,
		time:  <?php echo json_encode(\Joomla\CMS\Language\Text::_('FLEXI_BATCH_TOTAL_TASK_TIME')); ?>,
		back:  <?php echo json_encode(\Joomla\CMS\Language\Text::_('FLEXI_BATCH_BACK_TO_ITEMS')); ?>
	};
	var quick = <?php echo json_encode((bool) $isQuicktranslateMode); ?>;   // Modal: item(s) to multiple languages. Otherwise: items to the single chosen language
	var returnUrl = 'index.php?option=com_flexicontent&view=items';
	var running = false;

	function $(id) { return document.getElementById(id); }
	function esc(str) { var d = document.createElement('div'); d.textContent = str; return d.innerHTML; }

	// Returns false when there is nothing to do (let the normal form submit / validation handle it)
	function start() {
		var cids  = Array.prototype.map.call(form.querySelectorAll('input[name="cid[]"]'), function (el) { return el.value; });
		var langs;

		if (quick) {
			langs = Array.prototype.map.call(form.querySelectorAll('input[name="languages[]"]:checked'), function (el) { return el.value; });
		} else {
			var langEl = form.querySelector('input[name="language"]:checked') || form.querySelector('select[name="language"]');
			langs = langEl && langEl.value && langEl.value !== '*' ? [langEl.value] : [];
		}

		if (!langs.length || !cids.length) return false;
		if (running) return true;
		running = true;

		var btn = $('fc_add_translations_btn');
		if (btn) btn.disabled = true;

		var jobs = [];
		cids.forEach(function (id) { langs.forEach(function (lang) { jobs.push({ id: id, lang: lang }); }); });

		var total = jobs.length, done = 0, created = 0, errors = [], t0 = Date.now();
		$('fc_batch_progress').classList.add('fc-active');

		function render(current) {
			var percent = total ? (done / total * 100) : 100;
			$('fc_bp_inside').style.width = Math.min(300, percent * 3) + 'px';
			$('fc_bp_percent').textContent = ' ' + percent.toFixed(2) + ' %';
			$('fc_bp_status').innerHTML = done + ' / ' + total + ' ' + esc(txt.created)
				+ (current ? '<br/><br/>' + current : '')
				+ '<br/><br/>' + esc(txt.time) + ': ' + ((Date.now() - t0) / 1000).toFixed(2) + ' secs';
			$('fc_bp_errors').innerHTML = errors.map(esc).join('<br/>');
		}

		function finish() {
			$('fc_bp_head').innerHTML = '<strong>' + esc(txt.done.replace('%s', created)) + '</strong>';
			render('');
			var closeBtn = $('fc_bp_close');
			closeBtn.style.display = 'inline-block';
			closeBtn.onclick = quick
				? function () { $('fc_batch_progress').classList.remove('fc-active'); }
				: function () { window.location.href = returnUrl; };
			if (!quick) closeBtn.textContent = txt.back;
			running = false;
		}

		function next() {
			if (done >= total) return finish();

			var job = jobs[done];
			var title = itemTitles[job.id] || ('#' + job.id);
			render(esc(title) + ' &rarr; ' + esc(job.lang));

			var fd = new FormData(form);
			fd.delete('cid[]');
			fd.append('cid[]', job.id);
			if (quick) {
				fd.delete('languages[]');
				fd.append('languages[]', job.lang);
			}
			fd.set('task', 'batchprocess');
			fd.set('fc_ajax', '1');

			fetch(form.getAttribute('action') || 'index.php', { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.text(); })
				.then(function (body) {
					var data = null;
					try { data = JSON.parse(body); } catch (ex) {}

					if (data && data.ok) {
						created += parseInt(data.created, 10) || 0;
					} else if (data) {
						errors.push(title + ' (' + job.lang + '): ' + ((data.errors || []).join(' ') || data.message || 'no translation created'));
					} else {
						var tmp = document.createElement('div');
						tmp.innerHTML = body;
						errors.push(title + ' (' + job.lang + '): ' + ((tmp.textContent || '').trim().replace(/\s+/g, ' ') || 'HTTP error').substring(0, 300));
					}
				})
				.catch(function (err) {
					errors.push(title + ' (' + job.lang + '): ' + err);
				})
				.then(function () {
					done++;
					setTimeout(next, 20);
				});
		}

		next();
		return true;
	}

	// Hook the form submit (both the toolbar 'Save' button, via Joomla.submitform() that clicks a hidden submit button, and the modal's own button).
	// stopImmediatePropagation(): other submit handlers (AJAX / 'form is being submitted' blocker added by admin.js) must not run for our stepped requests
	form.addEventListener('submit', function (e) {
		var task = form.task && typeof form.task.value !== 'undefined' ? form.task.value : '';
		if (!/(^|\.)batchprocess$/.test(task)) return;

		if (start()) {
			e.preventDefault();
			e.stopImmediatePropagation();
		}
	});
})();
</script>
<?php endif; ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="controller" value="items" />
<input type="hidden" name="view" value="items" />
<input type="hidden" name="task" value="" />
<?php echo \Joomla\CMS\HTML\HTMLHelper::_( 'form.token' ); ?>
</form>
</div>
