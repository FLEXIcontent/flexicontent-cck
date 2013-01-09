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
		$this->registerTask( 'add'  ,				'edit' );
		$this->registerTask( 'apply', 			'save' );
		$this->registerTask( 'saveandnew',	'save' );
		$this->registerTask( 'import', 			'import' );
	}

	/**
	 * Logic to save a tag
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$task		= JRequest::getVar('task');

		//Sanitize
		$post = JRequest::get( 'post' );

		$model = $this->getModel('tag');

		if ( $model->store($post) ) {

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

			$cache = &JFactory::getCache('com_flexicontent');
			$cache->clean();

		} else {

			$msg = JText::_( 'FLEXI_ERROR_SAVING_TAG' );
			//JError::raiseWarning( 500, $model->getError() );
			$link 	= 'index.php?option=com_flexicontent&view=tag';
		}

		$model->checkin();

		$this->setRedirect($link, $msg);
	}

	/**
	 * Logic to publish categories
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function publish()
	{
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_PUBLISH' ) );
		} else {
			$model = $this->getModel('tags');

			if(!$model->publish($cid, 1)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
				if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
			}

			$total = count( $cid );
			$msg 	= $total.' '.JText::_( 'FLEXI_TAG_PUBLISHED' );
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=tags', $msg );
	}

	/**
	 * Logic to unpublish categories
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function unpublish()
	{
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_UNPUBLISH' ) );
		} else {
			$model = $this->getModel('tags');

			if(!$model->publish($cid, 0)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
				if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
			}

			$total = count( $cid );
			$msg 	= $total.' '.JText::_( 'FLEXI_TAG_UNPUBLISHED' );
			$cache = &JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=tags', $msg );
	}

	/**
	 * Logic to delete categories
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function remove()
	{
		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		} else {
			$model = $this->getModel('tags');

			if (!$model->delete($cid)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
				if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
			}
			
			$msg = count($cid).' '.JText::_( 'FLEXI_TAGS_DELETED' );
			$cache = &JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=tags', $msg );
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
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$tag = & JTable::getInstance('flexicontent_tags', '');
		$tag->bind(JRequest::get('post'));
		$tag->checkin();

		$this->setRedirect( 'index.php?option=com_flexicontent&view=tags' );
	}

	/**
	 * Logic to create the view for the edit categoryscreen
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function edit( )
	{
		JRequest::setVar( 'view', 'tag' );
		JRequest::setVar( 'hidemainmenu', 1 );

		$model 	= $this->getModel('tag');
		$user	=& JFactory::getUser();

		// Error if checkedout by another administrator
		if ($model->isCheckedOut( $user->get('id') )) {
			$this->setRedirect( 'index.php?option=com_flexicontent&view=tags', JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ) );
		}

		$model->checkout( $user->get('id') );

		parent::display();
	}



	//*************************
	// RAW output functions ...
	//*************************
	
	 
	/**
	 * Logic to import a tag list
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function import( )
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$list		= JRequest::getVar( 'taglist', null, 'post', 'string' );

		$model	= $this->getModel('tags');		
		$logs 	= $model->importList($list);
		
		if ($logs) {
			if ($logs['success']) {
				echo '<div class="copyok">'.JText::sprintf( 'FLEXI_TAG_IMPORT_SUCCESS', $logs['success'] ).'</div>';
			}
			if ($logs['error']) {
				echo '<div class="copywarn>'.JText::sprintf( 'FLEXI_TAG_IMPORT_FAILED', $logs['error'] ).'</div>';
			}
		} else {
			echo '<div class="copyfailed">'.JText::_( 'FLEXI_NO_TAG_TO_IMPORT' ).'</div>';
		}
	}
	
	
	/**
	 *  Add new Tag from item screen
	 *
	 */
	function addtag()
	{
		// Check for request forgeries
		// JRequest::checkToken('request') or jexit( 'Invalid Token' );

		$name 	= JRequest::getString('name', '');
		$model 	= $this->getModel('tag');
		$array = JRequest::getVar('cid',  0, '', 'array');
		$cid = (int)$array[0];
		$model->setId($cid);
		if($cid==0) {
			$result = $model->addtag($name);
			if($result)
				echo $model->_tag->id."|".$model->_tag->name;
			//else echo "|";
		} else {
			$id = $model->get('id');
			$name = $model->get('name');
			echo $id."|".$name;
		}
	}

}