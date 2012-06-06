<?php
/**
 * @version 1.5 stable $Id: ilayoutlist.php 967 2011-11-21 00:01:36Z ggppdk $
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
require_once(JPATH_ROOT.DS.'libraries'.DS.'joomla'.DS.'html'.DS.'html'.DS.'select.php');
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

/**
 * Renders a author element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.0
 */
class JElementIlayoutlist extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 * @since	1.5
	 */
	var	$_name = 'Ilayoutlist';

	function fetchElement($name, $value, &$node, $control_name)
	{
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$themes	= flexicontent_tmpl::getTemplates();
		$tmpls	= $themes->items;
		
		$values			= FLEXI_J16GE ? $this->value : $value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;

		// Field parameter (attributes)
		$attribs = '';
		
		$classes = 'inputbox ';
		if ( $node->attributes('required') && $node->attributes('required')=='true' ) {
			$classes .= 'required ';
		}
		if ( $node->attributes('validation_class') ) {
			$classes .= $node->attributes('validation_class');
		}
		$attribs = ' class="'.$classes.'" '.$attribs;
		
		$attribs = 'style="float:left;"';
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="true" ';
			$attribs .= (@$attributes['size']) ? ' size="'.$attributes['size'].'" ' : ' size="6" ';
			$fieldname .= !FLEXI_J16GE ? "[]" : "";  // NOTE: this added automatically in J2.5
			$maximize_link = "<a style='display:inline-block;".(FLEXI_J16GE ? 'float:left; margin: 6px 0px 0px 18px;':'margin:0px 0px 6px 12px')."' href='javascript:;' onclick='$element_id = document.getElementById(\"$element_id\"); if ($element_id.size<16) { ${element_id}_oldsize=$element_id.size; $element_id.size=16;} else { $element_id.size=${element_id}_oldsize; } ' >Maximize/Minimize</a>";
		} else {
			array_unshift($types, JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT')));
			$attribs .= 'class="inputbox"';
			$maximize_link = '';
		}
		if ($onchange = @$attributes['onchange']) {
			$attribs .= ' onchange="'.$onchange.'"';
		}
		
		// Field data (the templates)
		$lays = array();
		foreach ($tmpls as $tmpl) {
			$lays[] = $tmpl->name;
		}
		$lays = implode("','", $lays);
		
		if ($tmpls !== false) {
			foreach ($tmpls as $tmpl) {
				$layouts[] = JHTMLSelect::option($tmpl->name, $tmpl->name); 
			}
		}
		
		$html = JHTML::_('select.genericlist', $layouts, $fieldname, $attribs, 'value', 'text', $values, $element_id);
		return $html.$maximize_link;
	}
}
?>
