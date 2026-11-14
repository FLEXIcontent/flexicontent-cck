<?php
/**
 * @package         FLEXIcontent
 * @version         5.0
 *
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2026, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

/**
 * Displays the state of the phpThumb thumbnail script (library version, signing key, enforcement)
 * and a button to regenerate the signing key
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		5.0
 */
class JFormFieldPhpthumbstatus extends FormField
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$type = 'phpthumbstatus';


	protected function getLabel()
	{
		return '';
	}


	protected function getInput()
	{
		$user      = Factory::getApplication()->getIdentity();
		$state     = flexicontent_images::phpThumbManagedState();
		$config    = flexicontent_images::phpThumbConfig();
		$enforced  = flexicontent_images::phpThumbEnforced();
		$key_ok    = flexicontent_images::phpThumbKey() !== '';
		$requested = (bool) \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent')->get('phpthumb_high_security', 0);

		// Library version
		$version = '';
		$class_file = flexicontent_images::phpThumbDir() . '/phpthumb.class.php';

		if (is_file($class_file) && preg_match('/\$phpthumb_version\s*=\s*\'([^\']+)\'/', (string) file_get_contents($class_file, false, null, 0, 20000), $m))
		{
			$version = $m[1];
		}

		$rows = array();

		$rows[] = array(Text::_('FLEXI_PHPTHUMB_LIBRARY_VERSION'), $version ? htmlspecialchars($version, ENT_QUOTES, 'UTF-8') : '<span class="badge bg-danger">' . Text::_('FLEXI_NOT_FOUND') . '</span>');

		$rows[] = array(
			Text::_('FLEXI_PHPTHUMB_KEY_FILE'),
			(!$state['exists']
				? '<span class="badge bg-warning text-dark">' . Text::_('FLEXI_PHPTHUMB_KEY_MISSING') . '</span>'
				: ($key_ok
					? '<span class="badge bg-success">' . Text::_('FLEXI_PHPTHUMB_KEY_OK') . '</span>'
					: '<span class="badge bg-danger">' . Text::_('FLEXI_PHPTHUMB_KEY_WEAK') . '</span>'))
			. ' '
			. ($state['writable'] ? '' : '<span class="badge bg-danger">' . Text::_('FLEXI_PHPTHUMB_KEY_NOT_WRITABLE') . '</span>')
		);

		$enforced_text = $enforced
			? '<span class="badge bg-success">' . Text::_('FLEXI_PHPTHUMB_ENFORCED_YES') . '</span>'
			: '<span class="badge bg-secondary">' . Text::_('FLEXI_PHPTHUMB_ENFORCED_NO') . '</span>';

		if ($state['exists'] && $state['enabled'] !== $enforced)
		{
			$enforced_text .= ' <span class="badge bg-info text-dark">' . Text::_('FLEXI_PHPTHUMB_SET_BY_OVERRIDE') . '</span>';
		}
		elseif ($state['exists'] && $state['enabled'] !== $requested)
		{
			$enforced_text .= ' <span class="badge bg-warning text-dark">' . Text::_('FLEXI_PHPTHUMB_SAVE_TO_APPLY') . '</span>';
		}

		$rows[] = array(Text::_('FLEXI_PHPTHUMB_ENFORCED'), $enforced_text);

		$html = '<table class="table table-sm table-borderless mb-2" style="width:auto;">';

		foreach ($rows as $row)
		{
			$html .= '<tr><th scope="row" class="pe-3">' . $row[0] . '</th><td>' . $row[1] . '</td></tr>';
		}

		$html .= '</table>';

		if ($user && $user->authorise('core.admin', 'com_flexicontent'))
		{
			$link = Route::_('index.php?option=com_flexicontent&task=regeneratephpthumbkey&' . Session::getFormToken() . '=1', false);

			$confirmation = 'return confirm(' . json_encode(Text::_('FLEXI_PHPTHUMB_REGENERATE_KEY_CONFIRM'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');';
			$html .= '<p class="mb-2"><a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '"'
				. ' onclick="' . htmlspecialchars($confirmation, ENT_QUOTES, 'UTF-8') . '">'
				. '<span class="icon-refresh" aria-hidden="true"></span> ' . Text::_('FLEXI_PHPTHUMB_REGENERATE_KEY') . '</a></p>'
				. '<p class="small text-muted">' . Text::_('FLEXI_PHPTHUMB_REGENERATE_KEY_HELP') . '</p>';
		}

		$html .= '<details class="mb-3"><summary>' . Text::_('FLEXI_PHPTHUMB_KEY_LOCATION') . '</summary>'
			. '<code style="overflow-wrap:anywhere;">' . htmlspecialchars(str_replace(JPATH_SITE, '', $state['file']), ENT_QUOTES, 'UTF-8') . '</code></details>'
			. '<details class="mb-3"><summary>' . Text::_('FLEXI_PHPTHUMB_TEMPLATE_HELP_TITLE') . '</summary>'
			. '<div class="mt-2">' . Text::_('FLEXI_PHPTHUMB_TEMPLATE_HELP') . '</div></details>';

		return '<div class="fc-phpthumb-status" style="display:block;">' . $html . '</div>';
	}
}
