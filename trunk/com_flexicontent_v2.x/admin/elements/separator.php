<?php
/**
 * @version 1.5 stable $Id: separator.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
jimport('joomla.html.html');
jimport('joomla.form.formfield');
/**
 * Renders a fields element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
 
class JFormFieldSeparator extends JFormField{
	/**
	 * Element name
	 *
	 * @access	protected
	 * @var		string
	 */
	var	$type = 'Separator';

	function getInput() {
		$level = $this->element->getAttribute('level');
		if ($level == 'level2') {
			$style = 'padding: 4px 4px 4px 10px; background-color: #ccc; display: block; color: #000; font-weight: bold; margin-left:10px;';
		} else if ($level == 'level3') {
			$style = 'padding: 5px 4px 5px 5px; font-weight: bold;';
		} else {
			$style = 'padding: 5px 4px 5px 10px; background-color: #777; display: block; color: #fff; font-weight: bold;';
		}
		return '<fieldset style="float:left;width:100%;"><div style="'.$style.'">'.JText::_($this->value).'</div></fieldset>';
	}
}
