<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            http://www.flexicontent.com
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

/**
 * FLEXIcontent Component User Groups Model
 *
 */
if (FLEXI_J40GE)
{
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'Model'.DS.'GroupsModel.php');

	class FlexicontentModelGroups extends Joomla\Component\Users\Administrator\Model\GroupsModel
	{
	}
}
else
{
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'models'.DS.'groups.php');

	class FlexicontentModelGroups extends UsersModelGroups
	{
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */


	/**
	 * START OF MODEL LEGACY METHODS
	 */

}