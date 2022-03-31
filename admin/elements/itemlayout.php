<?php
/**
 * @version 1.5 stable $Id: itemlayout.php 967 2011-11-21 00:01:36Z ggppdk $
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

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('list');   // JFormFieldList

// Load JS tabber lib
JFactory::getDocument()->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
JFactory::getDocument()->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
JFactory::getDocument()->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

/**
 * Renders an itemlayout element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.0
 */
class JFormFieldItemlayout extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'Itemlayout';

	protected function getInput()
	{
		// Element params
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		$themes	= flexicontent_tmpl::getTemplates();
		$tmpls_all	= $themes->items ? $themes->items : array();
		$value = $this->value;
		//$value = $value ? $value : @$attributes['default'];
		
		// Get current extension and id being edited
		$app    = JFactory::getApplication();
		$db     = JFactory::getDbo();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'CMD');
		$view   = $jinput->get('view', '', 'CMD');

		$cparams = JComponentHelper::getParams('com_flexicontent');

		// Get RECORD id of current view
		$id = $jinput->get('id', array(0), 'array');
		$id = ArrayHelper::toInteger($id, array(0));
		$pk = (int) $id[0];
		
		if (!$pk)
		{
			$cid = $jinput->get('cid', array(0), 'array');
			$cid = ArrayHelper::toInteger($cid, array(0));
			$pk = (int) $cid[0];
		}
		
		// GET LIMITING to specific templates according to item's type, or according to type of new item
		$allowed_tmpls = array();
		$all_tmpl_allowed = true;
		$type_default_layout = '';
		$type_default_layout_mobile = '';
		
		if ($view === 'item')
		{
			// Get typeid from URL
			$typeid = $jinput->get('typeid', 0, 'int');
			
			// Get type attibutes
			$query = false;
			if ($pk)
			{
				$type_attribs = flexicontent_db::getTypeAttribs(false, 0, $pk);
			}
			elseif ($typeid)
			{
				$type_attribs = flexicontent_db::getTypeAttribs(false, $typeid, 0);
			}

			// Finally get allowed templates
			if (!empty($type_attribs))
			{
				$tparams = new JRegistry($type_attribs);
				$type_default_layout = $tparams->get('ilayout', 'grid');
				$type_default_layout_mobile = $tparams->get('ilayout_mobile', JText::_('FLEXI_USE_DESKTOP'));
				$allowed_tmpls = $tparams->get('allowed_ilayouts');

				if (empty($allowed_tmpls))
				{
					$allowed_tmpls = array();
				}
				elseif (!is_array($allowed_tmpls))
				{
					$allowed_tmpls = explode('|', $allowed_tmpls);
				}

				$all_tmpl_allowed = count($allowed_tmpls) == 0;
				if (!in_array($type_default_layout, $allowed_tmpls))
				{
					$allowed_tmpls[] = $type_default_layout;
				}

				$use_mobile_layouts = $cparams->get('use_mobile_layouts', 0 );

				if ($use_mobile_layouts && $type_default_layout_mobile)
				{
					if ( !in_array( $type_default_layout_mobile, $allowed_tmpls ) ) $allowed_tmpls[] = $type_default_layout_mobile;
				}
				//echo "Allowed Templates: "; print_r($allowed_tmpls); echo "<br>\n";
			}
		}
		
		$tmpls = array();
		$lays = array();
		foreach ($tmpls_all as $tmpl)
		{
			if ($all_tmpl_allowed || in_array($tmpl->name, $allowed_tmpls))
			{
				$tmpls[] = $tmpl;
				$lays[] = $tmpl->name;
			}
		}
		$lays = implode("','", $lays);
		
		if (@$attributes['enableparam'])
		{
			if (!$cparams->get($attributes['enableparam']))
			{
				return '';
			}
		}
		
