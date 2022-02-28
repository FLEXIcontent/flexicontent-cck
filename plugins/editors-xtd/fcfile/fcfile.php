<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Editors-xtd.fcfile
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Editor Article buton
 *
 * @since  1.5
 */
class PlgButtonFcfile extends JPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  3.1
	 */
	protected $autoloadLanguage = true;

	/**
	 * Display the button
	 *
	 * @param   string  $name  The name of the button to add
	 *
	 * @return  JObject  The button options as JObject
	 *
	 * @since   1.5
	 */
	public function onDisplay($name)
	{
		/**
		 * Our elements view already filters records according to user's view access levels
		 */
		JFactory::getDocument()->addScriptOptions('xtd-fcfile', array('editor' => $name));
		$link = 'index.php?option=com_flexicontent&amp;view=fileselement&amp;layout=default&amp;isxtdbtn=1&amp;tmpl=component&amp;'
			. JSession::getFormToken() . '=1&amp;editor=' . $name;

		$button = new JObject;
		$button->modal   = true;
		$button->class   = 'btn';
		$button->link    = $link;
		$button->text    = JText::_('PLG_EDITORS-XTD_FCFILE_BUTTON_FCFILE');
		$button->name    = 'flexicontent';
		$button->options = "{handler: 'iframe', size: {x: 800, y: 500}}";

		return $button;
	}
}
