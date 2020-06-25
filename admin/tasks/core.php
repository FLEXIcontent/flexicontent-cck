<?php
use Joomla\String\StringHelper;

if (!defined('JPATH_BASE'))
{
	define('_JEXEC', 1);
	define('DS', DIRECTORY_SEPARATOR);

	if (file_exists('defines.php'))
	{
		require_once 'defines.php';
	}
	elseif (file_exists(realpath(__DIR__) . '/' . 'defines.php'))
	{
		require_once realpath(__DIR__) . '/' . 'defines.php';
	}
	else
	{
		define('JPATH_BASE', realpath(__DIR__.'/../../..'));
	}
}

require_once JPATH_BASE . '/../components/com_flexicontent/tasks/core.php';
