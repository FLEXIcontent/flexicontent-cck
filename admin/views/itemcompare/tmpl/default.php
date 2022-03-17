<?php
/**
 * @version 1.5 stable $Id: default.php 1726 2013-08-19 17:42:51Z ggppdk $
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

$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
?>
<style type="text/css">
table#itemcompare u{
	color:green;
}
table#itemcompare s{
	color:red;
}
</style>
<div class="flexicontent" style="width: 90%;">

	<div align="left" id="itemcompare">
		<?php echo '
		<a href="index.php?option=com_flexicontent&view=itemcompare' .
			'&cid[]=' . $this->rows[0]->id .
			'&version=' . $this->version .
			'&tmpl=component&codemode=' . ($this->codemode ? 0 : 1) .
		'">
			' . JText::_($this->codemode ? 'FLEXI_VERSION_VIEW_MODE' : 'FLEXI_VERSION_CODE_MODE') . '
		</a>';
		?>
	</div>

	<table class="admintable" style="width: 100%; border: 1px solid black;">
		<tr>
			<th style="align: right; width: 6%; font-size:16px;">
			</th>
			<th style="align: left; width: 47%; font-size:16px;">
				<?php echo JText::_( 'FLEXI_VERSION_NR' ) . $this->version; ?>
			</th>
			<th style="align: left; width: 47%; font-size:16px;">
				<?php echo JText::_( 'FLEXI_CURRENT_VERSION' ); ?>
			</th>
		</tr>
		<?php
		$noplugin = '<div class="fc-mssg-inline fc-warning" style="margin:0 2px 6px 2px; max-width: unset;">'.JText::_( 'FLEXI_PLEASE_PUBLISH_THIS_PLUGIN' ).'</div>';
		$cnt = 0;
		foreach ($this->fsets[$this->version] as $fn => $field)
		{
			// Field of current version
			$html = null;
			$field0 = $this->fsets[0][$fn];
			$isTextarea = $field->field_type == 'textarea' || ($field->field_type === 'maintext' && !$this->tparams->get('hide_maintext') != 1);

			if ($isTextarea)
			{
				//echo "Calculating DIFF for: " $field->label."<br/>";
				$html = flexicontent_html::flexiHtmlDiff(
					!is_array($field->display) ? $field->display : implode('', $field->display),
					!is_array($field0->display) ? $field0->display : implode('', $field0->display),
					$this->codemode
				);
			}
			if ($field->field_type === 'coreprops')
			{
				continue;
			}
		?>
		<tr>
			<td class="key" style="text-align:right; vertical-align:top;">
				<label for="<?php echo $field->name; ?>" class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip($field->label, $field->description, 0); ?>">
					<?php echo JText::_($field->label); ?>
				</label>
			</td>
			<td valign="top">
				<?php
				if ($html)
				{
					echo $html[0];
				}
				else
				{
					echo isset($field->display) ? $field->display : $noplugin;
				}
				?>
			</td>
			<td valign="top">
				<?php
				if ($html)
				{
					echo $html[1];
				}
				else
				{
					echo isset($field0->display) ? $field0->display : $noplugin;
				}
				?>
			</td>
		</tr>
		<?php
		}
		?>
	</table>
</div>