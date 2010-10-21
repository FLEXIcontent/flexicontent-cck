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

<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
				<table  class="adminform">
					<tr>
						<td class="key">
							<label for="title">
								<?php echo JText::_( 'FLEXI_TITLE' ).':'; ?>
							</label>
						</td>
						<td>
							<input id="title" name="title" class="required" value="<?php echo $this->row->title; ?>" size="50" maxlength="100" />
						</td>
						<td>
							<label for="published">
								<?php echo JText::_( 'FLEXI_PUBLISHED' ).':'; ?>
							</label>
						</td>
						<td>
							<?php
							$html = JHTML::_('select.booleanlist', 'published', 'class="inputbox"', $this->row->published );
							echo $html;
							?>
						</td>
					</tr>
					<tr>
						<td>
							<label for="alias">
								<?php echo JText::_( 'FLEXI_ALIAS' ).':'; ?>
							</label>
						</td>
						<td>
							<input class="inputbox" type="text" name="alias" id="alias" size="50" maxlength="100" value="<?php echo $this->row->alias; ?>" />
						</td>
						<td>
							<label for="parent">
								<?php echo JText::_( 'FLEXI_PARENT' ).':'; ?>
							</label>
						</td>
						<td>
							<?php				
								echo $this->Lists['parent_id'];
							?>
						</td>
					</tr>
					<tr>
						<td>
							<label for="parent">
								<?php echo JText::_( 'FLEXI_COPY_PARAMETERS' ).':'; ?>
							</label>
						</td>
						<td>
							<?php				
								echo $this->Lists['copyid'];
							?>
						</td>
						<td>
						</td>
						<td>
						</td>
					</tr>
				</table>
									
				<?php
				if ($this->permission->CanCats) :
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
							<?php
							// parameters : areaname, content, hidden field, width, height, rows, cols
							echo $this->editor->display( 'description',  $this->row->description, '100%;', '350', '75', '20', array('pagebreak', 'readmore') ) ;
							?>
						</td>
					</tr>
				</table>
			
			</td>
			<td valign="top" width="320px" style="padding: 7px 0 0 5px">
				<?php
				$title = JText::_( 'FLEXI_ACCESS' );
				echo $this->pane->startPane( 'det-pane' );
				echo $this->pane->startPanel( $title, 'access' );
				?>
				<table>
					<tr>
						<td>
							<label for="access">
								<?php echo $this->iform->getLabel('access'); ?>
							</label>
						</td>
						<td>
							<?php echo $this->iform->getInput('access'); ?>
						</td>
					</tr>
				</table>
<?php echo JHtml::_('sliders.start','plugin-sliders-'.$this->row->id, array('useCookie'=>1)); ?>
				<?php
				echo $this->pane->endPanel();
				$fieldSets = $this->iform->getFieldsets('params');

foreach ($fieldSets as $name => $fieldSet) :
	$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_'.$name.'_FIELDSET_LABEL';
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
		<?php echo $this->iform->getLabel('note'); ?>
		<?php echo $this->iform->getInput('note'); ?>
	</fieldset>
<?php endforeach; ?>
<?php echo JHtml::_('sliders.end'); ?>
				<?php echo $this->pane->endPane();
				?>
			</td>
		</tr>
	</table>

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="section" value="<?php echo $this->row->section; ?>" />
<input type="hidden" name="controller" value="categories" />
<input type="hidden" name="view" value="category" />
<input type="hidden" name="task" value="" />
</form>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
