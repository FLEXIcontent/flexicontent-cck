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

class JFormFieldFcordering extends JFormFieldList
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	
	var	$type = 'Fcordering';
	
	function getOptions()
	{
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$ordering[] = JHTML::_('select.option',  'addedrev', 	JText::_( 'FLEXI_ORDER_OLDEST_FIRST' ) );       // 'date'
		$ordering[] = JHTML::_('select.option',  'added', 		JText::_( 'FLEXI_ORDER_MOST_RECENT_FIRST' ) );  // 'rdate'
		$ordering[] = JHTML::_('select.option',  'updated', 	JText::_( 'FLEXI_ORDER_LAST_MODIFIED_FIRST' ) );
		
		$ordering[] = JHTML::_('select.option',  'alpha', 		JText::_( 'FLEXI_ORDER_TITLE_ALPHABETICAL' ) );
		$ordering[] = JHTML::_('select.option',  'alpharev', 	JText::_( 'FLEXI_ORDER_TITLE_ALPHABETICAL_REVERSE' ) );  // 'ralpha'
		
		$ordering[] = JHTML::_('select.option',  'author', 		JText::_( 'FLEXI_ORDER_AUTHOR_ALPHABETICAL' ) );
		$ordering[] = JHTML::_('select.option',  'rauthor', 	JText::_( 'FLEXI_ORDER_AUTHOR_ALPHABETICAL_REVERSE' ) );
		
		$ordering[] = JHTML::_('select.option',  'popular', 	JText::_( 'FLEXI_ORDER_MOST_HITS' ) );          // 'hits'
		$ordering[] = JHTML::_('select.option',  'rhits',			JText::_( 'FLEXI_ORDER_LEAST_HITS' ) );
		
		$ordering[] = JHTML::_('select.option',  'id', 				JText::_( 'FLEXI_ORDER_HIGHEST_ITEM_ID' ) );
		$ordering[] = JHTML::_('select.option',  'rid', 			JText::_( 'FLEXI_ORDER_LOWEST_ITEM_ID' ) );
		
		$ordering[] = JHTML::_('select.option',  'commented',	JText::_( 'FLEXI_ORDER_MOST_COMMENTED' ) );
		$ordering[] = JHTML::_('select.option',  'rated',			JText::_( 'FLEXI_ORDER_BEST_RATED' ) ); 
		$ordering[] = JHTML::_('select.option',  'catorder', 	JText::_( 'FLEXI_ORDER_CONFIGURED_ORDER' ) );   // 'order'
		
		$ordering[] = JHTML::_('select.option',  'random', 		JText::_( 'FLEXI_RANDOM' ) );
		
		$ordering[] = JHTML::_('select.option',  'field', 		JText::_( 'FLEXI_CUSTOM_FIELD' ) );
		
		return $ordering;
	}
}