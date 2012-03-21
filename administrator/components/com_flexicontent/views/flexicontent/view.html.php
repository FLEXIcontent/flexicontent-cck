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

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the FLEXIcontent View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFlexicontent extends JView
{
	/**
	 * Creates the Entrypage
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		$mainframe = &JFactory::getApplication();
		$params 	= & JComponentHelper::getParams('com_flexicontent');
		
		//Load pane behavior
		jimport('joomla.html.pane');
		// load the file system librairies
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');	
		// activate the tooltips
		JHTML::_('behavior.tooltip');

		// handle jcomments integration
		if (JPluginHelper::isEnabled('system', 'jcomments.system') || JPluginHelper::isEnabled('system', 'jcomments')) {
			$CanComments 	= 1;
			$dest 			= JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'plugins'.DS.'com_flexicontent.plugin.php';
			$source 		= JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'jcomments'.DS.'com_flexicontent.plugin.php';
			if (!JFile::exists($dest)) {
				JFile::copy($source, $dest);
			}
		} else {
			$CanComments 	= 0;
		}

		// handle joomfish integration
		if (JPluginHelper::isEnabled('system', 'jfdatabase')) {
			$files = new stdClass;
			$files->fields->dest 	= JPATH_ADMINISTRATOR.DS.'components'.DS.'com_joomfish'.DS.'contentelements'.DS.'flexicontent_fields.xml';
			$files->fields->source 	= JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'joomfish'.DS.'flexicontent_fields.xml';
			$files->files->dest 	= JPATH_ADMINISTRATOR.DS.'components'.DS.'com_joomfish'.DS.'contentelements'.DS.'flexicontent_files.xml';
			$files->files->source 	= JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'joomfish'.DS.'flexicontent_files.xml';
			$files->tags->dest 		= JPATH_ADMINISTRATOR.DS.'components'.DS.'com_joomfish'.DS.'contentelements'.DS.'flexicontent_tags.xml';
			$files->tags->source 	= JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'joomfish'.DS.'flexicontent_tags.xml';

			foreach ($files as $file) {
				if (!JFile::exists($file->dest)) {
					JFile::copy($file->source, $file->dest);
				}
			}
		}
		
		//initialise variables
		$document	= & JFactory::getDocument();
		$pane   	= & JPane::getInstance('sliders');
		$template	= $mainframe->getTemplate();
		$user		= & JFactory::getUser();		
		// Get data from the model
		$db =& JFactory::getDBO();
		$draft	= & $this->get( 'Draft' );   $db->setQuery("SELECT FOUND_ROWS()");	 $totalrows['draft'] = $db->loadResult();
		$pending = & $this->get( 'Pending' );   $db->setQuery("SELECT FOUND_ROWS()");	 $totalrows['pending'] = $db->loadResult();
		$revised = & $this->get( 'Revised' );   $db->setQuery("SELECT FOUND_ROWS()");	 $totalrows['revised'] = $db->loadResult();
		$inprogress = & $this->get( 'Inprogress' );   $db->setQuery("SELECT FOUND_ROWS()");	 $totalrows['inprogress'] = $db->loadResult();
		$themes		= flexicontent_tmpl::getThemes();
		
		$session  =& JFactory::getSession();
		
		// 1. CHECK REQUIRED NON-AUTOMATIC TASKs
		//  THEY ARE TASKs THAT USER MUST COMPLETE MANUALLY
		$existcat 	= & $this->get( 'Existcat' );
		$existsec 	= & $this->get( 'Existsec' );
		$existmenu 	= & $this->get( 'Existmenu' );
		
		// 2. OPTIONAL AUTOMATIC TASKS,
		//  THESE ARE SEPARETELY CHECKED, AS THEY ARE NOT OBLIGATORY BUT RATHER RECOMMENDED
		$allplgpublish = $session->get('flexicontent.allplgpublish');
		if (($allplgpublish===NULL) || ($allplgpublish===false)) {
			$allplgpublish 		= & $this->get( 'AllPluginsPublished' );
		}
		$optional_tasks = !$allplgpublish; // || ..
		
		// 3. OBLIGATORY AUTOMATIC TASKS, THAT WILL BLOCK COMPONENT USE UNTIL THEY ARE COMPLETED
		$dopostinstall = $session->get('flexicontent.postinstall');
		// THE FOLLOWING WILL ONLY BE DISPLAYED IF $DOPOSTINSTALL IS INCOMPLETE
		// SO WHY CALCULATE THEM, WE SKIP THEM, USER MUST LOG OUT ANYWAY TO SEE THEM ...
		if(($dopostinstall===NULL) || ($dopostinstall===false) || $optional_tasks) {
			$model 				= $this->getModel('flexicontent');
			$use_versioning = $params->get('use_versioning', 1);
			
			$existmenuitems		= & $this->get( 'ExistMenuItems' );
			$existtype 			= & $this->get( 'ExistType' );
			$existfields 		= & $this->get( 'ExistFields' );
			$existfplg 			= & $this->get( 'ExistFieldsPlugins' );
			$existseplg 		= & $this->get( 'ExistSearchPlugin' );
			$existsyplg 		= & $this->get( 'ExistSystemPlugin' );
			$existlang	 		= $this->get( 'ExistLanguageColumn' ) && !$this->get('ItemsNoLang');
			$existversions 		= & $this->get( 'ExistVersionsTable' );
			$existversionsdata	= & $this->get( 'ExistVersionsPopulated' );
			$existauthors			= & $this->get( 'ExistAuthorsTable' );
			//$cachethumb			= & $this->get( 'CacheThumbChmod' );  // For J1.7 ?
			$oldbetafiles		= & $this->get( 'OldBetaFiles' );
			$nooldfieldsdata	= & $this->get( 'NoOldFieldsData' );
			$missingversion		= ($use_versioning&&$model->checkCurrentVersionData());
			//$initialpermission	= $model->checkInitialPermission();  // For J1.7
		}
		
		// 4. SILENTLY CHECKED and EXECUTED TASKs WITHOUT ALERTING THE USER
		$this->get( 'FieldsPositions' );
		
		//build toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_DASHBOARD' ), 'flexicontent' );

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		
		$css =	'.install-ok { background: url(components/com_flexicontent/assets/images/accept.png) 0% 50% no-repeat transparent; padding:1px 0; width: 20px; height:16px; display:block; }
				 .install-notok { background: url(components/com_flexicontent/assets/images/delete.png) 0% 50% no-repeat transparent; padding:1px 0; width: 20px; height:16px; display:block; float:left;}';		
		$document->addStyleDeclaration($css);

		$session  =& JFactory::getSession();

		if (FLEXI_ACCESS) {
			$user =& JFactory::getUser();
			$CanAdd 		= ($user->gid < 25) ? ((FAccess::checkComponentAccess('com_content', 'submit', 'users', $user->gmid)) || (FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all'))) : 1;
			$CanAddCats 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'addcats', 'users', $user->gmid) : 1;
			$CanCats 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid) : 1;
			$CanTypes 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'types', 'users', $user->gmid) : 1;
			$CanFields 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'fields', 'users', $user->gmid) : 1;
			$CanTags 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'tags', 'users', $user->gmid) : 1;
			$CanAuthors 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_users', 'manage', 'users', $user->gmid) : 1;
			$CanArchives 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid) : 1;
			$CanFiles	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'files', 'users', $user->gmid) : 1;
			$CanStats	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'stats', 'users', $user->gmid) : 1;
			$CanRights	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexiaccess', 'manage', 'users', $user->gmid) : 1;
			$CanPlugins	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_plugins', 'manage', 'users', $user->gmid) : 1;
			$CanComments 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_jcomments', 'manage', 'users', $user->gmid) : $CanComments;
			$CanTemplates	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'templates', 'users', $user->gmid) : 1;
			$CanImport		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'import', 'users', $user->gmid) : 1;
			$CanIndex		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'index', 'users', $user->gmid) : 1;
		} else {
			$CanAdd			= 1;
			$CanAddCats 	= 1;
			$CanCats 		= 1;
			$CanTypes 		= 1;
			$CanFields		= 1;
			$CanTags 		= 1;
			$CanAuthors		= 1;
			$CanArchives	= 1;
			$CanFiles		= 1;
			$CanStats		= 1;
			$CanRights		= 1;
			$CanPlugins		= 1;
			$CanTemplates	= 1;
			$CanImport		= 1;
			$CanIndex		= 1;
		}

		if (version_compare(PHP_VERSION, '5.0.0', '>')) {
			if ($user->gid > 24) {
				$toolbar=&JToolBar::getInstance('toolbar');
				$toolbar->appendButton('Popup', 'download', JText::_('FLEXI_IMPORT_JOOMLA'), JURI::base().'index.php?option=com_flexicontent&amp;layout=import&amp;tmpl=component', 400, 300);
				$toolbar->appendButton('Popup', 'language', JText::_('FLEXI_SEND_LANGUAGE'), JURI::base().'index.php?option=com_flexicontent&amp;layout=language&amp;tmpl=component', 800, 500);
				JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
			}
			
		}
		
		//Create Submenu
		$dopostinstall = $session->get('flexicontent.postinstall');
		$allplgpublish = $session->get('flexicontent.allplgpublish');
		
		FLEXISubmenu('notvariable', $dopostinstall);
		
		//updatecheck
		if($params->get('show_updatecheck', 1) == 1) {
		$cache = & JFactory::getCache('com_flexicontent');
		$cache->setCaching( 1 );
		$cache->setLifeTime( 100 );
		$check = $cache->get(array( 'FlexicontentViewFlexicontent', 'getUpdateComponent'), array('component'));
		$this->assignRef('check'		, $check);
		}
		
		// Lists
		jimport('joomla.filesystem.folder');
		$lists 		= array();
		$options 	= array();
		$folder 	= JPATH_ADMINISTRATOR.DS.'language';
   		$langs 		= JFolder::folders($folder);
		$activelang =& JFactory::getLanguage()->_lang;

		foreach ($langs as $lang) {
			$options[] = JHTML::_('select.option', $lang, $lang);		
		}
   		$lists['languages'] = JHTML::_('select.genericlist', $options, 'lang', '', 'value', 'text', $activelang);

		// Missing files
		$model = $this->getModel('flexicontent');
		$lists['missing_lang'] = $model->processlanguagefiles();

		// Get the default copyright values to populate the form automatically
		$config =& JFactory::getConfig();
		$mailfrom 	= $config->getValue('config.mailfrom');
		$fromname 	= $config->getValue('config.fromname');
		$website 	= $config->getValue('config.live_site');

				
		$this->assignRef('pane'			, $pane);
		$this->assignRef('pending'		, $pending);
		$this->assignRef('revised'		, $revised);
		$this->assignRef('draft'		, $draft);
		$this->assignRef('inprogress'	, $inprogress);
		$this->assignRef('totalrows'	, $totalrows);
		$this->assignRef('existcat'		, $existcat);
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
		$this->assignRef('dopostinstall'	, $dopostinstall);
		$this->assignRef('allplgpublish'	, $allplgpublish);
		$this->assignRef('existmenuitems'	, $existmenuitems);
		$this->assignRef('existtype'			, $existtype);
		$this->assignRef('existfields'		, $existfields);
		$this->assignRef('existfplg'			, $existfplg);
		$this->assignRef('existseplg'			, $existseplg);
		$this->assignRef('existsyplg'			, $existsyplg);
		$this->assignRef('existlang'			, $existlang);
		$this->assignRef('existversions'		, $existversions);
		$this->assignRef('existversionsdata', $existversionsdata);
		$this->assignRef('existauthors'			, $existauthors);
		//$this->assignRef('cachethumb'			, $cachethumb);
		$this->assignRef('oldbetafiles'			, $oldbetafiles);
		$this->assignRef('nooldfieldsdata'	, $nooldfieldsdata);
		$this->assignRef('missingversion'		, $missingversion);
		//$this->assignRef('initialpermission'	, $initialpermission);

		// assign Rights to the template
		$this->assignRef('CanAdd'		, $CanAdd);
		$this->assignRef('CanAddCats'	, $CanAddCats);
		$this->assignRef('CanCats'		, $CanCats);
		$this->assignRef('CanTypes'		, $CanTypes);
		$this->assignRef('CanFields'	, $CanFields);
		$this->assignRef('CanTags'		, $CanTags);
		$this->assignRef('CanAuthors'	, $CanAuthors);
		$this->assignRef('CanArchives'	, $CanArchives);
		$this->assignRef('CanFiles'		, $CanFiles);
		$this->assignRef('CanTemplates'	, $CanTemplates);
		$this->assignRef('CanStats'		, $CanStats);
		$this->assignRef('CanRights'	, $CanRights);
		$this->assignRef('CanPlugins'	, $CanPlugins);
		$this->assignRef('CanComments'	, $CanComments);
		$this->assignRef('CanImport'	, $CanImport);
		$this->assignRef('CanIndex'		, $CanIndex);

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
		$lang 		= & JFactory::getLanguage();
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

					echo JHTML::_('image', 'administrator/components/com_flexicontent/assets/images/'.$image, $text );
				?>
					<span><?php echo $text; ?></span>
				</a>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Check Flexicontent version
	 */
	
	function getUpdateComponent()
	 {
		$url = 'http://www.flexicontent.org/flexicontent_update.xml';
		$data = '';
		$check = array();
		$check['connect'] = 0;
		
		$com_xml 		= JApplicationHelper::parseXMLInstallFile( JPATH_ADMINISTRATOR .DS. 'components' .DS. 'com_flexicontent' .DS. 'manifest.xml' );
		$check['current_version'] = $com_xml['version'];

		//try to connect via cURL
		if(function_exists('curl_init') && function_exists('curl_exec')) {		
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
			//$fsock = @fsockopen("www.flexicontent.org", 80, $errno, $errstr, 5);
			$fsock = @fsockopen("flexicontent.googlecode.com", 80, $errno, $errstr, 5);
		
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
			$xml = & JFactory::getXMLparser('Simple');
			$xml->loadString($data);
			
			$version 				= & $xml->document->version[0];
			$check['version'] 		= & $version->data();
			$released 				= & $xml->document->released[0];
			$check['released'] 		= & $released->data();
			$check['connect'] 		= 1;
			$check['enabled'] 		= 1;
			
			$check['current'] 		= version_compare( $check['current_version'], $check['version'] );
		}
		
		return $check;
	}
	
	function fversion(&$tpl, &$params) {
		//updatecheck
		if($params->get('show_updatecheck', 1) == 1) {
			$cache = & JFactory::getCache('com_flexicontent');
			$cache->setCaching( 1 );
			$cache->setLifeTime( 100 );
			$check = $cache->get(array( 'FlexicontentViewFlexicontent', 'getUpdateComponent'), array('component'));
			$this->assignRef('check'		, $check);
		}
		parent::display($tpl);
	}
}
?>
