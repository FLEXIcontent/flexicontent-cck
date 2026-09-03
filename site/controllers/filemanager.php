<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die;

// The backend controller is also used internally by FLEXIcontent field plugins while
// saving an item. Block only direct frontend dispatch of the unsafe HTTP tasks so
// those internal method calls continue to work.
$app = \Joomla\CMS\Factory::getApplication();

if ($app->isClient('site'))
{
	$task_parts = explode('.', $app->input->get('task', '', 'cmd'));
	$task_method = strtolower((string) end($task_parts));

	if (in_array($task_method, array('addurl', 'addlocal'), true))
	{
		throw new \RuntimeException('Direct frontend access to this file-manager task is not allowed.', 403);
	}
}

// We will use require_once here, and not JLoader since this is meant to include backend file directly
require_once(JPATH_ADMINISTRATOR.DS."components".DS."com_flexicontent".DS."controllers".DS."filemanager.php");
