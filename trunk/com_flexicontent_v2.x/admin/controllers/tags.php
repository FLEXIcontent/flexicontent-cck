<?php
/**
 * @version 1.5 stable $Id: tags.php 1655 2013-03-16 17:55:25Z ggppdk $
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
 * FLEXIcontent Component Tags Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerTags extends FlexicontentController
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
		$this->registerTask( 'import', 			'import' );
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
		$model = $this->getModel('tag');

		// Get posted data
		$post = JRequest::get( 'post' );
		
		if ( $model->store($post) )
		{
			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_flexicontent&view=tag&cid[]='.(int) $model->get('id');
					break;

				case 'saveandnew' :
					$link = 'index.php?option=com_flexicontent&view=tag';
					break;

				default :
					$link = 'index.php?option=com_flexicontent&view=tags';
					break;
			}
			$msg = JText::_( 'FLEXI_TAG_SAVED' );

			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
			$filtercache = JFactory::getCache('com_flexicontent_filters');
			$filtercache->clean();

		} else {

			$msg = JText::_( 'FLEXI_ERROR_SAVING_TAG' );
			JError::raiseWarning( 500, $model->getError() );
			$link 	= 'index.php?option=com_flexicontent&view=tag';
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
		$tbl = 'flexicontent_tags';
		$redirect_url = 'index.php?option=com_flexicontent&view=tags';
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
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$model = $this->getModel('tags');
		
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_PUBLISH' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=tags', '');
			return;
		}
		$msg = '';
		if(!$model->publish($cid, 1)) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}
		
		$total = count( $cid );
		$msg 	= $total.' '.JText::_( 'FLEXI_TAG_PUBLISHED' );
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=tags', $msg );
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
		$model = $this->getModel('tags');
		
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_UNPUBLISH' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=tags', '');
			return;
		}
		
		
		$msg = '';
		if (!$model->publish($cid, 0)) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}
		$total = count( $cid );
		$msg 	= $total.' '.JText::_( 'FLEXI_TAG_UNPUBLISHED' );
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=tags', $msg );
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
		$cid   = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$model = $this->getModel('tags');

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		} else {

			if (!$model->delete($cid)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
				if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
			}
			
			$msg = count($cid).' '.JText::_( 'FLEXI_TAGS_DELETED' );
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=tags', $msg );
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

		$tag = JTable::getInstance('flexicontent_tags', '');
		$tag->bind(JRequest::get('post'));
		$tag->checkin();

		$this->setRedirect( 'index.php?option=com_flexicontent&view=tags' );
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
		JRequest::setVar( 'view', 'tag' );
		JRequest::setVar( 'hidemainmenu', 1 );

		$model = $this->getModel('tag');
		$user  = JFactory::getUser();
		
		// Check if record is checked out by other editor
		if ( $model->isCheckedOut( $user->get('id') ) ) {
			JError::raiseNotice( 500, JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=tags', '');
			return;
		}
		
		// Checkout the record and proceed to edit form
		if ( !$model->checkout() ) {
			JError::raiseWarning( 500, $model->getError() );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=tags', '');
			return;
		}
		
		parent::display();
	}
	
}