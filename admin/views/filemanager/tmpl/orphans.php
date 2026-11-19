<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Tool: Delete orphaned files (files / records not used by any item), shown inside a popup, loaded by layout 'indexer' (indexer=orphans)
 * Steps: (1) find the orphaned files (2) show warning and ask for confirmation (3) delete in small batches, with progress
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;

$token = \Joomla\CMS\Session\Session::getFormToken();
$url   = 'index.php?option=com_flexicontent&format=raw&task=filemanager.';
?>

<style>
#fc-orphans { padding: 8px 4px; }
#fc-orphans .fc-orphans-warning { margin: 12px 0; padding: 12px 14px; border: 1px solid #e0a800; background: #fff8e1; border-radius: 4px; }
#fc-orphans .fc-orphans-bar { width: 302px; height: 17px; border: 1px solid #000; padding: 0; margin: 12px 10px 0 0; float: left; clear: left; background: #fff; }
#fc-orphans .fc-orphans-bar > div { width: 0; height: 15px; margin: 1px; padding: 0; background-color: #000; transition: width .2s; }
#fc-orphans #fc-orphans-percent { margin-top: 12px; }
#fc-orphans #fc-orphans-status { clear: both; color: darkgreen; padding-top: 18px; }
#fc-orphans #fc-orphans-errors { color: #b00; margin-top: 10px; }
#fc-orphans .fc-orphans-btns { clear: both; margin-top: 16px; }
</style>

