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

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
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
	static $translateable_props = array();


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
			$link = flexicontent_html::getSefUrl($record_url . '&preview=2');

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
			$link = '';

			$disabled_class = 'disabled';
			$disabled_btn   = '<span class="fc_icon_disabled"></span>';
		}

		$attribs = ''
			. ' class="fc-preview-btn ntxt ' . $disabled_class . ' ' .  static::$btn_mbar_class . ' ' . static::$btn_sm_class . ' ' . static::$tooltip_class . '"'
			. ' title="' . flexicontent_html::getToolTip('FLEXI_PREVIEW', 'FLEXI_DISPLAY_ENTRY_IN_FRONTEND_DESC', 1, 1) . '"'
			. ($link ? ' href="' . $link .'"' : '')
			. ($link ? ' target="' . $target . '"' : '');

		$tag = $link ? 'a' : 'span';

		return '
		<' . $tag . ' ' . $attribs . '>
			' . $disabled_btn . '
			<span class="' . $config['iconClass'] . '"></span>
		</' . $tag . '> ';
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
			return '<span class="icon-pencil"></span>';
		}

		if (!$row->canCheckin)
		{
			return '<span class="icon-lock ' . static::$tooltip_class . '" title="' . JHtml::tooltipText('', 'FLEXI_RECORD_CHECKED_OUT_DIFF_USER', true, false) . '"></span> ';
		}

		$_tip_title = $row->checked_out == $user->id
			? JText::sprintf('FLEXI_CLICK_TO_RELEASE_YOUR_LOCK_DESC', $row->editor, $row->checked_out_time)
			: JText::sprintf('FLEXI_CLICK_TO_RELEASE_FOREIGN_LOCK_DESC', $row->editor, $row->checked_out_time);

		return 
		($row->checked_out != $user->id ? '<input id="cb'.$i.'" type="checkbox" value="'.$row->id.'" name="cid[]" style="display:none!important;">' : '') . '
		<a class="btn btn-micro btn-outline-secondary ntxt ' . static::$tooltip_class . '" title="' . JHtml::tooltipText('', $_tip_title, true, false) . '" href="javascript:;" onclick="var ccb=document.getElementById(\'cb'.$i.'\'); ccb.checked=1; ccb.form.task.value=\'' . static::$ctrl . '.checkin\'; ccb.form.submit();">
			<span class="icon-checkedout"></span>
		</a>
		';
	}


	/**
	 * Create the statetoggler button
	 *
	 * @param   object   $row        The row
	 * @param   int      $i          Row number
	 * @param   bool     $locked     Row number
	 *
	 * @return  string       HTML code
	 */
	public static function statebutton($row, $i, $locked = false)
	{
		static $config = null;

		if ($config === null)
		{
			$config = (object) array(
				'controller' => static::$ctrl,
				'record_name' => static::$name,
				'state_propname' => static::$state_propname,
				'addToggler' => true,
				'tipPlacement' => 'top',
				'class' => static::$btn_sm_class . ' ' . static::$btn_mbar_class,
				'locked' => false,
			);
		}

		$config->locked = $locked;

		return flexicontent_html::statebutton($row, null, $config);
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
	 * Configuration array parameter uses:
	 *   'ctrl' : controller name,
	 *   'option' : component name,
	 *   'keyprop' : property with the record id,
	 *   'onclick' : onclick JS, we will create a link, like this: <a onclick="..." href="javascript:;" data-href="URL">...</a>
	 *   'noTitle' : do add title text inside the link
	 *   'useModal' : create a modal, link will be same as onclick above
	 *   'attribs' : attributes of the link (except for onclick which must be given seperately)
	 *
	 * @param   object   $row         The row
	 * @param   int      $i           Row number
	 * @param   boolean  $canEdit     Is user allowed to edit the item
	 * @param   array    $config      Configuration array
	 *
	 *
	 * @return  string       HTML code
	 */
	public static function edit_link($row, $i, $canEdit, $config = array())
	{
		$title = in_array(static::$title_propname, static::$translateable_props)
			? JText::_($row->{static::$title_propname})
			: $row->{static::$title_propname};
		$title_original = $row->{static::$title_propname};
		$title_basic = '';

		if (!empty($row->custom_title))
		{
			$title_basic = $title;
			$title = JText::_($row->custom_title);
			$title_original = $row->custom_title;
		}

		// Limit title length
		$row->title_cut = StringHelper::strlen($title) > 100
			? htmlspecialchars(StringHelper::substr($title, 0, 100), ENT_QUOTES, 'UTF-8') . '...'
			: htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
		$title_cut = $row->title_cut;
		$title_cut_styled = $title_basic
			? '<i>( ' . $title_cut . ' )</i>'
			: $title_cut;

		// Escape & translate
		$title_escaped = htmlspecialchars($row->title_cut, ENT_QUOTES, 'UTF-8');
		$title_untranslated = $title !== $title_original
			? '<span class="icon-flag" title="' . htmlspecialchars($row->{static::$title_propname}, ENT_QUOTES, 'UTF-8') . '"></span>'
			: '';

		// Display title with no edit link ... if row is not-editable for any reason (no ACL or checked-out by other user)
		if (!$canEdit || ($row->checked_out && (int) $row->checked_out !== (int) JFactory::getUser()->id))
		{
			return $title_cut . $title_untranslated;
		}

		// Display title with edit link ... (row editable and not checked out)
		$option    = isset($config['option']) ? $config['option'] : 'com_flexicontent';
		$ctrl      = isset($config['ctrl']) ? $config['ctrl'] : static::$name;
		$view      = isset($config['view']) ? $config['view'] : static::$name;
		$keyname   = isset($config['keyname']) ? $config['keyname'] : 'id';

		// HTML before the link
		$nolinkPrefix = isset($config['nolinkPrefix']) ? $config['nolinkPrefix'] : '';

		// HTML at the start of the link 
		$linkedPrefix = isset($config['linkedPrefix']) ? $config['linkedPrefix'] : '';

		// The edit link
		$edit_task = 'task=' . $ctrl . '.edit';
		$edit_link = 'index.php?option=' . $option . '&amp;' . $edit_task . '&amp;view=' . $view . '&amp;'
			. 'id=' . $row->{$keyname}
			. (isset($config['url_data']) ? $config['url_data'] : '');

		if (!empty($config['attribs']))
		{
			$attrs = is_array($config['attribs'])
				? ArrayHelper::toString($config['attribs'])
				: $config['attribs'];
		}
		else
		{
			$attrs = ' class="fc-iblock" title="' . JText::_('FLEXI_EDIT', true) . '" ';
		}

		if (!empty($config['onclick']))
		{
			$attrs .= ' onclick="' . $config['onclick'] . '"';
		}
		elseif (!empty($config['useModal']))
		{
			$attrs .= ' onclick="' . 'var url = jQuery(this).attr(\'data-href\'); ' .
				'var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, ' . JText::_($config['useModal']->onclosefunc) . ', ' .
				'{title:\'' . JText::_($config['useModal']->title,  true) . '\', loadFunc: ' . JText::_($config['useModal']->onloadfunc) . '}); return false;' .
			'"';
		}

		// Display title with no edit link ... if row is not-editable for any reason (no ACL or checked-out by other user)
		
		if (!empty($config['onclick']) || !empty($config['useModal']))
		{
			return $nolinkPrefix . '
			<a href="javascript:;" data-href="' . $edit_link . '" ' . $attrs . '>
				' . $linkedPrefix . '
				' . (empty($config['noTitle']) ? $title_cut_styled : '') . '
			</a>';
		}
		else
		{
			return $nolinkPrefix . '
			<a href="' . $edit_link . '" ' . $attrs . '>
				' . $linkedPrefix . '
				' . (empty($config['noTitle']) ? $title_cut_styled : '') . '
			</a>
			' . (empty($config['noTitle']) ? $title_untranslated : '');
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
	 * @param   string   $onclick     The js to execute onclick
	 *
	 * @return  mixed    String of html with a checkbox if item is not checked out, null if checked out.
	 *
	 * @since   3.3
	 */
	public static function grid_id($rowNum, $recId, $checkedOut = false, $name = 'cid', $stub = 'cb', $title = '', $onclick = '')
	{
		if ($checkedOut)
		{
			return '';
		}

		return '
			<div class="group-fcset">
				<input type="checkbox" id="' . $stub . $rowNum . '" name="' . $name . '[]" value="' . $recId . '" onclick="Joomla.isChecked(this.checked);">
				<label for="' . $stub . $rowNum . '" class="green single" ' . ($onclick ? 'onclick="' . $onclick . '"' : '') . '>
					<span class="sr-only">' . JText::_('JSELECT') . ' ' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</span>
				</label>
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


	/**
	 * Create the save order button
	 *
	 * @param   array    $rows     The array of records
	 * @param   object   $config   Configuration object
	 *
	 * The $config object has
	 * -- string   $icon_class    An icon class for the button
	 * -- string   $custom_txt    Button text
	 * -- string   $custom_tip    Button tooltip
	 * -- string   $task_value    The task to execute
	 *
	 * @return  string       HTML code
	 */
	public static function saveorder_btn($rows, $config = null)
	{
		$config = (object) array(
			'icon_class' => (!empty($config->icon_class) ? $config->icon_class: 'icon-checkbox'),
			'custom_txt' => (!empty($config->custom_txt) ? $config->custom_txt : ''),
			'custom_tip' => (!empty($config->custom_tip) ? $config->custom_tip : JText::_('JLIB_HTML_SAVE_ORDER')),
			'task_value' => (!empty($config->task_value) ? $config->task_value : static::$ctrl . '.saveorder'),
		);

		return '
		<a href="javascript:;" onclick="var checkAllToggle = document.adminForm.elements[\'checkall-toggle\']; checkAllToggle.checked=true; Joomla.checkAll(checkAllToggle); Joomla.submitform(\'' . $config->task_value . '\');" '
				. ' class="saveorder btn btn-small btn-primary' . ($config->custom_tip ? ' hasTooltip' : '') . '" '
				. ' title="' . JText::_($config->custom_tip ?: '') . '" style="padding: 6px 7px 4px 8px;">
			<span class="' . $config->icon_class . '"></span>
			<span class="hidden-phone">' . JText::_($config->custom_txt ?: '') . '</span>
		</a>';
	}


	/**
	 * Create the manual order toggle button
	 *
	 * @param   array    $rows     The array of records
	 * @param   object   $config   Configuration object
	 *
	 * The $config object has
	 * -- string   $icon_class    An icon class for the button
	 * -- string   $custom_txt    Button text
	 * -- string   $custom_tip    Button tooltip
	 * -- string   $click_attr    The onclick attribute of the button, typically to toggle visibility of the ordering input tags
	 *
	 * @return  string       HTML code
	 */
	public static function manualorder_btn($rows, $config = null)
	{
		$config = (object) array(
			'icon_class' => (!empty($config->icon_class) ? $config->icon_class: 'icon-cog'),
			'custom_txt' => (!empty($config->custom_txt) ? $config->custom_txt : ''),
			'custom_tip' => (!empty($config->custom_tip) ? $config->custom_tip : JText::_('FLEXI_MANUAL_ORDER')),
			'click_attr' => (!empty($config->click_attr) ? $config->click_attr : 'jQuery(\'.fcitem_order_no\').slideToggle();'),
		);

		return '
		<a href="javascript:;" onclick="' . $config->click_attr . '" data-placement="bottom" '
				. ' class="saveorder btn btn-small' . ($config->custom_tip ? ' hasTooltip' : '') . '" '
				. ' title="' . JText::_($config->custom_tip ?: '') . '" style="padding: 6px 4px 4px 6px;">
			<span class="' . $config->icon_class . '"></span>
			<span class="hidden-phone">' . JText::_($config->custom_txt ?: '') . '</span>
		</a>';
	}


	/**
	 * Create the RSS link icon
	 *
	 * @param   object   $row        The row
	 * @param   int      $i          Row number
	 * @param   string   $propname   The column name of the property containing the text
	 *
	 * @return  string       HTML code
	 */
	public static function info_text($row, $i, $propname = 'description', $tip_title = 'FLEXI_DESCRIPTION')
	{
		$uncut_length = 0;

		$title = in_array(static::$title_propname, static::$translateable_props)
			? JText::_($row->{static::$title_propname})
			: $row->{static::$title_propname};

		$text = !$row->$propname ? '' : flexicontent_html::striptagsandcut(
			$row->$propname,
			$cut_text_length = 50,
			$uncut_length,
			$ops = array(
				'cut_at_word' => true,
				'more_toggler' => false,//true,
				'more_icon' => 'icon-paragraph-center',
				'more_txt' => 2,
				'modal_title' => $title
			)
		);

		if (!empty($text))
		{
			echo '<span class="icon-info ' . static::$tooltip_class . '" title="' . flexicontent_html::getToolTip(JText::_('FLEXI_FIELD_DESCRIPTION', true), $text, 0, 1) . '"></span>';
		}
	}


	/**
	 * Create the language display icon or text of a row
	 *
	 * @param   object   $row        The row
	 * @param   int      $i          Row number
	 * @param   array    $langs      Data of available languages
	 * @param   bool     $use_icon   Create an flag icon instead of textual display
	 * @param   string   $undefined  Language string for non-set language
	 *
	 * @return  string       HTML code
	 */
	public static function lang_display($row, $i, $langs, $use_icon = false, $undefined = 'JUNDEFINED')
	{
		if ($use_icon && !empty($row->language) && !empty($langs->{$row->language}->imgsrc))
		{
			return '<img class="' . static::$tooltip_class . '" '.
				' title=' . flexicontent_html::getToolTip(JText::_('FLEXI_LANGUAGE'), ($row->language === '*' ? JText::_('FLEXI_ALL') : (!empty($row->language) ? $langs->{$row->language}->name : '')), 0, 1) . '" ' .
				' src="' . $langs->{$row->language}->imgsrc . '" alt="'. $row->language . '" /> ' .
				($use_icon === 2 ? $langs->{$row->language}->name : '')
				;
		}
		elseif ($row->language === '*')
		{
			return JText::alt('JALL','language');
		}
		else
		{
			return !empty($row->language) ? $langs->{$row->language}->name : JText::_($undefined);
		}
	}
}