if (!@$attributes['skipparams'])
{
		$ext_option = 'com_flexicontent';
		$ext_view   = $view;
		$doc 	= JFactory::getDocument();
		$js 	= "
var ilayout_names = ['".$lays."'];

function ilayout_disablePanel(element)
{
	var panel_header_id = element+'-attribs-options',
		panel_id = panel_header_id + '-panel';
	
	var el,
		panel_header = jQuery('#'+panel_header_id),
		panel = panel_header.next();
	
	if (!panel.length)
	{
		panel_id = panel_header_id;
		panel_header_id = '';

		panel = jQuery('#'+panel_id),
		panel_header = panel.prev();

		if (!panel.length)
		{
			return;
		}
	}

	if (panel.parent().hasClass('pane-disabled'))
	{
		return;
	}

	var form_fields_active = panel.find('textarea:enabled, select:enabled, input[type=\"radio\"]:enabled:checked, input[type=\"checkbox\"]:enabled:checked, input:not(:button):not(:radio):not(:checkbox):enabled');
	form_fields_active.each(function(index)
	{
		el = jQuery(this);
		//if ( el.is(':disabled') ) return;  // no need, because above we selected only enabled elements
		el.addClass('fclayout_disabled_element');
		el.attr('disabled', 'disabled');
	});
	
	var panel_header_link = panel.prev().find('a');

	panel.parent().addClass('pane-disabled').hide();
}

function ilayout_loadPanel(element)
{
	var panel_header_id = element+'-attribs-options',
		panel_id = panel_header_id + '-panel';
	
	var el,
		panel_header = jQuery('#'+panel_header_id),
		panel = panel_header.next();
	
	if (!panel.length)
	{
		panel_id = panel_header_id;
		panel_header_id = '';

		panel = jQuery('#'+panel_id),
		panel_header = panel.prev();

		if (!panel.length)
		{
			return;
		}
	}

	var panel_header_link = panel_header.find('a');
	if (!panel.attr('id'))
	{
		panel.attr('id', panel_id);
	}

	if (panel.closest('.fc_preloaded').length)
	{
		//window.console.log('Found preloaded panel, using it: ' + panel_id);

		panel.closest('.fc_preloaded').removeClass('fc_preloaded');
	 	setTimeout(function(){
			if (panel_header_link.hasClass('collapsed') || panel_header.hasClass('pane-toggler'))
			{
				//window.console.log('clicking to open: ' + panel.attr('id'));
				// This does not work inside non-focus TAB, and causes options to stay closed, so we disabled auto-opening the slider ...
				//panel_header_link.hasClass('collapsed') ? panel_header_link.trigger('click') : panel_header.trigger('click');
			}
		}, 300);
		return;
	}

	// Add LOADING animation into the panel header, and show outer box that contains the panel header and the panel
	var _loading_img = '<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" style=\"vertical-align: middle;\">';
	panel_header_link.html('<span><span class=\"btn\"><i class=\"icon-edit\"><\/i>'+(panel.hasClass('fc_layout_loaded') ? '".JText::_( 'FLEXI_REFRESHING' )."' : '".JText::_( 'FLEXI_LOADING' )."')+' ... '+_loading_img+'<\/span><\/span>');
	panel.parent().removeClass('pane-disabled').show();

	//window.console.log('Server call to load panel : ' + element);

	// Re-enabled an already loaded panel, (avoid re-downloading which will cause modified parameters to be lost)
	if (panel.hasClass('fc_layout_loaded'))
	{
	 	panel.find('.fclayout_disabled_element').removeAttr('disabled').removeClass('fclayout_disabled_element');
	 	setTimeout(function(){
			panel_header_link.html('<span><span class=\"btn\"><i class=\"icon-edit\"><\/i>".JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ).": '+element+'<\/span><\/span>');

			if (panel_header_link.hasClass('collapsed') || panel_header.hasClass('pane-toggler'))
			{
				//window.console.log('clicking to open: ' + panel.attr('id'));
				panel_header_link.hasClass('collapsed') ? panel_header_link.trigger('click') : panel_header.trigger('click');
			}
		}, 300);
	}
	
	// (AJAX) Retrieve layout parameters for the selected template
	else
	{
		jQuery.ajax({
			type: 'GET',
			url: 'index.php?option=com_flexicontent&task=templates.getlayoutparams&ext_view=".$ext_view."&ext_option=".$ext_option."&ext_name='+element+'&ext_id=".$pk."&layout_name=item&ext_type=templates&directory='+element+'&format=raw&" . JSession::getFormToken() . "=1',
			success: function(str)
			{
				panel.addClass('fc_layout_loaded');

				// Initialize JS and CSS of the layout
				fc_initDynamicLayoutJsCss(panel_id, ['subform-row-add'], str);

				panel_header_link.html('<span><span class=\"btn\"><i class=\"icon-edit\"><\/i>".JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ).": '+element+'<\/span><\/span>');

				if (panel_header_link.hasClass('collapsed') || panel_header.hasClass('pane-toggler'))
				{
					//window.console.log('clicking to open: ' + panel.attr('id'));
					panel_header_link.hasClass('collapsed') ? panel_header_link.trigger('click') : panel_header.trigger('click');
				}
			}
		});
	}
}


function ilayout_activatePanel(active_layout_name)
{
	var inactives = jQuery.grep(ilayout_names, function( layout_name, index )
	{
		return layout_name != active_layout_name;
	});

	for (var i = 0; i < inactives.length; i++)
	{
		ilayout_disablePanel(inactives[i]);
	}

	if (active_layout_name)
	{
		ilayout_loadPanel(active_layout_name);
		jQuery('#__content_type_default_layout__').hide();
	}
	else
	{
		jQuery('#__content_type_default_layout__').show();
	}
}


jQuery(document).ready(function() {
	ilayout_activatePanel('".$value."');
});
";
		$doc->addScriptDeclaration($js);
}
		
		$layouts = array();

		if (@$attributes['firstoption'])
		{
			$layouts[] = JHtmlSelect::option('', JText::_($attributes['firstoption']));
		}
		elseif ($view !== 'type')
		{
			$type_layout = ($attributes['name'] == 'ilayout_mobile') ? $type_default_layout_mobile : $type_default_layout;
			$layouts[] = JHtmlSelect::option('', JText::_( 'FLEXI_TYPE_DEFAULT' ) .' :: '. $type_layout .' ::' );
		}

		foreach ($tmpls as $tmpl)
		{
			$layouts[] = JHtmlSelect::option($tmpl->name, $tmpl->name);
		}
		
		$fieldname	= $this->name;
		$element_id = $this->id;
		
		$attribs = '';
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="multiple" ';
			$attribs .= (@$attributes['size']) ? ' size="'.@$attributes['size'].'" ' : ' size="6" ';
		}
		if (@$attributes['class']) {
			$attribs .= 'class="'.$attributes['class'].'"';
		}
		
		if ( ! @$attributes['skipparams'] )
		{
			$attribs .= ' onchange="ilayout_activatePanel(this.value);"';
		}
		
		if ($inline_tip = @$attributes['inline_tip'])
		{
			$tip_img = @$attributes['tip_img'];
			$tip_img = $tip_img ? $tip_img : 'comments.png';
			$preview_img = @$attributes['preview_img'];
			$preview_img = $preview_img ? $preview_img : '';
			$tip_class = @$attributes['tip_class'];
			$tip_class .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$hintmage = JHtml::image ( 'components/com_flexicontent/assets/images/'.$tip_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin-left:12px; margin-right:0px;" ' );
			$previewimage = $preview_img ? JHtml::image ( 'components/com_flexicontent/assets/images/'.$preview_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin:0px;" ' ) : '';
			$tip_text = '<span class="'.$tip_class.'" style="" title="'.flexicontent_html::getToolTip(null, $inline_tip, 1, 1).'">'.$hintmage.$previewimage.'</span>';
		}
		if ($inline_tip = @$attributes['inline_tip2'])
		{
			$tip_img = @$attributes['tip_img2'];
			$tip_img = $tip_img ? $tip_img : 'comments.png';
			$preview_img = @$attributes['preview_img2'];
			$preview_img = $preview_img ? $preview_img : '';
			$tip_class = @$attributes['tip_class2'];
			$tip_class .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$hintmage = JHtml::image ( 'administrator/components/com_flexicontent/assets/images/'.$tip_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin-left:12px; margin-right:0px;" ' );
			$previewimage = $preview_img ? JHtml::image ( 'administrator/components/com_flexicontent/assets/images/'.$preview_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin:0px;" ' ) : '';
			$tip_text2 = '<span class="'.$tip_class.'" style="" title="'.flexicontent_html::getToolTip(null, $inline_tip, 1, 1).'">'.$hintmage.$previewimage.'</span>';
		}
		return
			JHtml::_('select.genericlist', $layouts, $fieldname, $attribs, 'value', 'text', $value, $element_id)
			. @ $tip_text . @ $tip_text2;
	}
	
	
	function getLabel()
	{
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		if ( @$attributes['enableparam'] ) {
			if ( !JComponentHelper::getParams('com_flexicontent')->get($attributes['enableparam']) ) return '';
		}
		
		$label = $this->element['label'];
		
		$class = (FLEXI_J30GE ? ' hasTooltip' : ' hasTip');
		if ( @$attributes['labelclass'] ) {
			$class .= ' '.$attributes['labelclass'];
		}

		$title = (string) $this->element['description']
			? flexicontent_html::getToolTip($label, (string) $this->element['description'], 1, 1)
			: '...';

		return '<label style=""  class="'.$class.'" title="'.$title.'" >'.JText::_($label).'</label>';
	}

	function set($property, $value)
	{
		$this->$property = $value;
	}
}
