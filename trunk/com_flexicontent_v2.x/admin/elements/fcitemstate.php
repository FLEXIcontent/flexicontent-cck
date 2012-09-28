<?php
/**
 * @version 1.5 beta 5 $Id: fcordering.php 567 2011-04-13 11:06:52Z emmanuel.danan@gmail.com $
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
 * Renders a ordering list
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
if (FLEXI_J16GE) {
	jimport('joomla.html.html');
	jimport('joomla.form.formfield');
	jimport('joomla.form.helper');
	JFormHelper::loadFieldClass('list');
}

class JFormFieldFcitemstate extends JFormFieldList
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	
	var	$type = 'Fcitemstate';
	
	function getOptions()
	{
		$states[] = JHTML::_('select.option',   1, 	JText::_( 'FLEXI_PUBLISHED' ) );
		$states[] = JHTML::_('select.option',  -5,	JText::_( 'FLEXI_IN_PROGRESS' ) );
		$states[] = JHTML::_('select.option',   0,	JText::_( 'FLEXI_UNPUBLISHED' ) ); 
		$states[] = JHTML::_('select.option',  -3,	JText::_( 'FLEXI_PENDING' ) );
		$states[] = JHTML::_('select.option',  -4, 	JText::_( 'FLEXI_TO_WRITE' ) );
		$states[] = JHTML::_('select.option',   2, 	JText::_( 'FLEXI_ARCHIVED' ) );

		return $states;
	}
}