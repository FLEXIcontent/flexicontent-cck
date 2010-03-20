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
		global $mainframe;
		
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
		$params 	= & JComponentHelper::getParams('com_flexicontent');
		$user		= & JFactory::getUser();		
		// Get data from the model
		$openquest	= & $this->get( 'Openquestions' );
		$unapproved = & $this->get( 'Pending' );
		$inprogress = & $this->get( 'Inprogress' );
		$themes		= flexicontent_tmpl::getThemes();
		
		// required setup check
		$existcat 	= & $this->get( 'Existcat' );
		$existsec 	= & $this->get( 'Existsec' );
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
		$missingversion		= $model->checkCurrentVersionData();

		//build toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_DASHBOARD' ), 'flexicontent' );

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		
		$css =	'.install-ok { background: url(components/com_flexicontent/assets/images/accept.png) 0% 50% no-repeat transparent; padding:1px 0; width: 20px; height:16px; display:block; }
				 .install-notok { background: url(components/com_flexicontent/assets/images/delete.png) 0% 50% no-repeat transparent; padding:1px 0; width: 20px; height:16px; display:block; float:left;}';		
		$document->addStyleDeclaration($css);

		if (version_compare(PHP_VERSION, '5.0.0', '>')) {
			if ($user->gid > 24) {
				$toolbar=&JToolBar::getInstance('toolbar');
				$toolbar->appendButton('Popup', 'download', JText::_('FLEXI_IMPORT_JOOMLA'), JURI::base().'index.php?option=com_flexicontent&amp;layout=import&amp;tmpl=component', 400, 300);
			}
			JToolBarHelper::preferences('com_flexicontent', '550', '650', 'Configuration');
		}		
		
		if (FLEXI_ACCESS) {
			$user =& JFactory::getUser();
			$CanAdd 		= ($user->gid < 25) ? ((FAccess::checkComponentAccess('com_content', 'submit', 'users', $user->gmid)) || (FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all'))) : 1;
			$CanAddCats 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'addcats', 'users', $user->gmid) : 1;
			$CanCats 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid) : 1;
			$CanTypes 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'types', 'users', $user->gmid) : 1;
			$CanFields 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'fields', 'users', $user->gmid) : 1;
			$CanTags 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'tags', 'users', $user->gmid) : 1;
			$CanArchives 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid) : 1;
			$CanFiles	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'files', 'users', $user->gmid) : 1;
			$CanStats	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'stats', 'users', $user->gmid) : 1;
			$CanRights	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexiaccess', 'manage', 'users', $user->gmid) : 1;
			$CanPlugins	 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_plugins', 'manage', 'users', $user->gmid) : 1;
			$CanComments 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_jcomments', 'manage', 'users', $user->gmid) : $CanComments;
			$CanTemplates	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'templates', 'users', $user->gmid) : 1;
		} else {
			$CanAdd			= 1;
			$CanAddCats 	= 1;
			$CanCats 		= 1;
			$CanTypes 		= 1;
			$CanFields		= 1;
			$CanTags 		= 1;
			$CanArchives	= 1;
			$CanFiles		= 1;
			$CanStats		= 1;
			$CanRights		= 1;
			$CanPlugins		= 1;
			$CanTemplates	= 1;
		}

		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent', true);
		// ensures the PHP version is correct
		if (version_compare(PHP_VERSION, '5.0.0', '>')) {
			JSubMenuHelper::addEntry( JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items');
			if ($CanTypes)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types');
			if ($CanCats) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories');
			if ($CanFields) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields');
			if ($CanTags) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags');
			if ($CanArchives) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive');
			if ($CanFiles) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager');
			if ($CanTemplates) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_TEMPLATES' ), 'index.php?option=com_flexicontent&view=templates');
			if ($CanStats)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats');
		}
		
		//updatecheck
		if($params->get('show_updatecheck')) {
			$check = & $this->get( 'Update' );
		} else {
			$check = array();
			$check['enabled'] = 0;
		}
				
		$this->assignRef('pane'			, $pane);
		$this->assignRef('unapproved'	, $unapproved);
		$this->assignRef('openquest'	, $openquest);
		$this->assignRef('inprogress'	, $inprogress);
		$this->assignRef('existcat'		, $existcat);
		$this->assignRef('existsec'		, $existsec);
		$this->assignRef('existmenu'	, $existmenu);
		$this->assignRef('check'		, $check);
		$this->assignRef('template'		, $template);
		$this->assignRef('params'		, $params);

		// install check
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
		$this->assignRef('CanAdd'		, $CanAdd);
		$this->assignRef('CanAddCats'	, $CanAddCats);
		$this->assignRef('CanCats'		, $CanCats);
		$this->assignRef('CanTypes'		, $CanTypes);
		$this->assignRef('CanFields'	, $CanFields);
		$this->assignRef('CanTags'		, $CanTags);
		$this->assignRef('CanArchives'	, $CanArchives);
		$this->assignRef('CanFiles'		, $CanFiles);
		$this->assignRef('CanTemplates'	, $CanTemplates);
		$this->assignRef('CanStats'		, $CanStats);
		$this->assignRef('CanRights'	, $CanRights);
		$this->assignRef('CanPlugins'	, $CanPlugins);
		$this->assignRef('CanComments'	, $CanComments);

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
}
?>