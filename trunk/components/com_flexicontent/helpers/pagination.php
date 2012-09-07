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
defined('JPATH_BASE') or die();
jimport('joomla.html.pagination');

/**
 * Pagination Class.  Provides a common interface for content pagination for the
 * Joomla! Framework
 *
 * @package 	Joomla.Framework
 * @subpackage	HTML
 * @since		1.5
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
			$html .= "\n".JText::_('No records found');
		}

		return $html;
	}
	
	
	/**
	 * Create and return the pagination data object.
	 *
	 * @return  object  Pagination data object.
	 *
	 * @since	1.5
	 */
	// ******************************************************************************************************
	// CAUSES PROBLEM WITH SH404SEF as SH404SEF and other SEF components have custom pagination link creation
	// ******************************************************************************************************
	/*public function _buildDataObject()
	{
		$uri = clone( JFactory::getURI() );
		$uri->delVar('limitstart');
		$uri->delVar('start');
		$uri->delVar('limit');
		$uri->delVar('format');
		$uri->delVar('lang');
		
		// Build the additional URL parameters string.
		$uri_vars = $uri->getQuery($toArray = true);
		if ( count($uri_vars) )
		{
			foreach ($uri_vars AS $varname => $var_value) {
				$varvalue = JRequest::getString($varname, '', 'request');
				$uri->setVar($varname, $varvalue);
			}
		}
		$link = $uri->toString();
		$vchar = count($uri_vars) ? '&' : '?';

		// Initialise variables.
		$data = new stdClass;
		
		$data->all	= new JPaginationObject(JText::_('View All'));
		if (!$this->_viewall)
		{
			$data->all->base = '0';
			$data->all->link	= JRoute::_("&limitstart=");
		}

		// Set the start and previous data objects.
		$data->start	= new JPaginationObject(JText::_('Start'));
		$data->previous	= new JPaginationObject(JText::_('Prev'));

		if ($this->get('pages.current') > 1)
		{
			$page = ($this->get('pages.current') - 2) * $this->limit;

			// Set the empty for removal from route
			//$page = $page == 0 ? '' : $page;
			
			$start_str = "";
			$previous_str = ($page > 2 || $link=='') ? $vchar."limitstart=".$page : "";
			$previous_str .= ($page > 2 || $link=='') ? "&limit=".$this->limit : "";
			
			$data->start->base = '0';
			$data->start->link = JRoute::_($link.$start_str);
			$data->previous->base = $page;
			$data->previous->link = JRoute::_($link.$previous_str);
		}

		// Set the next and end data objects.
		$data->next	= new JPaginationObject(JText::_('Next'));
		$data->end	= new JPaginationObject(JText::_('End'));

		if ($this->get('pages.current') < $this->get('pages.total'))
		{
			$next = $this->get('pages.current') * $this->limit;
			$end = ($this->get('pages.total') - 1) * $this->limit;
			
			$next_str = ($next || $link=='') ? $vchar."limitstart=".$next : "";
			$next_str .= ($next || $link=='') ? "&limit=".$this->limit : "";
			$end_str  = ($end  || $link=='') ? $vchar."limitstart=".$end : "";
			$end_str .= ($end || $link=='') ? "&limit=".$this->limit : "";
			
			$data->next->base = $next;
			$data->next->link	= JRoute::_($link.$next_str);
			$data->end->base = $end;
			$data->end->link = JRoute::_($link.$end_str);
		}

		$data->pages = array();
		$stop = $this->get('pages.stop');
		for ($i = $this->get('pages.start'); $i <= $stop; $i++)
		{
			$offset = ($i - 1) * $this->limit;
			// Set the empty for removal from route
			//$offset = $offset == 0 ? '' : $offset;

			$data->pages[$i] = new JPaginationObject($i);
			if ($i != $this->get('pages.current') || $this->_viewall)
			{
				$offset_str = ($offset || $link=='') ? $vchar."limitstart=".$offset : "";
				$offset_str .= ($offset || $link=='') ? "&limit=".$this->limit : "";
				
				$data->pages[$i]->base = $offset;
				$data->pages[$i]->link = JRoute::_($link.$offset_str);
			}
		}
		return $data;
	}*/
	
}
