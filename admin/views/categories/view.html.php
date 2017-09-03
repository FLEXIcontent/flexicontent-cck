<?php
/**
 * @version 1.5 stable $Id: view.html.php 1900 2014-05-03 07:25:51Z ggppdk $
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
class FlexicontentViewCategories extends JViewLegacy
{
	function display( $tpl = null )
	{
		// ***
		// *** Initialise variables
		// ***

		global $globalcats;
		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');

		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDbo();
		$document = JFactory::getDocument();
		$order_property = 'a.lft';
		
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
		$filter_cats      = $model->getState( 'filter_cats' );
		$filter_level     = $model->getState( 'filter_level' );
		$filter_access    = $model->getState( 'filter_access' );
		$filter_language	= $model->getState( 'filter_language' );

		if ($filter_state) $count_filters++;
		if ($filter_cats) $count_filters++;
		if ($filter_level) $count_filters++;
		if ($filter_access) $count_filters++;
		if ($filter_language) $count_filters++;
		
		// Item ID filter
		$filter_id = $model->getState('filter_id');
		if ($filter_id) $count_filters++;


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
		FLEXIUtilities::ManagerSideMenu('CanCats');
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_CATEGORIES' );
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, 'fc_categories' );
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$js = '';

		$contrl = "categories.";
		$contrl_singular = "category.";
		$toolbar = JToolbar::getInstance('toolbar');
		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);
		
		// Copy Parameters
		$btn_task = '';
		$popup_load_url = JUri::base().'index.php?option=com_flexicontent&view=categories&layout=params&tmpl=component';
		//$toolbar->appendButton('Popup', 'params', JText::_('FLEXI_COPY_PARAMS'), str_replace('&', '&amp;', $popup_load_url), 600, 440);
		$js .= "
			jQuery('#toolbar-params a.toolbar, #toolbar-params button').attr('href', '".$popup_load_url."')
				.attr('onclick', 'var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 600, 440, function(){document.body.innerHTML=\'<span class=\"fc_loading_msg\">"
					.$loading_msg."</span>\'; window.location.reload(false)}, {\'title\': \'".flexicontent_html::encodeHTML(JText::_('FLEXI_COPY_PARAMS'), 2)."\'}); return false;');
		";
		JToolbarHelper::custom( $btn_task, 'params.png', 'params_f2.png', 'FLEXI_COPY_PARAMS', false );

		//$toolbar->appendButton('Popup', 'move', JText::_('FLEXI_BATCH'), JUri::base().'index.php?option=com_flexicontent&amp;view=categories&amp;layout=batch&amp;tmpl=component', 800, 440);
		JToolbarHelper::divider();
		
		$add_divider = false;
		if ( $user->authorise('core.create', 'com_flexicontent') ) {
			$cancreate_cat = true;
		} else {
			$usercats = FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed = array('core.create')
				, $require_all = true, $check_published = true, $specific_catids = false, $find_first = true
			);
			$cancreate_cat  = count($usercats) > 0;
		}
		
		if ( $cancreate_cat ) {
			JToolbarHelper::addNew($contrl_singular.'add');
			$add_divider = true;
		}
		if ( $user->authorise('core.edit', 'com_flexicontent') || $user->authorise('core.edit.own', 'com_flexicontent') ) {
			JToolbarHelper::editList($contrl_singular.'edit');
			$add_divider = true;
		}
		
		$add_divider = false;
		if ( $user->authorise('core.edit.state', 'com_flexicontent') || $user->authorise('core.edit.state.own', 'com_flexicontent') )
		{
			JToolbarHelper::publishList($contrl.'publish');
			JToolbarHelper::unpublishList($contrl.'unpublish');
			JToolbarHelper::divider();
			JToolbarHelper::archiveList($contrl.'archive');
		}
		
		$add_divider = false;
		if ( $filter_state == -2 && $user->authorise('core.delete', 'com_flexicontent') ) {
			//JToolbarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
			// This will work in J2.5+ too and is offers more options (above a little bogus in J1.5, e.g. bad HTML id tag)
			$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE'));
			$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
			$btn_task    = $contrl.'remove';
			$extra_js    = "";
			flexicontent_html::addToolBarButton(
				'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
			
			$add_divider = true;
		}
		elseif ( $user->authorise('core.edit.state', 'com_flexicontent') )
		{
			JToolbarHelper::trash($contrl.'trash');
			$add_divider = true;
		}
		if ($add_divider) JToolbarHelper::divider();

		JToolbarHelper::checkin($contrl.'checkin');

		$appsman_path = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'appsman';
		if (file_exists($appsman_path))
		{
			$btn_icon = 'icon-download';
			$btn_name = 'download';
			$btn_task    = 'appsman.exportxml';
			$extra_js    = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: 'categories'}).appendTo(jQuery(f));";
			flexicontent_html::addToolBarButton(
				'Export now',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm=JText::_('FLEXI_EXPORT_NOW_AS_XML'),
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-info", $btn_icon);
			
			$btn_icon = 'icon-box-add';
			$btn_name = 'box-add';
			$btn_task    = 'appsman.addtoexport';
			$extra_js    = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: 'categories'}).appendTo(jQuery(f));";
			flexicontent_html::addToolBarButton(
				'Add to export',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm=JText::_('FLEXI_ADD_TO_EXPORT_LIST'),
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-info", $btn_icon);
		}
		
		if ($perms->CanConfig)
		{
			JToolbarHelper::custom($contrl.'rebuild', 'refresh.png', 'refresh_f2.png', 'JTOOLBAR_REBUILD', false);
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		if ($js)
		{
			$document->addScriptDeclaration('
				jQuery(document).ready(function(){
					' . $js . '
				});
			');
		}


		// Get data from the model
		if ( $print_logging_info )  $start_microtime = microtime(true);
		$rows = $this->get( 'Items' );
		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Get assigned items
		$rowids = array();
		foreach ($rows as $row) $rowids[] = $row->id;
		if ( $print_logging_info )  $start_microtime = microtime(true);
		//$rowtotals = $model->getAssignedItems($rowids);
		$byStateTotals = $model->countItemsByState($rowids);
		if ( $print_logging_info ) @$fc_run_times['execute_sec_queries'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		foreach ($rows as $row) {
			//$row->nrassigned = isset($rowtotals[$row->id]) ? $rowtotals[$row->id]->nrassigned : 0;
			$row->byStateTotals = isset($byStateTotals[$row->id]) ? $byStateTotals[$row->id] : array();
		}
		
		// Parse configuration for every category
   	foreach ($rows as $cat)  $cat->config = new JRegistry($cat->config);
		
		$this->state = $this->get('State');

		// Preprocess the list of items to find ordering divisions.
		foreach ($rows as &$item)
		{
			$this->ordering[$item->parent_id][] = $item->id;
		}
		unset($item);  // unset the variable reference to avoid trouble if variable is reused, thus overwritting last pointed variable

		// Create pagination object
		$pagination = $this->get( 'Pagination' );
		$inline_ss_max = 50000;
		$drag_reorder_max = 150;


		// ***
		// *** Create List Filters
		// ***

		$lists = array();

		$categories = & $globalcats;
		$lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="use_select2_lib"', false, true, $actions_allowed=array('core.edit'));
		$lists['destid'] = flexicontent_cats::buildcatselect($categories, 'destcid[]', '', false, 'class="use_select2_lib" size="10" multiple="true"', false, true, $actions_allowed=array('core.edit'));


		// build category filter (it's subtree will be displayed)
		$categories = $globalcats;
		$lists['cats'] = ($filter_cats || 1 ? '<div class="add-on">'.JText::_('FLEXI_CATEGORY').'</div>' : '').
			flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, '-', 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $check_published=true, $check_perms=false);
		
		// build depth level filter
		$options	= array();
		$options[]	= JHtml::_('select.option', '', '-'/*JText::_('FLEXI_SELECT_MAX_DEPTH')*/);
		for($i=1; $i<=10; $i++) $options[]	= JHtml::_('select.option', $i, $i);
		$fieldname =  $elementid = 'filter_level';
		$attribs = 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"';
		$lists['level']	= ($filter_level || 1 ? '<div class="add-on">'.JText::_('FLEXI_MAX_DEPTH').'</div>' : '').
			JHtml::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_level, $elementid, $translate=true );
		
		// build publication state filter
		$options = JHtml::_('jgrid.publishedOptions');
		array_unshift($options, JHtml::_('select.option', '', '-'/*JText::_('JOPTION_SELECT_PUBLISHED')*/) );
		$fieldname =  $elementid = 'filter_state';
		$attribs = 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"';
		$lists['state'] = ($filter_state || 1 ? '<div class="add-on">'.JText::_('FLEXI_STATE').'</div>' : '').
			JHtml::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_state, $elementid, $translate=true );
		
		// build access level filter
		$options = JHtml::_('access.assetgroups');
		array_unshift($options, JHtml::_('select.option', '', '-'/*JText::_('JOPTION_SELECT_ACCESS')*/) );
		$fieldname =  $elementid = 'filter_access';
		$attribs = 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"';
		$lists['access'] = ($filter_access || 1 ? '<div class="add-on">'.JText::_('FLEXI_ACCESS').'</div>' : '').
			JHtml::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid, $translate=true );
		
		// build language filter
		$lists['language'] = ($filter_language || 1 ? '<div class="add-on">'.JText::_('FLEXI_LANGUAGE').'</div>' : '').
			flexicontent_html::buildlanguageslist('filter_language', 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $filter_language, '-'/*2*/);
		
		// text search filter
		$lists['search']= $search;

		// search id
		$lists['filter_id'] = $filter_id;
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		$orderingx = ($lists['order'] == $order_property && strtolower($lists['order_Dir']) == 'asc') ? $order_property : '';

		//assign data to template
		$this->CanTemplates = $perms->CanTemplates;
		$this->count_filters = $count_filters;

		$this->lists = $lists;
		$this->rows = $rows;
		$this->pagination = $pagination;

		$this->perms = $perms;
		$this->orderingx = $orderingx;
		$this->user = $user;

		$this->inline_ss_max = $inline_ss_max;
		$this->option = $option;
		$this->view = $view;

		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
	}
}