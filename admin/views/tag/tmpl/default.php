<?php
/**
 * @version 1.5 stable $Id: default.php 1079 2012-01-02 00:18:34Z ggppdk $
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

$form = $this->form;
?>

<div class="flexicontent" id="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">
	<?php
	$fieldSets = $this->form->getFieldsets();
	foreach ($fieldSets as $name => $fieldSet) :

		//$label = !empty($fieldSet->label) ? $fieldSet->label : 'COM_FLEXICONTENT_'.$name.'_FIELDSET_LABEL';
		//echo '<h2>' . JText::_($label) . '</h2>';
	?>



		<?php foreach ($this->form->getFieldset($name) as $field) : ?>
			<?php if ($field->hidden): ?>
				<div style="display:none !important;">
					<?php echo $field->input; ?>
				</div>
			<?php else: ?>
<div class="control-group"> 
<div class="control-label"><?php echo $field->label; ?></div>
<div class="controls"><?php echo $field->input; ?></div>
</div>
			<?php endif; ?>
		<?php endforeach; ?>

	

	<?php endforeach; ?>

	<?php echo JHtml::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="tags" />
	<input type="hidden" name="view" value="tag" />
	<input type="hidden" name="task" value="" />
</form>
</div>
<div style="margin-bottom:24px;"></div>

<?php
//keep session alive while editing
JHtml::_('behavior.keepalive');
?>
