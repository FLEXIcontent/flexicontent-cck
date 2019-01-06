<?php
/**
 * @version 1.5 stable $Id: view.html.php 1940 2014-08-29 17:55:03Z ggppdk $
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

jimport('legacy.view.legacy');

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
		$app      = JFactory::getApplication();
		$jinput   = $app->input;

		$option   = $jinput->getCmd('option', '');
		$view     = $jinput->getCmd('view', '');

		$document = JFactory::getDocument();
		$menus    = $app->getMenu();
		$menu     = $menus->getActive();
		$uri      = JUri::getInstance();

		// Get view's Model
		$model  = $this->getModel();

		// Get tag and set tag parameters as VIEW's parameters (tag parameters are merged with component/page(=menu item) and optionally with tag cloud parameters)
		$tag = $model->getTag();

		// Raise a 404 error, if either tag doesn't exist or access to it isn't permitted, maybe handle access like we do for items ?
		if (empty($tag))
		{
			$msg = JText::_('Tag not found');
			throw new Exception($msg, 404);
		}

		// Get parameters via model
		$params  = $model->getParams();



		// ***
		// *** Get data from the model
		// ***

		$items   = $this->get('Data');
		$total   = $this->get('Total');


		// ***
		// *** Bind Fields to items and RENDER their display HTML, but check for document type, due to Joomla issue with system
		// *** plugins creating JDocument in early events forcing it to be wrong type, when format as url suffix is enabled
		// ***

		$items 	= FlexicontentFields::getFields($items, $view, $params);


		// ************************************************************************
		// Calculate CSS classes needed to add special styling markups to the items
		// ************************************************************************

		flexicontent_html::calculateItemMarkups($items, $params);


		// ***
		// *** Load needed JS libs & CSS styles
		// ***

		JHtml::_('behavior.framework', true);
		flexicontent_html::loadFramework('jQuery');
		flexicontent_html::loadFramework('flexi_tmpl_common');

		// Add css files to the document <head> section (also load CSS joomla template override)
		if (!$params->get('disablecss', ''))
		{
			$document->addStyleSheetVersion($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH);
		}
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css'))
		{
			$document->addStyleSheetVersion($this->baseurl.'/templates/'.$app->getTemplate().'/css/flexicontent.css', FLEXI_VHASH);
		}


		// **********************************************************
		// Calculate a (browser window) page title and a page heading
		// **********************************************************

		// Verify menu item points to correct view, and any others (significant) URL variables must match or be empty
		if ( $menu )
		{
			$view_ok     = 'tags'   == @$menu->query['view'];

			// These URL variables must match or be empty:
			$tid_ok      = $tag->id == (int) @$menu->query['id'];

			$menu_matches = $view_ok && $tid_ok;
		}
		else
		{
			$menu_matches = false;
		}

		// MENU ITEM matched, use its page heading (but use menu title if the former is not set)
		if ( $menu_matches )
		{
			$default_heading = $menu->title;

			// Cross set (show_) page_heading / page_title for compatibility of J2.5+ with J1.5 template (and for J1.5 with J2.5 template)
			$params->def('page_heading', $params->get('page_title',   $default_heading));
			$params->def('page_title',   $params->get('page_heading', $default_heading));
		  $params->def('show_page_heading', $params->get('show_page_title',   0));
		  $params->def('show_page_title',   $params->get('show_page_heading', 0));
		}

		// MENU ITEM did not match, clear page title (=browser window title) and page heading so that they are calculated below
		else
		{
			// Also clear some other menu options
			//$params->set('pageclass_sfx',	'');  // CSS class SUFFIX is behavior, so do not clear it ?

			// Calculate default page heading (=called page title in J1.5), which in turn will be document title below !! ...
			// meta_params->get('page_title') is meant for <title> but let's use as ... default page heading
			$default_heading = JText::_('FLEXI_ITEMS_WITH_TAG') . ': ' . $tag->name;

			// Decide to show page heading (=J1.5 page title), this is always yes
			$show_default_heading = 1;

			// Set both (show_) page_heading / page_title for compatibility of J2.5+ with J1.5 template (and for J1.5 with J2.5 template)
			$params->set('page_title',   $default_heading);
			$params->set('page_heading', $default_heading);
		  $params->set('show_page_heading', $show_default_heading);
			$params->set('show_page_title',   $show_default_heading);
		}

		// Prevent showing the page heading if ... currently no reason
		if ( 0 )
		{
			$params->set('show_page_heading', 0);
			$params->set('show_page_title',   0);
		}



		// ************************************************************
		// Create the document title, by from page title and other data
		// ************************************************************

		// Use the page heading as document title, (already calculated above via 'appropriate' logic ...)
		$doc_title = $params->get( 'page_title' );

		// Check and prepend or append site name to page title
		if ( $doc_title != $app->getCfg('sitename') ) {
			if ($app->getCfg('sitename_pagetitles', 0) == 1) {
				$doc_title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $doc_title);
			}
			elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
				$doc_title = JText::sprintf('JPAGETITLE', $doc_title, $app->getCfg('sitename'));
			}
		}

		// Finally, set document title
		$document->setTitle($doc_title);


		// ************************
		// Set document's META tags
		// ************************

		// Workaround for Joomla not setting the default value for 'robots', so component must do it
		$app_params = $app->getParams();
		if (($_mp=$app_params->get('robots')))    $document->setMetadata('robots', $_mp);

		// Overwrite with menu META data if menu matched
		if ($menu_matches) {
			if (($_mp=$menu->params->get('menu-meta_description')))  $document->setDescription( $_mp );
			if (($_mp=$menu->params->get('menu-meta_keywords')))     $document->setMetadata('keywords', $_mp);
			if (($_mp=$menu->params->get('robots')))                 $document->setMetadata('robots', $_mp);
			if (($_mp=$menu->params->get('secure')))                 $document->setMetadata('secure', $_mp);
		}


		/**
		 * Add canonical link (if needed and different than current URL), also preventing Joomla default (SEF plugin)
		 */

		if ($params->get('add_canonical'))
		{
			// Create desired REL canonical URL
			$start = $jinput->getInt('start', '');
			$ucanonical = JRoute::_(FlexicontentHelperRoute::getTagRoute($tag->id) . ($start ? "&start=" . $start : ''));
			flexicontent_html::setRelCanonical($ucanonical);
		}

		// Disable features, that are not supported by the view
		$params->set('use_filters',0);
		$params->set('show_alpha',0);
		$params->set('clayout_switcher',0);

		$lists = array();

		//ordering
		$lists['filter_order']     = $jinput->get('filter_order', 'i.title', 'cmd');
		$lists['filter_order_Dir'] = $jinput->get('filter_order_Dir', 'ASC', 'cmd');
		$lists['filter']           = $jinput->get('filter', '', 'string');



		// ***
		// *** Create the pagination object
		// ***

		$pageNav = $this->get('pagination');

		$_revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
		foreach($_GET as $i => $v)
		{
			// URL-encode filter values
			if (substr($i, 0, 6) === "filter")
			{
				if (is_array($v))
				{
					foreach($v as $ii => &$vv)
					{
						$vv = str_replace('&', '__amp__', $vv);
						$vv = strtr(rawurlencode($vv), $_revert);
						$pageNav->setAdditionalUrlParam($i.'['.$ii.']', $vv);
					}
					unset($vv);
				}
				else
				{
					$v = str_replace('&', '__amp__', $v);
					$v = strtr(rawurlencode($v), $_revert);
					$pageNav->setAdditionalUrlParam($i, $v);
				}
			}

			// Make sure all URL variables are added to the pagination URLs
			else
			{
				if (is_array($v))
				{
					foreach($v as $ii => &$vv)
					{
						$pageNav->setAdditionalUrlParam($i.'['.$ii.']', $vv);
					}
				}
				else
				{
					$pageNav->setAdditionalUrlParam($i, $v);
				}
			}
		}

		$_sh404sef = defined('SH404SEF_IS_RUNNING') && JFactory::getConfig()->get('sef');
		if ($_sh404sef) $pageNav->setAdditionalUrlParam('limit', $model->getState('limit'));

		// Create links, etc
		$link = JRoute::_(FlexicontentHelperRoute::getTagRoute($tag->slug), false);

		//$print_link = JRoute::_('index.php?view=tags&id='.$tag->slug.'&pop=1&tmpl=component');
		$curr_url   = str_replace('&', '&amp;', $_SERVER['REQUEST_URI']);
		$print_link = $curr_url .(strstr($curr_url, '?') ? '&amp;'  : '?').'pop=1&amp;tmpl=component&amp;print=1';

		$pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));

		$this->action = $link;  // $uri->toString()
		$this->print_link = $print_link;
		$this->tag = $tag;
		$this->items = $items;
		$this->lists = $lists;
		$this->params = $params;
		$this->pageNav = $pageNav;
		$this->pageclass_sfx = $pageclass_sfx;

		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }

		parent::display($tpl);

		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}
}
?>