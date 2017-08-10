<?php
/**
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

defined('_JEXEC') or die;

jimport('legacy.model.list');

/**
 * Methods supporting a list of user group records.
 *
 * @package		Joomla.Administrator
 * @subpackage	com_flexicontent
 * @since		1.6
 */
if (FLEXI_J40GE)
{
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'Model'.DS.'Groups.php');
	class FlexicontentModelGroups extends Joomla\Component\Users\Administrator\Model\Groups {}
}
else
{
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_users'.DS.'models'.DS.'groups.php');
	class FlexicontentModelGroups extends UsersModelGroups {}
}