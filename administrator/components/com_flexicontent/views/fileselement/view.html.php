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
 * HTML View class for the Fileselement View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFileselement extends JView
{
	/**
	 * Creates the Filemanagerview
	 *
	 * @since 1.0
	 */
	function display( $tpl = null ) {
		global $mainframe, $option;
		
		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$document	= & JFactory::getDocument();
		$app		= & JFactory::getApplication();
		$pane   	= & JPane::getInstance('Tabs');
		$db  		= & JFactory::getDBO();
		$user  		= & JFactory::getUser();
		$params 	= & JComponentHelper::getParams('com_flexicontent');
		$fieldid	= JRequest::getVar( 'field', null, 'request', 'int' );

		$client		= $app->isAdmin() ? '../' : '';

		JHTML::_('behavior.tooltip');
		// Load the form validation behavior
		JHTML::_('behavior.formvalidation');

		//get vars
		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter_order', 		'filter_order', 	'f.filename', 	'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter_order_Dir',	'filter_order_Dir',	'', 			'word' );
		$filter 			= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter', 				'filter', 			1, 				'int' );
		$filter_uploader	= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter_uploader', 	'filter_uploader', 	0, 				'int' );
		$filter_url			= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter_url', 			'filter_url', 		'',				'word' );
		$filter_secure		= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter_secure', 		'filter_secure', 	'', 			'word' );
		$filter_ext			= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter_ext', 			'filter_ext', 		'', 			'alnum' );
		$search 			= $mainframe->getUserStateFromRequest( $option.'.fileselement.search', 				'search', 			'', 			'string' );
		$filter_item 		= $mainframe->getUserStateFromRequest( $option.'.fileselement.items', 				'items', 			0,	 			'int' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );
		
		$itemid 		= $mainframe->getUserStateFromRequest( $option.'.fileselement.itemid', 'itemid', 0, 'int' );

		//add css and submenu to document
		$document->addStyleSheet( ($mainframe->isSite() ? 'administrator/' : '' ) . 'components/com_flexicontent/assets/css/flexicontentbackend.css');
		$document->addStyleSheet( ($mainframe->isSite() ? 'administrator/' : '' ) . 'templates/system/css/system.css');
		// include khepri stylesheet only if we are in frontend
		if ($mainframe->isSite()) {
			$document->addStyleSheet('administrator/templates/khepri/css/general.css');
			$searchcss = '.adminform #search {border:1px solid silver;font-size:10px;margin:0;padding:0;float:none;height:14px;width:250px;}';
			$document->addStyleDeclaration($searchcss);
		}
		//a trick to avoid loosing general style in modal window
		$css = 'body, td, th { font-size: 11px; }';
		$document->addStyleDeclaration($css);

		//add js to document
		//$document->addScript( JURI::base().'components/com_flexicontent/assets/js/fileselement.js' );
		$js = "
		function qffileselementadd(id, file) {
			document.adminForm.file.value=id;
			window.parent.qfSelectFile".$fieldid."(id, file);	
			//document.adminForm.submit();	
		}
		";
		$document->addScriptDeclaration($js);

		if (FLEXI_ACCESS) {
			$CanUpload	 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'uploadfiles', 'users', $user->gmid) : 1;
			$CanViewAllFiles	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'viewallfiles', 'users', $user->gmid) : 1;
		} else {
			$CanUpload			= 1;
			$CanViewAllFiles	= 1;
		}

		//Get data from the model
		$model = $this->getModel();
		$rows      	= & $this->get( 'Data');
		$pageNav 	= & $this->get( 'Pagination' );
		$items = & $this->get('Items');
		$users = &$this->get('Users');
		
		$files_selected = $model->getItemFiles($itemid);

		//search filter
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'FLEXI_FILENAME' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'FLEXI_DISPLAY_NAME' ) );
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter );

		// search
		$lists['search']= $search;

		//build url/file filterlist
		$url 	= array();
		$url[] 	= JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_FILES' ) .' -' );
		$url[] 	= JHTML::_('select.option',  'F', JText::_( 'FLEXI_FILE' ) );
		$url[] 	= JHTML::_('select.option',  'U', JText::_( 'FLEXI_URL' ) );

		$lists['url'] = JHTML::_('select.genericlist', $url, 'filter_url', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_url );

		//item lists
		$items_list = array();
		$items_list[] = JHTML::_('select.option', '', JText::_( '- Filter by item -' ) );
		foreach($items as $item) {
			$items_list[] = JHTML::_('select.option', $item->id, JText::_( $item->title ) );
		}
		$lists['items'] = JHTML::_('select.genericlist', $items_list, 'items', 'size="1" style="width:200px;" class="inputbox" onchange="submitform( );"', 'value', 'text', $filter_item );
		
		//build secure/media filterlist
		$secure 	= array();
		$secure[] 	= JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_DIRECTORIES' ) .' -' );
		$secure[] 	= JHTML::_('select.option',  'S', JText::_( 'FLEXI_SECURE' ) );
		$secure[] 	= JHTML::_('select.option',  'M', JText::_( 'FLEXI_MEDIA' ) );

		$lists['secure'] = JHTML::_('select.genericlist', $secure, 'filter_secure', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_secure );

		//build ext filterlist
		$lists['ext'] = flexicontent_html::buildfilesextlist('filter_ext', 'class="inputbox" size="1" onchange="submitform( );"', $filter_ext);

		//build uploader filterlist
		$lists['uploader'] = flexicontent_html::builduploaderlist('filter_uploader', 'class="inputbox" size="1" onchange="submitform( );"', $filter_uploader);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		// removed files
		$filelist = JRequest::getString('files');
		$file = JRequest::getInt('file');

		$filelist = explode(',', $filelist);
		$files = array();
		foreach ($filelist as $fileid) {
			
			if ($fileid && $fileid != $file) {
			$files[] = (int)$fileid;
			}
			
		}

		$files = implode(',', $files);
		if (strlen($files) > 0) {
			$files .= ',';
		}
		$files .= $file;

		//assign data to template
		$this->assignRef('session'			, JFactory::getSession());
		$this->assignRef('params'			, $params);
		$this->assignRef('client'			, $client);
		$this->assignRef('pane'				, $pane);
		$this->assignRef('lists'    	  	, $lists);
		$this->assignRef('rows'     	 	, $rows);
		$this->assignRef('pageNav' 			, $pageNav);
		$this->assignRef('files' 			, $files);
		$this->assignRef('fieldid' 			, $fieldid);
		$this->assignRef('CanUpload' 		, $CanUpload);
		$this->assignRef('CanViewAllFiles' 	, $CanViewAllFiles);
		$this->assignRef('files_selected', $files_selected);

		parent::display($tpl);
	}
}
?>
