<?php
/**
 * @copyright	Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');
require_once JPATH_COMPONENT.'/helpers/debug.php';

/**
 * Methods supporting a list of user records.
 *
 * @package		Joomla.Administrator
 * @subpackage	com_users
 * @since		1.6
 */
require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'models'.DS.'debuguser.php');

class FlexicontentModelDebugUser extends UsersModelDebugUser
{
}
