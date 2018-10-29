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

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

jimport('legacy.view.legacy');

/**
 * HTML View class for backend managers (Base)
 */
class FlexicontentViewBaseRecords extends JViewLegacy
{
	var $tooltip_class = FLEXI_J40GE ? 'hasTooltip' : 'hasTooltip';
	var $popover_class = FLEXI_J40GE ? 'hasPopover' : 'hasPopover';
	var $btn_sm_class  = FLEXI_J40GE ? 'btn btn-sm' : 'btn btn-small';
	var $btn_iv_class  = FLEXI_J40GE ? 'btn-dark' : 'btn-inverse';
	var $ina_grp_class = FLEXI_J40GE ? 'input-group' : 'input-append';
	var $inp_grp_class = FLEXI_J40GE ? 'input-group' : 'input-prepend';
	var $select_class  = FLEXI_J40GE ? 'use_select2_lib' : 'use_select2_lib';
	//var $txt_grp_class = FLEXI_J40GE ? 'input-group-text' : 'add-on';

	public function getFilterDisplay($filter)
	{
		$label_extra_class = isset($filter['label_extra_class']) ? $filter['label_extra_class'] : '';
		$label_extra_attrs = isset($filter['label_extra_attrs']) ? ArrayHelper::toString($filter['label_extra_attrs']) : '';

		if (!FLEXI_J40GE)
		{
			$label = $filter['label']
				? '<div class="add-on ' . $label_extra_class .'" ' . $label_extra_attrs .'>' . $filter['label'] . '</div>'
				: '';
			return '
				<div class="fc-filter nowrap_box">
					<div class="input-prepend input-append fc-xpended-row">
						' . $label . '
						' . $filter['html'] . '
					</div>
				</div>
			';
		}
		else
		{
			$label = $filter['label']
				? '<div class="input-group-text ' . $label_extra_class .'" ' . $label_extra_attrs .'>' . $filter['label'] . '</div>'
				: '';
			return '
				<div class="fc-filter nowrap_box">
					<div class="input-group fc-xpended-row">
						<div class="input-group-prepend">
						' . $label . '
							' . $filter['html'] . '
						</div>
					</div>
				</div>
			';
		}
	}


	/**
	 * Method to get the CSS for backend management listings
	 *
	 * @return	int
	 *
	 * @since	3.3.0
	 */
	public function addCssJs()
	{
	}


	/**
	 * Method to add the state changing buttons for setting a new state for multiple records
	 *
	 * @since   3.3.0
	 */
	public function addStateButtons($btn_arr)
	{
		if (count($btn_arr))
		{
			$drop_btn = '
				<button type="button" class="' . $this->btn_sm_class . ' dropdown-toggle" data-toggle="dropdown">
					<span title="'.JText::_('FLEXI_CHANGE_STATE').'" class="icon-menu"></span>
					'.JText::_('FLEXI_CHANGE_STATE').'
					<span class="caret"></span>
				</button>';
			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'action_btns_group', ' ');
		}
	}


	/**
	 * Method to create state changing buttons for setting a new state for multiple records
	 *
	 * @since   3.3.0
	 */
	public function getStateButtons($applicable = null)
	{
		// Use general permissions since we do not have examine any specific item
		$applicable = $applicable ?: array(
			'P' => 0,
			'U' => 0,
			'A' => 0,
			'T' => 0,
		);

		$states['P'] = array('btn_text' =>'FLEXI_PUBLISHED', 'btn_desc' =>'', 'btn_icon' => 'icon-publish', 'btn_class' => '', 'btn_name'=>'publish');
		$states['IP'] = array('btn_text' =>'FLEXI_IN_PROGRESS', 'btn_desc' =>'FLEXI_IN_PROGRESS_SLIDER', 'btn_icon' => 'icon-checkmark-2', 'btn_class' => '', 'btn_name'=>'inprogress');
		$states['U'] = array('btn_text' =>'FLEXI_UNPUBLISHED', 'btn_desc' =>'', 'btn_icon' => 'icon-unpublish', 'btn_class' => '', 'btn_name'=>'unpublish');
		$states['PE'] = array('btn_text' =>'FLEXI_PENDING', 'btn_desc' =>'FLEXI_PENDING_SLIDER', 'btn_icon' => 'icon-question', 'btn_class' => '', 'btn_name'=>'pending');
		$states['OQ'] = array('btn_text' =>'FLEXI_TO_WRITE', 'btn_desc' =>'FLEXI_DRAFT_SLIDER', 'btn_icon' => 'icon-pencil', 'btn_class' => '', 'btn_name'=>'draft');
		$states['A'] = array('btn_text' =>'FLEXI_ARCHIVE', 'btn_desc' =>'', 'btn_icon' => 'icon-archive', 'btn_class' => '_btn-info', 'btn_name'=>'archived');
		$states['T'] = array('btn_text' =>'FLEXI_TRASH', 'btn_desc' =>'', 'btn_icon' => 'icon-trash', 'btn_class' => '_btn-inverse', 'btn_name'=>'trashed');

		$contrl = "items.";
		$btn_arr = array();

		foreach($states as $alias => $s)
		{
			if (isset($applicable[$alias]))
			{
				$btn_text = $s['btn_text'];
				$btn_name = $applicable[$alias] ?: $s['btn_name'];
				$btn_task = '';

				$full_js = "window.parent.fc_parent_form_submit('fc_modal_popup_container', 'adminForm', {'newstate':'" . $alias . "', 'task': '" . $contrl . "changestate'}, {'task':'" . $contrl . "changestate', 'is_list':true});";
				$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
					$btn_text, $btn_name, $full_js,
					$msg_alert = JText::_('FLEXI_NO_ITEMS_SELECTED'), $msg_confirm = JText::_('FLEXI_ARE_YOU_SURE'),
					$btn_task, $extra_js='', $btn_list=true, $btn_menu=true, $btn_confirm=false,
					$s['btn_class'] . ' ' . $this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? 'btn-info' : '') . ' ' . $this->tooltip_class, $s['btn_icon'],
					'data-placement="right" title="' . flexicontent_html::encodeHTML(JText::_($s['btn_desc']), 2) . '"', $auto_add = 0, $tag_type='button'
				);
			}
		}

		return $btn_arr;
	}
}
