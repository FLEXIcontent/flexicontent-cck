<?php
/**
 * @version 1.5 stable $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;;

jimport('legacy.view.legacy');

/**
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
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
}