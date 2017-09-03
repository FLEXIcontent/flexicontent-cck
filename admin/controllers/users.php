<?php
/**
 * @version		$Id: users.php 1847 2014-02-16 06:29:06Z ggppdk $
 * @package		Joomla
 * @subpackage	Users
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters. All rights reserved.
 * @license		GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');

/**
 * Users Component Controller
 *
 * @package		Joomla
 * @subpackage	Users
 * @since 1.5
 */
class FlexicontentControllerUsers extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @params	array	Controller configuration array
	 */
	function __construct($config = array())
	{
		parent::__construct($config);

		// Register Extra tasks
		$this->registerTask( 'add',          'display' );
		$this->registerTask( 'edit',         'display' );
		$this->registerTask( 'apply',        'save' );
		$this->registerTask( 'save2new',     'save' );
		$this->registerTask( 'flogout',      'logout' );
		$this->registerTask( 'unblock',      'block' );
	}


	/**
	 * Displays a view
	 */
	function display($cachable = false, $urlparams = false)
	{
		$task = $this->getTask();

		// Force URL variables for add / edit task
		if ($task == 'add' || $task == 'edit')
		{
			$this->input->set('hidemainmenu', 1);
			$this->input->set('layout', 'form');
			$this->input->set('view', 'user');
			$this->input->set('edit', $task=='edit');
		}

		$view = $this->input->get('view', 'users', 'cmd');

		// Force 'form' layout if displaying singular view
		if ($view == 'user')
		{
			$this->input->set('layout', 'form');
		}

		parent::display();
	}


	/**
	 * Saves the record
	 */
	function save()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		// Initialize some variables
		$app = JFactory::getApplication();
		$db  = JFactory::getDbo();
		$me  = JFactory::getUser();
		$acl = JFactory::getACL();
		$config = JFactory::getConfig();
		$MailFrom	= $config->get('mailfrom');
		$FromName	= $config->get('fromname');
		$SiteName	= $config->get('sitename');

 		// Create a new JUser object for the given user id, and calculate / retrieve some information about the user
 		$id = JRequest::getVar( 'id', 0, 'post', 'int');
		$user = new JUser($id);
		
		$curIsSuperAdmin = $me->authorise('core.admin', 'root.1');
		$isSuperAdmin = $user->authorise('core.admin', 'root.1');
		$saving_myself = $user->id==$me->id;

		$post = JRequest::get('post');
		$data = & $post['jform'];
		
		
		// Merge template FIELDS-set this should include at least 'clayout' and optionally 'clayout_mobile' parameters
		if( !empty($data['templates']) )
		{
			$data['authorcatparams'] = array_merge($data['authorcatparams'], $data['templates']);
		}
		
		
		// Merge the parameters of the selected clayout
		$clayout = $data['templates']['clayout'];
		if( !empty($data['layouts'][$clayout]) )
		{
			$data['authorcatparams'] = array_merge($data['authorcatparams'], $data['layouts'][$clayout]);
		}
		
		
		// Bind posted data
		if (!$user->bind($data))
		{
			JError::raiseWarning(0, JText::_('CANNOT SAVE THE USER INFORMATION'));
			JError::raiseWarning(0, $user->getError());
			//$app->redirect( 'index.php?option=com_flexicontent&controller=users&view=users', $user->getError() );
			//return false;
			return $this->execute('edit');
		}
		
		
		// Check if we allowed to block/unblock the user
		$check_blocking = !$saving_myself || ($saving_myself && $data['block']);
		if ( $user->id && $check_blocking)
		{
			$can_block_unblock = $this->block($check_uids=$user->id, $data['block'] ? 'block' : 'unblock' ) ;
			if ( !$can_block_unblock ) return $this->execute('edit');
		}
		
		
		// Save the JUser object, creating the new user if it does not exist
		if (!$user->save())
		{
			JError::raiseWarning(0, JText::_('CANNOT SAVE THE USER INFORMATION'));
			JError::raiseWarning(0, $user->getError());
			return $this->execute('edit');
		}
		
		
		// *** BOF AUTHOR EXTENDED DATA ***
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		$author_postdata['user_id']	= $user->get('id');
		$author_postdata['author_basicparams']	= $data['authorbasicparams'];
		$author_postdata['author_catparams']	= $data['authorcatparams'];
		
		$flexiauthor_extdata = JTable::getInstance('flexicontent_authors_ext', '');
		
		// Bind data, Check data & Store the data to the database table
		if (!$flexiauthor_extdata->save($author_postdata))
		{
			JError::raiseWarning(0, JText::_('CANNOT SAVE THE AUTHOR EXTENDED INFORMATION'));
			JError::raiseWarning(0, $flexiauthor_extdata->getError());
			return $this->execute('edit');
		}
		// *** EOF AUTHOR EXTENDED DATA ***
		
		
		// Send email for new user
		if ($isNew)
		{
			$adminEmail = $me->get('email');
			$adminName	= $me->get('name');

			$subject = JText::_('NEW_USER_MESSAGE_SUBJECT');
			$message = sprintf ( JText::_('NEW_USER_MESSAGE'), $user->get('name'), $SiteName, JUri::root(), $user->get('username'), $user->password_clear );

			if ($MailFrom != '' && $FromName != '')
			{
				$adminName 	= $FromName;
				$adminEmail = $MailFrom;
			}
			JFactory::getMailer()->sendMail( $adminEmail, $adminName, $user->get('email'), $subject, $message );
		}
		
		
		$ctrl = 'users.';
		switch ($this->getTask())
		{
			case 'apply':
				$msg = JText::sprintf( 'Successfully Saved changes to User', $user->get('name') );
				$this->setRedirect( 'index.php?option=com_flexicontent&controller=users&view=user&task='.$ctrl.'edit&cid[]='. $user->get('id'), $msg );
				break;

			case 'save2new':
				$msg = JText::sprintf( 'Successfully Saved User', $user->get('name') );
				$this->setRedirect( 'index.php?option=com_flexicontent&controller=users&view=user&task='.$ctrl.'add', $msg );
				break;
				
			case 'save':
			default:
				$msg = JText::sprintf( 'Successfully Saved User', $user->get('name') );
				$this->setRedirect( 'index.php?option=com_flexicontent&controller=users&view=users', $msg );
				break;
		}
	}


	/**
	 * Removes the record(s) from the database
	 */
	function remove()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$db    = JFactory::getDbo();
		$me    = JFactory::getUser();
		$curIsSuperAdmin = $me->authorise('core.admin', 'root.1');
		
		$cid = JRequest::getVar( 'cid', array(), '', 'array' );
		JArrayHelper::toInteger( $cid );
		
		if (count( $cid ) < 1)
		{
			$msg = JText::_( 'Select a User to delete' );
			throw new Exception($msg, 500);
		}
		
		$msg = '';
		$err_msg = '';
		foreach ($cid as $id)
		{
			// check the action is allowed
			$user = JFactory::getUser($id);
			$isSuperAdmin = $user->authorise('core.admin', 'root.1');
			
			if ( $id == $me->get( 'id' ) )
			{
				$err_msg .= JText::_( 'You cannot delete Yourself!' ) ."<br>";
			}
			else if ( !$curIsSuperAdmin && $isSuperAdmin )
			{
				$message = "You cannot delete %s, skipping user: %s";
				$userType = 'a Super Admnistrator';
				$err_msg .= JText::sprintf( $message, $userType, $user->get('name') ) ."<br>";
			}
			else
			{
				// count number of active super admins
				$count = 2;
				if ( $isSuperAdmin )
				{
					$query = 'SELECT COUNT( u.id ) FROM #__users AS u'
						.' JOIN #__user_usergroup_map AS m ON u.id=m.user_id AND m.group_id=8 '
						.' WHERE u.block = 0'
					;
					$db->setQuery( $query );
					$count = $db->loadResult();
					if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
				}

				if ( $isSuperAdmin && $count <= 1 )
				{
					// cannot delete last active Super Admin
					$err_msg .= "You cannot delete last active Super Administrator: ".$user->get( 'name' )."<br>";
				}
				else
				{
					// disconnect user acounts active sessions
					$app->logout($user->id);
					
					// delete user
					$user->delete();
					$msg .= 'Deleted user: ' .$user->get( 'name' ) ."<br>";
				}
			}
		}

		if ($err_msg) $app->enqueueMessage($err_msg, 'notice');
		$this->setRedirect( 'index.php?option=com_flexicontent&controller=users&view=users', $msg);
	}



	/**
	 * Cancels an edit operation
	 */
	function cancel( )
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$this->setRedirect( 'index.php?option=com_flexicontent&view=users' );
	}



	/**
	 * Disables the user account
	 */
	function block($check_uids=null, $check_task='block')
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$app = JFactory::getApplication();
		$db  = JFactory::getDbo();
		$me  = JFactory::getUser();
		$curIsSuperAdmin = $me->authorise('core.admin', 'root.1');
		
		if (!$check_uids) {
			$cid = JRequest::getVar( 'cid', array(), '', 'array' );
			JArrayHelper::toInteger( $cid );
			$block = JRequest::getVar('task') == 'block';
		} else {
			$cid = is_array($check_uids) ? $check_uids : array($check_uids);
			$block = $check_task == 'block';
		}
		
		if (count( $cid ) < 1)
		{
			$msg = JText::_( 'Select a User to '.$this->getTask() );
			throw new Exception($msg, 500);
		}
		
		$msg = '';
		$err_msg = '';
		foreach ($cid as $id)
		{
			// check the action is allowed
			$user = JFactory::getUser($id);
			$isSuperAdmin = $user->authorise('core.admin', 'root.1');
			
			if ( $id == $me->get( 'id' ) )
			{
				$err_msg .= JText::_( 'You cannot block/unblock Yourself!' );
			}
			else if ( !$curIsSuperAdmin && $isSuperAdmin )
			{
				$message = "You cannot block/unblock %s, skipping user: %s";
				$userType = 'a Super Admnistrator';
				$err_msg .= JText::sprintf( $message, $userType, $user->get('name') ) ."<br>";
			}
			else
			{
				// count number of active super admins
				$count = 2;
				if ( $isSuperAdmin )
				{
					$query = 'SELECT COUNT( u.id ) FROM #__users AS u'
						.' JOIN #__user_usergroup_map AS m ON u.id=m.user_id AND m.group_id=8 '
						.' WHERE u.block = 0'
					;
					$db->setQuery( $query );
					$count = $db->loadResult();
					if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
				}

				if ( $block && $isSuperAdmin && $count <= 1 )
				{
					// cannot block last active Super Admin
					$err_msg .= "You cannot block last active Super Administrator: ".$user->get( 'name' )."<br>";
				}
				else if ( !$check_uids )  // Perform block/unblock, unless checking if it is allowed
				{
					// disconnect user acounts active sessions
					if ($block) $app->logout($user->id);
					
					$user->block = $block;
					$user->save();
					$msg .= ($block ? 'Blocked user: ' : 'Unblocked user: ') .$user->get( 'name' ) ."<br>";
				}
			}
		}
		
		if ($err_msg) $app->enqueueMessage($err_msg, 'notice');
		if ($check_uids) return (bool) ($err_msg=='');
		
		$this->setRedirect( 'index.php?option=com_flexicontent&controller=users&view=users', $msg);
	}


	/**
	 * Force log out a user
	 */
	function logout( )
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$app = JFactory::getApplication();
		$db  = JFactory::getDbo();
		$task   = $this->getTask();
		$cids   = JRequest::getVar( 'cid', array(), '', 'array' );
		$client = JRequest::getVar( 'client', 0, '', 'int' );
			
		JArrayHelper::toInteger($cids);

		if ( count( $cids ) < 1 )
		{
			$this->setRedirect( 'index.php?option=com_flexicontent&controller=users&view=users', JText::_( 'User Deleted' ) );
			return false;
		}

		foreach($cids as $cid)
		{
			$options = array();

			if ($task == 'logout' || $task == 'block') {
				$options['clientid'][] = 0; //site
				$options['clientid'][] = 1; //administrator
			} else if ($task == 'flogout') {
				$options['clientid'][] = $client;
			}

			$app->logout((int)$cid, $options);
		}

		$msg = JText::_( 'User Session Ended' );
		switch ( $task )
		{
			case 'flogout':
				$this->setRedirect( 'index.php', $msg );
				break;

			case 'remove':
			case 'block':
				return;
				break;

			default:
				$this->setRedirect( 'index.php?option=com_flexicontent&controller=users&view=users', $msg );
				break;
		}
	}



	function contact()
	{
		$contact_id = JRequest::getVar( 'contact_id', '', 'post', 'int' );
		$this->setRedirect( 'index.php?option=com_contact&task=edit&cid[]='. $contact_id );
	}

}
