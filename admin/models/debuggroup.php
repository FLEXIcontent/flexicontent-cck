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

require_once JPATH_COMPONENT.'/helpers/debug.php';

/**
 * FLEXIcontent Component Debug User Group Model
 *
 */
if (FLEXI_J40GE)
{
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'Model'.DS.'DebugGroupModel.php');

	class FlexicontentModelDebugGroup extends Joomla\Component\Users\Administrator\Model\DebugGroupModel
	{
		public function getDebugActions()
		{
			$app    = JFactory::getApplication();
			$component = $app->input->getCmd('option', '');

			return UsersHelperDebug::getDebugActions($component);
		}
	}
}
else
{
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'models'.DS.'debuggroup.php');

	class FlexicontentModelDebugGroup extends UsersModelDebugGroup
	{
		public function getDebugActions()
		{
			$app    = JFactory::getApplication();
			$component = $app->input->getCmd('option', '');

			return UsersHelperDebug::getDebugActions($component);
		}
	}
}
