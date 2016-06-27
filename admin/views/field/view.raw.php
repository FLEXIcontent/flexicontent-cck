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
		//initialise variables
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		
		//JHTML::_('behavior.tooltip');

		//get vars
		$cid 		= JRequest::getVar( 'cid' );
		$field_type = JRequest::getVar( 'field_type', 0 );
		
		//Get data from the model
		$model  = $this->getModel();
		$form   = $this->get('Form');
		if (!FLEXI_J16GE) {
			$row = & $this->get( 'Field' );
			
			//Import File system
			jimport('joomla.filesystem.file');
			
			// Create the form
			$pluginpath = JPATH_PLUGINS.DS.'flexicontent_fields'.DS.$field_type.'.xml';
			if (JFile::exists( $pluginpath )) {
				$form = new JParameter('', $pluginpath);
			} else {
				$form = new JParameter('', JPATH_PLUGINS.DS.'flexicontent_fields'.DS.'core.xml');
			}
			$form->loadINI($row->attribs);
		}
		
		$isnew = FLEXI_J16GE ? !$form->getValue('id') : !$row->id;
		
		// fail if checked out not by 'me'
		if ( !$isnew ) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$app->redirect( 'index.php?option=com_flexicontent&view=fields' );
			}
		}
		
		/*if ($field_type)
		{
			if (!FLEXI_J16GE) {
				echo $form->render('params', 'group-' . $field_type );
			} else {
				foreach ($form->getFieldset('group-' . $field_type) as $field) {
					echo '<fieldset class="panelform">' . $field->label . $field->input . '</fieldset>' . "\n";
				}
			}
		} else {
			echo "<br /><span style=\"padding-left:25px;\"'>" . JText::_( 'FLEXI_APPLY_TO_SEE_THE_PARAMETERS' ) . "</span><br /><br />";
		}*/
		
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
								<span class="label-fcouter">'.str_replace('class="', 'class="label label-fcinner ', $field->label).'</span>
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
		//parent::display($tpl);
	}
}
?>