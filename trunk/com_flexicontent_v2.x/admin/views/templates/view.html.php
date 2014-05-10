<?php
/**
 * @version 1.5 stable $Id: view.html.php 1869 2014-03-12 12:18:40Z ggppdk $
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
 * View class for the FLEXIcontent templates screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewTemplates extends JViewLegacy
{
	function display($tpl = null)
	{
		//initialise variables
		$mainframe = JFactory::getApplication();
		$option    = JRequest::getVar('option');
		$document  = JFactory::getDocument();
		$user      = JFactory::getUser();
		$db        = JFactory::getDBO();
		
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal');

		//add css and submenu to document
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');

		$permission = FlexicontentHelperPerm::getPerm();

		if (!$permission->CanTemplates) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		//Create Submenu
		FLEXISubmenu('CanTemplates');
		
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_TEMPLATES' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'templates' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		//JToolBarHelper::Back();
		if ($perms->CanConfig) {
			//JToolBarHelper::divider(); JToolBarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolBarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		$tmpldirectory = JPATH_COMPONENT_SITE . DS . 'templates' . DS;
		$source = JRequest::getString('source', '');
		$dest   = $source ? flexicontent_upload::sanitizedir($tmpldirectory, $source) : '';

		//Get data from the model
		$rows = $this->get( 'Data');

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