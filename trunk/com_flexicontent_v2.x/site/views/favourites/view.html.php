<?php
/**
 * @version 1.5 stable $Id: view.html.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
		global $mainframe;

		//initialize variables
		$document 	= & JFactory::getDocument();
		$menus		= & JSite::getMenu();
		$menu    	= $menus->getActive();
		$params 	= & JComponentHelper::getParams('com_flexicontent');
		$uri 		= & JFactory::getURI();

		$limitstart		= JRequest::getInt('limitstart');
		$limit			= JFactory::getApplication()->getUserStateFromRequest('com_flexicontent.favourites.limit', 'limit', $params->def('limit', 0), 'int');

		//add css file
		if (!$params->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		//allow css override
		if (file_exists(JPATH_SITE.DS.'templates'.DS.JApplication::getTemplate().DS.'css'.DS.'flexicontent.css')) {
			$document->addStyleSheet($this->baseurl.'/templates/'.JApplication::getTemplate().'/css/flexicontent.css');
		}

		// because the application sets a default page title, we need to get it
		// right from the menu item itself
		if (is_object( $menu )) {
			jimport( 'joomla.html.parameter' );
			$menu_params = new JParameter( $menu->params );		
			
			if (!$menu_params->get( 'page_title')) {
				$params->set('page_title',	JText::_( 'FLEXI_MY_FAVOURITES' ));
			}
			
		} else {
			$params->set('page_title',	JText::_( 'FLEXI_MY_FAVOURITES' ));
		}
		
		$document->setTitle($params->get('page_title'));
		$document->setMetadata( 'keywords' , $params->get('page_title') );
		
		$items 	= & $this->get('Data');
        $total 	= & $this->get('Total');
        
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
		
		$this->assign('action', 					$uri->toString());

		$this->assignRef('items' , 					$items);
		$this->assignRef('params' , 				$params);
		$this->assignRef('pageNav' , 				$pageNav);
		$this->assignRef('lists' ,	 				$lists);

		parent::display($tpl);

	}
}
?>