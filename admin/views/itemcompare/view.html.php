<?php
/**
 * @version 1.5 stable $Id: view.html.php 1800 2013-11-01 04:30:57Z ggppdk $
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
 * View class for the FLEXIcontent item comparison screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItemcompare extends JViewLegacy {

	function display($tpl = null)
	{
		$mainframe = JFactory::getApplication();
		$option = JRequest::getVar('option');

		//initialise variables
		$db       = JFactory::getDbo();
		$document = JFactory::getDocument();
		$template   = $mainframe->getTemplate();
		$dispatcher = JEventDispatcher::getInstance();
		$rev      = JRequest::getInt('version','','request');
		$codemode = JRequest::getInt('codemode',0);
		$cparams  = JComponentHelper::getParams('com_flexicontent');
		
		JHtml::_('behavior.modal');

		//a trick to avoid loosing general style in modal window
		$css = 'body, td, th { font-size: 11px; } .novalue { color: gray; font-style: italic; }';
		$document->addStyleDeclaration($css);

		//Get data from the model
		$model			= $this->getModel();
		$row     		= $model->getItem();
		$fields			= $model->getExtrafields();
		$versions		= $model->getVersionList();
		$tparams		= $model->getTypeparams();
				
		// Create the type parameters
		$tparams = new JRegistry($tparams);
		
		// Add html to field object trought plugins
		foreach ($fields as $field)
		{
			// Render current field value
			if ($field->iscore) {
				$items_params = null;
				FLEXIUtilities::call_FC_Field_Func('core', 'onDisplayCoreFieldValue', array( &$field, &$row, &$items_params, false, false, false, false, false, null, 'display' ));
			}
			else if ($field->value) {
				//$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $row ));
				$field_type = $field->field_type;
				FLEXIUtilities::call_FC_Field_Func($field_type, 'onDisplayFieldValue', array( &$field, $row ));
			}
			else {
				$field->display = '<span class="novalue">' . JText::_('FLEXI_NO_VALUE') . '</span>';
			}
			
			// Render versioned field value
			if ($field->version)
			{
				if ( in_array($field->field_type, array(/*'tags', 'categories', */'maintext')) )
				{
					// TODO render more core fields for versioned value, $field->version is raw we need to convert it to $categories, $tags, etc
					$items_params = null;
					FLEXIUtilities::call_FC_Field_Func('core', 'onDisplayCoreFieldValue', array( &$field, &$row, &$items_params, false, false, false, false, false, $field->version, 'displayversion' ));
				}
				else if ($field->iscore) {
					$field->displayversion = $field->version;
				}
				else {
					//$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $row, $field->version, 'displayversion' ));
					$field_type = $field->field_type;
					FLEXIUtilities::call_FC_Field_Func($field_type, 'onDisplayFieldValue', array( &$field, $row, $field->version, 'displayversion' ));
				}
			} else {
				$field->displayversion = '<span class="novalue">' . JText::_('FLEXI_NO_VALUE') . '</span>';
			}
		}
		
		//assign data to template
		$this->document = $document;
		$this->row = $row;
		$this->fields = $fields;
		$this->versions = $versions;
		$this->rev = $rev;
		$this->tparams = $tparams;
		$this->cparams = $cparams;
		$this->codemode = $codemode;

		parent::display($tpl);
	}
}
?>