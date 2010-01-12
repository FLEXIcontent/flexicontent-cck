<?php
/**
 * @version 1.5 beta 5 $Id: view.html.php 183 2009-11-18 10:30:48Z vistamedia $
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
 * View class for the FLEXIcontent category screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewType extends JView {

	function display($tpl = null)
	{
		global $mainframe;

		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		$pane 		= & JPane::getInstance('sliders');

		JHTML::_('behavior.tooltip');

		//get vars
		$cid 		= JRequest::getVar( 'cid' );

		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		//add js function to overload the joomla submitform
		$document->addScript('components/com_flexicontent/assets/js/admin.js');
		$document->addScript('components/com_flexicontent/assets/js/validate.js');

		//create the toolbar
		if ( $cid ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_TYPE' ), 'typeedit' );
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_ADD_TYPE' ), 'typeadd' );

			JSubMenuHelper::addEntry( JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent');
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items');
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types');
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories');
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields');
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags');
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive');
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager');
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats');
		}
		JToolBarHelper::apply();
		JToolBarHelper::save();
		JToolBarHelper::cancel();


		//Get data from the model
		$model		= & $this->getModel();
		$row     	= & $this->get( 'Type' );
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->items;

		// fail if checked out not by 'me'
		if ($row->id) {
			if ($model->isCheckedOut( $user->get('id') )) {
				JError::raiseWarning( 'SOME_ERROR_CODE', $row->name.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
				$mainframe->redirect( 'index.php?option=com_flexicontent&view=types' );
			}
		}

		//clean data
		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES );

		//create the parameter form
		$form = new JParameter('', JPATH_COMPONENT.DS.'models'.DS.'type.xml');
		$form->loadINI($row->attribs);
		foreach ($tmpls as $tmpl) {
			$tmpl->params->loadINI($row->attribs);
		}

		//assign data to template
		$this->assignRef('row'      	, $row);
		$this->assignRef('form'			, $form);
		$this->assignRef('pane'			, $pane);
		$this->assignRef('tmpls'		, $tmpls);

		parent::display($tpl);
	}
}
?>