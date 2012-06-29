<?php
/**
 * @version 1.5 stable $Id: view.html.php 1109 2012-01-16 01:05:22Z ggppdk $
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
 * HTML View class for the FLEXIcontent View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFlexicontent extends JView
{
	/**
	 * Creates the Forms for the View
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		$mainframe =& JFactory::getApplication();

		//initialize variables
		$document = & JFactory::getDocument();
		$menus    = & JSite::getMenu();
		$menu     = $menus->getActive();
		$uri      = & JFactory::getURI();
		
		// Get the PAGE/COMPONENT parameters (WARNING: merges current menu item parameters in J1.5 but not in J1.6+)
		$params = clone($mainframe->getParams('com_flexicontent'));
		
		// In J1.6+ the above function does not merge current menu item parameters, it behaves like JComponentHelper::getParams('com_flexicontent') was called
		if (FLEXI_J16GE && $menu) {
			$menuParams = new JRegistry;
			$menuParams->loadJSON($menu->params);
			$params->merge($menuParams);
		}
		
		//add css file
		if (!$params->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		
		//allow css override
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'css'.DS.'flexicontent.css')) {
			$document->addStyleSheet($this->baseurl.'/templates/'.$mainframe->getTemplate().'/css/flexicontent.css');
		}
		
		// Get data from the model		
		$categories = & $this->get('Data');
		$categories = !is_array($categories)?array():$categories;
		$total  = $this->get('Total');
		
		// Request variables, WARNING, must be loaded after retrieving items, because limitstart may have been modified
		$limitstart = JRequest::getInt('limitstart');
		$limit      = $params->def('catlimit', 0);
		// Set a page title if one was not already set
		if ( !$params->get('page_title') ) {
			$params->set('page_title',	JText::_( 'FLEXICONTENT_MAIN' ));
		}
		
		if (FLEXI_J16GE) {  // Not available in J1.5
			// Add Site Name to page title
			if ($mainframe->getCfg('sitename_pagetitles', 0) == 1) {
				$params->set('page_title', $mainframe->getCfg('sitename') ." - ". $params->get( 'page_title' ));
			}
			elseif ($mainframe->getCfg('sitename_pagetitles', 0) == 2) {
				$params->set('page_title', $params->get( 'page_title' ) ." - ". $mainframe->getCfg('sitename'));
			}
		}

		// Set title and metadata
		$document->setTitle( $params->get('page_title') );
		$document->setMetadata( 'keywords' , $params->get('page_title') );
		
		// ** writting both old and new way as an example
		if (!FLEXI_J16GE) {
			if ($mainframe->getCfg('MetaTitle') == '1') {
					$mainframe->addMetaTag('title', $params->get('page_title'));
			}
		} else {
			if (JApplication::getCfg('MetaTitle') == '1') {
					$document->setMetaData('title', $params->get('page_title'));
			}
		}
		
		// Add alternate feed link
		if ($params->get('show_feed_link', 1) == 1) {
			$link	= '&format=feed';
			$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
			$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
			$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
			$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);
		}
		
		// Create the pagination object
		jimport('joomla.html.pagination');
		
		$pageNav 	= new JPagination($total, $limitstart, $limit);
		
		$this->assignRef('params' , 				$params);
		$this->assignRef('categories' , 		$categories);
		$this->assignRef('pageNav' , 				$pageNav);

		parent::display($tpl);
	}
}
?>