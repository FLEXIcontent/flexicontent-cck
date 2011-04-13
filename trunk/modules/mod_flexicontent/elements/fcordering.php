<?php
/**
 * @version 1.5 beta 5 $Id$
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
 * Renders a ordering list
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
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
		$ordering[] = JHTML::_('select.option',  'commented', 	JText::_( 'FLEXI_MOST_COMMENTED' ) );
		$ordering[] = JHTML::_('select.option',  'rated',		JText::_( 'FLEXI_BEST_RATED' ) ); 
		$ordering[] = JHTML::_('select.option',  'added', 		JText::_( 'FLEXI_RECENTLY_ADDED' ) );
		$ordering[] = JHTML::_('select.option',  'updated', 	JText::_( 'FLEXI_RECENTLY_UPDATED' ) );
		$ordering[] = JHTML::_('select.option',  'alpha', 		JText::_( 'FLEXI_ALPHABETICAL' ) );
		$ordering[] = JHTML::_('select.option',  'alpharev', 	JText::_( 'FLEXI_ALPHABETICAL_REVERSE' ) );
		$ordering[] = JHTML::_('select.option',  'catorder', 	JText::_( 'FLEXI_CAT_ORDER' ) );
		$ordering[] = JHTML::_('select.option',  'random', 		JText::_( 'FLEXI_RANDOM' ) );

		$list = JHTML::_('select.genericlist', $ordering, $control_name.'['.$name.'][]', $class, 'value', 'text', $value );

		return $list;
	}
}