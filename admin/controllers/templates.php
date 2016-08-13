<?php
/**
 * @version 1.5 stable $Id: templates.php 1342 2012-06-07 01:48:19Z ggppdk $
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

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');

/**
 * FLEXIcontent Component Templates Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerTemplates extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'add'  ,     'edit' );
		$this->registerTask( 'apply',     'save' );
		$this->registerTask( 'apply_modal', 'save' );
	}


	/**
	 * Logic to save a template
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function save()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		// check access
		if ( !FlexicontentHelperPerm::getPerm()->CanTemplates )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent', '');
			return;
		}

		$post   = JRequest::get( 'post' );
		$task   = JRequest::getVar('task');
		$type   = JRequest::getVar('type',  'items', '', 'word');
		$folder = JRequest::getVar('folder',  'default', '', 'cmd');
		$cfgname= JRequest::getVar('cfgname',  '', '', 'cmd');
		
		$positions = JRequest::getVar('positions',  '');
		$positions = explode(',', $positions);
		$attribs = $post['jform']['layouts'][$folder];
		
		// Get model
		$model = $this->getModel('template');
		
		// Store field positions
		$model->storeFieldPositions($folder, $cfgname, $type, $positions, $post);
		
		// Store Layout configurations (template parameters)
		$model->storeLayoutConf($folder, $cfgname, $type, $attribs);
		
		// Store LESS configuration (less variables)
		$model->storeLessConf($folder, $cfgname, $type, $attribs);
		
		switch ($task)
		{
			case 'apply_modal' :
				$link = 'index.php?option=com_flexicontent&view=template&type='.$type.'&folder='.$folder.'&tmpl=component&ismodal=1';
				break;

			case 'apply' :
				$link = 'index.php?option=com_flexicontent&view=template&type='.$type.'&folder='.$folder;
				break;

			default :
				$link = 'index.php?option=com_flexicontent&view=templates';
				break;
		}
		$msg = JText::_( 'FLEXI_SAVE_FIELD_POSITIONS' );

		$this->setRedirect($link, $msg);
	}

	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function cancel()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		// check access
		if ( !FlexicontentHelperPerm::getPerm()->CanTemplates )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent', '');
			return;
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=templates' );
	}

}