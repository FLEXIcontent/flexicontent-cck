<?php
/**
 * @version 1.5 beta 5 $Id: fcordering.php 967 2011-11-21 00:01:36Z ggppdk $
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

class JElementFcordering extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	
	var	$_name = 'Fcordering';
	
	function fetchElement($name, $value, &$node, $control_name)
	{
		$class = 'class="inputbox" multiple="true" size="9"';

		$ordering[] = JHTML::_('select.option',  'popular', 	JText::_( 'FLEXI_MOST_POPULAR' ) );
		$ordering[] = JHTML::_('select.option',  'commented',	JText::_( 'FLEXI_MOST_COMMENTED' ) );
		$ordering[] = JHTML::_('select.option',  'rated',			JText::_( 'FLEXI_BEST_RATED' ) ); 
		$ordering[] = JHTML::_('select.option',  'added', 		JText::_( 'FLEXI_RECENTLY_ADDED' ) );
		$ordering[] = JHTML::_('select.option',  'addedrev', 	JText::_( 'FLEXI_RECENTLY_ADDED_REVERSE' ) );
		$ordering[] = JHTML::_('select.option',  'updated', 	JText::_( 'FLEXI_RECENTLY_UPDATED' ) );
		$ordering[] = JHTML::_('select.option',  'alpha', 		JText::_( 'FLEXI_ALPHABETICAL' ) );
		$ordering[] = JHTML::_('select.option',  'alpharev', 	JText::_( 'FLEXI_ALPHABETICAL_REVERSE' ) );
		$ordering[] = JHTML::_('select.option',  'catorder', 	JText::_( 'FLEXI_CAT_ORDER' ) );
		$ordering[] = JHTML::_('select.option',  'random', 		JText::_( 'FLEXI_RANDOM' ) );

		return JHTML::_('select.genericlist', $ordering, $control_name.'['.$name.'][]', $class, 'value', 'text', $value );
	}
}