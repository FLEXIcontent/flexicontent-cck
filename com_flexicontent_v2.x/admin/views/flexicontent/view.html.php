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
		
		FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
		flexicontent_html::loadJQuery();
		JHTML::_('behavior.tooltip');
		
		// Special displaying when getting flexicontent version
		$layout = JRequest::getVar('layout', 'default');
		if($layout=='fversion') {
			$this->fversion($tpl, $params);
			return;
		}
		
		//Load pane behavior
		if (!FLEXI_J16GE)
			jimport('joomla.html.pane');
		// load the file system librairies
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');	
		// activate the tooltips
		JHTML::_('behavior.tooltip');

		// handle jcomments integration
		if (JPluginHelper::isEnabled('system', 'jcomments.system') || JPluginHelper::isEnabled('system', 'jcomments')) {
			$CanComments 	= 1;
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
			$CanComments 	= 0;
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
		
		// initialise template related variables
		if (!FLEXI_J16GE)
			$pane = JPane::getInstance('sliders');
		$template	= $app->getTemplate();
		$themes		= flexicontent_tmpl::getThemes();
		
		// Get data from the model
		if ( $print_logging_info )  global $fc_run_times;
		if ( $print_logging_info ) $start_microtime = microtime(true);
		$draft      = $this->get( 'Draft' );   $db->setQuery("SELECT FOUND_ROWS()");	 $totalrows['draft'] = $db->loadResult();
		$pending    = $this->get( 'Pending' );   $db->setQuery("SELECT FOUND_ROWS()");	 $totalrows['pending'] = $db->loadResult();
		$revised    = $this->get( 'Revised' );   $db->setQuery("SELECT FOUND_ROWS()");	 $totalrows['revised'] = $db->loadResult();
		$inprogress = $this->get( 'Inprogress' );   $db->setQuery("SELECT FOUND_ROWS()");	 $totalrows['inprogress'] = $db->loadResult();
		if ( $print_logging_info ) $fc_run_times['quick_sliders'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		// 1. CHECK REQUIRED NON-AUTOMATIC TASKs
		//  THEY ARE TASKs THAT USER MUST COMPLETE MANUALLY
		$existcat 	= $this->get( 'Existcat' );
		if (!FLEXI_J16GE)
			$existsec = $this->get( 'Existsec' );
		$existmenu 	= $this->get( 'Existmenu' );
		
		// 2. OPTIONAL AUTOMATIC TASKS,
		//  THESE ARE SEPARETELY CHECKED, AS THEY ARE NOT OBLIGATORY BUT RATHER RECOMMENDED
		$allplgpublish = $session->get('flexicontent.allplgpublish');
		if (($allplgpublish===NULL) || ($allplgpublish===false)) {
			$allplgpublish = $this->get( 'AllPluginsPublished' );
		}
		$optional_tasks = !$allplgpublish; // || ..
		
		// 3. OBLIGATORY AUTOMATIC TASKS, THAT WILL BLOCK COMPONENT USE UNTIL THEY ARE COMPLETED
		$postinst_integrity_ok = $session->get('flexicontent.postinstall');
		// THE FOLLOWING WILL ONLY BE DISPLAYED IF $DOPOSTINSTALL IS INCOMPLETE
		// SO WHY CALCULATE THEM, WE SKIP THEM, USER MUST LOG OUT ANYWAY TO SEE THEM ...
		if(($postinst_integrity_ok===NULL) || ($postinst_integrity_ok===false) || $optional_tasks) {
			$model 				= $this->getModel('flexicontent');
			$use_versioning = $params->get('use_versioning', 1);
			
			$existmenuitems	= $this->get( 'ExistMenuItems' );
			$existtype 			= $this->get( 'ExistType' );
			$existfields 		= $this->get( 'ExistFields' );
			
			$existfplg 			= $this->get( 'ExistFieldsPlugins' );
			$existseplg 		= $this->get( 'ExistSearchPlugin' );
			$existsyplg 		= $this->get( 'ExistSystemPlugin' );
			
			$existcats					= !$this->get('ItemsNoCat');
			$existlang	 				= $this->get( 'ExistLanguageColumn' ) && !$this->get('ItemsNoLang');
			$existdbindexes			= $this->get( 'ExistDBindexes' );
			$itemcountingdok    = $model->getItemCountingDataOK();
			$existversions 			= $this->get( 'ExistVersionsTable' );
			$existversionsdata	= !$use_versioning || $this->get( 'ExistVersionsPopulated' );
			
			$existauthors			= $this->get( 'ExistAuthorsTable' );
			$cachethumb				= $this->get( 'CacheThumbChmod' );
			$oldbetafiles			= $this->get( 'OldBetaFiles' );
			$nooldfieldsdata	= $this->get( 'NoOldFieldsData' );
			$missingversion		= !$use_versioning || !$model->checkCurrentVersionData();
			
			$initialpermission = FLEXI_J16GE ? $model->checkInitialPermission() : true;
		}
		
		// 4. SILENTLY CHECKED and EXECUTED TASKs WITHOUT ALERTING THE USER
		$this->get( 'FieldsPositions' );
		
		//build toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_DASHBOARD' ), 'flexicontent' );

		//add css and submenu to document
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');
		
		$document->addStyleDeclaration('
			.pane-sliders {
				margin: -7px 0px 0px 0px !important;
				position: relative;
			}'
		);
		
		$css =	'.install-ok { background: url(components/com_flexicontent/assets/images/accept.png) 0% 50% no-repeat transparent; padding:1px 0; width: 20px; height:16px; display:block; }
				 .install-notok { background: url(components/com_flexicontent/assets/images/delete.png) 0% 50% no-repeat transparent; padding:1px 0; width: 20px; height:16px; display:block; float:left;}';		
		$document->addStyleDeclaration($css);
		
		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();

		if (version_compare(PHP_VERSION, '5.0.0', '>')) {
			$js = "window.addEvent('domready', function(){";
			
			if($perms->CanConfig)  {
				$toolbar = JToolBar::getInstance('toolbar');
				
				if (!FLEXI_J16GE) {
					$toolbar->appendButton('Popup', 'download', JText::_('FLEXI_IMPORT_JOOMLA'), JURI::base().'index.php?option=com_flexicontent&amp;layout=import&amp;tmpl=component', 400, 300);
				}
				
				$btn_task = '';
				$popup_load_url = JURI::base().'index.php?option=com_flexicontent&layout=language&tmpl=component';
				if (FLEXI_J16GE) {
					$js .= "
						$$('li#toolbar-language a.toolbar, #toolbar-language button')
							.set('onclick', 'javascript:;')
							.set('href', '".$popup_load_url."')
							.set('rel', '{handler: \'iframe\', size: {x: 800, y: 500}, onClose: function() {}}');
					";
					JToolBarHelper::custom( $btn_task, 'language.png', 'language_f2.png', 'FLEXI_SEND_LANGUAGE', false );
					JHtml::_('behavior.modal', 'li#toolbar-language a.toolbar, #toolbar-language button');
				} else {
					$toolbar->appendButton('Popup', 'language', JText::_('FLEXI_SEND_LANGUAGE'), $popup_load_url, 800, 500);
				}
				
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
		
		
		//Create Submenu
		FLEXISubmenu('notvariable');
		
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
		$model = $this->getModel('flexicontent');
		$lists['missing_lang'] = $model->processlanguagefiles();

		// Get the default copyright values to populate the form automatically
		$mailfrom = $app->getCfg('mailfrom');
		$fromname = $app->getCfg('fromname');
		$website 	= $app->getCfg('live_site');

				
		if (!FLEXI_J16GE)
			$this->assignRef('pane'			, $pane);
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
		$this->assignRef('existmenuitems'	, $existmenuitems);
		$this->assignRef('existtype'			, $existtype);
		$this->assignRef('existfields'		, $existfields);
		$this->assignRef('existfplg'			, $existfplg);
		$this->assignRef('existseplg'			, $existseplg);
		$this->assignRef('existsyplg'			, $existsyplg);
		$this->assignRef('existcats'			, $existcats);
		$this->assignRef('existlang'			, $existlang);
		$this->assignRef('existdbindexes'	, $existdbindexes);
		$this->assignRef('itemcountingdok', $itemcountingdok);
		$this->assignRef('existversions'		, $existversions);
		$this->assignRef('existversionsdata', $existversionsdata);
		$this->assignRef('existauthors'			, $existauthors);
		$this->assignRef('cachethumb'				, $cachethumb);
		$this->assignRef('oldbetafiles'			, $oldbetafiles);
		$this->assignRef('nooldfieldsdata'	, $nooldfieldsdata);
		$this->assignRef('missingversion'		, $missingversion);
		if (FLEXI_J16GE)
			$this->assignRef('initialpermission', $initialpermission);
		
		// assign Rights to the template
		$this->assignRef('perms'		, $perms);
		$this->assignRef('document'		, $document);

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
	function quickiconButton( $link, $image, $text, $modal = 0, $modaliframe = 1 )
	{
		//initialise variables
		$lang = JFactory::getLanguage();
  		?>

		<div style="float:<?php echo ($lang->isRTL()) ? 'right' : 'left'; ?>;">
			<div class="icon">
				<?php
				if ($modal == 1) {
					JHTML::_('behavior.modal');
					$rel = $modaliframe?" rel=\"{handler: 'iframe', size: {x: 900, y: 500}}\"":'';
				?>
					<a href="<?php echo $link; ?>" style="cursor:pointer" class="modal"<?php echo $rel;?>>
				<?php
				} else {
				?>
					<a href="<?php echo $link; ?>">
				<?php
				}

					echo FLEXI_J16GE ?
						JHTML::image('administrator/components/com_flexicontent/assets/images/'.$image, $text) :
						JHTML::_('image.site', $image, '../administrator/components/com_flexicontent/assets/images/', NULL, NULL, $text) ;
				?>
					<span><?php echo $text; ?></span>
				</a>
			</div>
		</div>
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
		
		if( $data && strstr($data, '<?xml version="1.0" encoding="utf-8"?><update>') ) {
			if (!FLEXI_J16GE) {
				$xml = JFactory::getXMLparser('Simple');
				$xml->loadString($data);
				$version           = & $xml->document->version[0];
				$check['version']  = $version->data();
				$released          = & $xml->document->released[0];
				$check['released'] = $released->data();
				$check['connect']  = 1;
				$check['enabled']  = 1;
				$check['current']  = version_compare( $check['current_version'], $check['version'] );
			} else {
				$xml = JFactory::getXML($data, $isFile=false);
				$check['version']  = (string)$xml->version;
				$check['released'] = (string)$xml->released;
				$check['connect']  = 1;
				$check['enabled']  = 1;
				$check['current']  = version_compare( $check['current_version'], $check['version'] );
			}
		}
		
		return $check;
	}
	
	
	function fversion(&$tpl, &$params) {
		//updatecheck
		if( $params->get('show_updatecheck', 1) == 1) {
			$cache = JFactory::getCache('com_flexicontent');
			$cache->setCaching( 1 );
			$cache->setLifeTime( 600 );
			$check = $cache->get(array( 'FlexicontentViewFlexicontent', 'getUpdateComponent'), array('component'));
			$this->assignRef('check'		, $check);
		}
		parent::display($tpl);
	}
}
?>
