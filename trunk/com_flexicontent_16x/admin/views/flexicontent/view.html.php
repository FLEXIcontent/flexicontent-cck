<?php
/**
 * @version 1.5 stable $Id: view.html.php 370 2010-07-21 04:55:46Z enjoyman $
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
class FlexicontentViewFlexicontent extends JView{
	/**
	 * Creates the Entrypage
	 *
	 * @since 1.0
	 */
	function display( $tpl = null ) {
		$mainframe = &JFactory::getApplication();
		
		//Load pane behavior
		jimport('joomla.html.pane');
		// load the file system librairies
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');	
		// activate the tooltips
		JHTML::_('behavior.tooltip');

		// handle jcomments integration
		if (JPluginHelper::isEnabled('system', 'jcomments.system') || JPluginHelper::isEnabled('system', 'jcomments')) {
			$dest 			= JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'plugins'.DS.'com_flexicontent.plugin.php';
			$source 		= JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'jcomments'.DS.'com_flexicontent.plugin.php';
			if (!JFile::exists($dest)) {
				JFile::copy($source, $dest);
			}
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
		$params 	= & JComponentHelper::getParams('com_flexicontent');
		$user		= & JFactory::getUser();		
		// Get data from the model
		$openquest	= & $this->get( 'Openquestions' );
		$unapproved = & $this->get( 'Pending' );
		$inprogress = & $this->get( 'Inprogress' );
		$themes		= flexicontent_tmpl::getThemes();
		
		// required setup check
		$existcat 	= & $this->get( 'Existcat' );
		//$existsec 	= & $this->get( 'Existsec' );
		$existmenu 	= & $this->get( 'Existmenu' );

		// install check
		$existtype 			= & $this->get( 'ExistType' );
		$existfields 		= & $this->get( 'ExistFields' );
		$existfplg 			= & $this->get( 'ExistFieldsPlugins' );
		$existseplg 		= & $this->get( 'ExistSearchPlugin' );
		$existsyplg 		= & $this->get( 'ExistSystemPlugin' );
		$allplgpublish 		= & $this->get( 'AllPluginsPublished' );
		$existlang	 		= & $this->get( 'ExistLanguageColumn' );
		$existversions 		= & $this->get( 'ExistVersionsTable' );
		$existversionsdata	= & $this->get( 'ExistVersionsPopulated' );
		$cachethumb			= & $this->get( 'CacheThumbChmod' );
		$oldbetafiles		= & $this->get( 'OldBetaFiles' );
		$fieldspositions	= & $this->get( 'FieldsPositions' );
		$nooldfieldsdata	= & $this->get( 'NoOldFieldsData' );
		$model 				= $this->getModel('flexicontent');
		$use_versioning = $params->get('use_versioning', 1);
		$missingversion		= ($use_versioning&&$model->checkCurrentVersionData());

		//build toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_DASHBOARD' ), 'flexicontent' );

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		
		$css =	'.install-ok { background: url(components/com_flexicontent/assets/images/accept.png) 0% 50% no-repeat transparent; padding:1px 0; width: 20px; height:16px; display:block; }
				 .install-notok { background: url(components/com_flexicontent/assets/images/delete.png) 0% 50% no-repeat transparent; padding:1px 0; width: 20px; height:16px; display:block; float:left;}';		
		$document->addStyleDeclaration($css);

		$session  =& JFactory::getSession();
		$dopostinstall = $session->get('flexicontent.postinstall');
		$permission = FlexicontentHelperPerm::getPerm();

		if (version_compare(PHP_VERSION, '5.0.0', '>')) {
			//if ($user->gid > 24) {
			if(JAccess::check($user->id, 'core.admin', 'root.1')) {
				$toolbar=&JToolBar::getInstance('toolbar');
				$toolbar->appendButton('Popup', 'download', JText::_('FLEXI_IMPORT_JOOMLA'), JURI::base().'index.php?option=com_flexicontent&amp;layout=import&amp;tmpl=component', 400, 300);
			}
			if(JAccess::check($user->id, 'core.admin', 'root.1') || $permission->CanConfig) JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		}
		$permission = FlexicontentHelperPerm::getPerm();
		//Create Submenu
		FLEXIcontentSubmenu();
		
		//updatecheck
		if($params->get('show_updatecheck', 1) == 1) {
		$cache = & JFactory::getCache('com_flexicontent');
		$cache->setCaching( 1 );
		$cache->setLifeTime( 100 );
		$check = $cache->get(array( 'FlexicontentViewFlexicontent', 'getUpdateComponent'), array('component'));
		$this->assignRef('check'		, $check);
		}

		$this->assignRef('pane'			, $pane);
		$this->assignRef('unapproved'	, $unapproved);
		$this->assignRef('openquest'	, $openquest);
		$this->assignRef('inprogress'	, $inprogress);
		$this->assignRef('existcat'		, $existcat);
		//$this->assignRef('existsec'		, $existsec);
		$this->assignRef('existmenu'	, $existmenu);
		$this->assignRef('template'		, $template);
		$this->assignRef('params'		, $params);

		// install check
		$this->assignRef('dopostinstall', $dopostinstall);
		$this->assignRef('existtype'			, $existtype);
		$this->assignRef('existfields'			, $existfields);
		$this->assignRef('existfplg'			, $existfplg);
		$this->assignRef('existseplg'			, $existseplg);
		$this->assignRef('existsyplg'			, $existsyplg);
		$this->assignRef('allplgpublish'		, $allplgpublish);
		$this->assignRef('existlang'			, $existlang);
		$this->assignRef('existversions'		, $existversions);
		$this->assignRef('existversionsdata'	, $existversionsdata);
		$this->assignRef('cachethumb'			, $cachethumb);
		$this->assignRef('oldbetafiles'			, $oldbetafiles);
		$this->assignRef('nooldfieldsdata'		, $nooldfieldsdata);
		$this->assignRef('missingversion'		, $missingversion);

		// assign Rights to the template
		$this->assignRef('permission'		, $permission);

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
	function quickiconButton( $link, $image, $text, $modal = 0 )
	{
		//initialise variables
		$lang 		= & JFactory::getLanguage();
  		?>

		<div style="float:<?php echo ($lang->isRTL()) ? 'right' : 'left'; ?>;">
			<div class="icon">
				<?php
				if ($modal == 1) {
					JHTML::_('behavior.modal');
				?>
					<a href="<?php echo $link; ?>" style="cursor:pointer" class="modal" rel="{handler: 'iframe', size: {x: 900, y: 500}}">
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
			$fsock = @fsockopen("www.flexicontent.org", 80, $errno, $errstr, 5);
		
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
}
?>
