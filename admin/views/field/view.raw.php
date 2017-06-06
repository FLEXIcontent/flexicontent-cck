<?php
/**
 * @version 1.5 stable $Id: view.raw.php 1577 2012-12-02 15:10:44Z ggppdk $
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

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('legacy.view.legacy');

/**
 * View class for the FLEXIcontent field screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewField extends JViewLegacy
{
	function display($tpl = null)
	{
		// ***
		// *** Initialise variables
		// ***

		$app      = JFactory::getApplication();
		$jinput   = $app->input;
		$user     = JFactory::getUser();

		// Get url vars and some constants
		$cid = $jinput->get('cid', 0, 'int');
		$field_type = $jinput->get('field_type', '', 'cmd');



		// ***
		// *** Get record data, and check if record is already checked out
		// ***
		
		// Get model and load the record data
		$model = $this->getModel();
		$row   = $this->get('Item');
		$isnew = ! $row->id;

		// Get JForm
		$form  = $this->get('Form');
		if (!$form)
		{
			jexit($model->getError());
		}

		// Fail if an existing record is checked out by someone else
		if ($row->id && $model->isCheckedOut($user->get('id')))
		{
			$app->enqueueMessage(JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ), 'warning');
			$app->redirect( 'index.php?option=com_flexicontent&view=' . $manager_view );
		}
		
		?>
		<div class="fctabber fields_tabset" id="field_specific_props_tabset">
			<?php
			$fieldSets = $form->getFieldsets('attribs');
			$prefix_len = strlen('group-'.$field_type.'-');
			foreach ($fieldSets as $name => $fieldSet) :
				if ($name!='basic' && $name!='standard' && (substr($name, 0, $prefix_len)!='group-'.$field_type.'-' || $name==='group-'.$field_type) ) continue;
				if ($fieldSet->label) $label = JText::_($fieldSet->label);
				else $label = $name=='basic' || $name=='standard' ? JText::_('FLEXI_BASIC') : ucfirst(str_replace("group-", "", $name));
				
				if (@$fieldSet->label_prefix) $label = JText::_($fieldSet->label_prefix) .' - '. $label;
				$icon = @$fieldSet->icon_class ? 'data-icon-class="'.$fieldSet->icon_class.'"' : '';
				$prepend = @$fieldSet->prepend_text ? 'data-prefix-text="'.JText::_($fieldSet->prepend_text).'"' : '';
				
				$description = $fieldSet->description ? JText::_($fieldSet->description) : '';
				?>
				<div class="tabbertab" id="fcform_tabset_<?php echo $name; ?>_tab" <?php echo $icon; ?> <?php echo $prepend; ?>>
					<h3 class="tabberheading hasTooltip" title="<?php echo $description; ?>"><?php echo $label; ?> </h3>
					<?php $i = 0; ?>
					<?php foreach ($form->getFieldset($name) as $field) {
						$_depends = $field->getAttribute('depend_class');

						if ( $field->getAttribute('box_type') )
							echo $field->input;
						else
							echo '
						<fieldset class="panelform'.($i ? '' : ' fc-nomargin').' '.($_depends ? ' '.$_depends : '').'" id="'.$field->id.'-container">
							'.($field->label ? '
								<span class="label-fcouter">'.str_replace('class="', 'class="label-fcinner ', $field->label).'</span>
								<div class="container_fcfield">'.$field->input.'</div>
							' : $field->input).'
						</fieldset>
						';
						$i++;
					} ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}