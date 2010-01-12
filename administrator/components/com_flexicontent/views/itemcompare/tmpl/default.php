<?php
/**
 * @version 1.5 beta 5 $Id: default.php 183 2009-11-18 10:30:48Z vistamedia $
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

<div class="flexicontent">
	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
					<table class="admintable">
						<tr>
							<th align="right" width="16%" style="font-size:16px;">
							</th>
							<th align="left" width="42%" style="font-size:16px;">
								<?php echo JText::_( 'FLEXI_CURRENT_VERSION' ); ?>
							</th>
							<th align="left" width="42%" style="font-size:16px;">
								<?php echo JText::_( 'FLEXI_VERSION_NR' ) . $this->rev; ?>
							</th>
						</tr>
						<?php
						foreach ($this->fields as $field)
						//dump($field,'field');
						{
							// used to hide the core fields from this listing
							if ( $field->iscore == 0 || ($field->field_type == 'maintext' && (!$this->tparams->get('hide_maintext'))) ) {
							// set the specific label for the maintext field
								if ($field->field_type == 'maintext') {
									$field->label 			= $this->tparams->get('maintext_label', $field->label);
									$field->description 	= $this->tparams->get('maintext_desc', $field->description);
									$field->display			= $field->value ? nl2br($field->value[0]) : JText::_( 'FLEXI_NO_VALUE' );
								}
						?>
						<tr>
							<td class="key">
								<label for="<?php echo $field->name; ?>" class="hasTip" title="<?php echo $field->label; ?>::<?php echo $field->description; ?>">
									<?php echo $field->label; ?>
								</label>
							</td>
							<td valign="top">
								<?php
								$noplugin = '<div id="fc-change-error" class="fc-error">'. JText::_( 'FLEXI_PLEASE_PUBLISH_PLUGIN' ) .'</div>';
								if(isset($field->display)){
									echo $field->display;
								} else {
									echo $noplugin;
								}
								?>
							</td>
							<td valign="top">
								<?php
								$noplugin = '<div id="fc-change-error" class="fc-error">'. JText::_( 'FLEXI_PLEASE_PUBLISH_PLUGIN' ) .'</div>';
								if ($field->field_type == 'maintext') {
									$field->displayversion		= $field->version ? nl2br($field->version[0]) : JText::_( 'FLEXI_NO_VALUE' );
								}
								
								if(isset($field->displayversion)){
									echo $field->displayversion;
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