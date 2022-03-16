<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Editors-xtd.fcitem
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
class PlgButtonFcitem extends JPlugin
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

		$link = 'index.php?option=com_flexicontent&amp;view=itemelement&amp;layout=default&amp;isxtdbtn=1&amp;tmpl=component&amp;'
			. JSession::getFormToken() . '=1&amp;editor=' . $name;

		$button = new JObject;
		$button->modal   = true;
		$button->icon    = 'add-fcitem';
		/*$button->iconSVG = '
			<svg width="24px" height="24px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
					<g>
							<path fill="none" d="M0 0h24v24H0z"/>
							<path d="M20 22H4a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1zM7 6v4h4V6H7zm0 6v2h10v-2H7zm0 4v2h10v-2H7zm6-9v2h4V7h-4z"/>
					</g>
			</svg>';*/
		$button->iconSVG = '
			<svg version="1.1" id="Icons" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
				 viewBox="0 0 32 32" style="enable-background:new 0 0 32 32;" xml:space="preserve">
				<g>
					<path d="M22,4h7c0.6,0,1-0.4,1-1s-0.4-1-1-1h-7c-0.6,0-1,0.4-1,1S21.4,4,22,4z"/>
					<path d="M29,8h-7c-0.6,0-1,0.4-1,1s0.4,1,1,1h7c0.6,0,1-0.4,1-1S29.6,8,29,8z"/>
					<path d="M29,14h-7c-0.6,0-1,0.4-1,1s0.4,1,1,1h7c0.6,0,1-0.4,1-1S29.6,14,29,14z"/>
					<path d="M29,20H3c-0.6,0-1,0.4-1,1s0.4,1,1,1h26c0.6,0,1-0.4,1-1S29.6,20,29,20z"/>
					<path d="M29,26H3c-0.6,0-1,0.4-1,1s0.4,1,1,1h26c0.6,0,1-0.4,1-1S29.6,26,29,26z"/>
					<path d="M8.6,8.2l3.4,2.6l5-3.6V3c0-0.6-0.4-1-1-1H3C2.4,2,2,2.4,2,3v9.3l5.4-4C7.8,7.9,8.2,7.9,8.6,8.2z M12,4c1.1,0,2,0.9,2,2
						s-0.9,2-2,2s-2-0.9-2-2S10.9,4,12,4z"/>
					<path d="M3,16h13c0.6,0,1-0.4,1-1V9.7l-4.4,3.2c-0.4,0.3-0.8,0.2-1.2,0L8,10.3l-6,4.5V15C2,15.6,2.4,16,3,16z"/>
				</g>
			</svg>';
		/*$button->iconSVG = '
			<svg version="1.1" id="_x31_" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
					 viewBox="0 0 128 128" style="enable-background:new 0 0 128 128;" xml:space="preserve">
				<g>
					<rect x="29.6" y="25.8" width="28.8" height="34.6"/>
					<rect x="69.9" y="25.8" width="23" height="5.8"/>
					<rect x="69.9" y="54.6" width="23" height="5.8"/>
					<rect x="69.9" y="40.2" width="23" height="5.8"/>
					<rect x="29.6" y="69.2" width="63.4" height="5.8"/>
					<rect x="29.6" y="97.3" width="63.4" height="5.8"/>
					<rect x="29.6" y="82.9" width="63.4" height="5.8"/>
					<path d="M15.2,7.3V123H109V7.3H15.2z M103.2,117.2H20.9V13.1h82.3V117.2z"/>
				</g>
			</svg>';*/
		$button->class   = 'btn';
		$button->link    = $link;
		$button->text    = JText::_('PLG_EDITORS-XTD_FCITEM_BUTTON_FCITEM');
		$button->name    = FLEXI_J40GE ? 'fcitem' : 'file-2';
		$button->options = "{handler: 'iframe', size: {x: 800, y: 500}}";

		return $button;
	}
}
