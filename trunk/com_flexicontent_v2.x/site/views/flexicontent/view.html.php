<?php
/**
 * @version 1.5 stable $Id: view.html.php 1887 2014-04-24 23:53:14Z ggppdk $
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
 * HTML View class for the FLEXIcontent View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFlexicontent extends JViewLegacy
{
	/**
	 * Creates the page's display
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		//initialize variables
		$app      = JFactory::getApplication();
		$document = JFactory::getDocument();
		$menus = $app->getMenu();
		$menu  = $menus->getActive();
		$uri   = JFactory::getURI();
		
		// Get view's Model
		$model  = $this->getModel();
		
		// Get parameters via model
		$params  = $model->getParams();
		
		// Get various data from the model
		$categories = $this->get('Data');
		$total   = $this->get('Total');
		
		// Make sure categories is and array
		$categories = !is_array($categories) ? array() : $categories;
		
		// ********************************
		// Load needed JS libs & CSS styles
		// ********************************
		
		//add css file
		if (!$params->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		
		//allow css override
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
			$document->addStyleSheet($this->baseurl.'/templates/'.$app->getTemplate().'/css/flexicontent.css');
		}
		
		
		// **********************************************************
		// Calculate a (browser window) page title and a page heading
		// **********************************************************
		
		// Verify menu item points to current FLEXIcontent object
		if ( $menu ) {
			$view_ok     = 'flexicontent' == @$menu->query['view'];
			$menu_matches = $view_ok;
			//$menu_params = FLEXI_J16GE ? $menu->params : new JParameter($menu->params);  // Get active menu item parameters
		} else {
			$menu_matches = false;
		}
		
		// MENU ITEM matched, use its page heading (but use menu title if the former is not set)
		if ( $menu_matches ) {
			$default_heading = FLEXI_J16GE ? $menu->title : $menu->name;
			
			// Cross set (show_) page_heading / page_title for compatibility of J2.5+ with J1.5 template (and for J1.5 with J2.5 template)
			$params->def('page_heading', $params->get('page_title',   $default_heading));
			$params->def('page_title',   $params->get('page_heading', $default_heading));
		  $params->def('show_page_heading', $params->get('show_page_title',   0));
		  $params->def('show_page_title',   $params->get('show_page_heading', 0));
		}
		
		// MENU ITEM did not match, clear page title (=browser window title) and page heading so that they are calculated below
		else {
			// Clear some menu parameters
			//$params->set('pageclass_sfx',	'');  // CSS class SUFFIX is behavior, so do not clear it ?
			
			// Calculate default page heading (=called page title in J1.5), which in turn will be document title below !! ...
			// meta_params->get('page_title') is meant for <title> but let's use as ... default page heading
			$default_heading = JText::_( 'FLEXI_CATEGORIES' );
			
			// Decide to show page heading (=J1.5 page title), this is always yes
			$show_default_heading = 1;
			
			// Set both (show_) page_heading / page_title for compatibility of J2.5+ with J1.5 template (and for J1.5 with J2.5 template)
			$params->set('page_title',   $default_heading);
			$params->set('page_heading', $default_heading);
		  $params->set('show_page_heading', $show_default_heading);
			$params->set('show_page_title',   $show_default_heading);
		}
		
		// Prevent showing the page heading if ... currently no reason
		if ( 0 ) {
			$params->set('show_page_heading', 0);
			$params->set('show_page_title',   0);
		}
		
		
		
		// ************************************************************
		// Create the document title, by from page title and other data
		// ************************************************************
		
		// Use the page heading as document title, (already calculated above via 'appropriate' logic ...)
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
		
		// Overwrite with menu META data if menu matched
		if (FLEXI_J16GE) {
			if ($menu_matches) {
				if (($_mp=$menu->params->get('menu-meta_description')))  $document->setDescription( $_mp );
				if (($_mp=$menu->params->get('menu-meta_keywords')))     $document->setMetadata('keywords', $_mp);
				if (($_mp=$menu->params->get('robots')))                 $document->setMetadata('robots', $_mp);
				if (($_mp=$menu->params->get('secure')))                 $document->setMetadata('secure', $_mp);
			}
		}
		
		// Add feed link
		if ($params->get('show_feed_link', 1) == 1) {
			$link	= '&format=feed';
			$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
			$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
			$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
			$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);
		}
		
		// Create the pagination object
		$pageNav = $this->get('pagination');
		$pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));
		
		$this->assignRef('categories',$categories);
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