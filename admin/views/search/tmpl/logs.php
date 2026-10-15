<?php
/**
 * @package         FLEXIcontent
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;

$app    = \Joomla\CMS\Factory::getApplication();
$db     = \Joomla\CMS\Factory::getDbo();
$order  = $app->input->getCmd('order', 'hits') === 'term' ? 'term' : 'hits';
$dir    = strtoupper($app->input->getCmd('dir', $order === 'hits' ? 'DESC' : 'ASC')) === 'ASC' ? 'ASC' : 'DESC';
$filter = trim($app->input->getString('q', ''));
$export = $app->input->getInt('export', 0);
$limit  = $app->input->getInt('limit', 50);
$limit  = in_array($limit, array(25, 50, 100), true) ? $limit : 50;
$page   = max(1, $app->input->getInt('page', 1));

$canPurge = \Joomla\CMS\Factory::getUser()->authorise('core.admin', 'com_flexicontent');

if ($canPurge && $app->input->getInt('purge', 0) && $app->input->getMethod() === 'POST')
{
	\Joomla\CMS\Session\Session::checkToken('post') or die(Text::_('JINVALID_TOKEN'));

	try
	{
		$db->setQuery('TRUNCATE TABLE #__flexicontent_search_log')->execute();
	}
	catch (\Exception $e)
	{
		// Table not created yet, nothing to purge
	}

	$app->redirect('index.php?option=com_flexicontent&view=search&layout=logs&tmpl=component');
}

$query = 'SELECT search_term, hits FROM #__flexicontent_search_log';

if ($filter !== '')
{
	$query .= ' WHERE search_term LIKE ' . $db->Quote('%' . $db->escape($filter, true) . '%', false);
}

$query .= ' ORDER BY ' . ($order === 'hits' ? 'hits' : 'search_term') . ' ' . $dir . ', search_term ASC';

$total = 0;

try
{
	if ($export)
	{
		// CSV export has no row limit
		$rows = $db->setQuery($query)->loadObjectList();
	}
	else
	{
		$countQuery = str_replace('SELECT search_term, hits FROM', 'SELECT COUNT(*) FROM', $query);
		$countQuery = substr($countQuery, 0, strpos($countQuery, ' ORDER BY'));
		$total      = (int) $db->setQuery($countQuery)->loadResult();
		$pages      = max(1, (int) ceil($total / $limit));
		$page       = min($page, $pages);
		$rows       = $db->setQuery($query, ($page - 1) * $limit, $limit)->loadObjectList();
	}
}
catch (\Exception $e)
{
	$rows = array();   // Table not created yet, no searches logged
}

if ($export)
{
	while (ob_get_level())
	{
		ob_end_clean();
	}

	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="search-log-' . date('Y-m-d') . '.csv"');

	$out = fopen('php://output', 'w');
	fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM, so that Excel detects the encoding
	fputcsv($out, array('search_term', 'hits'));

	foreach ($rows as $row)
	{
		// Prefix formula-like terms, so that spreadsheets do not execute them
		$term = preg_match('/^[=+\-@\t\r]/', $row->search_term) ? "'" . $row->search_term : $row->search_term;
		fputcsv($out, array($term, (int) $row->hits));
	}

	fclose($out);
	$app->close();
}

$pages = isset($pages) ? $pages : 1;
$base  = 'index.php?option=com_flexicontent&view=search&layout=logs&tmpl=component&limit=' . $limit . ($filter !== '' ? '&q=' . rawurlencode($filter) : '');
$pageLink = function ($n, $label) use ($base, $order, $dir)
{
	return '<a class="fclog-btn fclog-btn-sm" href="' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '&amp;order=' . $order . '&amp;dir=' . $dir . '&amp;page=' . $n . '">' . $label . '</a> ';
};
$sortLink = function ($col, $label) use ($base, $order, $dir)
{
	$newDir = ($order === $col && $dir === 'DESC') ? 'ASC' : 'DESC';
	$arrow  = $order === $col ? ($dir === 'DESC' ? ' &#9660;' : ' &#9650;') : '';
	return '<a href="' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '&amp;order=' . $col . '&amp;dir=' . $newDir . '">' . $label . $arrow . '</a>';
};
$csvLink = htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '&amp;order=' . $order . '&amp;dir=' . $dir . '&amp;export=1';
?>
<style>
	.fclog-btn { display: inline-block; padding: 6px 14px; border: 1px solid #adb5bd; border-radius: 4px; background: #f5f6f7; color: #212529; font: inherit; line-height: 1.4; cursor: pointer; text-decoration: none; vertical-align: middle; }
	.fclog-btn:hover { background: #e4e7ea; color: #212529; text-decoration: none; }
	.fclog-btn-primary { background: #2a69b8; border-color: #2a69b8; color: #fff; }
	.fclog-btn-primary:hover { background: #1f5399; color: #fff; }
	.fclog-btn-danger { background: #fff; border-color: #c0392b; color: #c0392b; }
	.fclog-btn-danger:hover { background: #c0392b; color: #fff; }
	.fclog-btn-sm { padding: 2px 9px; }
	.fclog-btn.disabled { pointer-events: none; }
</style>
<div style="padding: 10px;">
	<form action="index.php" method="get" style="margin-bottom: 12px;">
		<input type="hidden" name="option" value="com_flexicontent" />
		<input type="hidden" name="view" value="search" />
		<input type="hidden" name="layout" value="logs" />
		<input type="hidden" name="tmpl" value="component" />
		<input type="hidden" name="order" value="<?php echo $order; ?>" />
		<input type="hidden" name="dir" value="<?php echo $dir; ?>" />
		<input type="hidden" name="limit" value="<?php echo $limit; ?>" />

		<input type="text" name="q" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(Text::_('FLEXI_SEARCH_LOG_FILTER'), ENT_QUOTES, 'UTF-8'); ?>" style="min-width: 260px;" />
		<button type="submit" class="fclog-btn fclog-btn-primary"><?php echo Text::_('FLEXI_SEARCH'); ?></button>
		<a class="fclog-btn" href="index.php?option=com_flexicontent&amp;view=search&amp;layout=logs&amp;tmpl=component&amp;limit=<?php echo $limit; ?>&amp;order=<?php echo $order; ?>&amp;dir=<?php echo $dir; ?>"><?php echo Text::_('FLEXI_RESET'); ?></a>
		<a class="fclog-btn" style="margin-left: 16px;" href="<?php echo $csvLink; ?>"><?php echo Text::_('FLEXI_SEARCH_LOG_DOWNLOAD_CSV'); ?></a>
		<?php if ($canPurge && $total) : ?>
		<button type="submit" form="fclog-purge-form" class="fclog-btn fclog-btn-danger" style="float: right;"><?php echo Text::_('FLEXI_SEARCH_LOG_PURGE'); ?></button>
		<?php endif; ?>
	</form>

	<?php if ($canPurge && $total) : ?>
	<form id="fclog-purge-form" action="index.php?option=com_flexicontent&amp;view=search&amp;layout=logs&amp;tmpl=component" method="post" style="display: none;"
		onsubmit="return confirm('<?php echo htmlspecialchars(Text::_('FLEXI_SEARCH_LOG_PURGE_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>');">
		<input type="hidden" name="purge" value="1" />
		<?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
	</form>
	<?php endif; ?>

	<table class="table table-striped" style="width:100%;">
		<thead>
			<tr>
				<th><?php echo $sortLink('term', Text::_('FLEXI_SEARCH_LOG_TERM')); ?></th>
				<th style="width:120px; text-align:right;"><?php echo $sortLink('hits', Text::_('FLEXI_SEARCH_LOG_HITS')); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($rows as $row) : ?>
			<tr>
				<td><?php echo htmlspecialchars($row->search_term, ENT_QUOTES, 'UTF-8'); ?></td>
				<td style="text-align:right;"><?php echo (int) $row->hits; ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if (!$rows) : ?>
			<tr><td colspan="2"><?php echo Text::_('FLEXI_SEARCH_LOG_EMPTY'); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<?php if ($total) : ?>
	<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
		<div>
			<?php echo ($page - 1) * $limit + 1; ?>&ndash;<?php echo min($page * $limit, $total); ?> / <?php echo $total; ?>
		</div>
		<div>
			<?php
			if ($page > 1) echo $pageLink($page - 1, '&lsaquo;');
			$from = max(1, $page - 3);
			$to   = min($pages, $page + 3);
			if ($from > 1) echo $pageLink(1, '1') . ($from > 2 ? '&hellip; ' : '');
			for ($n = $from; $n <= $to; $n++)
			{
				echo $n === $page ? '<span class="fclog-btn fclog-btn-sm fclog-btn-primary disabled">' . $n . '</span> ' : $pageLink($n, $n);
			}
			if ($to < $pages) echo ($to < $pages - 1 ? '&hellip; ' : '') . $pageLink($pages, $pages);
			if ($page < $pages) echo $pageLink($page + 1, '&rsaquo;');
			?>
		</div>
		<div>
			<?php foreach (array(25, 50, 100) as $l) : ?>
				<?php echo $l === $limit
					? '<strong>' . $l . '</strong> '
					: '<a href="' . htmlspecialchars(str_replace('&limit=' . $limit, '&limit=' . $l, $base), ENT_QUOTES, 'UTF-8') . '&amp;order=' . $order . '&amp;dir=' . $dir . '">' . $l . '</a> '; ?>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>
</div>
