<?php
/**
 * @version 1.5 beta 5 $Id: separator.php 567 2011-04-13 11:06:52Z emmanuel.danan@gmail.com $
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
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('spacer');

$css="
div.pane-sliders ul.adminformlist li label.hasTip {
	display:inline-block; padding: 4px; margin: 1px 6px 0px 1px; text-align: right;	width:132px; font-weight: bold;
	background-color: #F6F6F6; border-bottom: 1px solid #E9E9E9; border-right: 1px solid #E9E9E9; color: #666666;
}
div.pane-sliders ul.adminformlist li ul#rules label.hasTip {
	display:inherit; padding: inherit; margin: inherit; text-align: inherit;	width: inherit; font-weight: inherit;
	background-color: inherit; border-width: 0px; color: inherit;
}
div.pane-sliders ul.adminformlist li select { margin-bottom: 0px;}
div.pane-sliders ul.adminformlist li fieldset  { margin: 0; padding: 0; }

div.current ul.config-option-list li label.hasTip {
	display:inline-block; padding: 4px; margin: 1px 6px 0px 1px; text-align: right;	width:220px; font-weight: bold;
	background-color: #F6F6F6; border-bottom: 1px solid #E9E9E9; border-right: 1px solid #E9E9E9; color: #666666;
}
div.current ul.config-option-list li ul#rules label.hasTip {
	display:inherit; padding: inherit; margin: inherit; text-align: inherit;	width: inherit; font-weight: inherit;
	background-color: inherit; border-width: 0px; color: inherit;
}
form#item-form div.pane-sliders ul.adminformlist li label.hasTip {
	display:inline-block; padding: 4px; margin: 1px 6px 0px 1px; text-align: right;	width:160px; font-weight: bold;
	background-color: #F6F6F6; border-bottom: 1px solid #E9E9E9; border-right: 1px solid #E9E9E9; color: #666666;
}
div fieldset.adminform fieldset.radio label, div fieldset.panelform fieldset.radio label {
	min-width:10px; padding: 0px 10px 0px 0px; margin: 5px 0px 0px 0px;
}
div fieldset input, div fieldset textarea, div fieldset img, div fieldset button { margin:5px 2px 2px 0px; }
div fieldset select { margin:0px; }
			
div.current ul.config-option-list li select { margin-bottom: 0px;}
div.current ul.config-option-list li fieldset  { margin: 0; padding: 0; }
";

$document = & JFactory::getDocument();
$document->addStyleDeclaration($css);
//$document->addStyleSheet('../tmpl/params.css');

class JFormFieldSeparator extends JFormFieldSpacer
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'separator';
	
	function getLabel()
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
		$value = $this->element['default'];
		
		if (FLEXI_J16GE && in_array($level, array('tblbreak','tabs_start','tab_open','tab_close','tabs_end')) ) return 'do no use type "'.$level.'" in J1.6+';
		
		static $tab_js_css_added = false;
		static $initial_tbl_hidden = false;
		
		if (!$tab_js_css_added && in_array($level, array('tblbreak','tabs_start','tab_open','tab_close','tabs_end')) ) {
			$tab_js_css_added = true;
			$document = & JFactory::getDocument();
			$document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/tabber-minimized.js');
			$document->addStyleSheet(JURI::root().'administrator/components/com_flexicontent/assets/css/tabber.css');
			$document->addStyleDeclaration(".fctabber{display:none;}");   // temporarily hide the tabbers until javascript runs, then the class will be changed to tabberlive
		}
		
		
		
		if ($level == 'tblbreak') {
			$style = 'padding: 4px 2% 4px 2%; display: block; background-color: #ffffff; color: darkred; font-size: 16px!important; font-weight: bold; margin: 24px 0% 2px 0%; width:auto; display: block; float: left; border: 1px solid lightgray; font-family:tahoma; font-size:12px;';
		} else if ($level == 'level2') {
			$style = 'padding: 2px 0% 2px 4%; display: block; background-color: #ccc; color: #000; font-weight: bold; margin: 0px 2% 2px 6%; width:84%; display: block; float: left; text-align: center; border: 1px outset #E9E9E9;';
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
					<h3>'.str_replace('&', ' - ', JText::_($value)).'</h3>
					<table width="100%" cellspacing="1" class="paramlist admintable flexi">
					<tr><td>
			';
		} else if ($level == 'tab_close_open') {
			return '
					</td></tr>
					</table>
				</div>
				<div class="tabbertab">
					<h3>'.str_replace('&', ' - ', JText::_($value)).'</h3>
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
