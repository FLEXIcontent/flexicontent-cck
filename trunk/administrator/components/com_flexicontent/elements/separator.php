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

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

/**
 * Renders a fields element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
$css="
div table td.paramlist_value {
	padding-left:12px;
}
div .paramlist_value label {
	min-width:10px!important; padding: 0px 10px 0px 0px!important; margin: 4px 0px 0px 1px!important;
}
div .paramlist_value input, div .paramlist_value textarea, div .paramlist_value img, div .paramlist_value button { margin:5px 0px 2px 0px; }
div .paramlist_value select { margin:0px; }

.tool-tip { min-width: 480px !important; }
.tool-title { }

";

$document = JFactory::getDocument();
$document->addStyleDeclaration($css);
//$document->addStyleSheet('../tmpl/params.css');

class JElementSeparator extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'separator';
	
	function fetchElement($name, $value, &$node, $control_name)
	{
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		$level = $attributes['level'];
		$description = @$attributes['description'];
		
		if (FLEXI_J16GE && in_array($level, array('tblbreak','tabs_start','tab_open','tab_close','tabs_end')) ) return 'do no use type "'.$level.'" in J1.6+';
		
		static $tab_js_css_added = false;
		static $initial_tbl_hidden = false;
		
		if (!$tab_js_css_added && in_array($level, array('tblbreak','tabs_start','tab_open','tab_close','tabs_end')) ) {
			$tab_js_css_added = true;
			$document = JFactory::getDocument();
			$document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/tabber-minimized.js');
			$document->addStyleSheet(JURI::root().'administrator/components/com_flexicontent/assets/css/tabber.css');
			$document->addStyleDeclaration(".fctabber{display:none;}");   // temporarily hide the tabbers until javascript runs, then the class will be changed to tabberlive
		}
		
		
		
		if ($level == 'tblbreak') {
			$style = 'padding: 4px 2% 4px 2%; display: block; background-color: #ffffff; color: darkred; font-size: 16px!important; font-weight: bold; margin: 24px 0% 2px 0%; width:auto; display: block; float: left; border: 1px solid lightgray; font-family:tahoma; font-size:12px;';
		} else if ($level == 'level2') {
			$style = 'padding: 2px 0% 2px 4%; display: block; background-color: #ccc; color: #000; font-weight: bold; margin: 0px 2% 2px 2%; width:92%; display: block; float: left; text-align: center; border: 1px outset #E9E9E9;';
		} else if ($level == 'level3') {
			$pad_left = FLEXI_J16GE ? 'left:20%;' : 'left:0%;';
			$width_val = FLEXI_J16GE ? 'width:64%;' : 'width:auto;';
			$style = 'padding: 2px 2% 4px 6%; margin-top:6px; font-weight: bold; clear:both; '.$width_val.' display: block; float: left; position:relative; '.$pad_left.' border:1px dashed gray; background:#eeeeee;';
		} else if ($level == 'level1') {
			$style = 'padding: 4px 2% 4px 2%; display: block; background-color: #333333; color: #fff; font-weight: bold; margin: 2px 0% 2px 0%; width:96%; display: block; float: left; border: 1px outset #E9E9E9; font-family:tahoma; font-size:12px;';
		} else {
			$style = '';
		}
		
		$class = ""; $title = "";
		if ($description) {
			$class = "hasTip";
			$title = JText::_($value)."::".JText::_($description);
		}
		
		if ($level == 'tabs_start') {
			$html = '';
			if (!$initial_tbl_hidden) {
				$initial_tbl_hidden = true;
				$html = '<style> table.paramlist.admintable {display:none;} table.paramlist.admintable.flexi {display:table;} div.tabberlive {margin-top:0px;}</style>';
			}
			return $html.'
			</td></tr>
			</table>
			<div class="fctabber">
				<div class="tabbertab">
					<h3 class="tabberheading">'.str_replace('&', ' - ', JText::_($value)).'</h3>
					<table width="100%" cellspacing="1" class="paramlist admintable flexi">
					<tr><td>
			';
		} else if ($level == 'tab_close_open') {
			return '
					</td></tr>
					</table>
				</div>
				<div class="tabbertab">
					<h3 class="tabberheading">'.str_replace('&', ' - ', JText::_($value)).'</h3>
					<table width="100%" cellspacing="1" class="paramlist admintable flexi">
					<tr><td>
				';
		} else if ($level == 'tabs_end') {
			return'
					</td></tr>
					</table>
				</div>
			</div>
				<table width="100%" cellspacing="1" class="paramlist admintable flexi">
				<tr><td>
			';
		} else if ($level == 'tblbreak') {
			return '
			</td></tr>
			</table>
			'
			.'<span style="'.$style.'" class="'.$class.'" title="'.$title.'" >'.JText::_($value).'</span>'.
			'
			<table width="100%" cellspacing="1" class="paramlist admintable flexi">
			<tr><td>
			';
		}
		return '<span style="'.$style.'" class="'.$class.'" title="'.$title.'" >'.JText::_($value).'</span>';
	}
}