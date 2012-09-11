<?php
/**
 * @version 1.5 stable $Id: view.html.php 1308 2012-05-15 10:37:44Z ggppdk $
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
 * HTML View class for the Items View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewTags extends JView
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
		$items  = & $this->get('Data');
		$tag    = & $this->get('Tag');
		$total  = $this->get('Total');
		
		// Request variables, WARNING, must be loaded after retrieving items, because limitstart may have been modified
		$limitstart = JRequest::getInt('limitstart');
		$limit      = $mainframe->getUserStateFromRequest('com_flexicontent.tags.limit', 'limit', $params->def('limit', 0), 'int');
		
		// Set tag parameters as VIEW's parameters (tag parameters are merged with component/page(=menu item) and optionally with tag cloud parameters)
		$params = & $tag->parameters;
		
		//set 404 if tag doesn't exist or access isn't permitted
		if ( empty($tag) ) {
			$tid = JRequest::getInt('id', 0);
			return JError::raiseError( 404, JText::sprintf( 'Tag #%d not found', $tid ) );
		}
		
		
		// **********************
		// Calculate a page title
		// **********************
		$m_id = (int) @$menu->query['id'] ;
		
		// Verify menu item points to current FLEXIcontent object, IF NOT then clear page title and page class suffix
		if ( $menu && ($menu->query['view'] != 'tags' || $m_id != JRequest::getInt('id') ) ) {
			$params->set('page_title',	'');
			$params->set('pageclass_sfx',	'');
		}
		
		// Set a page title if one was not already set
		$params->def('page_title',	JText::_('FLEXI_ITEMS_WITH_TAG').": ". $tag->name );
		
		
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
		


		// Add rel canonical html head link tag
		// @TODO check that as it seems to be dirty :(
		$base  = $uri->getScheme() . '://' . $uri->getHost();
		$start = JRequest::getVar('start', '');
		$start = $start ? "&start=".$start : "";
		$ucanonical 	= $base . JRoute::_(FlexicontentHelperRoute::getTagRoute($tag->id).$start);
		if ($params->get('add_canonical')) {
			$document->addHeadLink( $ucanonical, 'canonical', 'rel', '' );
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
		
		$tag_link   = JRoute::_(FlexicontentHelperRoute::getTagRoute($tag->id), false);
		$print_link = JRoute::_('index.php?view=tags&id='.$tag->id.'&pop=1&tmpl=component');
		$pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));
		
		$this->assignRef('tag' , 				$tag);
		$this->assignRef('action', 			$tag_link);  // $uri->toString()
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