<?php
/**
 * @package         FLEXIcontent
 *
 * @link            https://flexicontent.org
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;

/**
 * Report of the tags mass import, included by the raw tags controller
 *
 * @var array $logs  The result of FlexicontentModelTags::importList()
 */

$escape = static function ($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$groups = array(
	'created'  => 'FLEXI_TAG_IMPORT_REPORT_CREATED',
	'existing' => 'FLEXI_TAG_IMPORT_REPORT_EXISTING',
	'failed'   => 'FLEXI_TAG_IMPORT_REPORT_FAILED',
);

$csv_row = static function (array $values) {
	return implode(',', array_map(static function ($value) {
		return '"' . str_replace('"', '""', (string) $value) . '"';
	}, $values));
};

$sections = array();
$csv_rows = array($csv_row(array(Text::_('FLEXI_ID'), Text::_('FLEXI_TAG_NAME'), Text::_('JSTATUS'))));

foreach ($groups as $group => $label)
{
	if (empty($logs[$group]))
	{
		continue;
	}

	$ids = $names = array();

	foreach ($logs[$group] as $tag)
	{
		// Failed tags are plain names, they have no ID
		if (is_object($tag))
		{
			$ids[]   = $tag->id;
			$names[] = $tag->name;
			$csv_rows[] = $csv_row(array($tag->id, $tag->name, Text::_($label)));
		}
		else
		{
			$names[]    = $tag;
			$csv_rows[] = $csv_row(array('', $tag, Text::_($label)));
		}
	}

	$title = Text::_($label) . ' (' . count($names) . ')';

	$sections[$group] = array('title' => $title, 'ids' => $ids, 'names' => $names);
}

$file_name = 'tags-import-' . \Joomla\CMS\HTML\HTMLHelper::_('date', 'now', 'Y-m-d-His') . '.csv';
?>
<p class="fc-import-report-reopen">
	<button type="button" class="btn btn-primary" data-report-open><?php echo Text::_('FLEXI_TAG_IMPORT_REPORT_SHOW'); ?></button>
</p>

<dialog class="fc-import-report" aria-labelledby="fc-import-report-title">
	<h2 id="fc-import-report-title"><?php echo Text::_('FLEXI_TAG_IMPORT_REPORT'); ?></h2>

	<?php if (!$sections) : ?>
		<p><?php echo Text::_('FLEXI_NO_TAG_TO_IMPORT'); ?></p>
	<?php elseif (empty($sections['created'])) : ?>
		<p class="alert alert-warning"><?php echo Text::_('FLEXI_TAG_IMPORT_REPORT_NOTHING_NEW'); ?></p>
	<?php endif; ?>

	<?php foreach ($sections as $group => $section) : ?>
		<section class="fc-import-report-<?php echo $group; ?>">
			<h3><?php echo $escape($section['title']); ?></h3>
			<div class="fc-import-report-lists">
				<div class="fc-import-report-names">
					<textarea readonly wrap="off" rows="<?php echo min(10, max(3, count($section['names']) + 1)); ?>" aria-label="<?php echo $escape(Text::_('FLEXI_TAG_NAME')); ?>"><?php echo "\n" . $escape(implode("\n", $section['names'])); ?></textarea>
					<button type="button" class="btn btn-secondary btn-sm" data-report-copy><?php echo Text::_('FLEXI_TAG_IMPORT_COPY_NAMES'); ?></button>
				</div>
				<?php if ($section['ids']) : ?>
				<div class="fc-import-report-ids">
					<textarea readonly wrap="off" rows="<?php echo min(10, max(3, count($section['ids']) + 1)); ?>" aria-label="<?php echo $escape(Text::_('FLEXI_ID')); ?>"><?php echo "\n" . implode("\n", $section['ids']); ?></textarea>
					<button type="button" class="btn btn-secondary btn-sm" data-report-copy><?php echo Text::_('FLEXI_TAG_IMPORT_COPY_IDS'); ?></button>
				</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endforeach; ?>

	<textarea class="fc-import-report-file" hidden><?php echo "\n" . $escape(implode("\n", $csv_rows) . "\n"); ?></textarea>

	<div class="fc-import-report-footer">
		<?php if (FlexicontentHelperPerm::getPerm()->CanConfig) : ?>
		<button type="button" class="btn btn-primary fc-import-report-verify-btn" data-report-verify="<?php echo $escape(\Joomla\CMS\Uri\Uri::base(true) . '/index.php?option=com_flexicontent&view=tags&layout=indexer&tmpl=component&indexer=tag_mappings'); ?>" title="<?php echo $escape(strip_tags(Text::_('FLEXI_VERIFY_TAG_MAPPINGS_DESC'))); ?>">
			<span class="icon-loop" aria-hidden="true"></span> <?php echo Text::_('FLEXI_VERIFY_TAG_MAPPINGS'); ?>
		</button>
		<?php endif; ?>
		<?php if ($sections) : ?>
		<button type="button" class="btn btn-success" data-report-download="<?php echo $escape($file_name); ?>"><?php echo Text::_('FLEXI_TAG_IMPORT_DOWNLOAD_CSV'); ?></button>
		<?php endif; ?>
		<button type="button" class="btn btn-secondary" data-report-close><?php echo Text::_('JCLOSE'); ?></button>
	</div>
</dialog>

<script>
FlexiAdmin.showImportReport(document.getElementById('log-bind'), <?php echo json_encode(Text::_('FLEXI_TAG_IMPORT_COPIED'), JSON_HEX_TAG); ?>);
</script>
