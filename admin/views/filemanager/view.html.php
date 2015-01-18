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

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.view');

/**
 * HTML View class for the Filemanager View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFilemanager extends JViewLegacy
{
	function display( $tpl = null )
	{
		//initialise variables
		$app      = JFactory::getApplication();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		$option   = JRequest::getCmd('option');
		$view     = JRequest::getVar('view');
		//$authorparams = flexicontent_db::getUserConfig($user->id);
		$langs = FLEXIUtilities::getLanguages('code');
		
		flexicontent_html::loadFramework('select2');
		JHTML::_('behavior.tooltip');
		// Load the form validation behavior
		JHTML::_('behavior.formvalidation');

		// Get filters
		$count_filters = 0;
		
		$filter_order     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order',     'filter_order',     'f.filename', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir', 'filter_order_Dir', '',           'word' );
		
		$filter_lang		= $app->getUserStateFromRequest( $option.'.'.$view.'.filter_lang', 		'filter_lang', 		'', 		'string' );
		$filter_uploader= $app->getUserStateFromRequest( $option.'.'.$view.'.filter_uploader','filter_uploader',0,			'int' );
		$filter_url			= $app->getUserStateFromRequest( $option.'.'.$view.'.filter_url', 		'filter_url', 		'',			'word' );
		$filter_secure	= $app->getUserStateFromRequest( $option.'.'.$view.'.filter_secure', 	'filter_secure', 	'', 		'word' );
		$filter_ext			= $app->getUserStateFromRequest( $option.'.'.$view.'.filter_ext', 		'filter_ext', 		'', 		'alnum' );
		$filter_item 		= $app->getUserStateFromRequest( $option.'.'.$view.'.item_id', 				'item_id', 				0,	 		'int' );
		
		if ($filter_lang) $count_filters++; if ($filter_uploader) $count_filters++;
		if ($filter_url) $count_filters++; if ($filter_secure) $count_filters++;
		if ($filter_ext) $count_filters++; if ($filter_item) $count_filters++;
		
		$scope  = $app->getUserStateFromRequest( $option.'.'.$view.'.scope', 			'scope', 			1, 	'int' );
		$search = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 		'search', 		'', 'string' );
		$search = FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search ) ) ) : $db->getEscaped( trim(JString::strtolower( $search ) ) );
		if (strlen($search)) $count_filters++;
		
		// Add custom css and js to document
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/flexi-lib.js' );
		
		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// **************************
		// Create Submenu and toolbar
		// **************************
		FLEXISubmenu('CanFiles');
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_FILEMANAGER' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'files' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		if (FLEXI_J16GE) {
			JToolBarHelper::deleteList('Are you sure?', 'filemanager.remove');
		} else {
			JToolBarHelper::deleteList();
		}
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolBarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		// ***********************
		// Get data from the model
		// ***********************
		$folder_mode			= 0;
		$model   = $this->getModel();
		if ( !$folder_mode ) {
			$rows  = $this->get('Data');
		} else {
			// TODO MORE ...
		}
		$pagination = $this->get('Pagination');
		//$users = $this->get('Users');
		
		// Get item using at least one file (-of- the currently listed files)
		/*$items_single	= $model->getItemsSingleprop( array('file','minigallery') );
		$items_multi	= $model->getItemsMultiprop ( $field_props=array('image'=>'originalname'), $value_props=array('image'=>'filename') );
		$items = array();
		foreach ($items_single as $item_id => $_item) $items[$item_id] = $_item;
		foreach ($items_multi  as $item_id => $_item) $items[$item_id] = $_item;
		ksort($items);*/
		
		$assigned_fields_labels = array('image'=>'image/gallery', 'file'=>'file', 'minigallery'=>'minigallery');
		$assigned_fields_icons = array('image'=>'picture_link', 'file'=>'page_link', 'minigallery'=>'film_link');
		
		
		/*****************
		 ** BUILD LISTS **
		 *****************/
		
		$lists = array();
		
		// ** FILE UPLOAD FORM **
		
		// Build languages list
		//$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		//$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		$display_file_lang_as = $cparams->get('display_file_lang_as', 3);
		$allowed_langs = null;
		$lists['file-lang'] = flexicontent_html::buildlanguageslist('file-lang', '', '*', $display_file_lang_as, $allowed_langs, $published_only=false);
		
		
		/*************
		 ** FILTERS **
		 *************/
		
		// language filter
		$lists['language'] = ($filter_lang || 1 ? '<label class="label">'.JText::_('FLEXI_LANGUAGE').'</label>' : '').
			flexicontent_html::buildlanguageslist('filter_lang', 'class="use_select2_lib" onchange="submitform();" size="1" ', $filter_lang, '-'/*2*/);
		
		// search
		$lists['search'] 	= $search;
		
		//search filter
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'FLEXI_FILENAME' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ) );
		$lists['scope'] = JHTML::_('select.genericlist', $filters, 'scope', 'size="1" class="use_select2_lib fc_skip_highlight" title="'.JText::_('FLEXI_SEARCH_TEXT_INSIDE').'"', 'value', 'text', $scope );

		//build url/file filterlist
		$url 	= array();
		$url[] 	= JHTML::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_FILES' )*/ );
		$url[] 	= JHTML::_('select.option',  'F', JText::_( 'FLEXI_FILE' ) );
		$url[] 	= JHTML::_('select.option',  'U', JText::_( 'FLEXI_URL' ) );

		$lists['url'] = ($filter_url || 1 ? '<label class="label">'.JText::_('FLEXI_ALL_FILES').'</label>' : '').
			JHTML::_('select.genericlist', $url, 'filter_url', 'class="use_select2_lib" size="1" onchange="submitform( );"', 'value', 'text', $filter_url );

		//item lists
		/*$items_list = array();
		$items_list[] = JHTML::_('select.option', '', '- '. JText::_( 'FLEXI_FILTER_BY_ITEM' ) .' -' );
		foreach($items as $item) {
			$items_list[] = JHTML::_('select.option', $item->id, JText::_( $item->title ) . ' (#' . $item->id . ')' );
		}
		$lists['item_id'] = JHTML::_('select.genericlist', $items_list, 'item_id', 'size="1" class="use_select2_lib" onchange="submitform( );"', 'value', 'text', $filter_item );*/
		$lists['item_id'] = '<input type="text" name="item_id" size="1" class="inputbox" onchange="submitform( );" value="'.$filter_item.'" />';
		
		//build secure/media filterlist
		$secure 	= array();
		$secure[] 	= JHTML::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_DIRECTORIES' )*/ );
		$secure[] 	= JHTML::_('select.option',  'S', JText::_( 'FLEXI_SECURE_DIR' ) );
		$secure[] 	= JHTML::_('select.option',  'M', JText::_( 'FLEXI_MEDIA_DIR' ) );

		$lists['secure'] = ($filter_secure || 1 ? '<label class="label">'.JText::_('FLEXI_ALL_DIRECTORIES').'</label>' : '').
			JHTML::_('select.genericlist', $secure, 'filter_secure', 'class="use_select2_lib" size="1" onchange="submitform( );"', 'value', 'text', $filter_secure );

		//build ext filterlist
		$lists['ext'] = ($filter_ext || 1 ? '<label class="label">'.JText::_('FLEXI_ALL_EXT').'</label>' : '').
			flexicontent_html::buildfilesextlist('filter_ext', 'class="use_select2_lib" size="1" onchange="submitform( );"', $filter_ext, '-');

		//build uploader filterlist
		$lists['uploader'] = ($filter_uploader || 1 ? '<label class="label">'.JText::_('FLEXI_ALL_UPLOADERS').'</label>' : '').
			flexicontent_html::builduploaderlist('filter_uploader', 'class="use_select2_lib" size="1" onchange="submitform( );"', $filter_uploader, '-');

		// table ordering
		$lists['order_Dir']	= $filter_order_Dir;
		$lists['order']			= $filter_order;
		
		// uploadstuff
		if ($cparams->get('enable_flash', 1) && !FLEXI_J30GE) {
			JHTML::_('behavior.uploader', 'file-upload', array('onAllComplete' => 'function(){ window.location.reload(); }') );
		}
		jimport('joomla.client.helper');
		$ftp = !JClientHelper::hasCredentials('ftp');
		
		//assign data to template
		$this->assignRef('count_filters', $count_filters);
		$this->assignRef('params'     , $cparams);
		$this->assign('require_ftp'		, $ftp);
		//Load pane behavior
		if (!FLEXI_J16GE) {
			jimport('joomla.html.pane');
			$pane = JPane::getInstance('Tabs');
			$this->assignRef('pane'       , $pane);
		}
		$this->assignRef('lists'      , $lists);
		$this->assignRef('rows'       , $rows);
		$this->assignRef('pagination' , $pagination);
		$this->assignRef('CanFiles'        , $perms->CanFiles);
		$this->assignRef('CanUpload'       , $perms->CanUpload);
		$this->assignRef('CanViewAllFiles' , $perms->CanViewAllFiles);
		$this->assignRef('assigned_fields_labels' , $assigned_fields_labels);
		$this->assignRef('assigned_fields_icons'  , $assigned_fields_icons);
		$this->assignRef('langs', $langs);
		
		$this->assignRef('option', $option);
		$this->assignRef('view', $view);

		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
	}
}
?>