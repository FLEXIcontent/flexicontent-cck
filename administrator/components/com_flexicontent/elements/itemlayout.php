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
defined('_JEXEC') or die();

/**
 * Renders a author element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.0
 */
//jimport('joomla.html.html.select');
require_once(JPATH_ROOT.DS.'libraries'.DS.'joomla'.DS.'html'.DS.'html'.DS.'select.php');
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

class JElementItemlayout extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'Itemlayout';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$themes	= flexicontent_tmpl::getTemplates();
		$tmpls	= $themes->items;
		$class 	= 'class="inputbox" onchange="activatePanel(this.value);"';
		$view	= JRequest::getVar('view');

		$lays = array();
		foreach ($tmpls as $tmpl) {
			$lays[] = $tmpl->name;
		}
		$lays = implode("','", $lays);

		$doc 	= & JFactory::getDocument();
		$js 	= "
var tmpl = ['".$lays."'];	

function disablePanel(element) {
	var panel 	= $('params-'+element).getNext();
	var selects = panel.getElements('select');
	var inputs 	= panel.getElements('input');
	panel.getParent().addClass('pane-disabled');
	selects.each(function(el){
		el.setProperty('disabled', 'disabled');
	});
	inputs.each(function(el){
		el.setProperty('disabled', 'disabled');
	});
}

function enablePanel(element) {
	var panel 	= $('params-'+element).getNext();
	var selects = panel.getElements('select');
	var inputs 	= panel.getElements('input');
	panel.getParent().removeClass('pane-disabled');
	selects.each(function(el){
    	el.setProperty('disabled', '');
	});
	inputs.each(function(el){
    	el.setProperty('disabled', '');
	});
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

		if ($tmpls !== false) {
			if ($view != 'type') {
				$layouts[] = JHTMLSelect::option('', JText::_( 'FLEXI_USE_GLOBAL' ));
			}
			foreach ($tmpls as $tmpl) {
				$layouts[] = JHTMLSelect::option($tmpl->name, $tmpl->name); 
			}
		}

		return JHTMLSelect::genericList($layouts, $control_name.'['.$name.']', $class, 'value', 'text', $value, $control_name.$name);
	}
}
?>
