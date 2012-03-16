<?php
/**
 * @version 1.5 beta 5 $Id: separator.php 567 2011-04-13 11:06:52Z emmanuel.danan@gmail.com $
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

/**
 * Renders a fields element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('spacer');

class JFormFieldSeparator extends JFormFieldSpacer
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'separator';
	
	function getLabel()
	{
		static $css_added = false;
		if (!$css_added) {
			$document = & JFactory::getDocument();
			$css="
			div#module-sliders ul.adminformlist li label.hasTip {
				display:inline-block; padding: 4px; margin: 1px 6px 0px 1px; text-align: right;	width:220px; font-weight: bold;
				background-color: #F6F6F6; border-bottom: 1px solid #E9E9E9; border-right: 1px solid #E9E9E9; color: #666666;
			}
			div#module-sliders ul.adminformlist li ul#rules label.hasTip {
				display:inherit; padding: inherit; margin: inherit; text-align: inherit;	width: inherit; font-weight: inherit;
				background-color: inherit; border-width: 0px; color: inherit;
			}
			div#module-sliders ul.adminformlist li select { margin-bottom: 0px;}
			div#module-sliders ul.adminformlist li fieldset  { margin: 0; padding: 0; }
			";
			$document->addStyleDeclaration($css);
			$css_added = true;
		}
		
		$document 	=& JFactory::getDocument();
		//$document->addStyleSheet('../tmpl/params.css');
		
		$value = $this->element['default'];

		$level = $this->element['level'];
		if ($level == 'level2') {
			$style = 'padding: 2px 0% 2px 4%; display: block; background-color: #ccc; color: #000; font-weight: bold; margin: 2px 2% 2px 6%; width:84%; display: block; float: left; text-align: center; border: 1px outset #E9E9E9;';
		} else if ($level == 'level3') {
			$style = 'padding: 4px 6% 4px 6%; font-weight: bold; clear:both; width:100%; display: block; float: left;';
		} else {
			$style = 'padding: 4px 2% 4px 2%; display: block; background-color: #333333; color: #fff; font-weight: bold; margin: 2px 0% 2px 0%; width:96%; display: block; float: left; border: 1px outset #E9E9E9; font-family:tahoma; font-size:12px;';
		}
		
		$class = ""; $title = "";
		if ($this->element['description']) {
			$class = "hasTip";
			$title = JText::_($value)."::".JText::_($this->element['description']);
		}
		return '<span style="'.$style.'"  class="'.$class.'" title="'.$title.'" >'.JText::_($value).'</span>';
	}
}
