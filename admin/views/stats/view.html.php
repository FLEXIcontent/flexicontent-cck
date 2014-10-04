<?php
/**
 * @version 1.5 stable $Id: view.html.php 1869 2014-03-12 12:18:40Z ggppdk $
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
 * HTML View class for the Stats View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewStats extends JViewLegacy
{
	/**
	 * Creates the Entrypage
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		//initialise variables
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();
		
		// Get data from the model
		$genstats   = $this->get( 'Generalstats' );
		$popular    = $this->get( 'Popular' );
		$rating     = $this->get( 'Rating' );
		$worstrating= $this->get( 'WorstRating' );
		$favoured   = $this->get( 'Favoured' );
		$statestats = $this->get( 'Statestats' );
		$votesstats	= $this->get( 'Votesstats' );
		$creators   = $this->get( 'Creators' );
		$editors    = $this->get( 'Editors' );
		
		//add css and submenu to document
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');
		
		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// **************************
		// Create Submenu and toolbar
		// **************************
		FLEXISubmenu('CanStats');
		
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_STATISTICS' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'stats' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		//JToolBarHelper::Back();
		if ($perms->CanConfig) {
			//JToolBarHelper::divider(); JToolBarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolBarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		//Load pane behavior
		if (!FLEXI_J16GE) {
			jimport('joomla.html.pane');
			$pane = JPane::getInstance('Tabs');
			$this->assignRef('pane'       , $pane);
		}
		$this->assignRef('genstats'		, $genstats);
		$this->assignRef('popular'		, $popular);
		$this->assignRef('rating'			, $rating);
		$this->assignRef('worstrating', $worstrating);
		$this->assignRef('favoured'		, $favoured);
		$this->assignRef('statestats'	, $statestats);
		$this->assignRef('votesstats'	, $votesstats);
		$this->assignRef('creators'		, $creators);
		$this->assignRef('editors'		, $editors);
		
		parent::display($tpl);
	}
}