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
		$db       = JFactory::getDBO();
		$print_logging_info = $params->get('print_logging_info');
		
		// Special displaying when getting flexicontent version
		$layout = JRequest::getVar('layout', 'default');
		if($layout=='fversion') {
			$this->fversion($tpl, $params);
			return;
		}
		
		// Load the file system librairies
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');
		
		// activate the tooltips
		//JHTML::_('behavior.tooltip');

		// handle jcomments integration
		if (JPluginHelper::isEnabled('system', 'jcomments')) {
			$JComments_Installed 	= 1;
			$destpath		= JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'plugins';
			$dest 			= $destpath.DS.'com_flexicontent.plugin.php';
			$source 		= JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'jcomments'.DS.'com_flexicontent.plugin.php';
			
			if (!JFile::exists($dest)) {
				if (!JFolder::exists($destpath)) { 
					if (!JFolder::create($destpath)) { 
						JError::raiseWarning(100, JText::_('FLEXIcontent: Unable to create jComments plugin folder'));
					}
				}
				if (!JFile::copy($source, $dest)) {
					JError::raiseWarning(100, JText::_('FLEXIcontent: Unable to copy jComments plugin'));
				} else {
					$app->enqueueMessage(JText::_('Copied FLEXIcontent jComments plugin'));
				}
			}
		} else {
			$JComments_Installed 	= 0;
		}

		// handle joomfish integration
		if (JPluginHelper::isEnabled('system', 'jfdatabase')) {
			$files = new stdClass;
			$files->fields = new stdClass;
			$files->files  = new stdClass;
			$files->tags   = new stdClass;
			$files->fields->dest   = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_joomfish'.DS.'contentelements'.DS.'flexicontent_fields.xml';
			$files->fields->source = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'joomfish'.DS.'flexicontent_fields.xml';
			$files->files->dest    = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_joomfish'.DS.'contentelements'.DS.'flexicontent_files.xml';
			$files->files->source  = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'joomfish'.DS.'flexicontent_files.xml';
			$files->tags->dest     = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_joomfish'.DS.'contentelements'.DS.'flexicontent_tags.xml';
			$files->tags->source   = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'joomfish'.DS.'flexicontent_tags.xml';

			foreach ($files as $file) {
				if (!JFile::exists($file->dest)) {
					JFile::copy($file->source, $file->dest);
				}
			}
		}
		
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
		if (!FLEXI_J16GE)
			$existsec = $model->getExistsec();
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
		
		if(($postinst_integrity_ok===NULL) || ($postinst_integrity_ok===false)) {
			$use_versioning = $params->get('use_versioning', 1);
			
			$existtype 			= $model->getExistType();
			$existmenuitems	= $model->getExistMenuItems();
			$existfields 		= $model->getExistFields();
			
			$existfplg 			= $model->getExistFieldsPlugins();
			$existseplg 		= $model->getExistSearchPlugin();
			$existsyplg 		= $model->getExistSystemPlugin();
			
			$existcats					= !$model->getItemsNoCat();
			$existlang	 				= $model->getExistLanguageColumns() && !$model->getItemsNoLang();
			$existversions 			= $model->getExistVersionsTable();
			$existversionsdata	= !$use_versioning || $model->getExistVersionsPopulated();
			$existauthors			= $model->getExistAuthorsTable();

			$deprecatedfiles	= $model->getDeprecatedFiles();
			$nooldfieldsdata	= $model->getNoOldFieldsData();
			$missingversion		= true; //!$use_versioning || !$model->checkCurrentVersionData();
			$cachethumb				= $model->getCacheThumbChmod();
			
			$existdbindexes    = ! (boolean) ($missingindexes = $model->getExistDBindexes($check_only=false));
			$itemcountingdok   = $model->getItemCountingDataOK();
			$initialpermission = $model->checkInitialPermission();
			
		} else if ($optional_tasks) {  // IF optional tasks do not recheck instead just set the FLAGS to true
			$existtype = $existmenuitems = $existfields = true;
			$existfplg = $existseplg = $existsyplg = true;
		  $existcats = $existlang = $existversions = $existversionsdata = $existauthors = true;
		  $deprecatedfiles = $nooldfieldsdata = $missingversion = $cachethumb = true;
		  $existdbindexes = $itemcountingdok = $initialpermission = true;
		  $missingindexes = array();
		}
		
		
		
		// **************************
		// Add css and js to document
		// **************************
		
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH);
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH);
		
		$css =	'.install-ok { background: url(components/com_flexicontent/assets/images/accept.png) 0% 50% no-repeat transparent; padding:1px 0; width: 20px; height:16px; display:block; }
				 .install-notok { background: url(components/com_flexicontent/assets/images/delete.png) 0% 50% no-repeat transparent; padding:1px 0; width: 20px; height:16px; display:block; float:left;}';		
		$document->addStyleDeclaration($css);
		
		
		
		// *****************************
		// Get user's global permissions
		// *****************************
		
		$perms = FlexicontentHelperPerm::getPerm();
		
		
		
		// ************************
		// Create Submenu & Toolbar
		// ************************
		
		// Create Submenu (and also check access to current view)
		FLEXISubmenu('notvariable');
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_DASHBOARD' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'flexicontent' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		if (version_compare(PHP_VERSION, '5.0.0', '>')) {
			$js = "jQuery(document).ready(function(){";
			
			if($perms->CanConfig)  {
				$toolbar = JToolBar::getInstance('toolbar');
				
				$btn_task = '';
				$popup_load_url = JURI::base().'index.php?option=com_flexicontent&layout=import&tmpl=component';
				if (!FLEXI_J16GE) {
					$js .= "
						jQuery('#toolbar-download a.toolbar, #toolbar-download button')
							.attr('onclick', 'javascript:;')
							.attr('href', '".$popup_load_url."')
							.attr('rel', '{handler: \'iframe\', size: {x: 800, y: 500}, onClose: function() {}}');
					";
					JToolBarHelper::custom( $btn_task, 'download.png', 'download_f2.png', 'FLEXI_IMPORT_JOOMLA', false );
					JHtml::_('behavior.modal', '#toolbar-download a.toolbar, #toolbar-download button');
				} else {
					//$toolbar->appendButton('Popup', 'download', JText::_('FLEXI_IMPORT_JOOMLA'), str_replace('&', '&amp;', $popup_load_url), 400, 300);
				}
				
				/*$btn_task = '';
				$popup_load_url = JURI::base().'index.php?option=com_flexicontent&layout=language&tmpl=component';
				if (FLEXI_J16GE) {
					$js .= "
						jQuery('#toolbar-language a.toolbar, #toolbar-language button')
							.attr('onclick', 'javascript:;')
							.attr('href', '".$popup_load_url."')
							.attr('rel', '{handler: \'iframe\', size: {x: 800, y: 500}, onClose: function() {}}');
					";
					JToolBarHelper::custom( $btn_task, 'language.png', 'language_f2.png', 'FLEXI_SEND_LANGUAGE', false );
					JHtml::_('behavior.modal', '#toolbar-language a.toolbar, #toolbar-language button');
				} else {
					$toolbar->appendButton('Popup', 'language', JText::_('FLEXI_SEND_LANGUAGE'), str_replace('&', '&amp;', $popup_load_url), 800, 500);
				}*/
				
				$session = JFactory::getSession();
				$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
				$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
				$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
				$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
				JToolBarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
			}
			
			$js .= "});";
			$document->addScriptDeclaration($js);
		}
		
		// Lists
		jimport('joomla.filesystem.folder');
		$lists 		= array();
		$options 	= array();
		$folder 	= JPATH_ADMINISTRATOR.DS.'language';
		$langs 		= JFolder::folders($folder);
		$activelang = JComponentHelper::getParams('com_languages')->get('administrator', 'en-GB');
		
		foreach ($langs as $lang) {
			$options[] = JHTML::_('select.option', $lang, $lang);		
		}
		$lists['languages'] = JHTML::_('select.genericlist', $options, 'lang', '', 'value', 'text', $activelang);

		// Missing files
		$lists['missing_lang'] = $model->processlanguagefiles();

		// Get the default copyright values to populate the form automatically
		$mailfrom = $app->getCfg('mailfrom');
		$fromname = $app->getCfg('fromname');
		$website 	= $app->getCfg('live_site');

				
		$this->assignRef('pending'		, $pending);
		$this->assignRef('revised'		, $revised);
		$this->assignRef('draft'		, $draft);
		$this->assignRef('inprogress'	, $inprogress);
		$this->assignRef('totalrows'	, $totalrows);
		$this->assignRef('existcat'		, $existcat);
		if (!FLEXI_J16GE)
			$this->assignRef('existsec'		, $existsec);
		$this->assignRef('existmenu'	, $existmenu);
		$this->assignRef('template'		, $template);
		$this->assignRef('params'		, $params);
		$this->assignRef('lists'		, $lists);
		$this->assignRef('activelang'	, $activelang);
		$this->assignRef('mailfrom'		, $mailfrom);
		$this->assignRef('fromname'		, $fromname);
		$this->assignRef('website'		, $website);

		// install check
		$this->assignRef('dopostinstall'	, $postinst_integrity_ok);
		$this->assignRef('allplgpublish'	, $allplgpublish);
		
		$this->assignRef('existtype'			, $existtype);
		$this->assignRef('existmenuitems'	, $existmenuitems);
		$this->assignRef('existfields'		, $existfields);
		
		$this->assignRef('existfplg'			, $existfplg);
		$this->assignRef('existseplg'			, $existseplg);
		$this->assignRef('existsyplg'			, $existsyplg);
		
		$this->assignRef('existcats'			, $existcats);
		$this->assignRef('existlang'			, $existlang);
		$this->assignRef('existversions'		, $existversions);
		$this->assignRef('existversionsdata', $existversionsdata);
		$this->assignRef('existauthors'			, $existauthors);
		
		$this->assignRef('deprecatedfiles'	, $deprecatedfiles);
		$this->assignRef('nooldfieldsdata'	, $nooldfieldsdata);
		$this->assignRef('missingversion'		, $missingversion);
		$this->assignRef('cachethumb'				, $cachethumb);

		$this->assignRef('existdbindexes'	, $existdbindexes); $this->assignRef('missingindexes', $missingindexes);
		$this->assignRef('itemcountingdok', $itemcountingdok);
		$this->assignRef('initialpermission', $initialpermission);
		
		// assign Rights to the template
		$this->assignRef('perms'		, $perms);
		$this->assignRef('document'		, $document);

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
		$link_attribs = $modal ? 'onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', '.((int)(!$modal_create_iframe)).', '.$modal_width.', '.$modal_height.'); return false;"' : '';
		$img_attribs  = ' class="fc-board-btn-img"';
  	?>
		<span class="fc-board-button" style="float:<?php echo ($lang->isRTL()) ? 'right' : 'left'; ?>;">
			<span class="fc-board-button-inner">
				
				<?php if ($link) : ?><a href="<?php echo $link; ?>" class="fc-board-button-link" <?php echo $link_attribs; ?>><?php endif; ?>
					<?php echo FLEXI_J16GE ?
						JHTML::image('administrator/components/com_flexicontent/assets/images/'.$image, $text, $img_attribs) :
						JHTML::_('image.site', $image, '../administrator/components/com_flexicontent/assets/images/', NULL, NULL, $text, $attribs); ?>
					<span class="fc-board-btn-text <?php echo $link ? '' : ' fcdisabled'; ?>"><?php echo $text; ?></span>
				<?php if ($link) : ?></a><?php endif; ?>
				
			</span>
		</span>
		<?php
	}
	
	
	/**
	 * Fetch the version from the flexicontent.org server
	 */
	static function getUpdateComponent()
	{
		// Read installation file
		$manifest_path = JPATH_ADMINISTRATOR .DS. 'components' .DS. 'com_flexicontent' .DS. 'manifest.xml';
		$com_xml = JApplicationHelper::parseXMLInstallFile( $manifest_path );
		
		// Version checking URL
		$url = 'http://www.flexicontent.org/flexicontent_update.xml';
		$data = '';
		$check = array();
		$check['connect'] = 0;
		$check['current_version'] = $com_xml['version'];
		$check['current_creationDate'] = $com_xml['creationDate'];

		//try to connect via cURL
		if (function_exists('curl_init') && function_exists('curl_exec')) {
			$ch = @curl_init();
			
			@curl_setopt($ch, CURLOPT_URL, $url);
			@curl_setopt($ch, CURLOPT_HEADER, 0);
			//http code is greater than or equal to 300 ->fail
			@curl_setopt($ch, CURLOPT_FAILONERROR, 1);
			@curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			//timeout of 5s just in case
			@curl_setopt($ch, CURLOPT_TIMEOUT, 5);
						
			$data = @curl_exec($ch);
						
			@curl_close($ch);
		}
		
		//try to connect via fsockopen
		if(function_exists('fsockopen') && $data == '') {

			$errno = 0;
			$errstr = '';

			//timeout handling: 5s for the socket and 5s for the stream = 10s
			$fsock = @fsockopen("www.flexicontent.org", 80, $errno, $errstr, 5);
			//$fsock = @fsockopen("flexicontent.googlecode.com", 80, $errno, $errstr, 5);
		
			if ($fsock) {
				@fputs($fsock, "GET /flexicontent_update.xml HTTP/1.1\r\n");
				@fputs($fsock, "HOST: www.flexicontent.org\r\n");
				@fputs($fsock, "Connection: close\r\n\r\n");
				
				//force stream timeout...
				@stream_set_blocking($fsock, 1);
				@stream_set_timeout($fsock, 5);
				 
				$get_info = false;
				while (!@feof($fsock))
				{
					if ($get_info)
					{
						$data .= @fread($fsock, 1024);
					}
					else
					{
						if (@fgets($fsock, 1024) == "\r\n")
						{
							$get_info = true;
						}
					}
				}
				@fclose($fsock);
				
				//need to check data cause http error codes aren't supported here
				if(!strstr($data, '<?xml version="1.0" encoding="utf-8"?><update>')) {
					$data = '';
				}
			}
		}

		//try to connect via fopen
		if (function_exists('fopen') && ini_get('allow_url_fopen') && $data == '') {
			
			//set socket timeout
			ini_set('default_socket_timeout', 5);
			
			$handle = @fopen ($url, 'r');
			
			//set stream timeout
			@stream_set_blocking($handle, 1);
			@stream_set_timeout($handle, 5);
			
			$data	= @fread($handle, 1000);
			
			@fclose($handle);
		}
		
		if( $data && strstr($data, '<?xml') )
		{
			$xml = JFactory::getXML($data, $isFile=false);
			$check['version']  = (string)$xml->version;
			$check['released'] = (string)$xml->released;
			$check['connect']  = 1;
			$check['enabled']  = 1;
			$check['current']  = version_compare( str_replace(' ', '', $check['current_version']), str_replace(' ', '', $check['version']) );
		}
		
		return $check;
	}
	
	
	function fversion(&$tpl, &$params)
	{
		// Cache update check of FLEXIcontent version
		$cache = JFactory::getCache('com_flexicontent');
		$cache->setCaching( 1 );
		$cache->setLifeTime( 3600 );  // Set expire time (hard-code this to 1 hour), to avoid server load
		$check = $cache->get(array( 'FlexicontentViewFlexicontent', 'getUpdateComponent'), array('component'));
		$this->assignRef('check', $check);
		
		parent::display($tpl);
	}
}
?>
