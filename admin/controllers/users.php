<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentControllerBaseAdmin', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php');

/**
 * FLEXIcontent Users Controller
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerUsers extends FlexicontentControllerBaseAdmin
{
	/**
	 * Constructor
	 *
	 * @param   array   $config    associative array of configuration settings.
	 *
	 * @since 3.3
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Register task aliases
		$this->registerTask('add',          'display');
		$this->registerTask('edit',         'display');
		$this->registerTask('apply',        'save');
		$this->registerTask('save2new',     'save');
		$this->registerTask('flogout',      'logout');
		$this->registerTask('unblock',      'block');

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanAuthors;
	}


	/**
	 * Displays a view
	 */
	public function display($cachable = false, $urlparams = false)
	{
		$task = $this->getTask();

		// Force URL variables for add / edit task
		if ($task === 'add' || $task === 'edit')
		{
			$this->input->set('hidemainmenu', 1);
			$this->input->set('layout', 'form');
			$this->input->set('view', 'user');
			$this->input->set('edit', $task === 'edit');
		}

		$view = $this->input->get('view', 'users', 'cmd');

		// Force 'form' layout if displaying singular view
		if ($view === 'user')
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
		$config = JFactory::getConfig();
		$MailFrom	= $config->get('mailfrom');
		$FromName	= $config->get('fromname');
		$SiteName	= $config->get('sitename');

		// Create a new JUser object for the given user id, and calculate / retrieve some information about the user
		$id   = $this->input->getInt('id', 0);
		$user = new JUser($id);
		$isNew = !$id;

		$curIsSuperAdmin = $me->authorise('core.admin', 'root.1');
		$isSuperAdmin = $user->authorise('core.admin', 'root.1');
		$saving_myself = $user->id == $me->id;

		$data = $this->input->get('jform', array(), 'array');

		// Merge template FIELDS-set this should include at least 'clayout' and optionally 'clayout_mobile' parameters
		if (!empty($data['templates']))
		{
			$data['authorcatparams'] = array_merge($data['authorcatparams'], $data['templates']);
		}

		// Merge the parameters of the selected clayout
		$clayout = $data['templates']['clayout'];

		if (!empty($data['layouts'][$clayout]))
		{
			$data['authorcatparams'] = array_merge($data['authorcatparams'], $data['layouts'][$clayout]);
		}

		// Bind posted data
		if (!$user->bind($data))
		{
			JError::raiseWarning(0, JText::_('CANNOT SAVE THE USER INFORMATION'));
			JError::raiseWarning(0, $user->getError());

			// $app->redirect('index.php?option=com_flexicontent&controller=users&view=users', $user->getError());
			// return false;
			return $this->execute('edit');
		}

		// Check if we allowed to block/unblock the user
		if (isset($data['block']))
		{
			$check_blocking = !$saving_myself || ($saving_myself && $data['block']);

			if ($user->id && $check_blocking)
			{
				$can_block_unblock = $this->block($check_uids = $user->id, $data['block'] ? 'block' : 'unblock');

				if (!$can_block_unblock)
				{
					return $this->execute('edit');
				}
			}
		}

		// Save the JUser object, creating the new user if it does not exist
		if (!$user->save())
		{
			JError::raiseWarning(0, JText::_('CANNOT SAVE THE USER INFORMATION'));
			JError::raiseWarning(0, $user->getError());

			return $this->execute('edit');
		}

		// *** BOF AUTHOR EXTENDED DATA ***
		JTable::addIncludePath(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'tables');
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
		if (false /*$isNew*/)
		{
			$adminEmail = $me->get('email');
			$adminName	= $me->get('name');

			$subject = JText::_('NEW_USER_MESSAGE_SUBJECT');
			$message = sprintf(JText::_('NEW_USER_MESSAGE'), $user->get('name'), $SiteName, JUri::root(), $user->get('username'), $user->password_clear);

			if ($MailFrom != '' && $FromName != '')
			{
				$adminName 	= $FromName;
				$adminEmail = $MailFrom;
			}

			JFactory::getMailer()->sendMail($adminEmail, $adminName, $user->get('email'), $subject, $message);
		}

		$ctrl = 'users.';

		switch ($this->getTask())
		{
			case 'apply':
				$msg = JText::sprintf('Successfully Saved changes to User', $user->get('name'));
				$this->setRedirect('index.php?option=com_flexicontent&controller=users&view=user&task=' . $ctrl . 'edit&id=' . $user->get('id'), $msg);
				break;

			case 'save2new':
				$msg = JText::sprintf('Successfully Saved User', $user->get('name'));
				$this->setRedirect('index.php?option=com_flexicontent&controller=users&view=user&task=' . $ctrl . 'add', $msg);
				break;

			case 'save':
			default:
				$msg = JText::sprintf('Successfully Saved User', $user->get('name'));
				$this->setRedirect('index.php?option=com_flexicontent&controller=users&view=users', $msg);
				break;
		}
	}


	/**
	 * Logic to delete records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function remove()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$db    = JFactory::getDbo();
		$me    = JFactory::getUser();
		$curIsSuperAdmin = $me->authorise('core.admin', 'root.1');

		$cid = $this->input->get('cid', array(), 'array');
		$cid = ArrayHelper::toInteger($cid);

		if (count($cid) < 1)
		{
			$msg = JText::_('Select a User to delete');
			throw new Exception($msg, 500);
		}

		$msg = '';
		$err_msg = '';

		foreach ($cid as $id)
		{
			// Check the action is allowed
			$user = JFactory::getUser($id);
			$isSuperAdmin = $user->authorise('core.admin', 'root.1');

			if ($id == $me->get('id'))
			{
				$err_msg .= JText::_('You cannot delete Yourself!') . "<br>";
			}
			elseif (!$curIsSuperAdmin && $isSuperAdmin)
			{
				$message = "You cannot delete %s, skipping user: %s";
				$userType = 'a Super Admnistrator';
				$err_msg .= JText::sprintf($message, $userType, $user->get('name')) . "<br>";
			}
			else
			{
				// Count number of active super admins
				$count = 2;

				if ($isSuperAdmin)
				{
					$query = 'SELECT COUNT( u.id ) FROM #__users AS u'
						. ' JOIN #__user_usergroup_map AS m ON u.id=m.user_id AND m.group_id=8 '
						. ' WHERE u.block = 0';
					$db->setQuery($query);
					$count = $db->loadResult();
				}

				if ($isSuperAdmin && $count <= 1)
				{
					// Cannot delete last active Super Admin
					$err_msg .= "You cannot delete last active Super Administrator: " . $user->get('name') . "<br>";
				}
				else
				{
					// Disconnect user acounts active sessions
					$app->logout($user->id);

					// Delete user
					$user->delete();
					$msg .= 'Deleted user: ' . $user->get('name') . "<br>";
				}
			}
		}

		if ($err_msg)
		{
			$app->enqueueMessage($err_msg, 'notice');
		}

		$this->setRedirect('index.php?option=com_flexicontent&controller=users&view=users', $msg);
	}



	/**
	 * Cancels an edit operation
	 */
	function cancel( )
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$this->setRedirect('index.php?option=com_flexicontent&view=users');
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

		if (!$check_uids)
		{
			$cid   = $this->input->get('cid', array(), 'array');
			$cid   = ArrayHelper::toInteger($cid);
			$block = $this->input->getCmd('task') === 'block';
		}
		else
		{
			$cid = is_array($check_uids) ? $check_uids : array($check_uids);
			$block = $check_task == 'block';
		}

		if (count($cid) < 1)
		{
			$msg = JText::_('Select a User to ' . $this->getTask());
			throw new Exception($msg, 500);
		}

		$msg = '';
		$err_msg = '';

		foreach ($cid as $id)
		{
			// Check the action is allowed
			$user = JFactory::getUser($id);
			$isSuperAdmin = $user->authorise('core.admin', 'root.1');

			if ($id == $me->get('id'))
			{
				$err_msg .= JText::_('You cannot block/unblock Yourself!');
			}
			elseif (!$curIsSuperAdmin && $isSuperAdmin)
			{
				$message = "You cannot block/unblock %s, skipping user: %s";
				$userType = 'a Super Admnistrator';
				$err_msg .= JText::sprintf($message, $userType, $user->get('name')) . "<br>";
			}
			else
			{
				// Count number of active super admins
				$count = 2;

				if ($isSuperAdmin)
				{
					$query = 'SELECT COUNT( u.id ) FROM #__users AS u'
						. ' JOIN #__user_usergroup_map AS m ON u.id=m.user_id AND m.group_id=8 '
						. ' WHERE u.block = 0';
					$db->setQuery($query);
					$count = $db->loadResult();
				}

				if ($block && $isSuperAdmin && $count <= 1)
				{
					// Cannot block last active Super Admin
					$err_msg .= "You cannot block last active Super Administrator: " . $user->get('name') . "<br>";
				}
				elseif (!$check_uids)  // Perform block/unblock, unless checking if it is allowed
				{
					// Disconnect user acounts active sessions
					if ($block)
					{
						$app->logout($user->id);
					}

					$user->block = $block;
					$user->save();
					$msg .= ($block ? 'Blocked user: ' : 'Unblocked user: ') . $user->get('name') . "<br>";
				}
			}
		}

		if ($err_msg)
		{
			$app->enqueueMessage($err_msg, 'notice');
		}

		if ($check_uids)
		{
			return (bool) ($err_msg == '');
		}

		$this->setRedirect('index.php?option=com_flexicontent&controller=users&view=users', $msg);
	}


	/**
	 * Force log out a user
	 */
	function logout( )
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$app    = JFactory::getApplication();
		$db     = JFactory::getDbo();

		$task   = $this->getTask();
		$cids   = $this->input->get('cid', array(), 'array');
		$cids   = ArrayHelper::toInteger($cids);
		$client = $this->input->getInt('client', 0);

		if (count($cids) < 1)
		{
			$this->setRedirect('index.php?option=com_flexicontent&controller=users&view=users', JText::_('User Deleted'));

			return false;
		}

		foreach ($cids as $cid)
		{
			$options = array();

			if ($task === 'logout' || $task === 'block')
			{
				$options['clientid'][] = 0; // Site
				$options['clientid'][] = 1; // administrator
			}
			elseif ($task === 'flogout')
			{
				$options['clientid'][] = $client;
			}

			$app->logout((int) $cid, $options);
		}

		$msg = JText::_('User Session Ended');

		switch ($task)
		{
			case 'flogout':
				$this->setRedirect('index.php', $msg);
				break;

			case 'remove':
			case 'block':
				return;
				break;

			default:
				$this->setRedirect('index.php?option=com_flexicontent&controller=users&view=users', $msg);
				break;
		}
	}


	function contact()
	{
		$contact_id = $this->input->getInt('contact_id', 0);

		$this->setRedirect('index.php?option=com_contact&task=edit&id=' . $contact_id);
	}
}
