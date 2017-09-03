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
 * Fccats HTML helper
 *
 * @since  3.2
 */
abstract class JHtmlFccats
{
	/**
	 * Create the publish/unpublish links
	 *
	 * @param   int      $value      The state value
	 * @param   int      $i          Row number
	 * @param   boolean  $canChange  Is user allowed to change?
	 *
	 * @return  string       HTML code
	 */
	public static function published($value = 0, $i, $canChange = true)
	{
		static $states = null;
		//return JHtml::_('jgrid.published', $_published = $value, $i, 'categories.', $canChange);

		if ($states === null)
		{
			// Array of image, task, title, action
			$states = array(
				0 => array('unpublish', 'categories.publish', JHtml::tooltipText('JUNPUBLISHED'), JHtml::tooltipText('JPUBLISHED')),
				1 => array('publish', 'categories.unpublish', JHtml::tooltipText('JPUBLISHED'), JHtml::tooltipText('JUNPUBLISHED')),
			);
		}
		$value = (int) $value;
		$state = isset($states[$value]) ? $states[$value] : $states[1];

		return $canChange
			? '
			<a href="javascript:;" onclick="return listItemTask(\'cb' . $i . '\',\'' . $state[1] . '\')" class="statetoggler btn btn-small ntxt hasTooltip'
				. ($value == 1 ? ' active' : '') . '" title="' . $state[3] . '">
				<span class="icon-' . $state[0] . '"></span>
			</a>
			' : '
			<a class="statetoggler btn btn-small ntxt hasTooltip disabled' . ($value == 1 ? ' active' : '') . '" title="'
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
		//return JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'categories.', $row->canCheckin);

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
		<a class="btn btn-micro ntxt hasTooltip" title="'.$_tip_title.'" href="javascript:;" onclick="var ccb=document.getElementById(\'cb'.$i.'\'); ccb.checked=1; ccb.form.task.value=\'categories.checkin\'; ccb.form.submit();">
			<span class="icon-checkedout"></span>
		</a>
		';
	}
}
