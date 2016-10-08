<?php
/**
 * @version 1.5 stable $Id: categorylayout.php 1243 2012-04-12 04:59:40Z ggppdk $
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
jimport('cms.html.select');    // JHtmlSelect

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('list');   // JFormFieldList

/**
 * Renders a categorylayout element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.0
 */
class JFormFieldCategorylayout extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'Categorylayout';

	protected function getInput()
	{
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		$themes	= flexicontent_tmpl::getTemplates();
		$tmpls_all	= $themes->category ? $themes->category : array();
		$value = $this->value;
		//$value = $value ? $value : @$attributes['default'];
		
		$app = JFactory::getApplication();
		$db  = JFactory::getDBO();
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$jinput  = $app->input;
		$view	= $jinput->get('view', '', 'cmd');
		$controller	= $jinput->get('controller', '', 'cmd');
		
		// Get RECORED id of current view
		$cid = $jinput->get('cid', array(0), 'array');
		$pk = (int)$cid[0];
		
		// GET LIMITING to specific templates according to item's type, or according to type of new item
		$allowed_tmpls = array();
		$all_tmpl_allowed = true;
		$conf_default_layout = '';
		$conf_default_layout_mobile = '';
		
		$tmpls = array();
		$lays = array();
		foreach ($tmpls_all as $tmpl) {
			if ( $all_tmpl_allowed || in_array($tmpl->name, $allowed_tmpls) ) {
				$tmpls[] = $tmpl;
				$lays[] = $tmpl->name;
			}
		}
		$lays = implode("','", $lays);
		
		if ( @$attributes['enableparam'] ) {
			if ( !$cparams->get($attributes['enableparam']) ) return '';
		}
		
if ( ! @$attributes['skipparams'] )
{
		$ext_option = 'com_flexicontent';
		$ext_view = $view;
		$doc 	= JFactory::getDocument();
		$js 	= "
var tmpl = ['".$lays."'];

function clayout_disablePanel(element)
{
	var el, panel = jQuery('#'+element+'-attribs-options').next();
	
	if ( !panel.length ) return;
	if ( panel.parent().hasClass('pane-disabled') ) return;
	
	var form_fields_active = panel.find('textarea:enabled, select:enabled, input[type=\"radio\"]:enabled:checked, input[type=\"checkbox\"]:enabled:checked, input:not(:button):not(:radio):not(:checkbox):enabled');
	form_fields_active.each(function(index)
	{
		el = jQuery(this);
		//if ( el.is(':disabled') ) return;  // no need, because above we selected only enabled elements
		el.addClass('fclayout_disabled_element');
		el.attr('disabled', 'disabled');
	});
	
	panel.parent().addClass('pane-disabled').hide();
}

function clayout_loadPanel(element)
{
	var panel_header_id = element+'-attribs-options',
		panel_id = panel_header_id + '-panel';
	
	var el,
		panel_header = jQuery('#'+panel_header_id),
		panel = panel_header.next();
	
	if ( !panel.length ) return;
	
	if ( !panel.parent().hasClass('pane-disabled') ) panel.addClass('fc_layout_loaded');
	
	// Add LOADING animation into the panel header, and show outer box that contains the panel header and the panel
	var _loading_img = '<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\">';
	panel_header.html('<a href=\"javascript:void(0);\"><span><span class=\"btn\"><i class=\"icon-edit\"></i>'+(panel.hasClass('fc_layout_loaded') ? '".JText::_( 'FLEXI_REFRESHING' )."' : '".JText::_( 'FLEXI_LOADING' )."')+' ... '+_loading_img+'</span></span></a>');
	panel.parent().removeClass('pane-disabled').show();
	
	// Re-enabled an already loaded panel, (avoid re-downloading which will cause modified parameters to be lost)
	if ( panel.hasClass('fc_layout_loaded') )
	{
	 	panel.find('.fclayout_disabled_element').removeAttr('disabled').removeClass('fclayout_disabled_element');
	 	setTimeout(function(){
			panel_header.html('<a href=\"javascript:void(0);\"><span><span class=\"btn\"><i class=\"icon-edit\"></i>".JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ).": '+element+'</span></span></a>');
	 	}, 300);
		
	}
	
	// (AJAX) Retrieve layout parameters for the selected template
	else
	{
		panel.attr('id', panel_id);
		jQuery.ajax({
			type: 'GET',
			url: 'index.php?option=com_flexicontent&task=templates.getlayoutparams&ext_view=".$ext_view."&ext_option=".$ext_option."&ext_name='+element+'&ext_id=".$pk."&layout_name=category&ext_type=templates&directory='+element+'&format=raw',
			success: function(str)
			{
				panel.addClass('fc_layout_loaded').html(str);
				
				jQuery('.hasTooltip').tooltip({'html': true,'container': panel});
				fc_bindFormDependencies('#'+panel_id, 0, '');
				fc_bootstrapAttach('#'+panel_id);
				if (typeof(fcrecord_attach_sortable) == 'function')
				{
					fcrecord_attach_sortable('#'+panel_id);
				}
				//tabberAutomatic(tabberOptions, panel_id);

				panel_header.html('<a href=\"javascript:void(0);\"><span><span class=\"btn\"><i class=\"icon-edit\"></i>".JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ).": '+element+'</span></span></a>');
			}
		});
	}
}

function clayout_activatePanel(active)
{
	var inactives = jQuery.grep(tmpl, function( item, index ) {
		return item != active;
	});
	
	inactives.each(function(el){
		clayout_disablePanel(el);
	});
	
	if (active) {
		clayout_loadPanel(active);
	}
}

jQuery(document).ready(function() {
	clayout_activatePanel('".$value."');
});
";
		$doc->addScriptDeclaration($js);
}
		
		$layouts = array();
		if (  @$attributes['firstoption'] ) {
			$layouts[] = JHTMLSelect::option('', JText::_( $attributes['firstoption'] ));
		} else {
				$layouts[] = JHTMLSelect::option('', '-- '.JText::_( 'FLEXI_USE_GLOBAL' ). ' --');
		}
		foreach ($tmpls as $tmpl) {
			$layouts[] = JHTMLSelect::option($tmpl->name, $tmpl->name);
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
			$attribs .= ' onchange="clayout_activatePanel(this.value);"';
		}
		
		if ($inline_tip = @$attributes['inline_tip'])
		{
			$tip_img = @$attributes['tip_img'];
			$tip_img = $tip_img ? $tip_img : 'comment.png';
			$preview_img = @$attributes['preview_img'];
			$preview_img = $preview_img ? $preview_img : '';
			$tip_class = @$attributes['tip_class'];
			$tip_class .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$hintmage = JHTML::image ( 'components/com_flexicontent/assets/images/'.$tip_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin-left:12px; margin-right:0px;" ' );
			$previewimage = $preview_img ? JHTML::image ( 'components/com_flexicontent/assets/images/'.$preview_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin:0px;" ' ) : '';
			$tip_text = '<span class="'.$tip_class.'" style="" title="'.flexicontent_html::getToolTip(null, $inline_tip, 1, 1).'">'.$hintmage.$previewimage.'</span>';
		}
		if ($inline_tip = @$attributes['inline_tip2'])
		{
			$tip_img = @$attributes['tip_img2'];
			$tip_img = $tip_img ? $tip_img : 'comment.png';
			$preview_img = @$attributes['preview_img2'];
			$preview_img = $preview_img ? $preview_img : '';
			$tip_class = @$attributes['tip_class2'];
			$tip_class .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$hintmage = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/'.$tip_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin-left:12px; margin-right:0px;" ' );
			$previewimage = $preview_img ? JHTML::image ( 'administrator/components/com_flexicontent/assets/images/'.$preview_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin:0px;" ' ) : '';
			$tip_text2 = '<span class="'.$tip_class.'" style="" title="'.flexicontent_html::getToolTip(null, $inline_tip, 1, 1).'">'.$hintmage.$previewimage.'</span>';
		}
		return
			JHTML::_('select.genericlist', $layouts, $fieldname, $attribs, 'value', 'text', $value, $element_id)
			.@$tip_text.@$tip_text2;
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
		$title = "...";
		if ($this->element['description']) {
			$title = flexicontent_html::getToolTip($label, $this->element['description'], 1, 1);
		}
		return '<label style=""  class="'.$class.'" title="'.$title.'" >'.JText::_($label).'</label>';
	}
	
	function set($property, $value) {
		$this->$property = $value;
	}
	
}
?>
