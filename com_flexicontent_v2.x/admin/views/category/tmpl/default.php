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

/*
$layouts = array();
foreach ($this->tmpls as $tmpl) {
	$layouts[] = $tmpl->name;
}
$layouts = implode("','", $layouts);

$this->document->addScriptDeclaration("
	window.addEvent('domready', function() {
		activatePanel('blog');
	});
	");
dump($this->row);
*/
?>
<style>
.pane-sliders {
	margin: 8px 0px 0px 0px;
}
</style>
<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
				<table  class="adminform">
					<tr>
						<td class="key">
							<label for="title">
								<?php echo $this->iform->getLabel('title'); ?>
							</label>
						</td>
						<td>
							<?php echo $this->iform->getInput('title'); ?>
						</td>
						<td>
							<label for="published">
								<?php echo $this->iform->getLabel('published'); ?>
							</label>
						</td>
						<td>
							<?php echo $this->iform->getInput('published'); ?>
						</td>
					</tr>
					<tr>
						<td>
							<label for="alias">
								<?php echo $this->iform->getLabel('alias'); ?>
							</label>
						</td>
						<td>
							<?php echo $this->iform->getInput('alias'); ?>
						</td>
						<td>
							<label for="parent">
								<?php echo $this->iform->getLabel('parent_id'); ?>
							</label>
						</td>
						<td>
							<?php echo $this->iform->getInput('parent_id'); ?>
						</td>
					</tr>
					<tr>
						<td>
							<label for="copycid">
								<?php echo $this->iform->getLabel('copycid'); ?>
							</label>
						</td>
						<td>
							<?php echo $this->iform->getInput('copycid'); ?>
						</td>
						<td>
							<?php echo $this->iform->getLabel('language'); ?>
						</td>
						<td>
							<?php echo $this->iform->getInput('language'); ?>
						</td>
					</tr>
				</table>
									
				<?php
				if ($this->permission->CanConfig) :
				$this->document->addScriptDeclaration("
					window.addEvent('domready', function() {
						var slideaccess = new Fx.Slide('tabacces');
						var slidenoaccess = new Fx.Slide('notabacces');
						slideaccess.hide();
						$$('fieldset.flexiaccess legend').addEvent('click', function(ev) {
							slideaccess.toggle();
							slidenoaccess.toggle();
						});
					});
				");
				?>
				<fieldset class="flexiaccess">
					<legend><?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT' ); ?></legend>
					<table id="tabacces" class="admintable" width="100%">
					<tr>
					<td>
						<div id="access"><?php echo $this->iform->getInput('rules'); ?></div>
					</td>
					</tr>
					</table>
					<div id="notabacces">
					<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
					</div>
				</fieldset>
				<?php endif; ?>

				<table class="adminform">
					<tr>
						<td>
							<?php echo $this->iform->getInput('description'); ?>
						</td>
					</tr>
				</table>
			</td>
			<td valign="top" width="380px" style="padding: 0px 0 0 5px;vertical-align:top;">
				<?php
				echo JHtml::_('sliders.start','basic-sliders-'.$this->iform->getValue("id"), array('useCookie'=>1));
				echo JHtml::_('sliders.panel',JText::_('FLEXI_PUBLISHING_FIELDSET_LABEL'), 'publishing-details');
				?>
				<fieldset class="panelform">
					<ul class="adminformlist">
						<li><?php echo $this->iform->getLabel('created_user_id'); ?>
						<?php echo $this->iform->getInput('created_user_id'); ?></li>

						<?php if (intval($this->iform->getValue('created_time'))) : ?>
							<li><?php echo $this->iform->getLabel('created_time'); ?>
							<?php echo $this->iform->getInput('created_time'); ?></li>
						<?php endif; ?>

						<?php if ($this->iform->getValue('modified_user_id')) : ?>
							<li><?php echo $this->iform->getLabel('modified_user_id'); ?>
							<?php echo $this->iform->getInput('modified_user_id'); ?></li>

							<li><?php echo $this->iform->getLabel('modified_time'); ?>
							<?php echo $this->iform->getInput('modified_time'); ?></li>
						<?php endif; ?>

					</ul>
					<?php echo $this->iform->getLabel('access'); ?>
					<?php echo $this->iform->getInput('access'); ?>
				</fieldset>

				<?php echo JHtml::_('sliders.panel',JText::_('FLEXI_METADATA_FIELDSET_LABEL'), 'meta-options'); ?>
				<fieldset class="panelform">
				<ul class="adminformlist">
					<li><?php echo $this->iform->getLabel('metadesc'); ?>
					<?php echo $this->iform->getInput('metadesc'); ?></li>

					<li><?php echo $this->iform->getLabel('metakey'); ?>
					<?php echo $this->iform->getInput('metakey'); ?></li>

					<?php foreach($this->iform->getGroup('metadata') as $field): ?>
						<?php if ($field->hidden): ?>
							<li><?php echo $field->input; ?></li>
						<?php else: ?>
							<li><?php echo $field->label; ?>
							<?php echo $field->input; ?></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
				</fieldset>

				<?php
				$fieldSets = $this->iform->getFieldsets('params');
				foreach ($fieldSets as $name => $fieldSet) :
					$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMETERS_'.$name;
					echo JHtml::_('sliders.panel',JText::_($label), $name.'-options');
					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
					endif;
					?>
					<fieldset class="panelform">
						<?php foreach ($this->iform->getFieldset($name) as $field) : ?>
							<?php echo $field->label; ?>
							<?php echo $field->input; ?>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach;
				echo JHtml::_('sliders.end');
				
				echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_THEMES' ) . '</h3>';
				?>
				
				<?php foreach($this->iform->getGroup('templates') as $field): ?>
					<?php if ($field->hidden): ?>
						<?php echo $field->input; ?>
					<?php else: ?>
						<?php 
							echo $field->label;
							$field->set('input', null);
							$field->set('value', $this->attribs[$field->fieldname]);
							echo $field->input;
						?>
					<?php endif; ?>
				<?php endforeach; ?>
				
				<?php
				echo JHtml::_('sliders.start','theme-sliders-'.$this->iform->getValue("id"), array('useCookie'=>1));
				foreach ($this->tmpls as $tmpl) {
					$fieldSets = $tmpl->params->getFieldsets('attribs');
					foreach ($fieldSets as $name => $fieldSet) :
						$label = !empty($fieldSet->label) ? $fieldSet->label : JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
						echo JHtml::_('sliders.panel',JText::_($label), $tmpl->name.'-'.$name.'-options');
						if (isset($fieldSet->description) && trim($fieldSet->description)) :
							echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
						endif;
				?>
						<fieldset class="panelform">
							<?php foreach ($tmpl->params->getFieldset($name) as $field) :
								$fieldname =  $field->__get('fieldname');
								$value = $tmpl->params->getValue($fieldname, $name, @$this->attribs[$fieldname]);
							?>
								<?php echo $tmpl->params->getLabel($fieldname, $name); ?>
								<?php echo $tmpl->params->getInput($fieldname, $name, $value); ?>
							<?php endforeach; ?>
						</fieldset>
				<?php
					endforeach;
				}
				echo JHtml::_('sliders.end');
				?>
				<?php  ?>
			</td>
		</tr>
	</table>

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="id" value="<?php echo $this->iform->getValue('id'); ?>" />
<input type="hidden" name="controller" value="category" />
<input type="hidden" name="view" value="category" />
<input type="hidden" name="task" value="" />
<?php echo $this->iform->getInput('extension'); ?>
</form>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
