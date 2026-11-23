<?php
/**
 * @version 1.5 stable $Id: default_postinstall.php 1879 2014-03-27 12:20:20Z ggppdk $
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
?>

<script>
FlexiForm.ready(() => {
	const tasks = {
		existfields: 'createdefaultfields',
		existcpfields: 'createdefaultcpfields',
		existmenuitems: 'createmenuitems',
		existtype: 'createdefaultype',
		publishplugins: 'publishplugins',
		existcats: 'addmcatitemrelations',
		langsynced: 'updatelanguagedata',
		existdbindexes: 'createdbindexes',
		dbcharsetok: 'convertdbcharset',
		existversions: 'createversionstable',
		existversionsdata: 'populateversionstable',
		existauthors: 'createauthorstable',
		cachethumb: 'setcachethumbperms',
		itemcountingdok: 'updateitemcountingdata',
		deprecatedfiles: 'deletedeprecatedfiles',
		oldfieldsdata: 'cleanupoldtables',
		missingversion: 'addcurrentversiondata',
		initialpermission: 'updateinitialpermission',
	};
	for (const [id, task] of Object.entries(tasks)) {
		const button = document.getElementById(id);
		const result = document.getElementById(id + '-log');
		if (!button || !result) continue;
		button.addEventListener('click', async event => {
			event.preventDefault();
			const endpoint = new URL('index.php', document.baseURI);
			endpoint.search = new URLSearchParams({option: 'com_flexicontent', task: 'flexicontent.' + task,
				format: 'raw', tmpl: 'component', '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>': '1'});
			const loader = document.createElement('span');
			loader.className = 'ajax-loader';
			result.replaceChildren(loader);
			try {
				let more, log = '';
				do {
					const response = await fetch(endpoint, {credentials: 'same-origin'});
					if (!response.ok) throw Error(response.statusText);
					const html = await response.text();
					more = id === 'dbcharsetok' && html.includes('fc-dbcharset-more');
					log = more ? log + html : html;
					await FlexiForm.insertHTML(result, log);
				} while (more);
			} catch (error) {
				result.replaceChildren(button);
			}
		});
	}
});
</script>


<table class="adminlist table fcmanlist postinstall-tbl" style="margin: 10px 0 10px 10px; border: 0 none;">

	<thead>
		<tr>
			<th><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_ACTIONS' ); ?></th>
			<th><?php echo \Joomla\CMS\Language\Text::_( 'JSTATUS' ); ?></th></th>
		</tr>
	</thead>

	<tbody>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_PUBLISH_ALL_PLUGINS' ); ?>
			</td>
			<td>
				<div id="publishplugins-log" class="install-task">
					<?php echo $this->allplgpublish ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="publishplugins" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_INSTALL_DEFAULT_TYPE' ); ?>
			</td>
			<td>
				<div id="existtype-log" class="install-task">
					<?php echo $this->existtype ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existtype" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'Add/update default Menu Item for URLs' ); ?>
			</td>
			<td>
				<div id="existmenuitems-log" class="install-task">
					<?php echo $this->existmenuitems ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existmenuitems" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_INSTALL_DEFAULT_FIELDS' ); ?>
			</td>
			<td>
				<div id="existfields-log" class="install-task">
					<?php echo $this->existfields ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existfields" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_INSTALL_CORE_PROPERTY_FIELDS' ); ?>
			</td>
			<td>
				<div id="existcpfields-log" class="install-task">
					<?php echo $this->existcpfields ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existcpfields" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_INSTALL_MCATS_RELATIONS' ); ?>
			</td>
			<td>
				<div id="existcats-log" class="install-task">
					<?php echo $this->existcats ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existcats" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_INSTALL_MULTILINGUAL_SUPPORT' ); ?>
			</td>
			<td>
				<div id="langsynced-log" class="install-task">
					<?php echo $this->langsynced ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="langsynced" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_CREATE_DB_INDEXES' ); ?>
				<?php
					if (!$this->existdbindexes && !empty($this->missingindexes)) {
						echo "<br/><span class='fc-mssg-inline fc-mssg fc-info'>this may take a long time on big web-sites, if it timeouts (or takes >2 min) then please refresh, and click to create remaining indexes</span>";
						echo "<br># tables: ". count($this->missingindexes) ." : ";
						foreach($this->missingindexes as $tblname => $indexes)
						{
							echo isset($indexes['__indexing_started__']) ?
								"<br/><b>" .$tblname. "</b> (<small style='color:green'>Indexing started</small>)" :
								"<br/><b>" .$tblname. "</b> (". count($indexes) ." <small>indexes missing</small>)";
						}
					}
				?>
			</td>
			<td>
				<div id="existdbindexes-log" class="install-task">
					<?php echo $this->existdbindexes ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existdbindexes" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_CONVERT_DB_TABLES_UTF8MB4' ); ?>
				<?php
					if (!$this->dbcharsetok && !empty($this->dbcharsetpending)) {
						echo "<br/><span class='fc-mssg-inline fc-mssg fc-info'>" . \Joomla\CMS\Language\Text::_( 'FLEXI_CONVERT_DB_TABLES_UTF8MB4_DESC' ) . "</span>";
						echo "<br># tables: ". count($this->dbcharsetpending) ." : ";
						foreach($this->dbcharsetpending as $tblname => $tbl)
						{
							echo "<br/><b>" . htmlspecialchars($tblname, ENT_QUOTES, 'UTF-8') . "</b> (<small>" . (int) $tbl->rows . " rows, " . round($tbl->bytes / 1048576, 1) . " MB</small>)";
						}
					}
				?>
			</td>
			<td>
				<div id="dbcharsetok-log" class="install-task">
					<?php echo $this->dbcharsetok ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="dbcharsetok" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_INSTALL_VERSIONS_TABLE' ); ?>
			</td>
			<td>
				<div id="existversions-log" class="install-task">
					<?php echo $this->existversions ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existversions" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE_VERSIONS_DATA' ); ?>
			</td>
			<td>
				<div id="existversionsdata-log" class="install-task">
					<?php echo $this->existversionsdata ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existversionsdata" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_INSTALL_AUTHORS_TABLE' ); ?>
			</td>
			<td id="existauthors-log">
				<div class="install-task"><?php echo $this->existauthors ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existauthors" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?></div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE_TEMPORARY_ITEM_DATA' ); ?>
			</td>
			<td>
				<div id="itemcountingdok-log" class="install-task">
					<?php echo $this->itemcountingdok ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="itemcountingdok" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_SET_PHPTHUMB_CACHE_PERMISSIONS' ); ?>
			</td>
			<td>
				<div id="cachethumb-log" class="install-task">
					<?php echo $this->cachethumb ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="cachethumb" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_REMOVE_DEPRECATED_FILES' ); ?>
			</td>
			<td>
				<div id="deprecatedfiles-log" class="install-task">
					<?php echo $this->deprecatedfiles ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="deprecatedfiles" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_CLEANUP_TABLES' ); ?>
			</td>
			<td>
				<div id="oldfieldsdata-log" class="install-task">
					<?php echo $this->nooldfieldsdata ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="oldfieldsdata" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_CURRENT_VERSIONS' ); ?>
			</td>
			<td>
				<div id="missingversion-log" class="install-task">
					<?php echo $this->missingversion ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="missingversion" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_INITIAL_PERMISSION' ); ?>
			</td>
			<td>
				<div id="initialpermission-log" class="install-task">
					<?php echo $this->initialpermission ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="initialpermission" href="javascript:;">'.\Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
				</div>
			</td>
		</tr>
	</tbody>

</table>
