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

jQuery(document).ready(function() {
	var ajaxloader = '<span class="ajax-loader"><\/span>';


<?php if(!$this->existfields) : /*@TODO must write a class for all following cases */ ?>
	jQuery('#existfields').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.createdefaultfields&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#existfields-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#existfields-log').html(data);
		});

	});
<?php endif; ?>


<?php if(!$this->existcpfields) : /*@TODO must write a class for all following cases */ ?>
	jQuery('#existcpfields').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.createdefaultcpfields&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#existcpfields-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#existcpfields-log').html(data);
		});

	});
<?php endif; ?>


<?php if(!$this->existmenuitems) : ?>
	jQuery('#existmenuitems').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.createmenuitems&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#existmenuitems-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#existmenuitems-log').html(data);
		});

	});
<?php endif; ?>


<?php if(!$this->existtype) : ?>
	jQuery('#existtype').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.createdefaultype&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#existtype-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#existtype-log').html(data);
		});

	});
<?php endif; ?>


<?php if(!$this->allplgpublish) : ?>
	jQuery('#publishplugins').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.publishplugins&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#publishplugins-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#publishplugins-log').html(data);
		});

	});
<?php endif; ?>


<?php if(!$this->existcats) : ?>
	jQuery('#existcats').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.addmcatitemrelations&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#existcats-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#existcats-log').html(data);
		});

	});
<?php endif; ?>


<?php if(!$this->langsynced) : ?>
	jQuery('#langsynced').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.updatelanguagedata&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#langsynced-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#langsynced-log').html(data);
		});

	});
<?php endif; ?>



<?php if(!$this->existdbindexes) : ?>
	jQuery('#existdbindexes').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.createdbindexes&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#existdbindexes-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#existdbindexes-log').html(data);
		});

	});
<?php endif; ?>


<?php if(!$this->existversions) : ?>
	jQuery('#existversions').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.createversionstable&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#existversions-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#existversions-log').html(data);
		});

	});
<?php endif; ?>


<?php if(!$this->existversionsdata) : ?>
	jQuery('#existversionsdata').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.populateversionstable&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#existversionsdata-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#existversionsdata-log').html(data);
		});

	});
<?php endif; ?>


<?php if (!$this->existauthors) : ?>
	jQuery('#existauthors').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.createauthorstable&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#existauthors-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#existauthors-log').html(data);
		});

	});
<?php endif; ?>


<?php if (!$this->cachethumb) : ?>
	jQuery('#cachethumb').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.setcachethumbperms&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#cachethumb-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#cachethumb-log').html(data);
		});

	});
<?php endif; ?>


<?php if (!$this->itemcountingdok) : ?>
	jQuery('#itemcountingdok').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.updateitemcountingdata&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#itemcountingdok-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#itemcountingdok-log').html(data);
		});

	});
<?php endif; ?>


<?php if (!$this->deprecatedfiles) : ?>
	jQuery('#deprecatedfiles').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.deletedeprecatedfiles&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#deprecatedfiles-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#deprecatedfiles-log').html(data);
		});

	});
<?php endif; ?>


<?php if (!$this->nooldfieldsdata) : ?>
	jQuery('#oldfieldsdata').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.cleanupoldtables&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#oldfieldsdata-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#oldfieldsdata-log').html(data);
		});

	});
<?php endif; ?>


<?php if (!$this->missingversion) : ?>
	jQuery('#missingversion').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.addcurrentversiondata&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#missingversion-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#missingversion-log').html(data);
		});

	});
<?php endif; ?>


<?php if(!$this->initialpermission) : ?>
	jQuery('#initialpermission').on('click', function(e, data)
	{
		var url = "index.php?option=com_flexicontent&task=flexicontent.updateinitialpermission&format=raw&<?php echo JSession::getFormToken();?>=1&tmpl=component";

		jQuery('#initialpermission-log').html(ajaxloader);
		jQuery.ajax({
			type: 'GET',
			url: url,
			data: {}
		}).done( function(data) {
			jQuery('#initialpermission-log').html(data);
		});

	});
