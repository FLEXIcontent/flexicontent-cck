<?php
/**
 * @version 1.5 stable $Id: view.html.php 313 2010-06-19 08:32:09Z emmanuel.danan $
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
 * View class for the itemelement screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItemelement extends JView {

	function display($tpl = null) {
		global $globalcats;
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		//initialise variables
		$db			= & JFactory::getDBO();
		$document	= & JFactory::getDocument();
		$template 	= $mainframe->getTemplate();
		
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal');

		//get var
		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.itemelement.filter_order', 	'filter_order', 	'i.ordering'	, 'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.itemelement.filter_order_Dir',	'filter_order_Dir',	''				, 'word' );
		$filter_state 		= $mainframe->getUserStateFromRequest( $option.'.itemelement.filter_state', 	'filter_state', 	'*'				, 'word' );
		$filter_cats 		= $mainframe->getUserStateFromRequest( $option.'.itemelement.filter_cats', 		'filter_cats', 		0, 				'int' );
		$filter_type 		= $mainframe->getUserStateFromRequest( $option.'.itemelement.filter_type', 		'filter_type', 		0, 				'int' );
		$search 			= $mainframe->getUserStateFromRequest( $option.'.itemelement.search', 			'search', 			'', 'string' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );

		//prepare the document
		$document->setTitle(JText::_( 'FLEXI_SELECTITEM' ));
		$document->addStyleSheet('templates/'.$template.'/css/general.css');

		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontent.css');

		//Get data from the model
		$rows      	= & $this->get( 'Data');
		$types		= & $this->get( 'Typeslist' );
		$pageNav 	= & $this->get( 'Pagination' );

		$categories = $globalcats;
		
		// build the categories select list for filter
		$lists['filter_cats'] = flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, 2, 'class="inputbox" size="1" onchange="submitform( );"', false, false);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		
		$ordering = ($lists['order'] == 'i.ordering');

		//build type select list
		$lists['filter_type'] = flexicontent_html::buildtypesselect($types, 'filter_type', $filter_type, true, 'class="inputbox" size="1" onchange="submitform( );"');

		// search filter
		$lists['search']= $search;
		
		$state[] = JHTML::_('select.option',  '', JText::_( 'FLEXI_SELECT_STATE' ) );
		$state[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$state[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		$state[] = JHTML::_('select.option',  'PE', JText::_( 'FLEXI_PENDING' ) );
		$state[] = JHTML::_('select.option',  'OQ', JText::_( 'FLEXI_TO_WRITE' ) );
		$state[] = JHTML::_('select.option',  'IP', JText::_( 'FLEXI_IN_PROGRESS' ) );

		$lists['state'] = JHTML::_('select.genericlist',   $state, 'filter_state', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_state );

		//assign data to template
		$this->assignRef('lists'      	, $lists);
		$this->assignRef('rows'      	, $rows);
		$this->assignRef('pageNav' 		, $pageNav);
		$this->assignRef('ordering'		, $ordering);
		$this->assignRef('filter_cats'	, $filter_cats);

		parent::display($tpl);
	}

}
?>