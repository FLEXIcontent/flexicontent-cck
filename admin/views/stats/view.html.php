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
#[AllowDynamicProperties]
class FlexicontentViewStats extends FlexicontentViewBaseRecords
{
	/** @var mixed $creators */
	public mixed $creators = null;
	/** @var mixed $editors */
	public mixed $editors = null;
	/** @var mixed $favoured */
	public mixed $favoured = null;
	/** @var mixed $genstats */
	public mixed $genstats = null;
	/** @var mixed $itemsgraph */
	public mixed $itemsgraph = null;
	/** @var mixed $metadescription */
	public mixed $metadescription = null;
	/** @var mixed $metakeywords */
	public mixed $metakeywords = null;
	/** @var mixed $popular */
	public mixed $popular = null;
	/** @var mixed $rating */
	public mixed $rating = null;
	/** @var mixed $sidebar */
	public mixed $sidebar = null;
	/** @var mixed $statestats */
	public mixed $statestats = null;
	/** @var mixed $totalitemsprogress */
	public mixed $totalitemsprogress = null;
	/** @var mixed $totalitemspublish */
	public mixed $totalitemspublish = null;
	/** @var mixed $totalitemsunpublish */
	public mixed $totalitemsunpublish = null;
	/** @var mixed $totalitemswaiting */
	public mixed $totalitemswaiting = null;
	/** @var mixed $unpopular */
	public mixed $unpopular = null;
	/** @var mixed $votesstats */
	public mixed $votesstats = null;
	/** @var mixed $worstrating */
	public mixed $worstrating = null;


	/**
	 * Creates the Entrypage
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		//initialise variables
		$document = \Joomla\CMS\Factory::getDocument();
		$user     = \Joomla\CMS\Factory::getUser();
		$session  = \Joomla\CMS\Factory::getSession();
		
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
		
		!\Joomla\CMS\Factory::getLanguage()->isRtl()
			? /* J5/J6 WebAsset: */ $document->getWebAssetManager()->registerAndUseStyle('fc-style', \Joomla\CMS\Uri\Uri::root().'components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
			: /* J5/J6 WebAsset: */ $document->getWebAssetManager()->registerAndUseStyle('fc-style', \Joomla\CMS\Uri\Uri::root().'components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
		!\Joomla\CMS\Factory::getLanguage()->isRtl()
			? /* J5/J6 WebAsset: */ $document->getWebAssetManager()->registerAndUseStyle('fc-style', \Joomla\CMS\Uri\Uri::root().'components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
			: /* J5/J6 WebAsset: */ $document->getWebAssetManager()->registerAndUseStyle('fc-style', \Joomla\CMS\Uri\Uri::root().'components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));



		//*****************************************************************Adicionar as biblitecas*******************************************************************************************//
		/* J5/J6 WebAsset: */ $document->getWebAssetManager()->registerAndUseStyle('font-awesome', '//netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.css');
		/* J5/J6 WebAsset: */ $document->getWebAssetManager()->registerAndUseScript('fc-script', \Joomla\CMS\Uri\Uri::root().'components/com_flexicontent/librairies/esl/esl.js');
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
		$doc_title = \Joomla\CMS\Language\Text::_( 'FLEXI_STATISTICS' );
		$site_title = $document->getTitle();
		\Joomla\CMS\Toolbar\ToolbarHelper::title( $doc_title, 'icon-signal' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		//\Joomla\CMS\Toolbar\ToolbarHelper::Back();
		if ($perms->CanConfig)
		{
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			\Joomla\CMS\Toolbar\ToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
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

		$this->sidebar = null;

		if(FLEXI_J30GE && !FLEXI_J40GE) $this->sidebar = JHtmlSidebar::render();
		if(FLEXI_J40GE) $this->sidebar = \Joomla\CMS\HTML\Helpers\Sidebar::render();

		parent::display($tpl);
	}
}