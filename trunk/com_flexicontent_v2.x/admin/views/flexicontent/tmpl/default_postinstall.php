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

defined( '_JEXEC' ) or die( 'Restricted access' );
?>
<script type="text/javascript">
	window.addEvent('domready', function() {
		var ajaxloader = '<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">';
<?php if(!$this->existfields) : //@TODO must write a class for that!!! I'm a dirty lazy pig :-) ?>
	$('existfields').addEvent('click', function(e) {
		var url = "index.php?option=com_flexicontent&task=createdefaultfields&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		//e = new Event(e).stop();
		if(MooTools.version>="1.2.4") {
			$('existfields-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('existfields-log')
			}).send();
		}else{
			$('existfields-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('existfields-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if(!$this->existmenuitems) : ?>
	$('existmenuitems').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=createMenuItems&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('existmenuitems-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('existmenuitems-log')
			}).send();
		}else{
			$('existmenuitems-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('existmenuitems-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if(!$this->existtype) : ?>
	$('existtype').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=createdefaultype&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('existtype-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('existtype-log')
			}).send();
		}else{
			$('existtype-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('existtype-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if(!$this->allplgpublish) : ?>
	$('publishplugins').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=publishplugins&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('publishplugins-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('publishplugins-log')
			}).send();
		}else{
			$('publishplugins-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('publishplugins-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if(!$this->existcats) : ?>
	$('existcats').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=addmcatitemrelations&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('existcats-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('existcats-log')
			}).send();
		}else{
			$('existcats-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('existcats-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if(!$this->existlang) : ?>
	$('existlang').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=createlangcolumn&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('existlang-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('existlang-log')
			}).send();
		}else{
			$('existlang-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('existlang-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if( !empty($this->existdbindexes) ) : ?>
	$('existdbindexes').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=createdbindexes&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('existdbindexes-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('existdbindexes-log')
			}).send();
		}else{
			$('existdbindexes-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('existdbindexes-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if(!$this->existversions) : ?>
	$('existversions').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=createversionstbl&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('existversions-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('existversions-log')
			}).send();
		}else{
			$('existversions-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('existversions-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if(!$this->existversionsdata) : ?>
	$('existversionsdata').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=populateversionstbl&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('existversionsdata-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('existversionsdata-log')
			}).send();
		}else{
			$('existversionsdata-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('existversionsdata-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if (!$this->existauthors) : ?>
	$('existauthors').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=createauthorstbl&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('existauthors-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('existauthors-log')
			}).send();
		}else{
			$('existauthors-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('existauthors-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if (!$this->cachethumb) : ?>
	$('cachethumb').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=cachethumbchmod&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('cachethumb-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('cachethumb-log')
			}).send();
		}else{
			$('cachethumb-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('cachethumb-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if (!$this->itemcountingdok) : ?>
	$('itemcountingdok').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=updateitemcounting&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('itemcountingdok-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('itemcountingdok-log')
			}).send();
		}else{
			$('itemcountingdok-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('itemcountingdok-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if (!$this->oldbetafiles) : ?>
$('oldbetafiles').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=deleteoldfiles&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('oldbetafiles-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('oldbetafiles-log')
			}).send();
		}else{
			$('oldbetafiles-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('oldbetafiles-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if (!$this->nooldfieldsdata) : ?>
$('oldfieldsdata').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=cleanupoldtables&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('oldfieldsdata-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('oldfieldsdata-log')
			}).send();
		}else{
			$('oldfieldsdata-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('oldfieldsdata-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if (!$this->missingversion) : ?>
$('missingversion').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=addcurrentversiondata&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('missingversion-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('missingversion-log')
			}).send();
		}else{
			$('missingversion-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('missingversion-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
<?php if(!$this->initialpermission) : ?>
	$('initialpermission').addEvent('click', function(e) {
		//e = new Event(e).stop();
		var url = "index.php?option=com_flexicontent&task=initialpermission&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1&tmpl=component";
		if(MooTools.version>="1.2.4") {
			$('initialpermission-log').set('html', ajaxloader);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $('initialpermission-log')
			}).send();
		}else{
			$('initialpermission-log').set('html',ajaxloader);
			var ajax = new Ajax(url, {
				method: 'get',
				update: $('initialpermission-log')
			});
			ajax.request.delay(500, ajax);
		}
	});
<?php endif; ?>
	});
</script>
<table class="adminlist" cellspacing="0" cellpadding="0" border="0">
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_PUBLISH_ALL_PLUGINS' ); ?>
		</td>
		<td id="publishplugins-log">
			<?php echo $this->allplgpublish ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="publishplugins" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_INSTALL_DEFAULT_TYPE' ); ?>
		</td>
		<td id="existtype-log">
			<?php echo $this->existtype ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="existtype" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'Default Menu Item for URLs' ); ?>
		</td>
		<td id="existmenuitems-log">
			<?php echo $this->existmenuitems ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="existmenuitems" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_INSTALL_DEFAULT_FIELDS' ); ?>
		</td>
		<td id="existfields-log">
			<?php echo $this->existfields ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="existfields" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_INSTALL_MCATS_RELATIONS' ); ?>
		</td>
		<td id="existcats-log">
			<?php echo $this->existcats ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="existcats" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_INSTALL_MULTILINGUAL_SUPPORT' ); ?>
		</td>
		<td id="existlang-log">
			<?php echo $this->existlang ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="existlang" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_CREATE_DB_INDEXES' ); ?>
			<?php
				if (!empty($this->existdbindexes)) {
					echo "<br/><span class='fc-mssg-inline fc-mssg fc-note'>this may take a long time on big web-sites, if it timeouts (or takes >5 min) then please refresh, and click to create remaining indexes</span>";
					echo "<br># tables: ". count($this->existdbindexes) ." : ";
					foreach($this->existdbindexes as $tblname => $indexes) {
						if ( isset($indexes['__indexing_started__']) ) {
							echo "<br/><b>" .$tblname. "</b> (<small style='color:green'>Indexing started</small>)";
						} else {
							echo "<br/><b>" .$tblname. "</b> (". count($indexes) ." <small>indexes missing</small>)";
						}
					}
				}
			?>
		</td>
		<td id="existdbindexes-log">
			<?php echo empty($this->existdbindexes) ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="existdbindexes" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_INSTALL_VERSIONS_TABLE' ); ?>
		</td>
		<td id="existversions-log">
			<?php echo $this->existversions ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="existversions" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_UPDATE_VERSIONS_DATA' ); ?>
		</td>
		<td id="existversionsdata-log">
			<?php echo $this->existversionsdata ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="existversionsdata" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_INSTALL_AUTHORS_TABLE' ); ?>
		</td>
		<td id="existauthors-log">
			<?php echo $this->existauthors ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="existauthors" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_UPDATE_TEMPORARY_ITEM_DATA' ); ?>
		</td>
		<td id="itemcountingdok-log">
			<?php echo $this->itemcountingdok ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="itemcountingdok" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_SET_PHPTHUMB_CACHE_PERMISSIONS' ); ?>
		</td>
		<td id="cachethumb-log">
			<?php echo $this->cachethumb ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="cachethumb" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_CLEANUP_TEMPLATE_FILES' ); ?>
		</td>
		<td id="oldbetafiles-log">
			<?php echo $this->oldbetafiles ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="oldbetafiles" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_CLEANUP_TABLES' ); ?>
		</td>
		<td id="oldfieldsdata-log">
			<?php echo $this->nooldfieldsdata ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="oldfieldsdata" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_CURRENT_VERSIONS' ); ?>
		</td>
		<td id="missingversion-log">
			<?php echo $this->missingversion ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="missingversion" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
		</td>
	</tr>
	<tr>
		<td class="key">
			<?php echo JText::_( 'FLEXI_INITIAL_PERMISSION' ); ?>
		</td>
		<td id="initialpermission-log">
			<?php echo $this->initialpermission ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="initialpermission" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
		</td>
	</tr>
</table>
