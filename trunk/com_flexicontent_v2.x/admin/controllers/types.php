<?php
/**
 * @version 1.5 stable $Id: types.php 1655 2013-03-16 17:55:25Z ggppdk $
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
 * FLEXIcontent Component Types Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerTypes extends FlexicontentController
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
		$this->registerTask( 'add',          'edit' );
		$this->registerTask( 'apply',        'save' );
		$this->registerTask( 'saveandnew',   'save' );
		if (!FLEXI_J16GE) {
			$this->registerTask( 'accesspublic',     'access' );
			$this->registerTask( 'accessregistered', 'access' );
			$this->registerTask( 'accessspecial',    'access' );
		}
		$this->registerTask( 'copy',         'copy' );
	}

	/**
	 * Logic to save a record
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$task  = JRequest::getVar('task');
		$model = $this->getModel('type');
		
		// Get data from request and validate them
		if (FLEXI_J16GE) {
			// Retrieve form data these are subject to basic filtering
			$data   = JRequest::getVar('jform', array(), 'post', 'array');    // Core Fields and and item Parameters
			
			// Validate Form data for core fields and for parameters
			$form = $model->getForm($data, false);
			$post = $model->validate($form, $data);
			if (!$post) JError::raiseWarning( 500, "Error while validating data: " . $model->getError() );
			
			// Some values need to be assigned after validation
			$post['attribs'] = @ $data['attribs'];   // Workaround for item's template parameters being clear by validation since they are not present in item.xml
		} else {
			// Retrieve form data these are subject to basic filtering
			$post = JRequest::get( 'post' );  // Core & Custom Fields and item Parameters
		}
		
		if ( $model->store($post) )
		{
			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_flexicontent&view=type&cid[]='.(int) $model->get('id');
					break;

				case 'saveandnew' :
					$link = 'index.php?option=com_flexicontent&view=type';
					break;

				default :
					$link = 'index.php?option=com_flexicontent&view=types';
					break;
			}
			$msg = JText::_( 'FLEXI_TYPE_SAVED' );

			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
			$filtercache = JFactory::getCache('com_flexicontent_filters');
			$filtercache->clean();

		} else {

			$msg = JText::_( 'FLEXI_ERROR_SAVING_TYPE' );
			JError::raiseWarning( 500, $model->getError() );
			$link 	= 'index.php?option=com_flexicontent&view=type';
		}

		$model->checkin();
		$this->setRedirect($link, $msg);
	}
	
	
	/**
	 * Check in a record
	 *
	 * @since	1.5
	 */
	function checkin()
	{
		$tbl = 'flexicontent_types';
		$redirect_url = 'index.php?option=com_flexicontent&view=types';
		flexicontent_db::checkin($tbl, $redirect_url, $this);
		return;// true;
	}
	
	
	/**
	 * Logic to publish records
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function publish()
	{
		$cid  = JRequest::getVar( 'cid', array(0), 'default', 'array' );

		$msg = '';
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_PUBLISH' ) );
		} else {
			$model = $this->getModel('types');

			if(!$model->publish($cid, 1)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
				if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
			}
			$total = count( $cid );
			$msg 	= $total.' '.JText::_( 'FLEXI_TYPE_PUBLISHED' );
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=types', $msg );
	}
	
	
	/**
	 * Logic to unpublish records
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function unpublish()
	{
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$model = $this->getModel('types');

		$msg = '';
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_UNPUBLISH' ) );
		} else if (!$model->candelete($cid)) {
			JError::raiseWarning(500, JText::_( 'FLEXI_YOU_CANNOT_UNPUBLISH_THIS_TYPE_THERE_ARE_STILL_ITEMS_ASSOCIATED' ));
		} else {

			if (!$model->publish($cid, 0)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
				if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
			}
			
			$msg = count($cid).' '.JText::_( 'FLEXI_TYPE_UNPUBLISHED' );
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=types', $msg );
	}

	
	/**
	 * Logic to delete records
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function remove()
	{
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$model = $this->getModel('types');

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		} else if (!$model->candelete($cid)) {
			JError::raiseWarning(500, JText::_( 'FLEXI_YOU_CANNOT_REMOVE_THIS_TYPE_THERE_ARE_STILL_ITEMS_ASSOCIATED' ));
		} else {
			
			if (!$model->delete($cid)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
				if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
			}
			
			$msg = count($cid).' '.JText::_( 'FLEXI_TYPES_DELETED' );
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=types', $msg );
	}
	
	
	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function cancel()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$post = JRequest::get('post');
		$post = FLEXI_J16GE ? $post['jform'] : $post;
		JRequest::setVar('cid', $post['id']);
		$this->checkin();
	}
	
	
	/**
	 * Logic to create the view for the record editing
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function edit()
	{
		JRequest::setVar( 'view', 'type' );
		JRequest::setVar( 'hidemainmenu', 1 );

		$model = $this->getModel('type');
		$user  = JFactory::getUser();
		
		// Check if record is checked out by other editor
		if ( $model->isCheckedOut( $user->get('id') ) ) {
			JError::raiseNotice( 500, JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=types', '');
			return;
		}
		
		// Checkout the record and proceed to edit form
		if ( !$model->checkout() ) {
			JError::raiseWarning( 500, $model->getError() );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=types', '');
			return;
		}
		
		parent::display();
	}
	
	/**
	 * Logic to set the access level of the Types
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function access()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$task  = JRequest::getVar( 'task' );
		$model = $this->getModel('types');
		$cid   = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$id    = (int)$cid[0];
		if (FLEXI_J16GE) {
			$accesses	= JRequest::getVar( 'access', array(0), 'post', 'array' );
			$access = $accesses[$id];
		} else {
			if ($task == 'accesspublic') {
				$access = 0;
			} elseif ($task == 'accessregistered') {
				$access = 1;
			} else {
				$access = 2;
			}
		}

		
		if(!$model->saveaccess( $id, $access )) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		} else {
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$filtercache = JFactory::getCache('com_flexicontent_filters');
			$filtercache->clean();
		}
		
		$this->setRedirect('index.php?option=com_flexicontent&view=types' );
	}
	
	
	/**
	 * Logic to set the access level of the Types
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function copy()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		$model = $this->getModel('types');
		
		if(!$model->copy( $cid )) {
			$msg = JText::_('FLEXI_TYPES_COPY_SUCCESS');
			JError::raiseWarning(500, JText::_( 'FLEXI_TYPES_COPY_FAILED' ));
		} else {
			$msg = JText::_('FLEXI_TYPES_COPY_SUCCESS');
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect('index.php?option=com_flexicontent&view=types', $msg );
	}

}