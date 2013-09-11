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
 * FLEXIcontent Component Categories Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerCategories extends FlexicontentController
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
		$this->registerTask( 'add',         'edit' );
		$this->registerTask( 'apply',        'save' );
		$this->registerTask( 'saveandnew',   'save' );
		if (!FLEXI_J16GE) {
			$this->registerTask( 'accesspublic',     'access' );
			$this->registerTask( 'accessregistered', 'access' );
			$this->registerTask( 'accessspecial',    'access' );
		}
		$this->registerTask( 'params', 			'params' );
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
		
		$task		= JRequest::getVar('task');
		$user = JFactory::getUser();

		// define the rights for correct redirecting the save task
		if (FLEXI_J16GE) {
			$perms = FlexicontentHelperPerm::getPerm();
			$CanCats = $perms->CanCats;
		} else if (FLEXI_ACCESS) {
			$CanCats = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid) : 1;
		} else {
			$CanCats = 1;
		}

		//Sanitize
		$post = JRequest::get( 'post' );
		$post['description'] = JRequest::getVar( 'description', '', 'post', 'string', JREQUEST_ALLOWRAW );

		$model = $this->getModel('category');

		if ( $model->store($post) ) {
			
			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_flexicontent&view=category&cid[]='.(int) $model->get('id');
					break;

				case 'saveandnew' :
					$link = 'index.php?option=com_flexicontent&view=category';
					break;

				default :
					if ($CanCats) {
						$link = 'index.php?option=com_flexicontent&view=categories';
					} else {
						$link = 'index.php?option=com_flexicontent';
					}
					break;
			}
			$msg = JText::_( 'FLEXI_CATEGORY_SAVED' );
			
			//Take care of access levels and state
			$categoriesmodel = $this->getModel('categories');
			if (!FLEXI_ACCESS)
				$categoriesmodel->saveaccess($model->get('id'), $model->get('access'));
			
			$pubid = array();
			$pubid[] = $model->get('id');
			if($model->get('published') == 1) {
				$categoriesmodel->publish($pubid, 1);
			} else {
				$categoriesmodel->publish($pubid, 0);
			}
			
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache = JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();

		} else {
			
			$msg 	= JText::_( 'FLEXI_ERROR_SAVING_CATEGORY' );
			JError::raiseWarning( 500, $model->getError() );
			$link 	= 'index.php?option=com_flexicontent&view=category'.(@$_REQUEST['id']?'&cid[]='.(int)$_REQUEST['id']:'');
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
		$tbl = 'flexicontent_categories';
		$redirect_url = 'index.php?option=com_flexicontent&view=categories';
		flexicontent_db::checkin($tbl, $redirect_url, $this);
		return;// true;
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
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache = JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
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
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		// Get the arrays from the Request
		$order 	= JRequest::getVar( 'order', array(0), 'post', 'array' );
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		$model = $this->getModel('categories');
		if(!$model->saveorder($cid, $order)) {
			$msg = '';
			JError::raiseWarning(500, $model->getError());
		} else {
			$msg = 'NEW ORDERING SAVED';
			$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', $msg );
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
		$post = FLEXI_J16GE ? $post['jform'] : $post;
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
		if (FLEXI_J16GE) {
			$accesses	= JRequest::getVar( 'access', array(0), 'post', 'array' );
			$access = $accesses[$id];
		} else {
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
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache = JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
		}
		
		$this->setRedirect('index.php?option=com_flexicontent&view=categories' );
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
		// Check for request forgeries
		//JRequest::checkToken() or jexit( 'Invalid Token' );
		
		JRequest::setVar( 'view', 'category' );
		JRequest::setVar( 'hidemainmenu', 1 );

		$model = $this->getModel('category');
		$user  = JFactory::getUser();
		
		// Check if record is checked out by other editor
		if ( $model->isCheckedOut( $user->get('id') ) ) {
			JError::raiseNotice( 500, JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', '');
			return;
		}
		
		// Checkout the record and proceed to edit form
		if ( !$model->checkout() ) {
			JError::raiseWarning( 500, $model->getError() );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', '');
			return;
		}
		
		parent::display();
	}

	/**
	 * Logic to copy params from one category to others
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function params( )
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$copyid		= JRequest::getInt( 'copycid', '', 'post' );
		$destid		= JRequest::getVar( 'destcid', null, 'post', 'array' );
		$task		= JRequest::getVar( 'task' );

		$user = JFactory::getUser();
		$model 	= $this->getModel('category');		
		$params = $model->getParams($copyid);
		
		if (!$destid) {
			echo '<div class="copyfailed">'.JText::_( 'FLEXI_NO_TARGET' ).'</div>';
			print_r($destid);
			return;
		}
		if ($copyid)
		{
			$y = 0;
			$n = 0;
			$unauthorized = array();
			foreach ($destid as $id)
			{
				// Check unauthorized categories is for J1.6+ only (categories have ACL edit action)
				if ( FLEXI_J16GE && !$user->authorise('core.edit', 'com_content.category.'.$id) ) {
					$unauthorized[] = $id;
					continue;
				}
				if ($model->copyParams($id, $params)) {
					$y++;
				} else {
					$n++;				
				}
			}
			echo '<div class="copyok">'.JText::sprintf( 'FLEXI_CAT_PARAMS_COPIED', $y, $n ).'</div>';
			if ( FLEXI_J16GE && count($unauthorized) ) {
				echo '<div class="copyfailed">'.'Skipped '.count($unauthorized).' uneditable categories with ids: '.implode(', ',$unauthorized).'</div>';
			}
		} else {
			echo '<div class="copyfailed">'.JText::_( 'FLEXI_NO_SOURCE' ).'</div>';
		}
	}
}