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
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'search.php';

/**
 * FLEXIcontent Search Controller
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerSearch extends FlexicontentControllerBaseAdmin
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


	function purge()
	{
		$this->requireIndexMutation();

		$model = $this->getModel('search');
		$model->purge();
		$msg = \Joomla\CMS\Language\Text::_('FLEXI_ITEMS_PURGED');
		$this->setRedirect('index.php?option=com_flexicontent&view=search', $msg);
	}


	function custom_order()
	{
		$this->requireIndexMutation();
		flexicontent_db::setValues_commonDataTypes(null, true);
		$msg = \Joomla\CMS\Language\Text::_('FLEXI_CUSTOM_ORDER_INDEXES_UPDATED');
		$this->setRedirect('index.php?option=com_flexicontent&view=search', $msg);
	}

	/** Enforce the same authority as the Search screen before changing indexes. */
	private function requireIndexMutation()
	{
		$app = \Joomla\CMS\Factory::getApplication();
		$user = $app->getIdentity();
		if (!$app->isClient('administrator') || !$user->id
			|| !$user->authorise('flexicontent.managefields', 'com_flexicontent')
			|| !($user->authorise('flexicontent.createfield', 'com_flexicontent')
				|| $user->authorise('flexicontent.editfield', 'com_flexicontent')))
		{
			throw new \RuntimeException(\Joomla\CMS\Language\Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
		if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST')
		{
			throw new \RuntimeException('POST request required', 405);
		}
		if (!\Joomla\CMS\Session\Session::checkToken('post'))
		{
			throw new \RuntimeException(\Joomla\CMS\Language\Text::_('JINVALID_TOKEN'), 403);
		}
	}
}
