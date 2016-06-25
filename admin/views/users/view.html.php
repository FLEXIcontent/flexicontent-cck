<?php
/**
 * @version 1.5 stable $Id: view.html.php 1902 2014-05-10 16:06:11Z ggppdk $ 
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
 * View class for the FLEXIcontent users screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewUsers extends JViewLegacy
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
		
		$model = $this->getModel();
		
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		
		
		// ***********
		// Get filters
		// ***********
		$count_filters = 0;
		
		// ordering filters
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');
		
		// pagination filters
		$limit      = $model->getState('limit');
		$limitstart = $model->getState('limitstart');
		
		// Various filters
		$filter_itemscount = $model->getState( 'filter_itemscount' );
		$filter_usergrp    = $model->getState( 'filter_usergrp' );
		$filter_logged     = $model->getState( 'filter_logged' );
		
		if ($filter_itemscount) $count_filters++;
		if ($filter_usergrp)    $count_filters++;
		if ($filter_logged)     $count_filters++;
		
		$date       = $model->getState( 'date' );
		$startdate  = $model->getState( 'startdate' );
		$enddate    = $model->getState( 'enddate' );
		
		$startdate = $db->escape( StringHelper::trim(StringHelper::strtolower( $startdate ) ) );
		$enddate   = $db->escape( StringHelper::trim(StringHelper::strtolower( $enddate ) ) );
		if ($startdate) $count_filters++;
		if ($enddate)   $count_filters++;
		
		// Author id filter
		$filter_id  = $model->getState( 'filter_id' );
		if (strlen($filter_id))  $count_filters++;
		
		// Text search
		$search = $model->getState( 'search' );
		$search = $db->escape( StringHelper::trim(StringHelper::strtolower( $search ) ) );
		
		
		
		// *******************
		// Get data from model
		// *******************
		
		$rows  = $model->getData();    // User data
		$total = $model->getTotal();   // Total rows
		$pagination = $model->getPagination();  // Pagination
		
		
		
		// ******************************************
		// Add usability notices if these are enabled
		// ******************************************
		
		$conf_link = '<a href="index.php?option=com_config&view=component&component=com_flexicontent&path=" class="btn btn-info btn-small">'.JText::_("FLEXI_CONFIG").'</a>';
		
		/*if ( $cparams->get('show_usability_messages', 1) )
		{
			$notice_author_with_items_only	= $app->getUserStateFromRequest( $option.'.users.notice_author_with_items_only',	'notice_author_with_items_only',	0, 'int' );
			
			if (!$notice_author_with_items_only)
			{
				$app->setUserState( $option.'.users.notice_author_with_items_only', 1 );
				JFactory::getDocument()->addStyleDeclaration("#system-message-container .alert.alert-info > .alert-heading { display:none; }");
				
				$disable_use_notices = '<span class="fc-nowrap-box fc-disable-notices-box">'. JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF_IN').' '.$conf_link.'</span><div class="fcclear"></div>';
				$app->enqueueMessage(JText::_('FLEXI_BY_DEFAULT_ONLY_AUTHORS_WITH_ITEMS_SHOWN') .' '. $disable_use_notices, 'notice');
			}
		}*/
		
		$this->minihelp = '
			<div id="fc-mini-help" class="fc-mssg fc-info" style="display:none;">
				'.JText::_('FLEXI_BY_DEFAULT_ONLY_AUTHORS_WITH_ITEMS_SHOWN') .'
			</div>
		';
		
		
		// **************************
		// Add css and js to document
		// **************************
		
		flexicontent_html::loadFramework('select2');
		JHTML::_('behavior.calendar');
		//JHTML::_('behavior.tooltip');
		
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH);
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH);
		
		$js = "jQuery(document).ready(function(){";
		if ($search)            $js .= "jQuery('.col_title').each(function(){ jQuery(this).addClass('yellow'); });";
		if ($filter_itemscount) $js .= "jQuery('.col_itemscount').each(function(){ jQuery(this).addClass('yellow'); });";
		if ($filter_usergrp)    $js .= "jQuery('.col_usergrp').each(function(){ jQuery(this).addClass('yellow'); });";
		if ($filter_logged)     $js .= "jQuery('.col_logged').each(function(){ jQuery(this).addClass('yellow'); });";
		if ($filter_id)         $js .= "jQuery('.col_id').each(function(){ jQuery(this).addClass('yellow'); });";
		if ($startdate || $enddate)
		{
			if ($date == 1) {
				$js .= "jQuery('.col_registered').each(function(){ jQuery(this).addClass('yellow'); });";
			} else if ($date == 2) {
				$js .= "jQuery('.col_visited').each(function(){ jQuery(this).addClass('yellow'); });";
			}
		}
		$js .= "});";
		$document->addScriptDeclaration($js);
		
		
		
		// *****************************
		// Get user's global permissions
		// *****************************
		
		$perms = FlexicontentHelperPerm::getPerm();
		
		
		
		// ************************
		// Create Submenu & Toolbar
		// ************************
		
		// Create Submenu (and also check access to current view)
		FLEXISubmenu('CanAuthors');
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_AUTHORS' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'authors' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		// Create the toolbar
		$this->setToolbar();

		
		// DB Query to get -mulitple- user group ids for all authors,
		// Get user-To-usergoup mapping for users in current page
		$user_ids = array();
		foreach ($rows as $row) {
			$row->usergroups = array();
			if ($row->id) $user_ids[] = $row->id;
		}
		$query = 'SELECT user_id, group_id FROM #__user_usergroup_map ' . (count($user_ids) ? 'WHERE user_id IN ('.implode(',',$user_ids).')'  :  '');
		$db->setQuery( $query );
		$ugdata_arr = $db->loadObjectList();
		
		foreach ($ugdata_arr as $ugdata) $usergroups[$ugdata->user_id][] = $ugdata->group_id;
		foreach ($rows as $row) if ($row->id) $row->usergroups = $usergroups[$row->id];
		
		
		// Get list of Groups for dropdown filter
		$query = 'SELECT *, id AS value, title AS text FROM #__usergroups';
		$db->setQuery( $query );
		$usergroups = $db->loadObjectList('id');
		
		
		$types[]		= JHTML::_('select.option',  '', '-' /*JText::_( 'Select Group' )*/ );
		foreach( $usergroups as $ugrp )
		{
			$types[]	= JHTML::_('select.option',  $ugrp->value, JText::_( $ugrp->text ) );
		}
		
		$itemscount_options[] = JHTML::_('select.option',  '', '-');
		$itemscount_options[] = JHTML::_('select.option',  1, JText::_( 'None' ) );
		$itemscount_options[] = JHTML::_('select.option',  2, JText::_( 'One or more' ) );
		$lists['filter_itemscount'] = ($filter_itemscount || 1 ? '<label class="label"># '.JText::_('FLEXI_ITEMS').'</label>' : '').
			JHTML::_('select.genericlist', $itemscount_options, 'filter_itemscount', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_itemscount );
		
		$lists['filter_usergrp'] = ($filter_usergrp || 1 ? '<label class="label">'.JText::_('Select Group').'</label>' : '').
			JHTML::_('select.genericlist', $types, 'filter_usergrp', 'class="use_select2_lib" style="width:auto;" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_usergrp );

		// get list of Log Status for dropdown filter
		$logged[] = JHTML::_('select.option',  '', '-' /*JText::_( 'Select Log Status' )*/);
		$logged[] = JHTML::_('select.option',  1, JText::_( 'Logged In' ) );
		$logged[] = JHTML::_('select.option',  2, JText::_( 'Logged Out' ) );
		$lists['filter_logged'] = ($filter_logged || 1 ? '<label class="label">'.JText::_('Log Status').'</label>' : '').
			JHTML::_('select.genericlist', $logged, 'filter_logged', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_logged );

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		// build dates option list
		$dates = array();
		$dates[] = JHTML::_('select.option',  '1', JText::_( 'Registered' ) );
		$dates[] = JHTML::_('select.option',  '2', JText::_( 'Last Visit' ) );
		
		$lists['date'] = '<label class="label">'.JText::_('FLEXI_DATE').'</label>'.
			JHTML::_('select.genericlist', $dates, 'date', 'size="1" class="use_select2_lib fc_skip_highlight"', 'value', 'text', $date, 'date' );
		
		$lists['startdate'] = JHTML::_('calendar', $startdate, 'startdate', 'startdate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'8',  'maxlength'=>'19', 'style'=>'width:auto', 'placeholder'=>JText::_('FLEXI_FROM')));
		$lists['enddate'] 	= JHTML::_('calendar', $enddate, 'enddate', 'enddate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'8',  'maxlength'=>'19', 'style'=>'width:auto', 'placeholder'=>JText::_('FLEXI_TO')));
		
		// search filter
		$lists['search']= $search;
		// search id
		$lists['filter_id'] = $filter_id;

		$this->assignRef('count_filters'	, $count_filters);
		$this->assignRef('lists'			, $lists);
		$this->assignRef('rows'				, $rows);
		$this->assignRef('usergroups'	,	$usergroups);
		$this->assignRef('pagination'	, $pagination);

		// filters
		$this->assignRef('filter_id'	, $filter_id);
		$this->assignRef('filter_itemscount', $filter_itemscount);
		$this->assignRef('filter_usergrp'		, $filter_usergrp);
		$this->assignRef('filter_logged'		, $filter_logged);
		$this->assignRef('search'			, $search);
		$this->assignRef('filter_id'	, $filter_id);
		$this->assignRef('date'				, $date);
		$this->assignRef('startdate'	, $startdate);
		$this->assignRef('enddate'		, $enddate);
		
		$this->assignRef('option'	, $option);
		$this->assignRef('view'		, $view);
		
		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
	}
	
	
	/**
	 * Method to configure the toolbar for this view.
	 *
	 * @access	public
	 * @return	void
	 */
	function setToolbar()
	{
		$document = JFactory::getDocument();
		$perms = FlexicontentHelperPerm::getPerm();
		$contrl = "users.";
		JToolBarHelper::custom( 'logout', 'cancel.png', 'cancel_f2.png', 'Logout' );
		
		//JToolBarHelper::addNew($contrl.'add');
		JText::script("FLEXI_UPDATING_CONTENTS", true);
		$document->addScriptDeclaration('
			function fc_edit_juser_modal_load( container ) {
				if ( container.find("iframe").get(0).contentWindow.location.href.indexOf("view=users") != -1 )
				{
					container.dialog("close");
				}
			}
			function fc_edit_juser_modal_close() {
				window.location.reload();
				document.body.innerHTML = Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif">\';
			}
		');
		
		$modal_title = JText::_('Add new Joomla user', true);
		$tip_class = ' hasTooltip';
		JToolBarHelper::divider();
		flexicontent_html::addToolBarButton(
			'FLEXI_NEW', $btn_name='add_juser', $full_js="var url = jQuery(this).attr('data-href'); var the_dialog = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, fc_edit_juser_modal_close, {title:'".$modal_title."', loadFunc: fc_edit_juser_modal_load}); return false;", $msg_alert='', $msg_confirm='',
			$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="btn btn-small btn-success".$tip_class, $btn_icon="icon-new icon-white",
			'data-placement="bottom" data-href="index.php?option=com_users&task=user.edit&id=0" title="Add new Joomla user"'
		);
		
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
		
		JToolBarHelper::divider(); JToolBarHelper::spacer();
		JToolBarHelper::help('JHELP_USERS_USER_MANAGER');
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolBarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
	}
}
