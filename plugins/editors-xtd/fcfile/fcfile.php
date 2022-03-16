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
		$link = 'index.php?option=com_flexicontent&amp;view=fileselement&amp;layout=default&amp;isxtdbtn=1&amp;tmpl=component&amp;'
			. JSession::getFormToken() . '=1&amp;editor=' . $name;

		$button = new JObject;
		$button->modal   = true;
		$button->icon    = 'file-download';
		$button->iconSVG = '
		<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
			viewBox="0 0 325 325" style="enable-background:new 0 0 325 325;" xml:space="preserve">
			<g id="XMLID_786_">
				<g>
					<g>
						<path d="M260.232,83.385h-54.253V2.5h-86.957v80.885H64.769L162.5,214.211L260.232,83.385z M139.021,103.385V22.5h46.957v80.885
							h34.349L162.5,180.793l-57.827-77.408H139.021z"/>
						<path d="M260,182.5v70H65v-70H0v140h325v-140H260z M305,302.5H20v-100h25v70h235v-70h25V302.5z"/>
					</g>
				</g>
			</g>
		</svg>';
		$button->class   = 'btn';
		$button->link    = $link;
		$button->text    = JText::_('PLG_EDITORS-XTD_FCFILE_BUTTON_FCFILE');
		$button->name    = FLEXI_J40GE ? 'fcfile' : 'download';
		$button->options = "{handler: 'iframe', size: {x: 800, y: 500}}";

		return $button;
	}
}
