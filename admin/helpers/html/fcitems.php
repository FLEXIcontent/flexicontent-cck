<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_content
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
JHtml::_('bootstrap.tooltip');


/**
 * Fcitems HTML helper
 *
 * @since  3.2
 */
abstract class JHtmlFcitems
{
	/**
	 * Create the feature/unfeature links
	 *
	 * @param   int      $value      The state value
	 * @param   int      $i          Row number
	 * @param   boolean  $canChange  Is user allowed to change?
	 *
	 * @return  string       HTML code
	 */
	public static function featured($value = 0, $i, $canChange = true)
	{
		static $states = null;

		if ($states === null)
		{
			// Array of image, task, title, action
			$states = array(
				0 => array('unfeatured', 'items.featured', JHtml::tooltipText('COM_CONTENT_UNFEATURED'), JHtml::tooltipText('JGLOBAL_TOGGLE_FEATURED')),
				1 => array('featured', 'items.unfeatured', JHtml::tooltipText('COM_CONTENT_FEATURED'), JHtml::tooltipText('JGLOBAL_TOGGLE_FEATURED')),
			);
		}
		$value = (int) $value;
		$state = isset($states[$value]) ? $states[$value] : $states[1];

		return $canChange
			? '
			<a href="javascript:;" onclick="return listItemTask(\'cb' . $i . '\',\'' . $state[1] . '\')" class="featured btn btn-small ntxt hasTooltip'
				. ($value == 1 ? ' active' : '') . '" title="' . $state[3] . '">
				<span class="icon-' . $state[0] . '"></span>
			</a>
			' : '
			<a class="featured btn btn-small ntxt hasTooltip disabled' . ($value == 1 ? ' active' : '') . '" title="'
				. $state[2] . '">
				<span class="icon-' . $state[0] . '"></span>
			</a>';
	}


	/**
	 * Create the checkin link, also showing if a record is checkedout
	 *
	 * @param   int      $row        The row
	 * @param   int      $user       The user
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code
	 */
	public static function checkedout($row, $user, $i)
	{
		//return JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'items.', $row->canCheckin);

		if (!$row->checked_out) return '';
		if (!$row->canCheckin)
		{
			return '<span class="icon-lock hasTooltip" title="'.JHtml::tooltipText('FLEXI_RECORD_CHECKED_OUT_DIFF_USER').'"></span> ';
		}

		$_tip_title = $row->checked_out == $user->id
			? JText::sprintf('FLEXI_CLICK_TO_RELEASE_YOUR_LOCK_DESC', $row->editor, $row->checked_out_time)
			: JText::sprintf('FLEXI_CLICK_TO_RELEASE_FOREIGN_LOCK_DESC', $row->editor, $row->checked_out_time);

		return 
		($row->checked_out != $user->id ? '<input id="cb'.$i.'" type="checkbox" value="'.$row->id.'" name="cid[]" style="display:none!important;">' : '') . '
		<a class="btn btn-micro ntxt hasTooltip" title="'.$_tip_title.'" href="javascript:;" onclick="var ccb=document.getElementById(\'cb'.$i.'\'); ccb.checked=1; ccb.form.task.value=\'items.checkin\'; ccb.form.submit();">
			<span class="icon-checkedout"></span>
		</a>
		';
	}


	/**
	 * Create the scheduled/expired icons
	 *
	 * @param   int      $row        The row
	 * @param   int      $user       The user
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code
	 */
	public static function scheduled_expired($row, $user, $i)
	{
		static $html = null;
		if ($html === null)
		{
			$tip_class = 'hasTooltip';

			$tip_text = JText::_('FLEXI_SCHEDULED_FOR_PUBLICATION', true);
			$html['scheduled'] = '
			<span class="btn btn-micro ntxt active" style="cursor:default">
				<img src="components/com_flexicontent/assets/images/pushished_scheduled.png" width="16" height="16" style="border: 0;" class="'.$tip_class.'" alt="'.$tip_text.'" title="'.$tip_text.'" />
			</span> ';

			$tip_text = JText::_('FLEXI_PUBLICATION_EXPIRED', true);
			$html['expired'] = '
			<span class="btn btn-micro ntxt active" style="cursor:default">
				<img src="components/com_flexicontent/assets/images/pushished_expired.png" width="16" height="16" style="border: 0;" class="'.$tip_class.'" alt="'.$tip_text.'" title="'.$tip_text.'" />
			</span> ' ;
		}

		// Check publication START/FINISH dates (publication Scheduled / Expired)
		if ( in_array($row->state, array(1, -5, 2)) )
		{
			if ($row->publication_scheduled) return $html['scheduled'];
			if ($row->publication_expired)   return $html['expired'];
		}

		return '';
	}


	/**
	 * Create the reviewing needed icon
	 *
	 * @param   int      $row        The row
	 * @param   int      $user       The user
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code
	 */
	public static function reviewing_needed($row, $user, $i)
	{
		$html = '';
		if ($row->unapproved_version)
		{
			$tip_class = 'hasTooltip';
			$tip_text = JText::_('FLEXI_UNREVIEWED_VERSION') . ' , ' . JText::_('FLEXI_NEED_TO_BE_APPROVED');
			$html = '
			<span class="fc-revised-icon">
				<span class="icon-out-3 '.$tip_class.'" title="'.$tip_text.'"></span>
			</span> ';
		}

		return $html;
	}
}
