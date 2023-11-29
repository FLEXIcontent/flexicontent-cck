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

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentViewBaseRecord', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_record.php');

/**
 * HTML View class for the User Group screen
 */
class FlexicontentViewGroup extends FlexicontentViewBaseRecord
{
	protected $form;
	protected $item;
	protected $state;

	/**
	 * Display the view
	 */
	public function display($tpl = null)
	{
		/**
		 * Initialize variables, flags, etc
		 */

		$app        = \Joomla\CMS\Factory::getApplication();
		$jinput     = $app->input;
		$document   = \Joomla\CMS\Factory::getDocument();
		$user       = \Joomla\CMS\Factory::getUser();
		$db         = \Joomla\CMS\Factory::getDbo();
		$cparams    = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
		$perms      = FlexicontentHelperPerm::getPerm();

		// Get url vars and some constants
		$option     = $jinput->get('option', '', 'cmd');
		$view       = $jinput->get('view', '', 'cmd');
		$task       = $jinput->get('task', '', 'cmd');
		$controller = $jinput->get('controller', '', 'cmd');

		$isAdmin  = $app->isClient('administrator');
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		$tip_class = ' hasTooltip';
		$manager_view = 'groups';
		$ctrl = 'group';
		$js = '';


		/**
		 * Common view
		 */

		$this->prepare_common_fcview();


		$this->state	= $this->get('State');
		$this->item		= $this->get('Item');
		$this->form		= $this->get('Form');

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			$app->enqueueMessage(implode("<br>", $errors), 'error');
			$app->redirect( 'index.php?option=com_flexicontent&view=' . $manager_view );
		}


		/**
		 * Include needed files and add needed js / css files
		 */

		// Add css to document
		if ($isAdmin)
		{
			!\Joomla\CMS\Factory::getLanguage()->isRtl()
				? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
			!\Joomla\CMS\Factory::getLanguage()->isRtl()
				? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));
		}
		else
		{
			!\Joomla\CMS\Factory::getLanguage()->isRtl()
				? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', array('version' => FLEXI_VHASH));
		}
		
		$this->addToolbar();
		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @since	1.6
	 */
	protected function addToolbar()
	{
		\Joomla\CMS\Factory::getApplication()->input->set('hidemainmenu', 1);

		$user		= \Joomla\CMS\Factory::getUser();
		$isNew		= ($this->item->id == 0);
		$canDo		= UsersHelper::getActions();

		\Joomla\CMS\Toolbar\ToolbarHelper::title(\Joomla\CMS\Language\Text::_($isNew ? 'COM_USERS_VIEW_NEW_GROUP_TITLE' : 'COM_USERS_VIEW_EDIT_GROUP_TITLE'), 'users-cog');

		if ($canDo->get('core.edit') || $canDo->get('core.create'))
		{
			\Joomla\CMS\Toolbar\ToolbarHelper::apply('group.apply');
			\Joomla\CMS\Toolbar\ToolbarHelper::save('group.save');
		}

		if ($canDo->get('core.create'))
		{
			\Joomla\CMS\Toolbar\ToolbarHelper::save2new('group.save2new');
		}

		// If an existing item, can save to a copy.
		if (!$isNew && $canDo->get('core.create'))
		{
			\Joomla\CMS\Toolbar\ToolbarHelper::save2copy('group.save2copy');
		}

		empty($this->item->id)
			? \Joomla\CMS\Toolbar\ToolbarHelper::cancel('group.cancel')
			: \Joomla\CMS\Toolbar\ToolbarHelper::cancel('group.cancel', 'JTOOLBAR_CLOSE');

		\Joomla\CMS\Toolbar\ToolbarHelper::divider();
		\Joomla\CMS\Toolbar\ToolbarHelper::help('JHELP_USERS_GROUPS_EDIT');
	}
}
