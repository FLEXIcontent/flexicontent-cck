<?php
/**
 * @version 1.5 stable $Id: view.feed.php 183 2009-11-18 10:30:48Z vistamedia $
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

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the FLEXIcontent View (RSS)
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFlexicontent extends JView
{
	/**
	 * Creates the RSS for the View
	 *
	 * @since 1.0
	 */
	function display()
	{
		global $mainframe;
		
		$doc 		= & JFactory::getDocument();
		$params 	= & $mainframe->getParams();
		$doc->link 	= JRoute::_('index.php?option=com_flexicontent&view=flexicontent');
		
		JRequest::setVar('limit', $mainframe->getCfg('feed_limit'));
		$rows 		= & $this->get('Feed');
				
		foreach ( $rows as $row )
		{
			// strip html from feed item title
			$title = $this->escape( $row->title );
			$title = html_entity_decode( $title );

			$category = $this->escape( $row->cattitle );
			
			// url link to article
			// & used instead of &amp; as this is converted by feed creator
			$link = JRoute::_('index.php?option=com_flexicontent&view=items&cid='. $row->catslug .'&id='.$row->slug );

			// strip html from feed item description text
			$description	= ($params->get('feed_summary', 0) ? $row->introtext.$row->fulltext : $row->introtext);
		//	$author			= $row->created_by_alias ? $row->created_by_alias : $row->author;
			@$date 			= ( $row->created ? date( 'r', strtotime($row->created) ) : '' );

			// load individual item creator class
			$item = new JFeedItem();
			$item->title 		= $title;
			$item->link 		= $link;
			$item->description 	= $description;
			$item->date			= $date;
			$item->category   	= $category;

			// loads item info into rss array
			$doc->addItem( $item );
		}
	}
}
?>