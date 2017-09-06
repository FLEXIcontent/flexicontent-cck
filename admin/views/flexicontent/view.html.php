<?php
/**
 * @version 1.5 stable $Id: view.html.php 1900 2014-05-03 07:25:51Z ggppdk $
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
 * HTML View class for the FLEXIcontent View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFlexicontent extends JViewLegacy
{
	/**
	 * Creates the Entrypage
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		$app      = JFactory::getApplication();
		$config   = JFactory::getConfig();
		$params   = JComponentHelper::getParams('com_flexicontent');
		$document	= JFactory::getDocument();
		$session  = JFactory::getSession();
		$user     = JFactory::getUser();		
		$db       = JFactory::getDbo();
		$print_logging_info = $params->get('print_logging_info');

		// Load the file system librairies
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');
		
		// activate the tooltips
		//JHtml::_('behavior.tooltip');
		
		// Get model
		$model = $this->getModel('flexicontent');
		
		// initialise template related variables
		$template	= $app->getTemplate();
		$themes		= flexicontent_tmpl::getThemes();
		
		// Get data from the model
		if ( $print_logging_info )  global $fc_run_times;
		if ( $print_logging_info ) $start_microtime = microtime(true);
		$totalrows = array();
		$_total = 0;
		$draft      = $model->getDraft($_total);       $totalrows['draft'] = $_total;
		$pending    = $model->getPending($_total);     $totalrows['pending'] = $_total;
		$revised    = $model->getRevised($_total);     $totalrows['revised'] = $_total;
		$inprogress = $model->getInprogress($_total);  $totalrows['inprogress'] = $_total;
		if ( $print_logging_info ) $fc_run_times['quick_sliders'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		// 1. CHECK REQUIRED NON-AUTOMATIC TASKs
		//  THEY ARE TASKs THAT USER MUST COMPLETE MANUALLY
		$existcat 	= $model->getExistcat();
		$existmenu 	= $model->getExistmenu();
		
		// 2. OPTIONAL AUTOMATIC TASKS,
		//  THESE ARE SEPARETELY CHECKED, AS THEY ARE NOT OBLIGATORY BUT RATHER RECOMMENDED
		$allplgpublish = $session->get('flexicontent.allplgpublish');
		if (($allplgpublish===NULL) || ($allplgpublish===false)) {
			$allplgpublish = $model->getAllPluginsPublished();
		}
		$optional_tasks = !$allplgpublish; // || ..
		
		// 3. OBLIGATORY AUTOMATIC TASKS, THAT WILL BLOCK COMPONENT USE UNTIL THEY ARE COMPLETED
		$postinst_integrity_ok = $session->get('flexicontent.postinstall');
		// THE FOLLOWING WILL ONLY BE DISPLAYED IF $DOPOSTINSTALL IS INCOMPLETE
		// SO WHY CALCULATE THEM, WE SKIP THEM, USER MUST LOG OUT ANYWAY TO SEE THEM ...
		
		if(($postinst_integrity_ok===NULL) || ($postinst_integrity_ok===false))
		{
			$use_versioning = $params->get('use_versioning', 1);
			
			$existtype 			= $model->getExistType();
			$existmenuitems	= $model->getExistMenuItems();
			$existfields 		= $model->getExistFields();
			
			$existfplg 			= $model->getExistFieldsPlugins();
			$existseplg 		= $model->getExistSearchPlugin();
			$existsyplg 		= $model->getExistSystemPlugin();
			
			$existcats					= !$model->getItemsNoCat();
			$langsynced	 				= $model->getExistLanguageColumns() && !$model->getItemsBadLang();
			$existversions 			= $model->getExistVersionsTable();
			$existversionsdata	= !$use_versioning || $model->getExistVersionsPopulated();
			$existauthors			= $model->getExistAuthorsTable();

			$deprecatedfiles	= $model->getDeprecatedFiles();
			$nooldfieldsdata	= $model->getNoOldFieldsData();
			$missingversion		= true; //!$use_versioning || !$model->checkCurrentVersionData();
			$cachethumb				= $model->getCacheThumbPerms();
			
			$existdbindexes    = ! (boolean) ($missingindexes = $model->getExistDBindexes($check_only=false));
			$itemcountingdok   = $model->getItemCountingDataOK();
			$initialpermission = $model->checkInitialPermission();
		}

		else if ($optional_tasks)  // IF optional tasks do not recheck instead just set the FLAGS to true
		{
			$existtype = $existmenuitems = $existfields = true;
			$existfplg = $existseplg = $existsyplg = true;
		  $existcats = $langsynced = $existversions = $existversionsdata = $existauthors = true;
		  $deprecatedfiles = $nooldfieldsdata = $missingversion = $cachethumb = true;
		  $existdbindexes = $itemcountingdok = $initialpermission = true;
		  $missingindexes = array();
		}
		
		
		
		// **************************
		// Add css and js to document
		// **************************
		
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);



		// *****************************
		// Get user's global permissions
		// *****************************
		
		$perms = FlexicontentHelperPerm::getPerm();
		
		
		
		// ************************
		// Create Submenu & Toolbar
		// ************************
		
		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu(null);
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_DASHBOARD' );
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, 'flexicontent' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		$js = "jQuery(document).ready(function(){";

		// Create the toolbar
		$toolbar = JToolbar::getInstance('toolbar');
		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);

		if($perms->CanConfig)
		{
			if (0) // FLEXI_J37GE
			{
				$btn_task = '';
				$popup_load_url = JUri::base().'index.php?option=com_flexicontent&layout=import&format=raw';
				//$toolbar->appendButton('Popup', 'download', JText::_('FLEXI_IMPORT_JOOMLA'), str_replace('&', '&amp;', $popup_load_url), 780, 500);
				$js .= "
					jQuery('#toolbar-download a.toolbar, #toolbar-download button').attr('href', '".$popup_load_url."')
						.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 780, 500, function(){document.body.innerHTML=\'<span class=\"fc_loading_msg\">"
							.$loading_msg."</span>\'; window.location.reload(false)}, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('FLEXI_IMPORT_JOOMLA'), 2)."\'}); return false;');
				";
				JToolbarHelper::custom( $btn_task, 'download.png', 'download_f2.png', 'FLEXI_IMPORT_JOOMLA', false );
			}

			if (0) // TODO evaluate for e.g. submiting a template
			{
				$btn_task = '';
				$popup_load_url = JUri::base().'index.php?option=com_flexicontent&layout=language&tmpl=component';
				//$toolbar->appendButton('Popup', 'language', JText::_('FLEXI_SEND_LANGUAGE'), str_replace('&', '&amp;', $popup_load_url), 780, 540);
				$js .= "
					jQuery('#toolbar-language a.toolbar, #toolbar-language button').attr('href', '".$popup_load_url."')
						.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 780, 540, false, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('FLEXI_SEND_LANGUAGE'), 2)."\'}); return false;');
				";
				JToolbarHelper::custom( $btn_task, 'language.png', 'language_f2.png', 'FLEXI_SEND_LANGUAGE', false );
			}

			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}

		$js .= "});";
		$document->addScriptDeclaration($js);
		
		// Lists
		jimport('joomla.filesystem.folder');
		$lists 		= array();
		$options 	= array();
		$folder 	= JPATH_ADMINISTRATOR.DS.'language';
		$langs 		= JFolder::folders($folder);
		$activelang = JComponentHelper::getParams('com_languages')->get('administrator', 'en-GB');
		
		foreach ($langs as $lang) {
			$options[] = JHtml::_('select.option', $lang, $lang);		
		}
		$lists['languages'] = JHtml::_('select.genericlist', $options, 'lang', '', 'value', 'text', $activelang);

		// Missing files
		$lists['missing_lang'] = $model->createLanguagePack();

		// Get the default copyright values to populate the form automatically
		$mailfrom = $app->getCfg('mailfrom');
		$fromname = $app->getCfg('fromname');
		$website 	= $app->getCfg('live_site');

				
		$this->pending = $pending;
		$this->revised = $revised;
		$this->draft = $draft;
		$this->inprogress = $inprogress;
		$this->totalrows = $totalrows;
		$this->existcat = $existcat;
		$this->existmenu = $existmenu;
		$this->template = $template;
		$this->params = $params;
		$this->lists = $lists;
		$this->activelang = $activelang;
		$this->mailfrom = $mailfrom;
		$this->fromname = $fromname;
		$this->website = $website;

		// install check
		$this->dopostinstall = $postinst_integrity_ok;
		$this->allplgpublish = $allplgpublish;
		
		$this->existtype = isset($existtype) ? $existtype : null;
		$this->existmenuitems = isset($existmenuitems) ? $existmenuitems : null;
		$this->existfields = isset($existfields) ? $existfields : null;
		
		$this->existfplg = isset($existfplg) ? $existfplg : null;
		$this->existseplg = isset($existseplg) ? $existseplg : null;
		$this->existsyplg = isset($existsyplg) ? $existsyplg : null;
		
		$this->existcats = isset($existcats) ? $existcats : null;
		$this->langsynced = isset($langsynced) ? $langsynced : null;
		$this->existversions = isset($existversions) ? $existversions : null;
		$this->existversionsdata = isset($existversionsdata) ? $existversionsdata : null;
		$this->existauthors = isset($existauthors) ? $existauthors : null;
		
		$this->deprecatedfiles = isset($deprecatedfiles) ? $deprecatedfiles : null;
		$this->nooldfieldsdata = isset($nooldfieldsdata) ? $nooldfieldsdata : null;
		$this->missingversion = isset($missingversion) ? $missingversion : null;
		$this->cachethumb = isset($cachethumb) ? $cachethumb : null;

		$this->existdbindexes = isset($existdbindexes) ? $existdbindexes : null;
		$this->missingindexes = isset($missingindexes) ? $missingindexes : null;
		$this->itemcountingdok = isset($itemcountingdok) ? $itemcountingdok : null;
		$this->initialpermission = isset($initialpermission) ? $initialpermission : null;
		
		// assign Rights to the template
		$this->perms = $perms;
		$this->document = $document;

		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
	}


	/**
	 * Creates the buttons view
	 *
	 * @param string $link targeturl
	 * @param string $image path to image
	 * @param string $text image description
	 * @param boolean $modal 1 for loading in modal
	 */
	function quickiconButton( $link, $image, $text, $modal = 0, $modal_create_iframe = 1, $modal_width=0, $modal_height=0)
	{
		//initialise variables
		$lang = JFactory::getLanguage();
		$link_attribs = empty($_SERVER['HTTPS']) && $modal
			? ' onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', '.((int)(!$modal_create_iframe)).', '.$modal_width.', '.$modal_height.', false, {\'title\': \''.flexicontent_html::encodeHTML(JText::_($text), 2).'\'}); return false;"' : '';
		$img_attribs  = ' class="fc-board-btn-img"';
  	?>
		<span class="fc-board-button">
			<span class="fc-board-button-inner">

				<?php if ($link) : ?><a href="<?php echo $link; ?>" class="fc-board-button-link" <?php echo $link_attribs; ?>><?php endif; ?>
					<?php echo JHtml::image('administrator/components/com_flexicontent/assets/images/'.$image, $text, $img_attribs); ?>
					<span class="fc-board-btn-text <?php echo $link ? '' : ' fcdisabled'; ?>"><?php echo $text; ?></span>
				<?php if ($link) : ?></a><?php endif; ?>

			</span>
		</span>
		<?php
	}
}