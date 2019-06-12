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
 * Fcmediadatas HTML helper
 *
 * @since  3.3
 */
abstract class JHtmlFcmediadatas extends JHtmlFcbase
{
	static $tooltip_class  = FLEXI_J40GE ? 'hasTooltip' : 'hasTooltip';
	static $btn_sm_class   = FLEXI_J40GE ? 'btn btn-sm' : 'btn btn-small';
	static $btn_iv_class   = FLEXI_J40GE ? 'btn-dark' : 'btn-inverse';
	static $btn_mbar_class = FLEXI_J40GE ? 'btn-outline-info' : '';
	static $ctrl = 'mediadatas';
	static $name = 'mediadata';
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
			// Array of image, task, title, action
			$states = array(
				0 => array('cancel-circle', static::$ctrl . '.approved', JHtml::tooltipText('FLEXI_MEDIADATA_UNAPPROVED'), JHtml::tooltipText('FLEXI_TOGGLE'), 'color: #555;'),
				1 => array('checkmark-2', static::$ctrl . '.unapproved', JHtml::tooltipText('FLEXI_MEDIADATA_APPROVED'), JHtml::tooltipText('FLEXI_TOGGLE'), 'color: darkgreen;'),
			);
		}
		$value = (int) $row->approved;
		$state = isset($states[$value]) ? $states[$value] : $states[1];

		return $row->canEditState && $row->canCheckin
			? '
			<a href="javascript:;" onclick="return listItemTask(\'cb' . $i . '\',\'' . $state[1] . '\')" class="featured ntxt ' . static::$btn_sm_class . ' ' . static::$btn_mbar_class . ' ' . static::$tooltip_class
				. ($value == 1 ? ' active' : '') . '" title="' . $state[3] . '">
				<span class="icon-' . $state[0] . '" style="'. $state[4] .'"></span>
			</a>
			' : '
			<a class="fclink-approved ntxt ' . static::$btn_sm_class . ' ' . static::$btn_mbar_class . ' ' . static::$tooltip_class . ' disabled' . ($value == 1 ? ' active' : '') . '" title="'
				. $state[2] . '">
				<span class="icon-' . $state[0] . '" style="'. $state[4] .'"></span>
			</a>';
	}
}
