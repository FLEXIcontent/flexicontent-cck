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
	var	$type = 'separator';
	
	static $css_js_added = null;
	static $tab_css_js_added = null;
		
	function add_css_js()
	{
		self::$css_js_added = true;

		$app = JFactory::getApplication();
		$document = JFactory::getDocument();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$view = $jinput->get('view', '', 'cmd');
		$component = $jinput->get('component', '', 'cmd');

		// NOTE: this is imported by main Frontend/Backend CSS file, so import these only if it is not a flexicontent view
		if ($option!='com_flexicontent')
		{
			$isAdmin = $app->isClient('administrator');

			if (!JFactory::getLanguage()->isRtl())
			{
				!$isAdmin ?
					$document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH)) :
					$document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH));
			}
			else
			{
				!$isAdmin
					? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
			}

			!JFactory::getLanguage()->isRtl()
				? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));

			// Add flexicontent specific TABBing to non-flexicontent views
			$this->add_tab_css_js();
		}

		flexicontent_html::loadJQuery();

		// Add js function to overload the joomla submitform validation
		JHtml::_('behavior.formvalidator');  // load default validation JS to make sure it is overriden
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));

		if ($option=='com_config' && $view=='component' && $component=='com_flexicontent')
		{
			$this->add_comp_acl_headers();
		}
	}


	function add_tab_css_js()
	{
		self::$tab_css_js_added = true;
		$document = JFactory::getDocument();
		
		// Load JS tabber lib
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
		$document->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
		$document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
	}


	function add_comp_acl_headers()
	{
		JFactory::getDocument()->addScriptDeclaration('
			jQuery(document).ready(function()
			{
				jQuery("div.control-group > div").each(function(i, el) {
					if ( jQuery(el).html().trim() == "" && ( jQuery(el).attr("class") == "control-label" || jQuery(el).attr("class") == "controls" ))
					{
						jQuery(el).remove();
					}
				});
				jQuery("div.control-group").each(function(i, el) {
					if (jQuery(el).html().trim() == "")
					{
						jQuery(el).remove();
					}
				});

				var tr1  = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(1)");
				var tr4  = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(4)");
				var tr11 = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(11)");
				var tr15 = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(15)");
				var tr18 = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(18)");
				var tr23 = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(23)");
				var tr28 = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(28)");
				var tr30 = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(30)");
				var tr37 = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(37)");
				var tr41 = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(41)");
				var tr45 = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(45)");
				var tr47 = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(47)");
				var tr48 = jQuery("#permissions-sliders .tab-content .tab-pane tbody tr:nth-child(48)");
				
				tr1.before("<tr><td colspan=\"3\"><span class=\"fcsep_level2\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_COMPONENT_ACCESS_TITLE').'<\/td><\/tr>");
				tr4.before("<tr><td colspan=\"3\"><span class=\"fcsep_level2\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_ITEMS_CATEGORIES_TITLE').'<\/td><\/tr>");
				tr11.before("<tr><td colspan=\"3\"><span class=\"fcsep_level2\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_ITEM_FORM_TITLE').'<\/td><\/tr>");
				tr11.before("<tr><td colspan=\"3\"><span class=\"fcsep_level3\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_CATEGORY_TAGS_USAGE_TITLE').'<\/td><\/tr>");
				tr15.before("<tr><td colspan=\"3\"><span class=\"fcsep_level3 alert alert-info fcpadded\" style=\"margin-left: 10% !important;\"><b>'.JText::_('FLEXI_PERMISSIONS_COMPONENT_EXISTING_ITEMS_TITLE').'<\/b>:  ('.JText::_('FLEXI_PERMISSIONS_COMPONENT_OVERRIDABLE_IN_TYPE_TITLE').')<\/td><\/tr>");
				tr18.before("<tr><td colspan=\"3\"><span class=\"fcsep_level3\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_VARIOUS_TITLE').'<\/td><\/tr>");
				tr23.before("<tr><td colspan=\"3\"><span class=\"fcsep_level2\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_WORKFLOW_TITLE').'<\/td><\/tr>");
				tr28.before("<tr><td colspan=\"3\"><span class=\"fcsep_level2\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_ITEMS_MANAGER_TITLE').'<\/td><\/tr>");
				tr30.before("<tr><td colspan=\"3\"><span class=\"fcsep_level2\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_BACKEND_MANAGER_ACCESS_TITLE').'<\/td><\/tr>");
				tr37.before("<tr><td colspan=\"3\"><span class=\"fcsep_level2\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_FIELDS_MANAGER_TITLE').'<\/td><\/tr>");
				tr41.before("<tr><td colspan=\"3\"><span class=\"fcsep_level3 alert alert-info fcpadded\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_OVERRIDABLE_IN_FIELD_TITLE').'<\/td><\/tr>");
				tr45.before("<tr><td colspan=\"3\"><span class=\"fcsep_level2\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_REVIEWS_MANAGER_TITLE').'<\/td><\/tr>");
				tr47.before("<tr><td colspan=\"3\"><span class=\"fcsep_level2\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_FILES_MANAGER_TITLE').'<\/td><\/tr>");
				tr48.before("<tr><td colspan=\"3\"><span class=\"fcsep_level3 alert alert-info fcpadded\">'.JText::_('FLEXI_PERMISSIONS_COMPONENT_ALSO_USED_IN_ITEM_FORM_TITLE').'<\/td><\/tr>");

			});
		');
	}


	function getLabel()
	{
		return '';
	}


	function getInput()
	{
		static $tabset_stack = array();
		static $tab_stack = array();

		static $tabset_next_id = -1;
		static $tabset_id;
		static $tab_id;

		static $is_fc = null;

		if (self::$css_js_added===null)
		{
			$this->add_css_js();
			
			$jinput = JFactory::getApplication()->input;
			$is_fc = $jinput->get('option', '', 'cmd') == 'com_flexicontent';
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

		$is_level = substr($level, 0, 5) == 'level';
		$levelNum = '';

		if ($is_level)
		{
			$levelNum = (int) str_replace('level', '', $level);
			$classes .= ' fcsep_' . $level;
		}

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
			$tip = ' data-placement="top" data-title="'.flexicontent_html::getToolTip($title, $desc, 0, 1).'" ';
		}

		$icon_class = @$attributes['icon_class'];

		$box_count = $this->element['remove_boxes'] ?? '';
		$box_count = strlen($box_count) ? (int) $box_count : ($is_fc ? 0 : 2);
		$_bof = $box_count ? ($box_count == 2 ? '</div></div>' : str_repeat("</div>", $box_count)) : '';
		$_eof = $box_count ? ($box_count == 2
			? '<div class="fc_empty_box"><div>'
			: str_repeat("<div>",  $box_count)
		) : '';

		$box_type = @$attributes['box_type'];
		if (!$is_level) switch ($level)
		{
		case 'hidden_field':
			return '<input type="text" id="'. @$attributes['hidden_field_id'].'" name="'. @$attributes['hidden_field_id'].'" value="1" class="fc_hidden_value" />';
			break;

		case 'tabset_start':
			array_push($tabset_stack, $tabset_id);
			array_push($tab_stack, $tab_id);
			$tabset_id = ++$tabset_next_id;

			$tab_id = 0;
			return $box_type==2
				? $_bof . JHtml::_('tabs.start','core-tabs-cat-props-' . $tabset_id, array('useCookie'=>1)) . $_eof
				: $_bof . "\n". '<div class="fctabber '.$tab_class.' '.$classes.'" id="tabset_attrs_' . $tabset_id . '">' . $_eof;
			break;

		case 'tabset_close':
			$tabset_id = array_pop($tabset_stack);
			$tab_id    = array_pop($tab_stack);

			return $box_type==2
				? $_bof . JHtml::_('tabs.end') . $_eof
				: $_bof . '
					</div>
				</div>
				' . $_eof;
			break;

		case 'tab_open':
			if ($box_type==2)
			{
				return $_bof . JHtml::_('tabs.panel', $title, 'tab_attrs_'.$tabset_id.'_'.($tab_id++)) . $_eof;
			}
			else
			{
				return $_bof . '
					' . ($tab_id > 0 ? '</div>' : '') . '
					<div class="tabbertab" id="tab_attrs_'.$tabset_id.'_'.($tab_id++).'" data-icon-class="'.$icon_class.'" >
						<h3 class="tabberheading ' . $classes . '" ' . $tip . '>' . $title . '</h3>
					' . $_eof;
			}
			break;

		default:
			// Will be handled after switch
			break;
		}

		if (strlen($levelNum))
		{
			return $_bof . '
				<h' . ($levelNum + 2) . ' style="'.$style.'" class="'.$classes.'" '.$tip.' >
					' . $title . '
				</h' . ($levelNum + 2) . '>
				<div class="fcclear"></div>
				' . $_eof;
		}
		else
		{
			return $_bof . '
				<div style="'.$style.'" class="'.$classes.'" '.$tip.' >
					' . $title . '
				</div>
				<div class="fcclear"></div>
				' . $_eof;
		}
	}
}
