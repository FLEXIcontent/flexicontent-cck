<?php
/**
 * File library opened by the image, file and local media field pickers.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$label = static fn ($key) => htmlspecialchars(Text::_($key), ENT_QUOTES, 'UTF-8');
$state = $this->getModel()->getState();
$owner = (string) $state->get('filter_uploader', '');
$ownFilesOnly = !$this->CanViewAllFiles || ($this->assign_mode && $this->field->parameters->get('limit_by_uploader', 0));
$sorts = [
	'a.id' => 'FLEXI_ID', 'a.filename_displayed' => 'FLEXI_FILENAME', 'a.altname' => 'FLEXI_FILE_DISPLAY_TITLE',
	'a.size' => 'FLEXI_SIZE', 'a.uploaded' => 'FLEXI_UPLOAD_TIME', 'a.hits' => 'FLEXI_HITS',
];
foreach ([1, 2] as $n) {
	// Text::_() returns an unknown string (the heading of the property) unchanged
	if (!empty($this->cols['custom' . $n])) $sorts['a.custom' . $n] = $this->custom_headings[$n]['label'];
}
// Number of active filters of the filters panel, 'My files' / 'All files' have their own buttons
$panelFilters = [];
if ($this->layout !== 'image') {
	foreach (['state', 'access', 'lang', 'stamp'] as $col) {
		if (!empty($this->cols[$col])) $panelFilters[] = 'filter_' . $col;
	}
	if (isset($this->lists['filter_url'])) $panelFilters[] = 'filter_url';
	if (!empty($this->cols['target']) && !$dbFolder) $panelFilters[] = 'filter_secure';
}
if (!$ownFilesOnly && $owner !== (string) $user->id) $panelFilters[] = 'filter_uploader';
$panelFilters[] = 'filter_item';
$activeFilters = count(array_filter($panelFilters, static fn ($name) => (string) $state->get($name, '') !== ''));
$filtersTitle = $label('FLEXI_FILTERS') . ($activeFilters ? ' (' . $activeFilters . ')' : '');
$order = $this->lists['order'];
$ascending = strtolower($this->lists['order_Dir']) === 'asc';
$gridSizes = [90, 120, 150, 200, 250];
$total = $this->folder_mode ? count($this->rows) : (int) $this->pagination->total;
?>
<div class="fc-picker-bar" role="group" aria-label="<?php echo $label('FLEXI_PICKER_MY_FILES'); ?>">
	<?php if (!$this->folder_mode) : ?>
	<div class="fc-picker-find" role="search">
		<div class="fc-picker-segment" role="group" aria-label="<?php echo $label('FLEXI_UPLOADER'); ?>">
			<button type="button" data-picker-owner="<?php echo (int) $user->id; ?>" aria-pressed="<?php echo $ownFilesOnly || $owner === (string) $user->id ? 'true' : 'false'; ?>" <?php echo $ownFilesOnly ? 'disabled' : ''; ?>><?php echo $label('FLEXI_PICKER_MY_FILES'); ?></button>
			<?php if (!$ownFilesOnly) : ?>
			<button type="button" data-picker-owner="" aria-pressed="<?php echo $owner === '' ? 'true' : 'false'; ?>"><?php echo $label('FLEXI_PICKER_ALL_FILES'); ?></button>
			<?php endif; ?>
		</div>
		<div class="fc-picker-search">
			<?php echo $this->lists['scope']; ?>
			<button type="button" data-picker-search aria-label="<?php echo $label('FLEXI_SEARCH'); ?>"><span class="icon-search" aria-hidden="true"></span></button>
			<input type="search" id="search" name="search" aria-label="<?php echo $label('FLEXI_PICKER_SEARCH'); ?>" placeholder="<?php echo $label('FLEXI_PICKER_SEARCH'); ?>" value="<?php echo $this->escape($this->lists['search']); ?>">
		</div>
		<?php echo $this->lists['picker_ext']; ?>
		<details class="fc-picker-filters">
			<summary class="fc-picker-tool" aria-label="<?php echo $filtersTitle; ?>" title="<?php echo $filtersTitle; ?>"><span class="icon-filter" aria-hidden="true"></span><?php if ($activeFilters) : ?><span class="fc-picker-filter-count" aria-hidden="true"><?php echo $activeFilters; ?></span><?php endif; ?></summary>
			<div class="fc-picker-filter-panel">
				<?php
				if ($this->layout !== 'image')
				{
					foreach (['state', 'access', 'lang', 'stamp'] as $col)
					{
						if (!empty($this->cols[$col])) echo $this->lists['filter_' . $col];
					}
					echo $this->lists['filter_url'] ?? '';
					if (!empty($this->cols['target']) && !$dbFolder) echo $this->lists['filter_secure'];
				}
				if (!$ownFilesOnly) echo $this->lists['filter_uploader'] ?? '';
				echo $this->lists['item_id'] ?? '';
				?>
				<button type="button" class="fc-picker-tool" data-picker-reset><?php echo $label('FLEXI_RESET_FILTERS'); ?></button>
			</div>
		</details>
		<select class="form-select" data-picker-sort aria-label="<?php echo $label('JGLOBAL_SORT_BY'); ?>">
			<?php if ($order && !isset($sorts[$order])) : ?><option selected value="<?php echo $this->escape($order); ?>"><?php echo $label('JGLOBAL_ORDERING'); ?></option><?php endif; ?>
			<?php foreach ($sorts as $value => $key) : ?>
			<option value="<?php echo $value; ?>" <?php echo $value === $order ? 'selected' : ''; ?>><?php echo $label($key); ?></option>
			<?php endforeach; ?>
		</select>
		<select class="form-select" data-picker-direction aria-label="<?php echo $label('FLEXI_PICKER_SORT_DIRECTION'); ?>">
			<option value="asc" <?php echo $ascending ? 'selected' : ''; ?>><?php echo $label('FLEXI_PICKER_ASCENDING'); ?></option>
			<option value="desc" <?php echo !$ascending ? 'selected' : ''; ?>><?php echo $label('FLEXI_PICKER_DESCENDING'); ?></option>
		</select>
	</div>
	<?php endif; ?>
	<div class="fc-picker-tools">
		<div class="fc-picker-segment" role="group" aria-label="<?php echo $label('FLEXI_PICKER_VIEW'); ?>">
			<?php foreach (['grid' => ['icon-grid-view', 'FLEXI_FILEMAN_GRID'], 'list' => ['icon-list-view', 'FLEXI_FILEMAN_DETAILS']] as $mode => [$icon, $key]) : ?>
			<button type="button" id="btn-fman-<?php echo $mode; ?>-view" class="<?php echo $mode; ?>-view<?php echo $mode === 'list' ? ' active' : ''; ?>" data-toggle_selector=".fman_<?php echo $mode; ?>_element" aria-label="<?php echo $label($key); ?>" title="<?php echo $label($key); ?>" aria-pressed="<?php echo $mode === 'list' ? 'true' : 'false'; ?>"><span class="<?php echo $icon; ?>" aria-hidden="true"></span></button>
			<?php endforeach; ?>
		</div>
		<label class="fman_grid_element" style="display:none">
			<input type="range" min="0" max="4" value="<?php echo (int) array_search($thumb_size['fm-grid'], $gridSizes); ?>" data-picker-sizes="<?php echo implode(',', $gridSizes); ?>" aria-label="<?php echo $label('FLEXI_PICKER_THUMB_SIZE'); ?>">
		</label>
		<?php if (!$this->folder_mode) :
			$pagination_footer = $this->pagination->getListFooter();
			if (strpos($pagination_footer, '"limit"') === false) echo $this->pagination->getLimitBox();
		endif; ?>
	</div>
	<div class="fc-picker-actions">
		<label class="fc-picker-all">
			<input type="checkbox" name="checkall-toggle" id="checkall-toggle" onclick="Joomla.checkAll(this); fman_set_cids(this);" aria-label="<?php echo $label('JGLOBAL_CHECK_ALL'); ?>">
			<?php echo Text::plural('FLEXI_PICKER_FILE_COUNT', $total); ?>
		</label>
		<button type="button" class="fc-picker-tool" data-picker-delete onclick="fc_fileselement_delete_files()" title="<?php echo $label('FLEXI_DELETE'); ?>" aria-label="<?php echo $label('FLEXI_DELETE'); ?>"><span class="icon-trash" aria-hidden="true"></span></button>
		<button type="button" class="btn btn-primary" id="insert_selected_btn" onclick="fc_fileselement_assign_files()"><span class="icon-plus" aria-hidden="true"></span> <?php echo $label('FLEXI_INSERT'); ?> <span data-picker-count></span></button>
		<?php if ($picker_dashboard) : ?>
		<button type="button" class="btn btn-primary" data-fc-upload-dashboard aria-haspopup="dialog" onclick="<?php echo $uploader_tag_id; ?>.openDashboard('<?php echo $up_sfx_n; ?>', this)"><span class="icon-upload" aria-hidden="true"></span> <?php echo $label('FLEXI_UPLOAD_FILES'); ?></button>
		<?php endif; ?>
		<?php if ($has_link_tab || (!$picker_dashboard && $can_upload)) : ?>
		<button type="button" class="fc-picker-tool" aria-haspopup="dialog" onclick="fc_showAsDialog(document.getElementById('fc-fileman-addfiles'), 0, 0, null, {title: this.textContent.trim(), visibleOnClose: 0})"><?php echo $label(!$picker_dashboard && $can_upload ? 'FLEXI_ADD_FILE' : ($use_jmedia_man ? 'FLEXI_ADD_LINK' : 'FLEXI_ADD_URL')); ?></button>
		<?php endif; ?>
	</div>
</div>
