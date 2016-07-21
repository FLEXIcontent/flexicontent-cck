<?php
/**
 * @version 1.5 stable $Id: separator.php 1904 2014-05-20 12:21:09Z ggppdk $
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

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

jimport('cms.html.html');      // JHtml
jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('spacer');   // JFormFieldSpacer

/**
 * Renders the flexicontent 'separator' (header) element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldSeparator extends JFormFieldSpacer
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'separator';
	
	static $css_js_added = null;
	static $tab_css_js_added = null;
		
	function add_css_js()
	{
		self::$css_js_added = true;
		$css = "
		div.pane-sliders ul.adminformlist li select { margin-bottom: 0px;}
		div.pane-sliders ul.adminformlist li fieldset  { margin: 0; padding: 0; }
		
		div.current ul.config-option-list li .fcsep_level3 {
			left: 232px !important;
		}
		
		/*div.controls input, div.controls textarea { min-width: 56%; }*/

		*:not(.form-vertical) > div.control-group div.control-label,
		div.current ul.config-option-list li {
			margin: 0 !important;
			padding: 0 !important;
			max-width: 160px;
			min-width: 120px;
			width: 15%;
			border:0;
		}
		
		fieldset.form-vertical div.control-group {
			margin-bottom: 8px;
		}
		*:not(.form-vertical) > div.control-group div.control-label label.hasTooltip,
		div.current ul.config-option-list li label.hasTooltip {
			display:inline-block;
			
			border-bottom: 1px solid #E9E9E9;
			border-right: 1px solid #E9E9E9;
			color: white;
			border-radius: 3px;
			
			margin: 0px 4% 3px 0 !important;
			padding: 8px !important;
			background-color: #999;
			text-align: right;
			white-space: normal;
			font-weight: normal; 
			font-size: 12px;
			width: 96%;
			box-sizing: border-box;
		}
		
		/*div.current fieldset.radio label {
			min-width:10px!important; padding: 0px 16px 0px 0px!important; margin: 2px 0px 0px 1px!important;
		}
		div fieldset.adminform fieldset.radio label, div fieldset.panelform fieldset.radio label {
			min-width:10px!important; padding: 0px 10px 0px 0px!important; margin: 4px 0px 0px 1px!important;
		}*/
		
		/*div fieldset input, div fieldset textarea, div fieldset img, div fieldset button { margin:5px 2px 2px 0px; }*/
		div fieldset select { margin:0px; }
					
		div.current ul.config-option-list li select { margin-bottom: 0px; font-size:12px;}
		div.current ul.config-option-list li fieldset  { margin: 0; padding: 0; }
		
		.tool-tip { }
		.tip-title { }
		";
		
		$document = JFactory::getDocument();
		
		if (FLEXI_J30GE) $jinput = JFactory::getApplication()->input;
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$controller = $jinput->get('controller', '', 'cmd');
		$component  = $jinput->get('component', '', 'cmd');
		
		if ($option!='com_flexicontent') $document->addStyleDeclaration($css);
		
		// NOTE: this is imported by main Frontend/Backend CSS file
		// so import these only if it is not a flexicontent view
		if ($option!='com_flexicontent') {
			$document->addStyleSheetVersion(JURI::root(true).'/components/com_flexicontent/assets/css/flexi_containers.css', FLEXI_VHASH);
			$document->addStyleSheetVersion(JURI::root(true).'/components/com_flexicontent/assets/css/flexi_form.css', FLEXI_VHASH);  // NOTE: this is imported by main Frontend/Backend CSS file
			$document->addStyleSheetVersion(JURI::root(true).'/components/com_flexicontent/assets/css/flexi_shared.css', FLEXI_VHASH);  // NOTE: this is imported by main Frontend/Backend CSS file
			
			// Add flexicontent specific TABBing to non-flexicontent views
			$this->add_tab_css_js();
		}
		
		JHtml::_('behavior.framework', true);
		flexicontent_html::loadJQuery();
		
		// Add js function to overload the joomla submitform validation
		JHTML::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH);
	}


	function add_tab_css_js()
	{
		self::$tab_css_js_added = true;
		$document = JFactory::getDocument();
		
		// Load JS tabber lib
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', FLEXI_VHASH);
		$document->addStyleSheetVersion(JURI::root(true).'/components/com_flexicontent/assets/css/tabber.css', FLEXI_VHASH);
		$document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
	}


	function getLabel() {
		return "";
	}


	function getInput()
	{
		static $tabset_id = 0;
		static $tab_id;

		if (self::$css_js_added===null)
		{
			$this->add_css_js();
		}
		
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		$value = $this->element['default'];
		$description = @$attributes['description'];

		$level = @$attributes['level'];
		$classes = @$attributes['class'];
		$style = @$attributes['style'];
		
		$tab_class = @$attributes['tab_class'] ? $attributes['tab_class'] : 's-gray';
		
		if (self::$tab_css_js_added===null && $level=='tabset_start')
		{
			$this->add_tab_css_js();
		}

		$is_level = substr($level, 0, 5)=='level';
		$classes .= $is_level ? ' fcsep_'.$level : '';

		$tip = '';
		$title = JText::_($value);
		$desc = JText::_($description);
		if ($desc)
		{
			$classes .= ' hasTooltip';
			$tip = 'title="'.flexicontent_html::getToolTip($title, $desc, 0, 1).'"';
		}
		$icon_class = @$attributes['icon_class'];
		
		$box_count = (int) @ $attributes['remove_boxes'];
		$_bof = $box_count ? ($box_count == 2 ? '</div></div>' : str_repeat("</div>", $box_count)) : '';
		$_eof = $box_count ? ($box_count == 2 ? '<div><div>'   : str_repeat("<div>",  $box_count)) : '';

		$box_type = @$attributes['box_type'];
		if (!$is_level) switch ($level)
		{
		case 'tabset_start':
			$tab_id = 0;
			if ($box_type==2)
				return $_bof . JHtml::_('tabs.start','core-tabs-cat-props-'.($tabset_id++), array('useCookie'=>1)) . $_eof;
			else
				return $_bof . "\n". '<div class="fctabber '.$tab_class.'" id="tabset_attrs_'.($tabset_id++).'">' . $_eof;
			break;

		case 'tabset_close':
			if ($box_type==2)
				return $_bof . JHtml::_('tabs.end') . $_eof;
			else
			return $_bof . '
				</div>
			</div>' . $_eof;
			break;

		case 'tab_open':
			if ($box_type==2)
				return $_bof . JHtml::_('tabs.panel', $title, 'tab_attrs_'.$tabset_id.'_'.($tab_id++)) . $_eof;
			return $_bof . ($tab_id > 0 ? '
				</div>' : '').'
				<div class="tabbertab" id="tab_attrs_'.$tabset_id.'_'.($tab_id++).'" data-icon-class="'.$icon_class.'" >
					<h3 class="tabberheading '.$classes.'" '.$tip.'>'.$title.'</h3>
				' . $_eof;
			break;

		default:
			// Will be handled after switch
			break;
		}

		return $_bof . '<div style="'.$style.'" class="'.$classes.'" '.$tip.' >'.$title.'</div><div class="fcclear"></div>' . $_eof;
	}
}
