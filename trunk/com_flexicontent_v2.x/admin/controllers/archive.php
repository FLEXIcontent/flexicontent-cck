<?php
/**
 * @version 1.5 stable $Id: archive.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

jimport('joomla.application.component.controller');

/**
 * FLEXIcontent Component Archive Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerArchive extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 *@since 1.0
	 */
	function __construct()
	{
		parent::__construct();
	}

	/**
	 * unarchives an Item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function unarchive()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_UNARCHIVE' ) );
		}
		
		$model = $this->getModel('archive');

		if(!$model->unarchive($cid)) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}

		$total = count( $cid );
		$msg 	= $total.' '.JText::_( 'FLEXI_ITEMS_UNARCHIVED' );

		$this->setRedirect( 'index.php?option=com_flexicontent&view=archive', $msg );
	}

	/**
	 * removes an itam
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function remove()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$cid = JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		}		
		
		$model = $this->getModel('archive');
		if(!$model->delete($cid)) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}

		$total = count( $cid );
		$msg = $total.' '.JText::_( 'FLEXI_ITEMS_DELETED' );

		$this->setRedirect( 'index.php?option=com_flexicontent&view=archive', $msg );
	}
}
?>