<?php
/**
 * @version 1.5 stable $Id$
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

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the Favourites View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFavourites extends JView
{
	/**
	 * Creates the item page
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		$mainframe =& JFactory::getApplication();

		//initialize variables
		$document 	= & JFactory::getDocument();
		$menus		= & JSite::getMenu();
		$menu    	= $menus->getActive();
		$params 	= & $mainframe->getParams('com_flexicontent');
		$uri 		= & JFactory::getURI();

		$limitstart		= JRequest::getInt('limitstart');
		$limit			= $mainframe->getUserStateFromRequest('com_flexicontent.favourites.limit', 'limit', $params->def('limit', 0), 'int');

		//add css file
		if (!$params->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		//allow css override
		if (file_exists(JPATH_SITE.DS.'templates'.DS.JApplication::getTemplate().DS.'css'.DS.'flexicontent.css')) {
			$document->addStyleSheet($this->baseurl.'/templates/'.JApplication::getTemplate().'/css/flexicontent.css');
		}

		$items 	= & $this->get('Data');
		$total 	= & $this->get('Total');
		
		
		// **********************
		// Calculate a page title
		// **********************
		
		// Verify menu item points to current FLEXIcontent object, IF NOT then clear page title and page class suffix
		if ( $menu && $menu->query['view'] != 'favourites' ) {
			$params->set('page_title',	'');
			$params->set('pageclass_sfx',	'');
		}
		
		// Set a page title if one was not already set
		$params->def('page_title',	JText::_( 'FLEXI_YOUR_FAVOURED_ITEMS' ));
		
		
		// *******************
		// Create page heading
		// *******************
		
		if ( !FLEXI_J16GE )
			$params->def('show_page_heading', $params->get('show_page_title'));  // J1.5: parameter name was show_page_title instead of show_page_heading
		else
			$params->def('show_page_title', $params->get('show_page_heading'));  // J2.5: to offer compatibility with old custom templates or template overrides
		
		// if above did not set the parameter, then default to NOT showing page heading (title)
		$params->def('show_page_heading', 0);
		$params->def('show_page_title', 0);
		
		// ... the page heading text
		$params->def('page_heading', $params->get('page_title'));    // J1.5: parameter name was show_page_title instead of show_page_heading
		$params->def('page_title', $params->get('page_heading'));    // J2.5: to offer compatibility with old custom templates or template overrides
				
		
		// ************************************************************
		// Create the document title, by from page title and other data
		// ************************************************************
		
		$doc_title = $params->get( 'page_title' );
		
		// Check and prepend or append site name
		if (FLEXI_J16GE) {  // Not available in J1.5
			// Add Site Name to page title
			if ($mainframe->getCfg('sitename_pagetitles', 0) == 1) {
				$doc_title = $mainframe->getCfg('sitename') ." - ". $doc_title ;
			}
			elseif ($mainframe->getCfg('sitename_pagetitles', 0) == 2) {
				$doc_title = $doc_title ." - ". $mainframe->getCfg('sitename') ;
			}
		}
		
		// Finally, set document title
		$document->setTitle($doc_title);
		

		// ************************
		// Set document's META tags
		// ************************
		
		// ** writting both old and new way as an example
		if (!FLEXI_J16GE) {
			if ($mainframe->getCfg('MetaTitle') == '1') 	$mainframe->addMetaTag('title', $params->get('page_title'));
		} else {
			if (JApplication::getCfg('MetaTitle') == '1') $document->setMetaData('title', $params->get('page_title'));
		}
		
        
		//ordering
		$filter_order		= JRequest::getCmd('filter_order', 'i.title');
		$filter_order_Dir	= JRequest::getCmd('filter_order_Dir', 'ASC');
		$filter				= JRequest::getString('filter');
		
		$lists						= array();
		$lists['filter_order']		= $filter_order;
		$lists['filter_order_Dir'] 	= $filter_order_Dir;
		$lists['filter']			= $filter;
						
		// Create the pagination object
		jimport('joomla.html.pagination');
		
		$pageNav 	= new JPagination($total, $limitstart, $limit);
		
		$fav_link    = JRoute::_( JRequest::getInt('Itemid') ? 'index.php?Itemid='.JRequest::getInt('Itemid') : 'index.php?view=favourites', false );
		$print_link  = JRoute::_('index.php?view=favourites&pop=1&tmpl=component');
		$pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));
		
		$this->assignRef('action', 			$fav_link);  // $uri->toString()
		$this->assignRef('print_link' ,	$print_link);
		$this->assignRef('pageclass_sfx' ,	$pageclass_sfx);
		$this->assignRef('items' , 			$items);
		$this->assignRef('params' , 		$params);
		$this->assignRef('pageNav' , 		$pageNav);
		$this->assignRef('lists' ,	 		$lists);

		parent::display($tpl);

	}
}
?>