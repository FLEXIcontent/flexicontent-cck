<?php
/**
 * @version 1.5 stable $Id: default.php 1245 2012-04-12 05:16:57Z ggppdk $
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
.current:after{
	clear: both;
	content: "";
	display: block;
}
</style>

<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
				
				<div class="flexi_params">
				
					<div class="fcdualline_container">
						<?php echo $this->form->getLabel('title'); ?>
						<div class="container_fcfield fcdualline">
							<?php echo $this->form->getInput('title'); ?>
						</div>
					</div>
					<div class="fcdualline_container">
						<?php echo $this->form->getLabel('published'); ?>
						<div class="container_fcfield fcdualline">
								<?php echo $this->form->getInput('published'); ?>
						</div>
					</div>
					<div class="fcclear"></div>
					
					<div class="fcdualline_container">
						<?php echo $this->form->getLabel('alias'); ?>
						<div class="container_fcfield fcdualline">
							<?php echo $this->form->getInput('alias'); ?>
						</div>
					</div>
					<div class="fcdualline_container">
						<?php echo $this->form->getLabel('parent_id'); ?>
						<div class="container_fcfield fcdualline">
							<?php echo $this->Lists['parent_id']; ?>
						</div>
					</div>
					<div class="fcclear"></div>
					
					<div class="fcdualline_container">
						<?php echo $this->form->getLabel('language'); ?>
						<div class="container_fcfield fcdualline">
							<?php echo $this->form->getInput('language'); ?>
						</div>
					</div>
					<div class="fcclear"></div>
					
				</div>
				
				<?php
				if ($this->perms->CanConfig) :
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
				<fieldset class="flexiaccess" style="width: 95%;">
					<legend><?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT' ); ?></legend>
					<table id="tabacces" class="admintable" width="100%">
						<tr>
							<td>
								<div id="access"><?php echo $this->form->getInput('rules'); ?></div>
							</td>
						</tr>
					</table>
					<div id="notabacces">
						<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
					</div>
				</fieldset>
				<?php endif; ?>
				
				
				<?php
				echo JHtml::_('tabs.start','core-tabs-'.$this->form->getValue("id"), array('useCookie'=>1));
				$title = JText::_( 'FLEXI_DESCRIPTION' ) ;
				echo JHtml::_('tabs.panel', $title, 'cat-description');
				?>
				

				<div class="flexi_params" style="margin:0px 24px; width: 99% !important;">
					<?php
						// parameters : areaname, content, hidden field, width, height, rows, cols
						echo $this->editor->display( FLEXI_J16GE ? 'jform[description]' : 'description',  $this->row->description, '100%', '350px', '75', '20', array('pagebreak', 'readmore') ) ;
						//echo $this->form->getInput('description');  // does not use default user editor, but instead the one specified in XML file or the Globally configured one
					?>
				</div>
				
				<?php echo JHtml::_('tabs.panel',JText::_('FLEXI_IMAGE'), 'cat-image'); ?>
								
				<?php
				$fieldSets = $this->form->getFieldsets('params');
				foreach ($fieldSets as $name => $fieldSet) :
					if ($name != 'cat_basic' ) continue;
					$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMS_'.$name;
					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
					endif;
					?>
					<fieldset class="panelform">
						<?php foreach ($this->form->getFieldset($name) as $field) : ?>
							<?php echo $field->label; ?>
							<?php echo $field->input; ?>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach;
				?>
				
				
				<?php echo JHtml::_('tabs.panel',JText::_('FLEXI_PUBLISHING'), 'publishing-details'); ?>
				
				<fieldset class="panelform">
					<ul class="adminformlist">
						<li><?php echo $this->form->getLabel('created_user_id'); ?>
						<?php echo $this->form->getInput('created_user_id'); ?></li>

						<?php if (intval($this->form->getValue('created_time'))) : ?>
							<li><?php echo $this->form->getLabel('created_time'); ?>
							<?php echo $this->form->getInput('created_time'); ?></li>
						<?php endif; ?>

						<?php if ($this->form->getValue('modified_user_id')) : ?>
							<li><?php echo $this->form->getLabel('modified_user_id'); ?>
							<?php echo $this->form->getInput('modified_user_id'); ?></li>

							<li><?php echo $this->form->getLabel('modified_time'); ?>
							<?php echo $this->form->getInput('modified_time'); ?></li>
						<?php endif; ?>

					</ul>
					<?php echo $this->form->getLabel('access'); ?>
					<?php echo $this->form->getInput('access'); ?>
				</fieldset>

				<?php echo JHtml::_('tabs.panel',JText::_('FLEXI_META_SEO'), 'meta-options'); ?>
				<fieldset class="panelform">
				<ul class="adminformlist">
					<li><?php echo $this->form->getLabel('metadesc'); ?>
					<?php echo $this->form->getInput('metadesc'); ?></li>

					<li><?php echo $this->form->getLabel('metakey'); ?>
					<?php echo $this->form->getInput('metakey'); ?></li>

					<?php foreach($this->form->getGroup('metadata') as $field): ?>
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
				$title = JText::_( 'FLEXI_PARAMETERS_HANDLING' ) ;
				echo JHtml::_('tabs.panel', $title, 'cat-params-handling');
				?>

				<fieldset class="flexi_params">
					<div class="fcdualline_container">
						<?php echo $this->form->getLabel('copycid'); ?>
						<div class="container_fcfield fcdualline">
							<?php echo $this->Lists['copycid']; ?>
						</div>
					</div>
					<div class="fcclear"></div>

					<?php foreach($this->form->getGroup('special') as $field): ?>
					<div class="fcdualline_container">
						<?php echo $field->label; ?>
						<div class="container_fcfield fcdualline">
							<?php echo $this->Lists[$field->fieldname]; ?>
						</div>
					</div>
					<div class="fcclear"></div>
					<?php endforeach; ?>
				</fieldset>
				
				<span class="fc-note fc-mssg">
					<?php echo JText::_('FLEXI_CAT_PARAM_OVERRIDE_ORDER_DETAILS_INHERIT'); ?>
				</span>
				
				<?php
				$title = JText::_( 'FLEXI_PARAMETERS' ) ;
				echo JHtml::_('tabs.panel', $title, 'cat-params-common');
				
				//echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS' ) . '</h3>';
				echo JHtml::_('tabs.start','basic-sliders-'.$this->form->getValue("id"), array('useCookie'=>1));
				
				$fieldSets = $this->form->getFieldsets('params');
				foreach ($fieldSets as $name => $fieldSet) :
					if ($name == 'cat_basic' ) continue;
					if ($name == 'cat_notifications_conf' && ( !$this->cparams->get('enable_notifications', 0) || !$this->cparams->get('nf_allow_cat_specific', 0) ) ) continue;
					$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMS_'.$name;
					echo JHtml::_('tabs.panel',JText::_($label), $name.'-options');
					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
					endif;
					?>
					<fieldset class="panelform">
						<?php foreach ($this->form->getFieldset($name) as $field) : ?>
							<?php echo $field->label; ?>
							<?php echo $field->input; ?>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach;
				echo JHtml::_('tabs.end');
				?>
				
				<?php

				$title = JText::_( 'FLEXI_TEMPLATE' ) ;
				echo JHtml::_('tabs.panel', $title, 'cat-params-template');
				echo '<span class="fc-note fc-mssg-inline" style="margin: 8px 0px!important;">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_EXPLANATION' ) ;
				?>
				<br/><br/>
				<ol style="margin:0 0 0 16px; padding:0;">
					<li style="margin:0; padding:0;"> Select TEMPLATE layout </li>
					<li style="margin:0; padding:0;"> Open slider with TEMPLATE (layout) PARAMETERS </li>
				</ol>
				<br/>
				<b>NOTE:</b> Common method for -displaying- fields is by <b>editing the template layout</b> in template manager and placing the fields into <b>template positions</b>
				</span>
				
				
				<fieldset class="panelform">
				<?php foreach($this->form->getGroup('templates') as $field): ?>
					<?php if ($field->hidden): ?>
						<?php echo $field->input; ?>
					<?php else: ?>
						<?php 
							echo $field->label;
							if (method_exists ( $field , 'set' )) {
								$field->set('input', null);
								$field->set('value', @$this->row->params[$field->fieldname]);
							}
							echo $field->input;
						?>
					<?php endif; ?>
					<div class="clear"></div>
				<?php endforeach; ?>
				</fieldset>
				
				<?php
				echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1));
				$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >
				
				foreach ($this->tmpls as $tmpl) :
					$fieldSets = $tmpl->params->getFieldsets($groupname);
					foreach ($fieldSets as $fsname => $fieldSet) :
						$label = !empty($fieldSet->label) ? $fieldSet->label : JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
						echo JHtml::_('sliders.panel',JText::_($label), $tmpl->name.'-'.$fsname.'-options');
						if (isset($fieldSet->description) && trim($fieldSet->description)) :
							echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
						endif;
						?>
						<fieldset class="panelform">
							<?php foreach ($tmpl->params->getFieldset($fsname) as $field) :
								$fieldname =  $field->__get('fieldname');
								$value = $tmpl->params->getValue($fieldname, $groupname, @$this->row->params[$fieldname]);
								echo $tmpl->params->getLabel($fieldname, $groupname);
								echo
									str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_', 
										str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
											$tmpl->params->getInput($fieldname, $groupname, $value)
										)
									);
							endforeach; ?>
						</fieldset>
					<?php endforeach; ?>
				<?php endforeach; ?>
				
				<?php echo JHtml::_('sliders.end'); ?>
				<?php echo JHtml::_('tabs.end'); ?>
				
			</td>
		</tr>
	</table>

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="id" value="<?php echo $this->form->getValue('id'); ?>" />
<input type="hidden" name="controller" value="category" />
<input type="hidden" name="view" value="category" />
<input type="hidden" name="task" value="" />
<?php echo $this->form->getInput('extension'); ?>
</form>
</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>