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
 * View class for the FLEXIcontent tags screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewTags extends JViewLegacy
{
	function display( $tpl = null )
	{

		// ***
		// *** Initialise variables
		// ***

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



		// ***
		// *** Get filters
		// ***

		$count_filters = 0;

		// Various filters
		$filter_state     = $model->getState( 'filter_state' );
		$filter_assigned  = $model->getState( 'filter_assigned' );
		if ($filter_state) $count_filters++;
		if ($filter_assigned) $count_filters++;
		
		// Text search
		$search = $model->getState( 'search' );
		$search = $db->escape( StringHelper::trim(StringHelper::strtolower( $search ) ) );
		
		// Order and order direction
		$filter_order     = $model->getState('filter_order');
		$filter_order_Dir = $model->getState('filter_order_Dir');


		// ***
		// *** Important usability messages
		// ***

		if ( $cparams->get('show_usability_messages', 1) )
		{
		}
		
		
		
		// ***
		// *** Add css and js to document
		// ***
		
		flexicontent_html::loadFramework('select2');
		//JHTML::_('behavior.tooltip');
		
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);



		// ***
		// *** Create Submenu & Toolbar
		// ***
		
		// Get user's global permissions
		$perms = FlexicontentHelperPerm::getPerm();

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanTags');
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_TAGS' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'tags' );
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$js = "jQuery(document).ready(function(){";

		$contrl = "tags.";
		$contrl_singular = "tag.";
		$toolbar = JToolBar::getInstance('toolbar');
		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);

		if ($perms->CanConfig)
		{
			$btn_task = '';
			$popup_load_url = JURI::base().'index.php?option=com_flexicontent&view=tags&layout=import&tmpl=component';
			//$toolbar->appendButton('Popup', 'import', JText::_('FLEXI_IMPORT'), str_replace('&', '&amp;', $popup_load_url), 430, 500);
			$js .= "
				jQuery('#toolbar-import a.toolbar, #toolbar-import button')
					.attr('onclick', 'javascript:;')
					.attr('href', '".$popup_load_url."')
					.attr('rel', '{handler: \'iframe\', size: {x: 430, y: 500}, onClose: function() {}}');
			";
			JToolBarHelper::custom( $btn_task, 'import.png', 'import_f2.png', 'FLEXI_IMPORT', false );
			JHtml::_('behavior.modal', '#toolbar-import a.toolbar, #toolbar-import button');
			JToolBarHelper::divider();
		}

		JToolBarHelper::publishList($contrl.'publish');
		JToolBarHelper::unpublishList($contrl.'unpublish');
		if ($perms->CanCreateTags)
		{
			JToolBarHelper::addNew($contrl.'add');
		}

		if (1)
		{
			JToolBarHelper::editList($contrl.'edit');
		}

		if (1)
		{
			//JToolBarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
			$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE'));
			$msg_confirm = JText::_('FLEXI_ITEMS_DELETE_CONFIRM');
			$btn_task    = $contrl.'remove';
			$extra_js    = "";
			flexicontent_html::addToolBarButton(
				'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
		}

		JToolbarHelper::checkin($contrl.'checkin');
		
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolBarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		$js .= "});";
		$document->addScriptDeclaration($js);
		
		
		// Get data from the model
		if ( $print_logging_info )  $start_microtime = microtime(true);
		$rows = $this->get( 'Data');
		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// Get assigned items (via separate query),  (if not already retrieved)
		// ... when we order by assigned then this is already done via main DB query
		if ( $filter_order!='nrassigned' )
		{
			$rowids = array();
			foreach ($rows as $row) $rowids[] = $row->id;
			if ( $print_logging_info )  $start_microtime = microtime(true);
			$rowtotals = $model->getAssignedItems($rowids);
			if ( $print_logging_info ) @$fc_run_times['execute_sec_queries'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			foreach ($rows as $row) {
				$row->nrassigned = isset($rowtotals[$row->id]) ? $rowtotals[$row->id]->nrassigned : 0;
			}
		}

		// Create pagination object
		$pagination = $this->get( 'Pagination' );

		$lists = array();
		
		// Build orphaned/assigned filter
		$assigned 	= array();
		$assigned[] = JHTML::_('select.option',  '', '-'/*JText::_('FLEXI_ALL_TAGS')*/);
		$assigned[] = JHTML::_('select.option',  'O', JText::_( 'FLEXI_ORPHANED' ) );
		$assigned[] = JHTML::_('select.option',  'A', JText::_( 'FLEXI_ASSIGNED' ) );

		$lists['assigned'] = ($filter_assigned || 1 ? '<div class="add-on">'.JText::_('FLEXI_ASSIGNED').'</div>' : '').
			JHTML::_('select.genericlist', $assigned, 'filter_assigned', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_assigned );
		
		// build publication state filter
		$states 	= array();
		$states[] = JHTML::_('select.option',  '', '-'/*JText::_( 'FLEXI_SELECT_STATE' )*/ );
		$states[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$states[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		//$states[] = JHTML::_('select.option',  '-2', JText::_( 'FLEXI_TRASHED' ) );
		
		$lists['state'] = ($filter_state || 1 ? '<div class="add-on">'.JText::_('FLEXI_STATE').'</div>' : '').
			JHTML::_('select.genericlist', $states, 'filter_state', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_state );
			//JHTML::_('grid.state', $filter_state );
		
		
		// text search filter
		$lists['search']= $search;
		
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		
		
		//assign data to template
		$this->assignRef('count_filters', $count_filters);
		$this->lists = $lists;
		$this->rows = $rows;
		$this->pagination = $pagination;
		
		$this->option = $option;
		$this->view = $view;
		
		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
	}
}
