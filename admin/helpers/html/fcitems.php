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

use Joomla\Component\Content\Site\Helper\RouteHelper as ContentRouteHelper;
use Joomla\Database\DatabaseInterface;
defined('_JEXEC') or die;

require_once('fcbase.php');

version_compare(JVERSION, '4.0', 'lt')
    ? require_once JPATH_SITE . '/components/com_content/helpers/route.php'
    : require_once JPATH_SITE . '/components/com_content/src/Helper/RouteHelper.php';

/**
 * Fcitems HTML helper
 *
 * @since  3.3
 */
abstract class JHtmlFcitems extends JHtmlFcbase
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
		$allow_jview = $row->tparams->get('allow_jview', array('allow_jview'));

		return !$allow_jview
			? FlexicontentHelperRoute::getItemRoute($row->id . ':' . $row->alias, $row->categoryslug, 0, $row)
			: (version_compare(JVERSION, '4.0', 'lt')
				? ContentHelperRoute::getArticleRoute($row->id . ':' . $row->alias, $row->catid, $row->language)
				: ContentRouteHelper::getArticleRoute($row->id . ':' . $row->alias, $row->catid, $row->language)
			);
	}


	/**
	 * Create the scheduled/expired icons
	 *
	 * @param   object   $row        The row
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code
	 */
	public static function scheduled_expired($row, $i)
	{
		static $nullDate = null;
		static $nowDate = null;
		static $tz = null;

		if ($tz === null)
		{
			$nullDate = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class)->getNullDate();
			$nowDate = \Joomla\CMS\Factory::getDate()->toUnix();

			$tz = \Joomla\CMS\Factory::getApplication()->getIdentity()->getTimezone();
		}

		// Check publication START/FINISH dates on if item has state: publised / in-progress / archived
		if (!in_array($row->state, array(1, -5, 2)))
		{
			return '';
		}

		$publish_up = $row->publish_up && $row->publish_up != $nullDate
			? \Joomla\CMS\Factory::getDate($row->publish_up, 'UTC')->setTimeZone($tz)
			: false;
		$publish_down = $row->publish_down && $row->publish_down != $nullDate
			? \Joomla\CMS\Factory::getDate($row->publish_down, 'UTC')->setTimeZone($tz)
			: false;

		// Create tip text, only if we have publish up or down settings
		if ($publish_up && $publish_up > $nullDate && $nowDate < $publish_up->toUnix())
		{
			$tip_text = \Joomla\CMS\Language\Text::_('FLEXI_SCHEDULED_FOR_PUBLICATION', true)
				. ' <br> '
				. \Joomla\CMS\Language\Text::sprintf('JLIB_HTML_PUBLISHED_START', \Joomla\CMS\HTML\HTMLHelper::_('date', $publish_up, \Joomla\CMS\Language\Text::_('DATE_FORMAT_LC5'), 'UTC'));

			return '
			<span class="ntxt">
				<span class="icon-pending ' . static::$tooltip_class . '" data-title="' . $tip_text . '"></span>
			</span> ';
		}

		if ($publish_down && $publish_down > $nullDate && $nowDate > $publish_down->toUnix())
		{
			$tip_text = \Joomla\CMS\Language\Text::_('FLEXI_PUBLICATION_EXPIRED', true)
				. ' <br> '
				. \Joomla\CMS\Language\Text::sprintf('JLIB_HTML_PUBLISHED_FINISHED', \Joomla\CMS\HTML\HTMLHelper::_('date', $publish_down, \Joomla\CMS\Language\Text::_('DATE_FORMAT_LC5'), 'UTC'));

			return '
			<span class="ntxt">
				<span class="icon-expired ' . static::$tooltip_class . '" data-title="' . $tip_text . '"></span>
			</span> ';
		}

		return '';
	}


	/**
	 * Create the reviewing needed icon
	 *
	 * @param   object   $row        The row
	 * @param   object   $user       The user
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code
	 */
	public static function reviewing_needed($row, $user, $i)
	{
		$html = '';

		if ($row->unapproved_version)
		{
			$tip_text = \Joomla\CMS\Language\Text::_('FLEXI_UNREVIEWED_VERSION') . ' , ' . \Joomla\CMS\Language\Text::_('FLEXI_NEED_TO_BE_APPROVED');
			$html = '
			<span class="fc-revised-icon">
				<span class="icon-out-3 ' . static::$tooltip_class . '" title="' . $tip_text . '"></span>
			</span> ';
		}

		return $html;
	}


	/**
	 * Create the feature/unfeature links
	 *
	 * @param   object   $row        The row
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code of button to toggle the property
	 */
	public static function featured($row, $i)
	{
		static $states = null;

		if ($states === null)
		{
			// Array of image, task, title, action
			$states = array(
				0 => array('unfeatured', static::$ctrl . '.featured', \Joomla\CMS\HTML\HTMLHelper::tooltipText('COM_CONTENT_UNFEATURED'), \Joomla\CMS\HTML\HTMLHelper::tooltipText('JGLOBAL_TOGGLE_FEATURED')),
				1 => array('featured', static::$ctrl . '.unfeatured', \Joomla\CMS\HTML\HTMLHelper::tooltipText('COM_CONTENT_FEATURED'), \Joomla\CMS\HTML\HTMLHelper::tooltipText('JGLOBAL_TOGGLE_FEATURED')),
			);
		}
		$value = (int) $row->featured;
		$state = isset($states[$value]) ? $states[$value] : $states[1];

		return $row->canEditState && $row->canCheckin
			? '
			<a href="javascript:;" onclick="return Joomla.listItemTask(\'cb' . $i . '\',\'' . $state[1] . '\')" class="featured ntxt ' . static::$btn_sm_class . ' ' . static::$btn_mbar_class . ' ' . static::$tooltip_class
				. ($value == 1 ? ' active' : '') . '" title="' . $state[3] . '">
				<span class="icon-' . $state[0] . '"></span>
			</a>
			' : '
			<a class="featured ntxt ' . static::$btn_sm_class . ' ' . static::$btn_mbar_class . ' ' . static::$tooltip_class . ' disabled' . ($value == 1 ? ' active' : '') . '" title="'
				. $state[2] . '">
				<span class="icon-' . $state[0] . '"></span>
			</a>';
	}
}
