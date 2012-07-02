<?php
/**
 * @version 1.5 stable $Id: view.html.php 870 2011-08-28 23:19:55Z ggppdk $
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

jimport( 'joomla.application.component.view');

/**
 * View class for the FLEXIcontent type screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewType extends JView {

	function display($tpl = null)
	{
		$mainframe = &JFactory::getApplication();

		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();

		JHTML::_('behavior.tooltip');

		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		//add js function to overload the joomla submitform
		$document->addScript('components/com_flexicontent/assets/js/admin.js');
		$document->addScript('components/com_flexicontent/assets/js/validate.js');

		//Get data from the model
		$model		= & $this->getModel();
		$row     	= & $this->get( 'Item' );
		$form			= & $this->get('Form');
		$cid			=	FLEXI_J16GE ? $form->getValue('id') : $row->id;
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->items;
		
		//create the toolbar
		if ( $cid ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_TYPE' ), 'typeedit' );
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_ADD_TYPE' ), 'typeadd' );
		}
		JToolBarHelper::apply('types.apply');
		JToolBarHelper::save('types.save');
		JToolBarHelper::custom( 'types.saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolBarHelper::cancel('types.cancel');
		
		// fail if checked out not by 'me'
		if ($cid) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=types' );
			}
		}
		
		// Apply Template Parameters values into the form fields structures 
		foreach ($tmpls as $tmpl) {
			if (FLEXI_J16GE) {
				$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => true));
				$jform->load($tmpl->params);
				$tmpl->params = $jform;
				// ... values applied at the template form file
			} else {
				$tmpl->params->loadINI($row->attribs);
			}
		}		
		
		//assign data to template
		$this->assignRef('row'			, $row);
		$this->assignRef('form'			, $form);
		$this->assignRef('tmpls'		, $tmpls);
		
		parent::display($tpl);
	}
}
?>
