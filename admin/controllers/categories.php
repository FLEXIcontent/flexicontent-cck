<?php
/**
 * @version 1.5 stable $Id: categories.php 1614 2013-01-04 03:57:15Z ggppdk $
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

// Import parent controller
jimport('legacy.controller.admin');

/**
 * FLEXIcontent Component Categories Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerCategories extends JControllerAdmin
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		$this->text_prefix = 'com_content';
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'add',         'edit' );
		$this->registerTask( 'apply',        'save' );
		$this->registerTask( 'saveandnew',   'save' );
		$this->registerTask( 'params', 			'params' );
		$this->registerTask( 'orderdown', 	'orderdown' );
		$this->registerTask( 'orderup', 		'orderup' );
		$this->registerTask( 'saveorder', 	'saveorder' );
		$this->registerTask( 'publish', 		'publish' );
		$this->registerTask( 'unpublish', 	'unpublish' );
		$this->registerTask( 'archive', 		'archive' );
		$this->registerTask( 'trash', 			'trash' );
	}

	/**
	 * Logic to save a category
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		parent::save();
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$catscache = JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();
	}
	
	
	/**
	 * Check in a record
	 *
	 * @since	1.5
	 */
	function checkin()
	{
		$tbl = 'flexicontent_categories';
		$redirect_url = 'index.php?option=com_flexicontent&view=categories';
		flexicontent_db::checkin($tbl, $redirect_url, $this);
		return;// true;
	}
	
	
	/**
	 * Logic to publish, unpublish, archive, trash categories
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function publish()   { self::changestate( 1);  /*parent::publish();*/   }
	function unpublish() { self::changestate( 0);  /*parent::unpublish();*/ }
	function archive()   { self::changestate( 2);  /*parent::archive();*/   }
	function trash()     { self::changestate(-2);  /*parent::trash();*/     }
	
	
	/**
	 * Logic to unpublish categories
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function changestate($state=1)
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		$user = JFactory::getUser();
		$perms = FlexicontentHelperPerm::getPerm();
		$CanCats = $perms->CanCats;
		
		$cid = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$msg = '';
		
		if (!is_array( $cid ) || count( $cid ) < 1)
		{
			// no category selected
			JError::raiseWarning(500, JText::_( $state ? 'FLEXI_SELECT_ITEM_PUBLISH' : 'FLEXI_SELECT_ITEM_UNPUBLISH' ) );
		}
		else if (!$CanCats)
		{
			// no access rights
			JError::raiseWarning(500, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
		}
		else
		{
			// try to change state
			$model = $this->getModel('categories');
			if( !$model->publish($cid, $state) ) {
				JError::raiseWarning(500, $model->getError());
				$msg = JText::_('Failed');
			} else {		
				// set message
				$msg_arr = array(1=>'FLEXI_CATEGORY_PUBLISHED', 0=>'FLEXI_CATEGORY_UNPUBLISHED', 2=>'FLEXI_CATEGORIES_ARCHIVED', -2=>'FLEXI_CATEGORIES_TRASHED');
				$msg = isset($msg_arr[$state]) ? JText::_($msg_arr[$state]) : 'Category(-ies) state changed to: '.$state;
			}
			
			// clean cache
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache = JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
			//JFactory::getApplication()->enqueueMessage(JText::_( 'Cache cleaned' ), 'message');
		}

		// redirect to categories management tab
		$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', $msg );
	}
	
	
	/**
	 * Logic to orderup a category
	 *
	 * @access public
	 * @return void;
	 * @since 1.0
	 */
	function orderup()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$model = $this->getModel('categories');
		$model->move(-1);
		
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$catscache = JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=categories');
	}

	/**
	 * Logic to orderdown a category
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function orderdown()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$model = $this->getModel('categories');
		$model->move(1);
		
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$catscache = JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();

		$this->setRedirect( 'index.php?option=com_flexicontent&view=categories');
	}

	/**
	 * Logic to saver order of multiple categories at once
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function saveorder()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));
		
		// Get the arrays from the Request
		$order	= JRequest::getVar('order',	null, 'post', 'array');
		$originalOrder = explode(',', JRequest::getString('original_order_values'));

		// Make sure something has changed
		if (!($order === $originalOrder)) {
			parent::saveorder();
		} else {
			// Nothing to reorder
			$this->setRedirect(JRoute::_('index.php?option='.$this->option.'&view='.$this->view_list, false), 'Nothing to reorder');
		}
		
		// clean cache
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$catscache = JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();
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
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$user = JFactory::getUser();
		$perms = FlexicontentHelperPerm::getPerm();
		$CanCats = $perms->CanCats;
		
		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$msg = '';

		if (!is_array( $cid ) || count( $cid ) < 1)
		{
			// no category selected
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		}
		else if (!$CanCats)
		{
			// no access rights
			JError::raiseWarning(500, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
		}
		else
		{
			// try to delete the category and clean cache
			$model = $this->getModel('categories');
			$msg = $model->delete($cid);
			if (!$msg) {
				JError::raiseWarning(500, $model->getError());
				$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', $msg );
				return;
			}
			
			// clean cache
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache = JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
		}
		
		// redirect to categories management tab
		$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', $msg );
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
		
		$post = JRequest::get('post');
		$post = $post['jform'];
		JRequest::setVar('cid', $post['id']);
		$this->checkin();
	}
	
	
	/**
	 * Logic to set the category access level
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function access( )
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$user  = JFactory::getUser();
		$task  = JRequest::getVar( 'task' );
		$model = $this->getModel('categories');
		$cid   = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$id    = (int)$cid[0];
		
		// Get new category access
		$accesses	= JRequest::getVar( 'access', array(0), 'post', 'array' );
		$access = $accesses[$id];

		// Check authorization for access setting task
		$is_authorised = $user->authorise('core.edit', 'com_content.category.'.$id);
		if (!$is_authorised) {
			// no access rights
			JError::raiseWarning(500, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
		} else if(!$model->saveaccess( $id, $access )) {
			JError::raiseWarning(500, $model->getError());
		} else {
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache = JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
		}
		
		$this->setRedirect('index.php?option=com_flexicontent&view=categories' );
	}
	
	
	/**
	 * Override getModel function to return the customized categories
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function getModel($name = '', $prefix = '', $config = Array())
	{
		require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'categories.php');
		$model = new FlexicontentModelCategories();
		return $model;
	}
}
