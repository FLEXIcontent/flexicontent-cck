<?php
/**
 * @version		$Id: users.php 1619 2013-01-09 02:50:25Z ggppdk $
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

jimport('joomla.application.component.controller');

/**
 * Users Component Controller
 *
 * @package		Joomla
 * @subpackage	Users
 * @since 1.5
 */
class FlexicontentControllerusers extends FlexicontentController
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
		$this->registerTask( 'add'  , 	'display'  );
		$this->registerTask( 'edit'  , 	'display'  );
		$this->registerTask( 'apply', 	'save'  );
		$this->registerTask( 'saveandnew', 	'save' );
		$this->registerTask( 'flogout', 'logout');
		$this->registerTask( 'unblock', 'block' );
	}

	/**
	 * Displays a view
	 */
	function display($cachable = false, $urlparams = false)
	{
		switch($this->getTask())
		{
			case 'add'     :
			{	JRequest::setVar( 'hidemainmenu', 1 );
				JRequest::setVar( 'layout', 'form'  );
				JRequest::setVar( 'view', 'user' );
				JRequest::setVar( 'edit', false );
			} break;
			case 'edit'    :
			{
				JRequest::setVar( 'hidemainmenu', 1 );
				JRequest::setVar( 'layout', 'form'  );
				JRequest::setVar( 'view', 'user' );
				JRequest::setVar( 'edit', true );
			} break;
		}

		if (JRequest::getVar('view','users')=='user') JRequest::setVar('layout', 'form');

		parent::display();
	}

	/**
	 * Saves the record
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$option = JRequest::getCmd( 'option');

		// Initialize some variables
		$mainframe= JFactory::getApplication();
		$db				= JFactory::getDBO();
		$me				= JFactory::getUser();
		$acl			= JFactory::getACL();
		$MailFrom	= $mainframe->getCfg('mailfrom');
		$FromName	= $mainframe->getCfg('fromname');
		$SiteName	= $mainframe->getCfg('sitename');

 		// Create a new JUser object for the given user id, and calculate / retrieve some information about the user
 		$id = JRequest::getVar( 'id', 0, 'post', 'int');
		$user = new JUser($id);
		$original_gid = $user->get('gid');
		if (FLEXI_J16GE) {
			$isSuperAdmin = isset($user->groups[8]);
		} else {
			$acl				= JFactory::getACL();
			$objectID 	= $acl->get_object_id( 'users', $id, 'ARO' );
			$groups			= $acl->get_object_groups( $objectID, 'ARO' );
			$this_group	= strtolower( $acl->get_group_name( $groups[0], 'ARO' ) );
			$isSuperAdmin = $me->get( 'gid' ) == 25;  //$this_group == 'super administrator';
			$isAdmin			= $me->get( 'gid' ) == 24;  //$this_group == 'administrator'
		}
		$saving_myself = $user->id==$me->id;

		$post = JRequest::get('post');
		$data = FLEXI_J16GE ? $post['jform'] : $post;
		if (FLEXI_J16GE) {
			if(isset($_REQUEST['jform']['attribs'])) {
				$data['params'] = array_merge($data['params'], $_REQUEST['jform']['attribs']);
			}
	
			if(isset($_REQUEST['jform']['templates'])) {
				$data['params'] = array_merge($data['params'], $_REQUEST['jform']['templates']);
			}
		} else if (!FLEXI_J16GE) {
			$data['username']	= JRequest::getVar('username', '', 'post', 'username');
			$data['password']	= JRequest::getVar('password', '', 'post', 'string', JREQUEST_ALLOWRAW);
			$data['password2']	= JRequest::getVar('password2', '', 'post', 'string', JREQUEST_ALLOWRAW);
		}
		
		// Bind posted data
		if (!$user->bind($data))
		{
			JError::raiseWarning(0, JText::_('CANNOT SAVE THE USER INFORMATION'));
			JError::raiseWarning(0, $user->getError());
			//$mainframe->redirect( 'index.php?option=com_flexicontent&controller=users&view=users', $user->getError() );
			//return false;
			return $this->execute('edit');
		}

		
		// Check if we allowed to block/unblock the user
		$check_blocking = !$saving_myself || ($saving_myself && $data['block']);
		if ( $user->id && $check_blocking) {
			$can_block_unblock = $this->block($check_uids=$user->id, $data['block'] ? 'block' : 'unblock' ) ;
			if ( !$can_block_unblock ) return $this->execute('edit');
		}
		
		// Are we dealing with a new user which we need to create?
		$isNew 	= ($user->get('id') < 1);
		if (!$isNew)
		{
			// if group has been changed and where original group was a Super Admin
			if ( $user->get('gid') != $original_gid && $original_gid == 25 )
			{
				// count number of active super admins
				$query = 'SELECT COUNT( id )'
					. ' FROM #__users'
					. ' WHERE gid = 25'
					. ' AND block = 0'
				;
				$db->setQuery( $query );
				$count = $db->loadResult();

				if ( $count <= 1 )
				{
					// disallow change if only one Super Admin exists
					$this->setRedirect( 'index.php?option=com_flexicontent&controller=users&view=users', JText::_('WARN_ONLY_SUPER') );
					return false;
				}
			}
		}

		/*
	 	 * Lets save the JUser object
	 	 */
		if (!$user->save())
		{

			JError::raiseWarning(0, JText::_('CANNOT SAVE THE USER INFORMATION'));
			JError::raiseWarning(0, $user->getError());
			return $this->execute('edit');
		}

		// *** BOF FLEXIACCESS INTEGRATION *** //		
		if (FLEXI_ACCESS)
		{
			// Delete old records
			$query	= 'DELETE FROM #__flexiaccess_members'
					. ' WHERE member_id = ' . (int)$user->get('id')
					;
			$db->setQuery( $query );
			$db->query();
			
			// Save new records
			foreach ($data['groups'] as $group)
			{			
				$query = 'INSERT INTO #__flexiaccess_members'
						. ' SET `group_id` = ' . (int)$group . ', `member_id` = ' . (int)$user->get('id')
						;
				$db->setQuery( $query );
				$db->query();
			}
		}
		// *** EOF FLEXIACCESS INTEGRATION *** //		

		
		// *** BOF AUTHOR EXTENDED DATA ***
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		$author_postdata['user_id']	= $user->get('id');
		$author_postdata['author_basicparams']	= $data['authorbasicparams'];
		$author_postdata['author_catparams']	= $data['authorcatparams'];
		//echo "<pre>"; print_r($data); exit;
		
		$flexiauthor_extdata = JTable::getInstance('flexicontent_authors_ext', '');
		
		// Bind data, Check data & Store the data to the database table
		if (!$flexiauthor_extdata->save($author_postdata))
		{
			JError::raiseWarning(0, JText::_('CANNOT SAVE THE AUTHOR EXTENDED INFORMATION'));
			JError::raiseWarning(0, $flexiauthor_extdata->getError());
			return $this->execute('edit');
		}
		// *** EOF AUTHOR EXTENDED DATA ***


		/*
	 	 * Time for the email magic so get ready to sprinkle the magic dust...
	 	 */
		if ($isNew)
		{
			$adminEmail = $me->get('email');
			$adminName	= $me->get('name');

			$subject = JText::_('NEW_USER_MESSAGE_SUBJECT');
			$message = sprintf ( JText::_('NEW_USER_MESSAGE'), $user->get('name'), $SiteName, JURI::root(), $user->get('username'), $user->password_clear );

			if ($MailFrom != '' && $FromName != '')
			{
				$adminName 	= $FromName;
				$adminEmail = $MailFrom;
			}
			JUtility::sendMail( $adminEmail, $adminName, $user->get('email'), $subject, $message );
		}

		// If updating self, load the new user object into the session
		// TODO: implement this for J2.5
		if ( !FLEXI_J16GE && $saving_myself)
		{
			// Get an ACL object
			$acl = JFactory::getACL();
			$mainframe = JFactory::getApplication();

			// Get the user group from the ACL
			$grp = $acl->getAroGroup($user->get('id'));

			// Mark the user as logged in
			$user->set('guest', 0);
			$user->set('aid', 1);

			// Fudge Authors, Editors, Publishers and Super Administrators into the special access group
			if ($acl->is_group_child_of($grp->name, 'Registered')      ||
			    $acl->is_group_child_of($grp->name, 'Public Backend'))    {
				$user->set('aid', 2);
			}

			// Set the usertype based on the ACL group name
			$user->set('usertype', $grp->name);
			
			// Add FLEXIaccess JUser parameters to the session
			// @TODO: find a more generic solution that would trigger the onLogin event
			if (FLEXI_ACCESS) 
			{
				$user->set('gmid', $me->get('gmid'));
				$user->set('level', $me->get('level'));
			}

			$session = JFactory::getSession();
			$session->set('user', $user);
		}
		
		
		$ctrl = FLEXI_J16GE ? 'users.' : '';
		switch ( $this->getTask() )
		{
			case 'apply':
				$msg = JText::sprintf( 'Successfully Saved changes to User', $user->get('name') );
				$this->setRedirect( 'index.php?option=com_flexicontent&controller=users&view=user&task='.$ctrl.'edit&cid[]='. $user->get('id'), $msg );
				break;

			case 'saveandnew':
			default:
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
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$app   = JFactory::getApplication();
		$db    = JFactory::getDBO();
		$me    = JFactory::getUser();
		$curIsSuperAdmin = FLEXI_J16GE ? isset($me->groups[8]) : $me->get( 'gid' ) == 25;
		
		$cid = JRequest::getVar( 'cid', array(), '', 'array' );
		JArrayHelper::toInteger( $cid );
		
		if (count( $cid ) < 1) {
			$msg = JText::_( 'Select a User to delete' );
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}
		
		$msg = '';
		$err_msg = '';
		foreach ($cid as $id)
		{
			// check the action is allowed
			$user = JFactory::getUser($id);
			if (FLEXI_J16GE) {
				$isSuperAdmin = isset($user->groups[8]);
			} else {
				$acl				= JFactory::getACL();
				$objectID 	= $acl->get_object_id( 'users', $id, 'ARO' );
				$groups			= $acl->get_object_groups( $objectID, 'ARO' );
				$this_group	= strtolower( $acl->get_group_name( $groups[0], 'ARO' ) );
				$isSuperAdmin = $me->get( 'gid' ) == 25; //$this_group == 'super administrator';
				$isAdmin      = $me->get( 'gid' ) == 24; //$this_group == 'administrator';
			}
			
			if ( $id == $me->get( 'id' ) )
			{
				$err_msg .= JText::_( 'You cannot delete Yourself!' ) ."<br>";
			}
			else if ( !$curIsSuperAdmin && ($isSuperAdmin || (!FLEXI_J16GE && $isAdmin)) )
			{
				$message = "You cannot delete %s, skipping user: %s";
				$userType = (!FLEXI_J16GE && $isAdmin) ? 'an Administrator' : 'a Super Admnistrator';
				$err_msg .= JText::sprintf( $message, $userType, $user->get('name') ) ."<br>";
			}
			else
			{
				// count number of active super admins
				$count = 2;
				if ( $isSuperAdmin ) {
					$query = 'SELECT COUNT( u.id ) FROM #__users AS u'
						. ( FLEXI_J16GE ?  ' JOIN #__user_usergroup_map AS m ON u.id=m.user_id AND m.group_id=8': '')
						. (!FLEXI_J16GE ? ' WHERE gid = 25' : '')
						. ' AND u.block = 0'
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
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$this->setRedirect( 'index.php?option=com_flexicontent&view=users' );
	}

	/**
	 * Disables the user account
	 */
	function block($check_uids=null, $check_task='block')
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$app = JFactory::getApplication();
		$db  = JFactory::getDBO();
		$me  = JFactory::getUser();
		$curIsSuperAdmin = FLEXI_J16GE ? isset($me->groups[8]) : $me->get( 'gid' ) == 25;
		
		if (!$check_uids) {
			$cid = JRequest::getVar( 'cid', array(), '', 'array' );
			JArrayHelper::toInteger( $cid );
			$block = JRequest::getVar('task') == 'block';
		} else {
			$cid = is_array($check_uids) ? $check_uids : array($check_uids);
			$block = $check_task == 'block';
		}
		
		if (count( $cid ) < 1) {
			$msg = JText::_( 'Select a User to '.$this->getTask() );
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}
		
		$msg = '';
		$err_msg = '';
		foreach ($cid as $id)
		{
			// check the action is allowed
			$user = JFactory::getUser($id);
			if (FLEXI_J16GE) {
				$isSuperAdmin = isset($user->groups[8]);
			} else {
				$acl				= JFactory::getACL();
				$objectID 	= $acl->get_object_id( 'users', $id, 'ARO' );
				$groups			= $acl->get_object_groups( $objectID, 'ARO' );
				$this_group	= strtolower( $acl->get_group_name( $groups[0], 'ARO' ) );
				$isSuperAdmin = $me->get( 'gid' ) == 25;  //$this_group == 'super administrator';
				$isAdmin			= $me->get( 'gid' ) == 24;  //$this_group == 'administrator'
			}
			
			if ( $id == $me->get( 'id' ) )
			{
				$err_msg .= JText::_( 'You cannot block/unblock Yourself!' );
			}
			else if ( !$curIsSuperAdmin && ($isSuperAdmin || (!FLEXI_J16GE && $isAdmin)) )
			{
				$message = "You cannot block/unblock %s, skipping user: %s";
				$userType = (!FLEXI_J16GE && $isAdmin) ? 'an Administrator' : 'a Super Admnistrator';
				$err_msg .= JText::sprintf( $message, $userType, $user->get('name') ) ."<br>";
			}
			else
			{
				// count number of active super admins
				$count = 2;
				if ( $isSuperAdmin ) {
					$query = 'SELECT COUNT( u.id ) FROM #__users AS u'
						. ( FLEXI_J16GE ?  ' JOIN #__user_usergroup_map AS m ON u.id=m.user_id AND m.group_id=8': '')
						. (!FLEXI_J16GE ? ' WHERE gid = 25' : '')
						. ' AND u.block = 0'
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
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$mainframe = JFactory::getApplication();
		$db			= JFactory::getDBO();
		$task 	= $this->getTask();
		$cids 	= JRequest::getVar( 'cid', array(), '', 'array' );
		$client = JRequest::getVar( 'client', 0, '', 'int' );
			
		JArrayHelper::toInteger($cids);

		if ( count( $cids ) < 1 ) {
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

			$mainframe->logout((int)$cid, $options);
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
