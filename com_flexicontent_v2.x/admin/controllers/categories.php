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
		$this->text_prefix = 'com_content';
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'add'  ,		 		'edit' );
		$this->registerTask( 'apply', 			'save' );
		$this->registerTask( 'saveandnew',	'save' );
		$this->registerTask( 'params', 			'params' );
	}

	/**
	 * Logic to save a category
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	/*function save() {
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$task		= JRequest::getVar('task');
		$permission = FlexicontentHelperPerm::getPerm();

		// define the rights for correct redirecting the save task
		$CanCats 	= $permission->CanCats;

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
			$categoriesmodel = & $this->getModel('categories');
			
			$pubid = array();
			$pubid[] = $model->get('id');
			if($model->get('published') == 1) {
				$categoriesmodel->publish($pubid, 1);
			} else {
				$categoriesmodel->publish($pubid, 0);
			}
			
			$cache 		=& JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();

		} else {
			$msg 	= JText::_( 'FLEXI_ERROR_SAVING_CATEGORY '. $model->getError() );
			$link 	= 'index.php?option=com_flexicontent&view=category'.(@$_REQUEST['id']?'&cid[]='.(int)$_REQUEST['id']:'');
		}
		$model->checkin();
		$this->setRedirect($link, $msg);
	}*/

	/**
	 * Logic to publish categories
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function publish()
	{
		//self::changestate(1);
		parent::publish();
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
		parent::unpublish();
		//self::changestate(0);
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
		$permission = FlexicontentHelperPerm::getPerm();
		$cid = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$msg = '';

		if (!is_array( $cid ) || count( $cid ) < 1)
		{
			// no category selected
			JError::raiseWarning(500, JText::_( $state ? 'FLEXI_SELECT_ITEM_PUBLISH' : 'FLEXI_SELECT_ITEM_UNPUBLISH' ) );
		}
		else if (!$permission->CanCats)
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
	 * Logic to mass ordering categories
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function saveorder()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$order 	= JRequest::getVar( 'order', array(0), 'post', 'array' );

		$model = $this->getModel('categories');
		if(!$model->saveorder($cid, $order)) {
			$msg = '';
			JError::raiseWarning(500, $model->getError());
		}
		
		// clean cache
		$cache 		=& JFactory::getCache('com_flexicontent');
		$cache->clean();
		$catscache 	=& JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();

		$msg = 'NEW ORDERING SAVED';
		$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', $msg );
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
		$permission = FlexicontentHelperPerm::getPerm();
		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$msg = '';

		if (!is_array( $cid ) || count( $cid ) < 1)
		{
			// no category selected
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		}
		else if (!$permission->CanCats)
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
	 * Logic to set the category access level
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function access( ) {
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$id		= (int)$cid[0];
		$accesses	= JRequest::getVar( 'access', array(0), 'post', 'array' );
		$access = $accesses[$id];

		$user = JFactory::getUser();
		$model = $this->getModel('categories');
		
		if (!$user->authorise('core.edit', 'com_content.category.'.$id)) {
			// no access rights
			JError::raiseWarning(500, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			// redirect to categories management tab
			$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', $msg );
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
	
	function getModel() {
		require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'categories.php');
		$model = new FlexicontentModelCategories();
		return $model;
	}
}
