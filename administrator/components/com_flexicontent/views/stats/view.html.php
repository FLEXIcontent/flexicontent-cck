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
		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$document	= JFactory::getDocument();
		$pane   	= JPane::getInstance('Tabs');
		$user 		= JFactory::getUser();
		
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
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		
		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Create Submenu and check access
		FLEXISubmenu('CanStats');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_STATISTICS' ), 'stats' );
		//JToolBarHelper::Back();
		if ($perms->CanConfig) {
			//JToolBarHelper::divider(); JToolBarHelper::spacer();
			JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		}
		
		$this->assignRef('pane'				, $pane);
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