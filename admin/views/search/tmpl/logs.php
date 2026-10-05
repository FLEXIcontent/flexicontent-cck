<?php
/**
 * @package         FLEXIcontent
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

$app   = \Joomla\CMS\Factory::getApplication();
$db    = \Joomla\CMS\Factory::getDbo();
$order = $app->input->getCmd('order', 'hits') === 'term' ? 'term' : 'hits';
$dir   = strtoupper($app->input->getCmd('dir', $order === 'hits' ? 'DESC' : 'ASC')) === 'ASC' ? 'ASC' : 'DESC';

try
{
	$rows = $db->setQuery(
		'SELECT search_term, hits FROM #__flexicontent_search_log'
		. ' ORDER BY ' . ($order === 'hits' ? 'hits' : 'search_term') . ' ' . $dir . ', search_term ASC',
		0, 1000
	)->loadObjectList();
}
catch (\Exception $e)
{
	$rows = array();   // Table not created yet, no searches logged
}

$base = 'index.php?option=com_flexicontent&view=search&layout=logs&tmpl=component';
$sortLink = function ($col, $label) use ($base, $order, $dir)
{
	$newDir = ($order === $col && $dir === 'DESC') ? 'ASC' : 'DESC';
	$arrow  = $order === $col ? ($dir === 'DESC' ? ' &#9660;' : ' &#9650;') : '';
	return '<a href="' . $base . '&amp;order=' . $col . '&amp;dir=' . $newDir . '">' . $label . $arrow . '</a>';
};
?>
<div style="padding: 10px;">
	<table class="table table-striped" style="width:100%;">
		<thead>
			<tr>
				<th><?php echo $sortLink('term', \Joomla\CMS\Language\Text::_('FLEXI_SEARCH_LOG_TERM')); ?></th>
				<th style="width:120px; text-align:right;"><?php echo $sortLink('hits', \Joomla\CMS\Language\Text::_('FLEXI_SEARCH_LOG_HITS')); ?></th>
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
			<tr><td colspan="2"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_SEARCH_LOG_EMPTY'); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>
</div>
