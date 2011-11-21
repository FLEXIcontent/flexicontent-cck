<?php
/**
 * @version 1.5 beta 4 $Id: fcmethod.php 567 2011-04-13 11:06:52Z emmanuel.danan@gmail.com $
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
 * Renders a selcet method radio element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('radio');

class JFormFieldFcmethod extends JFormFieldRadio
{


	function getOptions()
	{
		$options = array(); 
		$options[] = JHTML::_('select.option', '1', JText::_('FLEXI_ALL')); 
		$options[] = JHTML::_('select.option', '2', JText::_('FLEXI_EXCLUDE')); 
		$options[] = JHTML::_('select.option', '3', JText::_('FLEXI_INCLUDE')); 

		return $options;
	}
}