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
	function display( $tpl = null )
	{
		//initialise variables
		global $globalcats;
		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		$document	= JFactory::getDocument();
		
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		JHTML::_('behavior.tooltip');
		
		//get vars
		$order_property = !FLEXI_J16GE ? 'c.ordering' : 'c.lft';
		$filter_order     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order',     'filter_order',     $order_property, 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$filter_state     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_state',     'filter_state',     '', 'string' );
		$filter_cats      = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_cats',      'filter_cats',			 '', 'int' );
		$filter_level     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_level',     'filter_level',     '', 'string' );
		$filter_access    = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_access',    'filter_access',    '', 'string' );
		if (FLEXI_J16GE) {
			$filter_language	= $app->getUserStateFromRequest( $option.'.'.$view.'.filter_language',  'filter_language',  '', 'string' );
		}
		$search  = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 			'search', 			'', 'string' );
		$search  = FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search ) ) ) : $db->getEscaped( trim(JString::strtolower( $search ) ) );

		// Prepare the document: add css files, etc
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');
		
		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Create Submenu (and also check access to current view)
		FLEXISubmenu('CanCats');
		
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_CATEGORIES' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'fc_categories' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// ******************
		// Create the toolbar
		// ******************
		$js = "window.addEvent('domready', function(){";
		
		$contrl = FLEXI_J16GE ? "categories." : "";
		$contrl_singular = FLEXI_J16GE ? "category." : "";
		$toolbar = JToolBar::getInstance('toolbar');
		
		// Copy Parameters
		$btn_task = '';
		$popup_load_url = JURI::base().'index.php?option=com_flexicontent&view=categories&layout=params&tmpl=component';
		if (FLEXI_J16GE) {
			$js .= "
				$$('li#toolbar-params a.toolbar, #toolbar-params button')
					.set('onclick', 'javascript:;')
					.set('href', '". $popup_load_url ."')
					.set('rel', '{handler: \'iframe\', size: {x: 600, y: 440}, onClose: function() {}}');
			";
			JToolBarHelper::custom( $btn_task, 'params.png', 'params_f2.png', 'FLEXI_COPY_PARAMS', false );
			JHtml::_('behavior.modal', 'li#toolbar-params a.toolbar, #toolbar-params button');
		} else {
			$toolbar->appendButton('Popup', 'params', JText::_('FLEXI_COPY_PARAMS'), str_replace('&', '&amp;', $popup_load_url), 600, 440);
		}
		//if (FLEXI_J16GE)
		//	$toolbar->appendButton('Popup', 'move', JText::_('FLEXI_COPY_MOVE'), JURI::base().'index.php?option=com_flexicontent&amp;view=categories&amp;layout=batch&amp;tmpl=component', 800, 440);
		JToolBarHelper::divider();
		
		$add_divider = false;
		if ( !FLEXI_J16GE || $user->authorise('core.create', 'com_flexicontent') ) {
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
		if ( !FLEXI_J16GE || ( $user->authorise('core.edit', 'com_flexicontent') || $user->authorise('core.edit.own', 'com_flexicontent') ) ) {
			JToolBarHelper::editList($contrl_singular.'edit');
			$add_divider = true;
		}
		
		if ( FLEXI_J16GE && $user->authorise('core.admin', 'checkin') )
		{
			JToolBarHelper::checkin($contrl.'checkin');
			$add_divider = true;
		}
		if ($add_divider) JToolBarHelper::divider();
		
		$add_divider = false;
		if ( !FLEXI_J16GE || ( $user->authorise('core.edit.state', 'com_flexicontent') || $user->authorise('core.edit.state.own', 'com_flexicontent') ) ) {
			JToolBarHelper::publishList($contrl.'publish');
			JToolBarHelper::unpublishList($contrl.'unpublish');
			JToolBarHelper::divider();
			if (FLEXI_J16GE) JToolBarHelper::archiveList($contrl.'archive');
		}
		
		$add_divider = false;
		if ( !FLEXI_J16GE || ( $filter_state == -2 && $user->authorise('core.delete', 'com_flexicontent') ) ) {
			//JToolBarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
			// This will work in J2.5+ too and is offers more options (above a little bogus in J1.5, e.g. bad HTML id tag)
			$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE') );
			$msg_confirm = JText::_('FLEXI_ITEMS_DELETE_CONFIRM');
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
		
		
		//Get data from the model
		if ( $print_logging_info )  $start_microtime = microtime(true);
		if (FLEXI_J16GE) {
			$rows = $this->get( 'Items');
		} else {
			$rows = $this->get( 'Data');
		}
		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		// Get assigned items
		$model =  $this->getModel();
		$rowids = array();
		foreach ($rows as $row) $rowids[] = $row->id;
		if ( $print_logging_info )  $start_microtime = microtime(true);
		$rowtotals = $model->getAssignedItems($rowids);
		if ( $print_logging_info ) @$fc_run_times['execute_sec_queries'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		foreach ($rows as $row) {
			$row->nrassigned = isset($rowtotals[$row->id]) ? $rowtotals[$row->id]->nrassigned : 0;
		}
		
		// Parse configuration for every category
   	foreach ($rows as $cat)  $cat->config = FLEXI_J16GE ? new JRegistry($cat->config) : new JParameter($cat->config);
		
		if (FLEXI_J16GE) {
			$this->state = $this->get('State');
			// Preprocess the list of items to find ordering divisions.
			foreach ($rows as &$item) {
				$this->ordering[$item->parent_id][] = $item->id;
			}
		}
		$pagination 	= $this->get( 'Pagination' );
		
		$categories = & $globalcats;
		$lists['copyid'] = flexicontent_cats::buildcatselect($categories, 'copycid', '', 2, 'class="inputbox"', false, true, $actions_allowed=array('core.edit'));
		$lists['destid'] = flexicontent_cats::buildcatselect($categories, 'destcid[]', '', false, 'class="inputbox" size="15" multiple="true"', false, true, $actions_allowed=array('core.edit'));
		
		
		// *******************
		// Create Form Filters
		// *******************
		
		// filter by a category (it's subtree will be displayed)
		$categories = $globalcats;
		$lists['cats'] = flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, 2, 'class="inputbox" size="1" onchange="this.form.submit();"', $check_published=true, $check_perms=false);
		
		// filter depth level
		$options	= array();
		$options[]	= JHtml::_('select.option', '', JText::_( 'FLEXI_SELECT_MAX_DEPTH' ));
		for($i=1; $i<=10; $i++) $options[]	= JHtml::_('select.option', $i, $i);
		$fieldname =  $elementid = 'filter_level';
		$attribs = ' size="1" class="inputbox" onchange="this.form.submit();" ';
		$lists['level']	= JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_level, $elementid, $translate=true );
		
		// filter publication state
		if (FLEXI_J16GE)
		{
			$options = JHtml::_('jgrid.publishedOptions');
			array_unshift($options, JHtml::_('select.option', '', JText::_('JOPTION_SELECT_PUBLISHED')) );
			$fieldname =  $elementid = 'filter_state';
			$attribs = ' size="1" class="inputbox" onchange="Joomla.submitform()" ';
			$lists['state']	= JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_state, $elementid, $translate=true );
		} else {
			$lists['state']	= JHTML::_('grid.state', $filter_state );
		}
		
		if (FLEXI_J16GE)
		{
			// filter access level
			$options = JHtml::_('access.assetgroups');
			array_unshift($options, JHtml::_('select.option', '', JText::_('JOPTION_SELECT_ACCESS')) );
			$fieldname =  $elementid = 'filter_access';
			$attribs = ' size="1" class="inputbox" onchange="Joomla.submitform()" ';
			$lists['access']	= JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid, $translate=true );
			
			// filter language
			$lists['language'] = flexicontent_html::buildlanguageslist('filter_language', 'size="1" class="inputbox" onchange="submitform();"', $filter_language, 2);
		} else {
			// filter access level
			$options = array();
			$options[] = JHtml::_('select.option', '', JText::_('FLEXI_SELECT_ACCESS_LEVEL'));
			$options[] = JHtml::_('select.option', '0', JText::_('Public'));
			$options[] = JHtml::_('select.option', '1', JText::_('Registered'));
			$options[] = JHtml::_('select.option', '2', JText::_('SPECIAL'));
			$fieldname =  $elementid = 'filter_access';
			$attribs = ' size="1" class="inputbox" onchange="this.form.submit()" ';
			$lists['access']	= JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid, $translate=true );
		}
		
		// filter search word
		$lists['search']= $search;
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		$ordering = ($lists['order'] == $order_property) ? $order_property : '';

		//assign data to template
		$this->assignRef('lists'	, $lists);
		$this->assignRef('rows'		, $rows);
		$this->assignRef('perms'	, $perms);
		if (FLEXI_J16GE) {
			$this->assignRef('orderingx'	, $ordering);
		} else {
			$this->assignRef('ordering'		, $ordering);
		}
		$this->assignRef('pagination'	, $pagination);
		$this->assignRef('user'				, $user);

		parent::display($tpl);
	}
}
?>
