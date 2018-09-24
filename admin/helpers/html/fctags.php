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
	 *
	 * @return  string       HTML code
	 */
	public static function rss_link($row, $target, $i)
	{
		if (!JComponentHelper::getParams('com_flexicontent')->get('tags_using_catview', 0))
		{
			return '';
		}

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
		if (!JComponentHelper::getParams('com_flexicontent')->get('tags_using_catview', 0))
		{
			$layout = false;
		}

		return parent::edit_layout($row, $target, $i, $canTemplates, $layout);
	}


	/**
	 * Create the edit link to edit the associated joomla record data in a modal
	 *
	 * @param   object   $row         The row
	 * @param   int      $i           Row number
	 * @param   string   $ctrl        Controller name
	 * @param   boolean  $canEdit     Is user allowed to edit the item
	 *
	 * @return  string       HTML code
	 */
	public static function edit_link_jrecord($row, $i, $ctrl, $canEdit)
	{
		static $common_attrs = null;
		$ctrl = $ctrl ?: static::$name;
		
		if ($common_attrs === null)
		{
			$common_attrs = 'title="' . JText::_('FLEXI_EDIT', true) . '" class="fc-iblock text-dark"';
			$common_attrs .= "onclick=\"var url = jQuery(this).attr('data-href'); var the_dialog = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, fc_edit_jtag_modal_close, {title:'" . JText::_('FLEXI_EDIT_JTAG') . "', loadFunc: fc_edit_jtag_modal_load}); return false;\"";
		}

		// Display title with no edit link ... if row is not-editable for any reason (no ACL or checked-out by other user)
		if (!$canEdit || ($row->checked_out && (int) $row->checked_out !== (int) JFactory::getUser()->id))
		{
			return htmlspecialchars($row->{static::$title_propname}, ENT_QUOTES, 'UTF-8');
		}

		// Display title with edit link ... (row editable and not checked out)
		else
		{
			$option    = 'com_tags';
			$edit_task = 'task=' . 'tag' . '.edit';
			$edit_link = 'index.php?option=' . $option . '&amp;' . $edit_task . '&amp;view=' . static::$name . '&amp;'
				. 'id=' . $row->jtag_id;

			return '
			<a href="javascript:;" data-href="' . $edit_link . '" ' . $common_attrs . '>
				<span class="icon-pencil"></span>
			</a>';
		}
	}
}
