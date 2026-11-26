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
	$controller = strtolower($app->input->get('controller', '', 'cmd'));
	$view = strtolower($app->input->get('view', '', 'cmd'));
	$is_filemanager_request = $controller === 'filemanager' || $view === 'filemanager'
		|| (count($task_parts) > 1 && strtolower($task_parts[0]) === 'filemanager');

	// Public/Registered inherit upload and own-file actions from the installer.
	// Those alone must not expose a standalone file-management endpoint. Internal
	// field operations keep their item/file checks and do not enter this guard.
	if ($is_filemanager_request)
	{
		$user = $app->getIdentity();
		$can_manage_files = $user && !$user->guest
			&& $user->authorise('flexicontent.managefiles', 'com_flexicontent');
		if ($user && !$user->guest && !$can_manage_files)
		{
			foreach (array('core.create', 'core.edit', 'core.edit.own') as $action)
			{
				if ($user->authorise($action, 'com_flexicontent')
					|| $user->getAuthorisedCategories('com_content', $action))
				{
					$can_manage_files = true;
					break;
				}
			}
		}
		if (!$can_manage_files)
		{
			throw new \RuntimeException('File-management or content-author permission is required to manage files.', 403);
		}
	}


	if (in_array($task_method, array('addurl', 'addlocal'), true))
	{
		throw new \RuntimeException('Direct frontend access to this file-manager task is not allowed.', 403);
	}
}

// We will use require_once here, and not JLoader since this is meant to include backend file directly
require_once(JPATH_ADMINISTRATOR.DS."components".DS."com_flexicontent".DS."controllers".DS."filemanager.php");
