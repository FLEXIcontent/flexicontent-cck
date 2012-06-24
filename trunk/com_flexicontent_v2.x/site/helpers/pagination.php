<?php
/**
 * @package     Joomla.Platform
 * @subpackage  HTML
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;
jimport('joomla.html.pagination');

/**
 * Pagination Class.  Provides a common interface for content pagination for the
 * Joomla! Platform.
 *
 * @package     Joomla.Platform
 * @subpackage  HTML
 * @since       11.1
 */
class FCPagination extends JPagination
{
	/**
	 * Create and return the pagination result set counter string, ie. Results 1-10 of 42
	 *
	 * @access	public
	 * @return	string	Pagination result set counter string
	 * @since	1.5
	 */
	function getResultsCounter()
	{
		// Initialize variables
		$html = null;
		$fromResult = $this->limitstart + 1;

		// If the limit is reached before the end of the list
		if ($this->limitstart + $this->limit < $this->total) {
			$toResult = $this->limitstart + $this->limit;
		} else {
			$toResult = $this->total;
		}

		// If there are results found
		if ($this->total > 0) {
			$html =
				 "<span class='item_total_label'>".JText::_( 'FLEXI_TOTAL')."</span> "
				."<span class='item_total_value'>".$this->total." " .JText::_( 'FLEXI_ITEM_S')."</span>"
				."<span class='item_total_label'>".JText::_( 'FLEXI_DISPLAYING')."</span> "
				."<span class='item_total_value'>".$fromResult ." - " .$toResult ." " .JText::_( 'FLEXI_ITEM_S')."</span>"
				;
		} else {
			$html .= "\n" . JText::_('JLIB_HTML_NO_RECORDS_FOUND');
		}

		return $html;
	}

	
}
