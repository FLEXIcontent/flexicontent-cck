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

// no direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_BASE . '/components/com_flexicontent/helpers/debug.php';

/**
 * FLEXIcontent Component Debug User Model
 *
 */
if (FLEXI_J40GE)
{
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'src'.DS.'Model'.DS.'DebugUserModel.php');

	class FlexicontentModelDebugUser extends Joomla\Component\Users\Administrator\Model\DebugUserModel
	{
	}
}
else
{
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'models'.DS.'debuguser.php');

	class FlexicontentModelDebugUser extends UsersModelDebugUser
	{
	}
}