<?php endif; ?>

});
</script>


<table class="adminlist table fcmanlist postinstall-tbl" style="margin: 10px 0 10px 10px; border: 0 none;">

	<thead>
		<tr>
			<th><?php echo JText::_( 'FLEXI_ACTIONS' ); ?></th>
			<th><?php echo JText::_( 'JSTATUS' ); ?></th></th>
		</tr>
	</thead>

	<tbody>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_PUBLISH_ALL_PLUGINS' ); ?>
			</td>
			<td>
				<div id="publishplugins-log" class="install-task">
					<?php echo $this->allplgpublish ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="publishplugins" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_INSTALL_DEFAULT_TYPE' ); ?>
			</td>
			<td>
				<div id="existtype-log" class="install-task">
					<?php echo $this->existtype ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existtype" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'Add/update default Menu Item for URLs' ); ?>
			</td>
			<td>
				<div id="existmenuitems-log" class="install-task">
					<?php echo $this->existmenuitems ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existmenuitems" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_INSTALL_DEFAULT_FIELDS' ); ?>
			</td>
			<td>
				<div id="existfields-log" class="install-task">
					<?php echo $this->existfields ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existfields" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_INSTALL_CORE_PROPERTY_FIELDS' ); ?>
			</td>
			<td>
				<div id="existcpfields-log" class="install-task">
					<?php echo $this->existcpfields ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existcpfields" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_INSTALL_MCATS_RELATIONS' ); ?>
			</td>
			<td>
				<div id="existcats-log" class="install-task">
					<?php echo $this->existcats ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existcats" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_INSTALL_MULTILINGUAL_SUPPORT' ); ?>
			</td>
			<td>
				<div id="langsynced-log" class="install-task">
					<?php echo $this->langsynced ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="langsynced" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_CREATE_DB_INDEXES' ); ?>
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
					<?php echo $this->existdbindexes ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existdbindexes" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_INSTALL_VERSIONS_TABLE' ); ?>
			</td>
			<td>
				<div id="existversions-log" class="install-task">
					<?php echo $this->existversions ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existversions" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_UPDATE_VERSIONS_DATA' ); ?>
			</td>
			<td>
				<div id="existversionsdata-log" class="install-task">
					<?php echo $this->existversionsdata ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existversionsdata" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_INSTALL_AUTHORS_TABLE' ); ?>
			</td>
			<td id="existauthors-log">
				<div class="install-task"><?php echo $this->existauthors ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="existauthors" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?></div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_UPDATE_TEMPORARY_ITEM_DATA' ); ?>
			</td>
			<td>
				<div id="itemcountingdok-log" class="install-task">
					<?php echo $this->itemcountingdok ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="itemcountingdok" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_SET_PHPTHUMB_CACHE_PERMISSIONS' ); ?>
			</td>
			<td>
				<div id="cachethumb-log" class="install-task">
					<?php echo $this->cachethumb ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="cachethumb" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_REMOVE_DEPRECATED_FILES' ); ?>
			</td>
			<td>
				<div id="deprecatedfiles-log" class="install-task">
					<?php echo $this->deprecatedfiles ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="deprecatedfiles" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_CLEANUP_TABLES' ); ?>
			</td>
			<td>
				<div id="oldfieldsdata-log" class="install-task">
					<?php echo $this->nooldfieldsdata ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="oldfieldsdata" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_CURRENT_VERSIONS' ); ?>
			</td>
			<td>
				<div id="missingversion-log" class="install-task">
					<?php echo $this->missingversion ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="missingversion" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_INITIAL_PERMISSION' ); ?>
			</td>
			<td>
				<div id="initialpermission-log" class="install-task">
					<?php echo $this->initialpermission ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span><a class="fc_button fc_simple" id="initialpermission" href="javascript:;">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
				</div>
			</td>
		</tr>
	</tbody>

</table>
