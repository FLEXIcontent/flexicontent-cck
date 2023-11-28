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

jimport('legacy.view.legacy');

/**
 * HTML View class for backend record screens (Base)
 */
class FlexicontentViewBaseRecord extends JViewLegacy
{
	var $tooltip_class = FLEXI_J40GE ? 'hasTooltip' : 'hasTooltip';
	var $popover_class = FLEXI_J40GE ? 'hasPopover' : 'hasPopover';
	var $btn_sm_class  = FLEXI_J40GE ? 'btn btn-sm' : 'btn btn-small';
	var $btn_iv_class  = FLEXI_J40GE ? 'btn-dark' : 'btn-inverse';
	var $ina_grp_class = FLEXI_J40GE ? 'input-group' : 'input-append';
	var $inp_grp_class = FLEXI_J40GE ? 'input-group' : 'input-prepend';
	var $select_class  = FLEXI_J40GE ? 'use_select2_lib' : 'use_select2_lib';
	//var $txt_grp_class = FLEXI_J40GE ? 'input-group-text' : 'add-on';


	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);
	}


	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	public function prepare_common_fcview($config = array())
	{
		$isAdmin = JFactory::getApplication()->isClient('administrator');

		/**
		 * Load Joomla language files of other extension
		 */
		if (!empty($this->proxy_option))
		{
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, null, true);
		}

		/**
		 * Note : we use some strings from administrator part, so we will also load administrator language file
		 * TODO: remove this need by moving common language string to different file ?
		 */
		if (!$isAdmin)
		{
			// Load english language file for 'com_content' component then override with current language file
			JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, null, true);

			// Load english language file for 'com_flexicontent' component then override with current language file
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);

			// Frontend form layout is named 'form' instead of 'default', 'default' in frontend is typically used for viewing would be used for
			$this->setLayout('form');
		}

		/**
		 * In older Joomla versions include Toolbar Helper in frontend
		 */
		if (JFactory::getApplication()->isClient('site'))
		{
			$jversion = new JVersion;

			if (version_compare($jversion->getShortVersion(), '3.9.0', 'lt'))
			{
				require_once JPATH_ADMINISTRATOR . '/includes/toolbar.php';
			}
		}
	}


	/**
	 * Method to get the CSS for backend record screens (typically edit forms)
	 *
	 * @return	int
	 *
	 * @since	3.3.0
	 */
	public function addCssJs()
	{
	}


	/**
	 * Method to get the display of field while showing the inherited value
	 *
	 * @return	int
	 *
	 * @since	3.3.0
	 */
	public function getFieldInheritedDisplay($field, $params)
	{
		return flexicontent_html::getInheritedFieldDisplay($field, $params);
	}
}
