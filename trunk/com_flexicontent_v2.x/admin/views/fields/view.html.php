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
class FlexicontentViewFields extends JViewLegacy 
{
	/**
	 * Creates the Entrypage
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		//initialise variables
		$app       = JFactory::getApplication();
		$cparams   = JComponentHelper::getParams( 'com_flexicontent' );
		$user      = JFactory::getUser();
		$db        = JFactory::getDBO();
		$document  = JFactory::getDocument();
		$option    = JRequest::getVar('option');
		
		FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
		flexicontent_html::loadJQuery();
		
		JHTML::_('behavior.tooltip');
		
		//get vars
		$filter_assigned  = $app->getUserStateFromRequest( $option.'.fields.filter_assigned', 	'filter_assigned', 	'', 'word' );
		$filter_fieldtype = $app->getUserStateFromRequest( $option.'.fields.filter_fieldtype', 	'filter_fieldtype', 	'', 'word' );
		$filter_state     = $app->getUserStateFromRequest( $option.'.fields.filter_state', 		'filter_state', 	'', 'word' );
		$filter_type      = $app->getUserStateFromRequest( $option.'.fields.filter_type', 		'filter_type', 		'', 'int' );
		$filter_order     = $app->getUserStateFromRequest( $option.'.fields.filter_order', 		'filter_order', 	't.ordering', 'cmd' );
		if ($filter_type && $filter_order == 't.ordering') {
			$filter_order	= $app->setUserState( $option.'.fields.filter_order', 'typeordering' );
		} else if (!$filter_type && $filter_order == 'typeordering') {
			$filter_order	= $app->setUserState( $option.'.fields.filter_order', 't.ordering' );
		}
		$filter_order_Dir	= $app->getUserStateFromRequest( $option.'.fields.filter_order_Dir',	'filter_order_Dir',	'ASC', 'word' );
		$search 			= $app->getUserStateFromRequest( $option.'.fields.search', 			'search', 			'', 'string' );
		$search 			= FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search ) ) ) : $db->getEscaped( trim(JString::strtolower( $search ) ) );
		
		if ( $cparams->get('show_usability_messages', 1) )     // Important usability messages
		{
			$notice_content_type_order = $app->getUserStateFromRequest( $option.'.fields.notice_content_type_order',	'notice_content_type_order',	0, 'int' );
			if (!$notice_content_type_order) {
				$app->setUserState( $option.'.fields.notice_content_type_order', 1 );
				$app->enqueueMessage(JText::_('FLEXI_DEFINE_FIELD_ORDER_FILTER_BY_TYPE'), 'notice');
				$app->enqueueMessage(JText::_('FLEXI_DEFINE_FIELD_ORDER_FILTER_WITHOUT_TYPE'), 'notice');
				$app->enqueueMessage(JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF'), 'message');
			}
		}
		
		//add css and submenu to document
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/flexi-lib.js' );

		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Create Submenu and check access
		FLEXISubmenu('CanFields');

		//create the toolbar
		$contrl = FLEXI_J16GE ? "fields." : "";
		JToolBarHelper::title( JText::_( 'FLEXI_FIELDS' ), 'fields' );
		if ($perms->CanCopyFields) {
			JToolBarHelper::custom( $contrl.'copy', 'copy.png', 'copy_f2.png', 'FLEXI_COPY' );
			JToolBarHelper::custom( $contrl.'copy_wvalues', 'copy.png', 'copy_f2.png', 'FLEXI_COPY_WITH_VALUES' );
			JToolBarHelper::divider();
		}
		JToolBarHelper::publishList($contrl.'publish');
		JToolBarHelper::unpublishList($contrl.'unpublish');
		if ($perms->CanAddField) {
			JToolBarHelper::addNew($contrl.'add');
		}
		if ($perms->CanEditField) {
			JToolBarHelper::editList($contrl.'edit');
		}
		if ($perms->CanDeleteField) {
			JToolBarHelper::deleteList('Are you sure?', $contrl.'remove');
		}
		
		JToolBarHelper::divider(); JToolBarHelper::spacer();
		$toggle_icon = FLEXI_J16GE ? 'refresh' : 'apply';
		$btn_task    = FLEXI_J16GE ? 'fields.toggleprop' : 'toggleprop';
		$extra_js    = "document.getElementById('adminForm').elements['propname'].value='issearch';";
		flexicontent_html::addToolBarButton(
			'FLEXI_TOGGLE_TEXT_SEARCHABLE', $toggle_icon, $full_js='', $msg_alert=JText::_('FLEXI_SELECT_FIELDS_TO_TOGGLE_PROPERTY'), $msg_confirm='',
			$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=false);
		
		$btn_task    = FLEXI_J16GE ? 'fields.toggleprop' : 'toggleprop';
		$extra_js    = "document.getElementById('adminForm').elements['propname'].value='isfilter';";
		flexicontent_html::addToolBarButton(
			'FLEXI_TOGGLE_FILTERABLE', $toggle_icon, $full_js='', $msg_alert=JText::_('FLEXI_SELECT_FIELDS_TO_TOGGLE_PROPERTY'), $msg_confirm='',
			$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=false);
		
		$btn_task    = FLEXI_J16GE ? 'fields.toggleprop' : 'toggleprop';
		$extra_js    = "document.getElementById('adminForm').elements['propname'].value='isadvsearch';";
		flexicontent_html::addToolBarButton(
			'FLEXI_TOGGLE_ADV_TEXT_SEARCHABLE', $toggle_icon, $full_js='', $msg_alert=JText::_('FLEXI_SELECT_FIELDS_TO_TOGGLE_PROPERTY'), $msg_confirm='',
			$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=false);
		
		$btn_task    = FLEXI_J16GE ? 'fields.toggleprop' : 'toggleprop';
		$extra_js    = "document.getElementById('adminForm').elements['propname'].value='isadvfilter';";
		flexicontent_html::addToolBarButton(
			'FLEXI_TOGGLE_ADV_FILTERABLE', $toggle_icon, $full_js='', $msg_alert=JText::_('FLEXI_SELECT_FIELDS_TO_TOGGLE_PROPERTY'), $msg_confirm='',
			$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=false);
		
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		}
		
		//Get data from the model
		$model = $this->getModel();
		$rows       = $this->get( FLEXI_J16GE ? 'Items' : 'Data' );
		$pagination = $this->get( 'Pagination' );
		$types      = $this->get( 'Typeslist' );
		$fieldtypes = $model->getFieldtypes($fields_in_groups = true);

		$lists = array();
		
		//build backend visible filter
		$ALL = mb_strtoupper(JText::_( 'FLEXI_ALL' ), 'UTF-8') . ' : ';
		$fftype 	= array();
		$fftype[] = JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_FIELDS_TYPE' ) .' -' );
		$fftype[] = JHTML::_('select.option',  'BV', $ALL . JText::_( 'FLEXI_BACKEND_FIELDS' ) );
		$fftype[] = JHTML::_('select.option',  'C', $ALL . JText::_( 'FLEXI_CORE_FIELDS' ) );
		$fftype[] = JHTML::_('select.option',  'NC', $ALL . JText::_( 'FLEXI_NON_CORE_FIELDS' ) );
		
		foreach ($fieldtypes as $field_group => $ft_types) {
			$fftype[] = JHTML::_('select.optgroup', $field_group );
			foreach ($ft_types as $field_type => $ftdata) {
				$field_friendlyname = str_ireplace("FLEXIcontent - ","",$ftdata->field_friendlyname);
				$fftype[] = JHTML::_('select.option', $field_type, '-'.$ftdata->assigned.'- '. $field_friendlyname);
			}
			$fftype[] = JHTML::_('select.optgroup', '' );
		}
		
		$lists['fftype'] = JHTML::_('select.genericlist', $fftype, 'filter_fieldtype', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_fieldtype );

		//build arphaned/assigned filter
		$assigned 	= array();
		$assigned[] = JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_FIELDS' ) .' -' );
		$assigned[] = JHTML::_('select.option',  'O', JText::_( 'FLEXI_ORPHANED' ) );
		$assigned[] = JHTML::_('select.option',  'A', JText::_( 'FLEXI_ASSIGNED' ) );

		$lists['assigned'] = JHTML::_('select.genericlist', $assigned, 'filter_assigned', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_assigned );

		//build type select list
		$lists['filter_type'] = flexicontent_html::buildtypesselect($types, 'filter_type', $filter_type, true, 'class="inputbox" size="1" onchange="submitform( );"', 'filter_type');
		
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
		$this->assignRef('permission'		, $perms);
		$this->assignRef('filter_type'  , $filter_type);
		$this->assignRef('lists'      	, $lists);
		$this->assignRef('rows'      	, $rows);
		$this->assignRef('ordering'		, $ordering);
		$this->assignRef('pagination'	, $pagination);

		parent::display($tpl);
	}
}
?>