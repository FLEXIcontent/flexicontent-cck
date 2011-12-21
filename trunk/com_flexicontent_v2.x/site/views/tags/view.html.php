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
		//initialize variables
		$document 	= & JFactory::getDocument();
		$menu		= & JSite::getMenu();
		$uri 		= & JFactory::getURI();
		$item    	= $menu->getActive();
		$params 	= & JComponentHelper::getParams('com_flexicontent');
		
		$tid 			= JRequest::getInt('id', 0);
		$limitstart		= JRequest::getInt('limitstart');
		$filter			= JRequest::getString('filter');
		$limit			= JFactory::getApplication()->getUserStateFromRequest('com_flexicontent.tags.limit', 'limit', $params->def('limit', 0), 'int');

		//add css file
		if (!$params->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		//allow css override
		if (file_exists(JPATH_SITE.DS.'templates'.DS.JApplication::getTemplate().DS.'css'.DS.'flexicontent.css')) {
			$document->addStyleSheet($this->baseurl.'/templates/'.JApplication::getTemplate().'/css/flexicontent.css');
		}

		//params
		@$params->def( 'page_title', $item->name);

		//pathway
		$pathway 	= & JFactory::getApplication()->getPathWay();
		@$pathway->setItemName( 1, $item->name );

		//Set Page title
		if (!@$item->name) {
			$document->setTitle($params->get('page_title'));
			$document->setMetadata( 'keywords' , $params->get('page_title') );
		}
		
		$items 	= & $this->get('Data');
		$tag	= & $this->get('Tag');
		$total 	= & $this->get('Total');
        
		// @TODO check that as it seems to be dirty :(
		$uri  			=& JFactory::getURI();
		$base 			= $uri->getScheme() . '://' . $uri->getHost();
		$start = JRequest::getVar('start', '');
		$start = $start ? "&start=".$start : "";
		$ucanonical 	= $base .'/'. JRoute::_(FlexicontentHelperRoute::getTagRoute($tag->id).$start);
		if ($params->get('add_canonical')) {
			$document->addHeadLink( $ucanonical, 'canonical', 'rel', '' );
		}
		        
		//set 404 if category doesn't exist or access isn't permitted
		if ( empty($tag) ) {
			return JError::raiseError( 404, JText::sprintf( 'Tag #%d not found', $tid ) );
		}
        
        //ordering
		$filter_order		= JRequest::getCmd('filter_order', 'i.title');
		$filter_order_Dir	= JRequest::getCmd('filter_order_Dir', 'ASC');
		
		$lists						= array();
		$lists['filter_order']		= $filter_order;
		$lists['filter_order_Dir'] 	= $filter_order_Dir;
		$lists['filter']			= $filter;
		
				
		// Create the pagination object
		jimport('joomla.html.pagination');
		
		$pageNav 	= new JPagination($total, $limitstart, $limit);
		
		$this->assign('action', 					$uri->toString());

		$this->assignRef('items' , 					$items);
		$this->assignRef('tag' , 					$tag);
		$this->assignRef('params' , 				$params);
		$this->assignRef('pageNav' , 				$pageNav);
		$this->assignRef('lists' ,	 				$lists);

		parent::display($tpl);

	}
}
?>