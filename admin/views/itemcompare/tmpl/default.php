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
table#itemcompare ins,
table#itemcompare u{
	color:green;
}
table#itemcompare del,
table#itemcompare s{
	color:red;
}
</style>
<div class="flexicontent" style="width: 100%;">
	<div align="right">
		<?php echo '
		<a class="btn btn-primary" href="index.php?option=com_flexicontent&view=itemcompare' .
			'&cid[]=' . $this->rows[0]->id .
			'&version=' . $this->version .
			'&tmpl=component&codemode=' . ($this->codemode ? 0 : 1) .
		'">
			' . JText::_('FLEXI_CHANGE_TO') . ' ' . JText::_($this->codemode ? 'FLEXI_VERSION_VIEW_MODE' : 'FLEXI_VERSION_CODE_MODE') . '
		</a>';
		?>
	</div>

	<table id="itemcompare" class="table table-striped table-condensed" style="width: 100%;">
		<tr>
			<th style="align: right; width: 6%;">
				<?php echo JText::_( 'FLEXI_FIELD' ); ?>
			</th>
			<th style="align: left;">
				<?php echo JText::_( 'FLEXI_VERSION_NR' ) . $this->version; ?>
			</th>
			<th style="align: left;">
				<?php echo JText::_( 'FLEXI_CURRENT_VERSION' ); ?>
			</th>
			<th style="align: left;">
			</th>
		</tr>
		<?php
		$novalue = '<span class="novalue">' . JText::_('FLEXI_NO_VALUE') . '</span>';
		$cnt = 0;
		//echo count($this->fsets[$this->version]); exit;
		foreach ($this->fsets[$this->version] as $fn => $field)
		{
			if ($field->field_type === 'coreprops' || $field->parameters->get('use_ingroup', 0))
			{
				continue;
			}

			// Field of current version
			$html = null;
			$field0 = $this->fsets[0][$fn];
			$isIndexedfield = !empty($field->isIndexedfield);
			$isText = $field->iscore || $field->field_type == 'text' || $field->field_type == 'textarea'
				|| ($field->field_type === 'maintext' && $this->tparams->get('hide_maintext') != 1)
				|| $isIndexedfield;

			if ($isText || $isIndexedfield || 1)
			{
				//echo 'Calculating DIFF for: ' . $field->label . '<br/>';
				$before = !is_array($field->display) ? $field->display : implode('', $field->display);
				$after  = !is_array($field0->display) ? $field0->display : implode('', $field0->display);
				if ($this->codemode)
				{
					$before = htmlspecialchars($before, ENT_QUOTES, 'UTF-8');
					$after  = htmlspecialchars($after, ENT_QUOTES, 'UTF-8');
				}
				else
				{
					$uncut_length = 0;
					$before = flexicontent_html::striptagsandcut($before, 100000, $uncut_length);
					$after  = flexicontent_html::striptagsandcut($after, 100000, $uncut_length);
				}
				$html = flexicontent_html::flexiHtmlDiff($before, $after, 0);
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
				if ($html && 0)
				{
					echo $html[0];
				}
				else
				{
					echo strlen($field->display) ? $field->display : $novalue;
				}
				?>
			</td>
			<td valign="top">
				<?php
				if ($html && 0)
				{
					echo $html[1];
				}
				else
				{
					echo strlen($field0->display) ? $field0->display : $novalue;
				}
				?>
			</td>
			<td valign="top">
				<?php echo $html && isset($html[2]) ? $html[2] : ''; ?>
			</td>
		</tr>
		<?php
		}
		?>
	</table>
</div>