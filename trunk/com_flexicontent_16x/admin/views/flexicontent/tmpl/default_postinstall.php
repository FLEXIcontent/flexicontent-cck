<?php
/**
 * @version 1.5 stable $Id: default_postinstall.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
if (!$this->existfields) : //@TODO must write a class for that!!! I'm a dirty lazy pig :-) ?>
<script type="text/javascript">
	window.addEvent('domready', function(){
		$('existfields').addEvent('click', function(e) {
			var url = "index.php?option=com_flexicontent&task=createdefaultfields&<?php echo JUtility::getToken();?>=1&format=raw";
			e = new Event(e).stop();
			if(MooTools.version>="1.2.4") {
				$('existfields-log').set('html', '<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('existfields-log')
				}).send();
			}else{
				$('existfields-log').setHTML('<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('existfields-log')
				});
				ajax.request.delay(500, ajax);
			}
		});
	});
</script>
<?php endif; ?>
<?php if (!$this->existtype) : ?>
<script type="text/javascript">
	window.addEvent('domready', function(){
		$('existtype').addEvent('click', function(e) {
			e = new Event(e).stop();
			var url = "index.php?option=com_flexicontent&task=createdefaultype&<?php echo JUtility::getToken();?>=1&format=raw";
			if(MooTools.version>="1.2.4") {
				$('existtype-log').set('html', '<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('existtype-log')
				}).send();
			}else{
				$('existtype-log').setHTML('<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('existtype-log')
				});
				ajax.request.delay(500, ajax);
			}
		});
	});
</script>
<?php endif; ?>
<?php if (!$this->allplgpublish) : ?>
<script type="text/javascript">
	window.addEvent('domready', function(){
		$('publishplugins').addEvent('click', function(e) {
			e = new Event(e).stop();
			var url = "index.php?option=com_flexicontent&task=publishplugins&<?php echo JUtility::getToken();?>=1&format=raw";
			if(MooTools.version>="1.2.4") {
				$('publishplugins-log').set('html', '<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('publishplugins-log')
				}).send();
			}else{
				$('publishplugins-log').setHTML('<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('publishplugins-log')
				});
				ajax.request.delay(500, ajax);
			}
		});
	});
</script>
<?php endif; ?>
<?php if (!$this->existlang) : ?>
<script type="text/javascript">
	window.addEvent('domready', function(){
		$('existlang').addEvent('click', function(e) {
			e = new Event(e).stop();
			var url = "index.php?option=com_flexicontent&task=createlangcolumn&<?php echo JUtility::getToken();?>=1&format=raw";
			if(MooTools.version>="1.2.4") {
				$('existlang-log').set('html', '<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('existlang-log')
				}).send();
			}else{
				$('existlang-log').setHTML('<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('existlang-log')
				});
				ajax.request.delay(500, ajax);
			}
		});
	});
</script>
<?php endif; ?>
<?php if (!$this->existversions) : ?>
<script type="text/javascript">
	window.addEvent('domready', function(){
		$('existversions').addEvent('click', function(e) {
			e = new Event(e).stop();
			var url = "index.php?option=com_flexicontent&task=createversionstable&<?php echo JUtility::getToken();?>=1&format=raw";
			if(MooTools.version>="1.2.4") {
				$('existversions-log').set('html', '<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('existversions-log')
				}).send();
			}else{
				$('existversions-log').setHTML('<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('existversions-log')
				});
				ajax.request.delay(500, ajax);
			}
		});
	});
</script>
<?php endif; ?>
<?php if (!$this->existversionsdata) : ?>
<script type="text/javascript">
	window.addEvent('domready', function(){
		$('existversionsdata').addEvent('click', function(e) {
			e = new Event(e).stop();
			var url = "index.php?option=com_flexicontent&task=populateversionstable&<?php echo JUtility::getToken();?>=1&format=raw";
			if(MooTools.version>="1.2.4") {
				$('existversionsdata-log').set('html', '<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('existversionsdata-log')
				}).send();
			}else{
				$('existversionsdata-log').setHTML('<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('existversionsdata-log')
				});
				ajax.request.delay(500, ajax);
			}
		});
	});
</script>
<?php endif; ?>
<?php if (!$this->cachethumb) : ?>
<script type="text/javascript">
	window.addEvent('domready', function(){
		$('cachethumb').addEvent('click', function(e) {
			e = new Event(e).stop();
			var url = "index.php?option=com_flexicontent&task=cachethumbchmod&<?php echo JUtility::getToken();?>=1&format=raw";
			if(MooTools.version>="1.2.4") {
				$('cachethumb-log').set('html', '<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('cachethumb-log')
				}).send();
			}else{
				$('cachethumb-log').setHTML('<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('cachethumb-log')
				});
				ajax.request.delay(500, ajax);
			}
		});
	});
</script>
<?php endif; ?>
<?php if (!$this->oldbetafiles) : ?>
<script type="text/javascript">
	window.addEvent('domready', function(){
		$('oldbetafiles').addEvent('click', function(e) {
			e = new Event(e).stop();
			var url = "index.php?option=com_flexicontent&task=deleteoldfiles&<?php echo JUtility::getToken();?>=1&format=raw";
			if(MooTools.version>="1.2.4") {
				$('oldbetafiles-log').set('html', '<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('oldbetafiles-log')
				}).send();
			}else{
				$('oldbetafiles-log').setHTML('<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('oldbetafiles-log')
				});
				ajax.request.delay(500, ajax);
			}
		});
	});
</script>
<?php endif; ?>
<?php if (!$this->nooldfieldsdata) : ?>
<script type="text/javascript">
	window.addEvent('domready', function(){
		$('oldfieldsdata').addEvent('click', function(e) {
			e = new Event(e).stop();
			var url = "index.php?option=com_flexicontent&task=cleanupoldtables&<?php echo JUtility::getToken();?>=1&format=raw";
			if(MooTools.version>="1.2.4") {
				$('oldfieldsdata-log').set('html', '<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('oldfieldsdata-log')
				}).send();
			}else{
				$('oldfieldsdata-log').setHTML('<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('oldfieldsdata-log')
				});
				ajax.request.delay(500, ajax);
			}
		});
	});
</script>
<?php endif; ?>
<?php if ($this->missingversion) : ?>
<script type="text/javascript">
	window.addEvent('domready', function(){
		$('missingversion').addEvent('click', function(e) {
			e = new Event(e).stop();
			var url = "index.php?option=com_flexicontent&task=addcurrentversiondata&<?php echo JUtility::getToken();?>=1&format=raw";
			if(MooTools.version>="1.2.4") {
				$('missingversion-log').set('html', '<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				new Request.HTML({
					url: url,
					method: 'get',
					update: $('missingversion-log')
				}).send();
			}else{
				$('missingversion-log').setHTML('<img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center">');
				var ajax = new Ajax(url, {
					method: 'get',
					update: $('missingversion-log')
				});
				ajax.request.delay(500, ajax);
			}
		});
	});
</script>
<?php endif; ?>

		<table class="adminlist" cellspacing="0" cellpadding="0" border="0" width="100%">
			<tr>
				<td class="key">
					<?php echo JText::_( 'FLEXI_PUBLISH_ALL_PLUGINS' ); ?>
				</td>
				<td id="publishplugins-log">
					<?php echo $this->allplgpublish ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="publishplugins" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
				</td>
			</tr>
			<tr>
				<td class="key" style="width:280px;">
					<?php echo JText::_( 'FLEXI_INSTALL_DEFAULT_TYPE' ); ?>
				</td>
				<td id="existtype-log">
					<?php echo $this->existtype ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="existtype" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
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
					<?php echo JText::_( 'FLEXI_INSTALL_MULTILINGUAL_SUPPORT' ); ?>
				</td>
				<td id="existlang-log">
					<?php echo $this->existlang ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="existlang" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>' ; ?>
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
					<?php echo !$this->missingversion ? '<span class="install-ok"></span>' : '<span class="install-notok"></span><span class="button-add"><a id="missingversion" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>'; ?>
				</td>
			</tr>
	</table>