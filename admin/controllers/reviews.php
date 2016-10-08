<?php
/**
 * @version 1.5 stable $Id: reviews.php 1655 2013-03-16 17:55:25Z ggppdk $
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
 * FLEXIcontent Component Reviews Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerReviews extends FlexicontentController
{
	var $reviews_tbl = 'flexicontent_reviews_dev';

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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$task  = JRequest::getVar('task');
		$model = $this->getModel('review');

		// Get posted data
		$post = JRequest::get( 'post' );
		
		if ( $model->store($post) )
		{
			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_flexicontent&view=review&cid[]='.(int) $model->get('id');
					break;

				case 'saveandnew' :
					$link = 'index.php?option=com_flexicontent&view=review';
					break;

				default :
					$link = 'index.php?option=com_flexicontent&view=reviews';
					break;
			}
			$msg = JText::_( 'FLEXI_REVIEW_SAVED' );

			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$reviewcache = JFactory::getCache('com_flexicontent_reviews');
			$reviewcache->clean();

		} else {

			$msg = JText::_( 'FLEXI_ERROR_SAVING_REVIEW' );
			JError::raiseWarning( 500, $model->getError() );
			$link 	= 'index.php?option=com_flexicontent&view=review';
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
		$redirect_url = 'index.php?option=com_flexicontent&view=reviews';
		flexicontent_db::checkin('flexicontent_reviews', $redirect_url, $this);
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
		$model = $this->getModel('reviews');
		
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_REVIEW_PUBLISH' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=reviews', '');
			return;
		}
		$msg = '';
		if(!$model->publish($cid, 1)) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}
		
		$total = count( $cid );
		$msg 	= $total.' '.JText::_( 'FLEXI_REVIEW_PUBLISHED' );
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=reviews', $msg );
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
		$model = $this->getModel('reviews');
		
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_REVIEW_UNPUBLISH' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=reviews', '');
			return;
		}
		
		
		$msg = '';
		if (!$model->publish($cid, 0)) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}
		$total = count( $cid );
		$msg 	= $total.' '.JText::_( 'FLEXI_REVIEW_UNPUBLISHED' );
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=reviews', $msg );
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
		$model = $this->getModel('reviews');

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_REVIEW_DELETE' ) );
		} else {

			if (!$model->delete($cid)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
				if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
			}
			
			$msg = count($cid).' '.JText::_( 'FLEXI_REVIEWS_DELETED' );
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=reviews', $msg );
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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		
		$post = JRequest::get('post');
		//$post = FLEXI_J16GE ? $post['jform'] : $post;  //JForm currently not used for reviews
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
		JRequest::setVar( 'view', 'review' );
		JRequest::setVar( 'hidemainmenu', 1 );
		
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();
		
		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', 'default', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));
		
		// Get/Create the model
		$model = $this->getModel('review');
		
		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;
		
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$review_id = (int)$cid[0];
		
		
		// calculate access
		if (!$review_id) {
			$is_authorised = $user->authorise('flexicontent.createreviews', 'com_flexicontent');
		} else {
			//$asset = 'com_flexicontent.review.' . $review_id;
			//$is_authorised = $user->authorise('flexicontent.editreview', $asset);
			$is_authorised = $user->authorise('flexicontent.managereviews', 'com_flexicontent');
		}
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=reviews', '');
			return;
		}
		
		// Check if record is checked out by other editor
		if ( $model->isCheckedOut( $user->get('id') ) ) {
			JError::raiseNotice( 500, JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=reviews', '');
			return;
		}
		
		// Checkout the record and proceed to edit form
		if ( !$model->checkout() ) {
			JError::raiseWarning( 500, $model->getError() );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=reviews', '');
			return;
		}
		
		parent::display();
	}
	
}