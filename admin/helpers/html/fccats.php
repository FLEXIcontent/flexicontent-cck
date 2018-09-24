<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            http://www.flexicontent.com
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

require_once('fcbase.php');


/**
 * Fccats HTML helper
 *
 * @since  3.3
 */
abstract class JHtmlFccats extends JHtmlFcbase
{
	static $tooltip_class  = FLEXI_J40GE ? 'hasTooltip' : 'hasTooltip';
	static $btn_sm_class   = FLEXI_J40GE ? 'btn btn-sm' : 'btn btn-small';
	static $btn_iv_class   = FLEXI_J40GE ? 'btn-dark' : 'btn-inverse';
	static $btn_mbar_class = FLEXI_J40GE ? 'btn-outline-info' : '';
	static $ctrl = 'categories';
	static $name = 'category';
	static $title_propname = 'title';
	static $state_propname = 'published';
	static $layout_type = 'category';

	/**
	 * Get the preview url
	 *
	 * @param   object   $row        The row
	 *
	 * @return  string   The preview URL
	 */
	protected static function _getPreviewUrl($row)
	{
		global $globalcats;

		return FlexicontentHelperRoute::getCategoryRoute($globalcats[$row->id]->slug, 0, array(), $row);
	}


	/**
	 * Create the RSS link icon
	 *
	 * @param   object   $row        The row
	 * @param   string   $target     The target of the link
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code
	 */
	public static function rss_link($row, $target, $i)
	{
		global $globalcats;

		// Route the record URL to an appropriate menu item
		$record_url = static::_getPreviewUrl($row);

		// Force language to be switched to the language of the record, thus showing the record (and not its associated translation of current FE language)
		if (isset($row->language) && $row->language !== '*' && isset(FLEXIUtilities::getLanguages()->{$row->language}))
		{
			$record_url .= '&lang=' . FLEXIUtilities::getLanguages()->{$row->language}->sef;
		}

		// Build a frontend SEF url
		$link = flexicontent_html::getSefUrl($record_url);

		// Add feed / type variables
		$link = $link . (strstr($link, '?') ? '&amp;' : '?') . 'format=feed&amp;type=rss';

		$attribs = ''
			. ' class="fc-preview-btn ntxt ' .  static::$btn_mbar_class . ' ' . static::$btn_sm_class . ' ' . static::$tooltip_class . '"'
			. ' title="' . flexicontent_html::getToolTip('FLEXI_PREVIEW', 'FLEXI_DISPLAY_ENTRY_IN_FRONTEND_DESC', 1, 1) . '"'
			. ' href="' . $link .'"'
			. '	target="' . $target . '"';

		return '
		<a ' . $attribs . '>
			<span class="icon-feed"></span>
		</a> ';
	}
}
