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
 * View class for the FLEXIcontent reviews screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewReviews extends JViewLegacy
{
	function display( $tpl = null )
	{
		flexicontent_html::__DEV_check_reviews_table();

		// ***
		// *** Initialise variables
		// ***

		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');

		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDbo();
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

		if ($filter_state) $count_filters++;
		
		// Text search
		$search = $model->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));
		
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
		
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);

		// Add JS frameworks
		flexicontent_html::loadFramework('select2');

		// Add js function to overload the joomla submitform validation
		JHtml::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);



		// ***
		// *** Create Submenu & Toolbar
		// ***
		
		// Get user's global permissions
		$perms = FlexicontentHelperPerm::getPerm();

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanReviews');
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_REVIEWS' );
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, 'reviews' );
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$js = "jQuery(document).ready(function(){";

		$contrl = "reviews.";
		$contrl_singular = "review.";
		$toolbar = JToolbar::getInstance('toolbar');
		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);

		if ($perms->CanConfig)
		{
			$btn_task = '';
			$popup_load_url = JUri::base().'index.php?option=com_flexicontent&view=reviews&layout=import&tmpl=component';
			//$toolbar->appendButton('Popup', 'import', JText::_('FLEXI_IMPORT'), str_replace('&', '&amp;', $popup_load_url), 430, 500);
			$js .= "
				jQuery('#toolbar-import a.toolbar, #toolbar-import button')
					.attr('onclick', 'javascript:;')
					.attr('href', '".$popup_load_url."')
					.attr('rel', '{handler: \'iframe\', size: {x: 430, y: 500}, onClose: function() {}}');
			";
			JToolbarHelper::custom( $btn_task, 'import.png', 'import_f2.png', 'FLEXI_IMPORT', false );
			JHtml::_('behavior.modal', '#toolbar-import a.toolbar, #toolbar-import button');
			JToolbarHelper::divider();
		}

		JToolbarHelper::publishList($contrl.'publish');
		JToolbarHelper::unpublishList($contrl.'unpublish');
		if ($perms->CanCreateReviews)
		{
			JToolbarHelper::addNew($contrl.'add');
		}

		if (1)
		{
			JToolbarHelper::editList($contrl.'edit');
		}

		if (1)
		{
			//JToolbarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
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
			JToolbarHelper::divider(); JToolbarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		$js .= "});";
		$document->addScriptDeclaration($js);
		
		
		// Get data from the model
		if ( $print_logging_info )  $start_microtime = microtime(true);
		$rows = $this->get( 'Data');
		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// Create pagination object
		$pagination = $this->get( 'Pagination' );

		$lists = array();
		
		// build publication state filter
		$states 	= array();
		$states[] = JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_SELECT_STATE' )*/ );
		$states[] = JHtml::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$states[] = JHtml::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		//$states[] = JHtml::_('select.option',  '-2', JText::_( 'FLEXI_TRASHED' ) );
		
		$lists['state'] = ($filter_state || 1 ? '<div class="add-on">'.JText::_('FLEXI_STATE').'</div>' : '').
			JHtml::_('select.genericlist', $states, 'filter_state', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_state );
			//JHtml::_('grid.state', $filter_state );
		
		
		// text search filter
		$lists['search']= $search;
		
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		
		
		//assign data to template
		$this->count_filters = $count_filters;
		$this->lists = $lists;
		$this->rows = $rows;
		$this->pagination = $pagination;

		$this->option = $option;
		$this->view = $view;

		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
	}
}
