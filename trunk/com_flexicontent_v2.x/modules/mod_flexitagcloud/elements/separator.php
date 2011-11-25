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
		$document 	=& JFactory::getDocument();
		//$document->addStyleSheet('../tmpl/params.css');
		
		$value = $this->element['default'];

		$level = $this->element['level'];
		if ($level == 'level2') {
			$style = 'padding: 4px 4% 4px 4%; display: block; background-color: #ccc; color: #000; font-weight: bold; margin: 2px 2% 2px 6%; width:84%; display: block; float: left;';
		} else if ($level == 'level3') {
			$style = 'padding: 4px 6% 4px 6%; font-weight: bold; clear:both; width:100%; display: block; float: left;';
		} else {
			$style = 'padding: 4px 2% 4px 2%; display: block; background-color: #777; color: #fff; font-weight: bold; margin: 2px 3% 2px 3%; width:91%; display: block; float: left;';
		}
		
		$class = ""; $title = "";
		if ($this->element['description']) {
			$class = "hasTip";
			$title = JText::_($value)."::".JText::_($this->element['description']);
		}
		return '<span style="'.$style.'"  class="'.$class.'" title="'.$title.'" >'.JText::_($value).'</span>';
	}
}
