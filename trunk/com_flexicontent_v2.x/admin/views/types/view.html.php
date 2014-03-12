<?php
/**
 * @version 1.5 stable $Id: view.html.php 1579 2012-12-03 03:37:21Z ggppdk $
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
class FlexicontentViewTypes extends JViewLegacy
{
	/**
	 * Creates the Entrypage
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		//initialise variables
		$app = JFactory::getApplication();
		$option    = JRequest::getVar('option');
		$user 		= JFactory::getUser();
		$db       = JFactory::getDBO();
		$document	= JFactory::getDocument();
		
		JHTML::_('behavior.tooltip');
		
		//get vars
		$filter_order		  = $app->getUserStateFromRequest( $option.'.types.filter_order', 		'filter_order', 	't.name', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( $option.'.types.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		$filter_state 		= $app->getUserStateFromRequest( $option.'.types.filter_state', 		'filter_state', 	'*', 'word' );
		$search 			= $app->getUserStateFromRequest( $option.'.types.search', 			'search', 			'', 'string' );
		$search 			= FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search ) ) ) : $db->getEscaped( trim(JString::strtolower( $search ) ) );

		//add css and submenu to document
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');
		
		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Create Submenu (and also check access to current view)
		FLEXISubmenu('CanTypes');

		//create the toolbar
		$contrl = FLEXI_J16GE ? "types." : "";
		JToolBarHelper::title( JText::_( 'FLEXI_TYPES' ), 'types' );
		JToolBarHelper::custom( $contrl.'copy', 'copy.png', 'copy_f2.png', 'FLEXI_COPY' );
		JToolBarHelper::divider(); JToolBarHelper::spacer();
		JToolBarHelper::publishList($contrl.'publish');
		JToolBarHelper::unpublishList($contrl.'unpublish');
		JToolBarHelper::addNew($contrl.'add');
		JToolBarHelper::editList($contrl.'edit');
		JToolBarHelper::deleteList('Are you sure?', $contrl.'remove');
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolBarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		//Get data from the model
		if (FLEXI_J16GE) {
			$rows = $this->get( 'Items');
		} else {
			$rows = $this->get( 'Data');
		}
		foreach($rows as $type) {
			$type->config = FLEXI_J16GE ? new JRegistry($type->config) : new JParameter($type->config);
		}
		$pagination = $this->get( 'Pagination' );

		$lists = array();
		
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