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

jimport( 'joomla.application.component.view');

/**
 * View class for the FLEXIcontent ACL edit screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewEditacl extends JViewLegacy
{
	function display($tpl = null)
	{
		$mainframe = &JFactory::getApplication();

		//initialise variables
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		
		//only admins have access to this view
		if ($user->get('gid') < 24) {
			JError::raiseWarning( 'SOME_ERROR_CODE', JText::_( 'FLEXI_ALERTNOTAUTH' ));
			$mainframe->redirect( 'index.php?option=com_flexicontent&view=flexicontent' );
		}

		//get vars
		$option		= JRequest::getVar('option');
		$filename	= 'flexicontent.acl.php';
		$path		= JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes';
		$acl_path	= $path.DS.$filename;

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_EDIT_ACL' ), 'editacl' );
		JToolBarHelper::apply( 'applyacl' );
		JToolBarHelper::save( 'saveacl' );
		JToolBarHelper::cancel();
		
		JRequest::setVar( 'hidemainmenu', 1 );

		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');

		//read the the stylesheet
		jimport('joomla.filesystem.file');
		$content = JFile::read($acl_path);
		
		jimport('joomla.client.helper');
		$ftp =& JClientHelper::setCredentialsFromRequest('ftp');

		if ($content !== false)
		{
			$content = htmlspecialchars($content, ENT_COMPAT, 'UTF-8');
		}
		else
		{
			$msg = JText::sprintf('FAILED TO OPEN FILE FOR WRITING', $acl_path);
			$mainframe->redirect('index.php?option='.$option, $msg);
		}

		//assign data to template
		$this->assignRef('acl_path'		, $acl_path);
		$this->assignRef('content'		, $content);
		$this->assignRef('filename'		, $filename);
		$this->assignRef('ftp'			, $ftp);
		

		parent::display($tpl);
	}
}