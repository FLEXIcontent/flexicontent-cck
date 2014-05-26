<?php
/**
 * @version 1.5 stable $Id: default.php 1079 2012-01-02 00:18:34Z ggppdk $
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
				<label>
					<?php echo JText::_( 'FLEXI_ID' ).':'; ?>
				</label>
			</td>
			<td>
				<span id="id" class=""><?php echo $this->row->id; ?></span>
			</td>
		</tr><tr>
			<td class="key">
				<label for="name">
					<?php echo JText::_( 'FLEXI_TAG_NAME' ).':'; ?>
				</label>
			</td>
			<td>
				<input id="name" name="name" class="required" value="<?php echo $this->row->name; ?>" size="50" maxlength="100" />
			</td>
		</tr><tr>
			<td class="key">
				<label for="alias">
					<?php echo JText::_( 'FLEXI_ALIAS' ).':'; ?>
				</label>
			</td>
			<td>
				<input id="alias" name="alias" class="" value="<?php echo $this->row->alias; ?>" size="50" maxlength="100" />
			</td>
		</tr><tr>
			<td class="key">
				<label for="published">
					<?php echo JText::_( 'FLEXI_PUBLISHED' ).':'; ?>
				</label>
			</td>
			<td>
				<fieldset class="radio btn-group btn-group-yesno" id="published">
					<?php
					$options = array( 0 => JText::_('FLEXI_NO'), 1 => JText::_('FLEXI_YES') );
					$curvalue = $this->row->published;
					$fieldname = 'published';
					$n=0;
					foreach ($options as $value => $label) {
						$checked = $curvalue==$value ? ' checked="checked" ' : '';
						echo '
							<input type="radio" '.$checked.' value="'.$value.'" name="'.$fieldname.'" id="'.$fieldname.$n.'">
							<label for="'.$fieldname.$n.'">'.$label.'</label>';
						$n++;
					}
					?>
				</fieldset>
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