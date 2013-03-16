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
class FlexicontentViewCategories extends JViewLegacy
{
	function display($tpl = null)
	{
		global $globalcats;
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		//initialise variables
		$user 		= & JFactory::getUser();
		$db  		= & JFactory::getDBO();
		$document	= & JFactory::getDocument();
		
		JHTML::_('behavior.tooltip');

		//get vars
		$order_property = !FLEXI_J16GE ? 'c.ordering' : 'c.lft';
		$filter_order     = $mainframe->getUserStateFromRequest( $option.'.categories.filter_order', 'filter_order', $order_property, 'cmd' );
		$filter_order_Dir = $mainframe->getUserStateFromRequest( $option.'.categories.filter_order_Dir', 'filter_order_Dir',	'', 'word' );
		$filter_state     = $mainframe->getUserStateFromRequest( $option.'.categories.filter_state', 		'filter_state', 	'*', 'word' );
		if (FLEXI_J16GE) {
			$filter_language	= $mainframe->getUserStateFromRequest( $option.'.categories.filter_language', 'filter_language', '*', 'cmd' );
		}
		$search 			= $mainframe->getUserStateFromRequest( $option.'.categories.search', 			'search', 			'', 'string' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		
		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Create Submenu (and also check access to current view)
		FLEXISubmenu('CanCats');

		//create the toolbar
		$contrl = FLEXI_J16GE ? "categories." : "";
		$contrl_singular = FLEXI_J16GE ? "category." : "";
		JToolBarHelper::title( JText::_( 'FLEXI_CATEGORIES' ), 'fc_categories' );
		$toolbar =&JToolBar::getInstance('toolbar');
		$toolbar->appendButton('Popup', 'params', JText::_('FLEXI_COPY_PARAMS'), JURI::base().'index.php?option=com_flexicontent&amp;view=categories&amp;layout=params&amp;tmpl=component', 400, 440);
		JToolBarHelper::divider(); JToolBarHelper::spacer();
		JToolBarHelper::publishList($contrl.'publish');
		JToolBarHelper::unpublishList($contrl.'unpublish');
		JToolBarHelper::addNew($contrl_singular.'add');
		JToolBarHelper::editList($contrl_singular.'edit');
		JToolBarHelper::deleteList('Are you sure?', $contrl.'remove');
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		}

		//Get data from the model
		if (FLEXI_J16GE) {
			$rows = & $this->get( 'Items');
		} else {
			$rows = & $this->get( 'Data');
		}
		
		// Parse configuration for every category
   	foreach ($rows as $cat)  $cat->config = FLEXI_J16GE ? new JRegistry($cat->config) : new JParameter($cat->config);
		
		if (FLEXI_J16GE) {
			$this->state = $this->get('State');
			$this->pagination	= $this->get('Pagination');
			// Preprocess the list of items to find ordering divisions.
			foreach ($rows as &$item) {
				$this->ordering[$item->parent_id][] = $item->id;
			}
		} else {
			$pageNav 	= & $this->get( 'Pagination' );
		}
		
		$categories = & $globalcats;
		if (FLEXI_J16GE) {
			$lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="inputbox"', false, true, $actions_allowed=array('core.edit'));
			$lists['destid'] = flexicontent_cats::buildcatselect($categories, 'destcid[]', '', false, 'class="inputbox" size="15" multiple="true"', false, true, $actions_allowed=array('core.edit'));
		} else if (FLEXI_ACCESS && ($user->gid < 25)) {
			if ((FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all')) || (FAccess::checkAllContentAccess('com_content','edit','users',$user->gmid,'content','all')) || (FAccess::checkAllContentAccess('com_content','editown','users',$user->gmid,'content','all')) || $CanCats) {
				$lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="inputbox"', false, false);
				$lists['destid'] = flexicontent_cats::buildcatselect($categories, 'destcid[]', '', false, 'class="inputbox" size="15" multiple="true"', false, false);
			} else {
				$lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="inputbox"');
				$lists['destid'] = flexicontent_cats::buildcatselect($categories, 'destcid[]', '', false, 'class="inputbox" size="15" multiple="true"');
			}
		} else {
			$lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="inputbox"');
			$lists['destid'] = flexicontent_cats::buildcatselect($categories, 'destcid[]', '', false, 'class="inputbox" size="15" multiple="true"');
		}
		//publish unpublished filter
		$lists['state']	= JHTML::_('grid.state', $filter_state );
		
		if (FLEXI_J16GE) {
			//publish unpublished filter
			$lists['language'] = flexicontent_html::buildlanguageslist('filter_language', 'class="inputbox" onchange="submitform();"', $filter_language, 2);
		}
		
		// search filter
		$lists['search']= $search;

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		$ordering = ($lists['order'] == $order_property) ? $order_property : '';

		//assign data to template
		$this->assignRef('lists'			, $lists);
		$this->assignRef('rows'				, $rows);
		if (FLEXI_J16GE) {
			$this->assignRef('permission'	, $perms);
			$this->assignRef('orderingx'	, $ordering);
		} else {
			$this->assignRef('CanRights'	, $CanRights);
			$this->assignRef('pageNav'		, $pageNav);
			$this->assignRef('ordering'		, $ordering);
		}
		$this->assignRef('user'				, $user);

		parent::display($tpl);
	}
}
?>
