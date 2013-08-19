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

jimport('joomla.application.component.view');

/**
 * HTML View class for the Tags View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewTags extends JViewLegacy
{
	/**
	 * Creates the page's display
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		//initialize variables
		$document = JFactory::getDocument();
		$app   = JFactory::getApplication();
		$menus = $app->getMenu();
		$menu  = $menus->getActive();
		$uri   = JFactory::getURI();
		$view  = JRequest::getCmd('view');
		
		// Get view's Model
		$model = $this->getModel();
		
		// Get tag and set tag parameters as VIEW's parameters (tag parameters are merged with component/page(=menu item) and optionally with tag cloud parameters)
		$tag = $model->getTag();
		if ( empty($tag) ) {
			// Raise a 404 error, if tag doesn't exist or access isn't permitted, maybe move this into model ??
			$tid = JRequest::getInt('id', 0);
			$msg = JText::sprintf( $tid ? 'Tag id was not set (is 0)' : 'Tag #%d not found', $tid );
			if (FLEXI_J16GE) throw new Exception($msg, 404); else JError::raiseError(404, $msg);
		}
		
		// Get parameters via model
		$params  = $model->getParams();
		
		// Get various data from the model
		$items   = $this->get('Data');
		$total   = $this->get('Total');
		
		// Make sure field values were retrieved e.g. we need 'item->categories' for template classes
		$items 	= FlexicontentFields::getFields($items, $view, $params);
		
		
		// ********************************
		// Load needed JS libs & CSS styles
		// ********************************
		FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
		flexicontent_html::loadFramework('jQuery');
		flexicontent_html::loadFramework('flexi_tmpl_common');
		
		//add css file
		if (!$params->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		
		//allow css override
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
			$document->addStyleSheet($this->baseurl.'/templates/'.$app->getTemplate().'/css/flexicontent.css');
		}
		
		
		// **********************
		// Calculate a page title
		// **********************
		
		// Verify menu item points to current FLEXIcontent object, IF NOT then clear page title and page class suffix
		if ( $menu ) {
			$view_ok     = @$menu->query['view']     == 'tags';
			$tid_ok      = @$menu->query['id']       == $tag->id;
			$menu_matches = $view_ok && $tid_ok;
			
			if ( !$menu_matches ) {
				$params->set('page_title', '');
				$params->set('page_heading', '');
				// These are behavior, so do not clear ?
				//$params->set('show_page_heading', '');
				//$params->set('pageclass_sfx',	'');
			}
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
		
		if (FLEXI_J16GE) {
			if ($menu && ($_mp=$menu->params->get('menu-meta_description')))  $document->setDescription( $_mp );
			if ($menu && ($_mp=$menu->params->get('menu-meta_keywords')))     $document->setMetadata('keywords', $_mp);
			if ($menu && ($_mp=$menu->params->get('robots')))                 $document->setMetadata('robots', $_mp);
		}
		
		
		// ************************************************************
		// Create the document title, by from page title and other data
		// ************************************************************
		
		$doc_title = $params->get( 'page_title' );
		
		// Check and prepend or append site name
		if (FLEXI_J16GE) {  // Not available in J1.5
			// Add Site Name to page title
			if ($app->getCfg('sitename_pagetitles', 0) == 1) {
				$doc_title = $app->getCfg('sitename') ." - ". $doc_title ;
			}
			elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
				$doc_title = $doc_title ." - ". $app->getCfg('sitename') ;
			}
		}
		
		// Finally, set document title
		$document->setTitle($doc_title);
		
		
		// ************************
		// Set document's META tags
		// ************************
		
		// ** writting both old and new way as an example
		// Deprecated <title> tag is used instead by search engines
		/*if (!FLEXI_J16GE) {
			if ($app->getCfg('MetaTitle') == '1') 	$app->addMetaTag('title', $params->get('page_title'));
		} else {
			if ($app->getCfg('MetaTitle') == '1') $document->setMetaData('title', $params->get('page_title'));
		}*/
		
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
		$pageNav = $this->get('pagination');
		
		// Create links
		$link = JRoute::_(FlexicontentHelperRoute::getTagRoute($tag->slug), false);
		$print_link = JRoute::_('index.php?view=tags&id='.$tag->slug.'&pop=1&tmpl=component');
		
		$pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));
		
		$this->assignRef('action',    $link);  // $uri->toString()
		$this->assignRef('print_link',$print_link);
		$this->assignRef('tag',       $tag);
		$this->assignRef('items',     $items);
		$this->assignRef('lists',     $lists);
		$this->assignRef('params',    $params);
		$this->assignRef('pageNav',   $pageNav);
		$this->assignRef('pageclass_sfx', $pageclass_sfx);
		
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }
		
		parent::display($tpl);
		
		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}
}
?>