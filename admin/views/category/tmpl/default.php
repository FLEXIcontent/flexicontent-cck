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

<?php
$useAssocs = flexicontent_db::useAssociations();

// Load JS tabber lib
$this->document->addScript(JURI::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js?v='.FLEXI_VERSION);
$this->document->addStyleSheet(JURI::root(true).'/components/com_flexicontent/assets/css/tabber.css?v='.FLEXI_VERSION);
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
$js = "
	jQuery(document).ready(function(){
		fc_bind_form_togglers('#flexicontent', 0, '');
	});
";
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

	<div class="container-fluid" style="padding:0px!important;">
		
		<div class="span6 full_width_980">
		
			<span class="label-fcouter">
				<?php echo str_replace('" class="', '" class="label label-fcinner ', $this->form->getLabel('title')); ?>
			</span>
			<div class="container_fcfield">
				<?php echo $this->form->getInput('title'); ?>
			</div>
			
			<div class="fcclear"></div>
			<span class="label-fcouter">
				<?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('alias')); ?>
			</span>
			<div class="container_fcfield">
				<?php echo $this->form->getInput('alias'); ?>
			</div>
			
			<div class="fcclear"></div>
			<span class="label-fcouter">
				<?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('language')); ?>
			</span>
			<div class="container_fcfield">
				<?php echo $this->form->getInput('language'); ?>
			</div>
			
		</div>
		
		<div class="span6 full_width_980">
			
			<span class="label-fcouter">
				<?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('published')); ?>
			</span>
			<div class="container_fcfield">
					<?php echo $this->form->getInput('published'); ?>
			</div>

			<div class="fcclear"></div>
			<span class="label-fcouter">
				<?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('parent_id')); ?>
			</span>
			<div class="container_fcfield">
				<?php echo $this->Lists['parent_id']; ?>
			</div>
		
		</div>
		
	</div>
	
	
	<?php /*echo JHtml::_('tabs.start','core-tabs-'.$this->form->getValue("id"), array('useCookie'=>1));*/ ?>
		<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_DESCRIPTION' ), 'cat-description');*/ ?>
		
	<div class="fctabber tabset_cats" id="tabset_cat_props">
		
		<div class="tabbertab" id="tabset_cat_props_desc_tab" data-icon-class="icon-file-2" >
			<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?> </h3>
			
			<div class="flexi_params">
				<?php
					// parameters : areaname, content, hidden field, width, height, rows, cols
					echo $this->editor->display( FLEXI_J16GE ? 'jform[description]' : 'description',  $this->row->description, '100%', '350px', '75', '20', array('pagebreak', 'readmore') ) ;
					//echo $this->form->getInput('description');  // does not use default user editor, but instead the one specified in XML file or the Globally configured one
				?>
			</div>
			
		</div><?php /*echo JHtml::_('tabs.panel',JText::_('FLEXI_IMAGE'), 'cat-image');*/ ?>
		
		
		<div class="tabbertab" id="tabset_cat_props_image_tab" data-icon-class="icon-image" >
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_IMAGE'); ?> </h3>
			
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
			<?php endforeach; ?>
			
		</div><?php /*echo JHtml::_('tabs.panel',JText::_('FLEXI_PUBLISHING'), 'publishing-details');*/ ?>
		
		
		<div class="tabbertab" id="tabset_cat_props_publishing_tab" data-icon-class="icon-calendar" >
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_PUBLISHING'); ?> </h3>
			
			<fieldset class="panelform">
				<?php echo $this->form->getLabel('created_user_id'); ?>
				<?php echo $this->form->getInput('created_user_id'); ?>
	
				<?php if (intval($this->form->getValue('created_time'))) : ?>
					<?php echo $this->form->getLabel('created_time'); ?>
					<?php echo $this->form->getInput('created_time'); ?>
				<?php endif; ?>
	
				<?php if ($this->form->getValue('modified_user_id')) : ?>
					<?php echo $this->form->getLabel('modified_user_id'); ?>
					<?php echo $this->form->getInput('modified_user_id'); ?>
	
					<?php echo $this->form->getLabel('modified_time'); ?>
					<?php echo $this->form->getInput('modified_time'); ?>
				<?php endif; ?>
				
				<?php echo $this->form->getLabel('access'); ?>
				<?php echo $this->form->getInput('access'); ?>
			</fieldset>
			
		</div><?php /*echo JHtml::_('tabs.panel',JText::_('FLEXI_META_SEO'), 'meta-options');*/ ?>
		
		
		<div class="tabbertab" id="tabset_cat_props_metaseo_tab" data-icon-class="icon-bookmark" >
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_META_SEO'); ?> </h3>
			
			<fieldset class="panelform">
				<?php echo $this->form->getLabel('metadesc'); ?>
				<?php echo $this->form->getInput('metadesc'); ?>
	
				<?php echo $this->form->getLabel('metakey'); ?>
				<?php echo $this->form->getInput('metakey'); ?>
	
				<?php foreach($this->form->getGroup('metadata') as $field): ?>
					<?php if ($field->hidden): ?>
						<?php echo $field->input; ?>
					<?php else: ?>
						<?php echo $field->label; ?>
						<?php echo $field->input; ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</fieldset>
			
			<?php
			$fieldSets = $this->form->getFieldsets('params');
			foreach ($fieldSets as $name => $fieldSet) :
				if ($name != 'cat_seo' ) continue;
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
			<?php endforeach; ?>
			
		</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_PARAMETERS_HANDLING'), 'cat-params-handling');*/ ?>
		
		
		<div class="tabbertab" id="tabset_cat_props_metaseo_tab" data-icon-class="icon-wrench" >
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_PARAMETERS_HANDLING'); ?> </h3>
			
			<div style="margin: 24px 0px;">
				
				<?php foreach($this->form->getGroup('special') as $field): ?>
					<fieldset class="panelform">
						<?php echo $field->label; ?>
						<div class="container_fcfield">
							<?php echo $this->Lists[$field->fieldname]; ?>
						</div>
					</fieldset>
				<?php endforeach; ?>
				
				<div class="fcclear"></div>
				<span class="fc-success fc-mssg" style="margin: 16px 0px 48px 0px !important; font-size:12px;">
					<?php echo JText::_('FLEXI_CAT_PARAM_OVERRIDE_ORDER_DETAILS_INHERIT'); ?>
				</span>
				<div class="fcclear"></div>
				
				
				<fieldset class="panelform">
					<?php echo $this->form->getLabel('copycid'); ?>
					<div class="container_fcfield">
						<?php echo $this->Lists['copycid']; ?>
					</div>
				</fieldset>
				
				<div class="fcclear"></div>
				<span class="fc-warning fc-mssg" style="margin: 16px 0px !important; font-size:12px;">
					<?php echo JText::_('FLEXI_COPY_PARAMETERS_DESC'); ?>
				</span>
				<div class="fcclear"></div>
				
			</div>
			
		</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_PARAMETERS'), 'cat-params-common');*/ ?>
		
		
		<div class="tabbertab" id="tabset_cat_props_params_tab" data-icon-class="icon-eye-open" >
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_PARAMETERS'); ?> </h3>
			
			<?php /*echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS' ) . '</h3>';*/ ?>
			<?php echo JHtml::_('tabs.start','basic-sliders-'.$this->form->getValue("id"), array('useCookie'=>1)); ?>
			
				<?php
				$fieldSets = $this->form->getFieldsets('params');
				foreach ($fieldSets as $name => $fieldSet) :
					if ($name == 'cat_basic' ) continue;
					if ($name == 'cat_notifications_conf' && ( !$this->cparams->get('enable_notifications', 0) || !$this->cparams->get('nf_allow_cat_specific', 0) ) ) continue;
					if ($name == 'cat_seo' ) continue;
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
				<?php endforeach; ?>
			
			<?php echo JHtml::_('tabs.end'); ?>
			
		</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_TEMPLATE'), 'cat-params-template');*/?>
		
		
		<div class="tabbertab" id="tabset_cat_props_tmpl_tab" data-icon-class="icon-palette" >
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_TEMPLATE'); ?> </h3>
			
			<?php echo '<span class="fc-info fc-nobgimage fc-mssg-inline" style="margin: 8px 0px 24px 0px !important; font-size:12px; min-width:50%;">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_EXPLANATION' ); ?>
			<br/><br/>
			<ol style="margin:0 0 0 16px; padding:0;">
				<li style="margin:0; padding:0;"> Select TEMPLATE layout </li>
				<li style="margin:0; padding:0;"> Open slider with TEMPLATE (layout) PARAMETERS </li>
			</ol>
			<br/>
			<b>NOTE:</b> Common method for -displaying- fields is by <b>editing the template layout</b> in template manager and placing the fields into <b>template positions</b>
			</span>
			
			<fieldset class="panelform">
				<?php
				$_p = & $this->row->params;
				foreach($this->form->getGroup('templates') as $field):
					$_name  = $field->fieldname;
					$_value = isset($_p[$_name])  ?  $_p[$_name]  :  null;
					
					if ($field->hidden):
						echo $field->input;
					else:
						// setValue(), is ok if input property, has not been already created
						// otherwise we need to re-initialize (which clears input)
						//$field->setup($field->element, $_value, $field->group);
						
						$field->setValue($_value);
						echo $field->label;
						echo $field->input;
						echo '<div class="clear"></div>';
					endif;
				endforeach;
				?>
			</fieldset>
			
			<div class="clear" style=""></div>
			
			<div style="max-width:1024px; margin-top:32px;">
				
				<?php echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1,'show'=>1)); ?>
				
				<?php
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
								if ($field->getAttribute('not_inherited')) continue;
								$fieldname =  $field->fieldname;
								$value = $tmpl->params->getValue($fieldname, $groupname, @$this->row->params[$fieldname]);
								echo str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
									$tmpl->params->getLabel($fieldname, $groupname));
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
				
			</div>
			
		</div>


		<?php if ($useAssocs) : ?>
		<!-- Associations tab -->
		<div class="tabbertab" id="fcform_tabset_assocs_tab" data-icon-class="icon-flag">
			<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_ASSOCIATIONS' ); ?> </h3>
			<?php echo $this->loadTemplate('associations'); ?>
		</div> <!-- end tab -->
		<?php endif; ?>


		<?php if ( $this->perms->CanRights ) : ?>
		<!-- Permissions tab -->
		<div class="tabbertab" id="fcform_tabset_perms_tab" data-icon-class="icon-power-cord">
			<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PERMISSIONS' ); ?> </h3>
			
			<div class="fc_tabset_inner">
				<div id="access"><?php echo $this->form->getInput('rules'); ?></div>
			</div>
			
		</div> <!-- end tab -->
		<?php endif; ?>

		
	</div><?php /*echo JHtml::_('tabs.end');*/ ?>
	
	
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