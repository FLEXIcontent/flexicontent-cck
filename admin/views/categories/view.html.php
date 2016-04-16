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
		// ********************
		// Initialise variables
		// ********************
		
		global $globalcats;
		
		$app      = JFactory::getApplication();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		$option   = JRequest::getCmd('option');
		$view     = JRequest::getVar('view');
		$order_property = 'c.lft';
		
		// Get model
		$model =  $this->getModel();
		
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		
		// ***********
		// Get filters
		// ***********
		$count_filters = 0;
		
		// various filters
		$filter_state     = $model->getState( 'filter_state' );
		$filter_cats      = $model->getState( 'filter_cats' );
		$filter_level     = $model->getState( 'filter_level' );
		$filter_access    = $model->getState( 'filter_access' );
		$filter_language	= $model->getState( 'filter_language' );
		
		if ($filter_state) $count_filters++; if ($filter_cats) $count_filters++;
		if ($filter_level) $count_filters++; if ($filter_access) $count_filters++;
		if ($filter_language) $count_filters++;
		
		// Item ID filter
		$filter_id  = $model->getState('filter_id');
		if ($filter_id) $count_filters++;
		
		// text search
		$search = $model->getState( 'search' );
		$search = $db->escape( StringHelper::trim(StringHelper::strtolower( $search ) ) );
		
		// ordering
		$filter_order     = $model->getState( 'filter_order' );
		$filter_order_Dir = $model->getState( 'filter_order_Dir' );
		
		
		
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
		FLEXISubmenu('CanCats');
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_CATEGORIES' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'fc_categories' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		$js = "jQuery(document).ready(function(){";
		
		$contrl = "categories.";
		$contrl_singular = "category.";
		$toolbar = JToolBar::getInstance('toolbar');
		
		// Copy Parameters
		$btn_task = '';
		$popup_load_url = JURI::base().'index.php?option=com_flexicontent&view=categories&layout=params&tmpl=component';
		if (FLEXI_J30GE || !FLEXI_J16GE) {  // Layout of Popup button broken in J3.1, add in J1.5 it generates duplicate HTML tag id (... just for validation), so add manually
			$js .= "
				jQuery('#toolbar-params a.toolbar, #toolbar-params button')
					.attr('onclick', 'javascript:;')
					.attr('href', '". $popup_load_url ."')
					.attr('rel', '{handler: \'iframe\', size: {x: 600, y: 440}, onClose: function() {}}');
			";
			JToolBarHelper::custom( $btn_task, 'params.png', 'params_f2.png', 'FLEXI_COPY_PARAMS', false );
			JHtml::_('behavior.modal', '#toolbar-params a.toolbar, #toolbar-params button');
		} else {
			$toolbar->appendButton('Popup', 'params', JText::_('FLEXI_COPY_PARAMS'), str_replace('&', '&amp;', $popup_load_url), 600, 440);
		}
		//$toolbar->appendButton('Popup', 'move', JText::_('FLEXI_BATCH'), JURI::base().'index.php?option=com_flexicontent&amp;view=categories&amp;layout=batch&amp;tmpl=component', 800, 440);
		JToolBarHelper::divider();
		
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
			JToolBarHelper::addNew($contrl_singular.'add');
			$add_divider = true;
		}
		if ( $user->authorise('core.edit', 'com_flexicontent') || $user->authorise('core.edit.own', 'com_flexicontent') ) {
			JToolBarHelper::editList($contrl_singular.'edit');
			$add_divider = true;
		}
		
		$add_divider = false;
		if ( $user->authorise('core.edit.state', 'com_flexicontent') || $user->authorise('core.edit.state.own', 'com_flexicontent') ) {
			JToolBarHelper::publishList($contrl.'publish');
			JToolBarHelper::unpublishList($contrl.'unpublish');
			JToolBarHelper::divider();
			JToolBarHelper::archiveList($contrl.'archive');
		}
		
		$add_divider = false;
		if ( $filter_state == -2 && $user->authorise('core.delete', 'com_flexicontent') ) {
			//JToolBarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
			// This will work in J2.5+ too and is offers more options (above a little bogus in J1.5, e.g. bad HTML id tag)
			$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE') );
			$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
			$btn_task    = $contrl.'remove';
			$extra_js    = "";
			flexicontent_html::addToolBarButton(
				'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
			
			$add_divider = true;
		}
		elseif ( $user->authorise('core.edit.state', 'com_flexicontent') ) {
			JToolBarHelper::trash($contrl.'trash');
			$add_divider = true;
		}
		if ($add_divider) JToolBarHelper::divider();
		
		// Checkin
		JToolBarHelper::checkin($contrl.'checkin');

		$appsman_path = JPATH_COMPONENT_ADMINISTRATOR.DS.'views'.DS.'appsman';
		if (file_exists($appsman_path))
		{
			$btn_icon = 'icon-download';
			$btn_name = 'download';
			$btn_task    = 'appsman.exportxml';
			$extra_js    = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: 'categories'}).appendTo(jQuery(f));";
			flexicontent_html::addToolBarButton(
				'Export now',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm='Export now as XML',
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-warning", $btn_icon);
			
			$btn_icon = 'icon-box-add';
			$btn_name = 'box-add';
			$btn_task    = 'appsman.addtoexport';
			$extra_js    = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: 'categories'}).appendTo(jQuery(f));";
			flexicontent_html::addToolBarButton(
				'Add to export',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm='Add to export list',
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true, $btn_class="btn-warning", $btn_icon);
		}
		
		if ($perms->CanConfig) {
			//JToolBarHelper::custom($contrl.'rebuild', 'refresh.png', 'refresh_f2.png', 'JTOOLBAR_REBUILD', false);
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
		$rows = $this->get( 'Items');
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
		foreach ($rows as &$item) {
			$this->ordering[$item->parent_id][] = $item->id;
		}
		unset($item);  // unset the variable reference to avoid trouble if variable is reused, thus overwritting last pointed variable
		
		$pagination 	= $this->get( 'Pagination' );
		
		$categories = & $globalcats;
		$lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="use_select2_lib"', false, true, $actions_allowed=array('core.edit'));
		$lists['destid'] = flexicontent_cats::buildcatselect($categories, 'destcid[]', '', false, 'class="use_select2_lib" size="10" multiple="true"', false, true, $actions_allowed=array('core.edit'));
		
		
		// *******************
		// Create Form Filters
		// *******************
		
		// filter by a category (it's subtree will be displayed)
		$categories = $globalcats;
		$lists['cats'] = ($filter_cats || 1 ? '<label class="label">'.JText::_('FLEXI_CATEGORY').'</label>' : '').
			flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, '-', 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $check_published=true, $check_perms=false);
		
		// filter depth level
		$options	= array();
		$options[]	= JHtml::_('select.option', '', '-'/*JText::_('FLEXI_SELECT_MAX_DEPTH')*/);
		for($i=1; $i<=10; $i++) $options[]	= JHtml::_('select.option', $i, $i);
		$fieldname =  $elementid = 'filter_level';
		$attribs = 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"';
		$lists['level']	= ($filter_level || 1 ? '<label class="label">'.JText::_('FLEXI_MAX_DEPTH').'</label>' : '').
			JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_level, $elementid, $translate=true );
		
		// filter publication state
		$options = JHtml::_('jgrid.publishedOptions');
		array_unshift($options, JHtml::_('select.option', '', '-'/*JText::_('JOPTION_SELECT_PUBLISHED')*/) );
		$fieldname =  $elementid = 'filter_state';
		$attribs = 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"';
		$lists['state']	= ($filter_state || 1 ? '<label class="label">'.JText::_('FLEXI_STATE').'</label>' : '').
			JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_state, $elementid, $translate=true );
		
		// filter access level
		$options = JHtml::_('access.assetgroups');
		array_unshift($options, JHtml::_('select.option', '', '-'/*JText::_('JOPTION_SELECT_ACCESS')*/) );
		$fieldname =  $elementid = 'filter_access';
		$attribs = 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"';
		$lists['access'] = ($filter_access || 1 ? '<label class="label">'.JText::_('FLEXI_ACCESS').'</label>' : '').
			JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid, $translate=true );
		
		// filter language
		$lists['language'] = ($filter_language || 1 ? '<label class="label">'.JText::_('FLEXI_LANGUAGE').'</label>' : '').
			flexicontent_html::buildlanguageslist('filter_language', 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $filter_language, '-'/*2*/);
		
		// filter search word
		$lists['search']= $search;
		// search id
		$lists['filter_id'] = $filter_id;
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		$orderingx = ($lists['order'] == $order_property && strtolower($lists['order_Dir']) == 'asc') ? $order_property : '';

		//assign data to template
		$this->assignRef('CanTemplates', $perms->CanTemplates);
		$this->assignRef('count_filters', $count_filters);
		$this->assignRef('lists'	, $lists);
		$this->assignRef('rows'		, $rows);
		$this->assignRef('perms'	, $perms);
		$this->assignRef('orderingx'	, $orderingx);
		$this->assignRef('pagination'	, $pagination);
		$this->assignRef('user'				, $user);
		
		$this->assignRef('option', $option);
		$this->assignRef('view', $view);
		
		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
	}
}
?>
