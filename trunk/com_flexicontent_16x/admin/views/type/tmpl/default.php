<?php
/**
 * @version 1.5 stable $Id: default.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
				<table  class="admintable">
					<tr>
						<td class="key">
							<label for="name">
								<?php //echo JText::_( 'FLEXI_TYPE_NAME' ).':'; ?>
								<?php echo $this->form->getLabel('name'); ?>
							</label>
						</td>
						<td>
							<?php echo $this->form->getInput('name'); ?>
						</td>
					</tr>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('published'); ?>
						</td>
						<td>
							<?php echo $this->form->getInput('published'); ?>
						</td>
					</tr>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('access'); ?>
						</td>
						<td>
							<?php echo $this->form->getInput('access'); ?>
						</td>
					</tr>
				</table>			
			</td>
			<td valign="top" width="350px" style="padding: 7px 0 0 5px" align="left" valign="top">
				<?php
				echo JHtml::_('sliders.start','basic-sliders-'.$this->form->getValue("id"), array('useCookie'=>1));
				$fieldSets = $this->form->getFieldsets('attribs');
				foreach ($fieldSets as $name => $fieldSet) :
					$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_'.strtoupper($name).'_FIELDSET_LABEL';
					echo JHtml::_('sliders.panel',JText::_($label), $name.'-options');
					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
					endif;
					?>
					<fieldset class="panelform">
						<table>
						<?php foreach ($this->form->getFieldset($name) as $field) : ?>
						<tr>
							<td><?php echo $field->label; ?></td>
							<td><?php echo $field->input; ?></td>
						</tr>
						<?php endforeach; ?>
						</table>
					</fieldset>
				<?php endforeach;
				echo JHtml::_('sliders.end');
				
				echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_THEMES' ) . '</h3>';
				echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1));
				foreach ($this->tmpls as $tmplname=>$tmpl) :
					$fieldSets = $tmpl->params->getFieldsets('attribs');
					foreach ($fieldSets as $name => $fieldSet) :
						$label = !empty($fieldSet->label) ? $fieldSet->label : JText::_( 'FLEXI_PARAMETERS_SPECIFIC' ) . ' : ' . $tmpl->name;
						echo JHtml::_('sliders.panel',JText::_($label), $tmpl->name.'-'.$name.'-options');
						if (isset($fieldSet->description) && trim($fieldSet->description)) :
							echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
						endif;
				?>
						<fieldset class="panelform">
							<table>
							<?php foreach ($tmpl->params->getFieldset($name) as $field) :
								$fieldname =  $field->__get('fieldname');
								//$value = isset($this->attribs[$fieldname])?$this->attribs[$fieldname]:$tmpl->params->getValue($fieldname, $name);
								$value = $tmpl->params->getValue($fieldname, $name, @$this->attribs[$fieldname]);
							?>
							<tr>
								<td><?php echo $tmpl->params->getLabel($fieldname, $name); ?></td>
								<td><?php echo $tmpl->params->getInput($fieldname, $name, $value); ?></td>
							</tr>
							<?php endforeach; ?>
							</table>
						</fieldset>
				<?php
					endforeach;//fieldSets
				endforeach;//tmpls
				echo JHtml::_('sliders.end');
				?>
			</td>
		</tr>
	</table>
<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="jform[id]" value="<?php echo $this->form->getValue('id'); ?>" />
<input type="hidden" name="controller" value="types" />
<input type="hidden" name="view" value="type" />
<input type="hidden" name="task" value="" />
</form>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
