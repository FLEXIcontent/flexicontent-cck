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
jQuery(document).ready(function () {
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
	var total = 0, deleted = 0, skipped = 0, t0 = 0, errors = [], lines = [], found_on = '';

	function call(task, data) {
		return jQuery.ajax({ url: url + task, data: jQuery.extend({}, data, (function () { var o = {}; o[token] = 1; return o; })()), dataType: 'json', cache: false });
	}

	function fail(msg) {
		jQuery('#fc-orphans-finding').html('<span style="color:#b00;">' + jQuery('<div>').text(msg).html() + '</span>');
	}

	function find() {
		jQuery('#fc-orphans-confirm').hide();
		jQuery('#fc-orphans-finding').show().html(<?php echo json_encode(Text::_('FLEXI_ORPHANS_FINDING')); ?> + ' <img src="components/com_flexicontent/assets/images/ajax-loader.gif" alt="" />');

		call('orphanscount', { min_age_hours: jQuery('#fc-orphans-skip-recent').is(':checked') ? 24 : 0 })
			.done(function (r) {
				if (!r || !r.ok) { return fail((r && r.error) || 'Error'); }

				total = r.count;
				lines = r.lines || [];
				found_on = r.found_on || '';
				var extra = r.skipped_recent ? '<br/>' + txt.skipped.replace('%d', r.skipped_recent) : '';
				extra += '<br/><small>' + txt.scanned.replace('%1$d', r.checked).replace('%2$s', r.elapsed) + '</small>';

				if (!total) {
					return jQuery('#fc-orphans-finding').html(txt.none + extra);
				}

				jQuery('#fc-orphans-finding').hide();
				jQuery('#fc-orphans-confirm-text').html(txt.confirm.replace('%d', '<strong>' + total + '</strong>') + extra);
				jQuery('#fc-orphans-start').text(txt.start.replace('%d', total));
				jQuery('#fc-orphans-confirm').show();
			})
			.fail(function (xhr) { fail('HTTP ' + xhr.status); });
	}

	function render(extra) {
		var processed = deleted + skipped;
		var percent = total ? Math.min(100, processed / total * 100) : 100;
		jQuery('#fc-orphans-inside').css('width', Math.min(300, percent * 3) + 'px');
		jQuery('#fc-orphans-percent').text(' ' + percent.toFixed(2) + ' %');
		jQuery('#fc-orphans-status').html(txt.progress.replace('%1$d', processed).replace('%2$d', total)
			+ ' &ndash; ' + <?php echo json_encode(Text::_('FLEXI_DELETE')); ?> + ': ' + deleted
			+ '<br/><br/>' + txt.time + ': ' + ((Date.now() - t0) / 1000).toFixed(2) + ' secs' + (extra || ''));
		jQuery('#fc-orphans-errors').html(errors.map(function (e) { return jQuery('<div>').text(e).html(); }).join('<br/>'));
	}

	function finish(halted) {
		render('<br/><br/><strong' + (halted ? ' style="color:#b00;"' : '') + '>' + (halted ? txt.stopped.replace('%d', deleted) : txt.done.replace('%d', deleted)) + '</strong>');
		jQuery('#fc-orphans-close').show().off('click').on('click', function () {
			try { window.parent.jQuery('#fc_modal_popup_container').dialog('close'); } catch (e) { window.close(); }
		});
	}

	function step() {
		call('orphansdelete', { batch: 25 })
			.done(function (r) {
				if (!r) { errors.push(txt.halted); return finish(true); }
				deleted += r.deleted || 0;
				skipped += r.skipped || 0;
				if (r.error) { errors.push(r.error); }
				(r.warnings || []).forEach(function (w) { errors.push(w); });
				render();

				if (!r.ok) { errors.push(txt.halted); return finish(true); }
				if (r.remaining > 0) { return setTimeout(step, 20); }
				finish();
			})
			.fail(function (xhr) { errors.push(txt.halted + ' (HTTP ' + xhr.status + ')'); finish(true); });
	}

	jQuery('#fc-orphans-skip-recent').on('change', find);

	// The list was sent together with the count, the text file is created in the browser and saved
	jQuery('#fc-orphans-download').on('click', function () {
		var header = [
			'# Orphaned files (not used by any item), found on ' + found_on + ' UTC',
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

	jQuery('#fc-orphans-cancel').on('click', function () {
		try { window.parent.jQuery('#fc_modal_popup_container').dialog('close'); } catch (e) { window.close(); }
	});

	jQuery('#fc-orphans-start').on('click', function () {
		if (!confirm(<?php echo json_encode(Text::_('FLEXI_ORPHANS_CONFIRM_FINAL')); ?>)) { return; }
		jQuery('#fc-orphans-step1, #fc-orphans-confirm').hide();
		jQuery('#fc-orphans-run').show();
		t0 = Date.now();
		render();
		step();
	});

	find();
});
</script>
