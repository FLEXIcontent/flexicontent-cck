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

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

jimport('legacy.view.legacy');

/**
 * HTML View class for backend record screens (Base)
 */
class FlexicontentViewBaseRecord extends JViewLegacy
{
	var $tooltip_class = FLEXI_J40GE ? 'hasTooltip' : 'hasTooltip';
	var $popover_class = FLEXI_J40GE ? 'hasPopover' : 'hasPopover';
	var $btn_sm_class  = FLEXI_J40GE ? 'btn btn-sm' : 'btn btn-small';
	var $btn_iv_class  = FLEXI_J40GE ? 'btn-dark' : 'btn-inverse';
	var $ina_grp_class = FLEXI_J40GE ? 'input-group' : 'input-append';
	var $inp_grp_class = FLEXI_J40GE ? 'input-group' : 'input-prepend';
	var $select_class  = FLEXI_J40GE ? 'use_select2_lib' : 'use_select2_lib';
	//var $txt_grp_class = FLEXI_J40GE ? 'input-group-text' : 'add-on';


	/**
	 * Method to get the CSS for backend record screens (typically edit forms)
	 *
	 * @return	int
	 *
	 * @since	3.3.0
	 */
	public function addCssJs()
	{
	}


	/**
	 * Method to get the display of field while showing the inherited value
	 *
	 * @return	int
	 *
	 * @since	3.3.0
	 */
	public function getFieldInheritedDisplay($field, $params)
	{
		return flexicontent_html::getInheritedFieldDisplay($field, $params);
	}
}
