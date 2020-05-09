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
 * Fcitemelement HTML helper
 *
 * @since  3.3
 */
abstract class JHtmlFcitemelement extends JHtmlFcbase
{
	static $tooltip_class  = FLEXI_J40GE ? 'hasTooltip' : 'hasTooltip';
	static $btn_sm_class   = FLEXI_J40GE ? 'btn btn-sm' : 'btn btn-small';
	static $btn_iv_class   = FLEXI_J40GE ? 'btn-dark' : 'btn-inverse';
	static $btn_mbar_class = FLEXI_J40GE ? 'btn-outline-info' : '';
	static $ctrl = 'items';
	static $name = 'item';
	static $title_propname = 'title';
	static $state_propname = 'state';
	static $layout_type = 'items';

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
}
