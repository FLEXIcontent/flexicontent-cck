<?php
/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

$esc = function ($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); };
$pending = $this->dbengine->pending;
$task_url = 'index.php?option=com_flexicontent&task=flexicontent.convertdbengine&format=raw&backup_confirmed=1&' . Session::getFormToken() . '=1&tmpl=component';
?>

<div id="flexicontent" class="flexicontent">
<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>">
<div id="j-main-container" class="col-md-12 span12" style="max-width: 1100px;">

	<p><?php echo Text::_('FLEXI_DBENGINE_INTRO'); ?></p>

	<table class="table table-striped" style="width: auto;">
		<thead>
			<tr><th><?php echo Text::_('FLEXI_DBENGINE_CHECK'); ?></th><th><?php echo Text::_('FLEXI_DBENGINE_RESULT'); ?></th><th></th></tr>
		</thead>
		<tbody>
		<?php foreach ($this->dbengine->checks as $check) : ?>
			<tr>
				<td><?php echo $esc($check->label); ?></td>
				<td><?php echo $esc($check->value); ?></td>
				<td><?php echo $check->ok === true ? '<span class="badge bg-success badge-success">OK</span>' : ($check->ok === false ? '<span class="badge bg-danger badge-important">' . Text::_('JNO') . '</span>' : ''); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

<?php if (!$pending) : ?>

	<div class="fc-mssg fc-success"><?php echo Text::_('FLEXI_DBENGINE_ALL_DONE'); ?></div>

<?php else : ?>

	<table class="table table-striped" style="width: auto;">
		<thead>
			<tr>
				<th><?php echo Text::_('FLEXI_DBENGINE_TABLE'); ?></th>
				<th><?php echo Text::_('FLEXI_DBENGINE_ROWS'); ?></th>
				<th><?php echo Text::_('FLEXI_DBENGINE_SIZE'); ?></th>
				<th><?php echo Text::_('FLEXI_DBENGINE_TIME'); ?></th>
				<th><?php echo Text::_('FLEXI_STATE'); ?></th>
			</tr>
		</thead>
		<tbody id="fc-dbengine-tables">
		<?php foreach ($pending as $table) : ?>
			<tr data-table="<?php echo $esc($table->name); ?>">
				<td><?php echo $esc($table->name); ?></td>
				<td style="text-align: right; white-space: nowrap;"><?php echo (int) $table->rows; ?></td>
				<td style="text-align: right; white-space: nowrap;"><?php echo $esc(FlexicontentDbEngine::formatBytes($table->bytes)); ?></td>
				<td style="text-align: right; white-space: nowrap;"><?php echo $esc(FlexicontentDbEngine::formatSeconds($table->seconds)); ?></td>
				<td class="fc-dbengine-state"></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div class="fc-mssg fc-note">
		<?php echo Text::_('FLEXI_DBENGINE_WARNING'); ?>
		<br/><br/>
		<?php echo Text::_('FLEXI_DBENGINE_ROLLBACK'); ?>
		<br/><br/>
		<?php echo Text::_('FLEXI_DBENGINE_CLI'); ?> <code>php cli/joomla.php flexicontent:database:innodb</code>
	</div>

	<?php if ($this->dbengine->ok) : ?>
	<p>
		<label for="fc-dbengine-backup" style="display: inline-block;">
			<input type="checkbox" id="fc-dbengine-backup" value="1" />
			<?php echo Text::_('FLEXI_DBENGINE_BACKUP_CONFIRM'); ?>
		</label>
	</p>
	<p>
		<button type="button" class="btn btn-primary" id="fc-dbengine-start" disabled="disabled"><?php echo Text::_('FLEXI_DBENGINE_START'); ?></button>
	</p>
	<?php else : ?>
	<div class="fc-mssg fc-warning"><?php echo Text::_('FLEXI_DBENGINE_CANNOT_START'); ?></div>
	<?php endif; ?>

	<div id="fc-dbengine-log" class="install-task" style="margin: 12px 0;"></div>

	<script>
	document.addEventListener('DOMContentLoaded', function()
	{
		var box = document.getElementById('fc-dbengine-backup');
		var btn = document.getElementById('fc-dbengine-start');
		var log = document.getElementById('fc-dbengine-log');
		var rows = document.getElementById('fc-dbengine-tables');
		if (!box || !btn) return;

		box.addEventListener('change', function() { btn.disabled = !box.checked; });

		// The state cell of the table that the next request converts: the first one without a result (same order as the task)
		var nextCell = function()
		{
			var cells = rows.querySelectorAll('td.fc-dbengine-state');
			for (var i = 0; i < cells.length; i++) if (!cells[i].hasChildNodes()) return cells[i];
			return null;
		};

		// Show the reply: a result goes into the row of its table, anything else below the list
		var show = function(html)
		{
			var reply = document.createElement('div');
			reply.innerHTML = html;

			while (reply.firstChild)
			{
				var node = reply.firstChild, name = node.nodeType === 1 ? node.getAttribute('data-table') : null, cell = null;

				for (var tr = name === null ? null : rows.firstElementChild; tr; tr = tr.nextElementSibling)
				{
					if (tr.getAttribute('data-table') === name) cell = tr.querySelector('td.fc-dbengine-state');
				}

				if (cell)
				{
					cell.innerHTML = node.innerHTML;
					reply.removeChild(node);
				}
				else log.appendChild(node);
			}
		};

		// One request converts and verifies one table. Repeat until the reply no longer asks for more
		var convert = function()
		{
			var cell = nextCell();
			if (cell)
			{
				cell.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> &hellip;';
				cell.parentNode.scrollIntoView({block: 'nearest'});
			}

			// A reply without a result for this table (a refusal, an error, a cut off request) leaves its row open
			var done = function() { if (cell && cell.querySelector('.spinner-border')) cell.innerHTML = ''; };

			var xhr = new XMLHttpRequest();
			xhr.open('GET', <?php echo json_encode($task_url); ?>);
			xhr.onload = function()
			{
				var more = xhr.status === 200 && xhr.responseText.indexOf('fc-dbengine-more') !== -1;
				show(xhr.status === 200 ? xhr.responseText : '<div><span class="install-notok"></span>HTTP ' + xhr.status + '</div>');
				done();
				if (more) convert();
				else log.insertAdjacentHTML('beforeend', '<div><a class="btn" href="index.php?option=com_flexicontent&view=flexicontent&layout=dbengine"><?php echo Text::_('FLEXI_DBENGINE_RELOAD', true); ?></a></div>');
			};
			xhr.onerror = xhr.ontimeout = function()
			{
				// The database server finishes the table it is converting even when the request is cut off
				done();
				log.insertAdjacentHTML('beforeend', '<div><?php echo Text::_('FLEXI_DBENGINE_TIMEOUT', true); ?> <a class="btn" href="index.php?option=com_flexicontent&view=flexicontent&layout=dbengine"><?php echo Text::_('FLEXI_DBENGINE_RELOAD', true); ?></a></div>');
			};
			xhr.send();
		};

		btn.addEventListener('click', function()
		{
			if (!box.checked) return;
			btn.disabled = box.disabled = true;
			convert();
		});
	});
	</script>

<?php endif; ?>

	<p><a class="btn" href="index.php?option=com_flexicontent"><?php echo Text::_('FLEXI_DASHBOARD'); ?></a></p>

</div>
</div>
</div>
