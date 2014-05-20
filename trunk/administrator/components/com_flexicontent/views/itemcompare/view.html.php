<?php
/**
 * @version 1.5 stable $Id$
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

jimport('joomla.application.component.view');

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
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		$template   = $mainframe->getTemplate();
		$dispatcher = JDispatcher::getInstance();
		$rev      = JRequest::getInt('version','','request');
		$codemode = JRequest::getInt('codemode',0);
		$cparams  = JComponentHelper::getParams('com_flexicontent');
		
		FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
		flexicontent_html::loadFramework('jQuery');
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal');

		//a trick to avoid loosing general style in modal window
		$css = 'body, td, th { font-size: 11px; } .novalue { color: gray; font-style: italic; }';
		$document->addStyleDeclaration($css);

		//Get data from the model
		$model			= $this->getModel();
		$row     		= $this->get( 'Item' );
		$fields			= $this->get( 'Extrafields' );
		$versions		= $this->get( 'VersionList' );
		$tparams		= $this->get( 'Typeparams' );
				
		// Create the type parameters
		$tparams = FLEXI_J16GE ? new JRegistry($tparams) : new JParameter($tparams);
		
		// Add html to field object trought plugins
		foreach ($fields as $field)
		{
			if ($field->value) {
				//$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $row ));
				$fieldname = $field->iscore ? 'core' : $field->field_type;
				FLEXIUtilities::call_FC_Field_Func($fieldname, 'onDisplayFieldValue', array( &$field, $row ));
			} else {
				$field->display = '<span class="novalue">' . JText::_('FLEXI_NO_VALUE') . '</span>';
			}
			if ($field->version) {
				//$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $row, $field->version, 'displayversion' ));
				$fieldname = $field->iscore ? 'core' : $field->field_type;
				FLEXIUtilities::call_FC_Field_Func($fieldname, 'onDisplayFieldValue', array( &$field, $row, $field->version, 'displayversion' ));
			} else {
				$field->displayversion = '<span class="novalue">' . JText::_('FLEXI_NO_VALUE') . '</span>';
			}
		}

		//assign data to template
		$this->assignRef('document'     , $document);
		$this->assignRef('row'      	, $row);
		$this->assignRef('fields'		, $fields);
		$this->assignRef('versions'		, $versions);
		$this->assignRef('rev'			, $rev);
		$this->assignRef('tparams'		, $tparams);
		$this->assignRef('cparams'		, $cparams);
		$this->assignRef('codemode'		, $codemode);

		parent::display($tpl);
	}
}
?>