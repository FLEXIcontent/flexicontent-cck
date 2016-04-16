<?php
/**
 * @version 1.5 stable $Id: view.html.php 1889 2014-04-26 03:25:28Z ggppdk $
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

jimport('legacy.view.legacy');
use Joomla\String\StringHelper;

/**
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewTypes extends JViewLegacy
{
	function display( $tpl = null )
	{
		// ********************
		// Initialise variables
		// ********************
		
		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');
		
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		
		// Get model
		$model = $this->getModel();

		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		
		
		// ***********
		// Get filters
		// ***********
		
		$count_filters = 0;
		
		// various filters
		$filter_state   = $model->getState('filter_state');
		$filter_access  = $model->getState('filter_access');
		if ($filter_state) $count_filters++; if ($filter_access) $count_filters++;
		
		// Order and order direction
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');
		
		// Text search
		$search = $model->getState( 'search' );
		$search = $db->escape( StringHelper::trim(StringHelper::strtolower( $search ) ) );
		
		
		
		// ****************************
		// Important usability messages
		// ****************************
		
		if ( $cparams->get('show_usability_messages', 1) )
		{
		}
		
		
		
		// **************************
		// Add css and js to document
		// **************************
		
		flexicontent_html::loadFramework('select2');
		//JHTML::_('behavior.tooltip');
		
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH);
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH);
		
		
		
		// *****************************
		// Get user's global permissions
		// *****************************
		
		$perms = FlexicontentHelperPerm::getPerm();
		
		
		
		// ************************
		// Create Submenu & Toolbar
		// ************************
		
		// Create Submenu (and also check access to current view)
		FLEXISubmenu('CanTypes');
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_TYPES' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'types' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		$contrl = "types.";
		JToolBarHelper::custom( $contrl.'copy', 'copy.png', 'copy_f2.png', 'FLEXI_COPY' );
		JToolBarHelper::divider(); JToolBarHelper::spacer();
		JToolBarHelper::publishList($contrl.'publish');
		JToolBarHelper::unpublishList($contrl.'unpublish');
		JToolBarHelper::addNew($contrl.'add');
		JToolBarHelper::editList($contrl.'edit');
		
		//JToolBarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
		// This will work in J2.5+ too and is offers more options (above a little bogus in J1.5, e.g. bad HTML id tag)
		$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE') );
		$msg_confirm = JText::_('FLEXI_ITEMS_DELETE_CONFIRM');
		$btn_task    = $contrl.'remove';
		$extra_js    = "";
		flexicontent_html::addToolBarButton(
			'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
			$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
		
		// Checkin
		JToolbarHelper::checkin($contrl.'checkin');
		
		$appsman_path = JPATH_COMPONENT_ADMINISTRATOR.DS.'views'.DS.'appsman';
		if (file_exists($appsman_path))
		{
			$btn_icon = 'icon-download';
			$btn_name = 'download';
			$btn_task    = 'appsman.exportxml';
			$extra_js    = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: 'flexicontent_types'}).appendTo(jQuery(f));";
			flexicontent_html::addToolBarButton(
				'Export now',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm='Export now as XML',
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-warning", $btn_icon);
			
			$btn_icon = 'icon-box-add';
			$btn_name = 'box-add';
			$btn_task    = 'appsman.addtoexport';
			$extra_js    = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: 'flexicontent_types'}).appendTo(jQuery(f));";
			flexicontent_html::addToolBarButton(
				'Add to export',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm='Add to export list',
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-warning", $btn_icon);
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
		
		// Get data from the model
		if ( $print_logging_info )  $start_microtime = microtime(true);
		$rows       = $this->get( 'Items' );
		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		$pagination = $this->get( 'Pagination' ); // Pagination
		
		// Create type's parameters
		foreach($rows as $type) {
			$type->config = FLEXI_J16GE ? new JRegistry($type->config) : new JParameter($type->config);
		}
		
		$lists = array();
		
		// build publication state filter
		$states 	= array();
		$states[] = JHTML::_('select.option',  '', '-'/*JText::_( 'FLEXI_SELECT_STATE' )*/ );
		$states[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$states[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		//$states[] = JHTML::_('select.option',  '-2', JText::_( 'FLEXI_TRASHED' ) );
		
		$lists['state'] = ($filter_state || 1 ? '<label class="label">'.JText::_('FLEXI_STATE').'</label>' : '').
			JHTML::_('select.genericlist', $states, 'filter_state', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_state );
			//JHTML::_('grid.state', $filter_state );
		
		
		// build access level filter
		$options = JHtml::_('access.assetgroups');
		array_unshift($options, JHtml::_('select.option', '', '-'/*JText::_('JOPTION_SELECT_ACCESS')*/) );
		$fieldname =  $elementid = 'filter_access';
		$attribs = 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"';
		$lists['access'] = ($filter_access || 1 ? '<label class="label">'.JText::_('FLEXI_ACCESS').'</label>' : '').
			JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid, $translate=true );
		
		
		// text search filter
		$lists['search']= $search;
		
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		
		
		//assign data to template
		$this->assignRef('CanTemplates', $perms->CanTemplates);
		$this->assignRef('count_filters', $count_filters);
		$this->assignRef('lists'			, $lists);
		$this->assignRef('rows'				, $rows);
		$this->assignRef('pagination'	, $pagination);
		
		$this->assignRef('option', $option);
		$this->assignRef('view', $view);
		
		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
	}
}
?>