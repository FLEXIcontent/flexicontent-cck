<?php
/**
 * @version 1.5 stable $Id: params.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
(function ($) {

	$(document).ready(function() {

		var ajaxloader = '<span class="ajax-loader"><\/span>';
		var adminForm = $('#adminForm');
		var log_bind = $('#log-bind');

		adminForm.on('submit', function(e, data)
		{
			e.preventDefault();
			log_bind.html(ajaxloader);

			$.ajax({
				type: 'POST',
				data: adminForm.serialize(),
				url:  adminForm.prop('action'),
				success: function(str) {
					log_bind.html(str);
				}
			});

		});

	});

})(jQuery);
</script>

<form action="index.php?option=com_flexicontent&task=categories.params&layout=params&format=raw" method="post" name="adminForm" id="adminForm">

	<fieldset>
		<span class="label"><?php echo JText::_( 'FLEXI_COPY_PARAMETERS_SOURCE' ).':'; ?></span>
		<?php echo $this->lists['copyid']; ?>
		<br/>
		<span class="label"><?php echo JText::_( 'FLEXI_COPY_PARAMETERS_DEST' ).':'; ?></span>
		<?php echo $this->lists['destid']; ?>
	</fieldset>
	<table width="100%" align="center">
		<tr>
			<td width="50%" align="right">
			<input id="copy" type="submit" class="fc_button" value="<?php echo JText::_( 'FLEXI_COPY_PARAMETERS_BUTTON' ); ?>" />
			</td>
			<td width="50%" align="left">
			<input type="button" class="fc_button" onclick="window.parent.SqueezeBox.close();;" value="<?php echo JText::_( 'FLEXI_CANCEL' ); ?>" />			
			</td>
		</tr>
	</table>
	<div id="log-bind"></div>

<?php echo JHtml::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="task" value="categories.params" />
<input type="hidden" name="layout" value="categories.params" />
<input type="hidden" name="format" value="raw" />
</form>

<?php
//keep session alive while editing
JHtml::_('behavior.keepalive');
?>