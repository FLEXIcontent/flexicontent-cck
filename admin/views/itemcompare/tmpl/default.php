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
<div class="flexicontent">
	<table cellspacing="0" cellpadding="0" border="0" width="100%" id="itemcompare" style="background-color:white;">
		<tr>
			<td valign="top">
					<?php if (!$this->cparams->get('disable_diff')) : ?>
					<div align="left"><a href="index.php?option=com_flexicontent&view=itemcompare&cid[]=<?php echo $this->row->id;?>&version=<?php echo $this->rev;?>&tmpl=component&codemode=<?php echo $this->codemode?0:1;?>"><?php echo JText::_(($this->codemode?'FLEXI_VERSION_VIEW_MODE':'FLEXI_VERSION_CODE_MODE'));?></a></div>
					<?php endif; ?>
					<table class="admintable">
						<tr>
							<th align="right" width="" style="font-size:16px;">
							</th>
							<th align="left" width="" style="font-size:16px;">
								<?php echo JText::_( 'FLEXI_VERSION_NR' ) . $this->rev; ?>
							</th>
							<th align="left" width="" style="font-size:16px;">
								<?php echo JText::_( 'FLEXI_CURRENT_VERSION' ); ?>
							</th>
						</tr>
						<?php
						foreach ($this->fields as $field)
						{
							if ( $field->iscore == 0 || ($field->field_type == 'maintext' && (!$this->tparams->get('hide_maintext'))) /*|| in_array($field->field_type, array('tags', 'categories'))*/ )
							{
								//$field->display = $field->value ? flexicontent_html::nl2space($field->value[0]) : JText::_( 'FLEXI_NO_VALUE' );
								//$field->displayversion = $field->version ? flexicontent_html::nl2space($field->version[0]) : JText::_( 'FLEXI_NO_VALUE' );
								
								$noplugin = '<div class="fc-mssg-inline fc-warning" style="margin:0 2px 6px 2px; max-width: unset;">'.JText::_( 'FLEXI_PLEASE_PUBLISH_THIS_PLUGIN' ).'</div>';
								
								//echo "Calculating DIFF for: " $field->label."<br/>";
								$html = flexicontent_html::flexiHtmlDiff(
									!is_array($field->displayversion) ? $field->displayversion : implode('', $field->displayversion),
									!is_array($field->display) ? $field->display : implode('', $field->display),
									$this->codemode
								);
						?>
						<tr>
							<td class="key" style="text-align:right;'">
								<label for="<?php echo $field->name; ?>" class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip($field->label, $field->description, 0); ?>">
									<?php echo JText::_($field->label); ?>
								</label>
							</td>
							<td valign="top">
								<?php
								if (isset($field->displayversion)) {
									if ((!$this->cparams->get('disable_diff')) && (($field->field_type == 'maintext') || ($field->field_type == 'textarea'))) {
										echo $html[0];
									} else {
										echo $field->displayversion;
									}
								} else {
									echo $noplugin;
								}
								?>
							</td>
							<td valign="top">
								<?php
								if (isset($field->display)) {
									if ((!$this->cparams->get('disable_diff')) && (($field->field_type == 'maintext') || ($field->field_type == 'textarea'))) {
										echo $html[1];
									} else {
										echo $field->display;
									}
								} else {
									echo $noplugin;
								}
								?>
							</td>
						</tr>
						<?php
							}
						}
						?>
					</table>
			</td>
		</tr>
	</table>
</div>