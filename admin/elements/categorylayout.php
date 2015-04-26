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
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');

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
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
			if ( !$cparams->get($attributes['enableparam']) ) return FLEXI_J16GE ? '' : JText::_('FLEXI_DISABLED');
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
		
		return JHTML::_('select.genericlist', $layouts, $fieldname, $attribs, 'value', 'text', $value, $element_id);
	}
	
	
	function getLabel()
	{
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		if ( @$attributes['enableparam'] ) {
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
			if ( !$cparams->get($attributes['enableparam']) ) return '';
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
