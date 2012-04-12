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
		$themes	= flexicontent_tmpl::getTemplates();
		$tmpls	= $themes->items;
		
		// Field name
		$fieldName	= $control_name.'['.$name.']';
		
		// Field values
		//var_dump($value);
		if ( empty($value) )							$values = array();
		else if ( ! is_array($value) )		$values = array($value);
		else															$values = $value;
		
		// Field parameter (attributes)
		$attribs = '';
		if ($node->attributes('size')) {
			$attribs .= ' size="'.$node->attributes('size').'" ';
		} else {
			$attribs .= ' size="8" ';
		}
		if ( $node->attributes('multiple') && $node->attributes('multiple')=='true' ) {
			$attribs .=' multiple="multiple"';
			$fieldName .= "[]";
		}

		// Field parameter (classes)
		$classes = 'inputbox ';
		if ( $node->attributes('required') && $node->attributes('required')=='true' ) {
			$classes .= 'required ';
		}
		if ( $node->attributes('validation_class') ) {
			$classes .= $node->attributes('validation_class');
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
		
		$parameters_str = ' class="'.$classes.'" '.$attribs;
		return JHTMLSelect::genericList($layouts, $fieldName, $parameters_str, 'value', 'text', $value, $control_name.$name);
	}
}
?>
