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

JHtml::_('bootstrap.tooltip');


/**
 * Fcbase HTML helper (helper with common methods)
 *
 * @since  3.3
 */
abstract class JHtmlFcbase
{
	static $tooltip_class  = FLEXI_J40GE ? 'hasTooltip' : 'hasTooltip';
	static $btn_sm_class   = FLEXI_J40GE ? 'btn btn-sm' : 'btn btn-small';
	static $btn_iv_class   = FLEXI_J40GE ? 'btn-dark' : 'btn-inverse';
	static $btn_mbar_class = FLEXI_J40GE ? 'btn-outline-info' : '';
	static $ctrl = 'records';
	static $name = 'record';
	static $title_propname = 'title';
	static $state_propname = 'state';
	static $layout_type = null;


	/**
	 * Create the a frontend link icon
	 *
	 * @param   object   $row        The row
	 * @param   string   $target     The target of the link
	 * @param   int      $i          Row number
	 * @param   array    $config     Configuration array, 'equery' : Extra query string, 'hash' : a HashTag, 'iconClass' : CSS icon class
	 *
	 * @return  string       HTML code
	 */
	protected static function icon_link($row, $target, $i, $config = array())
	{
		if ($row)
		{
			// Route the record URL to an appropriate menu item
			$record_url = static::_getPreviewUrl($row);

			// Force language to be switched to the language of the record, thus showing the record (and not its associated translation of current FE language)
			if (isset($row->language) && $row->language !== '*' && isset(FLEXIUtilities::getLanguages()->{$row->language}))
			{
				$record_url .= '&lang=' . FLEXIUtilities::getLanguages()->{$row->language}->sef;
			}

			// Build a frontend SEF url
			$link = flexicontent_html::getSefUrl($record_url);

			// Add extra query string e.g. feed / type variables
			if (!empty($config['equery']))
			{
				$link = $link . (strstr($link, '?') ? '&amp;' : '?') . $config['equery'];
			}

			// Add optional hashtag to jump at specific point
			$link .= !empty($config['hash']) ? $config['hash'] : '';

			$disabled_class = '';
			$disabled_btn = '';
		}
		else
		{
			$link = "javascript: return false;";
			$disabled_class = 'disabled';
			$disabled_btn = '<span class="fc_icon_disabled"></span>';
		}

		$attribs = ''
			. ' class="fc-preview-btn ntxt ' . $disabled_class . ' ' .  static::$btn_mbar_class . ' ' . static::$btn_sm_class . ' ' . static::$tooltip_class . '"'
			. ' title="' . flexicontent_html::getToolTip('FLEXI_PREVIEW', 'FLEXI_DISPLAY_ENTRY_IN_FRONTEND_DESC', 1, 1) . '"'
			. ' href="' . $link .'"'
			. '	target="' . $target . '"';

		return '
		<a ' . $attribs . '>
			' . $disabled_btn . '
			<span class="' . $config['iconClass'] . '"></span>
		</a> ';
	}


	/**
	 * Create the checkin link, also showing if a record is checkedout
	 *
	 * @param   object   $row        The row
	 * @param   object   $user       The user
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code
	 */
	public static function checkedout($row, $user, $i)
	{
		//return JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, static::$ctrl . '.', $row->canCheckin);

		if (!$row->checked_out)
		{
			return '<span class="icon-pencil-2"></span>';
		}

		if (!$row->canCheckin)
		{
			return '<span class="icon-lock ' . static::$tooltip_class . '" title="' . htmlspecialchars(JHtml::tooltipText('FLEXI_RECORD_CHECKED_OUT_DIFF_USER', true, false), ENT_QUOTES, 'UTF-8') . '"></span> ';
		}

		$_tip_title = $row->checked_out == $user->id
			? JText::sprintf('FLEXI_CLICK_TO_RELEASE_YOUR_LOCK_DESC', $row->editor, $row->checked_out_time)
			: JText::sprintf('FLEXI_CLICK_TO_RELEASE_FOREIGN_LOCK_DESC', $row->editor, $row->checked_out_time);

		return 
		($row->checked_out != $user->id ? '<input id="cb'.$i.'" type="checkbox" value="'.$row->id.'" name="cid[]" style="display:none!important;">' : '') . '
		<a class="btn btn-micro btn-outline-secondary ntxt ' . static::$tooltip_class . '" title="' . htmlspecialchars(JHtml::tooltipText($_tip_title, true, false), ENT_QUOTES, 'UTF-8') . '" href="javascript:;" onclick="var ccb=document.getElementById(\'cb'.$i.'\'); ccb.checked=1; ccb.form.task.value=\'' . static::$ctrl . '.checkin\'; ccb.form.submit();">
			<span class="icon-checkedout"></span>
		</a>
		';
	}


	/**
	 * Create the statetoggler button
	 *
	 * @param   object   $row        The row
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code
	 */
	public static function statebutton($row, $i)
	{
		static $params = null;
		static $addToggler = true;
		static $tipPlacement = 'top';
		static $config = null;

		if ($config === null)
		{
			$config = (object) array(
				'controller' => static::$ctrl,
				'record_name' => static::$name,
				'state_propname' => static::$state_propname,
				'addToggler' => $addToggler,
				'tipPlacement' => $tipPlacement,
				'class' => static::$btn_sm_class . ' ' . static::$btn_mbar_class,
			);
		}

		return flexicontent_html::statebutton($row, $params, $config);
	}


