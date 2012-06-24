<?php
/**
 * @version		$Id: pagination.php 14401 2010-01-26 14:10:00Z louis $
 * @package		Joomla.Framework
 * @subpackage	HTML
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters. All rights reserved.
 * @license		GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
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
	public function _buildDataObject()
	{
		$uri = clone( JFactory::getURI() );
		$uri->delVar('limitstart');
		$uri->delVar('start');
		$uri->delVar('format');
		$uri->delVar('lang');
		
		$uri_vars = $uri->getQuery($toArray = true);
		
		$link = '';
		if ( count($uri_vars) ) {
			foreach ($uri_vars AS $varname => $var_value) {
				$varvalue = JRequest::getString($varname, '', 'request');
				$uri->setVar($varname, $varvalue);
			}
			$link = $uri->toString();
		}

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
			
			$start_str = '';
			$previous_str = ($page  || $link=='') ? "&limitstart=".$page : "";
			
			$data->start->base = '0';
			$data->start->link = JRoute::_($link);
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

			$next_str = ($next || $link=='') ? "&limitstart=".$next : "";
			$end_str  = ($end  || $link=='') ? "&limitstart=".$end : "";
			
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
				$offset_str = ($offset || $link=='') ? "&limitstart=".$offset : "";
				$data->pages[$i]->base = $offset;
				$data->pages[$i]->link = JRoute::_($link.$offset_str);
			}
		}
		return $data;
	}
	
}
