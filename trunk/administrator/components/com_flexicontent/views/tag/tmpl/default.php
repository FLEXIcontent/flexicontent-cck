<?php
/**
 * @version 1.5 stable $Id: default.php 183 2009-11-18 10:30:48Z vistamedia $
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

<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td class="key">
				<label for="name">
					<?php echo JText::_( 'FLEXI_TAG_NAME' ).':'; ?>
				</label>
			</td>
			<td>
				<input id="name" name="name" class="required" value="<?php echo $this->row->name; ?>" size="50" maxlength="100" />
			</td>
			<td class="key">
				<label for="published">
					<?php echo JText::_( 'FLEXI_PUBLISHED' ).':'; ?>
				</label>
			</td>
			<td>
				<?php
				$html = JHTML::_('select.booleanlist', 'published', 'class="inputbox"', $this->row->published );
				echo $html;
				?>
			</td>
		</tr>
	</table>

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="controller" value="tags" />
<input type="hidden" name="view" value="tag" />
<input type="hidden" name="task" value="" />
</form>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>