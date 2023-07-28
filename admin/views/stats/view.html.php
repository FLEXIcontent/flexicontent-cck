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
defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentViewBaseRecords', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_records.php');

/**
 * HTML View class for the Stats View
 */
class FlexicontentViewStats extends FlexicontentViewBaseRecords
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
		$session  = JFactory::getSession();
		
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

		// ************************************************** New data*********************************************************************************************************************//
		$itemsgraph  			   = $this->get('Itemsgraph');
		$unpopular   			   = $this->get('Unpopular');
		$totalitemspublish         = $this->get('Itemspublish');
		$totalitemsunpublish       = $this->get('Itemsunpublish');
		$totalitemswaiting         = $this->get('Itemswaiting');
		$totalitemsprogress        = $this->get('Itemsprogress');
		$metadescription           = $this->get('Itemsmetadescription');
		$metakeywords              = $this->get('Itemsmetakeywords');
		
		// ************************************************** New data*********************************************************************************************************************//
		
		
		// **************************
		// Add css and js to document
		// **************************
		
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));



		//*****************************************************************Adicionar as biblitecas*******************************************************************************************//
		$document->addStyleSheet('//netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.css');
		$document->addScript(JUri::root(true).'/components/com_flexicontent/librairies/esl/esl.js');
		//*****************************************************************Adicionar as biblitecas*******************************************************************************************//
		
		
		
		// *****************************
		// Get user's global permissions
		// *****************************
		
		$perms = FlexicontentHelperPerm::getPerm();
		
		
		
		// ************************
		// Create Submenu & Toolbar
		// ************************
		
		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanStats');
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_STATISTICS' );
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, 'icon-signal' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		//JToolbarHelper::Back();
		if ($perms->CanConfig)
		{
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		$this->genstats = $genstats;
		$this->popular = $popular;
		$this->rating = $rating;
		$this->worstrating = $worstrating;
		$this->favoured = $favoured;
		$this->statestats = $statestats;
		$this->votesstats = $votesstats;
		$this->creators = $creators;
		$this->editors = $editors;

		$this->itemsgraph = $itemsgraph;
		$this->unpopular = $unpopular;
		$this->totalitemspublish = $totalitemspublish;
		$this->totalitemsunpublish = $totalitemsunpublish;
		$this->totalitemswaiting = $totalitemswaiting;
		$this->totalitemsprogress = $totalitemsprogress;
		$this->metadescription = $metadescription;
		$this->metakeywords = $metakeywords;

		$this->sidebar = JHtmlSidebar::render();
		parent::display($tpl);
	}
}