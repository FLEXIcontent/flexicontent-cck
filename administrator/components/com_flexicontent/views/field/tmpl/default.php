<?php
/**
 * @version 1.5 stable $Id: default.php 183 2009-11-18 10:30:48Z vistamedia $
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
$infoimage 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/lightbulb.png', JText::_( 'FLEXI_NOTES' ) );
?>

<form action="index.php" method="post" class="form-validate" name="adminForm" id="adminForm">

<table cellspacing="0" cellpadding="0" border="0" width="100%">
	<tr>
		<td valign="top">
			<fieldset>
			<legend><?php echo JText::_( 'FLEXI_FIELD_PROPERTIES' ); ?></legend>
				<table class="admintable">
					<tr>
						<td class="key">
							<label for="label">
								<?php echo JText::_( 'FLEXI_FIELD_LABEL' ).': *'; ?>
							</label>
						</td>
						<td>
							<input id ="label" name="label" value="<?php echo $this->row->label; ?>" class="required" maxlength="255" />
						</td>
					</tr>
					<?php if ($this->row->iscore == 0) : ?>
					<tr>
						<td class="key">
							<label for="name">
								<?php echo JText::_( 'FLEXI_FIELD_NAME' ).': *'; ?>
							</label>
						</td>
						<td>
							<input id="name" name="name" value="<?php echo $this->row->name; ?>" class="required" />
						</td>
					</tr>
					<?php else : ?>
						<td class="key">
							<label for="name">
								<?php echo JText::_( 'FLEXI_FIELD_NAME' ).': *'; ?>
							</label>
						</td>
						<td>
							<?php echo $this->row->name; ?>
						</td>
					<?php endif; ?>
					<?php
					$disabled = '';
					if ($this->row->id > 0 && $this->row->id < 7) $disabled = 'disabled="disabled"';
					?>
					<tr>
						<td class="key">
							<label for="published">
								<?php echo JText::_( 'FLEXI_PUBLISHED' ).':'; ?>
							</label>
						</td>
						<td>
							<?php
							$html = JHTML::_('select.booleanlist', 'published', 'class="inputbox"'.$disabled, $this->row->published );
							echo $html;
							?>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label for="published">
								<?php echo JText::_( 'FLEXI_FIELD_IS_SEARCHABLE' ).':'; ?>
							</label>
						</td>
						<td>
							<?php
							$html = JHTML::_('select.booleanlist', 'issearch', 'class="inputbox"', $this->row->issearch );
							echo $html;
							?>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label for="published">
								<?php echo JText::_( 'FLEXI_FIELD_IS_ADVANCED_SEARCHABLE' ).':'; ?>
							</label>
						</td>
						<td>
							<?php
							$html = JHTML::_('select.booleanlist', 'isadvsearch', 'class="inputbox"', $this->row->isadvsearch );
							echo $html;
							?>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label for="published">
								<?php echo JText::_( 'FLEXI_FIELD_ISFILTER' ).':'; ?>
							</label>
						</td>
						<td>
							<?php
							$html = JHTML::_('select.booleanlist', 'isfilter', 'class="inputbox"', $this->row->isfilter );
							echo $html;
							?>
						</td>
					</tr>
					<?php if (!FLEXI_ACCESS) : ?>
					<tr>
						<td class="key">
							<label for="access">
								<?php echo JText::_( 'FLEXI_ACCESS_LEVEL' ); ?>
							</label>
						</td>
						<td>
							<?php echo $this->lists['access']; ?>
						</td>
					</tr>
					<?php endif; ?>
					<?php if ($this->row->iscore == 0) : ?>
					<tr>
						<td class="key">
							<label for="field_type">
								<?php echo JText::_( 'FLEXI_FIELD_TYPE' ).': *'; ?>
							</label>
						</td>
						<td>
							<?php echo $this->lists['field_type']; ?>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<td class="key">
							<label for="field_type">
								<?php echo JText::_( 'Ordering' ).': '; ?>
							</label>
						</td>
						<td>
							<?php echo $this->lists['ordering']; ?>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label for="description">
								<?php echo JText::_( 'FLEXI_FIELD_DESCRIPTION' ).': '; ?>
							</label>
						</td>
						<td>
							<textarea id="description" cols="30" rows="5" name="description"><?php echo $this->row->description; ?></textarea>
						</td>
					</tr>
				</table>
			</fieldset>
	
			<?php
			if (FLEXI_ACCESS) :
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
		            		<div id="access"><?php echo $this->lists['access']; ?></div>
		            	</td>
		        	</tr>
		    	</table>
				<div id="notabacces">
				<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
		    	</div>
		    </fieldset>
		    <?php endif; ?>
	
		</td>

		<td valign="top" width="40%" style="padding: 7px 0 0 5px">
			<table width="100%" style="border: 1px dashed silver; padding: 5px; margin-bottom: 10px;">
				<tr>
					<td width="40%">
						<label for="tid">
						<strong><?php echo JText::_( 'FLEXI_TYPES' ); ?>: *</strong>
						<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_NOTES' ); ?>::<?php echo JText::_( 'FLEXI_TYPES_NOTES' );?>">
							<?php echo $infoimage; ?>
						</span>
						</label>
					</td>
					<td>
						<?php echo $this->lists['tid']; ?>
					</td>
				</tr>
			</table>
	
			<div class="pane-sliders" id="det-pane">
				<div class="panel">
					<h3 id="standard-page" class="title jpane-toggler-down"><span><?php echo JText::_( 'FLEXI_STANDARD_FIELDS_PROPERTIES' ); ?></span></h3>
					<div class="jpane-slider content" style="border-top: medium none; border-bottom: medium none; overflow: hidden; padding-top: 0px; padding-bottom: 0px;">
					<?php
					echo $this->form->render('params', 'standard');
					?>
					</div>
				</div>
				<div class="panel">
					<h3 id="group-page" class="title jpane-toggler-down"><span><?php echo JText::_( 'FLEXI_THIS_FIELDTYPE_PROPERTIES' ); ?></span></h3>
					<div id="fieldspecificproperties" class="jpane-slider content" style="border-top: medium none; border-bottom: medium none; overflow: hidden; padding-top: 0px; padding-bottom: 0px;">
					<?php
					if ($this->row->field_type)
					{
						echo $this->form->render('params', 'group-' . $this->row->field_type );
					} else {
						global $global_field_types;
						if(isset($global_field_types[0])) {
							// Create the form
							$pluginpath = JPATH_PLUGINS.DS.'flexicontent_fields'.DS.$global_field_types[0]->value.'.xml';
							if (JFile::exists( $pluginpath )) {
								$form = new JParameter('', $pluginpath);
							} else {
								$form = new JParameter('', JPATH_PLUGINS.DS.'flexicontent_fields'.DS.'core.xml');
							}
							echo $form->render('params', 'group-' . $global_field_types[0]->value );
						}else
						echo "<br /><span style=\"padding-left:25px;\"'>" . JText::_( 'FLEXI_APPLY_TO_SEE_THE_PARAMETERS' ) . "</span><br /><br />";
					}
					?>
					</div>
				</div>
			</div>
		</td>
	</tr>
</table>
<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<?php if ($this->row->iscore == 1) : ?>
<input type="hidden" name="iscore" value="<?php echo $this->row->iscore; ?>" />
<input type="hidden" name="name" value="<?php echo $this->row->name; ?>" />
<input type="hidden" name="field_type" value="<?php echo $this->row->field_type; ?>" />
<?php endif; ?>
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="controller" value="fields" />
<input type="hidden" name="view" value="field" />
<input type="hidden" name="task" value="" />
</form>
			
<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>