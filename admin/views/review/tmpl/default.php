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
<div class="flexicontent" id="flexicontent">

<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate">

	<table class="fc-form-tbl">
		<tr>
			<td class="key">
				<label class="label">
					<?php echo JText::_( 'FLEXI_ID' ); ?>
				</label>
			</td>
			<td>
				<span id="id" class=""><?php echo $this->row->id; ?></span>
			</td>
		</tr><tr>
			<td class="key">
				<label class="label" for="title">
					<?php echo JText::_( 'FLEXI_TITLE' ); ?>
				</label>
			</td>
			<td>
				<input type="text" id="title" name="title" class="input-xxlarge required" value="<?php echo $this->row->title; ?>" size="200" maxlength="100" />
			</td>
		</tr><tr>
			<td class="key">
				<label class="label">
					<?php echo JText::_( 'FLEXI_STATE' ); ?>
				</label>
			</td>
			<td>
				<fieldset class="radio btn-group btn-group-yesno" id="state">
					<?php
					$options = array( 0 => JText::_('FLEXI_NO'), 1 => JText::_('FLEXI_YES') );
					$curvalue = $this->row->state;
					$fieldname = 'state';
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
		</tr><tr>
			<td class="key">
				<label class="label" for="text">
					<?php echo JText::_( 'FLEXI_TEXT' ); ?>
				</label>
			</td>
			<td>
				<textarea id="text" name="text" class="input-xxlarge required" rows="6" cols="120"><?php echo $this->row->text; ?></textarea>
			</td>
		</tr>
	</table>

	<?php echo JHTML::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
	<input type="hidden" name="controller" value="reviews" />
	<input type="hidden" name="view" value="review" />
	<input type="hidden" name="task" value="" />

</form>
</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>