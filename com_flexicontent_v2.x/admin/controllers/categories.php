<?php
/**
 * @version 1.5 stable $Id: categories.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

jimport('joomla.application.component.controlleradmin');

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
		if (FLEXI_J16GE) {
			$this->text_prefix = 'com_content';
		}
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'add'  ,        'edit' );
		$this->registerTask( 'apply',        'save' );
		$this->registerTask( 'saveandnew',   'save' );
		if (!FLEXI_J16GE) {
			$this->registerTask( 'accesspublic',     'access' );
			$this->registerTask( 'accessregistered', 'access' );
			$this->registerTask( 'accessspecial',    'access' );
		}
		$this->registerTask( 'params', 			'params' );
		$this->registerTask( 'orderdown', 	'orderdown' );
		$this->registerTask( 'orderup', 		'orderup' );
		$this->registerTask( 'saveorder', 	'saveorder' );
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
			$cache 		=& JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
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
		if (FLEXI_J16GE)   parent::publish();
		else               self::changestate(1);
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
		if (FLEXI_J16GE)   parent::unpublish();
		else               self::changestate(0);
	}

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
		if (FLEXI_J16GE) {
			$perms = FlexicontentHelperPerm::getPerm();
			$CanCats = $perms->CanCats;
		} else if (FLEXI_ACCESS) {
			$CanCats = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid) : 1;
		} else {
			$CanCats = 1;
		}
		
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
				$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', $msg );
				return;
			}
			
			// set message
			$msg 	= $state ? JText::_( 'FLEXI_CATEGORY_PUBLISHED') : JText::_( 'FLEXI_CATEGORY_UNPUBLISHED' );
			
			// clean cache
			$cache 		=& JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
		}

		// redirect to categories management tab
		$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', $msg );
	}
	
	
	/**
	 * Check in a record
	 *
	 * @since	1.5
	 */
	function checkin()
	{
		$cid      = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$pk       = (int)$cid[0];
		$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', '' );
		
		// Only attempt to check the row in if it exists.
		if ($pk)
		{
			$user = JFactory::getUser();

			// Get an instance of the row to checkin.
			$table = & JTable::getInstance('flexicontent_categories', '');
			if (!$table->load($pk))
			{
				$this->setError($table->getError());
				return;// false;
			}

			// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (a) record checked out by current user
			if ($table->checked_out) {
				if (FLEXI_J16GE) {
					$canCheckin = $user->authorise('core.admin', 'checkin');
				} else if (FLEXI_ACCESS) {
					$canCheckin = ($user->gid < 25) ? FAccess::checkComponentAccess('com_checkin', 'manage', 'users', $user->gmid) : 1;
				} else {
					$canCheckin = $user->gid >= 24;
				}
				if ( !$canCheckin && $table->checked_out != $user->id) {
					$this->setError(JText::_( 'FLEXI_RECORD_CHECKED_OUT_DIFF_USER'));
					return;// false;
				}
			}

			// Attempt to check the row in.
			if (!$table->checkin($pk))
			{
				$this->setError($table->getError());
				return;// false;
			}
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', JText::sprintf('FLEXI_RECORD_CHECKED_IN_SUCCESSFULLY', 1) );
		return;// true;
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
		
		$cache 		=& JFactory::getCache('com_flexicontent');
		$cache->clean();
		$catscache 	=& JFactory::getCache('com_flexicontent_cats');
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
		
		$cache 		=& JFactory::getCache('com_flexicontent');
		$cache->clean();
		$catscache 	=& JFactory::getCache('com_flexicontent_cats');
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
		$cache 		=& JFactory::getCache('com_flexicontent');
		$cache->clean();
		$catscache 	=& JFactory::getCache('com_flexicontent_cats');
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
		if (FLEXI_J16GE) {
			$perms = FlexicontentHelperPerm::getPerm();
			$CanCats = $perms->CanCats;
		} else if (FLEXI_ACCESS) {
			$CanCats = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid) : 1;
		} else {
			$CanCats = 1;
		}
		
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
			$cache =& JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache =& JFactory::getCache('com_flexicontent_cats');
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
		
		// define the rights for correct redirecting the save task
		$user = JFactory::getUser();
		if (FLEXI_J16GE) {
			$perms = FlexicontentHelperPerm::getPerm();
			$CanCats = $perms->CanCats;
		} else if (FLEXI_ACCESS) {
			$CanCats	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid) : 1;
		} else {
			$CanCats 	= 1;
		}

		$category = & JTable::getInstance('flexicontent_categories','');
		$category->bind(JRequest::get('post'));
		$category->checkin();

		if ($CanCats) {
			$this->setRedirect( 'index.php?option=com_flexicontent&view=categories' );
		} else {
			$this->setRedirect( 'index.php?option=com_flexicontent' );
		}
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
		
		$user = JFactory::getUser();
		$model = $this->getModel('categories');
		$cid      = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$id       = (int)$cid[0];
		
		// Get new category access
		if (FLEXI_J16GE) {
			$accesses	= JRequest::getVar( 'access', array(0), 'post', 'array' );
			$access = $accesses[$id];
		}
		else
		{
			$task		= JRequest::getVar( 'task' );
			if ($task == 'accesspublic') {
				$access = 0;
			} elseif ($task == 'accessregistered') {
				$access = 1;
			} else {
				if (FLEXI_ACCESS) {
					$access = 3;
				} else {
					$access = 2;
				}
			}
		}

		// Check authorization for access setting task
		if (FLEXI_J16GE) {
			$is_authorised = $user->authorise('core.edit', 'com_content.category.'.$id);
		}else {
			$is_authorised = 1;
		}
		if (!$is_authorised) {
			// no access rights
			JError::raiseWarning(500, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
		} else if(!$model->saveaccess( $id, $access )) {
			JError::raiseWarning(500, $model->getError());
		} else {
			$cache 		=& JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
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
	function getModel()
	{
		require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'categories.php');
		$model = new FlexicontentModelCategories();
		return $model;
	}
}
