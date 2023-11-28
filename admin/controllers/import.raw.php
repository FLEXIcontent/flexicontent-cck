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

// Manually import models in case used by frontend, then models will not be autoloaded correctly via getModel('name')
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'import.php';

/**
 * FLEXIcontent Import Controller (RAW)
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerImport extends FlexicontentControllerBaseAdmin
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
	}


	function getlineno()
	{
		$session = JFactory::getSession();
		$has_zlib = function_exists("zlib_encode"); // Version_compare(PHP_VERSION, '5.4.0', '>=');

		$conf   = $session->get('csvimport_config', "", 'flexicontent');
		$conf		= unserialize($conf ? ($has_zlib ? zlib_decode(base64_decode($conf)) : base64_decode($conf)) : "");
		$lineno = $session->get('csvimport_lineno', 999999, 'flexicontent');

		if (!empty($conf))
		{
			echo 'success|' . count($conf['contents_parsed']) . '|' . $lineno . '|' . JSession::getFormToken();
		}
		else
		{
			echo 'fail|0|0';
		}

		jexit();
	}
}
