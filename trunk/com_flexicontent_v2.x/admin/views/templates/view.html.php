<?php
/**
 * @version 1.5 stable $Id: view.html.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
 * View class for the FLEXIcontent templates screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewTemplates extends JView {

	function display($tpl = null) {
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		//initialise variables
		$db  		= & JFactory::getDBO();
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal');

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/silveripe.js' );

		$permission = FlexicontentHelperPerm::getPerm();

		if (!$permission->CanTemplates) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		//Create Submenu
		FLEXIcontentSubmenu('CanTemplates');
		
		if($permission->CanConfig) JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_TEMPLATES' ), 'templates' );

		$tmpldirectory 	= JPATH_COMPONENT_SITE . DS . 'templates' . DS;
		$source			= JRequest::getString('source', '');
		$dest			= $source ? flexicontent_upload::sanitizedir($tmpldirectory, $source) : '';

		//Get data from the model
		$rows      	= & $this->get( 'Data');

		//assign data to template
		$this->assignRef('rows'      		, $rows);
		$this->assignRef('user'      		, $user);
		$this->assignRef('tmpldirectory' 	, $tmpldirectory);
		$this->assignRef('source'      		, $source);
		$this->assignRef('dest'      		, $dest);

		parent::display($tpl);
	}
}
?>