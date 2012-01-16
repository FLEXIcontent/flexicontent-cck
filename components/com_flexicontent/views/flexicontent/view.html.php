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
		$document 	= & JFactory::getDocument();
		$menus		= &JSite::getMenu();
		$menu		= $menus->getActive();
		
		// Get the page/component configuration
		if (!FLEXI_J16GE)	$params = $mainframe->getParams('com_flexicontent');
		else							$params = & JComponentHelper::getParams('com_flexicontent');
		
		//add css file
		if (!$params->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		
		//allow css override
		if (!FLEXI_J16GE) {
			if (file_exists(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'css'.DS.'flexicontent.css')) {
				$document->addStyleSheet($this->baseurl.'/templates/'.$mainframe->getTemplate().'/css/flexicontent.css');
			}
		} else {
			if (file_exists(JPATH_SITE.DS.'templates'.DS.JApplication::getTemplate().DS.'css'.DS.'flexicontent.css')) {
				$document->addStyleSheet($this->baseurl.'/templates/'.JApplication::getTemplate().'/css/flexicontent.css');
			}
		}

		$limitstart	= JRequest::getInt('limitstart');
		$limit 		= $params->def('catlimit', 0);
		$total		= $this->get('Total');
		$categories	= & $this->get('Data');
		$categories	= !is_array($categories)?array():$categories;

		// because the application sets a default page title, we need to get it
		// right from the menu item itself
		if (is_object( $menu )) {
			jimport( 'joomla.html.parameter' );
			$menu_params = new JParameter( $menu->params );		
			
			if (!$menu_params->get( 'page_title')) {
				$params->set('page_title',	(!FLEXI_J16GE ? $menu->name: $menu->title));
			}
			
		} else {
			$params->set('page_title',	JText::_( 'FLEXICONTENT_MAIN' ));
		}

		/*
		* Handle the metadata for the categories list
		*/
		$document->setTitle($params->get('page_title'));
		$document->setMetadata( 'keywords' , $params->get('page_title') );

		if (!FLEXI_J16GE) {
			if ($mainframe->getCfg('MetaTitle') == '1') {
					$mainframe->addMetaTag('title', $params->get('page_title'));
			}
		} else {
			if (JApplication::getCfg('MetaTitle') == '1') {
					$document->setMetaData('title', $params->get('page_title'));
			}
		}
		
		if ($params->get('show_feed_link', 1) == 1) {
			//add alternate feed link
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
		$this->assignRef('categories' , 			$categories);
		$this->assignRef('pageNav' , 				$pageNav);

		parent::display($tpl);
	}
}
?>