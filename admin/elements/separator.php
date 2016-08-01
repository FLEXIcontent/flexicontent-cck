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

		$document = JFactory::getDocument();
		$jinput = JFactory::getApplication()->input;
		$option = $jinput->get('option', '', 'cmd');

		// NOTE: this is imported by main Frontend/Backend CSS file, so import these only if it is not a flexicontent view
		if ($option!='com_flexicontent')
		{
			$css = "";
			if ($css) $document->addStyleDeclaration($css);

			JFactory::getApplication()->isSite() ?
				$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH) :
				$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH);
			$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH);

			// Add flexicontent specific TABBing to non-flexicontent views
			$this->add_tab_css_js();
		}

		JHtml::_('behavior.framework', true);
		flexicontent_html::loadJQuery();

		// Add js function to overload the joomla submitform validation
		JHTML::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
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


	function getLabel()
	{
		return '';
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
		$value_printf = @$attributes['value_printf'];

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
		if (!$value_printf)
		{
			$title = JText::_($value);
		}
		else
		{
			$vparts = preg_split("/\s*,\s*/", $value_printf);
			foreach($vparts as & $vpart)
			{
				$vpart = JText::_($vpart);
			}
			unset($vpart);
			array_unshift($vparts, $value);
			$title = call_user_func_array(array('JText', 'sprintf'), $vparts);
		}
		$desc = JText::_($description);
		if ($desc)
		{
			$classes .= ' hasTooltip';
			$tip = 'title="'.flexicontent_html::getToolTip($title, $desc, 0, 1).'"';
		}
		$icon_class = @$attributes['icon_class'];
		
		$box_count = (int) @ $attributes['remove_boxes'];
		$_bof = $box_count ? ($box_count == 2 ? '</div></div>' : str_repeat("</div>", $box_count)) : '';
		$_eof = $box_count ? ($box_count == 2 ? '<div class="fc_empty_box"><div>'   : str_repeat("<div>",  $box_count)) : '';

		$box_type = @$attributes['box_type'];
		if (!$is_level) switch ($level)
		{
		case 'hidden_field':
			return '<input type="text" id="'. @$attributes['hidden_field_id'].'" name="'. @$attributes['hidden_field_id'].'" value="1" class="fc_hidden_value" />';
			break;
		case 'tabset_start':
			$tab_id = 0;
			if ($box_type==2)
				return $_bof . JHtml::_('tabs.start','core-tabs-cat-props-'.($tabset_id++), array('useCookie'=>1)) . $_eof;
			else
				return $_bof . "\n". '<div class="fctabber '.$tab_class.' '.$classes.'" id="tabset_attrs_'.($tabset_id++).'">' . $_eof;
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
