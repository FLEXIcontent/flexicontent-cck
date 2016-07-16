<?php
/**
 * @version 1.5 stable $Id: flexicontent_reviews.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

defined('_JEXEC') or die('Restricted access');

/**
 * FLEXIcontent table class
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class flexicontent_reviews extends JTable
{
	function __construct(& $db)
	{
		parent::__construct('#__flexicontent_reviews_dev', 'id', $db);
	}


	// overloaded check function
	function check()
	{
		// Set submit date if it is empty
		if ( !$this->submit_date )
		{
			$datenow = JFactory::getDate();
			$this->submit_date = $datenow->toSql();
		}
		
		// If edited by review submitter then also set the update_date
		if ( $this->id && $this->user_id == JFactory::getUser()->id )
		{
			$datenow = JFactory::getDate();
			$this->update_date = $datenow->toSql();
		}
		
		return;
	}
}
?>