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

require_once('fcbase.php');


/**
 * Fcreviews HTML helper
 *
 * @since  3.3
 */
abstract class JHtmlFcreviews extends JHtmlFcbase
{
	static $tooltip_class  = FLEXI_J40GE ? 'hasTooltip' : 'hasTooltip';
	static $btn_sm_class   = FLEXI_J40GE ? 'btn btn-sm' : 'btn btn-small';
	static $btn_iv_class   = FLEXI_J40GE ? 'btn-dark' : 'btn-inverse';
	static $btn_mbar_class = FLEXI_J40GE ? 'btn-outline-info' : '';
	static $ctrl = 'reviews';
	static $name = 'review';
	static $title_propname = 'title';
	static $state_propname = 'state';
	static $layout_type = null;

	/**
	 * Get the preview url
	 *
	 * @param   object   $row        The row
	 *
	 * @return  string   The preview URL
	 */
	protected static function _getPreviewUrl($row)
	{
		return FlexicontentHelperRoute::getItemRoute($row->id . ':' . $row->alias, $row->categoryslug, 0, $row);
	}


	/**
	 * Create the approve/unapprove links
	 *
	 * @param   object   $row        The row
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code of button to toggle the property
	 */
	public static function approved($row, $i)
	{
		static $states = null;

		if ($states === null)
		{
			// Simple inline SVG icons: green check (approved), grey cross (unapproved)
			$svg_open = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false" style="display:block;"><path fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="';
			$svg_check = $svg_open . 'M4 12.5l5 5L20 6.5"/></svg>';
			$svg_cross = $svg_open . 'M6 6l12 12M18 6L6 18"/></svg>';

			// Array of icon, task, title, action, style
			$states = array(
				0 => array($svg_cross, static::$ctrl . '.approved', \Joomla\CMS\HTML\HTMLHelper::tooltipText('FLEXI_REVIEW_UNAPPROVED'), \Joomla\CMS\HTML\HTMLHelper::tooltipText('FLEXI_TOGGLE'), 'color: #9aa0a6;'),
				1 => array($svg_check, static::$ctrl . '.unapproved', \Joomla\CMS\HTML\HTMLHelper::tooltipText('FLEXI_REVIEW_APPROVED'), \Joomla\CMS\HTML\HTMLHelper::tooltipText('FLEXI_TOGGLE'), 'color: #198754;'),
			);
		}
		$value = (int) $row->approved;
		$state = isset($states[$value]) ? $states[$value] : $states[1];

		return $row->canEditState && $row->canCheckin
			? '
			<a href="javascript:;" onclick="return Joomla.listItemTask(\'cb' . $i . '\',\'' . $state[1] . '\')" class="fclink-approved ' . static::$tooltip_class . '" title="' . $state[3] . '"
				style="display:inline-block;text-decoration:none;cursor:pointer;' . $state[4] . '">' . $state[0] . '</a>
			' : '
			<span class="fclink-approved ' . static::$tooltip_class . '" title="' . $state[2] . '" style="display:inline-block;opacity:0.6;' . $state[4] . '">' . $state[0] . '</span>';
	}
}
