<?php
/**
 * @version 1.5 stable $Id: controller.php 1433 2012-08-13 22:20:28Z ggppdk $
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

// Check to ensure this file is within the rest of the framework
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
	function getResultsCounter() {
		// Initialize variables
		$app  = JFactory::getApplication();
		$view = JRequest::getCMD('view');
		$html = null;
		$fromResult = $this->limitstart + 1;

		// If the limit is reached before the end of the list
		if ($this->limitstart + $this->limit < $this->total) {
			$toResult = $this->limitstart + $this->limit;
		} else {
			$toResult = $this->total;
		}

		// If there are results found
		$fc_view_total = 0; //(int) $app->getUserState('fc_view_total_'.$view);
		if (!$fc_view_total) $fc_view_total = $this->total;
		
		if ($fc_view_total > 0) {
			// Check for maximum allowed of results
			$fc_view_limit_max = JRequest::getWord('view')!='search'  ?  0  :  (int) $app->getUserState('fc_view_limit_max_'.$view);
			$items_total_msg = $fc_view_limit_max && ($this->total >= $fc_view_limit_max) ? 'FLEXI_ITEM_S_OR_MORE' : 'FLEXI_ITEM_S';
			
			$html =
				 "<span class='flexi label item_total_label'>".JText::_( 'FLEXI_TOTAL')."</span> "
				."<span class='flexi value item_total_value'>".$fc_view_total." " .JText::_( $items_total_msg )."</span>"
				."<span class='flexi label item_total_label'>".JText::_( 'FLEXI_DISPLAYING')."</span> "
				."<span class='flexi value item_total_value'>".$fromResult ." - " .$toResult ." " .JText::_( 'FLEXI_ITEM_S')."</span>"
				;
		} else {
			$html .= "\n" . JText::_('JLIB_HTML_NO_RECORDS_FOUND');
		}

		return $html;
	}
	
	
	/**
	 * Create and return the pagination data object.
	 *
	 * @return  object  Pagination data object.
	 *
	 * @since   11.1
	 */
	// ******************************************************************************************************
	// CAUSES PROBLEM WITH SH404SEF as SH404SEF and other SEF components have custom pagination link creation
	// ******************************************************************************************************
	/*protected function _buildDataObject()
	{
		// Build the additional URL parameters string.
		$params = '';
		if (!empty($this->_additionalUrlParams))
		{
			foreach ($this->_additionalUrlParams as $key => $value)
			{
				$params .= '&' . $key . '=' . $value;
			}
		}

		// Initialise variables.
		$data = new stdClass;
		
		$data->all = new JPaginationObject(JText::_('JLIB_HTML_VIEW_ALL'), $this->prefix);
		if ( empty($this->_viewall) )
		{
			$data->all->base = '0';
			$data->all->link = JRoute::_($params . '&' . $this->prefix . 'limitstart=');
		}

		// Set the start and previous data objects.
		$data->start = new JPaginationObject(JText::_('JLIB_HTML_START'), $this->prefix);
		$data->previous = new JPaginationObject(JText::_('JPREV'), $this->prefix);

		if ($this->get('pages.current') > 1)
		{
			$page = ($this->get('pages.current') - 2) * $this->limit;

			// Set the empty for removal from route
			//$page = $page == 0 ? '' : $page;
			
			$limistart_start_str = '&' . $this->prefix . 'limitstart=0';
			$limistart_previous_str = '&' . $this->prefix . 'limitstart=' . $page;
			$limit_str     = '&' . $this->prefix . 'limit=' . $this->limit;
			
			$data->start->base = '0';
			$data->start->link = JRoute::_($params . $limistart_start_str);
			$data->previous->base = $page;
			$data->previous->link = JRoute::_($params . $limistart_previous_str . $limit_str );
		}

		// Set the next and end data objects.
		$data->next = new JPaginationObject(JText::_('JNEXT'), $this->prefix);
		$data->end = new JPaginationObject(JText::_('JLIB_HTML_END'), $this->prefix);

		if ($this->get('pages.current') < $this->get('pages.total'))
		{
			$next = $this->get('pages.current') * $this->limit;
			$end = ($this->get('pages.total') - 1) * $this->limit;
			
			$limistart_next_str = '&' . $this->prefix . 'limitstart=' . $next;
			$limistart_end_str = '&' . $this->prefix . 'limitstart=' . $end;
			$limit_str     = '&' . $this->prefix . 'limit=' . $this->limit;
			
			$data->next->base = $next;
			$data->next->link = JRoute::_($params . $limistart_next_str  . $limit_str );
			$data->end->base = $end;
			$data->end->link = JRoute::_($params . $limistart_end_str . $limit_str );
		}

		$data->pages = array();
		$stop = $this->get('pages.stop');
		for ($i = $this->get('pages.start'); $i <= $stop; $i++)
		{
			$offset = ($i - 1) * $this->limit;
			// Set the empty for removal from route
			//$offset = $offset == 0 ? '' : $offset;

			$data->pages[$i] = new JPaginationObject($i, $this->prefix);
			if ($i != $this->get('pages.current') || !empty($this->_viewall) || 1)
			{
				$limistart_str = '&' . $this->prefix . 'limitstart=' . $offset;
				$limit_str     = '&' . $this->prefix . 'limit=' . $this->limit;
				
				$data->pages[$i]->base = $offset;
				$data->pages[$i]->link = JRoute::_($params . $limistart_str . $limit_str );
			}
		}
		return $data;
	}*/
	
}
