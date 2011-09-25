<?php
/**
 * @version 1.5 stable $Id: view.html.php 633 2011-07-08 03:23:13Z enjoyman@gmail.com $
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

jimport( 'joomla.application.component.view');

/**
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFields extends JView {

	function display($tpl = null)
	{
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		//initialise variables
		$db  		= & JFactory::getDBO();
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		
		JHTML::_('behavior.tooltip');

		//get vars
		$filter_assigned	= $mainframe->getUserStateFromRequest( $option.'.fields.filter_assigned', 	'filter_assigned', 	'', 'word' );
		$filter_iscore		= $mainframe->getUserStateFromRequest( $option.'.fields.filter_iscore', 	'filter_iscore', 	'', 'word' );
		$filter_state 		= $mainframe->getUserStateFromRequest( $option.'.fields.filter_state', 		'filter_state', 	'', 'word' );
		$filter_type		= $mainframe->getUserStateFromRequest( $option.'.fields.filter_type', 		'filter_type', 		'', 'int' );
		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.fields.filter_order', 		'filter_order', 	't.ordering', 'cmd' );
		if ($filter_type && $filter_order == 't.ordering') {
			$filter_order	= $mainframe->setUserState( $option.'.fields.filter_order', 'typeordering' );
		} else if (!$filter_type && $filter_order == 'typeordering') {
			$filter_order	= $mainframe->setUserState( $option.'.fields.filter_order', 't.ordering' );
		}
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.fields.filter_order_Dir',	'filter_order_Dir',	'ASC', 'word' );
		$search 			= $mainframe->getUserStateFromRequest( $option.'.fields.search', 			'search', 			'', 'string' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');

		$permission = FlexicontentHelperPerm::getPerm();

		if (!$permission->CanFields) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		//Create Submenu
		FLEXIcontentSubmenu('CanFields');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_FIELDS' ), 'fields' );
		JToolBarHelper::customX( 'fields.copy', 'copy.png', 'copy_f2.png', 'FLEXI_COPY' );
		JToolBarHelper::publishList('fields.publish');
		JToolBarHelper::unpublishList('fields.unpublish');
		JToolBarHelper::addNew('fields.add');
		JToolBarHelper::editList('fields.edit');
		JToolBarHelper::deleteList('Are you sure?', 'fields.remove');
		if(JAccess::check($user->id, 'core.admin', 'root.1') || $permission->CanConfig) JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');

		//Get data from the model
		$rows      	= & $this->get( 'Items');
		$pageNav 	= & $this->get( 'Pagination' );
		$types		= & $this->get( 'Typeslist' );

		$lists = array();
		
		//build backend visible filter
		$iscore 	= array();
		$iscore[] = JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_FIELDS_TYPE' ) .' -' );
		$iscore[] = JHTML::_('select.option',  'BV', JText::_( 'FLEXI_BACKEND_FIELDS' ) );
		$iscore[] = JHTML::_('select.option',  'C', JText::_( 'FLEXI_CORE_FIELDS' ) );
		$iscore[] = JHTML::_('select.option',  'NC', JText::_( 'FLEXI_NON_CORE_FIELDS' ) );

		$lists['iscore'] = JHTML::_('select.genericlist', $iscore, 'filter_iscore', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_iscore );

		//build arphaned/assigned filter
		$assigned 	= array();
		$assigned[] = JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_FIELDS' ) .' -' );
		$assigned[] = JHTML::_('select.option',  'O', JText::_( 'FLEXI_ORPHANED' ) );
		$assigned[] = JHTML::_('select.option',  'A', JText::_( 'FLEXI_ASSIGNED' ) );

		$lists['assigned'] = JHTML::_('select.genericlist', $assigned, 'filter_assigned', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_assigned );

		//build type select list
		$lists['filter_type'] = flexicontent_html::buildtypesselect($types, 'filter_type', $filter_type, true, 'class="inputbox" size="1" onchange="submitform( );"');
		
		//publish unpublished filter
		$lists['state']	= JHTML::_('grid.state', $filter_state );
		
		// search filter
		$lists['search']= $search;

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		// filter ordering
		if ($filter_type == '' || $filter_type == 0)
		{
			$ordering = ($lists['order'] == 't.ordering');
		} else {
			$ordering = ($lists['order'] == 'typeordering');
		}

		//assign data to template
		$this->assignRef('filter_type'  , $filter_type);
		$this->assignRef('lists'      	, $lists);
		$this->assignRef('rows'      	, $rows);
		$this->assignRef('user'      	, $user);
		$this->assignRef('ordering'		, $ordering);
		$this->assignRef('pageNav' 		, $pageNav);

		parent::display($tpl);
	}
}
?>