	/**
	 * Create the preview link icon
	 *
	 * @param   object   $row        The row
	 * @param   string   $target     The target of the link
	 * @param   int      $i          Row number
	 * @param   int      $hash       HashTag to append to the link
	 *
	 * @return  string       HTML code
	 */
	public static function preview($row, $target, $i, $hash = '')
	{
		return static::icon_link($row, $target, $i, array(
			'iconClass' => 'icon-screen',
			'hash' => $hash,
		));
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
		return static::icon_link($row, $target, $i, array(
			'iconClass' => 'icon-feed',
			'hash' => $hash,
			'equery' => 'format=feed&amp;type=rss',
		));
	}


	/**
	 * Create the edit record link
	 *
	 * @param   object   $row         The row
	 * @param   int      $i           Row number
	 * @param   boolean  $canEdit     Is user allowed to edit the item
	 * @param   array    $config      Configuration array, 'ctrl' : controller name, 'option' : component name, 'jtag_id' : 'property name holding the id', 'useModal' : edit in modal
	 *
	 * @return  string       HTML code
	 */
	public static function edit_link($row, $i, $canEdit, $config = array())
	{
		// Display title with no edit link ... if row is not-editable for any reason (no ACL or checked-out by other user)
		if (!$canEdit || ($row->checked_out && (int) $row->checked_out !== (int) JFactory::getUser()->id))
		{
			return htmlspecialchars($row->{static::$title_propname}, ENT_QUOTES, 'UTF-8');
		}

		// Display title with edit link ... (row editable and not checked out)
		$option    = isset($config['option']) ? $config['option'] : 'com_flexicontent';
		$ctrl      = isset($config['ctrl']) ? $config['ctrl'] : static::$name;
		$keyname   = isset($config['keyname']) ? $config['keyname'] : 'id';
		$edit_task = 'task=' . $ctrl . '.edit';
		$edit_link = 'index.php?option=' . $option . '&amp;' . $edit_task . '&amp;view=' . static::$name . '&amp;'
			. 'id=' . $row->{$keyname};

		$attrs = ' title="' . JText::_('FLEXI_EDIT', true) . '" class="fc-iblock text-dark" ';

		if (!empty($config['useModal']))
		{
			$attrs .= " onclick=\"var url = jQuery(this).attr('data-href'); var the_dialog = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, fc_edit_jtag_modal_close, {title:'" . JText::_('FLEXI_EDIT_JTAG') . "', loadFunc: fc_edit_jtag_modal_load}); return false;\" ";
		}

		// Display title with no edit link ... if row is not-editable for any reason (no ACL or checked-out by other user)
		if (!empty($config['useModal']))
		{
			return '
			<a href="javascript:;" data-href="' . $edit_link . '" ' . $attrs . '>
				<span class="icon-pencil"></span>
			</a>';
		}
		else
		{
			return '
			<a href="' . $edit_link . '" ' . $attrs . '>
				' . htmlspecialchars($row->{static::$title_propname}, ENT_QUOTES, 'UTF-8') . '
			</a>';
		}
	}


	/**
	 * Method to create a checkbox for a grid row.
	 *
	 * @param   integer  $rowNum      The row index
	 * @param   integer  $recId       The record id
	 * @param   boolean  $checkedOut  True if item is checked out
	 * @param   string   $name        The name of the form element
	 * @param   string   $stub        The name of stub identifier
	 * @param   string   $title       The name of the item
	 *
	 * @return  mixed    String of html with a checkbox if item is not checked out, null if checked out.
	 *
	 * @since   3.3
	 */
	public static function grid_id($rowNum, $recId, $checkedOut = false, $name = 'cid', $stub = 'cb', $title = '')
	{
		if ($checkedOut)
		{
			return '';
		}

		return '
			<div class="group-fcset">
				<input type="checkbox" id="' . $stub . $rowNum . '" name="' . $name . '[]" value="' . $recId . '" onclick="Joomla.isChecked(this.checked);">
				<label for="' . $stub . $rowNum . '" class="green single"><span class="sr-only">' . JText::_('JSELECT') . ' ' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</span></label>
			</div>';
	}


	/**
	 * Get the preview url
	 *
	 * @param   object   $row        The row
	 *
	 * @return  string   The preview URL
	 */
	protected static function _getPreviewUrl($row)
	{
		die(__FUNCTION__ . ' is not implemented');
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
		if (!$canTemplates || !$layout)
		{
			$isDisabled = true;
		}

		$layout_url = 'index.php?option=com_flexicontent&amp;view=template&amp;type=' . static::$layout_type
			. '&amp;tmpl=component&amp;ismodal=' . ($target === '__modal__' ? '1' : '0') . '&amp;folder=' . $layout
			. '&amp;' . JSession::getFormToken() . '=1';

		if ($target === '__modal__')
		{
			$edit_title = htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', true), ENT_QUOTES, 'UTF-8');
			$target_attr = ' onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, 0, {title: \'' . $edit_title . '\'}); return false;"';
		}
		else
		{
			$target_attr = '	target="' . $target . '"';
		}

		$attribs = ''
			. ' class="fc-edit-layout-btn ntxt ' .  static::$btn_mbar_class . ' ' . static::$btn_sm_class . ' ' . static::$tooltip_class . '"'
			. ' title="'.flexicontent_html::getToolTip( 'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', null, 1, 1).'"'
			. ' href="' . $layout_url .'"'
			. $target_attr;

		return !empty($isDisabled) ? '
		<span class="fc_icon_disabled"></span><span class="icon-pencil"></span>
		' : '
		<a ' . $attribs . '>
			<span class="icon-pencil"></span>
		</a> ';
	}
}
