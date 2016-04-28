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
		
		$view	= JRequest::getVar('view');
		$controller	= JRequest::getVar('controller');
		$app = JFactory::getApplication();
		$db  = JFactory::getDBO();
		$cparams = JComponentHelper::getParams('com_flexicontent');
		
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
		
if ( ! @$attributes['skipparams'] ) {
		$doc 	= JFactory::getDocument();
		$js 	= "
var tmpl = ['".$lays."'];

function disablePanel(element) {
	if ( ! jQuery('#'+element+'-attribs-options') ) return;
	
	var panel 	= jQuery('#'+element+'-attribs-options').next();
	var selects = panel.find('select');
	var inputs 	= panel.find('input');
	panel.parent().addClass('pane-disabled');
	selects.each(function(index){
		jQuery(this).attr('disabled', 'disabled');
	});
	inputs.each(function(index){
		jQuery(this).attr('disabled', 'disabled');
	});
	panel.parent().css('display','none');
}

function enablePanel(element) {
	if ( ! jQuery('#'+element+'-attribs-options') ) return;
	
	var panel 	= jQuery('#'+element+'-attribs-options').next();
	var selects = panel.find('select');
	var inputs 	= panel.find('input');
	panel.parent().removeClass('pane-disabled');
	selects.each(function(index){
		jQuery(this).removeAttr('disabled');
	});
	inputs.each(function(index){
		jQuery(this).removeAttr('disabled');
	});
	panel.parent().css('display','');
}

function activatePanel(active) {
	var inactives = jQuery.grep(tmpl, function( item, index ) {
		return item != active;
	});
	
	inactives.each(function(el){
		disablePanel(el);
	});
	
	if (active) {
		enablePanel(active);
	}
}

jQuery(document).ready(function() {
	activatePanel('".$value."');
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
			$attribs .= ' onchange="activatePanel(this.value);"';
		}
		
		if ($inline_tip = @$attributes['inline_tip'])
		{
			$tip_img = @$attributes['tip_img'];
			$tip_img = $tip_img ? $tip_img : 'comment.png';
			$preview_img = @$attributes['preview_img'];
			$preview_img = $preview_img ? $preview_img : '';
			$tip_class = @$attributes['tip_class'];
			$tip_class .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$hintmage = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/'.$tip_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin-left:12px; margin-right:0px;" ' );
			$previewimage = $preview_img ? JHTML::image ( 'administrator/components/com_flexicontent/assets/images/'.$preview_img, JText::_( 'FLEXI_NOTES' ), ' style="max-height:24px; padding:0px; margin:0px;" ' ) : '';
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
