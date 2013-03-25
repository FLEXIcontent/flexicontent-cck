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

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.view');

/**
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewTags extends JViewLegacy
{
	function display($tpl = null)
	{
		//initialise variables
		$app      = JFactory::getApplication();
		$option   = JRequest::getVar('option');
		$user     = JFactory::getUser();
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		
		JHTML::_('behavior.tooltip');

		//get vars
		$filter_order     = $app->getUserStateFromRequest( $option.'.tags.filter_order', 		'filter_order', 	't.name', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.tags.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		$filter_state     = $app->getUserStateFromRequest( $option.'.tags.filter_state', 		'filter_state', 	'*', 'word' );
		$filter_assigned = $app->getUserStateFromRequest( $option.'.tags.filter_assigned', 	'filter_assigned', '*', 'word' );
		$search 			= $app->getUserStateFromRequest( $option.'.tags.search', 				'search', 			'', 'string' );
		$search 			= FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search ) ) ) : $db->getEscaped( trim(JString::strtolower( $search ) ) );

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');

		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();

		// Create Submenu (and also check access to current view)
		FLEXISubmenu('CanTags');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_TAGS' ), 'tags' );
		$toolbar = JToolBar::getInstance('toolbar');
		if ($perms->CanConfig) {
			$toolbar->appendButton('Popup', 'import', JText::_('FLEXI_IMPORT'), JURI::base().'index.php?option=com_flexicontent&amp;view=tags&amp;layout=import&amp;tmpl=component', 430, 500);
			JToolBarHelper::divider();  JToolBarHelper::spacer();
		}
		if (FLEXI_J16GE) {
			JToolBarHelper::publishList('tags.publish');
			JToolBarHelper::unpublishList('tags.unpublish');
			JToolBarHelper::addNew('tags.add');
			JToolBarHelper::editList('tags.edit');
			JToolBarHelper::deleteList('Are you sure?', 'tags.remove');
		} else {
			JToolBarHelper::publishList();
			JToolBarHelper::unpublishList();
			JToolBarHelper::addNew();
			JToolBarHelper::editList();
			JToolBarHelper::deleteList();
		}
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		}

		//Get data from the model
		$rows       = $this->get( 'Data');
		$pagination = $this->get( 'Pagination' );

		$lists = array();
		
		//build arphaned/assigned filter
		$assigned 	= array();
		$assigned[] = JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_TAGS' ) .' -' );
		$assigned[] = JHTML::_('select.option',  'O', JText::_( 'FLEXI_ORPHANED' ) );
		$assigned[] = JHTML::_('select.option',  'A', JText::_( 'FLEXI_ASSIGNED' ) );

		$lists['assigned'] = JHTML::_('select.genericlist', $assigned, 'filter_assigned', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_assigned );
		
		//publish unpublished filter
		$lists['state']	= JHTML::_('grid.state', $filter_state );
		
		// search filter
		$lists['search']= $search;

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		//assign data to template
		$this->assignRef('lists'      , $lists);
		$this->assignRef('rows'      	, $rows);
		$this->assignRef('pagination'	, $pagination);

		parent::display($tpl);
	}
}
?>