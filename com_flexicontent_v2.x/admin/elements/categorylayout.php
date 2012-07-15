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
if (FLEXI_J16GE) {
	jimport('joomla.html.html');
	jimport('joomla.form.formfield');
	jimport('joomla.form.helper');
	JFormHelper::loadFieldClass('list');
}
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

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
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	protected $type = 'Categorylayout';

	function getInput()
	{
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$themes	= flexicontent_tmpl::getTemplates();
		$tmpls	= $themes->category;
		$view	= JRequest::getVar('view');
		$value = FLEXI_J16GE ? $this->value : $value;
		
		$lays = array();
		foreach ($tmpls as $tmpl) {
			$lays[] = $tmpl->name;
		}
		$lays = implode("','", $lays);
		
if ( ! @$attributes['skipparams'] ) {
		$doc 	= & JFactory::getDocument();
		$js 	= "
var tmpl = ['".$lays."'];	

function disablePanel(element) {
	var panel 	= $(element+'-attribs-options').getNext();
	var selects = panel.getElements('select');
	var inputs 	= panel.getElements('input');
	panel.getParent().addClass('pane-disabled');
	selects.each(function(el){
		el.setProperty('disabled', 'disabled');
	});
	inputs.each(function(el){
		el.setProperty('disabled', 'disabled');
	});
	panel.getParent().setStyle('display','none');
}

function enablePanel(element) {
	var panel 	= $(element+'-attribs-options').getNext();
	var selects = panel.getElements('select');
	var inputs 	= panel.getElements('input');
	panel.getParent().removeClass('pane-disabled');
	selects.each(function(el){
    	el.setProperty('disabled', '');
	});
	inputs.each(function(el){
    	el.setProperty('disabled', '');
	});
	panel.getParent().setStyle('display','');
}

function activatePanel(active) {
	var inactives = tmpl.filter(function(item, index){
		return item != active;
		});
			
	inactives.each(function(el){
		disablePanel(el);
		});
		
	if (active) enablePanel(active);
}

window.addEvent('domready', function(){
	activatePanel('".$value."');			
});
";
		$doc->addScriptDeclaration($js);
}
	
		if ($tmpls !== false) {
			if ($view != 'category' && $view != 'user') {
				$layouts[] = JHTMLSelect::option('', JText::_( 'FLEXI_USE_GLOBAL' ));
			}
			foreach ($tmpls as $tmpl) {
				$layouts[] = JHTMLSelect::option($tmpl->name, $tmpl->name); 
			}
		}
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		$attribs = !FLEXI_J16GE ? ' style="float:left;" ' : '';
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="true" ';
			$attribs .= (@$attributes['size']) ? ' size="'.@$attributes['size'].'" ' : ' size="6" ';
			$fieldname .= !FLEXI_J16GE ? "[]" : "";  // NOTE: this added automatically in J2.5
		} else {
			$attribs .= 'class="inputbox"';
		}
		if ( ! @$attributes['skipparams'] )
		{
			$attribs .= ' onchange="activatePanel(this.value);"';
		}
		
		return JHTML::_('select.genericlist', $layouts, $fieldname, $attribs, 'value', 'text', $value, $element_id);
	}
	
	function getLabel()
	{
		$label = $this->element['label'];
		$class = "hasTip"; $title = "";
		if ($this->element['description']) {
			$class = "hasTip";
			$title = JText::_($label)."::".JText::_($this->element['description']);
		}
		return '<label style=""  class="'.$class.'" title="'.$title.'" >'.JText::_($label).'</label> &nbsp; ';
	}
	
	function set($property, $value) {
		$this->$property = $value;
	}
	
}
?>
