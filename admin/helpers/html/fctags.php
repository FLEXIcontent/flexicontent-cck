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
 * Fctags HTML helper
 *
 * @since  3.3
 */
abstract class JHtmlFctags extends JHtmlFcbase
{
	static $tooltip_class  = FLEXI_J40GE ? 'hasTooltip' : 'hasTooltip';
	static $btn_sm_class   = FLEXI_J40GE ? 'btn btn-sm' : 'btn btn-small';
	static $btn_iv_class   = FLEXI_J40GE ? 'btn-dark' : 'btn-inverse';
	static $btn_mbar_class = FLEXI_J40GE ? 'btn-outline-info' : '';
	static $ctrl = 'tags';
	static $name = 'tag';
	static $title_propname = 'name';
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
		return JComponentHelper::getParams('com_flexicontent')->get('tags_using_catview', 0)
			? FlexicontentHelperRoute::getCategoryRoute(0, 0, array('layout'=>'tags','tagid'=>$row->slug), $row)
			: FlexicontentHelperRoute::getTagRoute($row->slug);
	}


	/**
	 * Create the RSS link icon
	 *
	 * @param   object   $row        The row
	 * @param   string   $target     The target of the link
	 * @param   int      $i          Row number
	 * @param   int      $hash       HashTag to append to the link
	 *
	 * @return  string       HTML code
	 */
	public static function rss_link($row, $target, $i, $hash = '')
	{
		if (!JComponentHelper::getParams('com_flexicontent')->get('tags_using_catview', 0))
		{
			return '';
		}

		return parent::rss_link($row, $target, $i, $hash);
	}


	/**
	 * Create the edit layout icon
	 *
	 * @param   object   $row           The row
	 * @param   string   $target        The target of the link
	 * @param   int      $i             Row number
	 * @param   boolean  $canTemplates  Is user allowed to edit template layouts
	 * @param   string   $layout        Layout if the record row
	 *
	 * @return  string       HTML code
	 */
	public static function edit_layout($row, $target, $i, $canTemplates, $layout)
	{
		// UNUSED, possibly add layout parameter to tag parameters ?
		return 'UNUSED';

		if (!JComponentHelper::getParams('com_flexicontent')->get('tags_using_catview', 0))
		{
			$layout = false;
		}

		return parent::edit_layout($row, $target, $i, $canTemplates, $layout);
	}
}
