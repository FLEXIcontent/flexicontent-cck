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
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'add'  ,		 	'edit' );
		$this->registerTask( 'apply', 			'save' );
		$this->registerTask( 'saveandnew', 		'save' );
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
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_PUBLISH' ) );
		} else {

			$model = $this->getModel('categories');

			if(!$model->publish($cid, 1)) {
				JError::raiseError(500, $model->getError());
			}

			$msg 	= JText::_( 'FLEXI_CATEGORY_PUBLISHED' );
		
			$cache 		=& JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', $msg );
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
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_UNPUBLISH' ) );
		} else {

			$model = $this->getModel('categories');

			if(!$model->publish($cid, 0)) {
				JError::raiseError(500, $model->getError());
			}

			$msg 	= JText::_( 'FLEXI_CATEGORY_UNPUBLISHED' );
		
			$cache 		=& JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
		}
		
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

		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		} else {

			$model = $this->getModel('categories');

			$msg = $model->delete($cid);

			$cache 		=& JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
		}
		
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
		$user = &JFactory::getUser();
		
		// define the rights for correct redirecting the save task
		$permission = FlexicontentHelperPerm::getPerm();
		$user =& JFactory::getUser();
		$CanCats	= (!$permission->CanConfig) ? $permission->CanCats : 0;
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
	function access( ) {
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$id		= (int)$cid[0];
		$accesses	= JRequest::getVar( 'access', array(0), 'post', 'array' );
		$access = $accesses[$id];

		$model = $this->getModel('categories');
		
		if(!$model->saveaccess( $id, $access )) {
			JError::raiseError(500, $model->getError());
		} else {
			$cache 		=& JFactory::getCache('com_flexicontent');
			$cache->clean();
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
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
	/*function edit( )
	{
		// Check for request forgeries
		//JRequest::checkToken() or jexit( 'Invalid Token' );
		
		JRequest::setVar( 'view', 'category' );
		JRequest::setVar( 'hidemainmenu', 1 );

		$model 	= $this->getModel('category');
		$user	=& JFactory::getUser();

		// Error if checkedout by another administrator
		if ($model->isCheckedOut( $user->get('id') )) {
			$this->setRedirect( 'index.php?option=com_flexicontent&view=categories', JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ) );
		}

		$model->checkout( $user->get('id') );
		parent::display();
	}*/

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
			foreach ($destid as $id)
			{
				if ($model->copyParams($id, $params)) {
					$y++;
				} else {
					$n++;				
				}
			}
			echo '<div class="copyok">'.JText::sprintf( 'FLEXI_CAT_PARAMS_COPIED', $y, $n ).'</div>';
		} else {
			echo '<div class="copyfailed">'.JText::_( 'FLEXI_NO_SOURCE' ).'</div>';
		}
	}
}
