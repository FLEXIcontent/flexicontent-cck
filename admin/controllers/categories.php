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
		$this->registerTask( 'params', 			'params' );
		$this->registerTask( 'orderdown', 	'orderdown' );
		$this->registerTask( 'orderup', 		'orderup' );
		$this->registerTask( 'saveorder', 	'saveorder' );
		$this->registerTask( 'publish', 		'publish' );
		$this->registerTask( 'unpublish', 	'unpublish' );
		$this->registerTask( 'archive', 		'archive' );
		$this->registerTask( 'trash', 			'trash' );

		// Load Joomla 'com_categories' language files
		JFactory::getLanguage()->load('com_categories', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_categories', JPATH_ADMINISTRATOR, null, true);
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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		
		parent::save();

		// Clean cache
		$this->_cleanCache();
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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$user = JFactory::getUser();
		$perms = FlexicontentHelperPerm::getPerm();
		$CanCats = $perms->CanCats;
		
		$cid = $this->input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);
		$msg = '';
		
		if (!is_array( $cid ) || count( $cid ) < 1)
		{
			// no category selected
			$warnings_arr = array(
				1=>'FLEXI_SELECT_ITEM_PUBLISH',
				0=>'FLEXI_SELECT_ITEM_UNPUBLISH',
				2=>'FLEXI_SELECT_ITEM_ARCHIVE',
				-2=>'FLEXI_SELECT_ITEM_TRASH'
			);
			$app->setHeader('status', '500', true);
			$this->setRedirect('index.php?option=com_flexicontent&view=categories', JText::_( 'FLEXI_NO_ITEMS_SELECTED' ), 'warning');
			return;
		}

		if (!$CanCats)
		{
			// no access rights
			$app->setHeader('status', '403', true);
			$this->setRedirect('index.php?option=com_flexicontent&view=categories', JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ), 'error');
			return;
		}

		// Try to change state
		$model = $this->getModel('Categories');
		if (!$model->publish($cid, $state))
		{
			$app->setHeader('status', '500', true);
			$this->setRedirect('index.php?option=com_flexicontent&view=categories', JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');
			$success_msg = null;  // do not return let's clean cache since some records may have changed state
		}
		else
		{
			// Set success message
			$msg_arr = array(
				1=>'FLEXI_CATEGORY_PUBLISHED',
				0=>'FLEXI_CATEGORY_UNPUBLISHED',
				2=>'FLEXI_CATEGORIES_ARCHIVED',
				-2=>'FLEXI_CATEGORIES_TRASHED'
			);
			$success_msg = isset($msg_arr[$state])
				? JText::_($msg_arr[$state])
				: 'Category(-ies) state changed to: '.$state;
		}

		// Clean cache
		$this->_cleanCache();

		// redirect to categories management tab
		$this->setRedirect('index.php?option=com_flexicontent&view=categories', $success_msg);
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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		// Move record
		$model = $this->getModel('Categories');
		$model->move(-1);
		
		// Clean cache
		$this->_cleanCache();

		// Back to category listing
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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		
		// Move record
		$model = $this->getModel('Categories');
		$model->move(1);
		
		// Clean cache
		$this->_cleanCache();

		// Back to category listing
		$this->setRedirect( 'index.php?option=com_flexicontent&view=categories');
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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		
		$user = JFactory::getUser();
		$perms = FlexicontentHelperPerm::getPerm();
		$CanCats = $perms->CanCats;
		
		$cid = $this->input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);
		$msg = '';

		if (!is_array( $cid ) || count( $cid ) < 1)
		{
			// no category selected
			$app->setHeader('status', '500', true);
			$this->setRedirect('index.php?option=com_flexicontent&view=categories', JText::_( 'FLEXI_SELECT_ITEM_DELETE' ), 'warning');
			return;
		}

		if (!$CanCats)
		{
			// no access rights
			$app->setHeader('status', '403', true);
			$this->setRedirect('index.php?option=com_flexicontent&view=categories', JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ), 'error');
			return;
		}

		// Try to delete the category and clean cache
		$model = $this->getModel('Categories');
		$msg = $model->delete($cid);
		if (!$msg)
		{
			$app->setHeader('status', '500', true);
			$this->setRedirect('index.php?option=com_flexicontent&view=categories', $model->getError(), 'error');
			return;
		}

		// Clean cache
		$this->_cleanCache();

		// redirect to categories management tab
		$this->setRedirect('index.php?option=com_flexicontent&view=categories', $msg);
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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$data = $this->input->get('jform', array(), 'array');  // Unfiltered data (no need for filtering)
		$this->input->set('cid', (int) $data['id']);

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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		
		$user  = JFactory::getUser();
		$task  = JRequest::getVar( 'task' );
		$model = $this->getModel('Categories');

		$cid = $this->input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);
		$id = (int) $cid[0];
		
		if (!is_array( $cid ) || count( $cid ) < 1)
		{
			// no category selected
			$app->setHeader('status', '500', true);
			$this->setRedirect('index.php?option=com_flexicontent&view=categories', JText::_( 'FLEXI_NO_ITEMS_SELECTED' ), 'warning');
			return;
		}

		// Get new category access
		$accesses = $this->input->get('access', array(0), 'array');
		JArrayHelper::toInteger($accesses);
		$access = $accesses[$id];

		// Check authorization for access setting task
		$is_authorised = $user->authorise('core.edit', 'com_content.category.'.$id);
		if (!$is_authorised)
		{
			// no access rights
			$app->setHeader('status', '403', true);
			$app->enqueueMessage(JText::_('FLEXI_OPERATION_FAILED'), 'error');
		}
		else if(!$model->saveaccess( $id, $access ))
		{
			$app->setHeader('status', '500', true);
			$app->enqueueMessage($model->getError(), 'error');
		}
		else
		{
			// Clean cache
			$this->_cleanCache();
		}

		$this->setRedirect('index.php?option=com_flexicontent&view=categories' );
	}


	/**
	 * Proxy for getModel
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  The array of possible config values. Optional.
	 *
	 * @return  JModelLegacy  The model.
	 *
	 * @since   1.6
	 */
	public function getModel($name = 'Categories', $prefix = 'FlexicontentModel', $config = array('ignore_request' => true))
	{
		if ($this->input->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		$name = $name ?: 'Categories';
		require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS . strtolower($name) . '.php');

		return parent::getModel($name, $prefix, $config);
	}


	/**
	 * Rebuild the nested set tree.
	 *
	 * @return  bool  False on failure or error, true on success.
	 *
	 * @since  3.2
	 */
	public function rebuild()
	{
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$extension = 'com_content';
		$this->setRedirect(JRoute::_('index.php?option=com_flexicontent&view=categories', false));

		/** @var CategoriesModelCategory $model */
		$model = $this->getModel('Category');

		if ($model->rebuild())
		{
			// Rebuild succeeded.
			$this->setMessage(JText::_('COM_CATEGORIES_REBUILD_SUCCESS'));

			return true;
		}

		// Rebuild failed.
		$this->setMessage(JText::_('COM_CATEGORIES_REBUILD_FAILURE'));

		return false;
	}


	/**
	 * Method to clean cache related to categories
	 *
	 * @access private
	 * @return void
	 * @since 3.2.0
	 */
	private function _cleanCache()
	{
		if ($this->input->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		// Clean cache
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$catscache = JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();
	}
}