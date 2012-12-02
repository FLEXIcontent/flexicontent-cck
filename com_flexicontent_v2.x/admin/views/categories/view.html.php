<?php
/**
 * @version 1.5 stable $Id: view.html.php 1223 2012-03-30 08:34:34Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL, see LICENSE.php
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
class FlexicontentViewCategories extends JViewLegacy {

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
		$filter_language	= $mainframe->getUserStateFromRequest( $option.'.categories.filter_language', 'filter_language', '*', 'cmd' );
		
		$search 			= $mainframe->getUserStateFromRequest( $option.'.categories.search', 			'search', 			'', 'string' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		
		$permission = FlexicontentHelperPerm::getPerm();
		if (!$permission->CanCats) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		//Create Submenu
		FLEXISubmenu('CanCats');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_CATEGORIES' ), 'fc_categories' );
		
		$toolbar =&JToolBar::getInstance('toolbar');
		$toolbar->appendButton('Popup', 'params', JText::_('FLEXI_COPY_PARAMS'), JURI::base().'index.php?option=com_flexicontent&amp;view=categories&amp;layout=params&amp;tmpl=component', 400, 440);
		
		JToolBarHelper::spacer();
		JToolBarHelper::publishList('categories.publish');
		JToolBarHelper::unpublishList('categories.unpublish');
		JToolBarHelper::addNew('category.add');
		JToolBarHelper::editList('category.edit');
		JToolBarHelper::deleteList('Are you sure?', 'categories.remove');
		if($permission->CanConfig) JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');

		//Get data from the model
		$rows = & $this->get( 'Items');
		
		// Parse configuration for every category
   	foreach ($rows as $cat) $cat->config = new JParameter($cat->config);

		$this->state = $this->get('State');
		$this->pagination	= $this->get('Pagination');
		$children = array();
		
		// Preprocess the list of items to find ordering divisions.
		foreach ($rows as &$item) {
			$this->ordering[$item->parent_id][] = $item->id;
		}
		
		$categories = $globalcats;
		$lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="inputbox"', false, true, $actions_allowed=array('core.edit'));
		$lists['destid'] = flexicontent_cats::buildcatselect($categories, 'destcid[]', '', false, 'class="inputbox" size="15" multiple="true"', false, true, $actions_allowed=array('core.edit'));

		//publish unpublished filter
		$lists['state']	= JHTML::_('grid.state', $filter_state );

		//publish unpublished filter
		$lists['language'] = flexicontent_html::buildlanguageslist('filter_language', 'class="inputbox" onchange="submitform();"', $filter_language, 2);
		
		// search filter
		$lists['search']= $search;

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		$ordering = ($lists['order'] == $order_property) ? $order_property : '';

		//assign data to template
		$this->assignRef('lists'      , $lists);
		$this->assignRef('rows'       , $rows);
		$this->assignRef('permission' , $permission);
		$this->assignRef('orderingx'  , $ordering);
		$this->assignRef('user'       , $user);

		parent::display($tpl);
	}
}
?>
