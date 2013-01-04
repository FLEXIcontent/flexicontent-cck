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
 * View class for the FLEXIcontent file screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFile extends JViewLegacy {

	function display($tpl = null)
	{
		$mainframe = &JFactory::getApplication();

		//initialise variables
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();

		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_EDIT_FILE' ), 'fileedit' );
		
		if (FLEXI_J16GE) {
			JToolBarHelper::apply('filemanager.apply');
			JToolBarHelper::save('filemanager.save');
			JToolBarHelper::cancel('filemanager.cancel');
		} else {
			JToolBarHelper::apply();
			JToolBarHelper::save();
			JToolBarHelper::cancel();
		}
		//Get data from the model
		$model		= & $this->getModel();
		if (FLEXI_J16GE) $form = & $this->get('Form');
		$row     	= & $this->get( 'File' );
		
		// fail if checked out not by 'me'
		if ($row->id) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=filemanager' );
			}
		}
		
		//build access level list
		if (FLEXI_ACCESS) {
			$lists['access']	= FAccess::TabGmaccess( $row, 'field', 1, 0, 0, 0, 0, 0, 0, 0, 0 );
		} else {
			$lists['access'] 	= JHTML::_('list.accesslevel', $row );
		}
		$document	= & JFactory::getDocument();

		//clean data
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES );

		//assign data to template
		if (FLEXI_J16GE) $this->assignRef('form'				, $form);
		$this->assignRef('row'				, $row);
		$this->assignRef('lists'			, $lists);
		$this->assignRef('document'		, $document);

		parent::display($tpl);
	}
}
?>