<div id="fc-orphans">
	<h3><?php echo Text::_('FLEXI_ORPHANS_TITLE'); ?></h3>
	<p><?php echo Text::_('FLEXI_ORPHANS_DESC'); ?></p>

	<div id="fc-orphans-step1">
		<label class="checkbox">
			<input type="checkbox" id="fc-orphans-skip-recent" checked="checked" />
			<?php echo Text::_('FLEXI_ORPHANS_SKIP_RECENT'); ?>
		</label>
		<div id="fc-orphans-finding"><?php echo Text::_('FLEXI_ORPHANS_FINDING'); ?> <img src="components/com_flexicontent/assets/images/ajax-loader.gif" alt="" /></div>
	</div>

	<div id="fc-orphans-confirm" style="display: none;">
		<div class="fc-orphans-warning">
			<strong><?php echo Text::_('FLEXI_ORPHANS_WARNING_TITLE'); ?></strong><br/>
			<span id="fc-orphans-confirm-text"></span><br/>
			<?php echo Text::_('FLEXI_ORPHANS_WARNING_NOUNDO'); ?>
		</div>
		<div class="fc-orphans-btns">
			<button type="button" class="btn btn-danger" id="fc-orphans-start"></button>
			<button type="button" class="btn" id="fc-orphans-download"><span class="icon-download"></span> <?php echo Text::_('FLEXI_ORPHANS_DOWNLOAD_LIST'); ?></button>
			<button type="button" class="btn" id="fc-orphans-cancel"><?php echo Text::_('JCANCEL'); ?></button>
		</div>
	</div>

	<div id="fc-orphans-run" style="display: none;">
		<div class="fc-orphans-bar"><div id="fc-orphans-inside"></div></div>
		<div id="fc-orphans-percent">0 %</div>
		<div id="fc-orphans-status"></div>
		<div id="fc-orphans-errors"></div>
		<div class="fc-orphans-btns"><button type="button" class="btn btn-success" id="fc-orphans-close" style="display: none;"><?php echo Text::_('JTOOLBAR_CLOSE'); ?></button></div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
	var url = <?php echo json_encode($url); ?>, token = <?php echo json_encode($token); ?>;
	var txt = {
		confirm: <?php echo json_encode(Text::_('FLEXI_ORPHANS_CONFIRM_TEXT')); ?>,
		start:   <?php echo json_encode(Text::_('FLEXI_ORPHANS_START')); ?>,
		none:    <?php echo json_encode(Text::_('FLEXI_ORPHANS_NONE')); ?>,
		skipped: <?php echo json_encode(Text::_('FLEXI_ORPHANS_SKIPPED_RECENT')); ?>,
		done:    <?php echo json_encode(Text::_('FLEXI_ORPHANS_DONE')); ?>,
		progress:<?php echo json_encode(Text::_('FLEXI_ORPHANS_PROGRESS')); ?>,
		time:    <?php echo json_encode(Text::_('FLEXI_BATCH_TOTAL_TASK_TIME')); ?>,
		halted:  <?php echo json_encode(Text::_('FLEXI_ORPHANS_HALTED')); ?>,
		stopped: <?php echo json_encode(Text::_('FLEXI_ORPHANS_STOPPED')); ?>,
		scanned: <?php echo json_encode(Text::_('FLEXI_ORPHANS_SCANNED')); ?>
	};
	const node = id => document.getElementById('fc-orphans-' + id);
	const show = (id, visible) => { node(id).style.display = visible ? '' : 'none'; };
	let total = 0, deleted = 0, skipped = 0, started = 0, errors = [], lines = [], foundOn = '';
	let scan;

	async function call(task, data, signal) {
		const endpoint = new URL(url + task, document.baseURI);
		for (const [key, value] of Object.entries({...data, [token]: 1})) endpoint.searchParams.set(key, value);
		const response = await fetch(endpoint, {credentials: 'same-origin', cache: 'no-store', signal});
		if (!response.ok) throw Error('HTTP ' + response.status);
		return response.json();
	}

	async function find() {
		scan?.abort();
		const current = scan = new AbortController();
		show('confirm', false);
		show('finding', true);
		node('finding').textContent = <?php echo json_encode(Text::_('FLEXI_ORPHANS_FINDING')); ?>;
		try {
			const result = await call('orphanscount', {min_age_hours: node('skip-recent').checked ? 24 : 0}, current.signal);
			if (current.signal.aborted) return;
			if (!result?.ok) throw Error(result?.error || txt.halted);
			total = result.count;
			lines = result.lines || [];
			foundOn = result.found_on || '';
			const summary = [result.skipped_recent ? txt.skipped.replace('%d', result.skipped_recent) : '',
				txt.scanned.replace('%1$d', result.checked).replace('%2$s', result.elapsed)].filter(Boolean).join(' ');
			if (!total) {
				node('finding').textContent = txt.none + ' ' + summary;
				return;
			}
			show('finding', false);
			node('confirm-text').textContent = txt.confirm.replace('%d', total) + ' ' + summary;
			node('start').textContent = txt.start.replace('%d', total);
			show('confirm', true);
		} catch (error) {
			if (!current.signal.aborted) node('finding').textContent = error.message;
		}
	}

	function render(message = '') {
		const processed = deleted + skipped;
		const percent = total ? Math.min(100, processed / total * 100) : 100;
		node('inside').style.width = (percent * 3) + 'px';
		node('percent').textContent = percent.toFixed(2) + ' %';
		node('status').replaceChildren(txt.progress.replace('%1$d', processed).replace('%2$d', total),
			' ? ' + <?php echo json_encode(Text::_('FLEXI_DELETE')); ?> + ' ' + deleted,
			document.createElement('br'), txt.time + ' ' + ((Date.now() - started) / 1000).toFixed(2) + ' secs',
			document.createElement('br'), message);
		node('errors').replaceChildren(...errors.map(error => {
			const line = document.createElement('div');
			line.textContent = error;
			return line;
		}));
	}

	async function step() {
		try {
			const result = await call('orphansdelete', {batch: 25});
			if (!result) throw Error(txt.halted);
			deleted += result.deleted || 0;
			skipped += result.skipped || 0;
			if (result.error) errors.push(result.error);
			errors.push(...(result.warnings || []));
			if (!result.ok) throw Error(txt.halted);
			render();
			if (result.remaining > 0) {
				setTimeout(step, 20);
				return;
			}
			render(txt.done.replace('%d', deleted));
		} catch (error) {
			errors.push(error.message);
			render(txt.stopped.replace('%d', deleted));
		}
		show('close', true);
	}

	function close() {
		if (window.parent !== window && typeof window.parent.fc_closeDialog === 'function') window.parent.fc_closeDialog('fc_modal_popup_container');
		else window.close();
	}

	node('skip-recent').addEventListener('change', find);
	node('close').addEventListener('click', close);

	// The list was sent together with the count, the text file is created in the browser and saved
	node('download').addEventListener('click', function () {
		var header = [
			'# Orphaned files (not used by any item), found on ' + foundOn + ' UTC',
			'# Files: ' + lines.length,
			'# id\tfile name\toriginal name\ttitle\ttype\tuploaded\tpath / url'
		];
		var text = header.concat(lines).join('\r\n') + '\r\n';
		var d = new Date(), pad = function (n) { return (n < 10 ? '0' : '') + n; };
		var name = 'orphaned-files-' + d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + '_' + pad(d.getHours()) + '-' + pad(d.getMinutes()) + '.txt';

		var a = document.createElement('a');
		a.href = URL.createObjectURL(new Blob(['\ufeff' + text], { type: 'text/plain;charset=utf-8' }));
		a.download = name;
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); document.body.removeChild(a); }, 1000);
	});

	node('cancel').addEventListener('click', close);
	node('start').addEventListener('click', () => {
		if (!confirm(<?php echo json_encode(Text::_('FLEXI_ORPHANS_CONFIRM_FINAL')); ?>)) return;
		show('step1', false);
		show('confirm', false);
		show('run', true);
		started = Date.now();
		render();
		step();
	});

	find();
});
</script>
