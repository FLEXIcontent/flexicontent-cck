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
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'category.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'categories.php';

/**
 * FLEXIcontent Categories Controller (RAW)
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerCategories extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl = 'categories';
	var $records_jtable = 'flexicontent_categories';

	var $record_name = 'category';
	var $record_name_pl = 'categories';

	var $_NAME = 'CATEGORY';
	var $record_alias = 'alias';

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = array();
	var $exitLogTexts = array();
	var $exitSuccess  = true;

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

		// The prefix to use with controller messages.
		$this->text_prefix = 'COM_CONTENT';

		// Register task aliases
		$this->registerTask('params',     'params');
	}


	/**
	 * Proxy for getModel
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  The array of possible config values. Optional.
	 *
	 * @return  JModelLegacy  The model.
	 *
	 * @since   1.6
	 */
	public function getModel($name = 'Categories', $prefix = 'FlexicontentModel', $config = array('ignore_request' => true))
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		$name = $name ?: 'Categories';
		require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . strtolower($name) . '.php';

		return parent::getModel($name, $prefix, $config);
	}


	/**
	 * Logic to copy params from one category to others
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function params( )
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$task   = $this->input->getCmd('task');
		$copyid	= $this->input->getInt('copycid', 0);
		$destid	= $this->input->get('destcid', array(), 'array');

		$destid = ArrayHelper::toInteger($destid);

		$user   = JFactory::getUser();
		$model  = $this->getModel($this->record_name);
		$params = $model->getParams($copyid);

		if (!$destid)
		{
			echo '<div class="copyfailed">' . JText::_('FLEXI_NO_TARGET') . '</div>';

			return;
		}

		if (!$copyid)
		{
			echo '<div class="copyfailed">' . JText::_('FLEXI_NO_SOURCE') . '</div>';

			return;
		}

		// Check for unauthorized categories
		$y = 0;
		$n = 0;
		$unauthorized = array();

		foreach ($destid as $id)
		{
			if (!$user->authorise('core.edit', 'com_content.category.' . $id))
			{
				$unauthorized[] = $id;
				continue;
			}

			$model->copyParams($id, $params)
				? $y++
				: $n++;
		}

		echo '<div class="copyok">' . JText::sprintf('FLEXI_CAT_PARAMS_COPIED', $y, $n) . '</div>';

		if (count($unauthorized))
		{
			echo '<div class="copyfailed">' . 'Skipped ' . count($unauthorized) . ' uneditable categories with ids: ' . implode(', ', $unauthorized) . '</div>';
		}
	}
}
