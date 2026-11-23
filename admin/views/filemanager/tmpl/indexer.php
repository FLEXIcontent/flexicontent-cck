<?php
/**
 * @version 1.5 stable $Id: indexer.php 1775 2013-09-27 02:04:02Z ggppdk $
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

$records_name = 'files';
$ctrl_task = 'task=filemanager.';

$app = \Joomla\CMS\Factory::getApplication();
$indexer_name = $app->input->get('indexer', 'filemanager_stats', 'cmd');

// Tool: delete orphaned files, has its own template (warning, confirmation, progress)
if ($indexer_name === 'orphans')
{
	include __DIR__ . '/orphans.php';

	return;
}
$rebuildmode  = $app->input->get('rebuildmode', '', 'cmd');
$index_urls   = $app->input->get('index_urls', 0, 'int');

// Sanitize: allow only comma-separated integers
$_selected_ids_raw = $app->input->getString('selected_ids', '');
$_selected_ids_arr = array_filter(array_map('intval', explode(',', $_selected_ids_raw)));
$selected_ids = implode(',', $_selected_ids_arr);
?>

<div>&nbsp;</div>
<div style="heading">
	<?php echo \Joomla\CMS\Language\Text::_('FLEXI_TASK_RUNNING'); ?> ... <br/>
	
<script>
document.addEventListener('DOMContentLoaded', async () => {
	const progress = document.getElementById('insideprogress');
	const percent = document.getElementById('updatepercent');
	const status = document.getElementById('statuscomment');
	const params = {
		option: 'com_flexicontent', format: 'raw',
		index_urls: <?php echo json_encode($index_urls); ?>,
		indexer: <?php echo json_encode($indexer_name); ?>,
		[<?php echo json_encode(\Joomla\CMS\Session\Session::getFormToken()); ?>]: '1'
	};
	const started = performance.now();

	async function request(task, query = {}) {
		const url = new URL('index.php', document.baseURI);
		url.search = new URLSearchParams({...params, ...query, task: 'filemanager.' + task});
		const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
		if (!response.ok) throw Error('HTTP ' + response.status);
		const [result, ...fields] = (await response.text()).split('|');
		if (result.trim() !== 'success') throw Error(fields[0] || result);
		return fields;
	}

	function update(current, total, summary, details) {
		const ratio = total ? Math.min(current / total, 1) : 1;
		progress.style.width = 300 * ratio + 'px';
		percent.textContent = (100 * ratio).toFixed(2) + ' %';
		status.replaceChildren(
			Math.min(current, total) + ' / ' + total + ' <?php echo $records_name; ?>',
			document.createElement('br'), summary || '', document.createElement('br'),
			'Total task time ' + ((performance.now() - started) / 1000).toFixed(2) + ' seconds',
			document.createElement('br'), details || ''
		);
	}

	try {
		const [num, , offset, summary, details] = await request('countrows', {selected_ids: <?php echo json_encode($selected_ids); ?>});
		const total = Number(num);
		let current = Number(offset);
		if (!Number.isFinite(total) || !Number.isFinite(current)) throw Error('Invalid indexer count');
		update(current, total, summary, details);
		while (current < total) {
			const [next, details, log] = await request('index', {
				records_per_call: 1000,
				records_cnt: current,
				rebuildmode: <?php echo json_encode($rebuildmode); ?>
			});
			if (!(Number(next) > current)) throw Error('The indexer returned no progress');
			current = Number(next);
			update(current, total, details, log);
		}
		status.append(document.createElement('br'), 'Indexing finished. You may close this window.');
	} catch (error) {
		status.textContent = 'Indexer halted. ' + error.message;
	}
}, {once: true});
</script>

<style>
div#advancebar{
	width:302px;
	height:17px;
	border:1px solid #000;
	padding:0px;
	margin:0 10px 0 0;
	float:left;
	clear:left;
}
div#insideprogress{
	width:0px;
	height:15px;
	background-color:#000;
	padding:0px;
	margin:1px;
}
div#updatepercent{
	clear:right;
}
div#statuscomment{
	color:darkgreen;
	margin-top:30px;
}
</style>
<div class="clr"></div>
<div>&nbsp;</div>
<div id="advancebar"><div id="insideprogress"></div></div>
<div id="updatepercent">0 %</div>
<div class="clr"></div>
<div id="statuscomment">Initializing <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif"></div>
<div class="clr"></div>