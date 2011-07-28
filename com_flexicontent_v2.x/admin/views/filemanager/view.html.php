<?php
/**
 * @version 1.5 stable $Id: view.html.php 270 2010-06-11 09:08:38Z enjoyman $
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
 * HTML View class for the Filemanager View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFilemanager extends JView{
	/**
	 * Creates the Filemanagerview
	 *
	 * @since 1.0
	 */
	function display( $tpl = null ) {
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		$user = &JFactory::getUser();
		//Load pane behavior
		jimport('joomla.html.pane');

		JHTML::_('behavior.tooltip');
		// Load the form validation behavior
		JHTML::_('behavior.formvalidation');

		//initialise variables
		$document	= & JFactory::getDocument();
		$pane   	= & JPane::getInstance('Tabs');
		$db  		= & JFactory::getDBO();
		$params 	= & JComponentHelper::getParams('com_flexicontent');

		//get vars
		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_order', 	'filter_order', 	'f.filename', 	'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_order_Dir',	'filter_order_Dir',	'', 			'word' );
		$filter 			= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter', 			'filter', 			'', 			'int' );
		$filter_uploader	= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_uploader', 	'filter_uploader', '', 				'int' );
		$filter_url			= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_url', 		'filter_url', 		'',				'word' );
		$filter_secure		= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_secure', 	'filter_secure', 	'', 			'word' );
		$filter_ext			= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_ext', 		'filter_ext', 		'', 			'alnum' );
		$search 			= $mainframe->getUserStateFromRequest( $option.'.filemanager.search', 			'search', 			'', 'string' );
		$filter_item 		= $mainframe->getUserStateFromRequest( $option.'.filemanager.items', 			'items', 			'', 'int' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');

		$permission = FlexicontentHelperPerm::getPerm();

		if (!$permission->CanFiles) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		//Create Submenu
		FLEXIcontentSubmenu();
		
		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_FILEMANAGER' ), 'files' );
		JToolBarHelper::deleteList('filemanager.remove');
		if(JAccess::check($user->id, 'core.admin', 'root.1') || $permission->CanConfig) JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		
		//Get data from the model
		$rows      	= & $this->get( 'Data');
		$pageNav 	= & $this->get( 'Pagination' );
		$items = & $this->get('Items');
		$users = &$this->get('Users');

		//search
		$lists 				= array();
		$lists['search'] 	= $search;
		
		//search filter
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'FLEXI_FILENAME' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'FLEXI_DISPLAY_NAME' ) );
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter );

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
		$lists['items'] = JHTML::_('select.genericlist', $items_list, 'items', 'size="1" class="inputbox" onchange="submitform( );"', 'value', 'text', $filter_item );

		//build secure/media filterlist
		$secure 	= array();
		$secure[] 	= JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_DIRECTORIES' ) .' -' );
		$secure[] 	= JHTML::_('select.option',  'S', JText::_( 'FLEXI_SECURE_DIR' ) );
		$secure[] 	= JHTML::_('select.option',  'M', JText::_( 'FLEXI_MEDIA_DIR' ) );

		$lists['secure'] = JHTML::_('select.genericlist', $secure, 'filter_secure', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_secure );

		//build ext filterlist
		$lists['ext'] = flexicontent_html::buildfilesextlist('filter_ext', 'class="inputbox" size="1" onchange="submitform( );"', $filter_ext);

		//build uploader filterlist
		$lists['uploader'] = flexicontent_html::builduploaderlist('filter_uploader', 'class="inputbox" size="1" onchange="submitform( );"', $filter_uploader);

		//table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] 	= $filter_order;
		
		//uploadstuff
		if ($params->get('enable_flash', 1)) {
			JHTML::_('behavior.uploader', 'file-upload', array('onAllComplete' => 'function(){ window.location.reload(); }') );
		}
		
		jimport('joomla.client.helper');
		$ftp = !JClientHelper::hasCredentials('ftp');
		
		//assign data to template
		$this->assign('require_ftp'		, $ftp);
		
		$this->assignRef('session'			, JFactory::getSession());
		$this->assignRef('pane'				, $pane);
		$this->assignRef('params'			, $params);
		$this->assignRef('lists'      		, $lists);
		$this->assignRef('rows'      		, $rows);
		$this->assignRef('pageNav' 			, $pageNav);
		$this->assignRef('permission' 		, $permission);

		parent::display($tpl);

	}
}
?>